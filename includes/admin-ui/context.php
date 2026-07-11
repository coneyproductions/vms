<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_admin_ui_query_arg')) {
	function vms_admin_ui_query_arg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin shell routing helper.
		return vms_request_read_scalar($_GET, $key);
	}
}

if (!function_exists('vms_admin_ui_get_page_slug')) {
	function vms_admin_ui_get_page_slug(): string
	{
		$page = sanitize_key(vms_admin_ui_query_arg('page'));
		return (string) $page;
	}
}

if (!function_exists('vms_admin_ui_get_post_type')) {
	/**
	 * @param WP_Screen|null $screen
	 */
	function vms_admin_ui_get_post_type($screen = null): string
	{
		$post_type = sanitize_key(vms_admin_ui_query_arg('post_type'));
		if ($post_type !== '') {
			return $post_type;
		}

		if (!is_object($screen) && function_exists('get_current_screen')) {
			$screen = get_current_screen();
		}
		if (is_object($screen) && isset($screen->post_type)) {
			$post_type = sanitize_key((string) $screen->post_type);
		}

		if ($post_type !== '') {
			return $post_type;
		}

		$post_id = absint(vms_admin_ui_query_arg('post'));
		if ($post_id > 0) {
			$post_type = get_post_type($post_id);
			if (is_string($post_type)) {
				return sanitize_key($post_type);
			}
		}

		return '';
	}
}

if (!function_exists('vms_admin_ui_is_vms_screen')) {
	/**
	 * @param WP_Screen|null $screen
	 */
	function vms_admin_ui_is_vms_screen($screen = null): bool
	{
		$page = vms_admin_ui_get_page_slug();
		if ($page === 'vms' || strpos($page, 'vms-') === 0) {
			return true;
		}

		$post_type = vms_admin_ui_get_post_type($screen);
		if ($post_type !== '' && strpos($post_type, 'vms_') === 0) {
			return true;
		}

		if (!is_object($screen) && function_exists('get_current_screen')) {
			$screen = get_current_screen();
		}
		if (is_object($screen) && isset($screen->id)) {
			$screen_id = (string) $screen->id;
			if (strpos($screen_id, 'vms_') !== false) {
				return true;
			}
		}

		return false;
	}
}

if (!function_exists('vms_admin_ui_is_shell_page')) {
	function vms_admin_ui_is_shell_page(): bool
	{
		$page = vms_admin_ui_get_page_slug();

		// Premium Ops pages render their own wrappers; treat them as non-shell so
		// the global VMS top nav can render consistently.
		if ($page === 'vms-ops-console' && function_exists('vms_ops_admin_render_settings_page')) {
			return false;
		}
		if ($page === 'vms-teams' && function_exists('vms_ops_admin_render_teams_page')) {
			return false;
		}
		if ($page === 'vms-alert-presets' && function_exists('vms_ops_admin_render_presets_page')) {
			return false;
		}

		$shell_pages = array(
			'vms-dashboard',
			'vms-vendor-command-center',
			'vms-vendor-availability',
			'vms-settings',
			'vms-guided-tours',
			'vms-status-notices',
			'vms-passes',
			'vms-schedule',
			'vms-due-dates',
			'vms-continuity-binder',
			'vms-social-sharing',
			'vms-tour-maintenance',
			'vms-integrity-venue-links',
			'vms-integrity-calendar-links',
			'vms-marketing-social',
			'vms-data-tools',
			'vms-import-event-plans',
			'vms-teams',
			'vms-alert-presets',
			'vms-ops-console-hub',
			'vms-ops-console',
			'vms-ticket-integrity',
			'vms-admin-pages',
		);

		/**
		 * Allow add-ons to opt into the shared VMS admin shell so they inherit the
		 * compact top navigation and consistent admin chrome.
		 *
		 * @param string[] $shell_pages
		 */
		$shell_pages = apply_filters('vms_admin_ui_shell_pages', $shell_pages);
		if (!is_array($shell_pages)) {
			$shell_pages = array();
		}

		return in_array($page, $shell_pages, true);
	}
}

if (!function_exists('vms_admin_ui_get_planning_memory')) {
	function vms_admin_ui_get_planning_memory(int $user_id = 0): string
	{
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ($user_id <= 0) {
			return 'schedule';
		}

		$value = sanitize_key((string) get_user_meta($user_id, 'vms_last_planning_view', true));
		if (!in_array($value, array('schedule', 'event_plans'), true)) {
			return 'schedule';
		}

		return $value;
	}
}

