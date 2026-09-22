<?php
declare(strict_types=1);
// Companion contract with the tracked Data Tools source, not a core dependency probe.

define('ABSPATH', __DIR__);

$root = dirname(__DIR__);
$portal_path = $root . '/includes/portal/vendor-portal.php';
$helpers_path = $root . '/includes/helpers.php';
$data_tools_path = $root . '/companion-plugins/vms-data-tools/includes/admin/page-reporting-module.php';
$data_tools_provider_path = $root . '/companion-plugins/vms-data-tools/includes/integrations/bvm-reporting-provider.php';
$reporting_contract_path = $root . '/includes/core/reporting-providers.php';
$portal_source = (string) file_get_contents($portal_path);
$helpers_source = (string) file_get_contents($helpers_path);
$data_tools_source = (string) file_get_contents($data_tools_path);
$data_tools_provider_source = (string) file_get_contents($data_tools_provider_path);
$reporting_contract_source = (string) file_get_contents($reporting_contract_path);

function bonus_progress_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function bonus_progress_same($expected, $actual, string $message): void
{
	bonus_progress_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function bonus_progress_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to find function ' . $name . '.');
	}

	$depth = 1;
	for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '{' ? 1 : 0;
		$depth -= $source[$index] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($index - $start) + 1);
		}
	}

	throw new RuntimeException('Unable to parse function ' . $name . '.');
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	$value = strtolower((string) $value);
	return (string) preg_replace('/[^a-z0-9_-]/', '', $value);
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function wp_strip_all_tags($value): string
{
	return strip_tags((string) $value);
}

