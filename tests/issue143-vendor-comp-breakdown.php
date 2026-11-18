<?php
/**
 * #143: focused synthetic regression test for the recovered complimentary detail.
 *
 * Run: php tests/issue143-vendor-comp-breakdown.php
 * No database, WordPress install, tickets, or payments are changed.
 */
declare(strict_types=1);

$plugin_root = $argv[1] ?? dirname(__DIR__) . '/backstage-venue-manager';
$source = file_get_contents($plugin_root . '/includes/portal/vendor-portal.php');
if ($source === false) {
    throw new RuntimeException('Vendor Portal source not found.');
}

function issue143_same($expected, $actual, string $description): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($description . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function issue143_true(bool $result, string $description): void
{
    if (!$result) {
        throw new RuntimeException($description);
    }
}

define('ABSPATH', rtrim($plugin_root, '/') . '/');
require ABSPATH . 'includes/core/reporting-providers.php';

function esc_html__(string $value, string $domain = ''): string { return esc_html(__($value, $domain)); }
function _n(string $one, string $many, int $qty, string $domain = ''): string { return $qty === 1 ? $one : $many; }
function esc_attr($value): string { return esc_html($value); }
function bvmgr_vendor_portal_format_money(float $amount): string { return '$' . number_format($amount, 2); }
if (getenv('ISSUE143_WP_KSES')) {
    function apply_filters($name, $value) { return $value; }
    function did_action($name): int { return 0; }
    function wp_allowed_protocols(): array { return ['http', 'https']; }
    $wp_includes = dirname(getenv('ISSUE143_WP_KSES'));
    foreach (array('compat-utf8.php', 'utf8.php', 'class-wp-token-map.php') as $file) {
        require $wp_includes . '/' . $file;
    }
    foreach (glob($wp_includes . '/html-api/*.php') as $file) {
        if (basename($file) !== 'class-wp-html-processor.php') {
            require $file;
        }
    }
    require getenv('ISSUE143_WP_KSES');
} else {
    function wp_kses_post($value): string { return $value; }
}
function absint($value): int { return abs((int) $value); }
function __(string $value, string $domain = ''): string { return $value; }
function sanitize_key(string $value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? ''; }
function wp_strip_all_tags(string $value): string { return strip_tags($value); }
function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function number_format_i18n($value): string { return number_format((int) $value); }
function bvmgr_vendor_portal_get_guest_admissions_count(int $plan_id): int
{
    return (int) ($GLOBALS['issue143_guest_counts'][$plan_id] ?? 0);
}

// Load the real snapshot and rendering functions without starting WordPress.
foreach (array(
    'bvmgr_vendor_portal_get_data_tools_sales_snapshot',
    'bvmgr_vendor_portal_get_count_breakdown',
    'bvmgr_vendor_portal_render_count_breakdown_markup',
    'bvmgr_vendor_portal_render_progress_cards_section',
) as $function_name) {
    $marker = "if (!function_exists('" . $function_name . "')) {";
    $start = strpos($source, $marker);
    issue143_true($start !== false, 'Missing guarded function: ' . $function_name);
    $end = strpos($source, "\nif (!function_exists(", $start + strlen($marker));
    issue143_true($end !== false, 'Unable to isolate guarded function: ' . $function_name);
    eval(substr($source, $start, $end - $start));
}

$GLOBALS['issue143_guest_counts'] = array(5001 => 4, 5002 => 2, 5003 => 1, 5004 => 0, 5005 => 0);

$upcoming_provider = array(
        'available' => true,
        'calculated' => true,
        'has_countable_data' => true,
        'online_qty' => 139,
        'door_paid_qty' => 0,
        'excluded_free_online_qty' => 28,
        'door_free_qty' => 0,
        'complimentary_categories' => array(
            array('key' => 'veterans', 'label' => 'Veterans', 'qty' => 14),
            array('key' => 'children', 'label' => 'Children 12 & under', 'qty' => 7),
            array('key' => 'responders', 'label' => 'Police / Fire / EMT', 'qty' => 2),
            array('key' => 'teachers', 'label' => 'Public School Teacher', 'qty' => 5),
        ),
);
issue143_true(bvmgr_reporting_register_provider(array(
    'id' => 'synthetic',
    'version' => '1',
    'contract_version' => 1,
    'capabilities' => array('event_ticket_sales'),
    'callback' => static fn () => $upcoming_provider,
)), 'Fixture provider registered');
$upcoming = bvmgr_vendor_portal_get_count_breakdown(5001, array(
    'stats_payload' => array('qty_sold' => 139),
    'merged_snapshot' => bvmgr_vendor_portal_get_data_tools_sales_snapshot(5001),
));
issue143_same(139, $upcoming['presales'], 'Upcoming paid presales preserved');
issue143_same(0, $upcoming['door_sales'], 'Upcoming paid door preserved');
issue143_same(32, $upcoming['comp_guest'], 'Upcoming 32-comp total preserved');
issue143_same(32, array_sum(array_column($upcoming['comp_detail'], 'qty')), 'Upcoming details reconcile');
issue143_same(4, count($upcoming['comp_detail']) - 1, 'Upcoming category count');
$upcoming_markup = bvmgr_vendor_portal_render_count_breakdown_markup($upcoming);
issue143_true(strpos($upcoming_markup, '<details class="vms-vp-progress-breakdown__details">') !== false, 'Disclosure exists');
issue143_true(strpos($upcoming_markup, 'Complimentary admissions — 32') !== false, 'Disclosure summary shows total');
issue143_true(strpos($upcoming_markup, 'Guest passes') !== false, 'Disclosure includes guest passes');
issue143_true(strpos($upcoming_markup, '<details open') === false, 'Disclosure starts collapsed');

$historical = bvmgr_vendor_portal_get_count_breakdown(5002, array(
    'stats_payload' => array('qty_sold' => 136),
    'merged_snapshot' => array(
        'online_qty' => 136,
        'door_paid_qty' => 8,
        'excluded_free_online_qty' => 29,
        'door_free_qty' => 0,
        'complimentary_categories' => array(
            array('label' => 'Veterans', 'qty' => 18),
            array('label' => 'Children 12 & under', 'qty' => 6),
            array('label' => 'Police / Fire / EMT', 'qty' => 4),
            array('label' => 'Public School Teacher', 'qty' => 1),
        ),
    ),
));
issue143_same(136, $historical['presales'], '#10 historical presales');
issue143_same(8, $historical['door_sales'], '#10 historical paid doors');
issue143_same(31, $historical['comp_guest'], '#10 historical complimentary total');
issue143_same(31, array_sum(array_column($historical['comp_detail'], 'qty')), '#10 historical categories reconcile');

$fallback = bvmgr_vendor_portal_get_count_breakdown(5003, array(
    'stats_payload' => array('qty_sold' => 7),
    'merged_snapshot' => array(
        'online_qty' => 7,
        'door_paid_qty' => 0,
        'excluded_free_online_qty' => 3,
        'door_free_qty' => 0,
        'complimentary_categories' => array(),
    ),
));
issue143_same(4, $fallback['comp_guest'], 'Fallback total');
issue143_same('Other complimentary admissions', $fallback['comp_detail'][0]['label'], 'Unknown category is not invented');
issue143_same(3, $fallback['comp_detail'][0]['qty'], 'Unknown category quantity');
issue143_same(4, array_sum(array_column($fallback['comp_detail'], 'qty')), 'Fallback details reconcile');

$capped = bvmgr_vendor_portal_get_count_breakdown(5004, array(
    'stats_payload' => array('qty_sold' => 1),
    'merged_snapshot' => array(
        'online_qty' => 1,
        'door_paid_qty' => 0,
        'excluded_free_online_qty' => 2,
        'door_free_qty' => 0,
        'complimentary_categories' => array(array('label' => 'Veterans', 'qty' => 20)),
    ),
));
issue143_same(2, array_sum(array_column($capped['comp_detail'], 'qty')), 'Excess category quantity capped');

$none = bvmgr_vendor_portal_get_count_breakdown(5005, array(
    'stats_payload' => array('qty_sold' => 1),
    'merged_snapshot' => array('online_qty' => 1),
));
issue143_same(array(), $none['comp_detail'], 'No empty complimentary disclosure');
issue143_true(strpos(bvmgr_vendor_portal_render_count_breakdown_markup($none), '<details') === false, 'No disclosure for zero comps');

function issue143_card(array $breakdown, int $attendance, bool $history): string
{
    ob_start();
    bvmgr_vendor_portal_render_progress_cards_section(array(array(
        'title' => $history ? 'Historical fixture' : 'Upcoming fixture',
        'attendance_count' => $attendance,
        'count_breakdown' => $breakdown,
        'snapshot' => array('projected_total' => 1500.00, 'base_pay' => 1500.00, 'current_bonus' => 0.00),
    )), $history ? 'Past Show Performance' : 'Vendor Bonus Progress', $history);
    return (string) ob_get_clean();
}

$upcoming_card = issue143_card($upcoming, 139, false);
// General attendance can include comps. The ticket count shown on the card cannot.
$historical_card = issue143_card($historical, 175, true);
foreach (array(
    array($upcoming_card, 'Current paid count: 139', '32', 'Children 12 &amp; under'),
    array($historical_card, 'Final paid count: 144', '31', 'Guest passes'),
) as [$card, $label, $total, $category]) {
    issue143_true(strpos($card, $label) !== false, 'Paid wording and quantity in the rendered card');
    issue143_true(strpos($card, 'Count source') !== false, 'Count source present in rendered card');
    issue143_true(strpos($card, '<details class="vms-vp-progress-breakdown__details">') !== false, 'Initially collapsed disclosure in card output');
    issue143_true(strpos($card, '<summary>Complimentary admissions — ' . $total . '</summary>') !== false, 'Disclosure total survives card sanitization');
    issue143_true(strpos($card, $category) !== false, 'Real category label survives into card output');
    issue143_true(strpos($card, '$1,500.00') !== false && strpos($card, '$0.00') !== false, 'Fixture payout and bonus remain unchanged');
}
issue143_true(strpos(issue143_card(array(), 175, true), 'Final count: 175') !== false, 'General attendance fallback is not mislabeled as paid');
if (getenv('ISSUE143_RENDER')) {
    file_put_contents(getenv('ISSUE143_RENDER'), '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Synthetic Vendor Portal counters</title><style>'
        . file_get_contents(ABSPATH . 'assets/css/vms-portal.css') . '</style><div class="vms-portal">' . $upcoming_card . $historical_card . '</div></html>');
}
echo "#143 synthetic regression: PASS (provider to portal, upcoming and past cards, fallback, cap, zero, paid labels).\n";
