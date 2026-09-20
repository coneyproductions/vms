<?php
/** Isolated activation-option spies; never boots WordPress. */
define('ABSPATH', '/synthetic/wordpress/');
function plugin_basename($file) { return 'relocated-bvm/' . basename($file); }
function get_option($key, $default = false) { return $GLOBALS['site_options'][$key] ?? $default; }
function get_site_option($key, $default = false) { return $GLOBALS['network_options'][$key] ?? $default; }
function update_option(...$args) { throw new RuntimeException('Unauthorized site option write'); }
function update_site_option(...$args) { throw new RuntimeException('Unauthorized network option write'); }
function is_multisite() { return $GLOBALS['multisite']; }
function add_action(...$args) { $GLOBALS['hooks'][] = $args; }
function register_activation_hook(...$args) { $GLOBALS['activation'][] = $args; }
$root = dirname(__DIR__);
require $root . '/includes/plugin-basename-compat.php';
if (function_exists('bvmgr_migrate_legacy_plugin_basename')) throw new RuntimeException('Prohibited migration still ships');
$main = file_get_contents($root . '/backstage-venue-manager.php');
$guard = substr($main, strpos($main, '// Detect incompatible'), strpos($main, "define('BVMGR_PLUGIN_FILE'") - strpos($main, '// Detect incompatible'));
$count = 0;
foreach (array(false, true) as $network) {
    foreach (array('other/other.php' => false, 'vms/vendor-management-system.php' => true, 'other-bvm/backstage-venue-manager.php' => true, 'relocated-bvm/vendor-management-system.php' => false) as $basename => $conflict) {
        $GLOBALS['multisite'] = $network;
        $GLOBALS['site_options'] = array('active_plugins' => $network ? array() : array($basename));
        $GLOBALS['network_options'] = array('active_sitewide_plugins' => $network ? array($basename => 12345) : array());
        $before = serialize(array($GLOBALS['site_options'], $GLOBALS['network_options']));
        $GLOBALS['activation'] = array(); $GLOBALS['hooks'] = array();
        eval($guard);
        if ((count($GLOBALS['activation']) > 0) !== $conflict) throw new RuntimeException('Wrong conflict outcome: ' . $basename);
        if (serialize(array($GLOBALS['site_options'], $GLOBALS['network_options'])) !== $before) throw new RuntimeException('Activation state changed');
        $count++;
    }
}
echo "PASS $count single-site/network activation-state scenarios; zero option writes\n";
