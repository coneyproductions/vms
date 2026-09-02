<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);

$plugin_root_env = getenv('BVMGR_TEST_PLUGIN_ROOT');
$plugin_root = is_string($plugin_root_env) && $plugin_root_env !== '' ? realpath($plugin_root_env) : dirname(__DIR__);
if (!is_string($plugin_root) || !is_dir($plugin_root)) {
    throw new RuntimeException('BVMGR_TEST_PLUGIN_ROOT must identify the exact plugin package under test.');
}

$GLOBALS['bvmgr_legacy_snapshot_test_meta'] = array();
$GLOBALS['bvmgr_legacy_snapshot_test_names'] = array();
$GLOBALS['bvmgr_legacy_snapshot_test_timezone'] = 'UTC';

function add_filter(...$args): bool
{
    return true;
}

function absint($value): int
{
    return abs((int) $value);
}

function sanitize_key($value): string
{
    return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', (string) $value));
}

function wc_get_order_item_meta(int $order_item_id, string $meta_key, bool $single = true)
{
    return $GLOBALS['bvmgr_legacy_snapshot_test_meta'][$order_item_id][$meta_key] ?? '';
}

function wp_timezone(): DateTimeZone
{
    return new DateTimeZone((string) $GLOBALS['bvmgr_legacy_snapshot_test_timezone']);
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
    $timestamp = $timestamp ?? time();
    $timezone = $timezone ?? wp_timezone();
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
}

final class WC_Order_Item_Product
{
    private int $item_id;

    public function __construct(int $item_id)
    {
        $this->item_id = $item_id;
    }

    public function get_name(): string
    {
        return (string) ($GLOBALS['bvmgr_legacy_snapshot_test_names'][$this->item_id] ?? '');
    }
}

require_once $plugin_root . '/includes/core/event-reschedule.php';
require_once $plugin_root . '/includes/core/ticket-revenue.php';

if (!function_exists('bvmgr_event_occurrence_normalize_snapshot_date') && function_exists('vms_event_occurrence_normalize_snapshot_date')) {
    function bvmgr_event_occurrence_normalize_snapshot_date(string $value): string
    {
        return vms_event_occurrence_normalize_snapshot_date($value);
    }

    function bvmgr_event_occurrence_snapshot_date(int $order_item_id): string
    {
        return vms_event_occurrence_snapshot_date($order_item_id);
    }

    function bvmgr_event_occurrence_order_item_name_date(int $order_item_id): string
    {
        return vms_event_occurrence_order_item_name_date($order_item_id);
    }

    function bvmgr_ticket_revenue_normalize_ymd($value): string
    {
        return vms_ticket_revenue_normalize_ymd($value);
    }
}

$assertions = 0;
$assert_same = static function (string $expected, string $actual, string $message) use (&$assertions): void {
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException(
            'Assertion ' . $assertions . ' failed: ' . $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
        );
    }
};

$assert_same('2026-09-12', bvmgr_event_occurrence_normalize_snapshot_date('2026-09-12'), 'ISO snapshots must remain supported.');
$assert_same('2026-09-12', bvmgr_event_occurrence_normalize_snapshot_date('Sep 12, 2026'), 'Legacy September 12 must normalize as a calendar date.');
$assert_same('2026-09-19', bvmgr_event_occurrence_normalize_snapshot_date('Sep 19, 2026'), 'Legacy September 19 must normalize as a calendar date.');
$assert_same('2024-02-29', bvmgr_event_occurrence_normalize_snapshot_date('Feb 29, 2024'), 'Valid leap day must normalize.');
$assert_same('', bvmgr_event_occurrence_normalize_snapshot_date('Feb 29, 2023'), 'Impossible legacy dates must fail closed.');
$assert_same('', bvmgr_event_occurrence_normalize_snapshot_date('2026-02-30'), 'Impossible ISO dates must fail closed.');
$assert_same('', bvmgr_event_occurrence_normalize_snapshot_date('09/12/2026'), 'Locale-ambiguous numeric dates must fail closed.');
$assert_same('', bvmgr_event_occurrence_normalize_snapshot_date('September 12, 2026'), 'Unsupported long month names must fail closed.');
$assert_same('', bvmgr_event_occurrence_normalize_snapshot_date('the second Saturday in September'), 'Arbitrary prose must fail closed.');
$assert_same('', bvmgr_event_occurrence_normalize_snapshot_date(''), 'Empty snapshots must remain empty.');

