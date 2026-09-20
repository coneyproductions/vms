<?php
/** Exercise the actual service initialization without changing third-party script state. */
define('ABSPATH', __DIR__);
$GLOBALS['bvmgr_test_hooks'] = array();
function add_action($hook, $callback, ...$args) { $GLOBALS['bvmgr_test_hooks'][$hook][] = $callback; }
function apply_filters($hook, $value) { return $hook === 'vms_register_tours' ? array(array('id' => 'legacy_fixture')) : $value; }
function wp_dequeue_script($handle) { throw new RuntimeException('Foreign script dequeued: ' . $handle); }
function wp_deregister_script($handle) { throw new RuntimeException('Foreign script deregistered: ' . $handle); }
foreach (array('screen', 'storage', 'registry', 'compat', 'admin', 'service') as $part) require dirname(__DIR__) . '/includes/tours/class-vms-tours-' . $part . '.php';
BVMGR_Tours_Service::instance();
if (!empty($GLOBALS['bvmgr_test_hooks']['wp_print_scripts'])) throw new RuntimeException('Tours registered a global script-removal callback');
if (empty($GLOBALS['bvmgr_test_hooks']['admin_enqueue_scripts']) || empty($GLOBALS['bvmgr_test_hooks']['wp_enqueue_scripts'])) throw new RuntimeException('Own tours enqueue hooks lost');
$compat = new BVMGR_Tours_Compat(new BVMGR_Tours_Screen());
$compat->init();
$compat->deregister_legacy_scripts();
if ($compat->collect_legacy_tours() !== array(array('id' => 'legacy_fixture'))) throw new RuntimeException('Legacy tour definition collection changed');
foreach (array('compat', 'service') as $part) {
    $source = file_get_contents(dirname(__DIR__) . '/includes/tours/class-vms-tours-' . $part . '.php');
    if (preg_match('/wp_(?:dequeue|deregister)_script\s*\(/', $source)) throw new RuntimeException('Tours still remove scripts');
}
echo "PASS tour initialization, own enqueue hooks, legacy collection, and no foreign script removal\n";
