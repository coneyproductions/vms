<?php

declare(strict_types=1);

$scenario = $argv[1] ?? '';
if (!in_array($scenario, array('modern', 'legacy', 'post-type', 'absent'), true)) {
    fwrite(STDERR, "Usage: php tests/bvm-public-runtime-compatibility.php <modern|legacy|post-type|absent>\n");
    exit(2);
}

define('ABSPATH', __DIR__ . '/');

final class WP_Post
{
    public int $ID = 6540;
    public string $post_type = 'tribe_events';
}

function post_type_exists(string $post_type): bool
{
    global $scenario;
    return $post_type === 'vms_event_plan' && $scenario === 'post-type';
}

if ($scenario === 'modern') {
    define('BVMGR_PLUGIN_FILE', '/plugins/backstage-venue-manager/backstage-venue-manager.php');
    define('BVMGR_VERSION', '1.2.0');

    function bvmgr_get_event_plan_for_tec_event(int $event_id): int
    {
        return $event_id === 6540 ? 2534 : 0;
    }

    function vms_get_event_plan_for_tec_event(int $event_id): int
    {
        return $event_id === 6540 ? 9999 : 0;
    }
} elseif ($scenario === 'legacy') {
    define('VMS_PLUGIN_FILE', '/plugins/vms/vendor-management-system.php');
    define('VMS_VERSION', '1.1.0');

    function vms_get_event_plan_for_tec_event(int $event_id): int
    {
        return $event_id === 6540 ? 2534 : 0;
    }
}

require dirname(__DIR__) . '/includes/helpers.php';

function vmseb_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$expected_active = $scenario !== 'absent';
vmseb_test_assert(vmseb_is_vms_active() === $expected_active, 'dependency detection did not match the scenario');

if (in_array($scenario, array('modern', 'legacy'), true)) {
    vmseb_test_assert(vmseb_resolve_event_plan_id_from_post(new WP_Post()) === 2534, 'TEC event did not resolve to the expected Event Plan');
}

fwrite(STDOUT, "PASS: {$scenario}\n");
