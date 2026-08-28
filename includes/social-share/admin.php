<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_social_admin_url')) {
	/**
	 * @param array<string,mixed> $args
	 */
	function bvmgr_social_admin_url(array $args = array()): string
	{
		$base = admin_url('admin.php?page=vms-social-sharing');
		if (empty($args)) {
			return $base;
		}
		return add_query_arg($args, $base);
	}
}

if (!function_exists('bvmgr_social_admin_query_arg')) {
	function bvmgr_social_admin_query_arg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Social Share admin routing, tabs, and filters only change admin display state.
		if (!isset($_GET[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only Social Share admin routing, tabs, and filters are unslashed here and sanitized by the caller.
		return (string) wp_unslash($_GET[$key]);
	}
}

if (!function_exists('bvmgr_social_admin_tabs')) {
	/**
	 * @return array<string,string>
	 */
	function bvmgr_social_admin_tabs(): array
	{
		return array(
			'overview' => __('Overview', 'backstage-venue-manager'),
			'settings' => __('Settings', 'backstage-venue-manager'),
			'accounts' => __('Accounts', 'backstage-venue-manager'),
			'venue_map' => __('Venue Mapping', 'backstage-venue-manager'),
			'templates' => __('Templates', 'backstage-venue-manager'),
			'queue' => __('Queue', 'backstage-venue-manager'),
			'logs' => __('Logs', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('bvmgr_social_admin_current_tab')) {
	function bvmgr_social_admin_current_tab(): string
	{
		$tabs = bvmgr_social_admin_tabs();
		$tab = sanitize_key(bvmgr_social_admin_query_arg('tab'));
		return isset($tabs[$tab]) ? $tab : 'overview';
	}
}

if (!function_exists('bvmgr_social_supported_platforms')) {
	/**
	 * @return array<int,string>
	 */
	function bvmgr_social_supported_platforms(): array
	{
		return array('facebook', 'instagram', 'linkedin', 'x', 'mock', 'webhook', 'meta');
	}
}

if (!function_exists('bvmgr_social_venue_choices')) {
	/**
	 * @return array<int,WP_Post>
	 */
	function bvmgr_social_venue_choices(): array
	{
		$posts = get_posts(array(
			'post_type' => 'vms_venue',
			'post_status' => array('publish', 'draft', 'pending'),
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		));
		return is_array($posts) ? $posts : array();
	}
}

if (!function_exists('bvmgr_social_enqueue_admin_assets')) {
	function bvmgr_social_enqueue_admin_assets(string $hook_suffix = ''): void
	{
		$page = sanitize_key(bvmgr_social_admin_query_arg('page'));
		$post_type = sanitize_key(bvmgr_social_admin_query_arg('post_type'));
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (is_object($screen) && isset($screen->post_type) && $post_type === '') {
			$post_type = sanitize_key((string) $screen->post_type);
		}

		$should_load = ($page === 'vms-social-sharing') || ($post_type === 'vms_event_plan');
		if (!$should_load) {
			return;
		}

		$ver = defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : null;
		wp_enqueue_script(
			'bvmgr-social-admin',
			BVMGR_PLUGIN_URL . 'assets/js/vms-social-admin.js',
			array(),
			$ver,
			true
		);
	}
}
add_action('admin_enqueue_scripts', 'bvmgr_social_enqueue_admin_assets', 30);

if (!function_exists('bvmgr_social_register_admin_menu')) {
	function bvmgr_social_register_admin_menu(): void
	{
		$primary_parent = 'vms-dashboard';
		$primary_cap = bvmgr_social_manage_capability();
		$fallback_parent = 'edit.php?post_type=vms_event_plan';
		$fallback_cap = bvmgr_social_operator_capability();

		add_submenu_page(
			$primary_parent,
			__('Social Sharing', 'backstage-venue-manager'),
			__('Social Sharing', 'backstage-venue-manager'),
			$primary_cap,
			'vms-social-sharing',
			'bvmgr_social_render_admin_page'
		);

		// Operator parity path for accounts without manage_options/VMS top-level menu visibility.
		if (!current_user_can('manage_options')) {
			add_submenu_page(
				$fallback_parent,
				__('Social Sharing', 'backstage-venue-manager'),
				__('Social Sharing', 'backstage-venue-manager'),
				$fallback_cap,
				'vms-social-sharing',
				'bvmgr_social_render_admin_page'
			);
		}
	}
}
add_action('admin_menu', 'bvmgr_social_register_admin_menu', 45);

if (!function_exists('bvmgr_social_redirect_with_notice')) {
	function bvmgr_social_redirect_with_notice(string $tab, string $notice, string $type = 'success'): void
	{
		$url = bvmgr_social_admin_url(array(
			'tab' => $tab,
			'vms_social_notice' => rawurlencode($notice),
			'vms_social_notice_type' => sanitize_key($type),
		));
		wp_safe_redirect($url);
		exit;
	}
}

if (!function_exists('bvmgr_social_render_notices')) {
	function bvmgr_social_render_notices(): void
	{
		$notice = sanitize_text_field(bvmgr_social_admin_query_arg('vms_social_notice'));
		if ($notice === '') {
			return;
		}
		$type = sanitize_key(bvmgr_social_admin_query_arg('vms_social_notice_type'));
		$class = in_array($type, array('error', 'warning', 'success', 'info'), true) ? $type : 'success';
		echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($notice) . '</p></div>';
	}
}

if (!function_exists('bvmgr_social_render_admin_page')) {
	function bvmgr_social_render_admin_page(): void
	{
		bvmgr_social_require_manage_capability();

		if (function_exists('bvmgr_admin_ui_render_shell')) {
			bvmgr_admin_ui_render_shell(
				array(
					'title' => __('Social Sharing', 'backstage-venue-manager'),
					'notices_callback' => 'bvmgr_social_render_notices',
				),
				'bvmgr_social_render_admin_page_content'
			);
			return;
		}

		echo '<div class="wrap vms-social-admin">';
		echo '<h1>' . esc_html__('Social Sharing', 'backstage-venue-manager') . '</h1>';
		bvmgr_social_render_notices();
		bvmgr_social_render_admin_page_content();
		echo '</div>';
	}
}

if (!function_exists('bvmgr_social_render_admin_page_content')) {
	function bvmgr_social_render_admin_page_content(): void
	{
		bvmgr_social_require_manage_capability();
		$tab = bvmgr_social_admin_current_tab();
		$tabs = bvmgr_social_admin_tabs();
		echo '<div class="vms-social-admin">';

		echo '<nav class="nav-tab-wrapper">';
		foreach ($tabs as $key => $label) {
			$class = ($tab === $key) ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a class="' . esc_attr($class) . '" href="' . esc_url(bvmgr_social_admin_url(array('tab' => $key))) . '">' . esc_html($label) . '</a>';
		}
		echo '</nav>';

		echo '<div class="vms-social-panel">';
		switch ($tab) {
			case 'settings':
				bvmgr_social_render_settings_tab();
				break;
			case 'accounts':
				bvmgr_social_render_accounts_tab();
				break;
			case 'venue_map':
				bvmgr_social_render_venue_map_tab();
				break;
			case 'templates':
				bvmgr_social_render_templates_tab();
				break;
			case 'queue':
				bvmgr_social_render_queue_tab();
				break;
			case 'logs':
				bvmgr_social_render_logs_tab();
				break;
			case 'overview':
			default:
				bvmgr_social_render_overview_tab();
				break;
		}
		echo '</div>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_social_render_overview_tab')) {
	function bvmgr_social_render_overview_tab(): void
	{
		$settings = bvmgr_social_get_settings();
		$providers = bvmgr_social_get_providers();
		$next = wp_next_scheduled(defined('BVMGR_SOCIAL_CRON_HOOK') ? (string) BVMGR_SOCIAL_CRON_HOOK : 'vms_social_process_queue');

		echo '<h2>' . esc_html__('Module Status', 'backstage-venue-manager') . '</h2>';
		echo '<p><strong>' . esc_html__('Enabled:', 'backstage-venue-manager') . '</strong> ' . ($settings['enabled'] ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</p>';
		echo '<p><strong>' . esc_html__('Kill switch:', 'backstage-venue-manager') . '</strong> ' . ($settings['kill_switch'] ? esc_html__('Active', 'backstage-venue-manager') : esc_html__('Off', 'backstage-venue-manager')) . '</p>';
		echo '<p><strong>' . esc_html__('Next cron run:', 'backstage-venue-manager') . '</strong> ';
		echo $next ? esc_html(wp_date('M j, Y g:ia', (int) $next, wp_timezone())) : esc_html__('Not scheduled', 'backstage-venue-manager');
		echo '</p>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('vms_social_run_queue_now');
		echo '<input type="hidden" name="action" value="vms_social_run_queue_now" />';
		echo '<button type="submit" class="button button-secondary">' . esc_html__('Run Queue Now', 'backstage-venue-manager') . '</button>';
		echo '</form>';

		echo '<h2>' . esc_html__('Provider Health', 'backstage-venue-manager') . '</h2>';
		echo '<div class="vms-social-provider-grid">';
		foreach ($providers as $key => $provider) {
			$caps = $provider->get_capabilities();
			echo '<div class="vms-social-provider-card">';
			echo '<h3>' . esc_html($provider->get_display_name()) . '</h3>';
			echo '<p><code>' . esc_html($key) . '</code></p>';
			echo '<p>' . esc_html__('Capabilities:', 'backstage-venue-manager') . ' ';
			$labels = array();
			foreach ($caps as $cap => $enabled) {
				if ($enabled) {
					$labels[] = $cap;
				}
			}
			echo esc_html(implode(', ', $labels));
			echo '</p>';
			echo '</div>';
		}
		echo '</div>';
	}
}

if (!function_exists('bvmgr_social_render_settings_tab')) {
	function bvmgr_social_render_settings_tab(): void
	{
		$settings = bvmgr_social_get_settings();
		echo '<h2>' . esc_html__('Global Settings', 'backstage-venue-manager') . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('vms_social_save_settings');
		echo '<input type="hidden" name="action" value="vms_social_save_settings" />';
		echo '<input type="hidden" name="tab" value="settings" />';
		echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked(1, (int) $settings['enabled'], false) . ' /> ' . esc_html__('Enable social sharing module', 'backstage-venue-manager') . '</label></p>';
		echo '<p><label><input type="checkbox" name="kill_switch" value="1" ' . checked(1, (int) $settings['kill_switch'], false) . ' /> ' . esc_html__('Disable all auto-posting (Kill Switch)', 'backstage-venue-manager') . '</label></p>';
		echo '<p><label><input type="checkbox" name="utm_enabled" value="1" ' . checked(1, (int) $settings['utm_enabled'], false) . ' /> ' . esc_html__('Append UTM parameters to shared links', 'backstage-venue-manager') . '</label></p>';
		echo '<p><label>' . esc_html__('Max Retry Attempts', 'backstage-venue-manager') . ' <input type="number" min="1" max="10" name="max_attempts" value="' . esc_attr((string) $settings['max_attempts']) . '" /></label></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Settings', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';
	}
}

if (!function_exists('bvmgr_social_render_accounts_tab')) {
	function bvmgr_social_render_accounts_tab(): void
	{
		$accounts = bvmgr_social_account_rows();
		echo '<h2>' . esc_html__('Accounts', 'backstage-venue-manager') . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__('ID', 'backstage-venue-manager') . '</th><th>' . esc_html__('Platform', 'backstage-venue-manager') . '</th><th>' . esc_html__('Label', 'backstage-venue-manager') . '</th><th>' . esc_html__('Auth State', 'backstage-venue-manager') . '</th><th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
		echo '</tr></thead><tbody>';
		if (empty($accounts)) {
			echo '<tr><td colspan="5">' . esc_html__('No accounts yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($accounts as $row) {
				echo '<tr>';
				echo '<td>' . (int) $row['id'] . '</td>';
				echo '<td><code>' . esc_html((string) $row['platform']) . '</code></td>';
				echo '<td>' . esc_html((string) $row['label']) . '</td>';
				echo '<td>' . esc_html((string) $row['auth_state']) . '</td>';
				echo '<td>';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return window.confirm(\'' . esc_js(__('Delete this account?', 'backstage-venue-manager')) . '\');">';
				wp_nonce_field('vms_social_delete_account');
				echo '<input type="hidden" name="action" value="vms_social_delete_account" />';
				echo '<input type="hidden" name="tab" value="accounts" />';
				echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '" />';
				echo '<button class="button button-small" type="submit">' . esc_html__('Delete', 'backstage-venue-manager') . '</button>';
				echo '</form>';
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h3>' . esc_html__('Add Account', 'backstage-venue-manager') . '</h3>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('vms_social_save_account');
		echo '<input type="hidden" name="action" value="vms_social_save_account" />';
		echo '<input type="hidden" name="tab" value="accounts" />';
		echo '<p><label>' . esc_html__('Platform', 'backstage-venue-manager') . ' <select name="platform">';
		foreach (bvmgr_social_supported_platforms() as $platform) {
			echo '<option value="' . esc_attr($platform) . '">' . esc_html($platform) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Label', 'backstage-venue-manager') . ' <input type="text" name="label" class="regular-text" required /></label></p>';
		echo '<p><label>' . esc_html__('Webhook URL (for webhook platform)', 'backstage-venue-manager') . ' <input type="url" name="webhook_url" class="regular-text" /></label></p>';
		echo '<p><label>' . esc_html__('Signing Secret (for webhook platform)', 'backstage-venue-manager') . ' <input type="text" name="signing_secret" class="regular-text" /></label></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Account', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';
	}
}

if (!function_exists('bvmgr_social_render_venue_map_tab')) {
	function bvmgr_social_render_venue_map_tab(): void
	{
		$rows = bvmgr_social_venue_map_rows();
		$venues = bvmgr_social_venue_choices();
		$accounts = bvmgr_social_account_rows();
		$templates = bvmgr_social_templates_all();

		echo '<h2>' . esc_html__('Venue Mapping', 'backstage-venue-manager') . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>ID</th><th>' . esc_html__('Venue', 'backstage-venue-manager') . '</th><th>' . esc_html__('Platform', 'backstage-venue-manager') . '</th><th>' . esc_html__('Account', 'backstage-venue-manager') . '</th><th>' . esc_html__('Destination', 'backstage-venue-manager') . '</th><th>' . esc_html__('Template', 'backstage-venue-manager') . '</th><th>' . esc_html__('Enabled', 'backstage-venue-manager') . '</th><th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
		echo '</tr></thead><tbody>';
		if (empty($rows)) {
			echo '<tr><td colspan="8">' . esc_html__('No venue mappings yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($rows as $row) {
				$venue_name = get_the_title((int) $row['venue_id']);
				echo '<tr>';
				echo '<td>' . (int) $row['id'] . '</td>';
				echo '<td>' . esc_html($venue_name !== '' ? $venue_name : ('#' . (int) $row['venue_id'])) . '</td>';
				echo '<td><code>' . esc_html((string) $row['platform']) . '</code></td>';
				echo '<td>#' . (int) $row['account_id'] . '</td>';
				echo '<td>' . esc_html((string) $row['destination_id']) . '</td>';
				echo '<td>' . (int) $row['default_template_id'] . '</td>';
				echo '<td>' . (!empty($row['is_enabled']) ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</td>';
				echo '<td>';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return window.confirm(\'' . esc_js(__('Delete this mapping?', 'backstage-venue-manager')) . '\');">';
				wp_nonce_field('vms_social_delete_venue_map');
				echo '<input type="hidden" name="action" value="vms_social_delete_venue_map" />';
				echo '<input type="hidden" name="tab" value="venue_map" />';
				echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '" />';
				echo '<button class="button button-small" type="submit">' . esc_html__('Delete', 'backstage-venue-manager') . '</button>';
				echo '</form>';
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h3>' . esc_html__('Add Venue Mapping', 'backstage-venue-manager') . '</h3>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('vms_social_save_venue_map');
		echo '<input type="hidden" name="action" value="vms_social_save_venue_map" />';
		echo '<input type="hidden" name="tab" value="venue_map" />';
		echo '<p><label>' . esc_html__('Venue', 'backstage-venue-manager') . ' <select name="venue_id" required>';
		echo '<option value="">' . esc_html__('Select a venue', 'backstage-venue-manager') . '</option>';
		foreach ($venues as $venue) {
			echo '<option value="' . (int) $venue->ID . '">' . esc_html($venue->post_title) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Platform', 'backstage-venue-manager') . ' <select name="platform">';
		foreach (bvmgr_social_supported_platforms() as $platform) {
			echo '<option value="' . esc_attr($platform) . '">' . esc_html($platform) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Account', 'backstage-venue-manager') . ' <select name="account_id">';
		echo '<option value="0">' . esc_html__('None', 'backstage-venue-manager') . '</option>';
		foreach ($accounts as $account) {
			echo '<option value="' . (int) $account['id'] . '">#' . (int) $account['id'] . ' - ' . esc_html((string) $account['label']) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Destination ID', 'backstage-venue-manager') . ' <input type="text" name="destination_id" class="regular-text" required /></label></p>';
		echo '<p><label>' . esc_html__('Default Template', 'backstage-venue-manager') . ' <select name="default_template_id">';
		echo '<option value="0">' . esc_html__('None', 'backstage-venue-manager') . '</option>';
		foreach ($templates as $tpl) {
			echo '<option value="' . (int) $tpl['id'] . '">#' . (int) $tpl['id'] . ' - ' . esc_html((string) $tpl['name']) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label><input type="checkbox" name="is_enabled" value="1" checked /> ' . esc_html__('Enabled', 'backstage-venue-manager') . '</label></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Mapping', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';
	}
}

if (!function_exists('bvmgr_social_render_templates_tab')) {
	function bvmgr_social_render_templates_tab(): void
	{
		$templates = bvmgr_social_templates_all();
		echo '<h2>' . esc_html__('Templates', 'backstage-venue-manager') . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>ID</th><th>' . esc_html__('Platform', 'backstage-venue-manager') . '</th><th>' . esc_html__('Name', 'backstage-venue-manager') . '</th><th>' . esc_html__('Default', 'backstage-venue-manager') . '</th><th>' . esc_html__('Preview', 'backstage-venue-manager') . '</th><th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
		echo '</tr></thead><tbody>';
		if (empty($templates)) {
			echo '<tr><td colspan="6">' . esc_html__('No templates yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($templates as $tpl) {
				echo '<tr>';
				echo '<td>' . (int) $tpl['id'] . '</td>';
				echo '<td><code>' . esc_html((string) $tpl['platform']) . '</code></td>';
				echo '<td>' . esc_html((string) $tpl['name']) . '</td>';
				echo '<td>' . (!empty($tpl['is_default']) ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</td>';
				echo '<td><code>' . esc_html(bvmgr_social_trim_preview((string) $tpl['body'], 120)) . '</code></td>';
				echo '<td>';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return window.confirm(\'' . esc_js(__('Delete this template?', 'backstage-venue-manager')) . '\');">';
				wp_nonce_field('vms_social_delete_template');
				echo '<input type="hidden" name="action" value="vms_social_delete_template" />';
				echo '<input type="hidden" name="tab" value="templates" />';
				echo '<input type="hidden" name="id" value="' . (int) $tpl['id'] . '" />';
				echo '<button class="button button-small" type="submit">' . esc_html__('Delete', 'backstage-venue-manager') . '</button>';
				echo '</form>';
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h3>' . esc_html__('Add Template', 'backstage-venue-manager') . '</h3>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('vms_social_save_template');
		echo '<input type="hidden" name="action" value="vms_social_save_template" />';
		echo '<input type="hidden" name="tab" value="templates" />';
		echo '<p><label>' . esc_html__('Platform', 'backstage-venue-manager') . ' <select name="platform">';
		foreach (bvmgr_social_supported_platforms() as $platform) {
			echo '<option value="' . esc_attr($platform) . '">' . esc_html($platform) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Name', 'backstage-venue-manager') . ' <input type="text" name="name" class="regular-text" required /></label></p>';
		echo '<p><label>' . esc_html__('Body', 'backstage-venue-manager') . '<br /><textarea name="body" rows="6" class="large-text" required>{event_title}\n{event_date}\n{ticket_url}</textarea></label></p>';
		echo '<p><label><input type="checkbox" name="is_default" value="1" /> ' . esc_html__('Set as default for this platform', 'backstage-venue-manager') . '</label></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Template', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';
	}
}

if (!function_exists('bvmgr_social_render_queue_tab')) {
	function bvmgr_social_render_queue_tab(): void
	{
		$status = sanitize_key(bvmgr_social_admin_query_arg('status'));
		$platform = sanitize_key(bvmgr_social_admin_query_arg('platform'));
		$rows = bvmgr_social_queue_list(array('status' => $status, 'platform' => $platform), 200);

		echo '<h2>' . esc_html__('Queue', 'backstage-venue-manager') . '</h2>';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="vms-social-queue-filters">';
		echo '<input type="hidden" name="page" value="vms-social-sharing" />';
		echo '<input type="hidden" name="tab" value="queue" />';
		echo '<label>' . esc_html__('Status', 'backstage-venue-manager') . ' <select name="status"><option value="">' . esc_html__('All', 'backstage-venue-manager') . '</option>';
		foreach (bvmgr_social_queue_statuses() as $s) {
			echo '<option value="' . esc_attr($s) . '" ' . selected($status, $s, false) . '>' . esc_html($s) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__('Platform', 'backstage-venue-manager') . ' <input type="text" name="platform" value="' . esc_attr($platform) . '" /></label> ';
		echo '<button type="submit" class="button">' . esc_html__('Filter', 'backstage-venue-manager') . '</button>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>ID</th><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Platform', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th><th>' . esc_html__('Scheduled (UTC)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Attempts', 'backstage-venue-manager') . '</th><th>' . esc_html__('Last Error', 'backstage-venue-manager') . '</th><th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
		echo '</tr></thead><tbody>';
		if (empty($rows)) {
			echo '<tr><td colspan="8">' . esc_html__('No queue items found.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($rows as $row) {
				echo '<tr>';
				echo '<td>' . (int) $row['id'] . '</td>';
				echo '<td>' . (int) $row['event_plan_id'] . '</td>';
				echo '<td><code>' . esc_html((string) $row['platform']) . '</code></td>';
				echo '<td>' . esc_html((string) $row['status']) . '</td>';
				echo '<td>' . esc_html((string) $row['scheduled_at_utc']) . '</td>';
				echo '<td>' . (int) $row['attempts'] . '</td>';
				echo '<td>' . esc_html((string) $row['last_error_message']) . '</td>';
				echo '<td class="vms-social-queue-actions">';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
				wp_nonce_field('vms_social_queue_retry');
				echo '<input type="hidden" name="action" value="vms_social_queue_retry" />';
				echo '<input type="hidden" name="tab" value="queue" />';
				echo '<input type="hidden" name="queue_id" value="' . (int) $row['id'] . '" />';
				echo '<button class="button button-small" type="submit">' . esc_html__('Retry', 'backstage-venue-manager') . '</button>';
				echo '</form>';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
				wp_nonce_field('vms_social_queue_cancel');
				echo '<input type="hidden" name="action" value="vms_social_queue_cancel" />';
				echo '<input type="hidden" name="tab" value="queue" />';
				echo '<input type="hidden" name="queue_id" value="' . (int) $row['id'] . '" />';
				echo '<button class="button button-small" type="submit">' . esc_html__('Cancel', 'backstage-venue-manager') . '</button>';
				echo '</form>';
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}
}

if (!function_exists('bvmgr_social_render_logs_tab')) {
	function bvmgr_social_render_logs_tab(): void
	{
		$search = sanitize_text_field(bvmgr_social_admin_query_arg('log_search'));
		$rows = bvmgr_social_audit_recent(200, $search);
		echo '<h2>' . esc_html__('Audit Logs', 'backstage-venue-manager') . '</h2>';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
		echo '<input type="hidden" name="page" value="vms-social-sharing" />';
		echo '<input type="hidden" name="tab" value="logs" />';
		echo '<label>' . esc_html__('Search', 'backstage-venue-manager') . ' <input type="text" name="log_search" value="' . esc_attr($search) . '" /></label> ';
		echo '<button type="submit" class="button">' . esc_html__('Filter', 'backstage-venue-manager') . '</button>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>ID</th><th>' . esc_html__('When (UTC)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Action', 'backstage-venue-manager') . '</th><th>' . esc_html__('Queue', 'backstage-venue-manager') . '</th><th>' . esc_html__('Platform', 'backstage-venue-manager') . '</th><th>' . esc_html__('Details', 'backstage-venue-manager') . '</th>';
		echo '</tr></thead><tbody>';
		if (empty($rows)) {
			echo '<tr><td colspan="6">' . esc_html__('No logs found.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($rows as $row) {
				echo '<tr>';
				echo '<td>' . (int) $row['id'] . '</td>';
				echo '<td>' . esc_html((string) $row['created_at']) . '</td>';
				echo '<td><code>' . esc_html((string) $row['action']) . '</code></td>';
				echo '<td>' . (int) $row['queue_id'] . '</td>';
				echo '<td>' . esc_html((string) $row['platform']) . '</td>';
				echo '<td><code>' . esc_html(bvmgr_social_trim_preview((string) $row['details_json'], 180)) . '</code></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}
}

if (!function_exists('bvmgr_social_handle_save_settings')) {
	function bvmgr_social_handle_save_settings(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_save_settings');
		$settings = bvmgr_social_update_settings(array(
			'enabled' => isset($_POST['enabled']) ? 1 : 0,
			'kill_switch' => isset($_POST['kill_switch']) ? 1 : 0,
			'utm_enabled' => isset($_POST['utm_enabled']) ? 1 : 0,
			'max_attempts' => absint(wp_unslash((string) ($_POST['max_attempts'] ?? 5))),
		));
		bvmgr_social_audit_log('settings_change', $settings, 0, '', get_current_user_id());
		bvmgr_social_redirect_with_notice('settings', __('Settings saved.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_social_save_settings', 'bvmgr_social_handle_save_settings');

if (!function_exists('bvmgr_social_handle_save_account')) {
	function bvmgr_social_handle_save_account(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_save_account');

		$platform = sanitize_key(wp_unslash((string) ($_POST['platform'] ?? '')));
		$label = sanitize_text_field(wp_unslash((string) ($_POST['label'] ?? '')));
		$token_json = array();
		if ($platform === 'webhook') {
			$token_json['webhook_url'] = esc_url_raw(wp_unslash((string) ($_POST['webhook_url'] ?? '')));
			$token_json['signing_secret'] = sanitize_text_field(wp_unslash((string) ($_POST['signing_secret'] ?? '')));
		}

		$id = bvmgr_social_account_save(array(
			'platform' => $platform,
			'label' => $label,
			'auth_state' => 'connected',
			'token_json' => $token_json,
			'meta_json' => array(),
		));

		bvmgr_social_audit_log('connect', array('account_id' => $id, 'platform' => $platform), 0, $platform, get_current_user_id());
		bvmgr_social_redirect_with_notice('accounts', __('Account saved.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_social_save_account', 'bvmgr_social_handle_save_account');

if (!function_exists('bvmgr_social_handle_delete_account')) {
	function bvmgr_social_handle_delete_account(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_delete_account');
		$id = absint(wp_unslash((string) ($_POST['id'] ?? 0)));
		if ($id > 0) {
			bvmgr_social_account_delete($id);
			bvmgr_social_audit_log('disconnect', array('account_id' => $id), 0, '', get_current_user_id());
		}
		bvmgr_social_redirect_with_notice('accounts', __('Account deleted.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_social_delete_account', 'bvmgr_social_handle_delete_account');

if (!function_exists('bvmgr_social_handle_save_venue_map')) {
	function bvmgr_social_handle_save_venue_map(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_save_venue_map');
		$id = bvmgr_social_venue_map_save(array(
			'venue_id' => absint(wp_unslash((string) ($_POST['venue_id'] ?? 0))),
			'platform' => sanitize_key(wp_unslash((string) ($_POST['platform'] ?? ''))),
			'account_id' => absint(wp_unslash((string) ($_POST['account_id'] ?? 0))),
			'destination_id' => sanitize_text_field(wp_unslash((string) ($_POST['destination_id'] ?? ''))),
			'default_template_id' => absint(wp_unslash((string) ($_POST['default_template_id'] ?? 0))),
			'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
		));
		bvmgr_social_audit_log('settings_change', array('venue_map_id' => $id), 0, '', get_current_user_id());
		bvmgr_social_redirect_with_notice('venue_map', __('Venue mapping saved.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_social_save_venue_map', 'bvmgr_social_handle_save_venue_map');

if (!function_exists('bvmgr_social_handle_delete_venue_map')) {
	function bvmgr_social_handle_delete_venue_map(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_delete_venue_map');
		$id = absint(wp_unslash((string) ($_POST['id'] ?? 0)));
		if ($id > 0) {
			bvmgr_social_venue_map_delete($id);
			bvmgr_social_audit_log('settings_change', array('venue_map_deleted' => $id), 0, '', get_current_user_id());
		}
		bvmgr_social_redirect_with_notice('venue_map', __('Venue mapping deleted.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_social_delete_venue_map', 'bvmgr_social_handle_delete_venue_map');

if (!function_exists('bvmgr_social_template_body_from_post')) {
	function bvmgr_social_template_body_from_post(array $source): string
	{
		if (!array_key_exists('body', $source) || !is_scalar($source['body'])) {
			return '';
		}

		$body = wp_unslash($source['body']);
		return is_scalar($body) ? (string) $body : '';
	}
}

if (!function_exists('bvmgr_social_handle_save_template')) {
	function bvmgr_social_handle_save_template(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_save_template');

		$id = bvmgr_social_template_save(array(
			'platform' => bvmgr_request_read_key($_POST, 'platform'),
			'name' => bvmgr_request_read_text_field($_POST, 'name'),
			'body' => bvmgr_social_template_body_from_post($_POST),
			'is_default' => isset($_POST['is_default']) ? 1 : 0,
			'settings_json' => array(),
		));
		bvmgr_social_audit_log('settings_change', array('template_id' => $id), 0, '', get_current_user_id());
		bvmgr_social_redirect_with_notice('templates', __('Template saved.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_social_save_template', 'bvmgr_social_handle_save_template');

if (!function_exists('bvmgr_social_handle_delete_template')) {
	function bvmgr_social_handle_delete_template(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_delete_template');
		$id = absint(wp_unslash((string) ($_POST['id'] ?? 0)));
		if ($id > 0) {
			bvmgr_social_template_delete($id);
			bvmgr_social_audit_log('settings_change', array('template_deleted' => $id), 0, '', get_current_user_id());
		}
		bvmgr_social_redirect_with_notice('templates', __('Template deleted.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_social_delete_template', 'bvmgr_social_handle_delete_template');

if (!function_exists('bvmgr_social_handle_queue_retry')) {
	function bvmgr_social_handle_queue_retry(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_queue_retry');
		$queue_id = absint(wp_unslash((string) ($_POST['queue_id'] ?? 0)));
		$event_plan_id = absint(wp_unslash((string) ($_POST['event_plan_id'] ?? 0)));
		if ($queue_id > 0) {
			bvmgr_social_queue_retry($queue_id);
			bvmgr_social_audit_log('retry', array('queue_id' => $queue_id), $queue_id, '', get_current_user_id());
		}
		if ($event_plan_id > 0 && function_exists('bvmgr_social_redirect_event_edit')) {
			bvmgr_social_redirect_event_edit($event_plan_id, __('Queue item set to retry.', 'backstage-venue-manager'), 'success');
		}
		bvmgr_social_redirect_with_notice('queue', __('Queue item set to retry.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_social_queue_retry', 'bvmgr_social_handle_queue_retry');

if (!function_exists('bvmgr_social_handle_queue_cancel')) {
	function bvmgr_social_handle_queue_cancel(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_queue_cancel');
		$queue_id = absint(wp_unslash((string) ($_POST['queue_id'] ?? 0)));
		$event_plan_id = absint(wp_unslash((string) ($_POST['event_plan_id'] ?? 0)));
		if ($queue_id > 0) {
			bvmgr_social_queue_cancel($queue_id);
			bvmgr_social_audit_log('cancel', array('queue_id' => $queue_id), $queue_id, '', get_current_user_id());
		}
		if ($event_plan_id > 0 && function_exists('bvmgr_social_redirect_event_edit')) {
			bvmgr_social_redirect_event_edit($event_plan_id, __('Queue item canceled.', 'backstage-venue-manager'), 'success');
		}
		bvmgr_social_redirect_with_notice('queue', __('Queue item canceled.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_social_queue_cancel', 'bvmgr_social_handle_queue_cancel');

if (!function_exists('bvmgr_social_handle_run_queue_now')) {
	function bvmgr_social_handle_run_queue_now(): void
	{
		bvmgr_social_require_manage_capability();
		check_admin_referer('vms_social_run_queue_now');
		$summary = bvmgr_social_process_queue(50);
		$message = sprintf(
			/* translators: 1: processed count */
			__('Queue run complete. Processed %d item(s).', 'backstage-venue-manager'),
			(int) ($summary['processed'] ?? 0)
		);
		bvmgr_social_redirect_with_notice('overview', $message);
	}
}
add_action('admin_post_vms_social_run_queue_now', 'bvmgr_social_handle_run_queue_now');
