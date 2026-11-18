<?php
/** Financial authority v1. Read-only interpretation; no accounting finalization. */
defined('ABSPATH') || exit;

/** All amounts are cents. Null is unavailable; zero is a real observation. */
function bvmgr_financial_value($amount, string $basis, string $source, string $scope, array $evidence = array()): array
{
    return array_merge(array(
        'amount_cents' => is_numeric($amount) ? (int) $amount : null,
        'basis' => is_numeric($amount) ? $basis : 'UNAVAILABLE',
        'source' => $source,
        'scope' => $scope,
        'provider_id' => '',
        'calculated_at_utc' => null,
        'freshness' => 'unknown',
        'confidence' => 'partial',
        'completeness' => 'partial',
        'finalized' => false,
        'verified' => false,
    ), $evidence);
}

/** Provider freshness is optional in contract v1; never invent an upstream sync time. */
function bvmgr_financial_source_is_stale(array $freshness): bool
{
    return !empty($freshness['stale']) || !empty($freshness['is_stale'])
        || in_array(strtolower((string) ($freshness['state'] ?? $freshness['status'] ?? '')), array('stale', 'unavailable', 'error'), true);
}

/** Pure interpretation of independently sourced financial observations. */
function bvmgr_financial_build_snapshot(int $plan_id, array $input): array
{
    $ticket = (array) ($input['ticket'] ?? array());
    $freshness = (array) ($ticket['freshness'] ?? array());
    $stale = bvmgr_financial_source_is_stale($freshness);
    $available = !empty($ticket['available']) && !empty($ticket['calculated']) && !$stale;
    $receipt = bvmgr_financial_value($available ? ($ticket['revenue_cents'] ?? null) : null,
        'TRANSACTIONAL_ACTUAL', (string) ($ticket['source'] ?? ''), 'ticket_channels_only', array(
            'source_label' => (string) ($ticket['source_label'] ?? ''),
            'provider_id' => (string) ($ticket['provider_id'] ?? ''),
            'provider_version' => (string) ($ticket['provider_version'] ?? ''),
            'freshness' => $stale ? 'stale' : ($available ? 'calculated_from_available_records' : 'unavailable'),
            'provider_freshness' => $freshness,
            'calculated_at_utc' => $input['calculated_at_utc'] ?? null,
            'amount_definition' => 'Ticket receipts: Woo net of refunds; POS follows provider scope. Not accounting gross.',
        ));
    $manual = (array) ($input['manual'] ?? array());
    $costs = array();
    foreach (array('direct', 'processing') as $key) {
        $costs[$key] = bvmgr_financial_value($manual[$key] ?? null, 'MANUAL_ACTUAL', 'event_plan_' . $key, $key . '_cost', array('confidence' => 'operator_reported_not_independently_verified'));
    }
    $costs['labor'] = bvmgr_financial_value(null, 'UNAVAILABLE', 'no_paid_payroll_source', 'paid_labor');
    $known = array_filter(array($costs['direct']['amount_cents'], $costs['processing']['amount_cents']), static function ($v): bool { return $v !== null; });
    $known_cost = count($known) ? array_sum($known) : null;
    $contribution = $receipt['amount_cents'] !== null && $known_cost !== null ? $receipt['amount_cents'] - $known_cost : null;
    $forecast = (array) ($input['forecast'] ?? array());
    $staffing = (array) ($input['staffing_labor'] ?? array());
    $staffing_available = ($staffing['availability'] ?? '') === 'available';
    $labor = $staffing_available ? ($staffing['planned_cents'] ?? null) : null;
    $committed = $staffing_available ? ($staffing['committed_cents'] ?? null) : null;
    $labor_evidence = (array) ($staffing['evidence'] ?? array());
    $labor_evidence['freshness'] = ($staffing['availability'] ?? '') === 'available' ? 'current_normalized_rows' : 'unavailable';
    $forecast_cost = isset($forecast['direct_costs_cents'], $forecast['processing_fees_cents']) && $labor !== null
        ? (int) $forecast['direct_costs_cents'] + (int) $forecast['processing_fees_cents'] + (int) $labor : null;
    $forecast_gross = $forecast['gross_revenue_cents'] ?? null;
    $forecast_margin = $forecast_gross !== null && $forecast_cost !== null ? (int) $forecast_gross - $forecast_cost : null;
    $recorded = array();
    foreach ((array) ($input['legacy_totals'] ?? array()) as $key => $amount) {
        if (!is_numeric($amount)) { continue; }
        // Old save/refresh paths pad missing keys with zero and mix POS revenue with manual costs.
        $recorded[$key] = bvmgr_financial_value(null, 'UNAVAILABLE', 'legacy_actuals_totals', (string) $key, array(
            'observed_amount_cents' => (int) $amount,
            'provider_id' => (string) ($input['legacy_provider'] ?? ''),
            'recorded_at_utc' => (string) ($input['legacy_pulled_at'] ?? ''),
            'confidence' => 'ambiguous_legacy_observation',
        ));
    }
    return array(
        'contract_version' => 2,
        'event_plan_id' => $plan_id,
        'revenue' => array(
            'tickets' => $receipt,
            'add_ons' => bvmgr_financial_value(null, 'UNAVAILABLE', 'no_distinct_revenue_contract', 'add_ons'),
            'pos_other' => bvmgr_financial_value(null, 'UNAVAILABLE', 'no_nonoverlapping_revenue_contract', 'pos_non_ticket'),
            'manual_concessions' => bvmgr_financial_value($manual['concessions'] ?? null, 'MANUAL_ACTUAL', 'event_plan_manual_concessions', 'concessions', array('confidence' => 'operator_reported_not_independently_verified')),
            'manual_override' => bvmgr_financial_value(null, 'UNAVAILABLE', 'no_verified_event_override_contract', 'event_total'),
        ),
        'costs' => $costs,
        'actual' => array(
            'gross' => bvmgr_financial_value(null, 'UNAVAILABLE', 'incomplete_revenue_channels', 'complete_event_gross'),
            'ticket_receipts' => $receipt,
            'known_costs' => bvmgr_financial_value($known_cost, 'MANUAL_ACTUAL', 'reported_direct_and_processing', 'known_costs_only'),
            'margin' => bvmgr_financial_value($contribution, 'DERIVED_ACTUAL', 'ticket_receipts_less_known_reported_costs', 'ticket_contribution_excluding_labor_and_missing_costs', array('confidence' => 'provisional', 'component_bases' => array('TRANSACTIONAL_ACTUAL', 'MANUAL_ACTUAL'))),
        ),
        'staffing' => array(
            'planned' => bvmgr_financial_value($labor, 'PLANNED_LABOR', 'normalized_staffing_lifecycle', 'active_slot_required_headcount', $labor_evidence),
            'committed' => bvmgr_financial_value($committed, 'COMMITTED_LABOR', 'normalized_staffing_lifecycle', 'confirmed_assignment_estimate', $labor_evidence),
            'actual' => $costs['labor'],
        ),
        'forecast' => array(
            'gross' => bvmgr_financial_value($forecast_gross, 'FORECAST', 'goals_forecast_model', 'modeled_event_revenue'),
            'costs' => bvmgr_financial_value($forecast_cost, 'FORECAST', 'configured_costs_and_planned_labor', 'direct_costs_excluding_overhead'),
            'margin' => bvmgr_financial_value($forecast_margin, 'FORECAST', 'goals_model_less_planned_labor', 'direct_margin_excluding_overhead'),
            'labor' => bvmgr_financial_value($labor, 'PLANNED_LABOR', 'normalized_staffing_lifecycle', 'active_slot_required_headcount', $labor_evidence),
            'direct' => bvmgr_financial_value($forecast['direct_costs_cents'] ?? null, 'FORECAST', 'configured_compensation', 'direct_costs'),
            'processing' => bvmgr_financial_value($forecast['processing_fees_cents'] ?? null, 'FORECAST', 'configured_processing_fees', 'processing'),
        ),
        'final' => bvmgr_financial_value(null, 'UNAVAILABLE', 'no_accounting_final_source', 'event'),
        'recorded_observations' => $recorded,
        'ticket_qty' => $available ? ($ticket['paid_qty'] ?? null) : null,
        'warnings' => (array) ($ticket['warnings'] ?? array()),
    );
}

