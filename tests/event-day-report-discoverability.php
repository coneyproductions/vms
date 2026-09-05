<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

$pluginRootEnv = getenv('BVMGR_TEST_PLUGIN_ROOT');
$pluginRoot = is_string($pluginRootEnv) && $pluginRootEnv !== '' ? realpath($pluginRootEnv) : dirname(__DIR__);
if (!is_string($pluginRoot) || !is_dir($pluginRoot)) {
	throw new RuntimeException('BVMGR_TEST_PLUGIN_ROOT must identify the exact plugin package under test.');
}

if (!function_exists('bvmgr_ticketing_v2_get_config')) {
	require_once $pluginRoot . '/vendor-management-system.php';
}
require_once $pluginRoot . '/includes/admin/event-day-report.php';
require_once $pluginRoot . '/includes/admin-ui/helpers.php';
require_once $pluginRoot . '/includes/admin/event-command-center.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException('Assertion ' . $assertions . ' failed: ' . $message);
	}
};

$adminUiSource = (string) file_get_contents($pluginRoot . '/includes/modules/admissions/admin-ui.php');
$commandCenterSource = (string) file_get_contents($pluginRoot . '/includes/admin/event-command-center.php');
$reportSource = (string) file_get_contents($pluginRoot . '/includes/admin/event-day-report.php');
$reportCss = (string) file_get_contents($pluginRoot . '/assets/css/vms-event-day-report.css');
$reportJs = (string) file_get_contents($pluginRoot . '/assets/js/vms-event-day-report.js');

$assert(substr_count($adminUiSource, 'Print Event-Day Guest List') === 1, 'Guest List operations area must expose exactly one report button.');
$assert(strpos($adminUiSource, "'eventDayReportUrl' => function_exists('bvmgr_event_day_report_url')") !== false, 'Admissions configuration must expose the existing report URL.');
$assert(strpos($commandCenterSource, 'Print Event-Day Guest List') !== false, 'Event Plan submit box must expose the prominent report button.');
$assert(strpos($commandCenterSource, "current_user_can(function_exists('bvmgr_admission_manage_capability')") !== false, 'Submit-box report button must remain capability protected.');
$assert(strpos($reportSource, "wp_verify_nonce(\$nonce, bvmgr_event_day_report_nonce_action(\$event_plan_id))") !== false, 'Report handler must retain its Event Plan-scoped nonce gate.');
$assert(strpos($reportSource, "current_user_can(\$capability)") !== false, 'Report handler must retain its capability gate.');
$assert(strpos($reportSource, 'bvmgr_admission_audit_log(') === false, 'Read-only report views must not write admission audit rows.');
$assert(strpos($reportSource, 'foreach ($rows as &$row)') !== false, 'Admission bridge enrichment must persist on returned report rows.');
$assert(strpos($reportCss, '@media print') !== false && strpos($reportCss, 'overflow:visible') !== false, 'Report must retain its dedicated print layout.');
$assert(strpos($reportCss, '.vms-edr-print-now,.vms-edr-toolbar') !== false, 'Print layout must hide action controls.');
$assert(strpos($reportJs, 'window.print()') !== false, 'Existing report Print button must invoke the browser print dialog.');

$registeredTicketPostType = false;
if (!post_type_exists('tribe_wooticket')) {
	register_post_type('tribe_wooticket', array('public' => false));
	$registeredTicketPostType = true;
}
$attendeeId = wp_insert_post(array(
	'post_type' => 'tribe_wooticket',
	'post_status' => 'publish',
	'post_title' => 'Event-Day identity fixture',
), true);
if (is_wp_error($attendeeId) || (int) $attendeeId <= 0) {
	throw new RuntimeException('Could not create attendee-linkage fixture.');
}
$attendeeId = (int) $attendeeId;
update_post_meta($attendeeId, '_tribe_wooticket_order', '91001');
update_post_meta($attendeeId, '_tribe_wooticket_product', '92001');
update_post_meta($attendeeId, '_tribe_wooticket_event', '93001');

global $wpdb;
$auditBefore = null;
if (function_exists('bvmgr_admission_table_audit')) {
	$auditTable = bvmgr_admission_table_audit();
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $auditTable)) === $auditTable) {
		$auditBefore = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$auditTable}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test-only before/after mutation sentinel for the plugin-owned audit table.
	}
}

