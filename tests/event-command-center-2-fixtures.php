<?php
/**
 * Disposable ECC component fixtures. Never loads WordPress, a DB, provider or transport.
 * Uses the real production dashboard, readiness and financial presentation functions.
 * Usage: php tests/event-command-center-2-fixtures.php [/private/tmp/output-directory]
 */
define('ABSPATH', __DIR__ . '/');
function add_action(...$args) {}
function add_filter(...$args) {}
function __($text, $domain = '') { return $text; }
function _n($one, $many, $n, $domain = '') { return $n === 1 ? $one : $many; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url($text) { return preg_match('/^(?:javascript|data):/i', (string) $text) ? '' : esc_attr($text); }
function esc_html__($text, $domain = '') { return esc_html($text); }
function esc_attr__($text, $domain = '') { return esc_attr($text); }
function absint($number) { return abs((int) $number); }
function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function sanitize_text_field($text) { return trim(strip_tags((string) $text)); }
function wp_strip_all_tags($text) { return strip_tags((string) $text); }
function wp_kses($text, $allowed) { return $text; }
function wp_kses_post($text) { return $text; }
function wpautop($text) { return '<p>' . $text . '</p>'; }
function number_format_i18n($number, $decimals = 0) { return number_format($number, $decimals); }
function wp_timezone() { return new DateTimeZone('America/Chicago'); }
function current_time($format, $utc = false) { return $format === 'timestamp' ? 1788804000 : '2026-09-07 13:00:00'; }
function wp_date($format, $time = null, $zone = null) { return date($format, $time ?? 1788804000); }
function human_time_diff($from, $to = 0) { return '5 minutes'; }
function bvmgr_goals_fmt_money($cents) { return ($cents < 0 ? '-$' : '$') . number_format(abs($cents) / 100, 2); }
function admin_url($path = '') { return '/wp-admin/' . $path; }
function add_query_arg($args, $url = '') { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); }
function get_edit_post_link($id, $context = '') { return '/wp-admin/post.php?post=' . $id . '&action=edit'; }
function bvmgr_admin_ui_page_url($page, $args = array()) { return add_query_arg(array_merge(array('page' => $page), $args), admin_url('admin.php')); }
function current_user_can(...$args) { return true; }
function bvmgr_event_command_center_can_manage_promo_video($id): bool { return false; }
function get_post_meta(...$args) { return ''; }
function get_the_title($id) { return 'Fixture event'; }
function wp_nonce_url($url, $action) { return add_query_arg(array('_wpnonce' => 'fixture-' . $action), $url); }
function wp_create_nonce($action) { return 'fixture-' . $action; }
function wp_json_encode($value) { return json_encode($value); }
require dirname(__DIR__) . '/includes/core/financial-snapshot.php';
require dirname(__DIR__) . '/includes/admin/event-command-center.php';
require dirname(__DIR__) . '/includes/admin/event-day-report.php';