/** Shared read-only adapter. No refresh, writes, activation or direct add-on loading. */
function bvmgr_financial_get_event_snapshot(int $plan_id): array
{
    // Re-read labor and operator entries after lifecycle mutations in this request.
    // Ticket evidence retains its separate accepted request-cache contract.
        if ($plan_id <= 0) { return bvmgr_financial_build_snapshot($plan_id, array()); }
        $ticket = bvmgr_reporting_get_ticket_truth($plan_id);
        if (empty($ticket['available']) || empty($ticket['calculated'])) {
            $key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1';
            $cache = bvmgr_reporting_normalize_ticket_cache((array) get_post_meta($plan_id, $key ?: '_vms_ticket_stats_v1', true), array());
            $ticket = array_merge($ticket, array(
                'available' => !empty($cache['is_current']), 'calculated' => !empty($cache['is_current']),
                'revenue_cents' => $cache['revenue_cents'], 'paid_qty' => $cache['sold'],
                'source' => 'cached_ticket_stats', 'source_label' => __('Cached ticket stats', 'backstage-venue-manager'),
                'freshness' => array('state' => $cache['state'], 'computed_at_gmt' => $cache['stats_computed_at_gmt']),
            ));
        }
        if (function_exists('wp_cache_delete')) wp_cache_delete($plan_id, 'post_meta');
        $manual = array();
        foreach (array('direct' => '_vms_event_direct_costs_cents', 'processing' => '_vms_event_processing_fees_cents') as $key => $meta) {
            $raw = get_post_meta($plan_id, $meta, true);
            $manual[$key] = $raw !== '' && is_numeric($raw) ? max(0, (int) $raw) : null;
        }
        if (get_post_meta($plan_id, '_vms_concessions_actual_source', true) === 'manual') {
            $raw = get_post_meta($plan_id, '_vms_concessions_actual_cents', true);
            $manual['concessions'] = $raw !== '' && is_numeric($raw) ? max(0, (int) $raw) : null;
        }
        try {
            $staffing_labor = function_exists('bvmgr_staffing_get_financial_labor')
                ? bvmgr_staffing_get_financial_labor($plan_id) : array();
        } catch (Throwable $error) {
            $staffing_labor = array('availability' => 'unavailable', 'evidence' => array('reason' => 'staffing_authority_failed'));
        }
        $forecast = function_exists('bvmgr_goals_get_event_pnl')
            ? bvmgr_goals_get_event_pnl($plan_id, array('headcount_mode' => 'forecast', 'include_overhead' => false)) : array();
        return bvmgr_financial_build_snapshot($plan_id, array(
            'ticket' => $ticket, 'manual' => $manual, 'forecast' => $forecast,
            'staffing_labor' => $staffing_labor,
            'legacy_totals' => (array) get_post_meta($plan_id, '_vms_event_actuals_totals', true),
            'legacy_provider' => (string) get_post_meta($plan_id, '_vms_event_actuals_provider', true),
            'legacy_pulled_at' => (string) get_post_meta($plan_id, '_vms_event_actuals_pulled_at_utc', true),
            'calculated_at_utc' => !empty($ticket['freshness']['computed_at_gmt']) ? gmdate('Y-m-d H:i:s', (int) $ticket['freshness']['computed_at_gmt']) : gmdate('Y-m-d H:i:s'),
        ));
}

