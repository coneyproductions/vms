<?php

declare(strict_types=1);

$scenario = $argv[1] ?? '';
if (!in_array($scenario, array('modern', 'legacy', 'meta'), true)) {
    fwrite(STDERR, "Usage: php tests/public-cache-invalidation.php <modern|legacy|meta>\n");
    exit(2);
}

define('ABSPATH', __DIR__ . '/');
define('VMSEB_VERSION', '0.6.24');

$stored_options = array('vmseb_public_cache_version' => '0.6.23');
$cleaned_post_ids = array();
$pruned_cache_dirs = array();
$actions = array();
$get_posts_args = array();

function absint($value): int
{
    return abs((int) $value);
}

function get_option(string $name, $default = false)
{
    global $stored_options;
    return array_key_exists($name, $stored_options) ? $stored_options[$name] : $default;
}

function update_option(string $name, $value, bool $autoload = false): bool
{
    global $stored_options;
    unset($autoload);
    $stored_options[$name] = $value;
    return true;
}

function get_posts(array $args): array
{
    global $get_posts_args;
    $get_posts_args = $args;
    return array(2534, 2600, 2700);
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
    unset($single);
    $meta = array(
        2534 => array(
            '_vms_express_bar_enabled' => '1',
            '_vmseb_auto_embed' => '1',
            '_vms_tec_event_id' => '6540',
        ),
        2600 => array(
            '_vms_express_bar_enabled' => '0',
            '_vmseb_auto_embed' => '1',
            '_vms_tec_event_id' => '6600',
        ),
        2700 => array(
            '_vms_express_bar_enabled' => '1',
            '_vmseb_auto_embed' => '0',
            '_vms_tec_event_id' => '6700',
        ),
    );
    return $meta[$post_id][$key] ?? '';
}

function vmseb_find_public_page_id(bool $allow_content_scan = false): int
{
    unset($allow_content_scan);
    return 15;
}

function clean_post_cache(int $post_id): void
{
    global $cleaned_post_ids;
    $cleaned_post_ids[] = $post_id;
}

if ($scenario === 'modern') {
    function get_current_url_supercache_dir(int $post_id): string
    {
        return '/cache/post-' . $post_id;
    }

    function prune_super_cache(string $cache_dir, bool $force = false): bool
    {
        global $pruned_cache_dirs;
        $pruned_cache_dirs[] = array($cache_dir, $force);
        return true;
    }
}

function do_action(string $hook, ...$args): void
{
    global $actions;
    $actions[] = array($hook, $args);
}

if ($scenario === 'modern') {
    function bvmgr_get_plan_tec_event_id(int $event_plan_id): int
    {
        return $event_plan_id === 2534 ? 6540 : 0;
    }

    function vms_get_plan_tec_event_id(int $event_plan_id): int
    {
        return $event_plan_id === 2534 ? 9999 : 0;
    }
} elseif ($scenario === 'legacy') {
    function vms_get_plan_tec_event_id(int $event_plan_id): int
    {
        return $event_plan_id === 2534 ? 6540 : 0;
    }
}

require dirname(__DIR__) . '/includes/cache.php';

function vmseb_cache_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

vmseb_cache_test_assert(vmseb_get_linked_tec_event_id_for_cache(2534) === 6540, 'linked TEC event resolution failed');
vmseb_cache_test_assert(vmseb_cache_sensitive_event_plan_ids() === array(2534), 'cache-sensitive Event Plan filtering failed');
vmseb_cache_test_assert(($get_posts_args['meta_key'] ?? '') === '_vms_express_bar_enabled' && ($get_posts_args['meta_value'] ?? '') === '1', 'version migration did not query only Express Bar-enabled plans');

vmseb_maybe_invalidate_public_cache_on_version_change();
$expected_post_ids = array(2534, 6540, 15);
if ($scenario === 'modern') {
    vmseb_cache_test_assert($cleaned_post_ids === array(), 'WP Super Cache exact purge unexpectedly used the generic post-cache path');
    vmseb_cache_test_assert($pruned_cache_dirs === array(
        array('/cache/post-2534', true),
        array('/cache/post-6540', true),
        array('/cache/post-15', true),
    ), 'version change did not force-delete the exact WP Super Cache page directories');
} else {
    vmseb_cache_test_assert($cleaned_post_ids === $expected_post_ids, 'version change did not invalidate exactly the eligible Event Plan, linked event, and public page');
}
vmseb_cache_test_assert($stored_options['vmseb_public_cache_version'] === '0.6.24', 'cache version marker was not advanced');

vmseb_maybe_invalidate_public_cache_on_version_change();
if ($scenario === 'modern') {
    vmseb_cache_test_assert(count($pruned_cache_dirs) === 3, 'matching cache version invalidated pages again');
} else {
    vmseb_cache_test_assert($cleaned_post_ids === $expected_post_ids, 'matching cache version invalidated pages again');
}

vmseb_invalidate_event_plan_public_cache(2534);
if ($scenario === 'modern') {
    vmseb_cache_test_assert(count($pruned_cache_dirs) === 6, 'event configuration invalidation did not cover all WP Super Cache surfaces');
} else {
    vmseb_cache_test_assert($cleaned_post_ids === array(2534, 6540, 15, 2534, 6540, 15), 'event configuration invalidation did not cover all public surfaces');
}

$admin_source = file_get_contents(dirname(__DIR__) . '/includes/admin.php');
$bootstrap_source = file_get_contents(dirname(__DIR__) . '/includes/bootstrap.php');
vmseb_cache_test_assert(is_string($admin_source) && strpos($admin_source, 'vmseb_invalidate_event_plan_public_cache($post_id);') !== false, 'Event Plan save is not wired to cache invalidation');
vmseb_cache_test_assert(is_string($admin_source) && substr_count($admin_source, 'vmseb_invalidate_dedicated_public_cache();') >= 3, 'Express Bar settings are not wired to dedicated-page invalidation');
vmseb_cache_test_assert(is_string($bootstrap_source) && strpos($bootstrap_source, "add_action('init', 'vmseb_maybe_invalidate_public_cache_on_version_change', 99);") !== false, 'version-change cache invalidation hook is missing');

fwrite(STDOUT, "PASS: {$scenario}\n");
