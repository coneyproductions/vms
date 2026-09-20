<?php
/** Real WordPress cron serialization/clearing with an isolated option store. */
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
define('MINUTE_IN_SECONDS', 60);
define('BVMGR_SOCIAL_CRON_HOOK', 'fixture_custom_social_queue');
define('BVMGR_CRON_TASKS_NIGHTLY', 'fixture_custom_tasks_nightly');
define('BVMGR_CALENDAR_TICKET_COUNTS_CRON_HOOK', 'fixture_custom_calendar_counts');
$root = getenv('BVM_ROUND2_CRON_SOURCE_ROOT') ?: dirname(__DIR__);
$wordpress = getenv('BVM_ROUND2_HOOK_WORDPRESS_ROOT');
if (!$wordpress || !is_file($wordpress . '/wp-includes/cron.php')) throw new RuntimeException('Qualified WordPress fixture required');
function get_option($key, $default = false) { return $GLOBALS['cron_test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { if ($key !== 'cron') throw new RuntimeException('Unexpected option write'); $GLOBALS['cron_test_options'][$key] = $value; return true; }
function is_wp_error($value) { return $value instanceof WP_Error; }
require $wordpress . '/wp-includes/class-wp-error.php';
require $wordpress . '/wp-includes/plugin.php';
require $wordpress . '/wp-includes/cron.php';
function cron_test_extract(string $source, string $name): string {
    $tokens = token_get_all($source);
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
        $n = $index + 1;
        while (isset($tokens[$n]) && is_array($tokens[$n]) && $tokens[$n][0] === T_WHITESPACE) $n++;
        if (!isset($tokens[$n]) || !is_array($tokens[$n]) || $tokens[$n][1] !== $name) continue;
        $body = ''; $depth = 0; $opened = false;
        for ($j = $index; $j < count($tokens); $j++) {
            $t = $tokens[$j]; $body .= is_array($t) ? $t[1] : $t;
            if ($t === '{') { $depth++; $opened = true; }
            if ($t === '}' && --$depth === 0 && $opened) return $body;
        }
    }
    throw new RuntimeException('Missing production function: ' . $name);
}
$source = file_get_contents($root . '/includes/runtime-guards.php');
eval(cron_test_extract($source, 'bvmgr_is_owned_cron_hook'));
eval(cron_test_extract($source, 'bvmgr_unschedule_all_owned_cron_hooks'));
require $root . '/includes/activation.php';
$owned = array(
    'vms_square_nightly_sync', 'vms_social_process_queue', 'vms_tasks_notifications_tick',
    'vms_tasks_notifications_digest_tick', 'vms_tasks_nightly_generator', 'vms_tasks_generate_for_event_queued',
    'vms_email_followups_cron', 'vms_calendar_ticket_counts_nightly', 'vms_vendor_booking_onboarding_daily',
    'vms_notify_digest_tick_cron', 'vms_ticket_integrity_daily_scan', 'vms_ticket_integrity_spot_scan',
    'vms_ticket_integrity_daily_report', 'vms_ticket_integrity_payment_gateway_health', 'vms_integrity_daily_scan',
    'vms_ticketing_v2_legacy_cleanup', 'vms_ticketing_verification_cleanup', 'vms_event_plan_legacy_ticket_cleanup',
    'vms_event_plan_calendar_maintenance', 'vms_event_plan_deferred_calendar_publish', 'vms_staffing_seed_event_slots_queued',
    'vms_ticketing_v2_async_send_woo_ticket_emails', 'vms_ticketing_verification_send_submission_notification_async',
    BVMGR_SOCIAL_CRON_HOOK, BVMGR_CRON_TASKS_NIGHTLY, BVMGR_CALENDAR_TICKET_COUNTS_CRON_HOOK,
);
$foreign = array('vms_unrelated_fixture_job', 'vms_express_bar_fixture_job', 'bvmgr_unregistered_fixture_job', 'foreign_fixture_job', ' vms_tasks_nightly_generator ');
$checks = 0;
$assert = static function ($condition, $message) use (&$checks) {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
foreach ($foreign as $hook) $assert(!bvmgr_is_owned_cron_hook($hook), 'Foreign/lookalike hook must not be claimed: ' . $hook);
foreach ($owned as $hook) $assert(bvmgr_is_owned_cron_hook($hook), 'Known producer/configured hook must be owned: ' . $hook);
$variants = array(array(), array(7));
$foreign_variants = array_merge($variants, array(array('slot' => 7), array(2 => 7, 'context' => 'fixture')));
$time = time() + 3600;
foreach (array_merge($owned, $foreign) as $hook) {
    foreach (in_array($hook, $foreign, true) ? $foreign_variants : $variants as $args) $assert(wp_schedule_single_event($time, $hook, $args) === true, 'Native cron schedule failed');
}
$before = _get_cron_array();
bvmgr_unschedule_all_owned_cron_hooks();
$after = _get_cron_array();
foreach ($owned as $hook) foreach ($variants as $args) $assert(wp_next_scheduled($hook, $args) === false, 'Exact owned argument variant must be removed: ' . $hook);
foreach ($foreign as $hook) $assert(($after[$time][$hook] ?? null) === $before[$time][$hook], 'Foreign serialized event must be byte-for-byte preserved');
$stable = serialize($after);
bvmgr_unschedule_all_owned_cron_hooks();
$assert(serialize(_get_cron_array()) === $stable, 'Repeat deactivation cleanup must be idempotent');

// Argument-key repair is deliberately excluded; test supported positional legacy cleanup only.
foreach ($variants as $args) wp_schedule_single_event($time, 'vms_square_nightly_sync', $args);
$legacy = bvmgr_cleanup_legacy_square_nightly_sync_wp_cron_fallback('vms_square_nightly_sync');
$assert($legacy['complete'] && $legacy['remaining'] === 0 && $legacy['found'] === count($variants), 'Legacy fallback must clear positional/empty arguments');
$assert(serialize(_get_cron_array()) === $stable, 'Legacy cleanup must preserve all foreign events');
echo "PASS: $checks native WordPress cron ownership checks (owned positional jobs; foreign keyed arguments preserved)\n";
