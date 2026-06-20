<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_goals_fmt_money')) {
	function vms_goals_fmt_money(int $cents): string
	{
		$amount = $cents / 100;
		return '$' . number_format_i18n($amount, 2);
	}
}

if (!function_exists('vms_goals_admin_url')) {
	function vms_goals_admin_url(array $args = array()): string
	{
		return add_query_arg($args, admin_url('admin.php?page=vms-goals-forecast'));
	}
}

if (!function_exists('vms_goals_post_value')) {
	function vms_goals_post_value(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed POST values are sanitized or validated at the call site.
		if (!isset($_POST[$key]) || is_array($_POST[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed POST values are sanitized or validated at the call site.
		return (string) wp_unslash($_POST[$key]);
	}
}

if (!function_exists('vms_goals_post_array')) {
	function vms_goals_post_array(string $key): array
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed POST arrays are sanitized element-by-element at the call site.
		if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed POST arrays are sanitized element-by-element at the call site.
		return wp_unslash($_POST[$key]);
	}
}

if (!function_exists('vms_goals_query_value')) {
	function vms_goals_query_value(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin query values are sanitized by the caller.
		if (!isset($_GET[$key]) || is_array($_GET[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin query values are sanitized by the caller.
		return (string) wp_unslash($_GET[$key]);
	}
}

if (!function_exists('vms_goals_request_value')) {
	function vms_goals_request_value(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Caller performs capability and nonce validation for this request.
		if (!isset($_REQUEST[$key]) || is_array($_REQUEST[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Caller performs capability and nonce validation for this request.
		return (string) wp_unslash($_REQUEST[$key]);
	}
}

if (!function_exists('vms_goals_admin_redirect_with_notice')) {
	function vms_goals_admin_redirect_with_notice(string $status, string $message, array $extra = array()): void
	{
		$args = array_merge($extra, array(
			'vms_goals_status' => sanitize_key($status),
			'vms_goals_message' => sanitize_text_field($message),
		));
		wp_safe_redirect(vms_goals_admin_url($args));
		exit;
	}
}

add_action('admin_menu', 'vms_goals_admin_menu', 45);
function vms_goals_admin_menu(): void
{
	add_submenu_page(
		'vms-dashboard',
		__('Goals & Forecasting', 'vms'),
		__('Goals & Forecasting', 'vms'),
		'manage_options',
		'vms-goals-forecast',
		'vms_goals_render_admin_page'
	);
}

add_action('admin_enqueue_scripts', 'vms_goals_admin_enqueue_assets');
function vms_goals_admin_enqueue_assets(string $hook): void
{
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	$page = sanitize_key(vms_goals_query_value('page'));

	$load = ($page === 'vms-goals-forecast');
	if ($screen && isset($screen->post_type) && $screen->post_type === 'vms_event_plan') {
		$load = true;
	}

	if (!$load) {
		return;
	}

	$css = VMS_PLUGIN_URL . 'assets/css/vms-goals-forecast-admin.css';
	wp_enqueue_style(
		'vms-goals-forecast-admin',
		$css,
		array(),
		function_exists('vms_asset_version') ? vms_asset_version() : (defined('VMS_VERSION') ? (string) VMS_VERSION : '')
	);

	$js = <<<'JS'
jQuery(function($){
  function setActiveTab($root, tab) {
    $root.find('[data-vms-goals-tab]').removeClass('is-active');
    $root.find('[data-vms-goals-tab="' + tab + '"]').addClass('is-active');
    $root.find('[data-vms-goals-panel]').hide();
    $root.find('[data-vms-goals-panel="' + tab + '"]').show();
  }

  $('.vms-goals-finance').each(function(){
    var $root = $(this);
    var initial = $root.find('[data-vms-goals-tab].is-active').data('vmsGoalsTab') || 'goal-impact';
    setActiveTab($root, initial);
    $root.on('click', '[data-vms-goals-tab]', function(e){
      e.preventDefault();
      setActiveTab($root, $(this).data('vmsGoalsTab'));
    });
  });

  $(document).on('click', '.vms-goals-refresh-actuals', function(e){
    e.preventDefault();
    var url = $(this).data('actionUrl');
    if (url) window.location.href = url;
  });
});
JS;
	wp_add_inline_script('jquery-core', $js);
}

add_action('admin_post_vms_goals_save_settings', 'vms_goals_admin_post_save_settings');
function vms_goals_admin_post_save_settings(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}
	check_admin_referer('vms_goals_save_settings');

	$tab = sanitize_key(vms_goals_post_value('tab'));
	if ($tab === '') {
		$tab = 'forecast-defaults';
	}
	$update = array();

	if ($tab === 'overhead-rules') {
		$update['overhead_rules'] = array(
			'mode' => sanitize_key(vms_goals_post_value('overhead_mode')),
			'flat_per_event_cents' => vms_goals_parse_money_to_cents(vms_goals_post_value('overhead_flat_per_event')),
			'per_attendee_cents' => vms_goals_parse_money_to_cents(vms_goals_post_value('overhead_per_attendee')),
			'percent_of_gross_bps' => max(0, min(10000, (int) vms_goals_post_value('overhead_percent_bps'))),
		);
		if ($update['overhead_rules']['mode'] === '') {
			$update['overhead_rules']['mode'] = 'flat_per_event';
		}
	} else {
		$map_keys = vms_goals_post_array('bucket_map_key');
		$map_components = vms_goals_post_array('bucket_map_component');
		$bucket_map = array();
		foreach ($map_keys as $i => $bucket_key) {
			$key = sanitize_key((string) $bucket_key);
			if ($key === '') {
				continue;
			}
			$component = isset($map_components[$i]) ? sanitize_key((string) $map_components[$i]) : '';
			if (!in_array($component, array('ticket', 'concessions', 'add_on', 'other'), true)) {
				continue;
			}
			$bucket_map[$key] = $component;
		}

		$default_metric = sanitize_key(vms_goals_post_value('default_metric'));
		if ($default_metric === '') {
			$default_metric = 'true_profit';
		}

		$default_allocation_mode = sanitize_key(vms_goals_post_value('default_allocation_mode'));
		if ($default_allocation_mode === '') {
			$default_allocation_mode = 'even';
		}

		$default_trailing_window_events_raw = vms_goals_post_value('default_trailing_window_events');
		$default_trailing_window_events = ($default_trailing_window_events_raw !== '') ? max(1, (int) $default_trailing_window_events_raw) : 6;

		$headcount_default_door_mode = sanitize_key(vms_goals_post_value('headcount_default_door_mode'));
		if ($headcount_default_door_mode === '') {
			$headcount_default_door_mode = 'percent';
		}

		$headcount_default_door_percent_raw = vms_goals_post_value('headcount_default_door_percent');
		$headcount_default_door_percent = ($headcount_default_door_percent_raw !== '') ? max(0, min(95, (int) $headcount_default_door_percent_raw)) : 15;

		$headcount_default_door_count_raw = vms_goals_post_value('headcount_default_door_count');
		$headcount_default_door_count = ($headcount_default_door_count_raw !== '') ? max(0, (int) $headcount_default_door_count_raw) : 0;

		$concessions_buyer_rate_pct_raw = vms_goals_post_value('concessions_buyer_rate_pct');
		$concessions_buyer_rate_pct = ($concessions_buyer_rate_pct_raw !== '') ? max(0, min(100, (int) $concessions_buyer_rate_pct_raw)) : 35;

		$update = array(
			'default_metric' => $default_metric,
			'default_overhead_mode' => (vms_goals_post_value('include_overhead_by_default') !== '') ? 'include_overhead' : 'exclude_overhead',
			'default_allocation_mode' => $default_allocation_mode,
			'default_trailing_window_events' => $default_trailing_window_events,
			'headcount_default_door_mode' => $headcount_default_door_mode,
			'headcount_default_door_percent' => $headcount_default_door_percent,
			'headcount_default_door_count' => $headcount_default_door_count,
			'default_avg_ticket_price_cents' => vms_goals_parse_money_to_cents(vms_goals_post_value('default_avg_ticket_price')),
			'provider_bucket_component_map' => $bucket_map,
			'concessions_model_defaults' => array(
				'buyer_rate_pct' => $concessions_buyer_rate_pct,
				'avg_spend_cents' => vms_goals_parse_money_to_cents(vms_goals_post_value('concessions_avg_spend')),
				'mode' => 'simple',
				'use_bucket_keys' => array_values(array_filter(array_map('sanitize_key', vms_goals_post_array('concessions_use_bucket_keys')))),
			),
		);
	}

	vms_goals_update_settings($update);
	vms_goals_admin_redirect_with_notice('success', 'Settings saved.', array('tab' => $tab));
}

add_action('admin_post_vms_goals_save_goal', 'vms_goals_admin_post_save_goal');
function vms_goals_admin_post_save_goal(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}
	check_admin_referer('vms_goals_save_goal');

	$goal_id = absint(vms_goals_post_value('goal_id'));
	$payload = array(
		'name' => sanitize_text_field(vms_goals_post_value('name')),
		'metric' => sanitize_key(vms_goals_post_value('metric')),
		'period_type' => sanitize_key(vms_goals_post_value('period_type')),
		'period_start_local' => sanitize_text_field(vms_goals_post_value('period_start_local')),
		'period_end_local' => sanitize_text_field(vms_goals_post_value('period_end_local')),
		'target_cents' => vms_goals_post_value('target_amount'),
		'allocation_mode' => sanitize_key(vms_goals_post_value('allocation_mode')),
		'weight_mode' => sanitize_key(vms_goals_post_value('weight_mode')),
		'venue_id' => absint(vms_goals_post_value('venue_id')),
		'is_active' => (vms_goals_post_value('is_active') !== '') ? 1 : 0,
	);
	if ($payload['metric'] === '') {
		$payload['metric'] = 'true_profit';
	}
	if ($payload['period_type'] === '') {
		$payload['period_type'] = 'month';
	}
	if ($payload['allocation_mode'] === '') {
		$payload['allocation_mode'] = 'even';
	}
	if ($payload['weight_mode'] === '') {
		$payload['weight_mode'] = 'none';
	}

	$result = vms_goals_save_goal($payload, $goal_id);
	if (empty($result['ok'])) {
		vms_goals_admin_redirect_with_notice('error', (string) ($result['message'] ?? 'Unable to save goal.'), array('tab' => 'goals'));
	}
	vms_goals_admin_redirect_with_notice('success', (string) ($result['message'] ?? 'Goal saved.'), array('tab' => 'goals'));
}

add_action('admin_post_vms_goals_delete_goal', 'vms_goals_admin_post_delete_goal');
function vms_goals_admin_post_delete_goal(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}
	$goal_id = absint(vms_goals_query_value('goal_id'));
	$nonce = sanitize_text_field(vms_goals_query_value('_wpnonce'));
	if ($goal_id <= 0 || !wp_verify_nonce($nonce, 'vms_goals_delete_goal_' . $goal_id)) {
		vms_goals_admin_redirect_with_notice('error', 'Delete request failed security checks.', array('tab' => 'goals'));
	}

	$ok = vms_goals_delete_goal($goal_id);
	vms_goals_admin_redirect_with_notice($ok ? 'success' : 'error', $ok ? 'Goal deleted.' : 'Unable to delete goal.', array('tab' => 'goals'));
}

add_action('admin_post_vms_goals_activate_goal', 'vms_goals_admin_post_activate_goal');
function vms_goals_admin_post_activate_goal(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}
	$goal_id = absint(vms_goals_query_value('goal_id'));
	$nonce = sanitize_text_field(vms_goals_query_value('_wpnonce'));
	if ($goal_id <= 0 || !wp_verify_nonce($nonce, 'vms_goals_activate_goal_' . $goal_id)) {
		vms_goals_admin_redirect_with_notice('error', 'Activate request failed security checks.', array('tab' => 'goals'));
	}

	if (function_exists('vms_goals_set_active_goal') && vms_goals_set_active_goal($goal_id)) {
		vms_goals_admin_redirect_with_notice('success', 'Goal activated.', array('tab' => 'goals'));
	}

	vms_goals_admin_redirect_with_notice('error', 'Unable to activate goal.', array('tab' => 'goals'));
}

if (!function_exists('vms_goals_render_admin_notice')) {
	function vms_goals_render_admin_notice(): void
	{
		$status = sanitize_key(vms_goals_query_value('vms_goals_status'));
		$message = sanitize_text_field(vms_goals_query_value('vms_goals_message'));
		if ($status === '' || $message === '') {
			return;
		}
		$class = ($status === 'success') ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
	}
}

if (!function_exists('vms_goals_render_admin_page')) {
	function vms_goals_render_admin_page(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$tab = sanitize_key(vms_goals_query_value('tab'));
		if ($tab === '') {
			$tab = 'goals';
		}
		if (!in_array($tab, array('goals', 'overhead-rules', 'forecast-defaults'), true)) {
			$tab = 'goals';
		}

		$settings = vms_goals_get_settings();
		$goals = vms_goals_list();
		$edit_goal_id = absint(vms_goals_query_value('edit_goal_id'));
		$edit_goal = ($edit_goal_id > 0) ? vms_goals_get_goal($edit_goal_id) : array();

		echo '<div class="wrap vms-goals-admin">';
		echo '<h1>' . esc_html__('Goals & Forecasting', 'vms') . '</h1>';
		vms_goals_render_admin_notice();

		echo '<h2 class="nav-tab-wrapper">';
		echo '<a class="nav-tab ' . ($tab === 'goals' ? 'nav-tab-active' : '') . '" href="' . esc_url(vms_goals_admin_url(array('tab' => 'goals'))) . '">' . esc_html__('Goals', 'vms') . '</a>';
		echo '<a class="nav-tab ' . ($tab === 'overhead-rules' ? 'nav-tab-active' : '') . '" href="' . esc_url(vms_goals_admin_url(array('tab' => 'overhead-rules'))) . '">' . esc_html__('Overhead Rules', 'vms') . '</a>';
		echo '<a class="nav-tab ' . ($tab === 'forecast-defaults' ? 'nav-tab-active' : '') . '" href="' . esc_url(vms_goals_admin_url(array('tab' => 'forecast-defaults'))) . '">' . esc_html__('Forecast Defaults', 'vms') . '</a>';
		echo '</h2>';

		if ($tab === 'goals') {
			vms_goals_render_goals_tab($goals, $edit_goal);
		} elseif ($tab === 'overhead-rules') {
			vms_goals_render_overhead_tab($settings);
		} else {
			vms_goals_render_forecast_defaults_tab($settings);
		}

		echo '</div>';
	}
}

if (!function_exists('vms_goals_render_goals_tab')) {
	function vms_goals_render_goals_tab(array $goals, array $edit_goal): void
	{
		echo '<div class="vms-goals-grid">';
		echo '<section class="vms-goals-card">';
		echo '<h2>' . esc_html__('Goals', 'vms') . '</h2>';
		if (empty($goals)) {
			echo '<p>' . esc_html__('No goals created yet.', 'vms') . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__('Name', 'vms') . '</th>';
			echo '<th>' . esc_html__('Metric', 'vms') . '</th>';
			echo '<th>' . esc_html__('Period', 'vms') . '</th>';
			echo '<th>' . esc_html__('Target', 'vms') . '</th>';
			echo '<th>' . esc_html__('Active', 'vms') . '</th>';
			echo '<th>' . esc_html__('Actions', 'vms') . '</th>';
			echo '</tr></thead><tbody>';
			foreach ($goals as $goal) {
				$goal_id = (int) ($goal['id'] ?? 0);
				$name = (string) ($goal['name'] ?? '');
				$metric = (string) ($goal['metric'] ?? '');
				$period = (string) ($goal['period_type'] ?? '') . ': ' . (string) ($goal['period_start_local'] ?? '') . ' → ' . (string) ($goal['period_end_local'] ?? '');
				$target = vms_goals_fmt_money((int) ($goal['target_cents'] ?? 0));
				$is_active = !empty($goal['is_active']);

				$edit_url = vms_goals_admin_url(array('tab' => 'goals', 'edit_goal_id' => $goal_id));
				$delete_url = wp_nonce_url(
					admin_url('admin-post.php?action=vms_goals_delete_goal&goal_id=' . $goal_id),
					'vms_goals_delete_goal_' . $goal_id
				);
				$activate_url = wp_nonce_url(
					admin_url('admin-post.php?action=vms_goals_activate_goal&goal_id=' . $goal_id),
					'vms_goals_activate_goal_' . $goal_id
				);

				echo '<tr>';
				echo '<td>' . esc_html($name) . '</td>';
				echo '<td><code>' . esc_html($metric) . '</code></td>';
				echo '<td>' . esc_html($period) . '</td>';
				echo '<td>' . esc_html($target) . '</td>';
				echo '<td>' . ($is_active ? esc_html__('Yes', 'vms') : esc_html__('No', 'vms')) . '</td>';
				echo '<td>';
				echo '<a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'vms') . '</a> ';
				if (!$is_active) {
					echo '<a class="button button-small" href="' . esc_url($activate_url) . '">' . esc_html__('Set Active', 'vms') . '</a> ';
				}
				echo '<a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete this goal?\');">' . esc_html__('Delete', 'vms') . '</a>';
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';

		$goal = !empty($edit_goal) ? $edit_goal : array();
		$goal_id = (int) ($goal['id'] ?? 0);
		$name = (string) ($goal['name'] ?? '');
		$metric = (string) ($goal['metric'] ?? 'true_profit');
		$period_type = (string) ($goal['period_type'] ?? 'month');
		$period_start = (string) ($goal['period_start_local'] ?? '');
		$period_end = (string) ($goal['period_end_local'] ?? '');
		$target_cents = (int) ($goal['target_cents'] ?? 0);
		$allocation_mode = (string) ($goal['allocation_mode'] ?? 'even');
		$weight_mode = (string) ($goal['weight_mode'] ?? 'none');
		$venue_id = (int) ($goal['venue_id'] ?? 0);
		$is_active = !empty($goal['is_active']);

		echo '<section class="vms-goals-card">';
		echo '<h2>' . esc_html($goal_id > 0 ? __('Edit Goal', 'vms') : __('Add Goal', 'vms')) . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-goals-form">';
		wp_nonce_field('vms_goals_save_goal');
		echo '<input type="hidden" name="action" value="vms_goals_save_goal" />';
		echo '<input type="hidden" name="goal_id" value="' . (int) $goal_id . '" />';

		echo '<label><strong>' . esc_html__('Name', 'vms') . '</strong></label>';
		echo '<input type="text" name="name" value="' . esc_attr($name) . '" required />';

		echo '<label><strong>' . esc_html__('Metric', 'vms') . '</strong></label>';
		echo '<select name="metric">';
		echo '<option value="true_profit" ' . selected($metric, 'true_profit', false) . '>' . esc_html__('True Profit', 'vms') . '</option>';
		echo '<option value="event_profit" ' . selected($metric, 'event_profit', false) . '>' . esc_html__('Event Profit', 'vms') . '</option>';
		echo '<option value="gross_revenue" ' . selected($metric, 'gross_revenue', false) . '>' . esc_html__('Gross Revenue', 'vms') . '</option>';
		echo '</select>';

		echo '<label><strong>' . esc_html__('Period Type', 'vms') . '</strong></label>';
		echo '<select name="period_type">';
		foreach (array('year', 'quarter', 'month', 'week', 'custom') as $type) {
			echo '<option value="' . esc_attr($type) . '" ' . selected($period_type, $type, false) . '>' . esc_html(ucfirst($type)) . '</option>';
		}
		echo '</select>';

		echo '<label><strong>' . esc_html__('Custom Start (used only for custom)', 'vms') . '</strong></label>';
		echo '<input type="text" name="period_start_local" value="' . esc_attr($period_start) . '" placeholder="YYYY-MM-DD HH:MM:SS" />';
		echo '<label><strong>' . esc_html__('Custom End (used only for custom)', 'vms') . '</strong></label>';
		echo '<input type="text" name="period_end_local" value="' . esc_attr($period_end) . '" placeholder="YYYY-MM-DD HH:MM:SS" />';

		echo '<label><strong>' . esc_html__('Target Amount', 'vms') . '</strong></label>';
		echo '<input type="text" name="target_amount" value="' . esc_attr(number_format($target_cents / 100, 2, '.', '')) . '" placeholder="50000.00" />';

		echo '<label><strong>' . esc_html__('Allocation Mode', 'vms') . '</strong></label>';
		echo '<select name="allocation_mode">';
		echo '<option value="even" ' . selected($allocation_mode, 'even', false) . '>' . esc_html__('Even', 'vms') . '</option>';
		echo '<option value="weighted" ' . selected($allocation_mode, 'weighted', false) . '>' . esc_html__('Weighted', 'vms') . '</option>';
		echo '</select>';

		echo '<label><strong>' . esc_html__('Weight Mode', 'vms') . '</strong></label>';
		echo '<select name="weight_mode">';
		echo '<option value="none" ' . selected($weight_mode, 'none', false) . '>None</option>';
		echo '<option value="forecast_headcount" ' . selected($weight_mode, 'forecast_headcount', false) . '>Forecast Headcount</option>';
		echo '<option value="forecast_revenue" ' . selected($weight_mode, 'forecast_revenue', false) . '>Forecast Revenue</option>';
		echo '</select>';

		echo '<label><strong>' . esc_html__('Venue ID (optional)', 'vms') . '</strong></label>';
		echo '<input type="number" min="0" step="1" name="venue_id" value="' . (int) $venue_id . '" />';

		echo '<label><input type="checkbox" name="is_active" value="1" ' . checked($is_active, true, false) . ' /> ' . esc_html__('Set as active goal', 'vms') . '</label>';

		echo '<p><button class="button button-primary">' . esc_html__('Save Goal', 'vms') . '</button></p>';
		echo '</form>';
		echo '</section>';

		echo '</div>';
	}
}

if (!function_exists('vms_goals_render_overhead_tab')) {
	function vms_goals_render_overhead_tab(array $settings): void
	{
		$overhead = isset($settings['overhead_rules']) && is_array($settings['overhead_rules']) ? $settings['overhead_rules'] : array();
		echo '<section class="vms-goals-card">';
		echo '<h2>' . esc_html__('Overhead Rules', 'vms') . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-goals-form">';
		wp_nonce_field('vms_goals_save_settings');
		echo '<input type="hidden" name="action" value="vms_goals_save_settings" />';
		echo '<input type="hidden" name="tab" value="overhead-rules" />';

		echo '<label><strong>' . esc_html__('Mode', 'vms') . '</strong></label>';
		echo '<select name="overhead_mode">';
		foreach (array('flat_per_event', 'per_attendee', 'percent_of_gross', 'hybrid') as $mode) {
			echo '<option value="' . esc_attr($mode) . '" ' . selected((string) ($overhead['mode'] ?? ''), $mode, false) . '>' . esc_html($mode) . '</option>';
		}
		echo '</select>';

		echo '<label><strong>' . esc_html__('Flat per event ($)', 'vms') . '</strong></label>';
		echo '<input type="text" name="overhead_flat_per_event" value="' . esc_attr(number_format(((int) ($overhead['flat_per_event_cents'] ?? 0)) / 100, 2, '.', '')) . '" />';
		echo '<label><strong>' . esc_html__('Per attendee ($)', 'vms') . '</strong></label>';
		echo '<input type="text" name="overhead_per_attendee" value="' . esc_attr(number_format(((int) ($overhead['per_attendee_cents'] ?? 0)) / 100, 2, '.', '')) . '" />';
		echo '<label><strong>' . esc_html__('Percent of gross (basis points)', 'vms') . '</strong></label>';
		echo '<input type="number" min="0" max="10000" step="1" name="overhead_percent_bps" value="' . (int) ($overhead['percent_of_gross_bps'] ?? 0) . '" />';
		echo '<p class="description">250 bps = 2.50%</p>';

		echo '<p><button class="button button-primary">' . esc_html__('Save Overhead Rules', 'vms') . '</button></p>';
		echo '</form>';
		echo '</section>';
	}
}

if (!function_exists('vms_goals_render_forecast_defaults_tab')) {
	function vms_goals_render_forecast_defaults_tab(array $settings): void
	{
		$concessions = isset($settings['concessions_model_defaults']) && is_array($settings['concessions_model_defaults'])
			? $settings['concessions_model_defaults']
			: array();
		$map = isset($settings['provider_bucket_component_map']) && is_array($settings['provider_bucket_component_map'])
			? $settings['provider_bucket_component_map']
			: array();

		if (empty($map)) {
			$map = array('door' => 'ticket');
		}

		echo '<section class="vms-goals-card">';
		echo '<h2>' . esc_html__('Forecast Defaults', 'vms') . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-goals-form">';
		wp_nonce_field('vms_goals_save_settings');
		echo '<input type="hidden" name="action" value="vms_goals_save_settings" />';
		echo '<input type="hidden" name="tab" value="forecast-defaults" />';

		echo '<label><strong>Default metric</strong></label>';
		echo '<select name="default_metric">';
		echo '<option value="true_profit" ' . selected((string) $settings['default_metric'], 'true_profit', false) . '>True Profit</option>';
		echo '<option value="event_profit" ' . selected((string) $settings['default_metric'], 'event_profit', false) . '>Event Profit</option>';
		echo '<option value="gross_revenue" ' . selected((string) $settings['default_metric'], 'gross_revenue', false) . '>Gross Revenue</option>';
		echo '</select>';

		echo '<label><input type="checkbox" name="include_overhead_by_default" value="1" ' . checked((string) $settings['default_overhead_mode'], 'include_overhead', false) . ' /> Include overhead by default</label>';

		echo '<label><strong>Default allocation mode</strong></label>';
		echo '<select name="default_allocation_mode">';
		echo '<option value="even" ' . selected((string) $settings['default_allocation_mode'], 'even', false) . '>Even</option>';
		echo '<option value="weighted" ' . selected((string) $settings['default_allocation_mode'], 'weighted', false) . '>Weighted</option>';
		echo '</select>';

		echo '<label><strong>Trailing window events</strong></label>';
		echo '<input type="number" min="1" max="52" step="1" name="default_trailing_window_events" value="' . (int) $settings['default_trailing_window_events'] . '" />';

		echo '<label><strong>Door default mode</strong></label>';
		echo '<select name="headcount_default_door_mode">';
		echo '<option value="percent" ' . selected((string) $settings['headcount_default_door_mode'], 'percent', false) . '>Percent</option>';
		echo '<option value="count" ' . selected((string) $settings['headcount_default_door_mode'], 'count', false) . '>Count</option>';
		echo '</select>';

		echo '<label><strong>Door default percent</strong></label>';
		echo '<input type="number" min="0" max="95" step="1" name="headcount_default_door_percent" value="' . (int) $settings['headcount_default_door_percent'] . '" />';

		echo '<label><strong>Door default count</strong></label>';
		echo '<input type="number" min="0" step="1" name="headcount_default_door_count" value="' . (int) $settings['headcount_default_door_count'] . '" />';

		echo '<label><strong>Default avg ticket price ($)</strong></label>';
		echo '<input type="text" name="default_avg_ticket_price" value="' . esc_attr(number_format(((int) $settings['default_avg_ticket_price_cents']) / 100, 2, '.', '')) . '" />';

		echo '<h3>Concessions defaults</h3>';
		echo '<label><strong>Buyer rate %</strong></label>';
		echo '<input type="number" min="0" max="100" step="1" name="concessions_buyer_rate_pct" value="' . (int) ($concessions['buyer_rate_pct'] ?? 35) . '" />';
		echo '<label><strong>Average spend ($)</strong></label>';
		echo '<input type="text" name="concessions_avg_spend" value="' . esc_attr(number_format(((int) ($concessions['avg_spend_cents'] ?? 1800)) / 100, 2, '.', '')) . '" />';

		echo '<h3>Provider bucket mapping</h3>';
		echo '<p class="description">Default behavior: bucket <code>door</code> maps to tickets, all others map to concessions.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Bucket key</th><th>Component</th></tr></thead><tbody>';
		$idx = 0;
		foreach ($map as $bucket_key => $component) {
			echo '<tr>';
			echo '<td><input type="text" name="bucket_map_key[' . (int) $idx . ']" value="' . esc_attr((string) $bucket_key) . '" /></td>';
			echo '<td><select name="bucket_map_component[' . (int) $idx . ']">';
			foreach (array('ticket', 'concessions', 'add_on', 'other') as $opt) {
				echo '<option value="' . esc_attr($opt) . '" ' . selected((string) $component, $opt, false) . '>' . esc_html($opt) . '</option>';
			}
			echo '</select></td>';
			echo '</tr>';
			$idx++;
		}
		for ($i = 0; $i < 2; $i++) {
			echo '<tr>';
			echo '<td><input type="text" name="bucket_map_key[' . (int) ($idx + $i) . ']" value="" /></td>';
			echo '<td><select name="bucket_map_component[' . (int) ($idx + $i) . ']">';
			foreach (array('ticket', 'concessions', 'add_on', 'other') as $opt) {
				echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
			}
			echo '</select></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<p><button class="button button-primary">Save Forecast Defaults</button></p>';
		echo '</form>';
		echo '</section>';
	}
}

add_action('add_meta_boxes', 'vms_goals_event_plan_add_metabox', 25);
function vms_goals_event_plan_add_metabox(): void
{
	add_meta_box(
		'vms_event_plan_goals_finance',
		__('Finance: Goal Impact + Profitability', 'vms'),
		'vms_goals_event_plan_metabox_html',
		'vms_event_plan',
		'normal',
		'default'
	);
}

if (!function_exists('vms_goals_event_plan_refresh_url')) {
	function vms_goals_event_plan_refresh_url(int $event_plan_id): string
	{
		$redirect = get_edit_post_link($event_plan_id, 'raw');
		if (!is_string($redirect) || $redirect === '') {
			$redirect = admin_url('post.php?post=' . $event_plan_id . '&action=edit');
		}
		$url = add_query_arg(
			array(
				'action' => 'vms_goals_refresh_event_actuals',
				'event_plan_id' => $event_plan_id,
				'redirect' => $redirect,
			),
			admin_url('admin-post.php')
		);
		return wp_nonce_url($url, 'vms_goals_refresh_event_actuals_' . $event_plan_id);
	}
}

if (!function_exists('vms_goals_event_plan_metabox_html')) {
	function vms_goals_event_plan_metabox_html(WP_Post $post): void
	{
		$forecast = max(0, (int) get_post_meta($post->ID, '_vms_forecast_headcount', true));
		$door_mode = (string) get_post_meta($post->ID, '_vms_door_sales_mode', true);
		if (!in_array($door_mode, array('percent', 'count'), true)) {
			$door_mode = 'percent';
		}
		$door_percent = max(0, (int) get_post_meta($post->ID, '_vms_door_sales_percent', true));
		$door_count = max(0, (int) get_post_meta($post->ID, '_vms_door_sales_count', true));
		$comp_forecast = max(0, (int) get_post_meta($post->ID, '_vms_comp_headcount_forecast', true));
		$true_hc = max(0, (int) get_post_meta($post->ID, '_vms_true_headcount', true));
		$comp_true = max(0, (int) get_post_meta($post->ID, '_vms_comp_headcount_true', true));
		$concessions_actual_cents = max(0, (int) get_post_meta($post->ID, '_vms_concessions_actual_cents', true));
		$direct_costs_cents = max(0, (int) get_post_meta($post->ID, '_vms_event_direct_costs_cents', true));
		$processing_fees_cents = max(0, (int) get_post_meta($post->ID, '_vms_event_processing_fees_cents', true));

		$provider_detected = vms_pos_provider_detect();
		$provider_names = array_keys($provider_detected);
		$provider_status = empty($provider_names) ? 'No provider detected' : ('Detected: ' . implode(', ', $provider_names));

		$snapshot_provider = (string) get_post_meta($post->ID, '_vms_event_actuals_provider', true);
		$snapshot_pulled = (string) get_post_meta($post->ID, '_vms_event_actuals_pulled_at_utc', true);
		$snapshot_totals = vms_goals_get_manual_event_actual_totals($post->ID);
		$refresh_url = vms_goals_event_plan_refresh_url((int) $post->ID);

		$active_goal = vms_goals_get_active_goal();
		$goal_progress = !empty($active_goal) ? vms_goals_compute_goal_progress($active_goal) : array();
		$required_for_this_event = 0;
		if (!empty($goal_progress['allocations']) && is_array($goal_progress['allocations'])) {
			foreach ($goal_progress['allocations'] as $allocation) {
				if ((int) ($allocation['event_plan_id'] ?? 0) === (int) $post->ID) {
					$required_for_this_event = (int) ($allocation['required_contribution_cents'] ?? 0);
					break;
				}
			}
		}

		$pnl_forecast = vms_goals_get_event_pnl($post->ID, array('headcount_mode' => 'forecast', 'include_overhead' => true));
		$pnl_ticketed = vms_goals_get_event_pnl($post->ID, array('headcount_mode' => 'ticketed', 'include_overhead' => true));
		$pnl_true = vms_goals_get_event_pnl($post->ID, array('headcount_mode' => 'true', 'include_overhead' => true));
		$break_even = vms_goals_break_even_headcount($post->ID, array('include_overhead' => true));

		$notice_status = sanitize_key(vms_goals_query_value('vms_goals_actuals_status'));
		$notice_event = absint(vms_goals_query_value('vms_goals_actuals_event'));
		$notice_msg = sanitize_text_field(vms_goals_query_value('vms_goals_actuals_message'));

		wp_nonce_field('vms_goals_event_finance_save', 'vms_goals_event_finance_nonce');

		echo '<div class="vms-goals-finance">';
		if ($notice_event === (int) $post->ID && $notice_msg !== '' && in_array($notice_status, array('success', 'error'), true)) {
			$class = ($notice_status === 'success') ? 'notice-success' : 'notice-error';
			echo '<div class="notice inline ' . esc_attr($class) . '"><p>' . esc_html($notice_msg) . '</p></div>';
		}

		echo '<div class="vms-goals-tabs">';
		echo '<button type="button" class="button button-secondary is-active" data-vms-goals-tab="goal-impact">Goal Impact</button> ';
		echo '<button type="button" class="button button-secondary" data-vms-goals-tab="event-profitability">Event Profitability</button>';
		echo '</div>';

		echo '<div class="vms-goals-panel" data-vms-goals-panel="goal-impact">';
		if (empty($active_goal)) {
			echo '<p>No active goal selected. Configure one in <a href="' . esc_url(vms_goals_admin_url(array('tab' => 'goals'))) . '">Goals &amp; Forecasting</a>.</p>';
		} else {
			if (!empty($goal_progress['is_truncated'])) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__('Goal calculations are performance-capped for this period. Narrow date range for full precision.', 'vms') . '</p></div>';
			}
			echo '<p><strong>Active Goal:</strong> ' . esc_html((string) ($active_goal['name'] ?? '')) . '</p>';
			echo '<p><strong>Required contribution for this event:</strong> ' . esc_html(vms_goals_fmt_money($required_for_this_event)) . '</p>';
			echo '<p><strong>Projected true profit (forecast mode):</strong> ' . esc_html(vms_goals_fmt_money((int) ($pnl_forecast['true_profit_cents'] ?? 0))) . '</p>';
			$delta = (int) ($pnl_forecast['true_profit_cents'] ?? 0) - $required_for_this_event;
			echo '<p><strong>Delta vs required:</strong> ' . esc_html(vms_goals_fmt_money($delta)) . '</p>';
			if (!empty($goal_progress)) {
				echo '<p><strong>Remaining required across goal:</strong> ' . esc_html(vms_goals_fmt_money((int) ($goal_progress['remaining_required_cents'] ?? 0))) . '</p>';
				echo '<p><strong>Required avg per remaining event:</strong> ' . esc_html(vms_goals_fmt_money((int) ($goal_progress['required_avg_per_remaining_event_cents'] ?? 0))) . '</p>';
			}
		}
		echo '</div>';

		echo '<div class="vms-goals-panel" data-vms-goals-panel="event-profitability">';
		echo '<p><strong>Provider status:</strong> ' . esc_html($provider_status) . '</p>';
		echo '<p><button type="button" class="button vms-goals-refresh-actuals" data-action-url="' . esc_url($refresh_url) . '">Refresh Actuals</button></p>';
		if ($snapshot_provider !== '') {
			echo '<p><strong>Snapshot provider:</strong> ' . esc_html($snapshot_provider) . '</p>';
		}
		if ($snapshot_pulled !== '') {
			echo '<p><strong>Pulled at (UTC):</strong> ' . esc_html($snapshot_pulled) . '</p>';
		}

		echo '<div class="vms-goals-kpis">';
		echo '<div><strong>Forecast Profit</strong><br />' . esc_html(vms_goals_fmt_money((int) ($pnl_forecast['true_profit_cents'] ?? 0))) . '</div>';
		echo '<div><strong>Ticketed Profit</strong><br />' . esc_html(vms_goals_fmt_money((int) ($pnl_ticketed['true_profit_cents'] ?? 0))) . '</div>';
		echo '<div><strong>True Profit</strong><br />' . esc_html(vms_goals_fmt_money((int) ($pnl_true['true_profit_cents'] ?? 0))) . '</div>';
		echo '<div><strong>Break-even HC</strong><br />' . esc_html((string) ((int) ($break_even['break_even_headcount'] ?? 0))) . '</div>';
		echo '</div>';

		echo '<table class="widefat striped vms-goals-mini-table"><thead><tr><th>Metric</th><th>Forecast</th><th>Ticketed</th><th>True</th></tr></thead><tbody>';
		$rows = array(
			'Headcount' => array((int) ($pnl_forecast['headcount'] ?? 0), (int) ($pnl_ticketed['headcount'] ?? 0), (int) ($pnl_true['headcount'] ?? 0), false),
			'Gross Revenue' => array((int) ($pnl_forecast['gross_revenue_cents'] ?? 0), (int) ($pnl_ticketed['gross_revenue_cents'] ?? 0), (int) ($pnl_true['gross_revenue_cents'] ?? 0), true),
			'Direct Costs' => array((int) ($pnl_forecast['direct_costs_cents'] ?? 0), (int) ($pnl_ticketed['direct_costs_cents'] ?? 0), (int) ($pnl_true['direct_costs_cents'] ?? 0), true),
			'Processing Fees' => array((int) ($pnl_forecast['processing_fees_cents'] ?? 0), (int) ($pnl_ticketed['processing_fees_cents'] ?? 0), (int) ($pnl_true['processing_fees_cents'] ?? 0), true),
			'Overhead' => array((int) ($pnl_forecast['overhead_allocated_cents'] ?? 0), (int) ($pnl_ticketed['overhead_allocated_cents'] ?? 0), (int) ($pnl_true['overhead_allocated_cents'] ?? 0), true),
			'True Profit' => array((int) ($pnl_forecast['true_profit_cents'] ?? 0), (int) ($pnl_ticketed['true_profit_cents'] ?? 0), (int) ($pnl_true['true_profit_cents'] ?? 0), true),
		);
		foreach ($rows as $label => $vals) {
			echo '<tr><th scope="row">' . esc_html($label) . '</th>';
			for ($i = 0; $i < 3; $i++) {
				$val = (int) $vals[$i];
				if (!empty($vals[3])) {
					echo '<td>' . esc_html(vms_goals_fmt_money($val)) . '</td>';
				} else {
					echo '<td>' . esc_html((string) $val) . '</td>';
				}
			}
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h4>Manual Actual Inputs</h4>';
		echo '<p class="description">Manual values are always available and used when provider data is unavailable.</p>';
		echo '<div class="vms-goals-form-grid">';
		echo '<label>Forecast Headcount<input type="number" min="0" step="1" name="vms_forecast_headcount" value="' . (int) $forecast . '" /></label>';
		echo '<label>Door Sales Mode<select name="vms_door_sales_mode"><option value="percent" ' . selected($door_mode, 'percent', false) . '>Percent</option><option value="count" ' . selected($door_mode, 'count', false) . '>Count</option></select></label>';
		echo '<label>Door Sales Percent<input type="number" min="0" max="95" step="1" name="vms_door_sales_percent" value="' . (int) $door_percent . '" /></label>';
		echo '<label>Door Sales Count<input type="number" min="0" step="1" name="vms_door_sales_count" value="' . (int) $door_count . '" /></label>';
		echo '<label>Forecast Comp Headcount<input type="number" min="0" step="1" name="vms_comp_headcount_forecast" value="' . (int) $comp_forecast . '" /></label>';
		echo '<label>True Headcount<input type="number" min="0" step="1" name="vms_true_headcount" value="' . (int) $true_hc . '" /></label>';
		echo '<label>True Comp Headcount<input type="number" min="0" step="1" name="vms_comp_headcount_true" value="' . (int) $comp_true . '" /></label>';
		echo '<label>Concessions Actual ($)<input type="text" name="vms_concessions_actual" value="' . esc_attr(number_format($concessions_actual_cents / 100, 2, '.', '')) . '" /></label>';
		echo '<label>Direct Costs Actual ($)<input type="text" name="vms_event_direct_costs_actual" value="' . esc_attr(number_format($direct_costs_cents / 100, 2, '.', '')) . '" /></label>';
		echo '<label>Processing Fees Actual ($)<input type="text" name="vms_event_processing_fees_actual" value="' . esc_attr(number_format($processing_fees_cents / 100, 2, '.', '')) . '" /></label>';
		echo '</div>';

		echo '<details><summary>Show normalized actual totals</summary>';
		echo '<pre>' . esc_html(wp_json_encode($snapshot_totals, JSON_PRETTY_PRINT)) . '</pre>';
		echo '</details>';

		echo '</div>';
		echo '</div>';
	}
}

add_action('save_post_vms_event_plan', 'vms_goals_event_plan_save_meta', 35, 2);
function vms_goals_event_plan_save_meta(int $post_id, WP_Post $post): void
{
	if ($post->post_type !== 'vms_event_plan') {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}
	$nonce = sanitize_text_field(vms_goals_post_value('vms_goals_event_finance_nonce'));
	if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_goals_event_finance_save')) {
		return;
	}

	$forecast = max(0, (int) vms_goals_post_value('vms_forecast_headcount'));
	$door_mode = sanitize_key(vms_goals_post_value('vms_door_sales_mode'));
	if ($door_mode === '') {
		$door_mode = 'percent';
	}
	if (!in_array($door_mode, array('percent', 'count'), true)) {
		$door_mode = 'percent';
	}
	$door_percent = max(0, min(95, (int) vms_goals_post_value('vms_door_sales_percent')));
	$door_count = max(0, (int) vms_goals_post_value('vms_door_sales_count'));
	$comp_forecast = max(0, (int) vms_goals_post_value('vms_comp_headcount_forecast'));
	$true_hc = max(0, (int) vms_goals_post_value('vms_true_headcount'));
	$comp_true = max(0, (int) vms_goals_post_value('vms_comp_headcount_true'));
	$concessions_cents = vms_goals_parse_money_to_cents(vms_goals_post_value('vms_concessions_actual'));
	$direct_costs_cents = vms_goals_parse_money_to_cents(vms_goals_post_value('vms_event_direct_costs_actual'));
	$processing_cents = vms_goals_parse_money_to_cents(vms_goals_post_value('vms_event_processing_fees_actual'));

	update_post_meta($post_id, '_vms_forecast_headcount', $forecast);
	update_post_meta($post_id, '_vms_door_sales_mode', $door_mode);
	update_post_meta($post_id, '_vms_door_sales_percent', $door_percent);
	update_post_meta($post_id, '_vms_door_sales_count', $door_count);
	update_post_meta($post_id, '_vms_comp_headcount_forecast', $comp_forecast);
	update_post_meta($post_id, '_vms_true_headcount', $true_hc);
	update_post_meta($post_id, '_vms_comp_headcount_true', $comp_true);
	update_post_meta($post_id, '_vms_concessions_actual_cents', $concessions_cents);
	update_post_meta($post_id, '_vms_concessions_actual_source', 'manual');
	update_post_meta($post_id, '_vms_event_direct_costs_cents', $direct_costs_cents);
	update_post_meta($post_id, '_vms_event_processing_fees_cents', $processing_cents);

	$existing = vms_goals_get_manual_event_actual_totals($post_id);
	$existing['concessions_revenue_cents'] = $concessions_cents;
	$existing['direct_costs_cents'] = $direct_costs_cents;
	$existing['processing_fees_cents'] = $processing_cents;

	$gross = max(0, (int) ($existing['gross_revenue_cents'] ?? 0));
	$overhead = max(0, (int) ($existing['overhead_allocated_cents'] ?? 0));
	$existing['true_profit_cents'] = (int) ($gross - $direct_costs_cents - $processing_cents - $overhead);
	update_post_meta($post_id, '_vms_event_actuals_totals', $existing);
}

add_action('admin_post_vms_goals_refresh_event_actuals', 'vms_goals_admin_post_refresh_event_actuals');
function vms_goals_admin_post_refresh_event_actuals(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	$event_plan_id = absint(vms_goals_request_value('event_plan_id'));
	$redirect = esc_url_raw(vms_goals_request_value('redirect'));
	if ($redirect === '') {
		$redirect = admin_url('post.php?post=' . $event_plan_id . '&action=edit');
	}
	$redirect = wp_validate_redirect($redirect, admin_url('edit.php?post_type=vms_event_plan'));
	$nonce = sanitize_text_field(vms_goals_request_value('_wpnonce'));

	if ($event_plan_id <= 0 || !wp_verify_nonce($nonce, 'vms_goals_refresh_event_actuals_' . $event_plan_id)) {
		$redirect = add_query_arg(array(
			'vms_goals_actuals_status' => 'error',
			'vms_goals_actuals_event' => $event_plan_id,
			'vms_goals_actuals_message' => 'Refresh request failed security checks.',
		), $redirect);
		wp_safe_redirect($redirect);
		exit;
	}

	$result = vms_goals_refresh_event_actuals($event_plan_id);
	$status = !empty($result['ok']) ? 'success' : 'error';
	$message = sanitize_text_field((string) ($result['message'] ?? 'Refresh complete.'));
	$redirect = add_query_arg(array(
		'vms_goals_actuals_status' => $status,
		'vms_goals_actuals_event' => $event_plan_id,
		'vms_goals_actuals_message' => $message,
	), $redirect);
	wp_safe_redirect($redirect);
	exit;
}

if (!function_exists('vms_goals_render_dashboard_panel')) {
	function vms_goals_render_dashboard_panel(): void
	{
		$active_goal = vms_goals_get_active_goal();
		echo '<section id="vms-dashboard-goals">';
		echo '<h2>Goal Tracker</h2>';
		if (empty($active_goal)) {
			echo '<div class="vms-panel-body"><p>No active goal configured.</p>';
			echo '<p><a class="button button-secondary" href="' . esc_url(vms_goals_admin_url(array('tab' => 'goals'))) . '">Configure Goals</a></p></div>';
			echo '</section>';
			return;
		}

		$goal_id = (int) ($active_goal['id'] ?? 0);
		$cache_key = 'vms_goals_progress_' . $goal_id;
		$progress = get_transient($cache_key);
		if (!is_array($progress)) {
			$progress = vms_goals_compute_goal_progress($active_goal);
			set_transient($cache_key, $progress, 2 * MINUTE_IN_SECONDS);
		}
		echo '<div class="vms-panel-body vms-goals-dashboard-body">';
		if (!empty($progress['is_truncated'])) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__('Goal metrics are performance-capped; narrow the goal period for full precision.', 'vms') . '</p></div>';
		}
		echo '<p><strong>Goal:</strong> ' . esc_html((string) ($active_goal['name'] ?? '')) . '</p>';
		echo '<p><strong>Target:</strong> ' . esc_html(vms_goals_fmt_money((int) ($progress['target_cents'] ?? 0))) . '</p>';
		echo '<p><strong>Actual to date:</strong> ' . esc_html(vms_goals_fmt_money((int) ($progress['actual_to_date_cents'] ?? 0))) . '</p>';
		echo '<p><strong>Remaining required:</strong> ' . esc_html(vms_goals_fmt_money((int) ($progress['remaining_required_cents'] ?? 0))) . '</p>';
		echo '<p><strong>Remaining events:</strong> ' . esc_html((string) ((int) ($progress['remaining_events_count'] ?? 0))) . '</p>';
		echo '<p><strong>Required avg / event:</strong> ' . esc_html(vms_goals_fmt_money((int) ($progress['required_avg_per_remaining_event_cents'] ?? 0))) . '</p>';
		echo '<p><strong>Projected end:</strong> ' . esc_html(vms_goals_fmt_money((int) ($progress['projected_end_cents'] ?? 0))) . '</p>';
		echo '<p><strong>Projection gap:</strong> ' . esc_html(vms_goals_fmt_money((int) ($progress['projection_gap_cents'] ?? 0))) . '</p>';
		echo '<p><a class="button button-secondary" href="' . esc_url(vms_goals_admin_url(array('tab' => 'goals'))) . '">Manage Goals</a></p>';
		echo '</div>';
		echo '</section>';
	}
}
