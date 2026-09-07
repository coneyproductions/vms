<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
$plugin_root = dirname(__DIR__);
require_once $plugin_root . '/includes/core/financial-ticket-source.php';
require_once $plugin_root . '/includes/core/financial-snapshot.php';
$staffing_path = $plugin_root . '/includes/core/staffing.php';
$command_center_path = $plugin_root . '/includes/admin/event-command-center.php';
$event_plans_path = $plugin_root . '/includes/cpt/event-plans.php';

function p0_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function p0_same($expected, $actual, string $message): void
{
	p0_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function p0_extract_function(string $source, string $name): string
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
	return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $value));
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function human_time_diff(int $from, int $to = 0): string
{
	$seconds = abs(($to > 0 ? $to : time()) - $from);
	return (string) max(0, (int) floor($seconds / 3600)) . ' hours';
}

function bvmgr_event_command_center_money(int $cents): string
{
	return '$' . number_format($cents / 100, 2, '.', ',');
}

$staffing_source = (string) file_get_contents($staffing_path);
$command_center_source = (string) file_get_contents($command_center_path);
$command_center_dashboard_source = (string) file_get_contents($plugin_root . '/includes/admin/event-command-center-dashboard.php');
$event_plans_source = (string) file_get_contents($event_plans_path);
p0_assert($staffing_source !== '' && $command_center_source !== '' && $event_plans_source !== '', 'P0 source files must be readable.');

foreach (array(
	'bvmgr_staffing_normalize_role_activation_thresholds',
	'bvmgr_staffing_build_event_snapshot',
	'bvmgr_staffing_resolve_event_snapshot',
	'bvmgr_staffing_reconcile_existing_assignment_rows',
) as $function_name) {
	eval(p0_extract_function($staffing_source, $function_name));
}

$GLOBALS['p0_rollup_compute_calls'] = 0;
function bvmgr_staffing_compute_rollup(int $event_plan_id): array
{
	$GLOBALS['p0_rollup_compute_calls']++;
	return array(
		'ok' => true,
		'event_plan_id' => $event_plan_id,
		'readiness_status' => 'ready',
		'headcount_needed_total' => 999,
		'headcount_filled_total' => 999,
		'open_headcount_total' => 0,
		'conflict_count' => 0,
		'computed_at' => '2026-09-05 12:00:00',
	);
}
function bvmgr_staffing_derive_rollup(int $event_plan_id, ?array $slots = null, ?array $roles = null): array { return array('ok' => true, 'conflict_count' => 0); }
foreach (array(
	'bvmgr_event_command_center_summarize_ticket_report_rows',
	'bvmgr_event_command_center_normalize_ticket_cache',
	'bvmgr_event_command_center_build_ticket_sales_snapshot',
	'bvmgr_event_command_center_ticket_activity_from_cache',
) as $function_name) {
	eval(p0_extract_function($command_center_source, $function_name));
}

$role_map = array(
	10 => array('name' => 'Security', 'is_critical' => 1),
	20 => array('name' => 'Box Office', 'is_critical' => 0),
);
$headcount = array('wired' => true, 'headcount' => 40, 'source' => 'anticipated_guests');
$fresh_rollup = array(
	'computed_at' => '2026-09-05 12:00:00',
	'conflict_count' => 0,
	'unavailable_assigned_count' => 0,
);
$slot = static function (int $slot_id, int $role_id, int $needed, array $assignments = array(), string $status = 'active'): array {
	return array(
		'slot_id' => $slot_id,
		'role_id' => $role_id,
		'role_name' => $role_id === 10 ? 'Security' : 'Box Office',
		'headcount_needed' => $needed,
		'status' => $status,
		'assignments' => $assignments,
	);
};
$assignment = static function (int $assignment_id, int $staff_id, string $status): array {
	return array('assignment_id' => $assignment_id, 'staff_id' => $staff_id, 'status' => $status);
};
$snapshot = static function (array $slots, array $legacy = array(), array $thresholds = array(), ?array $headcount_override = null, array $rollup_override = array()) use ($role_map, $headcount, $fresh_rollup): array {
	return bvmgr_staffing_build_event_snapshot(
		2534,
		$slots,
		$legacy,
		$role_map,
		$thresholds,
		$headcount_override ?? $headcount,
		array_merge($fresh_rollup, $rollup_override),
		'fresh'
	);
};

