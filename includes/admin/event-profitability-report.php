<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_event_profitability_admin_url')) {
	function vms_event_profitability_admin_url(array $args = array()): string
	{
		return add_query_arg($args, admin_url('admin.php?page=vms-event-profitability'));
	}
}

add_action('admin_menu', 'vms_event_profitability_admin_menu', 46);
function vms_event_profitability_admin_menu(): void
{
	add_submenu_page(
		'vms-dashboard',
		__('Reporting: Event Profitability', 'vms'),
		__('Reporting: Event Profitability', 'vms'),
		'manage_options',
		'vms-event-profitability',
		'vms_event_profitability_render_admin_page'
	);
}

add_action('admin_enqueue_scripts', 'vms_event_profitability_enqueue_assets');
function vms_event_profitability_enqueue_assets(string $hook): void
{
	$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
	if ($page !== 'vms-event-profitability') {
		return;
	}

	wp_enqueue_style(
		'vms-event-profitability-admin',
		VMS_PLUGIN_URL . 'assets/css/vms-event-profitability-admin.css',
		array(),
		function_exists('vms_asset_version') ? vms_asset_version() : (defined('VMS_VERSION') ? (string) VMS_VERSION : '')
	);
}

if (!function_exists('vms_event_profitability_readable_status')) {
	function vms_event_profitability_readable_status(string $status): string
	{
		$status = sanitize_key($status);
		$labels = function_exists('vms_event_plan_statuses') ? (array) vms_event_plan_statuses() : array();
		if (isset($labels[$status]) && is_string($labels[$status]) && $labels[$status] !== '') {
			return (string) $labels[$status];
		}
		if ($status === 'cancelled') {
			return __('Cancelled', 'vms');
		}
		if ($status === '') {
			return __('Unknown', 'vms');
		}
		return ucwords(str_replace('_', ' ', $status));
	}
}