function esc_html($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function number_format_i18n($value): string
{
	return number_format((int) $value);
}

function current_time(string $type)
{
	return $type === 'timestamp' ? 1788498000 : '2026-09-04 00:00:00';
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function wp_date(string $format, $timestamp = null, $timezone = null): string
{
	unset($timestamp, $timezone);
	return $format === 'Y-m-d' ? '2026-09-04' : 'Sep 4, 2026 12:00am';
}

$GLOBALS['bonus_progress_website'] = array();
$GLOBALS['bonus_progress_square'] = array();
$GLOBALS['bonus_progress_stats'] = array();
$GLOBALS['bonus_progress_guests'] = array();
$GLOBALS['bonus_progress_terms'] = array();
$GLOBALS['bonus_progress_section_cards'] = array(array('plan_id' => 2534));
$GLOBALS['bonus_progress_section_title'] = '';

final class WP_Post
{
	public int $ID;

	public function __construct(int $id)
	{
		$this->ID = $id;
	}
}

final class WP_Query
{
	/** @var WP_Post[] */
	public array $posts;

	public function __construct(array $args)
	{
		unset($args);
		$this->posts = array(new WP_Post(2537));
	}
}

function wp_reset_postdata(): void
{
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	if ($key === '_vms_event_date') {
		return $post_id === 2537 ? '2026-09-19' : '2026-09-05';
	}
	if ($key === '_vms_square_location_id' && $post_id === 2537) {
		return 'KING-GEORGE-SQUARE';
	}
	if ($key === '_vms_band_vendor_id' && $post_id === 2537) {
		return $single ? 77 : array(77);
	}
	return '';
}

function get_the_title(int $post_id): string
{
	if ($post_id === 2534) {
		return 'The Alternatives';
	}
	return $post_id === 2537 ? 'George Strait tribute: King George' : 'Test Event ' . $post_id;
}

function bvmgr_format_local_ymd(string $date, string $format): string
{
	unset($format);
	return $date;
}

function bvmgr_vendor_portal_format_stats_updated_label(array $stats): string
{
	return (string) ($stats['updated_label'] ?? '');
}

function vms_dt_reporting_build_website_detail_rows(int $plan_id): array
{
	return $GLOBALS['bonus_progress_website'][$plan_id] ?? array('ticket_rows' => array(), 'addon_rows' => array());
}

function vms_dt_reporting_build_square_line_evidence(int $plan_id, array $filters): array
{
	$GLOBALS['bonus_progress_square_filters'][$plan_id] = $filters;
	return $GLOBALS['bonus_progress_square'][$plan_id] ?? array(
		'ticket_rows' => array(),
		'warnings' => array(),
		'errors' => array(),
	);
}

function bvmgr_reporting_resolve_event_ticket_sales(int $plan_id, array $context = array()): array
{
	if (($context['scope'] ?? '') !== 'vendor_portal') {
		return array('available' => false, 'calculated' => false);
	}

	return array_merge(
		vms_dt_bvm_reporting_vendor_portal_summary($plan_id),
		array(
			'provider_id' => 'vms-data-tools',
			'provider_version' => '0.5.55',
			'provider_contract_version' => 1,
		)
	);
}

function bvmgr_vendor_portal_get_ticket_sales_snapshot(int $plan_id): array
{
	return $GLOBALS['bonus_progress_stats'][$plan_id] ?? array();
}

function bvmgr_vendor_portal_get_guest_admissions_count(int $plan_id): int
{
	return max(0, (int) ($GLOBALS['bonus_progress_guests'][$plan_id] ?? 0));
}

function bvmgr_staffing_get_event_plan_headcount_context(int $plan_id): array
{
	unset($plan_id);
	return array(
		'wired' => false,
		'headcount' => 0,
		'source' => 'none',
		'label' => 'Sales not wired yet',
	);
}

function bvmgr_event_plan_get_status(int $plan_id, string $context): string
{
	unset($plan_id, $context);
	return 'published';
}

function bvmgr_get_event_plan_comp_terms(int $plan_id): array
{
	return $GLOBALS['bonus_progress_terms'][$plan_id] ?? array();
}

function bvmgr_vendor_portal_get_bonus_progress_cards(int $vendor_id): array
{
	unset($vendor_id);
	return $GLOBALS['bonus_progress_section_cards'];
}

function bvmgr_vendor_portal_render_progress_cards_section(array $cards, string $title, bool $history_mode = false): void
{
	unset($cards, $history_mode);
	$GLOBALS['bonus_progress_section_title'] = $title;
}

bonus_progress_assert($portal_source !== '', 'Mirror Vendor Portal source should be readable.');
bonus_progress_assert($helpers_source !== '', 'BVM compensation helper source should be readable.');
bonus_progress_assert($data_tools_source !== '', 'Data Tools reporting source should be readable.');
bonus_progress_assert($data_tools_provider_source !== '', 'Data Tools BVM provider source should be readable.');
// Explicit companion contract: extract the tracked Data Tools provider into this isolated
// test process. BVM runtime does not boot the inactive companion from its source mirror.
eval(bonus_progress_extract_function($data_tools_source, 'vms_dt_reporting_zero_ticket_source_rollup'));
eval(bonus_progress_extract_function($data_tools_source, 'vms_dt_reporting_build_ticket_source_rollup'));
eval(bonus_progress_extract_function($data_tools_provider_source, 'vms_dt_bvm_reporting_complimentary_label'));
eval(bonus_progress_extract_function($data_tools_provider_source, 'vms_dt_bvm_reporting_complimentary_categories'));
eval(bonus_progress_extract_function($data_tools_provider_source, 'vms_dt_bvm_reporting_vendor_portal_summary'));
eval(bonus_progress_extract_function($reporting_contract_source, 'bvmgr_reporting_normalize_complimentary_categories'));
eval(bonus_progress_extract_function($helpers_source, 'bvmgr_attendance_bonus_supported_modes'));
eval(bonus_progress_extract_function($helpers_source, 'bvmgr_normalize_attendance_bonus_mode'));
eval(bonus_progress_extract_function($helpers_source, 'bvmgr_normalize_comp_nonnegative_float'));
eval(bonus_progress_extract_function($helpers_source, 'bvmgr_normalize_comp_nonnegative_int'));
eval(bonus_progress_extract_function($helpers_source, 'bvmgr_calculate_attendance_bonus_payout'));
eval(bonus_progress_extract_function($helpers_source, 'bvmgr_get_attendance_bonus_progress_snapshot'));
eval(bonus_progress_extract_function($portal_source, 'bvmgr_vendor_portal_get_data_tools_sales_snapshot'));
eval(bonus_progress_extract_function($portal_source, 'bvmgr_vendor_portal_get_count_breakdown'));
eval(bonus_progress_extract_function($portal_source, 'bvmgr_vendor_portal_render_count_breakdown_markup'));
eval(bonus_progress_extract_function($portal_source, 'bvmgr_vendor_portal_get_progress_headcount_context'));
$builder_source = bonus_progress_extract_function($portal_source, 'bvmgr_vendor_portal_build_bonus_progress_card');
eval($builder_source);
eval(bonus_progress_extract_function($portal_source, 'bvmgr_vendor_portal_get_past_assigned_event_rows'));
eval(bonus_progress_extract_function($portal_source, 'bvmgr_vendor_portal_render_bonus_progress_section'));

$terms = array(
	'structure' => 'attendance_bonus',
	'flat_fee_amount' => 1500,
	'attendance_bonus_mode' => 'step',
	'attendance_bonus_start_count' => 100,
	'attendance_bonus_step_size' => 50,
	'attendance_bonus_step_bonus' => 250,
);

// The production-shaped case: 20 paid presales, four free tickets, eight comp/pass admissions, and add-ons.
$GLOBALS['bonus_progress_terms'][2534] = $terms;
$GLOBALS['bonus_progress_website'][2534] = array(
	'ticket_rows' => array(
		array('quantity' => 20, 'refunded_quantity' => 0, 'net_subtotal_cents' => 38002, 'sold_date' => '2026-09-01'),
		array('quantity' => 4, 'refunded_quantity' => 0, 'net_subtotal_cents' => 0, 'sold_date' => '2026-09-02'),
	),
	'addon_rows' => array(
		array('quantity' => 11, 'refunded_quantity' => 0, 'net_subtotal_cents' => 22000),
	),
);
$GLOBALS['bonus_progress_square'][2534] = array('ticket_rows' => array(), 'warnings' => array(), 'errors' => array());
$GLOBALS['bonus_progress_stats'][2534] = array(
	'qty_sold' => 20,
	'source_mode' => 'live',
	'ticket_product_ids' => array(6541),
	'all_ticket_product_ids' => array(6541, 6547),
);
$GLOBALS['bonus_progress_guests'][2534] = 8;

$merged = bvmgr_vendor_portal_get_data_tools_sales_snapshot(2534);
bonus_progress_same(24, $merged['headcount'], 'The regression fixture should reproduce the old paid-plus-free merged headline.');
bonus_progress_same(20, $merged['paid_ticket_qty_total'], 'Data Tools rollup should retain the authoritative paid total.');
bonus_progress_same(4, $merged['free_ticket_qty_total'], 'Data Tools rollup should retain free tickets separately.');
bonus_progress_same(20, $merged['online_qty'], 'F. Add-on rows should not increase the paid admission count.');

$card = bvmgr_vendor_portal_build_bonus_progress_card(2534, false);
bonus_progress_same(20, $card['attendance_count'], 'A. Current count should use 20 paid presales, not merged ticketed attendance.');
bonus_progress_same(20, $card['snapshot']['attendance_count'], 'A. Canonical bonus math should receive the same paid count shown to the vendor.');
bonus_progress_same(130, $card['snapshot']['tickets_to_next'], 'B. Twenty paid tickets should leave 130 to the threshold at 150.');
bonus_progress_same(0.0, $card['snapshot']['current_bonus'], 'Free and comp admissions should not unlock a bonus.');
bonus_progress_same(1500.0, $card['snapshot']['projected_total'], 'Projected payout should use the paid-basis bonus result.');
bonus_progress_same(20 / 150, $card['snapshot']['meter_percent'], 'Progress meter should use the same paid basis.');
bonus_progress_same(20, $card['count_breakdown']['presales'], 'Count Source should show paid presales separately.');
bonus_progress_same(0, $card['count_breakdown']['door_sales'], 'Count Source should show paid door sales separately.');
bonus_progress_same(12, $card['count_breakdown']['comp_guest'], 'D/E. Free tickets and comp/pass admissions should remain visible only in Comped / guest list.');
bonus_progress_same('Comped / guest list', $card['count_breakdown']['lines'][2]['label'], 'Comp/guest source label changed.');

// Paid presales and eligible paid door sales both advance every bonus value; free tickets still do not.
$GLOBALS['bonus_progress_terms'][2535] = $terms;
$GLOBALS['bonus_progress_website'][2535] = array(
	'ticket_rows' => array(
		array('quantity' => 130, 'refunded_quantity' => 0, 'net_subtotal_cents' => 260000, 'sold_date' => '2026-09-01'),
		array('quantity' => 7, 'refunded_quantity' => 0, 'net_subtotal_cents' => 0, 'sold_date' => '2026-09-01'),
	),
	'addon_rows' => array(array('quantity' => 30, 'net_subtotal_cents' => 30000)),
);
$GLOBALS['bonus_progress_square'][2535] = array(
	'ticket_rows' => array(
		array('treatment' => 'counted', 'is_direct_ticket' => true, 'quantity' => 25, 'net_cents' => 50000),
		array('treatment' => 'counted', 'is_direct_ticket' => true, 'quantity' => 3, 'net_cents' => 0),
	),
	'warnings' => array(),
	'errors' => array(),
);
$GLOBALS['bonus_progress_stats'][2535] = array('qty_sold' => 130, 'ticket_product_ids' => array(7001));
$GLOBALS['bonus_progress_guests'][2535] = 2;

$door_card = bvmgr_vendor_portal_build_bonus_progress_card(2535, false);
bonus_progress_same(155, $door_card['attendance_count'], 'C. Paid presales plus eligible paid door sales should form the bonus basis.');
bonus_progress_same(130, $door_card['count_breakdown']['presales'], 'C. Presales source value changed.');
bonus_progress_same(25, $door_card['count_breakdown']['door_sales'], 'C. Eligible paid door source value changed.');
bonus_progress_same(12, $door_card['count_breakdown']['comp_guest'], 'D/E. Free online, free door and guest admissions should remain excluded.');
bonus_progress_same(250.0, $door_card['snapshot']['current_bonus'], 'Current bonus should be calculated from the 155 paid basis.');
bonus_progress_same(1750.0, $door_card['snapshot']['projected_total'], 'Projected payout should use the paid-basis bonus.');
bonus_progress_same(200, $door_card['snapshot']['next_threshold_count'], 'Next threshold should be evaluated from the paid basis.');
bonus_progress_same(45, $door_card['snapshot']['tickets_to_next'], 'To-go value should be evaluated from the paid basis.');

// A linked, valid attendance-bonus event must render even when every paid count is zero.
$GLOBALS['bonus_progress_terms'][2536] = $terms;
$GLOBALS['bonus_progress_website'][2536] = array(
	'ticket_rows' => array(),
	'addon_rows' => array(array('quantity' => 9, 'net_subtotal_cents' => 9000)),
);
$GLOBALS['bonus_progress_square'][2536] = array('ticket_rows' => array(), 'warnings' => array(), 'errors' => array());
$GLOBALS['bonus_progress_stats'][2536] = array(
	'qty_sold' => 0,
	'source_mode' => 'live',
	'ticket_product_ids' => array(7101),
	'all_ticket_product_ids' => array(7101, 7102),
);
$GLOBALS['bonus_progress_guests'][2536] = 0;

$zero_card = bvmgr_vendor_portal_build_bonus_progress_card(2536, false);
bonus_progress_assert($zero_card !== array(), 'G. A valid linked zero-sales attendance-bonus card should still render.');
bonus_progress_same(0, $zero_card['attendance_count'], 'G. Zero paid sales should remain zero progress.');
bonus_progress_same(150, $zero_card['snapshot']['tickets_to_next'], 'G. Zero paid sales should retain the first threshold.');

// Issue #10: King-George-shaped canonical truth, without event-specific production code.
$GLOBALS['bonus_progress_terms'][2537] = $terms;
$GLOBALS['bonus_progress_website'][2537] = array(
	'ticket_rows' => array(
		array('item_name' => '2026-09-19 19:00 - General Admission', 'quantity' => 136, 'refunded_quantity' => 0, 'net_subtotal_cents' => 272000, 'sold_date' => '2026-09-18'),
		array('item_name' => '2026-09-19 19:00 - Veteran Admission', 'quantity' => 18, 'refunded_quantity' => 0, 'net_subtotal_cents' => 0, 'sold_date' => '2026-09-18'),
		array('item_name' => '2026-09-19 19:00 - Police / Fire / EMT Admission', 'quantity' => 4, 'refunded_quantity' => 0, 'net_subtotal_cents' => 0, 'sold_date' => '2026-09-18'),
		array('item_name' => "2026-09-19 19:00 - Child's Admission (12 & under)", 'quantity' => 6, 'refunded_quantity' => 0, 'net_subtotal_cents' => 0, 'sold_date' => '2026-09-18'),
		array('item_name' => '2026-09-19 19:00 - Public School Teacher Admission', 'quantity' => 1, 'refunded_quantity' => 0, 'net_subtotal_cents' => 0, 'sold_date' => '2026-09-18'),
	),
	'addon_rows' => array(
		array('item_name' => 'Fan rental', 'quantity' => 2, 'refunded_quantity' => 0, 'net_subtotal_cents' => 4000),
		array('item_name' => 'Ticket add-on', 'quantity' => 5, 'refunded_quantity' => 0, 'net_subtotal_cents' => 5000),
	),
);
$GLOBALS['bonus_progress_square'][2537] = array(
	'ticket_rows' => array(
		array('line_name' => 'Door admission', 'treatment' => 'counted', 'is_direct_ticket' => true, 'quantity' => 8, 'net_cents' => 16000),
		array('line_name' => 'Merchandise', 'treatment' => 'counted', 'is_direct_ticket' => false, 'quantity' => 20, 'net_cents' => 40000),
	),
	'warnings' => array(),
	'errors' => array(),
);
$GLOBALS['bonus_progress_stats'][2537] = array('qty_sold' => 136, 'ticket_product_ids' => array(9001));
$GLOBALS['bonus_progress_guests'][2537] = 2;

$king_card = bvmgr_vendor_portal_build_bonus_progress_card(2537, true);
bonus_progress_same('KING-GEORGE-SQUARE', $GLOBALS['bonus_progress_square_filters'][2537]['square_location_id'] ?? '', 'The provider must scope Square truth to the Event Plan location.');
bonus_progress_same(136, $king_card['count_breakdown']['presales'], 'King George paid website admissions changed.');
bonus_progress_same(8, $king_card['count_breakdown']['door_sales'], 'King George paid Square door admissions changed.');
bonus_progress_same(31, $king_card['count_breakdown']['comp_guest'], 'King George complimentary aggregate changed.');
bonus_progress_same(144, $king_card['attendance_count'], 'King George final paid count must be paid presales plus paid door only.');
bonus_progress_same(144, $king_card['snapshot']['attendance_count'], 'King George bonus basis diverged from the displayed paid count.');
bonus_progress_same(0.0, $king_card['snapshot']['current_bonus'], 'King George free tickets or guest passes entered the bonus basis.');
bonus_progress_same(1500.0, $king_card['snapshot']['projected_total'], 'King George final payout changed.');

$expected_comp_detail = array(
	array('key' => 'veterans', 'label' => 'Veterans', 'qty' => 18),
	array('key' => 'children12under', 'label' => 'Children 12 & under', 'qty' => 6),
	array('key' => 'policefireemt', 'label' => 'Police / Fire / EMT', 'qty' => 4),
	array('key' => 'publicschoolteacher', 'label' => 'Public School Teacher', 'qty' => 1),
	array('key' => 'guest_passes', 'label' => 'Guest passes', 'qty' => 2),
);
bonus_progress_same($expected_comp_detail, $king_card['count_breakdown']['comp_detail'], 'King George complimentary detail changed.');
bonus_progress_same(31, array_sum(array_column($king_card['count_breakdown']['comp_detail'], 'qty')), 'Complimentary detail must reconcile to the aggregate.');

$past_rows = bvmgr_vendor_portal_get_past_assigned_event_rows(77, 1);
bonus_progress_same(1, count($past_rows), 'Past Shows should include the completed King George fixture once.');
bonus_progress_same(144, $past_rows[0]['attendance_count'] ?? null, 'Past Shows must use the same canonical paid count as Past Show Performance.');
bonus_progress_same($king_card['count_breakdown'], $past_rows[0]['count_breakdown'] ?? array(), 'Past Shows and Past Show Performance must share one canonical breakdown.');

$king_markup = bvmgr_vendor_portal_render_count_breakdown_markup($king_card['count_breakdown']);
foreach (array('Complimentary admissions — 31', 'Veterans', 'Children 12 &amp; under', 'Police / Fire / EMT', 'Public School Teacher', 'Guest passes') as $expected_markup) {
	bonus_progress_assert(strpos($king_markup, $expected_markup) !== false, 'Vendor-facing complimentary detail is missing: ' . $expected_markup);
}

bonus_progress_assert(strpos($portal_source, "__('Final paid count: %d', 'backstage-venue-manager')") !== false, 'Event History must label the canonical value as Final paid count.');

// Dashboard heading and Profile placement remain separate contracts.
bvmgr_vendor_portal_render_bonus_progress_section(2533, 'dashboard');
bonus_progress_same('Vendor Bonus Progress', $GLOBALS['bonus_progress_section_title'], 'I. Dashboard heading should be Vendor Bonus Progress.');
$profile_start = strpos($portal_source, "} elseif (\$tab === 'profile') {");
$profile_end = $profile_start === false ? false : strpos($portal_source, "} elseif (\$tab === 'tax-profile') {", $profile_start);
bonus_progress_assert($profile_start !== false && $profile_end !== false, 'Profile branch should remain discoverable.');
$profile_branch = substr($portal_source, $profile_start, $profile_end - $profile_start);
bonus_progress_assert(strpos($profile_branch, 'render_bonus_progress_section') === false, 'H. Profile should remain free of the bonus-progress renderer.');

// The builder must orchestrate existing contracts only; source/revenue calculations stay in their owners.
foreach (array(
	'bvmgr_vendor_portal_get_progress_headcount_context',
	'bvmgr_vendor_portal_get_count_breakdown',
	'bvmgr_get_attendance_bonus_progress_snapshot',
) as $required_contract) {
	bonus_progress_assert(strpos($builder_source, $required_contract . '(') !== false, 'J. Builder should retain canonical contract: ' . $required_contract);
}
foreach (array(
	'vms_dt_reporting_build_website_detail_rows',
	'vms_dt_reporting_build_square_line_evidence',
	'bvmgr_ticket_revenue_',
	'wc_get_orders',
	'WP_Query',
) as $forbidden_calculation) {
	bonus_progress_assert(strpos($builder_source, $forbidden_calculation) === false, 'J. Builder should not duplicate source/revenue calculation: ' . $forbidden_calculation);
}

fwrite(STDOUT, "Vendor Portal paid bonus-basis regression OK.\n");