// 1. Normalized slot with no assignments.
$case = $snapshot(array($slot(1, 10, 2)));
p0_same(array(2, 0, 2), array($case['headcount_needed_total'], $case['headcount_filled_total'], $case['open_headcount_total']), 'No-assignment staffing totals are inconsistent.');

// 2. Legacy-only data remains available with explicit provenance and unknown status.
$case = $snapshot(array(), array(10 => array(101, 101, 102)));
p0_same('legacy', $case['authority'], 'Legacy-only authority was not selected.');
p0_same(2, $case['legacy_status_unknown_headcount'], 'Legacy-only status provenance was lost.');

// 3. Proposed and 4. confirmed assignments both fill coverage but remain distinct.
$case = $snapshot(array($slot(1, 10, 2, array($assignment(1, 101, 'proposed'), $assignment(2, 102, 'confirmed')))));
p0_same(array(2, 1, 1, 0), array($case['headcount_filled_total'], $case['proposed_headcount'], $case['confirmed_headcount'], $case['open_headcount_total']), 'Proposed/confirmed coverage split is inconsistent.');

// 5. Canceled assignments do not fill the slot.
$case = $snapshot(array($slot(1, 10, 1, array($assignment(1, 101, 'canceled')))));
p0_same(array(0, 1), array($case['headcount_filled_total'], $case['open_headcount_total']), 'Canceled assignment incorrectly filled staffing coverage.');

// 6. Any normalized record makes legacy data non-authoritative for every role.
$case = $snapshot(array($slot(1, 10, 1)), array(20 => array(201)));
p0_same('normalized_legacy_ignored', $case['provenance'], 'Mixed storage did not record ignored legacy data.');
p0_assert(!isset($case['roles_by_id'][20]), 'Mixed storage leaked a legacy role into normalized truth.');

// 7-9. Runtime resolver derives missing, dirty, and incomplete rollups without calling the writer.
$resolver_args = array(
	'slots' => array($slot(1, 10, 1, array($assignment(1, 101, 'confirmed')))),
	'legacy_assignments' => array(),
	'role_map' => $role_map,
	'activation_thresholds' => array(),
	'headcount_context' => $headcount,
);
foreach (array(
	'missing' => null,
	'dirty' => array('dirty' => 1, 'computed_at' => '2026-09-05 11:00:00'),
	'incomplete' => array('dirty' => 0, 'computed_at' => '2026-09-05 11:00:00'),
) as $rollup_case => $rollup_value) {
	$resolved = bvmgr_staffing_resolve_event_snapshot(2534, $resolver_args + array('rollup' => $rollup_value));
	p0_same('derived_' . $rollup_case, $resolved['rollup_state'], ucfirst($rollup_case) . ' rollup was not derived in memory.');
	p0_same(array(1, 1, 0), array($resolved['headcount_needed_total'], $resolved['headcount_filled_total'], $resolved['open_headcount_total']), ucfirst($rollup_case) . ' rollup leaked inconsistent stored totals.');
}
p0_same(0, $GLOBALS['p0_rollup_compute_calls'], 'A reader called the persistent rollup writer.');

