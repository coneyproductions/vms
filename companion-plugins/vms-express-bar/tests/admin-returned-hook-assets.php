<?php
declare(strict_types=1);

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

define('ABSPATH', __DIR__ . '/');
define('VMSEB_URL', 'https://example.test/wp-content/plugins/vms-express-bar/');
define('VMSEB_VERSION', 'test');

$GLOBALS['vmseb_hook_test'] = array(
    'actions' => array(),
    'filters' => array(),
    'menu_calls' => array(),
    'returned_hooks' => array(
        'vms-express-bar' => 'backstage-venue-manager_page_vms-express-bar',
        'vms-bar-menu' => 'backstage-venue-manager_page_vms-bar-menu',
    ),
    'styles' => array(),
    'scripts' => array(),
    'localized' => array(),
);

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
    $GLOBALS['vmseb_hook_test']['actions'][] = array($hook, $callback, $priority, $accepted_args);
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
    $GLOBALS['vmseb_hook_test']['filters'][] = array($hook, $callback, $priority, $accepted_args);
}

function __(string $text, string $domain = ''): string
{
    unset($domain);
    return $text;
}

function sanitize_key($value): string
{
    $sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
    return is_string($sanitized) ? $sanitized : '';
}

function add_submenu_page(
    string $parent_slug,
    string $page_title,
    string $menu_title,
    string $capability,
    string $menu_slug,
    $callback = ''
) {
    $GLOBALS['vmseb_hook_test']['menu_calls'][] = array(
        'parent' => $parent_slug,
        'page_title' => $page_title,
        'menu_title' => $menu_title,
        'capability' => $capability,
        'slug' => $menu_slug,
        'callback' => $callback,
    );
    return $GLOBALS['vmseb_hook_test']['returned_hooks'][$menu_slug] ?? false;
}

function wp_enqueue_style(string $handle, string $source, array $dependencies = array(), $version = false): void
{
    $GLOBALS['vmseb_hook_test']['styles'][] = array($handle, $source, $dependencies, $version);
}

function wp_enqueue_script(string $handle, string $source, array $dependencies = array(), $version = false, bool $footer = false): void
{
    $GLOBALS['vmseb_hook_test']['scripts'][] = array($handle, $source, $dependencies, $version, $footer);
}

function wp_localize_script(string $handle, string $object_name, array $data): void
{
    $GLOBALS['vmseb_hook_test']['localized'][] = array($handle, $object_name, $data);
}

function admin_url(string $path = ''): string
{
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_create_nonce(string $action): string
{
    return 'nonce-' . $action;
}

function vmseb_parent_menu_slug(): string
{
    return 'vms-dashboard';
}

require dirname(__DIR__) . '/includes/admin.php';

$assert(function_exists('vmseb_admin_page_hooks'), 'Express Bar must expose returned submenu hooks.');
$assert(function_exists('vmseb_admin_menu'), 'Express Bar must expose submenu registration.');
$assert(function_exists('vmseb_admin_enqueue_assets'), 'Express Bar must expose page-scoped asset loading.');

vmseb_admin_menu();

$hooks = vmseb_admin_page_hooks();
$assert(($hooks['vms-express-bar'] ?? '') === 'backstage-venue-manager_page_vms-express-bar', 'Express Bar must store WordPress\'s returned queue-page hook.');
$assert(($hooks['vms-bar-menu'] ?? '') === 'backstage-venue-manager_page_vms-bar-menu', 'Express Bar must store WordPress\'s returned menu-page hook.');
$assert(count($GLOBALS['vmseb_hook_test']['menu_calls']) === 2, 'Express Bar must register exactly two submenus.');
foreach ($GLOBALS['vmseb_hook_test']['menu_calls'] as $call) {
    $assert(($call['parent'] ?? '') === 'vms-dashboard', 'Express Bar pages must remain under the BVM parent.');
    $assert(($call['capability'] ?? '') === 'manage_options', 'Express Bar page capability must remain manage_options.');
}

$asset_count = static function (): array {
    return array(
        count($GLOBALS['vmseb_hook_test']['styles']),
        count($GLOBALS['vmseb_hook_test']['scripts']),
        count($GLOBALS['vmseb_hook_test']['localized']),
    );
};

vmseb_admin_enqueue_assets('backstage-venue-manager_page_vms-express-bar');
$assert($asset_count() === array(1, 1, 1), 'Queue-page hook must enqueue one stylesheet, script, and localization payload.');

vmseb_admin_enqueue_assets('backstage-venue-manager_page_vms-bar-menu');
$assert($asset_count() === array(2, 2, 2), 'Bar Menu hook must enqueue its stylesheet, script, and localization payload.');

vmseb_admin_enqueue_assets('dashboard');
$assert($asset_count() === array(2, 2, 2), 'Unrelated admin pages must not receive Express Bar assets.');

vmseb_admin_enqueue_assets('vms_page_vms-express-bar');
$assert($asset_count() === array(2, 2, 2), 'Reconstructed legacy hook names must not bypass WordPress\'s returned hooks.');

$GLOBALS['vmseb_hook_test']['returned_hooks'] = array(
    'vms-express-bar' => 'vms_page_vms-express-bar',
    'vms-bar-menu' => 'vms_page_vms-bar-menu',
);
$GLOBALS['vmseb_hook_test']['styles'] = array();
$GLOBALS['vmseb_hook_test']['scripts'] = array();
$GLOBALS['vmseb_hook_test']['localized'] = array();
vmseb_admin_menu();
vmseb_admin_enqueue_assets('vms_page_vms-express-bar');
vmseb_admin_enqueue_assets('vms_page_vms-bar-menu');
$assert($asset_count() === array(2, 2, 2), 'Historical parent hooks must remain compatible when WordPress actually returns them.');

if ($failures !== array()) {
    fwrite(STDERR, "Express Bar returned-hook asset failures:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Express Bar returned-hook assets passed.\n";