function ecc_fixture_base(): array {
    $plan_id = 420;
    return array(
        'header' => array('plan_id' => $plan_id, 'title' => 'Riverlight Sessions', 'status' => 'confirmed', 'status_label' => 'Confirmed', 'status_tone' => 'good', 'date_raw' => '2026-09-19', 'date_label' => 'Saturday, September 19, 2026', 'time_label' => '7:00 pm – 10:00 pm', 'venue_id' => 31, 'venue_label' => 'Serenade Range · Main stage', 'days_until' => 12, 'days_until_label' => 'In 12 days', 'edit_url' => get_edit_post_link($plan_id), 'public_event_url' => '', 'edit_event_url' => '', 'ticket_url' => '', 'tec_event_id' => 721, 'modified_label' => 'Sep 7, 2026 12:55 pm', 'marketing_url' => '/wp-admin/admin.php?page=vms-marketing-social', 'social_url' => '/wp-admin/admin.php?page=vms-social-sharing', 'weather_url' => '/wp-admin/admin.php?page=vms-weather-risk&event_plan_id=420'),
        'ticket' => array('ticket_state' => 'CURRENT', 'display_sales' => true, 'is_current' => true, 'is_stale' => false, 'is_pending_refresh' => false, 'is_valid_zero' => false, 'sold' => 184, 'revenue_cents' => 552000, 'free_qty' => 12, 'comp_count' => 12, 'comp_count_basis' => 'transaction_free', 'comp_forecast' => 20, 'total_ticket_count' => 196, 'ticket_source' => 'vms-data-tools', 'ticket_source_label' => 'Data Tools 0.5.55', 'ticket_source_warnings' => array(), 'stats_age_label' => 'Calculated from current reporting data.', 'status_label' => 'Current sales', 'sales_summary_label' => '184 paid tickets', 'capacity' => 300, 'remaining' => 104, 'sell_through' => 61.3, 'integrity_status' => 'ready', 'issue_summary' => '', 'issues' => array(), 'revenue_basis' => 'actual_transaction', 'provider_id' => 'vms-data-tools', 'provider_version' => '0.5.55'),
        'staffing' => array('ok' => true, 'headcount_context' => array('wired' => true), 'authority' => 'normalized', 'provenance' => 'Normalized staffing lifecycle', 'readiness_status' => 'ready', 'readiness_label' => 'Ready', 'headcount_needed_total' => 8, 'planned_headcount' => 8, 'required_now_headcount_total' => 8, 'assigned_headcount' => 8, 'proposed_headcount' => 0, 'confirmed_headcount' => 8, 'open_positions' => 0, 'required_open_positions' => 0, 'open_headcount_total' => 0, 'critical_open_headcount' => 0, 'conflict_count' => 0, 'overlap_warnings' => array(), 'roles' => array(), 'rollup_state' => 'fresh', 'rollup' => array('computed_at' => '2026-09-07 12:55:00')),
        'financial' => bvmgr_financial_build_snapshot($plan_id, array('ticket' => array('available' => true, 'calculated' => true, 'revenue_cents' => 552000, 'paid_qty' => 184, 'source' => 'vms-data-tools', 'source_label' => 'Data Tools', 'provider_id' => 'vms-data-tools'), 'manual' => array('direct' => 185000, 'processing' => 16000, 'concessions' => 24000), 'forecast' => array('gross_revenue_cents' => 880000, 'direct_costs_cents' => 185000, 'processing_fees_cents' => 27000), 'staffing_labor' => array('availability' => 'available', 'planned_cents' => 96000, 'committed_cents' => 96000), 'calculated_at_utc' => '2026-09-07 17:55:00')),
        'lineup' => array('entries' => array(array('name' => 'The Riverlight Band', 'role_label' => 'Headliner', 'status_label' => 'Confirmed', 'status_tone' => 'good', 'edit_url' => '#talent')), 'primary' => array('display_name' => 'The Riverlight Band', 'role_label' => 'Headliner'), 'supporting' => array(), 'secondary' => array(), 'summary' => array(), 'warnings' => array()),
        'marketing' => array('promo_video' => array(), 'ticket_url' => '', 'event_url' => '', 'warnings' => array()),
        'weather' => array('active' => true, 'available' => true, 'state' => 'current', 'risk_level' => 'low', 'label' => 'Low weather risk', 'summary' => 'No operational weather concern in the event window.', 'freshness_label' => 'Updated 5 minutes ago', 'url' => '/wp-admin/admin.php?page=vms-weather-risk&event_plan_id=420'),
        'context' => array('event_day_url' => bvmgr_event_day_report_url($plan_id), 'communications' => array('available' => true, 'pending' => 0, 'failed' => 0, 'review_required' => 0, 'summary' => 'No occurrence-change notices are recorded. Marketing and outreach have separate workflows.', 'url' => '/wp-admin/post.php?post=420&action=edit#bvmgr-event-communications'), 'documents' => array('available' => true, 'issues' => array(), 'summary' => '1 active agreement packet; 0 require review.', 'url' => '/wp-admin/admin.php?page=vms-agreements&vmsa_setup_event_plan=420'), 'tools' => array(array('label' => 'Event-Day Guest List / Report', 'url' => bvmgr_event_day_report_url($plan_id)), array('label' => 'Admissions / check-in', 'url' => get_edit_post_link($plan_id) . '#vms_guest_list_comp_admission'), array('label' => 'Profitability report (all events)', 'url' => '/wp-admin/admin.php?page=vms-event-profitability'))),
        'notes' => array('has_notes' => true, 'notes' => 'Radio check at doors. Keep the west access lane clear for vendor load-in.'),
        'alerts' => array(), 'health' => array(), 'timeline' => array(array('time' => '19:00', 'label' => 'Show starts', 'detail' => 'The Riverlight Band')), 'actions' => array(), 'activity' => array(),
    );
}