// 10. Duplicate slot/staff rows count once, and reconciliation keeps confirmed.
$case = $snapshot(array($slot(1, 10, 1, array($assignment(1, 101, 'proposed'), $assignment(2, 101, 'confirmed')))));
p0_same(array(1, 0, 1, 1), array($case['headcount_filled_total'], $case['proposed_headcount'], $case['confirmed_headcount'], $case['duplicate_assignment_count']), 'Duplicate normalized assignment was not collapsed to confirmed.');
$plan = bvmgr_staffing_reconcile_existing_assignment_rows(array(
	$assignment(5, 101, 'proposed'),
	$assignment(8, 101, 'confirmed'),
	$assignment(9, 101, 'canceled'),
));
p0_same('confirmed', $plan['primary_by_staff'][101]['status'], 'Matrix reconciliation did not retain confirmed status.');
p0_same(array(5, 9), $plan['duplicate_assignment_ids'], 'Matrix reconciliation did not identify non-primary duplicate rows.');
p0_assert(strpos($staffing_source, 'bvmgr_staffing_matrix_proposals($slot_id, $staff_ids)') !== false, 'Matrix must delegate state preservation to the canonical lifecycle repository; real retained-state behavior is tested with disposable SQL.');

// 11. One person in two roles fills two planned positions but is one unique person.
$case = $snapshot(array(
	$slot(1, 10, 1, array($assignment(1, 101, 'confirmed'))),
	$slot(2, 20, 1, array($assignment(2, 101, 'proposed'))),
));
p0_same(array(2, 1), array($case['headcount_filled_total'], $case['unique_assigned_people_count']), 'Cross-role staffing assignment/people counts were conflated.');

// 12. Headcount greater than one preserves the full planned/open count.
$case = $snapshot(array($slot(1, 10, 3, array($assignment(1, 101, 'confirmed')))));
p0_same(array(3, 1, 2), array($case['headcount_needed_total'], $case['headcount_filled_total'], $case['open_headcount_total']), 'Multi-headcount staffing slot was flattened.');

// 13. A future activation threshold is planned/open but not required/open now.
$case = $snapshot(array($slot(1, 10, 2)), array(), array(10 => 50), array('wired' => true, 'headcount' => 40));
p0_same(array(2, 0, 2, 0), array($case['headcount_needed_total'], $case['required_now_headcount_total'], $case['open_positions'], $case['open_headcount_total']), 'Thresholded staffing role was marked required too early.');

// All three staffing surfaces are wired to the same resolver and exact shared fields.
p0_assert(strpos($event_plans_source, 'bvmgr_staffing_resolve_event_snapshot($post_id') !== false, 'Event Plan role cards do not use the shared staffing resolver.');
p0_assert(strpos($command_center_source, 'bvmgr_staffing_resolve_event_snapshot($plan_id)') !== false, 'Full Command Center does not use the shared staffing resolver.');
p0_assert(strpos($command_center_source, 'return bvmgr_event_command_center_get_staffing_snapshot($plan_id);') !== false, 'Light Event Plan summary does not delegate to the full shared staffing projection.');
$surface_case = $snapshot(array(
	$slot(1, 10, 2, array($assignment(1, 101, 'confirmed'))),
	$slot(2, 20, 1, array($assignment(2, 101, 'proposed'))),
));
$role_card_surface = array(
	'planned' => array_sum(array_column($surface_case['roles'], 'headcount_needed')),
	'required_now' => array_sum(array_column($surface_case['roles'], 'required_now_headcount')),
	'assigned' => array_sum(array_column($surface_case['roles'], 'assigned_headcount')),
	'proposed' => array_sum(array_column($surface_case['roles'], 'proposed_headcount')),
	'confirmed' => array_sum(array_column($surface_case['roles'], 'confirmed_headcount')),
	'open_now' => array_sum(array_column($surface_case['roles'], 'required_open_positions')),
);
$event_plan_summary_surface = array(
	'planned' => $surface_case['headcount_needed_total'],
	'required_now' => $surface_case['required_now_headcount_total'],
	'assigned' => $surface_case['headcount_filled_total'],
	'proposed' => $surface_case['proposed_headcount'],
	'confirmed' => $surface_case['confirmed_headcount'],
	'open_now' => $surface_case['open_headcount_total'],
);
$full_command_center_surface = array(
	'planned' => $surface_case['planned_headcount'],
	'required_now' => $surface_case['required_now_headcount_total'],
	'assigned' => $surface_case['assigned_headcount'],
	'proposed' => $surface_case['proposed_headcount'],
	'confirmed' => $surface_case['confirmed_headcount'],
	'open_now' => $surface_case['required_open_positions'],
);
p0_same($role_card_surface, $event_plan_summary_surface, 'Event Plan summary diverged from role-card staffing fields.');
p0_same($role_card_surface, $full_command_center_surface, 'Full Command Center diverged from role-card staffing fields.');

