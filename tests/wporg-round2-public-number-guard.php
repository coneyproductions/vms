<?php
/** Public guard registration and foreign-input isolation; no WordPress or network. */
define('ABSPATH', __DIR__);
define('BVMGR_PLUGIN_URL', 'https://example.invalid/plugin/');
define('BVMGR_VERSION', 'fixture');
$GLOBALS['bvmgr_test_hooks'] = $GLOBALS['bvmgr_test_scripts'] = $GLOBALS['bvmgr_test_enqueued'] = array();
function add_action($hook, $callback, ...$rest) { $GLOBALS['bvmgr_test_hooks'][$hook][] = $callback; }
function add_filter(...$args) {}
function wp_enqueue_style($handle, ...$args) { $GLOBALS['bvmgr_test_style_queue'][] = $handle; }
function wp_register_style($handle, $url, $deps, ...$args) { $GLOBALS['bvmgr_test_style_registry'][$handle] = $deps; }
function wp_register_script($handle, ...$args) { $GLOBALS['bvmgr_test_scripts'][] = $handle; }
function wp_enqueue_script($handle, ...$args) { $GLOBALS['bvmgr_test_enqueued'][] = $handle; }
function apply_filters($hook, $value) { return $GLOBALS['bvmgr_test_portal_override'] ?? $value; }
class WP_Post { public string $post_content = ''; }
function get_queried_object() { return $GLOBALS['bvmgr_test_queried'] ?? null; }
function has_shortcode($content, $tag) { return strpos($content, '[' . $tag . ']') !== false; }
require dirname(__DIR__) . '/includes/core/plugin.php';
foreach ($GLOBALS['bvmgr_test_hooks']['wp_enqueue_scripts'] as $callback) $callback();
if (!in_array('bvmgr-number-input-guard', $GLOBALS['bvmgr_test_scripts'], true) || in_array('bvmgr-number-input-guard', $GLOBALS['bvmgr_test_enqueued'], true)) throw new RuntimeException('Unrelated public pages must only register the guard.');
$checks = 0;
foreach (array('', '[foreign_form]', '[vms_vendor_portal]', '[vms_staff_portal]') as $content) {
    $post = new WP_Post(); $post->post_content = $content; $GLOBALS['bvmgr_test_queried'] = $post;
    $GLOBALS['bvmgr_test_style_queue'] = array();
    foreach ($GLOBALS['bvmgr_test_hooks']['wp_enqueue_scripts'] as $callback) $callback();
    $expected = in_array($content, array('[vms_vendor_portal]', '[vms_staff_portal]'), true) ? array('bvmgr-portal') : array();
    if ($GLOBALS['bvmgr_test_style_queue'] !== $expected) throw new RuntimeException('Styles loaded without a BVM consumer or portal lost its styles');
    if ($GLOBALS['bvmgr_test_style_registry'] !== array('bvmgr-shared'=>array(), 'bvmgr-ui'=>array('bvmgr-shared'), 'bvmgr-portal'=>array('bvmgr-ui'))) throw new RuntimeException('Shared dependency chain changed');
    $checks++;
}
$GLOBALS['bvmgr_test_portal_override'] = false; $GLOBALS['bvmgr_test_style_queue'] = array();
foreach ($GLOBALS['bvmgr_test_hooks']['wp_enqueue_scripts'] as $callback) $callback();
if ($GLOBALS['bvmgr_test_style_queue']) throw new RuntimeException('Explicit portal filter opt-out ignored');
$GLOBALS['bvmgr_test_queried'] = null; $GLOBALS['bvmgr_test_portal_override'] = true;
foreach ($GLOBALS['bvmgr_test_hooks']['wp_enqueue_scripts'] as $callback) $callback();
if ($GLOBALS['bvmgr_test_style_queue'] !== array('bvmgr-portal')) throw new RuntimeException('Explicit template integration opt-in ignored');
echo 'PASS ' . ($checks + 2) . " public stylesheet scope/dependency/override checks\n";
foreach (array('includes/portal/vendor-portal.php', 'includes/portal/staff-portal.php', 'includes/modules/admissions/pass-claims.php') as $file) {
    if (!str_contains(file_get_contents(dirname(__DIR__) . '/' . $file), "wp_enqueue_script('bvmgr-number-input-guard'")) throw new RuntimeException('BVM form renderer must own the guard: ' . $file);
}
$command = 'node ' . escapeshellarg(__DIR__ . '/helpers/round2-number-input-guard.mjs');
passthru($command, $status);
if ($status) throw new RuntimeException('Actual JavaScript input-boundary assertions failed.');
echo "PASS public registration only; BVM renderers own enqueueing\n";