function ecc_fixture_cases(): array {
    $base = ecc_fixture_base();
    $patches = array(
        '01-healthy-upcoming' => array(),
        '02-show-day' => array('header' => array('days_until' => 0, 'days_until_label' => 'Today', 'date_label' => 'Monday, September 7, 2026')),
        '03-valid-zero-sales' => array('ticket' => array('sold' => 0, 'free_qty' => 0, 'comp_count' => 0, 'total_ticket_count' => 0, 'revenue_cents' => 0, 'ticket_state' => 'VALID_ZERO', 'is_valid_zero' => true, 'status_label' => 'Valid zero', 'sales_summary_label' => 'No paid tickets yet')),
        '04-paid-and-comp' => array('ticket' => array('sold' => 125, 'free_qty' => 18, 'comp_count' => 18, 'total_ticket_count' => 143)),
        '05-tentative-staffing' => array('staffing' => array('proposed_headcount' => 3, 'confirmed_headcount' => 5)),
        '06-confirmed-staffing' => array(),
        '07-staffing-conflict-open' => array('staffing' => array('readiness_status' => 'red_flag', 'confirmed_headcount' => 5, 'assigned_headcount' => 6, 'proposed_headcount' => 1, 'open_positions' => 2, 'required_open_positions' => 2, 'open_headcount_total' => 2, 'critical_open_headcount' => 1, 'conflict_count' => 1)),
        '08-weather-warning' => array('weather' => array('state' => 'current', 'concern' => true, 'risk_level' => 'high', 'label' => 'Weather warning', 'summary' => 'Thunderstorms possible during doors and the first set. Review shelter and delay procedures.')),
        '09-weather-unavailable' => array('weather' => array('active' => false, 'available' => false, 'state' => 'unavailable', 'label' => 'Weather unavailable', 'summary' => 'Weather Risk is not available for this event.', 'freshness_label' => '', 'url' => '')),
        '10-actual-and-forecast' => array(),
        '11-actual-unavailable' => array(),
        '12-stale-ticket-provider' => array('ticket' => array('ticket_state' => 'STALE', 'display_sales' => false, 'is_current' => false, 'is_stale' => true, 'sold' => null, 'revenue_cents' => null, 'status_label' => 'Stale ticket data', 'stats_age_label' => 'Last known provider data is 2 days old.')),
        '13-document-exception' => array('context' => array('documents' => array('available' => true, 'summary' => '1 active agreement packet; 1 requires review.', 'issues' => array(array('title' => 'Headliner agreement needs review', 'detail' => 'Acknowledgment pending for current agreement terms.', 'action_label' => 'Review agreement', 'action_url' => '/wp-admin/admin.php?page=vms-agreements&packet_id=91'))))),
        '14-communication-pending' => array('context' => array('communications' => array('available' => true, 'pending' => 28, 'failed' => 2, 'review_required' => 1, 'summary' => 'Occurrence-change notices: 28 pending, 2 failed, 1 requiring review.', 'url' => '/wp-admin/post.php?post=420&action=edit#bvmgr-event-communications'))),
        '15-minimal-optionals-unavailable' => array('staffing' => array('headcount_needed_total' => 0, 'required_now_headcount_total' => 0, 'confirmed_headcount' => 0, 'assigned_headcount' => 0, 'readiness_status' => 'not_applicable'), 'lineup' => array('entries' => array(), 'primary' => array()), 'weather' => array('active' => false, 'available' => false, 'state' => 'unavailable', 'label' => 'Weather unavailable', 'summary' => 'Weather Risk is not available.', 'freshness_label' => '', 'url' => ''), 'notes' => array('has_notes' => false, 'notes' => ''), 'timeline' => array()),
        '16-past-event' => array('header' => array('days_until' => -3, 'days_until_label' => '3 days ago', 'date_label' => 'Friday, September 4, 2026')),
    );
    $cases = array();
    foreach ($patches as $name => $patch) {
        $cases[$name] = $base;
        foreach ($patch as $group => $values) { $cases[$name][$group] = $values === array() ? array() : array_replace($base[$group], $values); }
    }
    $cases['11-actual-unavailable']['financial'] = bvmgr_financial_build_snapshot(420, array('forecast' => array('gross_revenue_cents' => 880000, 'direct_costs_cents' => 185000, 'processing_fees_cents' => 27000), 'staffing_labor' => array('availability' => 'available', 'planned_cents' => 96000, 'committed_cents' => 96000)));
    $cases['03-valid-zero-sales']['financial'] = bvmgr_financial_build_snapshot(420, array('ticket' => array('available' => true, 'calculated' => true, 'revenue_cents' => 0, 'paid_qty' => 0, 'source_label' => 'Data Tools'), 'forecast' => array('gross_revenue_cents' => 880000, 'direct_costs_cents' => 185000, 'processing_fees_cents' => 27000), 'staffing_labor' => array('availability' => 'available', 'planned_cents' => 96000, 'committed_cents' => 96000)));
    $cases['12-stale-ticket-provider']['financial'] = bvmgr_financial_build_snapshot(420, array('ticket' => array('available' => true, 'calculated' => true, 'revenue_cents' => 552000, 'paid_qty' => 184, 'source_label' => 'Data Tools', 'freshness' => array('state' => 'stale')), 'forecast' => array('gross_revenue_cents' => 880000, 'direct_costs_cents' => 185000, 'processing_fees_cents' => 27000), 'staffing_labor' => array('availability' => 'available', 'planned_cents' => 96000, 'committed_cents' => 96000)));
    $cases['15-minimal-optionals-unavailable']['financial'] = bvmgr_financial_build_snapshot(420, array());
    $cases['15-minimal-optionals-unavailable']['context']['documents'] = array('available' => false, 'issues' => array(), 'summary' => 'Agreement status unavailable.');
    $cases['15-minimal-optionals-unavailable']['context']['communications'] = array('available' => false, 'summary' => 'Customer notice status unavailable.');
    $cases['15-minimal-optionals-unavailable']['context']['tools'] = array_slice($base['context']['tools'], 0, 1);
    return $cases;
}