if (!function_exists('vms_event_profitability_get_event_timestamp')) {
	function vms_event_profitability_get_event_timestamp(int $event_plan_id): int
	{
		$dt = function_exists('vms_staffing_event_plan_datetime')
			? (array) vms_staffing_event_plan_datetime($event_plan_id)
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

if (!function_exists('vms_event_profitability_stage_label')) {
	function vms_event_profitability_stage_label(int $event_plan_id, int $event_ts, string $status): string
	{
		$status = sanitize_key($status);
		if ($status === 'cancelled') {
			return __('Cancelled', 'vms');
		}

		$today = wp_date('Y-m-d');
		$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		if ($event_date === $today) {
			return __('Live', 'vms');
		}

		if ($event_ts > time()) {
			return __('Projected', 'vms');
		}

		return __('Final', 'vms');
	}
}

if (!function_exists('vms_event_profitability_badge')) {
	function vms_event_profitability_badge(int $total_contribution_cents, string $status): array
	{
		$status = sanitize_key($status);
		if ($status === 'cancelled') {
			return array(
				'label' => __('Cancelled', 'vms'),
				'class' => 'is-cancelled',
			);
		}

		if ($total_contribution_cents >= 50000) {
			return array(
				'label' => __('Good', 'vms'),
				'class' => 'is-good',
			);
		}

		if ($total_contribution_cents >= 0) {
			return array(
				'label' => __('Decent', 'vms'),
				'class' => 'is-decent',
			);
		}

		return array(
			'label' => __('Bust', 'vms'),
			'class' => 'is-bust',
		);
	}
}

if (!function_exists('vms_event_profitability_get_labor_cost_cents')) {
	function vms_event_profitability_get_labor_cost_cents(int $event_plan_id): int
	{
		$labor_dollars = null;

		if (function_exists('vms_staffing_get_rollup')) {
			$rollup = vms_staffing_get_rollup($event_plan_id);
			$needs_compute = !is_array($rollup)
				|| !array_key_exists('est_labor_cost_total', $rollup)
				|| $rollup['est_labor_cost_total'] === null
				|| $rollup['est_labor_cost_total'] === ''
				|| (!empty($rollup['dirty']) && function_exists('vms_staffing_compute_rollup'));

			if ($needs_compute && function_exists('vms_staffing_compute_rollup')) {
				$computed = (array) vms_staffing_compute_rollup($event_plan_id);
				if (!empty($computed['ok']) && isset($computed['est_labor_cost_total']) && $computed['est_labor_cost_total'] !== null) {
					$labor_dollars = (float) $computed['est_labor_cost_total'];
				}
			}

			if ($labor_dollars === null && is_array($rollup) && isset($rollup['est_labor_cost_total']) && $rollup['est_labor_cost_total'] !== null && $rollup['est_labor_cost_total'] !== '') {
				$labor_dollars = (float) $rollup['est_labor_cost_total'];
			}
		}

		if ($labor_dollars === null) {
			return 0;
		}

		return max(0, (int) round($labor_dollars * 100));
	}
}

if (!function_exists('vms_event_profitability_get_rows')) {
	function vms_event_profitability_get_rows(string $view = 'all', string $search = ''): array
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

			$event_ts = vms_event_profitability_get_event_timestamp($event_plan_id);
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

			$status = function_exists('vms_event_plan_get_status')
				? (string) vms_event_plan_get_status($event_plan_id, 'financial')
				: 'draft';
			$status = sanitize_key($status);

			$ticket_stats = function_exists('vms_goals_get_ticket_stats')
				? (array) vms_goals_get_ticket_stats($event_plan_id)
				: array('qty_sold' => 0, 'revenue_cents' => 0);
			$manual_actuals = function_exists('vms_goals_get_manual_event_actual_totals')
				? (array) vms_goals_get_manual_event_actual_totals($event_plan_id)
				: array();

			$ticket_qty = max(0, (int) ($ticket_stats['qty_sold'] ?? 0));
			$ticket_revenue_cents = max(0, (int) ($manual_actuals['ticket_revenue_cents'] ?? 0));
			if ($ticket_revenue_cents <= 0) {
				$ticket_revenue_cents = max(0, (int) ($ticket_stats['revenue_cents'] ?? 0));
			}

			$concessions_cents = max(0, (int) ($manual_actuals['concessions_revenue_cents'] ?? 0));
			if ($concessions_cents <= 0) {
				$concessions_cents = max(0, (int) get_post_meta($event_plan_id, '_vms_concessions_actual_cents', true));
			}

			$vendor_cost_cents = max(0, (int) ($manual_actuals['direct_costs_cents'] ?? 0));
			if ($vendor_cost_cents <= 0 && function_exists('vms_goals_get_default_direct_costs_cents')) {
				$vendor_cost_cents = max(0, (int) vms_goals_get_default_direct_costs_cents($event_plan_id));
			}

			$labor_cents = vms_event_profitability_get_labor_cost_cents($event_plan_id);
			$core_profit_cents = (int) ($ticket_revenue_cents - $vendor_cost_cents - $labor_cents);
			$estimated_bar_profit_cents = (int) round($concessions_cents * $concession_margin);
			$total_contribution_cents = (int) ($core_profit_cents + $estimated_bar_profit_cents);
			$badge = vms_event_profitability_badge($total_contribution_cents, $status);
			$stage = vms_event_profitability_stage_label($event_plan_id, $event_ts, $status);
			$venue_id = absint(get_post_meta($event_plan_id, '_vms_venue_id', true));

			$row = array(
				'event_plan_id' => $event_plan_id,
				'title' => $title,
				'event_ts' => $event_ts,
				'event_date_raw' => $event_date,
				'event_date_label' => $event_date !== '' ? wp_date(get_option('date_format'), strtotime($event_date . ' 12:00:00')) : __('Date TBD', 'vms'),
				'start_time_label' => (string) get_post_meta($event_plan_id, '_vms_start_time', true),
				'is_past' => $is_past,
				'status' => $status,
				'status_label' => vms_event_profitability_readable_status($status),
				'stage_label' => $stage,
				'badge_label' => (string) ($badge['label'] ?? ''),
				'badge_class' => (string) ($badge['class'] ?? ''),
				'venue_name' => $venue_id > 0 ? (string) get_the_title($venue_id) : '',
				'ticket_qty' => $ticket_qty,
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
			$summary['ticket_revenue_cents'] += $ticket_revenue_cents;
			$summary['concessions_cents'] += $concessions_cents;
			$summary['labor_cents'] += $labor_cents;
			$summary['core_profit_cents'] += $core_profit_cents;
			$summary['total_contribution_cents'] += $total_contribution_cents;
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

if (!function_exists('vms_event_profitability_money_class')) {
	function vms_event_profitability_money_class(int $cents): string
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

if (!function_exists('vms_event_profitability_render_admin_page')) {
	function vms_event_profitability_render_admin_page(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$view = isset($_GET['profit_view']) ? sanitize_key((string) $_GET['profit_view']) : 'all';
		$search = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';
		$data = vms_event_profitability_get_rows($view, $search);
		$rows = (array) ($data['rows'] ?? array());
		$summary = (array) ($data['summary'] ?? array());
		$margin_pct = (int) ($data['concession_margin_pct'] ?? 65);

		echo '<div class="wrap vms-event-profitability-admin">';
		echo '<h1>' . esc_html__('Reporting: Event Profitability', 'vms') . '</h1>';
		echo '<p class="vms-event-profitability-intro">' . esc_html__('Mobile-first event scorecard for a fast read on whether a night looks good, decent, or like a bust. Core Profit excludes concessions. Night Score adds an estimated bar contribution so you can see the bigger picture on the fly.', 'vms') . '</p>';

		echo '<div class="vms-event-profitability-note-grid">';
		echo '<div class="vms-event-profitability-note"><strong>' . esc_html__('Core Profit', 'vms') . '</strong><span>' . esc_html__('Ticket revenue − vendor cost − labor OH', 'vms') . '</span></div>';
		echo '<div class="vms-event-profitability-note"><strong>' . esc_html__('Night Score', 'vms') . '</strong><span>' . esc_html(sprintf(__('Core Profit + estimated bar profit at %d%% margin', 'vms'), $margin_pct)) . '</span></div>';
		echo '</div>';

		echo '<form method="get" class="vms-event-profitability-filters">';
		echo '<input type="hidden" name="page" value="vms-event-profitability" />';
		echo '<div class="vms-event-profitability-view-tabs">';
		$views = array(
			'all' => __('All', 'vms'),
			'future' => __('Future + Live', 'vms'),
			'past' => __('Past', 'vms'),
		);
		foreach ($views as $key => $label) {
			$class = ($view === $key) ? 'is-active' : '';
			echo '<a class="vms-event-profitability-tab ' . esc_attr($class) . '" href="' . esc_url(vms_event_profitability_admin_url(array('profit_view' => $key, 's' => $search))) . '">' . esc_html($label) . '</a>';
		}
		echo '</div>';
		echo '<div class="vms-event-profitability-search-row">';
		echo '<label class="screen-reader-text" for="vms-event-profitability-search">' . esc_html__('Search events', 'vms') . '</label>';
		echo '<input id="vms-event-profitability-search" type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search events', 'vms') . '" />';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Filter', 'vms') . '</button>';
		if ($search !== '') {
			echo '<a class="button button-secondary" href="' . esc_url(vms_event_profitability_admin_url(array('profit_view' => $view))) . '">' . esc_html__('Clear', 'vms') . '</a>';
		}
		echo '</div>';
		echo '</form>';

		echo '<section class="vms-event-profitability-summary">';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Events', 'vms') . '</span><strong>' . esc_html((string) (int) ($summary['count'] ?? 0)) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Ticket Revenue', 'vms') . '</span><strong>' . esc_html(vms_goals_fmt_money((int) ($summary['ticket_revenue_cents'] ?? 0))) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Concession Sales', 'vms') . '</span><strong>' . esc_html(vms_goals_fmt_money((int) ($summary['concessions_cents'] ?? 0))) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Labor OH', 'vms') . '</span><strong>' . esc_html(vms_goals_fmt_money((int) ($summary['labor_cents'] ?? 0))) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Core Profit', 'vms') . '</span><strong class="' . esc_attr(vms_event_profitability_money_class((int) ($summary['core_profit_cents'] ?? 0))) . '">' . esc_html(vms_goals_fmt_money((int) ($summary['core_profit_cents'] ?? 0))) . '</strong></article>';
		echo '<article class="vms-event-profitability-summary-card"><span class="label">' . esc_html__('Night Score', 'vms') . '</span><strong class="' . esc_attr(vms_event_profitability_money_class((int) ($summary['total_contribution_cents'] ?? 0))) . '">' . esc_html(vms_goals_fmt_money((int) ($summary['total_contribution_cents'] ?? 0))) . '</strong></article>';
		echo '</section>';

		if (empty($rows)) {
			echo '<div class="notice notice-info"><p>' . esc_html__('No event plans matched this filter.', 'vms') . '</p></div>';
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
			echo '<span class="label">' . esc_html__('Core Profit', 'vms') . '</span>';
			echo '<strong class="' . esc_attr(vms_event_profitability_money_class((int) ($row['core_profit_cents'] ?? 0))) . '">' . esc_html(vms_goals_fmt_money((int) ($row['core_profit_cents'] ?? 0))) . '</strong>';
			echo '</div>';
			echo '<div class="vms-event-profitability-big-metric">';
			echo '<span class="label">' . esc_html__('Night Score', 'vms') . '</span>';
			echo '<strong class="' . esc_attr(vms_event_profitability_money_class((int) ($row['total_contribution_cents'] ?? 0))) . '">' . esc_html(vms_goals_fmt_money((int) ($row['total_contribution_cents'] ?? 0))) . '</strong>';
			echo '</div>';
			echo '</div>';

			echo '<dl class="vms-event-profitability-metrics">';
			echo '<div><dt>' . esc_html__('Tickets Sold', 'vms') . '</dt><dd>' . esc_html((string) (int) ($row['ticket_qty'] ?? 0)) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Ticket Revenue', 'vms') . '</dt><dd>' . esc_html(vms_goals_fmt_money((int) ($row['ticket_revenue_cents'] ?? 0))) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Concession Sales', 'vms') . '</dt><dd>' . esc_html(vms_goals_fmt_money((int) ($row['concessions_cents'] ?? 0))) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Vendor Cost', 'vms') . '</dt><dd>' . esc_html(vms_goals_fmt_money((int) ($row['vendor_cost_cents'] ?? 0))) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Labor OH', 'vms') . '</dt><dd>' . esc_html(vms_goals_fmt_money((int) ($row['labor_cents'] ?? 0))) . '</dd></div>';
			echo '<div><dt>' . esc_html__('Est. Bar Profit', 'vms') . '</dt><dd>' . esc_html(vms_goals_fmt_money((int) ($row['estimated_bar_profit_cents'] ?? 0))) . '</dd></div>';
			echo '</dl>';

			echo '<div class="vms-event-profitability-card-actions">';
			if ($edit_link !== '') {
				echo '<a class="button button-secondary" href="' . esc_url($edit_link) . '">' . esc_html__('Open Event Plan', 'vms') . '</a>';
			}
			echo '</div>';
			echo '</article>';
		}
		echo '</section>';

		echo '</div>';
	}
}