$now = 2_000_000_000;
$stale_after = 3600;
$empty_truth = array('available' => false, 'calculated' => false, 'warnings' => array());
$live_truth = static function (string $source, int $paid, int $free, int $revenue): array {
	return array(
		'available' => true,
		'calculated' => true,
		'source' => $source,
		'source_label' => $source,
		'paid_qty' => $paid,
		'free_qty' => $free,
		'total_qty' => $paid + $free,
		'revenue_cents' => $revenue,
		'warnings' => array(),
	);
};
$ticket = static function (array $truth, array $v1 = array(), array $v2 = array(), int $forecast = 0, int $true = 0) use ($now, $stale_after): array {
	return bvmgr_event_command_center_build_ticket_sales_snapshot($truth, $v1, $v2, $forecast, $true, $now, $stale_after);
};

// 1. Current explicit zero is not missing.
$case = $ticket($empty_truth, array('provider' => 'woocommerce', 'qty_sold' => 0, 'revenue_cents' => 0, 'computed_at_gmt' => $now - 30));
p0_same(array('VALID_ZERO', true, 0, 0), array($case['ticket_state'], $case['display_sales'], $case['sold'], $case['revenue_cents']), 'Valid zero ticket cache was not preserved.');

// 2. Paid-only current cache.
$case = $ticket($empty_truth, array('provider' => 'woocommerce', 'qty_sold' => 4, 'revenue_cents' => 8000, 'computed_at_gmt' => $now - 30));
p0_same(array('CURRENT', 4, 8000), array($case['ticket_state'], $case['sold'], $case['revenue_cents']), 'Paid-only ticket cache is inconsistent.');

// 3. Paid plus free live truth.
$case = $ticket($live_truth('dt_reporting_model', 4, 2, 8000));
p0_same(array(4, 2, 6, 'transaction_free'), array($case['sold'], $case['comp_count'], $case['total_ticket_count'], $case['comp_count_basis']), 'Paid/free live totals are inconsistent.');

// 4. Refunded quantity and add-ons are excluded from net ticket truth.
$refunds = bvmgr_event_command_center_summarize_ticket_report_rows(array(
	array('item_kind' => 'ticket', 'quantity' => 5, 'refunded_quantity' => 2, 'net_subtotal_cents' => 6000),
	array('item_kind' => 'ticket', 'quantity' => 2, 'refunded_quantity' => 0, 'net_subtotal_cents' => 0),
	array('item_kind' => 'ticket', 'quantity' => 1, 'refunded_quantity' => 1, 'net_subtotal_cents' => 0),
	array('item_kind' => 'addon', 'quantity' => 8, 'refunded_quantity' => 0, 'net_subtotal_cents' => 4000),
));
p0_same(array('paid_qty' => 3, 'free_qty' => 2, 'total_qty' => 5, 'revenue_cents' => 6000), $refunds, 'Refund-aware ticket row summary is wrong.');

// 5. No stats is unavailable, never zero.
$case = $ticket($empty_truth);
p0_same(array('UNAVAILABLE', false, null, null), array($case['ticket_state'], $case['display_sales'], $case['sold'], $case['revenue_cents']), 'Missing ticket stats were presented as zero.');

// 6. Old valid cache remains displayable but explicitly stale.
$case = $ticket($empty_truth, array('qty_sold' => 3, 'revenue_cents' => 4500, 'computed_at_gmt' => $now - 7200));
p0_same(array('STALE', true, 3), array($case['ticket_state'], $case['display_sales'], $case['sold']), 'Old valid ticket cache was not marked stale.');