$original_runtime_timezone = date_default_timezone_get();
try {
    foreach (array('UTC', 'America/Chicago', 'Pacific/Kiritimati', 'Pacific/Honolulu') as $wordpress_timezone) {
        $GLOBALS['bvmgr_legacy_snapshot_test_timezone'] = $wordpress_timezone;
        foreach (array('UTC', 'America/Chicago', 'Asia/Tokyo', 'Pacific/Honolulu') as $server_timezone) {
            date_default_timezone_set($server_timezone);
            $context = $wordpress_timezone . ' / ' . $server_timezone;
            $assert_same('2026-09-12', bvmgr_event_occurrence_normalize_snapshot_date('Sep 12, 2026'), 'Strict date-only normalization shifted under ' . $context . '.');
            $assert_same('2026-09-12', bvmgr_ticket_revenue_normalize_ymd('Sep 12, 2026'), 'Resolver diagnostic normalization shifted under ' . $context . '.');
        }
    }
} finally {
    date_default_timezone_set($original_runtime_timezone);
    $GLOBALS['bvmgr_legacy_snapshot_test_timezone'] = 'UTC';
}

$GLOBALS['bvmgr_legacy_snapshot_test_meta'][101] = array(
    '_vms_effective_event_start_local' => '2026-09-19 19:00:00',
    '_vms_event_date_snapshot' => 'Sep 12, 2026',
    '_vms_event_when_snapshot' => 'Sat, Sep 12, 2026 7:00pm',
);
$assert_same('2026-09-19', bvmgr_event_occurrence_snapshot_date(101), 'Effective occurrence precedence must remain unchanged.');

$GLOBALS['bvmgr_legacy_snapshot_test_meta'][102] = array(
    '_vms_event_date_snapshot' => 'unsupported snapshot',
    '_vms_event_when_snapshot' => 'Sat, Sep 19, 2026 7:00pm',
);
$assert_same('2026-09-19', bvmgr_event_occurrence_snapshot_date(102), 'Existing When fallback must remain available.');

$GLOBALS['bvmgr_legacy_snapshot_test_meta'][103] = array(
    '_vms_event_date_snapshot' => 'Sep 12, 2026',
);
$assert_same('2026-09-12', bvmgr_event_occurrence_snapshot_date(103), 'Canonical snapshot resolution must recognize the legacy production shape.');

$GLOBALS['bvmgr_legacy_snapshot_test_meta'][104] = array(
    '_vms_event_date_snapshot' => 'Sep 31, 2026',
);
$assert_same('', bvmgr_event_occurrence_snapshot_date(104), 'Canonical snapshot resolution must reject impossible legacy values.');

$GLOBALS['bvmgr_legacy_snapshot_test_names'][201] = '2026-09-12 19:00 - General Admission';
$GLOBALS['bvmgr_legacy_snapshot_test_names'][202] = 'General Admission (Sep 12, 2026)';
$GLOBALS['bvmgr_legacy_snapshot_test_names'][203] = 'General Admission (Feb 29, 2023)';
$assert_same('2026-09-12', bvmgr_event_occurrence_order_item_name_date(201), 'ISO retained line names must remain supported.');
$assert_same('2026-09-12', bvmgr_event_occurrence_order_item_name_date(202), 'Legacy retained line-name dates must use strict normalization.');
$assert_same('', bvmgr_event_occurrence_order_item_name_date(203), 'Impossible retained line-name dates must fail closed.');

fwrite(STDOUT, 'PASS: ' . $assertions . " legacy occurrence snapshot normalization assertions.\n");