try {
	$resolvedIds = bvmgr_event_day_report_attendee_ids_for_woo_row(array(
		'order_id' => 91001,
		'product_id' => 92001,
		'tec_event_id' => 93001,
		'attendee_ids' => array(),
	));
	$assert($resolvedIds === array($attendeeId), 'Exact order/product/event fallback must recover attendees when order-item metadata is absent.');
	$assert(bvmgr_event_day_report_attendee_ids_for_woo_row(array(
		'order_id' => 91001,
		'product_id' => 92001,
		'tec_event_id' => 93002,
		'attendee_ids' => array(),
	)) === array(), 'Attendee fallback must not cross the TEC event boundary.');

	$attendees = static function (int $count, string $prefix): array {
		$rows = array();
		for ($index = 1; $index <= $count; $index++) {
			$rows[] = array(
				'id' => 100000 + $index,
				'name' => $prefix . ' ' . $index,
				'email' => strtolower(str_replace(' ', '.', $prefix)) . $index . '@example.test',
				'reference' => 'fixture-' . $prefix . '-' . $index,
				'checked_in' => false,
			);
		}
		return $rows;
	};
	$wooRows = array(
		array(
			'event_plan_id' => 94001,
			'line_kind' => 'ticket',
			'order_id' => 95001,
			'order_item_id' => 1,
			'order_number' => '95001',
			'product_name' => 'General Admission',
			'qty' => 20,
			'refunded_qty' => 0,
			'customer_name' => 'Purchaser Fixture',
			'customer_email' => 'purchaser@example.test',
			'attendees' => $attendees(20, 'Attendee Fixture'),
		),
		array(
			'event_plan_id' => 94001,
			'line_kind' => 'ticket',
			'order_id' => 95002,
			'order_item_id' => 2,
			'order_number' => '95002',
			'product_name' => 'Veteran Admission',
			'qty' => 2,
			'refunded_qty' => 0,
			'customer_name' => 'Veteran Purchaser',
			'attendees' => $attendees(2, 'Veteran Guest'),
		),
		array(
			'event_plan_id' => 94001,
			'line_kind' => 'ticket',
			'order_id' => 95003,
			'order_item_id' => 3,
			'order_number' => '95003',
			'product_name' => 'Complimentary Admission',
			'qty' => 4,
			'refunded_qty' => 0,
			'customer_name' => 'Comp Purchaser',
			'attendees' => $attendees(4, 'Comp Guest'),
		),
		array(
			'event_plan_id' => 94001,
			'line_kind' => 'addon',
			'order_id' => 95001,
			'order_item_id' => 4,
			'order_number' => '95001',
			'product_id' => 96001,
			'product_name' => 'Fire Table #03',
			'reservation_family' => 'Fire Table',
			'qty' => 1,
			'refunded_qty' => 0,
			'customer_name' => 'Purchaser Fixture',
			'customer_email' => 'purchaser@example.test',
		),
	);
	$admissions = array();
	for ($index = 1; $index <= 8; $index++) {
		$admissions[] = array(
			'id' => 97000 + $index,
			'guest_name' => 'Guest List Fixture ' . $index,
			'party_size' => 1,
			'checked_in_qty' => 0,
			'status' => 'active',
			'source' => 'pass_claim',
			'admission_kind' => 'pass',
			'claim_reference' => 'guest-list-fixture-' . $index,
		);
	}

	$model = bvmgr_event_day_report_build_model_from_sources(array(
		'event_plan_id' => 94001,
		'tec_event_id' => 93001,
		'title' => 'Event-Day Report Fixture',
		'schedule_label' => 'September 5, 2026 · 7:00 pm',
		'generated_label' => 'September 4, 2026 7:00 pm CDT',
	), array(
		'woo_result' => array('rows' => $wooRows),
		'admissions' => $admissions,
	));

	$assert((int) ($model['totals']['expected'] ?? 0) === 34, 'Twenty-six ticket attendees plus eight guest-list admissions must total 34 humans.');
	$assert((int) ($model['totals']['reservation_units'] ?? 0) === 1, 'The Fire Table add-on must remain separate from human attendance.');
	$assert(count((array) ($model['reservations'] ?? array())) === 1, 'The report must retain one separate add-on reservation row.');
	$assert((string) ($model['parties'][0]['name'] ?? '') === 'Attendee Fixture 1', 'Attendee name must be the primary party label before purchaser fallback.');
	$assert((string) ($model['parties'][0]['purchaser_name'] ?? '') === 'Purchaser Fixture', 'Differing purchaser context must be preserved behind the attendee name.');
	$assert((string) ($model['reservations'][0]['display_name'] ?? '') === 'Attendee Fixture 1', 'Reservation must show its associated guest name before purchaser fallback.');
	$assert((string) ($model['reservations'][0]['purchaser_name'] ?? '') === 'Purchaser Fixture', 'Reservation must retain differing purchaser context.');

	ob_start();
	bvmgr_event_day_report_render_document($model, 'full', true);
	$markup = (string) ob_get_clean();
	$assert(strpos($markup, 'Event-Day Report Fixture') !== false && strpos($markup, 'September 5, 2026') !== false, 'Printed report must identify the event and date.');
	$assert(strpos($markup, 'Generated September 4, 2026 7:00 pm CDT') !== false, 'Printed report must include its generated timestamp.');
	$assert(strpos($markup, 'Attendee Fixture 1') < strpos($markup, 'Purchased by Purchaser Fixture'), 'Rendered report must present attendee name before purchaser context.');
	$assert(strpos($markup, 'Guest List') !== false && strpos($markup, 'Reservations') !== false && strpos($markup, 'Fire Table #03') !== false, 'Admissions and add-ons must render in separate report sections.');
	$assert(strpos($markup, 'wp-admin') === false && strpos($markup, 'adminmenu') === false, 'Standalone report must not render WordPress admin chrome.');

	wp_set_current_user(1);
	set_current_screen('vms_event_plan');
	$savedGet = $_GET;
	$_GET['post'] = '94001';
	ob_start();
	bvmgr_event_command_center_submitbox_link();
	$adminMarkup = (string) ob_get_clean();
	$_GET = $savedGet;
	$assert(strpos($adminMarkup, 'Print Event-Day Guest List') !== false, 'Authorized Event Plan editor must show the no-scroll report action.');
	$assert(strpos($adminMarkup, 'target="_blank"') !== false && strpos($adminMarkup, 'rel="noopener noreferrer"') !== false, 'Event Plan report action must open safely in a new tab.');
	$assert(strpos($adminMarkup, 'action=vms_event_day_report') !== false, 'Event Plan action must use the existing protected report route.');

	$url = html_entity_decode(bvmgr_event_day_report_url(94001), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
	$nonce = (string) ($query['_wpnonce'] ?? '');
	$assert(($query['action'] ?? '') === 'vms_event_day_report' && (int) ($query['event_plan_id'] ?? 0) === 94001, 'Report URL must remain bound to the current Event Plan.');
	$assert($nonce !== '' && (bool) wp_verify_nonce($nonce, bvmgr_event_day_report_nonce_action(94001)), 'Report URL must carry a valid current-plan nonce.');
	$assert(!wp_verify_nonce($nonce, bvmgr_event_day_report_nonce_action(94002)), 'One Event Plan nonce must not authorize another event report.');

	wp_set_current_user(0);
	set_current_screen('vms_event_plan');
	$_GET['post'] = '94001';
	ob_start();
	bvmgr_event_command_center_submitbox_link();
	$unauthorizedMarkup = (string) ob_get_clean();
	$_GET = $savedGet;
	$assert(strpos($unauthorizedMarkup, 'Print Event-Day Guest List') === false, 'Unauthorized Event Plan view must not expose the report action.');
} finally {
	wp_set_current_user(0);
	wp_delete_post($attendeeId, true);
	if ($registeredTicketPostType && post_type_exists('tribe_wooticket')) {
		unregister_post_type('tribe_wooticket');
	}
}

if ($auditBefore !== null) {
	$auditAfter = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$auditTable}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test-only before/after mutation sentinel for the plugin-owned audit table.
	$assert($auditAfter === $auditBefore, 'Report build/render and discoverability checks must not write admission audit data.');
}

fwrite(STDOUT, 'PASS: ' . $assertions . " Event-Day report discoverability, identity, count, print, and security assertions.\n");