// 7. Mapping-only pending payload never renders zero or a refreshed claim.
$pending_v1 = array('provider' => 'pending_refresh', 'computed_at_gmt' => $now - 20);
$pending_v2 = array('provider' => 'pending_refresh', 'computed_at_gmt' => $now - 20, 'ticket_product_ids' => array(11));
$case = $ticket($empty_truth, $pending_v1, $pending_v2);
p0_same(array('PENDING_REFRESH', false, null, null), array($case['ticket_state'], $case['display_sales'], $case['sold'], $case['revenue_cents']), 'Pending ticket cache rendered fabricated zero totals.');
$activity = bvmgr_event_command_center_ticket_activity_from_cache($pending_v1, $pending_v2, $now);
p0_same('Product mapping reconciled', $activity['title'], 'Mapping-only activity was labeled as a sales refresh.');

// 8. V2 mapping presence does not override a valid V1 sales calculation.
$case = $ticket($empty_truth, array('provider' => 'woocommerce', 'qty_sold' => 2, 'revenue_cents' => 3000, 'computed_at_gmt' => $now - 20), $pending_v2);
p0_same('CURRENT', $case['ticket_state'], 'V2 mapping metadata incorrectly overrode valid V1 sales.');

// 9. Data Tools current truth is preferred over cache.
$case = $ticket($live_truth('dt_reporting_model', 7, 1, 14000), array('qty_sold' => 1, 'revenue_cents' => 100, 'computed_at_gmt' => $now - 7200));
p0_same(array('dt_reporting_model', 7, 14000), array($case['ticket_source'], $case['sold'], $case['revenue_cents']), 'Data Tools reporting truth was not preferred.');

// 10. Core Woo report is the live fallback when Data Tools is absent.
$case = $ticket($live_truth('core_ticket_revenue', 5, 0, 10000));
p0_same(array('core_ticket_revenue', 'CURRENT', 5), array($case['ticket_source'], $case['ticket_state'], $case['sold']), 'Core Woo reporting fallback is inconsistent.');

// 11. When live reporting is unavailable, valid cache remains the explicit fallback.
$case = $ticket($empty_truth, array('provider' => 'woocommerce', 'qty_sold' => 6, 'revenue_cents' => 12000, 'computed_at_gmt' => $now - 20));
p0_same(array('cached_ticket_stats', 'CURRENT', 6), array($case['ticket_source'], $case['ticket_state'], $case['sold']), 'Valid cache fallback failed when Woo reporting was unavailable.');

// Full and light ticket surfaces consume the same resolver fields and pending-safe copy.
p0_assert(substr_count($command_center_source, 'bvmgr_event_command_center_resolve_ticket_sales_snapshot(') >= 3, 'Ticket reporting surfaces are not wired to the shared resolver.');
p0_assert(strpos($command_center_source, 'bvmgr_event_command_center_render_dashboard($plan_id, bvmgr_event_command_center_build_payload($plan_id))') !== false, 'Full Command Center must render the shared payload through its dashboard.');
p0_assert(strpos($command_center_dashboard_source, "\$available = !empty(\$ticket['display_sales'])") !== false && strpos($command_center_dashboard_source, "\$available ? (string) (\$ticket['sold'] ?? 0) : \$unknown") !== false, 'Full Command Center must hide pending/unavailable paid totals.');
p0_assert(strpos($command_center_source, "(string) (\$ticket['sales_summary_label']") !== false, 'Module hub must use the shared state-aware sales summary.');
p0_assert(strpos($command_center_dashboard_source, 'bvmgr_financial_render_summary($financial)') !== false, 'ECC must render shared financial authority.');
p0_assert(strpos($command_center_source, 'return bvmgr_financial_get_event_snapshot($plan_id);') !== false, 'ECC must resolve the shared financial contract.');

echo "P0 source consistency repair tests passed.\n";
