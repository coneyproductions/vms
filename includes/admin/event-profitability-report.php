<?php

defined('ABSPATH') || exit;

if (!function_exists('bvmgr_event_profitability_admin_url')) {
	function bvmgr_event_profitability_admin_url(array $args = array()): string
	{
		return add_query_arg($args, admin_url('admin.php?page=vms-event-profitability'));
	}
}

if (!function_exists('bvmgr_event_profitability_query_arg')) {
	function bvmgr_event_profitability_query_arg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filters only change admin display state.
		if (!isset($_GET[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only report filters are unslashed here and sanitized or allowlisted by the caller.
		return (string) wp_unslash($_GET[$key]);
	}
}

if (!function_exists('bvmgr_event_profitability_selected_filters')) {
	function bvmgr_event_profitability_selected_filters(): array
	{
		$view = sanitize_key(bvmgr_event_profitability_query_arg('profit_view'));
		if (!in_array($view, array('all', 'future', 'past'), true)) {
			$view = 'all';
		}

		return array(
			'view' => $view,
			'search' => sanitize_text_field(bvmgr_event_profitability_query_arg('s')),
		);
	}
}

add_action('admin_menu', 'bvmgr_event_profitability_admin_menu', 46);
function bvmgr_event_profitability_admin_menu(): void
{
	add_submenu_page(
		'vms-dashboard',
		__('Reporting: Event Profitability', 'backstage-venue-manager'),
		__('Reporting: Event Profitability', 'backstage-venue-manager'),
		'manage_options',
		'vms-event-profitability',
		'bvmgr_event_profitability_render_admin_page'
	);
}

add_action('admin_enqueue_scripts', 'bvmgr_event_profitability_enqueue_assets');
function bvmgr_event_profitability_enqueue_assets(string $hook): void
{
	$page = sanitize_key(bvmgr_event_profitability_query_arg('page'));
	if ($page !== 'vms-event-profitability') {
		return;
	}

	wp_enqueue_style(
		'bvmgr-event-profitability-admin',
		BVMGR_PLUGIN_URL . 'assets/css/vms-event-profitability-admin.css',
		array(),
		function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '')
	);
}

if (!function_exists('bvmgr_event_profitability_readable_status')) {
	function bvmgr_event_profitability_readable_status(string $status): string
	{
		$status = sanitize_key($status);
		$labels = function_exists('bvmgr_event_plan_statuses') ? (array) bvmgr_event_plan_statuses() : array();
		if (isset($labels[$status]) && is_string($labels[$status]) && $labels[$status] !== '') {
			return (string) $labels[$status];
		}
		if ($status === 'cancelled') {
			return __('Cancelled', 'backstage-venue-manager');
		}
		if ($status === '') {
			return __('Unknown', 'backstage-venue-manager');
		}
		return ucwords(str_replace('_', ' ', $status));
	}
}

if (!function_exists('bvmgr_event_profitability_get_event_timestamp')) {
	function bvmgr_event_profitability_get_event_timestamp(int $event_plan_id): int
	{
		$dt = function_exists('bvmgr_staffing_event_plan_datetime')
			? (array) bvmgr_staffing_event_plan_datetime($event_plan_id)
			: array();

		if (isset($dt['start_local']) && $dt['start_local'] instanceof DateTimeImmutable) {
			return (int) $dt['start_local']->getTimestamp();
		}

		$ymd = isset($dt['event_date_ymd']) ? (string) $dt['event_date_ymd'] : (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
			$midday = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' 12:00:00', wp_timezone());
			if ($midday instanceof DateTimeImmutable) {
				return (int) $midday->getTimestamp();
			}
		}

		$published = get_post_time('U', true, $event_plan_id);
		return is_numeric($published) ? (int) $published : 0;
	}
}