function ecc_fixture_assertions(): int {
    $checks = 0;
    $assert = static function (bool $ok, string $message) use (&$checks): void {
        $checks++;
        if (!$ok) { throw new RuntimeException($message); }
    };
    $cases = ecc_fixture_cases();
    $expected = array('ready', 'ready', 'ready', 'ready', 'attention', 'ready', 'blocked', 'attention', 'ready', 'ready', 'incomplete', 'incomplete', 'attention', 'attention', 'incomplete', 'ready');
    foreach (array_values($cases) as $i => $payload) {
        $before = serialize($payload);
        $readiness = bvmgr_event_command_center_operational_readiness($payload);
        $assert($readiness['status'] === $expected[$i], 'Readiness fixture ' . ($i + 1));
        ob_start(); bvmgr_event_command_center_render_dashboard(420, $payload); $html = ob_get_clean();
        $assert($before === serialize($payload), 'Renderer does not mutate snapshot ' . $i);
        $assert(strpos($html, 'Audience &amp; admissions') !== false && strpos($html, 'Required now') !== false, 'Operational headings and staffing scope ' . $i);
        $assert(strpos($html, 'event_plan_id=420') !== false, 'Report carries fixture plan scope ' . $i);
        $assert($i === 14 ? strpos($html, 'vms-cc-financial-groups') === false : (strpos($html, 'Forecast / planned') !== false && strpos($html, 'Reported / manual') !== false && strpos($html, 'Current / transactional') !== false), 'Financial bases stay distinct; wholly unavailable data stays compact ' . $i);
        $assert(strpos($html, 'Express Bar') === false, 'Unavailable optional tool omitted ' . $i);
    }
    $payload = $cases['01-healthy-upcoming'];
    $payload['ticket']['comp_count_basis'] = 'none';
    $payload['ticket']['comp_count'] = 0;
    ob_start(); bvmgr_event_command_center_render_dashboard(420, $payload); $html = ob_get_clean();
    $assert((bool) preg_match('/Comp \/ free tickets<\/span>\s*<strong[^>]*>Unavailable<\/strong>/', $html), 'Unknown comp quantity is unavailable, not zero');
    $payload['ticket']['comp_count_basis'] = 'forecast';
    $payload['ticket']['comp_count'] = 20;
    ob_start(); bvmgr_event_command_center_render_dashboard(420, $payload); $html = ob_get_clean();
    $assert(strpos($html, 'Forecast only') !== false, 'Forecast comp basis visible');
    $assert(strpos($html, 'not check-ins or a deduplicated attendance total') !== false, 'No mixed population masquerades as check-ins');
    $payload['header']['title'] = '<script>alert(1)</script>';
    $payload['notes'] = array('has_notes' => true, 'notes' => '<img src=x onerror=alert(1)>');
    ob_start(); bvmgr_event_command_center_render_dashboard(420, $payload); $html = ob_get_clean();
    $assert(strpos($html, '<script>') === false && strpos($html, '<img src=x') === false && strpos($html, '&lt;script&gt;') !== false, 'Text data is escaped');
    $payload = $cases['01-healthy-upcoming'];
    $payload['staffing']['overlap_warnings'] = array(array('assignment_id' => 7, 'overlapping_assignment_ids' => array(12)));
    $assert(bvmgr_event_command_center_operational_readiness($payload)['status'] === 'attention', 'Soft cross-event staffing overlap requires attention even with all local staff confirmed');
    $payload = $cases['01-healthy-upcoming'];
    $payload['staffing']['headcount_context']['wired'] = false;
    $assert(bvmgr_event_command_center_operational_readiness($payload)['status'] === 'incomplete', 'Explicitly unknown staffing headcount wiring cannot appear ready');
    $payload = $cases['15-minimal-optionals-unavailable'];
    ob_start(); bvmgr_event_command_center_render_dashboard(420, $payload); $html = ob_get_clean();
    $assert(strpos($html, 'vms-cc-financial-groups') === false && strpos($html, '$0.00') === false, 'Wholly unavailable finances have no repeated metric groups or fabricated zero');
    return $checks;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $assertions = ecc_fixture_assertions();
    $out = $argv[1] ?? sys_get_temp_dir() . '/bvm-ecc2-ui-fixtures';
    if (!is_dir($out) && !mkdir($out, 0700, true)) { throw new RuntimeException('Cannot create fixture output'); }
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/vms-admin-ui.css') . "\n" . file_get_contents(dirname(__DIR__) . '/assets/css/vms-event-command-center.css');
    $shell_css = 'body{margin:0;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#1d2735}*{box-sizing:border-box}a{color:#165fcf}button,input,select{font:inherit}.fixture-top{background:#1d2327;color:#f0f0f1;padding:8px 22px;font-size:13px}.fixture-layout{margin:24px 30px 40px 180px}.fixture-side{position:absolute;top:35px;left:0;width:150px;padding:20px 12px;color:#414b56}.fixture-side p{margin:0 0 16px}.button{display:inline-block;text-decoration:none;min-height:32px;padding:5px 12px;border:1px solid #2271b1;border-radius:3px;background:#f6f7f7;color:#2271b1;cursor:pointer}.button-primary{background:#2271b1;color:#fff}.button:focus-visible,a:focus-visible,summary:focus-visible{outline:2px solid #165fcf;outline-offset:3px}.fixture-caption{font-size:12px;color:#5a677a;margin:0 0 16px}@media(max-width:782px){.fixture-side{display:none}.fixture-layout{margin:16px 10px}.fixture-top{padding:8px 12px}}';
    $index = array();
    foreach (ecc_fixture_cases() as $name => $payload) {
        ob_start();
        bvmgr_event_command_center_render_dashboard(420, $payload);
        $dashboard = ob_get_clean();
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:; base-uri \'none\'; form-action \'none\'"><title>' . esc_html($name) . ' · ECC fixture</title><style>' . $shell_css . "\n" . $css . '</style></head><body class="vms-admin"><div class="fixture-top">Backstage Venue Manager · Disposable visual fixture</div><aside class="fixture-side"><p>Dashboard</p><p>Event Plans</p><p><strong>Command Center</strong></p><p>Vendors &amp; Staff</p><p>Reports</p></aside><main class="fixture-layout"><h1>Event Command Center</h1><p class="fixture-caption">Synthetic event data · actual ECC renderer and styles · no connected systems</p><div class="vms-admin-shell" data-vms-cluster="planning"><div class="vms-admin-shell__content">' . $dashboard . '</div></div></main></body></html>';
        file_put_contents($out . '/' . $name . '.html', $html);
        $index[$name] = array('readiness' => bvmgr_event_command_center_operational_readiness($payload), 'bytes' => strlen($html));
    }
    file_put_contents($out . '/fixture-manifest.json', json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo 'PASS ' . $assertions . ' semantic assertions; rendered ' . count($index) . ' actual ECC fixture states to ' . $out . "\n";
}