if (!function_exists('vms_admin_ui_get_planning_landing_url')) {
	function vms_admin_ui_get_planning_landing_url(int $user_id = 0): string
	{
		$value = vms_admin_ui_get_planning_memory($user_id);
		if ($value === 'event_plans') {
			return vms_admin_ui_post_type_url('vms_event_plan');
		}

		return vms_admin_ui_page_url('vms-schedule');
	}
}

if (!function_exists('vms_admin_ui_track_planning_context')) {
	/**
	 * @param WP_Screen $screen
	 */
	function vms_admin_ui_track_planning_context($screen): void
	{
		if (!is_admin() || !is_user_logged_in()) {
			return;
		}

		$user_id = (int) get_current_user_id();
		if ($user_id <= 0) {
			return;
		}

		$page = vms_admin_ui_get_page_slug();
		if ($page === 'vms-schedule') {
			update_user_meta($user_id, 'vms_last_planning_view', 'schedule');
			return;
		}

		$post_type = vms_admin_ui_get_post_type($screen);
		if ($post_type === 'vms_event_plan') {
			update_user_meta($user_id, 'vms_last_planning_view', 'event_plans');
		}
	}
}
add_action('current_screen', 'vms_admin_ui_track_planning_context', 20);

if (!function_exists('vms_admin_ui_active_cluster')) {
	function vms_admin_ui_active_cluster(): string
	{
		$page = vms_admin_ui_get_page_slug();
		$post_type = vms_admin_ui_get_post_type();

		if ($post_type === 'vms_event_plan') {
			return 'planning';
		}

		if (in_array($post_type, array('vms_vendor', 'vms_staff', 'vms_comp_package', 'vms_rating', 'vms_vendor_app', 'vms_vendor_application'), true)) {
			return 'vendors_staff';
		}

		if ($post_type === 'vms_venue') {
			return 'venues';
		}

		if (in_array($page, array('vms-dashboard', 'vms-dashboard-operations', 'vms-dashboard-finance', 'vms-dashboard-health', 'vms-budget-calculator', 'vms-due-dates', 'vms-approvals'), true)) {
			return 'dashboard';
		}

		if (in_array($page, array('vms-schedule', 'vms-season-dates', 'vms-holidays', 'vms-passes', 'vms-event-command-center'), true)) {
			return 'planning';
		}

		if (in_array($page, array('vms-vendor-command-center', 'vms-vendor-availability', 'vms-tasks', 'vms-task-templates', 'vms-checklist-templates', 'vms-task-settings', 'vms-my-tasks', 'vms-staffing-templates', 'vms-staffing-rollups', 'vms-verifications'), true)) {
			return 'vendors_staff';
		}

		if (in_array($page, array('vms-teams', 'vms-alert-presets', 'vms-ops-console-teams', 'vms-ops-console-presets'), true)) {
			return 'vendors_staff';
		}

		if ($page === 'vms-social-sharing' || $page === 'vms-marketing-social' || strpos($page, 'vms-ma-ads-') === 0 || strpos($page, 'vms-meta-ads') === 0) {
			return 'marketing_social';
		}

		if (in_array($page, array('vms-integrity-venue-links', 'vms-integrity-calendar-links', 'vms-tour-maintenance'), true)) {
			return 'venues';
		}

		if (in_array($page, array('vms-settings', 'vms-guided-tours', 'vms-status-notices', 'vms-reference-keys-map', 'vms-continuity-binder', 'vms-docs', 'vms-import-event-plans'), true)) {
			return 'settings';
		}

			if (in_array($page, array('vms-data-tools', 'vms-ops-console-hub', 'vms-ops-console', 'vms-ops-console-id-scans', 'vms-ticket-integrity', 'vms-admin-pages'), true)) {
				return 'tools';
			}

			/**
			 * Final chance for add-ons to map their own admin pages into an existing VMS
			 * top-nav cluster (planning, vendors_staff, tools, etc.).
			 *
			 * @param string $cluster
			 * @param string $page
			 * @param string $post_type
			 */
			$cluster = apply_filters('vms_admin_ui_active_cluster', '', $page, $post_type);
			if (!is_string($cluster)) {
				$cluster = '';
			}

			return $cluster;
	}
}