if (!function_exists('bvmgr_event_profitability_stage_label')) {
	function bvmgr_event_profitability_stage_label(int $event_plan_id, int $event_ts, string $status): string
	{
		$status = sanitize_key($status);
		if ($status === 'cancelled') {
			return __('Cancelled', 'backstage-venue-manager');
		}

		$today = wp_date('Y-m-d');
		$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		if ($event_date === $today) {
			return __('Live', 'backstage-venue-manager');
		}

		if ($event_ts > time()) {
			return __('Projected', 'backstage-venue-manager');
		}

		return __('Past event — provisional', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_event_profitability_badge')) {
	function bvmgr_event_profitability_badge(int $total_contribution_cents, string $status): array
	{
		$status = sanitize_key($status);
		if ($status === 'cancelled') {
			return array(
				'label' => __('Cancelled', 'backstage-venue-manager'),
				'class' => 'is-cancelled',
			);
		}

		if ($total_contribution_cents >= 50000) {
			return array(
				'label' => __('Good', 'backstage-venue-manager'),
				'class' => 'is-good',
			);
		}

		if ($total_contribution_cents >= 0) {
			return array(
				'label' => __('Decent', 'backstage-venue-manager'),
				'class' => 'is-decent',
			);
		}

		return array(
			'label' => __('Bust', 'backstage-venue-manager'),
			'class' => 'is-bust',
		);
	}
}

if (!function_exists('bvmgr_event_profitability_get_labor_cost_cents')) {
	function bvmgr_event_profitability_get_labor_cost_cents(int $event_plan_id): int
	{
		$labor_dollars = null;

		if (function_exists('bvmgr_staffing_resolve_event_snapshot')) {
			$staffing_snapshot = (array) bvmgr_staffing_resolve_event_snapshot($event_plan_id);
			$rollup = is_array($staffing_snapshot['rollup'] ?? null) ? (array) $staffing_snapshot['rollup'] : array();
			if (isset($rollup['est_labor_cost_total']) && $rollup['est_labor_cost_total'] !== null && $rollup['est_labor_cost_total'] !== '') {
				$labor_dollars = (float) $rollup['est_labor_cost_total'];
			}
		}

		if ($labor_dollars === null) {
			return 0;
		}

		return max(0, (int) round($labor_dollars * 100));
	}
}

if (!function_exists('bvmgr_event_profitability_get_rows')) {
	function bvmgr_event_profitability_get_rows(string $view = 'all', string $search = ''): array
	{
		$view = sanitize_key($view);
		if (!in_array($view, array('all', 'future', 'past'), true)) {
			$view = 'all';
		}

		$search = sanitize_text_field($search);
		$search_lc = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
		$now = time();
		$today = wp_date('Y-m-d');
		$concession_margin = 0.65;

		$query = new WP_Query(array(
			'post_type' => 'vms_event_plan',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'fields' => 'ids',
		));

		$rows = array();
		$summary = array(
			'count' => 0,
			'ticket_revenue_cents' => 0,
			'concessions_cents' => 0,
			'labor_cents' => 0,
			'core_profit_cents' => 0,
			'total_contribution_cents' => 0,
		);

		foreach ((array) $query->posts as $event_plan_id) {
			$event_plan_id = absint($event_plan_id);
			if ($event_plan_id <= 0 || get_post_status($event_plan_id) === 'trash') {
				continue;
			}

			$title = (string) get_the_title($event_plan_id);
			if ($search_lc !== '') {
				$title_lc = function_exists('mb_strtolower') ? mb_strtolower($title) : strtolower($title);
				if (strpos($title_lc, $search_lc) === false) {
					continue;
				}
			}

			$event_ts = bvmgr_event_profitability_get_event_timestamp($event_plan_id);
			$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
			$is_past = false;
			if ($event_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
				$is_past = ($event_date < $today);
			} else {
				$is_past = ($event_ts > 0 && $event_ts < $now);
			}

			if ($view === 'future' && $is_past) {
				continue;
			}
			if ($view === 'past' && !$is_past) {
				continue;
			}

			$status = function_exists('bvmgr_event_plan_get_status')
				? (string) bvmgr_event_plan_get_status($event_plan_id, 'financial')
				: 'draft';
			$status = sanitize_key($status);

			$financial = bvmgr_financial_get_event_snapshot($event_plan_id);
			$ticket_qty = $financial['ticket_qty'];
			$ticket_revenue_cents = $financial['revenue']['tickets']['amount_cents'];
			$concessions_cents = $financial['revenue']['manual_concessions']['amount_cents'];
			$vendor_cost_cents = $financial['costs']['direct']['amount_cents'] ?? $financial['forecast']['direct']['amount_cents'];
			$labor_cents = $financial['forecast']['labor']['amount_cents'];
			$processing_cents = $financial['costs']['processing']['amount_cents'];
			$core_profit_cents = $ticket_revenue_cents !== null && $vendor_cost_cents !== null && $labor_cents !== null && $processing_cents !== null
				? $ticket_revenue_cents - $vendor_cost_cents - $labor_cents - $processing_cents : null;
			$estimated_bar_profit_cents = $concessions_cents !== null ? (int) round($concessions_cents * $concession_margin) : null;
			$total_contribution_cents = $core_profit_cents !== null && $estimated_bar_profit_cents !== null ? $core_profit_cents + $estimated_bar_profit_cents : null;
			$badge = $total_contribution_cents !== null ? bvmgr_event_profitability_badge($total_contribution_cents, $status)
				: array('label' => __('Unavailable', 'backstage-venue-manager'), 'class' => '');
			$stage = bvmgr_event_profitability_stage_label($event_plan_id, $event_ts, $status);
			$venue_id = absint(get_post_meta($event_plan_id, '_vms_venue_id', true));

			$row = array(
				'event_plan_id' => $event_plan_id,
				'title' => $title,
				'event_ts' => $event_ts,
				'event_date_raw' => $event_date,
				'event_date_label' => $event_date !== '' ? wp_date(get_option('date_format'), strtotime($event_date . ' 12:00:00')) : __('Date TBD', 'backstage-venue-manager'),
				'start_time_label' => (string) get_post_meta($event_plan_id, '_vms_start_time', true),
				'is_past' => $is_past,
				'status' => $status,
				'status_label' => bvmgr_event_profitability_readable_status($status),
				'stage_label' => $stage,
				'badge_label' => (string) ($badge['label'] ?? ''),
				'badge_class' => (string) ($badge['class'] ?? ''),
				'venue_name' => $venue_id > 0 ? (string) get_the_title($venue_id) : '',
				'ticket_qty' => $ticket_qty,
				'financial_snapshot' => $financial,
				'contribution_basis' => 'MIXED_PLANNING_ESTIMATE',
				'processing_cents' => $processing_cents,
				'ticket_revenue_cents' => $ticket_revenue_cents,
				'concessions_cents' => $concessions_cents,
				'vendor_cost_cents' => $vendor_cost_cents,
				'labor_cents' => $labor_cents,
				'core_profit_cents' => $core_profit_cents,
				'estimated_bar_profit_cents' => $estimated_bar_profit_cents,
				'total_contribution_cents' => $total_contribution_cents,
				'edit_link' => get_edit_post_link($event_plan_id, ''),
			);

			$rows[] = $row;
			$summary['count']++;
			foreach (array('ticket_revenue_cents', 'concessions_cents', 'labor_cents', 'core_profit_cents', 'total_contribution_cents') as $metric) {
				if ($row[$metric] === null) {
					$summary['unavailable'][$metric] = ($summary['unavailable'][$metric] ?? 0) + 1;
				} else {
					$summary[$metric] += $row[$metric];
					$summary['known'][$metric] = ($summary['known'][$metric] ?? 0) + 1;
				}
			}
		}
		foreach (array('ticket_revenue_cents', 'concessions_cents', 'labor_cents', 'core_profit_cents', 'total_contribution_cents') as $metric) {
			if (empty($summary['known'][$metric])) { $summary[$metric] = null; }
		}

		usort($rows, static function (array $a, array $b): int {
			$bucket_a = !empty($a['is_past']) ? 1 : 0;
			$bucket_b = !empty($b['is_past']) ? 1 : 0;
			if ($bucket_a !== $bucket_b) {
				return $bucket_a <=> $bucket_b;
			}

			$ts_a = (int) ($a['event_ts'] ?? 0);
			$ts_b = (int) ($b['event_ts'] ?? 0);
			if ($bucket_a === 0) {
				if ($ts_a === $ts_b) {
					return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
				}
				return $ts_a <=> $ts_b;
			}

			if ($ts_a === $ts_b) {
				return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
			}
			return $ts_b <=> $ts_a;
		});

		return array(
			'rows' => $rows,
			'summary' => $summary,
			'view' => $view,
			'search' => $search,
			'concession_margin_pct' => (int) round($concession_margin * 100),
		);
	}
}

if (!function_exists('bvmgr_event_profitability_money_class')) {
	function bvmgr_event_profitability_money_class(int $cents): string
	{
		if ($cents > 0) {
			return 'is-positive';
		}
		if ($cents < 0) {
			return 'is-negative';
		}
		return 'is-neutral';
	}
}

if (!function_exists('bvmgr_event_profitability_render_admin_page')) {
	function bvmgr_event_profitability_render_admin_page(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$filters = bvmgr_event_profitability_selected_filters();
		$view = (string) ($filters['view'] ?? 'all');
		$search = (string) ($filters['search'] ?? '');
		$data = bvmgr_event_profitability_get_rows($view, $search);
		$rows = (array) ($data['rows'] ?? array());
		$summary = (array) ($data['summary'] ?? array());
		$margin_pct = (int) ($data['concession_margin_pct'] ?? 65);

		echo '<div class="wrap vms-event-profitability-admin">';
		echo '<h1>' . esc_html__('Reporting: Event Profitability', 'backstage-venue-manager') . '</h1>';
		echo '<p class="vms-event-profitability-intro">' . esc_html__('Planning scorecard using transaction ticket receipts, reported or configured direct costs, reported processing fees and estimated labor. Contributions exclude overhead and missing expenses. Summary values total available rows only. These estimates are not actual event profit or final accounting.', 'backstage-venue-manager') . '</p>';

		echo '<div class="vms-event-profitability-note-grid">';
		echo '<div class="vms-event-profitability-note"><strong>' . esc_html__('Estimated core contribution', 'backstage-venue-manager') . '</strong><span>' . esc_html__('Ticket receipts − reported/configured direct costs − estimated labor − reported processing fees', 'backstage-venue-manager') . '</span></div>';
		/* translators: %d: estimated bar profit margin percentage. */
		echo '<div class="vms-event-profitability-note"><strong>' . esc_html__('Estimated night score', 'backstage-venue-manager') . '</strong><span>' . esc_html(sprintf(__('Estimated core contribution + estimated bar profit at %d%% margin', 'backstage-venue-manager'), $margin_pct)) . '</span></div>';
		echo '</div>';

		echo '<form method="get" class="vms-event-profitability-filters">';
		echo '<input type="hidden" name="page" value="vms-event-profitability" />';
		echo '<div class="vms-event-profitability-view-tabs">';
		$views = array(
			'all' => __('All', 'backstage-venue-manager'),
			'future' => __('Future + Live', 'backstage-venue-manager'),
			'past' => __('Past', 'backstage-venue-manager'),
		);
		foreach ($views as $key => $label) {
			$class = ($view === $key) ? 'is-active' : '';
			echo '<a class="vms-event-profitability-tab ' . esc_attr($class) . '" href="' . esc_url(bvmgr_event_profitability_admin_url(array('profit_view' => $key, 's' => $search))) . '">' . esc_html($label) . '</a>';
		}
		echo '</div>';
		echo '<div class="vms-event-profitability-search-row">';
		echo '<label class="screen-reader-text" for="vms-event-profitability-search">' . esc_html__('Search events', 'backstage-venue-manager') . '</label>';
		echo '<input id="vms-event-profitability-search" type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search events', 'backstage-venue-manager') . '" />';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Filter', 'backstage-venue-manager') . '</button>';
		if ($search !== '') {
			echo '<a class="button button-secondary" href="' . esc_url(bvmgr_event_profitability_admin_url(array('profit_view' => $view))) . '">' . esc_html__('Clear', 'backstage-venue-manager') . '</a>';
		}
		echo '</div>';
		echo '</form>';

		echo '<section class="vms-event-profitability-summary">';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Events', 'backstage-venue-manager') . '</span><strong>' . esc_html((string) (int) ($summary['count'] ?? 0)) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Transactional ticket receipts', 'backstage-venue-manager') . '</span><strong>' . esc_html(bvmgr_financial_money($summary['ticket_revenue_cents'] ?? null)) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Manual concessions reported', 'backstage-venue-manager') . '</span><strong>' . esc_html(bvmgr_financial_money($summary['concessions_cents'] ?? null)) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Estimated labor', 'backstage-venue-manager') . '</span><strong>' . esc_html(bvmgr_financial_money($summary['labor_cents'] ?? null)) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Estimated core contribution', 'backstage-venue-manager') . '</span><strong class="' . esc_attr(bvmgr_event_profitability_money_class((int) ($summary['core_profit_cents'] ?? 0))) . '">' . esc_html(bvmgr_financial_money($summary['core_profit_cents'] ?? null)) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Estimated night score', 'backstage-venue-manager') . '</span><strong class="' . esc_attr(bvmgr_event_profitability_money_class((int) ($summary['total_contribution_cents'] ?? 0))) . '">' . esc_html(bvmgr_financial_money($summary['total_contribution_cents'] ?? null)) . '</strong></article>';
		echo '</section>';
		$summary_labels = array(
			'ticket_revenue_cents' => __('Transactional ticket receipts', 'backstage-venue-manager'),
			'concessions_cents' => __('Manual concessions reported', 'backstage-venue-manager'),
			'labor_cents' => __('Estimated labor', 'backstage-venue-manager'),
			'core_profit_cents' => __('Estimated core contribution', 'backstage-venue-manager'),
			'total_contribution_cents' => __('Estimated night score', 'backstage-venue-manager'),
		);
		foreach ((array) ($summary['unavailable'] ?? array()) as $metric => $count) {
			/* translators: 1: financial metric label, 2: number of excluded events. */
			echo '<p>' . esc_html(sprintf(__('%1$s: %2$d unavailable events excluded from this total.', 'backstage-venue-manager'), $summary_labels[$metric], $count)) . '</p>';
		}

		if (empty($rows)) {
			echo '<div class="notice notice-info"><p>' . esc_html__('No event plans matched this filter.', 'backstage-venue-manager') . '</p></div>';
			echo '</div>';
			return;
		}

		echo '<section class="vms-event-profitability-list">';
		foreach ($rows as $row) {
			$title = (string) ($row['title'] ?? '');
			$status_label = (string) ($row['status_label'] ?? '');
			$stage_label = (string) ($row['stage_label'] ?? '');
			$badge_label = (string) ($row['badge_label'] ?? '');
			$badge_class = (string) ($row['badge_class'] ?? '');
			$venue_name = (string) ($row['venue_name'] ?? '');
			$event_date_label = (string) ($row['event_date_label'] ?? '');
			$start_time_label = trim((string) ($row['start_time_label'] ?? ''));
			$edit_link = (string) ($row['edit_link'] ?? '');

			echo '<article class="vms-event-profitability-card">';
			echo '<div class="vms-event-profitability-card-head">';
			echo '<div class="vms-event-profitability-card-title-wrap">';
			echo '<h2>' . esc_html($title) . '</h2>';
			echo '<div class="vms-event-profitability-card-meta">';
			echo '<span>' . esc_html($event_date_label) . '</span>';
			if ($start_time_label !== '') {
				echo '<span>' . esc_html($start_time_label) . '</span>';
			}
			if ($venue_name !== '') {
				echo '<span>' . esc_html($venue_name) . '</span>';
			}
			echo '</div>';
			echo '</div>';
			echo '<div class="vms-event-profitability-badges">';
			echo '<span class="vms-profit-pill vms-profit-pill-stage">' . esc_html($stage_label) . '</span>';
			echo '<span class="vms-profit-pill vms-profit-pill-status">' . esc_html($status_label) . '</span>';
			echo '<span class="vms-profit-pill ' . esc_attr($badge_class) . '">' . esc_html($badge_label) . '</span>';
			echo '</div>';
			echo '</div>';

			echo '<div class="vms-event-profitability-core">';
			echo '<div class="vms-event-profitability-big-metric">';
			echo '<span class="label">' . esc_html__('Estimated core contribution', 'backstage-venue-manager') . '</span>';
			echo '<strong class="' . esc_attr(bvmgr_event_profitability_money_class((int) ($row['core_profit_cents'] ?? 0))) . '">' . esc_html(bvmgr_financial_money($row['core_profit_cents'] ?? null)) . '</strong>';
			echo '</div>';
			echo '<div class="vms-event-profitability-big-metric">';
			echo '<span class="label">' . esc_html__('Estimated night score', 'backstage-venue-manager') . '</span>';
			echo '<strong class="' . esc_attr(bvmgr_event_profitability_money_class((int) ($row['total_contribution_cents'] ?? 0))) . '">' . esc_html(bvmgr_financial_money($row['total_contribution_cents'] ?? null)) . '</strong>';
			echo '</div>';
			echo '</div>';

			echo '<dl class="vms-event-profitability-metrics">';
			echo '<div><dt>' . esc_html__('Tickets Sold', 'backstage-venue-manager') . '</dt><dd>' . esc_html(isset($row['ticket_qty']) ? (string) $row['ticket_qty'] : __('Unavailable', 'backstage-venue-manager')) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Transactional ticket receipts', 'backstage-venue-manager') . '</dt><dd>' . esc_html(bvmgr_financial_money($row['ticket_revenue_cents'] ?? null)) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Manual concessions reported', 'backstage-venue-manager') . '</dt><dd>' . esc_html(bvmgr_financial_money($row['concessions_cents'] ?? null)) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Reported/configured direct costs', 'backstage-venue-manager') . '</dt><dd>' . esc_html(bvmgr_financial_money($row['vendor_cost_cents'] ?? null)) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Estimated labor', 'backstage-venue-manager') . '</dt><dd>' . esc_html(bvmgr_financial_money($row['labor_cents'] ?? null)) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Est. Bar Profit', 'backstage-venue-manager') . '</dt><dd>' . esc_html(bvmgr_financial_money($row['estimated_bar_profit_cents'] ?? null)) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Reported processing fees', 'backstage-venue-manager') . '</dt><dd>' . esc_html(bvmgr_financial_money($row['processing_cents'] ?? null)) . '</dd></div>';
			echo '</dl>';

			echo '<details><summary>' . esc_html__('Financial authority and forecast', 'backstage-venue-manager') . '</summary>';
			bvmgr_financial_render_summary($row['financial_snapshot']);
			echo '</details>';
			echo '<div class="vms-event-profitability-card-actions">';
			if ($edit_link !== '') {
				echo '<a class="button button-secondary" href="' . esc_url($edit_link) . '">' . esc_html__('Open Event Plan', 'backstage-venue-manager') . '</a>';
			}
			echo '</div>';
			echo '</article>';
		}
		echo '</section>';

		echo '</div>';
	}
}