/** Stable user-facing vocabulary shared by all operational consumers. */
function bvmgr_financial_display_rows(array $s): array
{
    return array(
        array(__('Transactional ticket receipts', 'backstage-venue-manager'), $s['revenue']['tickets'], __('Ticket channels only; not complete event gross', 'backstage-venue-manager')),
        array(__('Manual concessions reported', 'backstage-venue-manager'), $s['revenue']['manual_concessions'], __('Separate operator entry; not independently verified or an override', 'backstage-venue-manager')),
        array(__('Known reported costs', 'backstage-venue-manager'), $s['actual']['known_costs'], __('Direct costs and processing only; incomplete', 'backstage-venue-manager')),
        array(__('Provisional ticket contribution', 'backstage-venue-manager'), $s['actual']['margin'], __('Ticket receipts less known reported costs; excludes labor and missing expenses', 'backstage-venue-manager')),
        array(__('Forecast gross revenue', 'backstage-venue-manager'), $s['forecast']['gross'], __('Forecast/model; not transaction truth', 'backstage-venue-manager')),
        array(__('Forecast direct margin', 'backstage-venue-manager'), $s['forecast']['margin'], __('Modeled revenue less configured costs and planned labor; excludes overhead', 'backstage-venue-manager')),
        array(__('Planned staffing labor', 'backstage-venue-manager'), $s['staffing']['planned'], __('Active planned positions, including unfilled positions; not paid payroll', 'backstage-venue-manager')),
        array(__('Committed staffing labor', 'backstage-venue-manager'), $s['staffing']['committed'], __('Confirmed assignments at configured rates; not paid payroll or an additional forecast cost', 'backstage-venue-manager')),
        array(__('Actual paid labor', 'backstage-venue-manager'), $s['staffing']['actual'], __('No paid payroll source', 'backstage-venue-manager')),
        array(__('Final accounting', 'backstage-venue-manager'), $s['final'], __('No finalized accounting source', 'backstage-venue-manager')),
    );
}

function bvmgr_financial_money($amount): string
{
    return $amount === null ? __('Unavailable', 'backstage-venue-manager') : bvmgr_goals_fmt_money((int) $amount);
}

function bvmgr_financial_render_summary(array $snapshot): void
{
    echo '<dl class="bvm-financial-authority">';
    foreach (bvmgr_financial_display_rows($snapshot) as $row) {
        echo '<dt>' . esc_html($row[0]) . '</dt><dd>' . esc_html(bvmgr_financial_money($row[1]['amount_cents'])) . ' — ' . esc_html($row[2]) . '</dd>';
    }
    $ticket = $snapshot['revenue']['tickets'];
    echo '</dl><p>' . esc_html__('Ticket source / freshness:', 'backstage-venue-manager') . ' ' . esc_html(($ticket['source_label'] ?: $ticket['source']) . ' / ' . $ticket['freshness'] . ' / ' . ($ticket['calculated_at_utc'] ?? '')) . '</p>';
    foreach ($snapshot['warnings'] as $warning) {
        echo '<p>' . esc_html((string) $warning) . '</p>';
    }
    echo '<p>' . esc_html__('Calculation time does not certify upstream synchronization. POS may cover a full venue day. Missing values are unavailable; these figures are not final accounting.', 'backstage-venue-manager') . '</p>';
}
