<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('BVMGR_VERSION', '1.2.0');
define('VMS_PLUGIN_FILE', '/example/legacy-vms/vendor-management-system.php');

$GLOBALS['backstage_outreach_legacy_guard_actions'] = array();

function __($text, $domain = ''): string { return (string) $text; }
function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
function plugin_dir_url(string $file): string { return 'https://example.invalid/wp-content/plugins/backstage-outreach/'; }
function register_activation_hook(string $file, string $callback): void {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool { $GLOBALS['backstage_outreach_legacy_guard_actions'][] = array($hook, $callback, $priority, $accepted_args); return true; }
function bvmgr_register_admin_page(array $page): bool { return true; }

require_once dirname(__DIR__) . '/companion-plugins/backstage-outreach/backstage-outreach.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(str_contains(backstage_outreach_dependency_error(), 'cannot run while the legacy VMS plugin is active'), 'The companion must report the legacy VMS conflict explicitly.');
backstage_outreach_boot();
$assert(!function_exists('vms_outreach_module_boot'), 'The legacy VMS conflict must prevent all recovered module code from loading.');
$assert(in_array('admin_notices', array_column($GLOBALS['backstage_outreach_legacy_guard_actions'], 0), true), 'The legacy VMS conflict must register an administrator-facing notice.');

echo 'Backstage Outreach legacy-VMS guard OK (' . $assertions . " assertions).\n";
