<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_admin_ui_vendor_application_post_type')) {
	function vms_admin_ui_vendor_application_post_type(): string
	{
		if (defined('BVMGR_VENDOR_APP_CPT') && BVMGR_VENDOR_APP_CPT !== '') {
			return sanitize_key((string) BVMGR_VENDOR_APP_CPT);
		}

		if (post_type_exists('vms_vendor_application')) {
			return 'vms_vendor_application';
		}

		return 'vms_vendor_app';
	}
}

if (!function_exists('vms_admin_ui_registered_page_url')) {
	function vms_admin_ui_registered_page_url(string $slug): string
	{
		if (!function_exists('menu_page_url')) {
			return '';
		}

		$url = menu_page_url($slug, false);
		if (!is_string($url) || $url === '') {
			return '';
		}

		return $url;
	}
}

if (!function_exists('vms_admin_ui_meta_ads_urls')) {
	/**
	 * @return array<string,string>
	 */
	function vms_admin_ui_meta_ads_urls(string $fallback_url): array
	{
		$urls = array(
			'builder' => add_query_arg(array('section' => 'meta-ads-builder'), $fallback_url),
			'promote' => add_query_arg(array('section' => 'promotable-events'), $fallback_url),
			'performance' => add_query_arg(array('section' => 'meta-ads-performance'), $fallback_url),
			'logs' => add_query_arg(array('section' => 'meta-ads-logs'), $fallback_url),
			'settings' => add_query_arg(array('section' => 'meta-ads-settings'), $fallback_url),
		);

		$slugs = array(
			'builder' => 'vms-ma-ads-builder',
			'promote' => 'vms-ma-ads-promote',
			'performance' => 'vms-ma-ads-performance',
			'logs' => 'vms-ma-ads-logs',
			'settings' => 'vms-ma-ads-settings',
		);

		foreach ($slugs as $key => $slug) {
			$registered = vms_admin_ui_registered_page_url($slug);
			if ($registered !== '') {
				$urls[$key] = $registered;
			}
		}

		return $urls;
	}
}

if (!function_exists('vms_admin_ui_registered_or_page_url')) {
	function vms_admin_ui_registered_or_page_url(string $registered_slug, string $fallback_slug): string
	{
		$registered = vms_admin_ui_registered_page_url($registered_slug);
		if ($registered !== '') {
			return $registered;
		}

		return vms_admin_ui_page_url($fallback_slug);
	}
}

if (!function_exists('vms_admin_ui_data_tools_capability')) {
	function vms_admin_ui_data_tools_capability(): string
	{
		if (defined('VMS_CAP_MANAGE_DATA_TOOLS') && is_string(VMS_CAP_MANAGE_DATA_TOOLS) && VMS_CAP_MANAGE_DATA_TOOLS !== '') {
			return (string) VMS_CAP_MANAGE_DATA_TOOLS;
		}

		if (defined('VMS_DT_CAP_IMPORT_VENDORS') && is_string(VMS_DT_CAP_IMPORT_VENDORS) && VMS_DT_CAP_IMPORT_VENDORS !== '') {
			return (string) VMS_DT_CAP_IMPORT_VENDORS;
		}

		return 'manage_options';
	}
}

if (!function_exists('vms_admin_ui_current_user_can_data_tools')) {
	function vms_admin_ui_current_user_can_data_tools(): bool
	{
		return current_user_can(vms_admin_ui_data_tools_capability()) || current_user_can('manage_options');
	}
}

if (!function_exists('vms_admin_ui_ops_capability')) {
	function vms_admin_ui_ops_capability(): string
	{
		$cap = vms_admin_ui_data_tools_capability();
		$cap = apply_filters('vms_admin_ui_ops_capability', $cap);
		if (!is_string($cap) || $cap === '') {
			$cap = 'manage_options';
		}
		return $cap;
	}
}

if (!function_exists('vms_admin_ui_current_user_can_ops')) {
	function vms_admin_ui_current_user_can_ops(): bool
	{
		return current_user_can(vms_admin_ui_ops_capability()) || current_user_can('manage_options');
	}
}

if (!function_exists('vms_admin_ui_nav_clusters')) {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	function vms_admin_ui_nav_clusters(): array
	{
		$vendor_app_pt = vms_admin_ui_vendor_application_post_type();
		$rating_url = post_type_exists('vms_rating')
			? vms_admin_ui_post_type_url('vms_rating')
			: vms_admin_ui_post_type_url('vms_vendor');
		$vendor_app_url = post_type_exists($vendor_app_pt)
			? vms_admin_ui_post_type_url($vendor_app_pt)
			: vms_admin_ui_post_type_url('vms_vendor');
		$planning_landing = vms_admin_ui_get_planning_landing_url();
		$marketing_landing = vms_admin_ui_page_url('vms-marketing-social');
		$meta_ads_urls = vms_admin_ui_meta_ads_urls($marketing_landing);
		$ops_console_url = function_exists('vms_ops_admin_render_settings_page')
			? admin_url('admin.php?page=vms-ops-console')
			: vms_admin_ui_registered_or_page_url('vms-ops-console', 'vms-ops-console-hub');
		$teams_url = vms_admin_ui_registered_or_page_url('vms-ops-console-teams', 'vms-teams');
		$alert_presets_url = vms_admin_ui_registered_or_page_url('vms-ops-console-presets', 'vms-alert-presets');

		$clusters = array(
			'dashboard' => array(
				'label' => 'Dashboard',
				'url'   => vms_admin_ui_page_url('vms-dashboard'),
				'items' => array(
					array('label' => 'Dashboard', 'url' => vms_admin_ui_page_url('vms-dashboard')),
					array('label' => 'Approvals', 'url' => vms_admin_ui_page_url('vms-approvals')),
					array('label' => 'Dashboard: Operations', 'url' => vms_admin_ui_page_url('vms-dashboard-operations')),
					array('label' => 'Dashboard: Finance', 'url' => vms_admin_ui_page_url('vms-dashboard-finance')),
					array('label' => 'Dashboard: Onboarding & Health', 'url' => vms_admin_ui_page_url('vms-dashboard-health')),
					array('label' => 'Budget Calculator', 'url' => vms_admin_ui_page_url('vms-budget-calculator')),
					array('label' => 'Due Dates', 'url' => vms_admin_ui_page_url('vms-due-dates')),
				),
			),
			'planning' => array(
				'label' => 'Planning',
				'url'   => $planning_landing,
				'items' => array(
					array('label' => 'Schedule', 'url' => vms_admin_ui_page_url('vms-schedule')),
					array('label' => 'Event Plans', 'url' => vms_admin_ui_post_type_url('vms_event_plan')),
					array('label' => 'Guest Passes', 'url' => vms_admin_ui_page_url('vms-passes')),
					array('label' => 'Season Dates', 'url' => vms_admin_ui_page_url('vms-season-dates')),
					array('label' => 'Holidays', 'url' => vms_admin_ui_page_url('vms-holidays')),
				),
			),
			'vendors_staff' => array(
				'label' => 'Vendors & Staff',
				'url'   => vms_admin_ui_page_url('vms-vendor-command-center'),
				'items' => array(
					array('label' => 'Vendor Command Center', 'url' => vms_admin_ui_page_url('vms-vendor-command-center')),
					array('label' => 'Vendor Availability', 'url' => vms_admin_ui_page_url('vms-vendor-availability')),
					array('label' => 'Vendors', 'url' => vms_admin_ui_post_type_url('vms_vendor')),
					array('label' => 'Vendor Types', 'url' => add_query_arg(array('taxonomy' => 'vms_vendor_type', 'post_type' => 'vms_vendor'), admin_url('edit-tags.php'))),
					array('label' => 'Vendor Categories', 'url' => add_query_arg(array('taxonomy' => 'vms_vendor_category', 'post_type' => 'vms_vendor'), admin_url('edit-tags.php'))),
					array('label' => 'Staff', 'url' => vms_admin_ui_post_type_url('vms_staff')),
					array('label' => 'Staff Tasks', 'url' => vms_admin_ui_page_url('vms-tasks')),
					array('label' => 'Task Templates', 'url' => vms_admin_ui_page_url('vms-task-templates')),
					array('label' => 'Checklist Templates', 'url' => vms_admin_ui_page_url('vms-checklist-templates')),
					array('label' => 'Task Settings', 'url' => vms_admin_ui_page_url('vms-task-settings')),
					array('label' => 'My Tasks', 'url' => vms_admin_ui_page_url('vms-my-tasks')),
					array('label' => 'Comp Packages', 'url' => vms_admin_ui_post_type_url('vms_comp_package')),
					array('label' => 'Ratings', 'url' => $rating_url),
					array('label' => 'Vendor Applications', 'url' => $vendor_app_url),
					array('label' => 'Eligibility Approvals', 'url' => vms_admin_ui_page_url('vms-verifications')),
					array('label' => 'Staff Roles', 'url' => add_query_arg(array('taxonomy' => 'vms_staff_role', 'post_type' => 'vms_staff'), admin_url('edit-tags.php'))),
					array('label' => 'Staffing Templates', 'url' => vms_admin_ui_page_url('vms-staffing-templates')),
					array('label' => 'Staffing Rollups', 'url' => vms_admin_ui_page_url('vms-staffing-rollups')),
					array('label' => 'Teams', 'url' => $teams_url),
					array('label' => 'Alert Presets', 'url' => $alert_presets_url),
				),
			),
			'marketing_social' => array(
				'label' => 'Marketing & Social',
				'url'   => $marketing_landing,
				'items' => array(
					array('label' => 'Social Sharing', 'url' => vms_admin_ui_page_url('vms-social-sharing')),
					array('label' => 'Email Follow-Ups', 'url' => vms_admin_ui_page_url('vms-email-followups')),
					array('label' => 'Meta Ads Builder', 'url' => $meta_ads_urls['builder']),
					array('label' => 'Promotable Events', 'url' => $meta_ads_urls['promote']),
					array('label' => 'Meta Ads Performance', 'url' => $meta_ads_urls['performance']),
					array('label' => 'Meta Ads Logs', 'url' => $meta_ads_urls['logs']),
					array('label' => 'Meta Ads Settings', 'url' => $meta_ads_urls['settings']),
				),
			),
			'venues' => array(
				'label' => 'Venues',
				'url'   => vms_admin_ui_post_type_url('vms_venue'),
				'items' => array(
					array('label' => 'Venues', 'url' => vms_admin_ui_post_type_url('vms_venue')),
					array('label' => 'Integrity: Venue Links', 'url' => vms_admin_ui_page_url('vms-integrity-venue-links')),
					array('label' => 'Integrity: Calendar Links', 'url' => vms_admin_ui_page_url('vms-integrity-calendar-links')),
					array('label' => 'Guided Tours', 'url' => vms_admin_ui_page_url('vms-guided-tours')),
				),
			),
			'settings' => array(
				'label' => 'Settings',
				'url'   => vms_admin_ui_page_url('vms-settings'),
				'items' => array(
					array('label' => 'Settings', 'url' => vms_admin_ui_page_url('vms-settings')),
					array('label' => 'Guided Tours', 'url' => vms_admin_ui_page_url('vms-guided-tours')),
					array('label' => 'Status Notices', 'url' => vms_admin_ui_page_url('vms-status-notices')),
					array('label' => 'Import Event Plans (CSV)', 'url' => vms_admin_ui_page_url('vms-import-event-plans')),
					array('label' => 'Reference: Keys + Identifiers', 'url' => vms_admin_ui_page_url('vms-reference-keys-map')),
					array('label' => 'Continuity Binder', 'url' => vms_admin_ui_page_url('vms-continuity-binder')),
					array('label' => 'Docs', 'url' => vms_admin_ui_page_url('vms-docs')),
				),
			),
			'tools' => array(
				'label' => 'Tools',
				'url'   => vms_admin_ui_page_url('vms-admin-pages'),
				'items' => array(
					array('label' => 'Data Tools', 'url' => vms_admin_ui_page_url('vms-data-tools')),
					array('label' => 'Ticket Integrity', 'url' => vms_admin_ui_page_url('vms-ticket-integrity')),
					array('label' => 'Ops Console', 'url' => $ops_console_url),
				),
			),
		);

		if (function_exists('vms_admin_menu_apply_registry_to_nav_clusters')) {
			$clusters = vms_admin_menu_apply_registry_to_nav_clusters($clusters);
		}

		/**
		 * Allow add-ons to register links into the compact VMS top navigation without
		 * growing the wp-admin left rail again.
		 *
		 * Expected shape mirrors the core cluster structure:
		 * array(
		 *   'planning' => array(
		 *     'label' => 'Planning',
		 *     'url'   => admin_url('admin.php?page=vms-schedule'),
		 *     'items' => array(
		 *       array('label' => 'Fill Dates', 'url' => admin_url('admin.php?page=vms-fill-dates')),
		 *     ),
		 *   ),
		 * )
		 *
		 * @param array<string,array<string,mixed>> $clusters
		 */
		$clusters = apply_filters('vms_admin_ui_nav_clusters', $clusters);
		if (!is_array($clusters)) {
			$clusters = array();
		}

		foreach ($clusters as $cluster_key => $cluster) {
			if (!is_array($cluster)) {
				unset($clusters[$cluster_key]);
				continue;
			}

			$cluster = apply_filters('vms_admin_ui_nav_cluster', $cluster, $cluster_key, $clusters);
			if (!is_array($cluster)) {
				unset($clusters[$cluster_key]);
				continue;
			}

			$items = isset($cluster['items']) && is_array($cluster['items']) ? array_values($cluster['items']) : array();
			$items = apply_filters('vms_admin_ui_nav_cluster_items', $items, $cluster_key, $cluster);
			$cluster['items'] = is_array($items) ? array_values($items) : array();
			$clusters[$cluster_key] = $cluster;
		}

		return $clusters;
	}
}

if (!function_exists('vms_admin_ui_render_top_nav')) {
	if (!function_exists('vms_admin_ui_nav_cluster_icon_class')) {
		function vms_admin_ui_nav_cluster_icon_class(string $cluster_key): string
		{
			$map = array(
				'dashboard' => 'dashicons-chart-pie',
				'planning' => 'dashicons-calendar-alt',
				'vendors_staff' => 'dashicons-groups',
				'marketing_social' => 'dashicons-megaphone',
				'venues' => 'dashicons-location-alt',
				'settings' => 'dashicons-admin-generic',
				'tools' => 'dashicons-admin-tools',
			);

			return isset($map[$cluster_key]) ? (string) $map[$cluster_key] : '';
		}
	}

	/**
	 * @param array<string,string> $current
	 */
	function vms_admin_ui_nav_item_is_current(array $item, array $current): bool
	{
		$url = isset($item['url']) ? (string) $item['url'] : '';
		if ($url === '') {
			return false;
		}

		$parts = wp_parse_url($url);
		if (!is_array($parts)) {
			return false;
		}

		$query = isset($parts['query']) ? (string) $parts['query'] : '';
		if ($query === '') {
			return false;
		}

		$args = array();
		parse_str($query, $args);

		$item_page = isset($args['page']) ? sanitize_key((string) $args['page']) : '';
		$item_post_type = isset($args['post_type']) ? sanitize_key((string) $args['post_type']) : '';
		$item_taxonomy = isset($args['taxonomy']) ? sanitize_key((string) $args['taxonomy']) : '';
		$item_tab = isset($args['tab']) ? sanitize_key((string) $args['tab']) : '';
		$item_section = isset($args['section']) ? sanitize_key((string) $args['section']) : '';

		if ($item_page === '' && $item_post_type === '' && $item_taxonomy === '' && $item_tab === '' && $item_section === '') {
			return false;
		}

		if ($item_page !== '' && $item_page !== $current['page']) {
			return false;
		}
		if ($item_post_type !== '' && $item_post_type !== $current['post_type']) {
			return false;
		}
		if ($item_taxonomy !== '' && $item_taxonomy !== $current['taxonomy']) {
			return false;
		}
		if ($item_tab !== '' && $item_tab !== $current['tab']) {
			return false;
		}
		if ($item_section !== '' && $item_section !== $current['section']) {
			return false;
		}

		// Prevent broad post-type links from also matching taxonomy manager screens.
		if (
			$item_post_type !== ''
			&& $item_taxonomy === ''
			&& in_array($current['pagenow'], array('edit-tags.php', 'term.php'), true)
			&& $current['taxonomy'] !== ''
		) {
			return false;
		}

		// Prevent "base page" links from also matching sectioned/tabbed views of the same page.
		if (
			$item_page !== ''
			&& $item_taxonomy === ''
			&& $item_tab === ''
			&& $item_section === ''
			&& ($current['taxonomy'] !== '' || $current['tab'] !== '' || $current['section'] !== '')
		) {
			return false;
		}

		return true;
	}

		function vms_admin_ui_render_top_nav(): void
		{
			$clusters = vms_admin_ui_nav_clusters();
			$active = vms_admin_ui_active_cluster();
		$current_page = vms_admin_ui_get_page_slug();
		$current_post_type = vms_admin_ui_get_post_type();
		$current_taxonomy = sanitize_key(vms_admin_ui_query_arg('taxonomy'));
		$current_tab = sanitize_key(vms_admin_ui_query_arg('tab'));
		$current_section = sanitize_key(vms_admin_ui_query_arg('section'));

		global $pagenow;
		$current_context = array(
			'page' => $current_page,
			'post_type' => $current_post_type,
			'taxonomy' => $current_taxonomy,
			'tab' => $current_tab,
			'section' => $current_section,
			'pagenow' => is_string($pagenow) ? $pagenow : '',
		);

		if ($active === '' || !isset($clusters[$active])) {
			foreach ($clusters as $key => $cluster) {
				$items = isset($cluster['items']) && is_array($cluster['items']) ? $cluster['items'] : array();
				foreach ($items as $item) {
					if (vms_admin_ui_nav_item_is_current($item, $current_context)) {
						$active = $key;
						break 2;
					}
				}
			}
		}

		$active_cluster = '';
		if ($active !== '' && isset($clusters[$active])) {
			$active_cluster = (string) $active;
		}

		echo '<nav class="vms-admin-topnav" aria-label="Backstage Venue Manager top navigation"';
		if ($active_cluster !== '') {
			echo ' data-vms-cluster="' . esc_attr($active_cluster) . '"';
		}
		echo '>';
		echo '<div class="vms-admin-topnav__primary-row">';
		foreach ($clusters as $key => $cluster) {
			$label = isset($cluster['label']) ? (string) $cluster['label'] : '';
			$url = isset($cluster['url']) ? (string) $cluster['url'] : '#';
			$items = isset($cluster['items']) && is_array($cluster['items']) ? $cluster['items'] : array();
			$icon_class = vms_admin_ui_nav_cluster_icon_class((string) $key);
			$item_class = 'vms-admin-topnav__primary';
			$is_active = ($key === $active);
			if ($is_active) {
				$item_class .= ' is-active';
			}
			$wrap_class = 'vms-admin-topnav__primary-wrap';
			if ($is_active) {
				$wrap_class .= ' is-active';
			}
			$has_quick_menu = !empty($items);
			if ($has_quick_menu) {
				$wrap_class .= ' has-quick-menu';
			}
			$quick_menu_id = 'vms-admin-topnav-quick-' . sanitize_html_class((string) $key);

			echo '<div class="' . esc_attr($wrap_class) . '" data-vms-cluster="' . esc_attr((string) $key) . '">';
			echo '<a class="' . esc_attr($item_class) . '" href="' . esc_url($url) . '" data-vms-cluster="' . esc_attr((string) $key) . '"';
			if ($is_active) {
				echo ' aria-current="page"';
			}
			if ($has_quick_menu) {
				echo ' aria-haspopup="menu" aria-expanded="false" aria-controls="' . esc_attr($quick_menu_id) . '"';
			}
			echo '>';
			if ($icon_class !== '') {
				echo '<span class="dashicons ' . esc_attr($icon_class) . ' vms-admin-topnav__icon" aria-hidden="true"></span>';
			}
			echo '<span class="vms-admin-topnav__label">' . esc_html($label) . '</span>';
			echo '</a>';

			if ($has_quick_menu) {
				/* translators: %s: top navigation item label. */
				$quick_menu_label = sprintf(__('%s quick menu', 'backstage-venue-manager'), $label);
				echo '<div class="vms-admin-topnav__quick-menu" id="' . esc_attr($quick_menu_id) . '" role="menu" aria-label="' . esc_attr($quick_menu_label) . '">';
				foreach ($items as $quick_item) {
					$quick_label = isset($quick_item['label']) ? (string) $quick_item['label'] : '';
					$quick_url = isset($quick_item['url']) ? (string) $quick_item['url'] : '#';
					$quick_class = 'vms-admin-topnav__quick-link';
					$quick_current = vms_admin_ui_nav_item_is_current($quick_item, $current_context);
					if ($quick_current) {
						$quick_class .= ' is-current';
					}

					echo '<a class="' . esc_attr($quick_class) . '" href="' . esc_url($quick_url) . '" role="menuitem"';
					if ($quick_current) {
						echo ' aria-current="page"';
					}
					echo '>' . esc_html($quick_label) . '</a>';
				}
				echo '</div>';
			}

			echo '</div>';
		}
		echo '</div>';

			if ($active !== '' && isset($clusters[$active]) && isset($clusters[$active]['items']) && is_array($clusters[$active]['items'])) {
				$active_items = $clusters[$active]['items'];
				if (!empty($active_items)) {
					echo '<div class="vms-admin-topnav__secondary-row">';
				foreach ($active_items as $item) {
					$item_label = isset($item['label']) ? (string) $item['label'] : '';
					$item_url = isset($item['url']) ? (string) $item['url'] : '#';
					$item_class = 'vms-admin-topnav__sublink';
					$is_current = vms_admin_ui_nav_item_is_current($item, $current_context);
					if ($is_current) {
						$item_class .= ' is-current';
					}
					echo '<a class="' . esc_attr($item_class) . '" href="' . esc_url($item_url) . '"';
					if ($is_current) {
						echo ' aria-current="page"';
					}
					echo '>' . esc_html($item_label) . '</a>';
				}
					echo '</div>';
				}
			}

			$version = defined('BVMGR_VERSION') ? trim((string) BVMGR_VERSION) : '';
			if ($version !== '') {
				echo '<div class="vms-admin-topnav__build">Backstage Venue Manager v' . esc_html($version) . '</div>';
			}

			echo '</nav>';
		}
	}

if (!function_exists('vms_admin_ui_render_global_top_nav')) {
	function vms_admin_ui_render_global_top_nav(): void
	{
		if (!vms_admin_ui_is_vms_screen()) {
			return;
		}

		if (vms_admin_ui_is_shell_page()) {
			return;
		}

		$active_cluster = '';
		if (function_exists('vms_admin_ui_active_cluster')) {
			$cluster = vms_admin_ui_active_cluster();
			if (is_string($cluster) && $cluster !== '') {
				$active_cluster = sanitize_html_class($cluster);
			}
		}

		echo '<div class="vms-admin-global-header-zone"';
		if ($active_cluster !== '') {
			echo ' data-vms-cluster="' . esc_attr($active_cluster) . '"';
		}
		echo '>';
		echo '<div class="vms-admin-global-topnav">';
		vms_admin_ui_render_top_nav();
		echo '</div>';
		echo '</div>';
	}
}
add_action('all_admin_notices', 'vms_admin_ui_render_global_top_nav', 100);

	if (!function_exists('vms_admin_ui_register_hub_pages')) {
	function vms_admin_ui_register_hub_pages(): void
	{
		$capability = 'manage_options';
		$menu_cap = 'read';

		add_submenu_page(
			'vms-dashboard',
			__('Marketing & Social', 'backstage-venue-manager'),
			__('Marketing & Social', 'backstage-venue-manager'),
			$capability,
			'vms-marketing-social',
			'vms_admin_ui_render_marketing_social_hub_page'
		);

		if (vms_admin_ui_current_user_can_data_tools()) {
			add_submenu_page(
				'vms-dashboard',
				__('Data Tools', 'backstage-venue-manager'),
				__('Data Tools', 'backstage-venue-manager'),
				$menu_cap,
				'vms-data-tools',
				'vms_admin_ui_render_data_tools_page'
			);
		}

		if (vms_admin_ui_current_user_can_ops()) {
			$ops_slug = function_exists('vms_ops_admin_render_settings_page')
				? 'vms-ops-console'
				: 'vms-ops-console-hub';
			$ops_callback = function_exists('vms_ops_admin_render_settings_page')
				? 'vms_admin_ui_render_ops_console_settings_page'
				: 'vms_admin_ui_render_ops_console_page';

			add_submenu_page(
				'vms-dashboard',
				__('Ops Console', 'backstage-venue-manager'),
				__('Ops Console', 'backstage-venue-manager'),
				$menu_cap,
				$ops_slug,
				$ops_callback
			);

			add_submenu_page(
				'vms-dashboard',
				__('Teams', 'backstage-venue-manager'),
				__('Teams', 'backstage-venue-manager'),
				$menu_cap,
				'vms-teams',
				'vms_admin_ui_render_teams_page'
			);

			add_submenu_page(
				'vms-dashboard',
				__('Alert Presets', 'backstage-venue-manager'),
				__('Alert Presets', 'backstage-venue-manager'),
				$menu_cap,
				'vms-alert-presets',
				'vms_admin_ui_render_alert_presets_page'
			);

			// Hidden bridge route so legacy Ops hub URLs continue to resolve.
			if (function_exists('vms_ops_admin_render_settings_page')) {
				add_submenu_page(
					'vms-dashboard',
					__('Ops Console Hub', 'backstage-venue-manager'),
					__('Ops Console Hub', 'backstage-venue-manager'),
					$menu_cap,
					'vms-ops-console-hub',
					'vms_admin_ui_render_ops_console_page'
				);
			}
		}
	}
}
add_action('admin_menu', 'vms_admin_ui_register_hub_pages', 20);


if (!function_exists('vms_admin_ui_compact_left_menu_hide_known_secondary')) {
	function vms_admin_ui_compact_left_menu_hide_known_secondary(string $slug): bool
	{
		$known_secondary = array(
			'vms-dashboard-operations',
			'vms-dashboard-finance',
			'vms-dashboard-health',
			'vms-approvals',
			'vms-verifications',
			'vms-budget-calculator',
			'vms-event-profitability',
			'vms-goals-forecast',
			'vms-due-dates',
			'vms-season-dates',
			'vms-holidays',
			'vms-event-command-center',
			'vms-passes',
			'vms-vendor-availability',
			'edit.php?post_type=vms_vendor',
			'edit.php?post_type=vms_staff',
			'edit.php?post_type=vms_comp_package',
			'edit.php?post_type=vms_rating',
			'edit.php?post_type=vms_vendor_app',
			'edit.php?post_type=vms_vendor_application',
			'vms-tasks',
			'vms-task-templates',
			'vms-checklist-templates',
			'vms-task-settings',
			'vms-my-tasks',
			'vms-staffing-templates',
			'vms-staffing-rollups',
			'vms-teams',
			'vms-alert-presets',
			'vms-ops-console-teams',
			'vms-ops-console-presets',
			'vms-social-sharing',
			'vms-email-followups',
			'vms-integrity-venue-links',
			'vms-integrity-calendar-links',
			'vms-guided-tours',
			'vms-tour-maintenance',
			'vms-status-notices',
			'vms-reference-keys-map',
			'vms-continuity-binder',
			'vms-import-event-plans',
			'vms-docs',
			'vms-ops-console-id-scans',
			'vms-ticket-integrity',
			'vms-square-sync-protection',
		);

		$known_secondary = apply_filters('vms_admin_ui_compact_left_menu_known_secondary_slugs', $known_secondary);
		if (!is_array($known_secondary)) {
			$known_secondary = array();
		}

		if (function_exists('vms_admin_menu_left_menu_visible_slugs')) {
			$registry_visible = vms_admin_menu_left_menu_visible_slugs();
			if (in_array($slug, $registry_visible, true)) {
				return false;
			}
		}

		return in_array($slug, array_map('strval', $known_secondary), true);
	}
}

if (!function_exists('vms_admin_ui_default_force_visible_secondary_slugs')) {
	/**
	 * Compact-menu safety valve.
	 *
	 * Default behavior deliberately keeps the WordPress left rail limited to
	 * durable section launchers. Add-ons/pages should use registry top-nav and
	 * directory metadata for discovery; only section-level menu changes should
	 * use the vms_admin_menu_left_rail_specs filter.
	 */
	function vms_admin_ui_default_force_visible_secondary_slugs(array $slugs): array
	{
		return array_values(array_unique(array_filter(array_map('strval', $slugs))));
	}
}
add_filter('vms_admin_ui_compact_left_menu_force_visible_secondary_slugs', 'vms_admin_ui_default_force_visible_secondary_slugs', 5);

if (!function_exists('vms_admin_ui_menu_item_label_text')) {
	function vms_admin_ui_menu_item_label_text($label): string
	{
		$label = wp_strip_all_tags((string) $label);
		$label = preg_replace('/\s+/', ' ', $label);
		return is_string($label) ? trim($label) : '';
	}
}

if (!function_exists('vms_admin_ui_grouped_left_menu_cluster_for_page')) {
	/**
	 * @param array<string,mixed> $page
	 */
	function vms_admin_ui_grouped_left_menu_cluster_for_page(array $page): string
	{
		$section = isset($page['section']) ? sanitize_key((string) $page['section']) : '';
		if ($section !== '' && function_exists('vms_admin_menu_cluster_for_section')) {
			$cluster = vms_admin_menu_cluster_for_section($section);
			if (is_string($cluster) && $cluster !== '') {
				return sanitize_key($cluster);
			}
		}

		$slug = isset($page['slug']) ? (string) $page['slug'] : '';
		$label = isset($page['label']) ? (string) $page['label'] : '';
		if (function_exists('vms_admin_menu_guess_section') && function_exists('vms_admin_menu_cluster_for_section')) {
			$guess = vms_admin_menu_guess_section($slug, $label);
			$cluster = vms_admin_menu_cluster_for_section($guess);
			if (is_string($cluster) && $cluster !== '') {
				return sanitize_key($cluster);
			}
		}

		return 'tools';
	}
}

if (!function_exists('vms_admin_ui_grouped_left_menu_default_clusters')) {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	function vms_admin_ui_grouped_left_menu_default_clusters(): array
	{
		$clusters = array();

		if (function_exists('vms_admin_menu_left_rail_specs')) {
			foreach (vms_admin_menu_left_rail_specs() as $spec) {
				if (!is_array($spec)) {
					continue;
				}

				$key = isset($spec['cluster']) ? sanitize_key((string) $spec['cluster']) : '';
				if ($key === '') {
					$key = isset($spec['section']) ? sanitize_key((string) $spec['section']) : '';
				}
				if ($key === '') {
					continue;
				}

				$clusters[$key] = array(
					'key' => $key,
					'label' => isset($spec['label']) ? (string) $spec['label'] : ucwords(str_replace('_', ' ', $key)),
					'sections' => isset($spec['sections']) && is_array($spec['sections']) ? array_map('sanitize_key', array_map('strval', $spec['sections'])) : array(),
					'slugs' => isset($spec['slugs']) && is_array($spec['slugs']) ? array_map('strval', $spec['slugs']) : array(),
					'pages' => array(),
				);
			}
		}

		if (empty($clusters)) {
			$clusters = array(
				'dashboard' => array('key' => 'dashboard', 'label' => __('Dashboard', 'backstage-venue-manager'), 'sections' => array('dashboard'), 'slugs' => array('vms-dashboard'), 'pages' => array()),
				'planning' => array('key' => 'planning', 'label' => __('Planning', 'backstage-venue-manager'), 'sections' => array('events_schedule', 'tickets_admissions'), 'slugs' => array('vms-schedule', 'edit.php?post_type=vms_event_plan'), 'pages' => array()),
				'vendors_staff' => array('key' => 'vendors_staff', 'label' => __('Vendors & Staff', 'backstage-venue-manager'), 'sections' => array('vendors_staff'), 'slugs' => array('vms-vendor-command-center'), 'pages' => array()),
				'marketing_social' => array('key' => 'marketing_social', 'label' => __('Marketing & Social', 'backstage-venue-manager'), 'sections' => array('marketing_sales'), 'slugs' => array('vms-marketing-social'), 'pages' => array()),
				'venues' => array('key' => 'venues', 'label' => __('Venues', 'backstage-venue-manager'), 'sections' => array('venue_setup'), 'slugs' => array('edit.php?post_type=vms_venue'), 'pages' => array()),
				'settings' => array('key' => 'settings', 'label' => __('Settings', 'backstage-venue-manager'), 'sections' => array('settings_addons'), 'slugs' => array('vms-settings'), 'pages' => array()),
				'tools' => array('key' => 'tools', 'label' => __('Tools', 'backstage-venue-manager'), 'sections' => array('reports_finance', 'tools_integrity'), 'slugs' => array('vms-admin-pages'), 'pages' => array()),
			);
		}

		return apply_filters('vms_admin_ui_grouped_left_menu_clusters', $clusters);
	}
}

if (!function_exists('vms_admin_ui_compact_left_menu')) {
	/**
	 * Keep the WordPress VMS flyout short.
	 *
	 * The left rail is only a launcher list for the primary VMS categories.
	 * Registered pages/modules still belong to their declared sections, but
	 * those detailed lists live inside VMS top navigation, section hubs, and
	 * All Backstage Venue Manager Pages. This prevents the WP sidebar from becoming a full module
	 * directory again.
	 */
	function vms_admin_ui_compact_left_menu(): void
	{
		global $submenu;

		if (!isset($submenu['vms-dashboard']) || !is_array($submenu['vms-dashboard'])) {
			return;
		}

		$existing_items = (array) $submenu['vms-dashboard'];
		if (empty($existing_items)) {
			return;
		}

		if (
			!isset($GLOBALS['bvmgr_admin_menu_all_submenu_items'])
			|| !is_array($GLOBALS['bvmgr_admin_menu_all_submenu_items'])
			|| count($existing_items) > count($GLOBALS['bvmgr_admin_menu_all_submenu_items'])
		) {
			$GLOBALS['bvmgr_admin_menu_all_submenu_items'] = $existing_items;
		}

		$items_by_slug = array();
		foreach ($existing_items as $item) {
			if (!is_array($item) || !isset($item[2])) {
				continue;
			}

			$slug = (string) $item[2];
			if ($slug === '' || isset($items_by_slug[$slug])) {
				continue;
			}

			$items_by_slug[$slug] = $item;
		}

		$specs = function_exists('vms_admin_menu_left_rail_specs') ? vms_admin_menu_left_rail_specs() : array();
		if (!is_array($specs) || empty($specs)) {
			return;
		}

		$launcher_items = array();
		$seen_slugs = array();

		foreach ($specs as $spec) {
			if (!is_array($spec)) {
				continue;
			}

			$label = isset($spec['label']) ? vms_admin_ui_menu_item_label_text($spec['label']) : '';
			if ($label === '') {
				$label = isset($spec['section']) ? ucwords(str_replace('_', ' ', (string) $spec['section'])) : '';
			}
			if ($label === '') {
				continue;
			}

			$preferred_slugs = isset($spec['slugs']) && is_array($spec['slugs']) ? array_map('strval', $spec['slugs']) : array();
			$launcher_item = null;
			$launcher_slug = '';

			foreach ($preferred_slugs as $preferred_slug) {
				if ($preferred_slug !== '' && isset($items_by_slug[$preferred_slug])) {
					$launcher_item = $items_by_slug[$preferred_slug];
					$launcher_slug = $preferred_slug;
					break;
				}
			}

			if (!is_array($launcher_item) || $launcher_slug === '') {
				continue;
			}

			if (isset($seen_slugs[$launcher_slug])) {
				continue;
			}

			$capability = isset($launcher_item[1]) && is_string($launcher_item[1]) && $launcher_item[1] !== ''
				? (string) $launcher_item[1]
				: 'manage_options';
			if (!current_user_can($capability)) {
				continue;
			}

			$launcher_item[0] = $label;
			$launcher_item[1] = $capability;
			$launcher_item[2] = $launcher_slug;
			$launcher_item[3] = $label;
			$launcher_item[4] = trim((isset($launcher_item[4]) && is_string($launcher_item[4]) ? $launcher_item[4] . ' ' : '') . 'vms-admin-ui-menu-section vms-admin-ui-menu-launcher');

			$launcher_items[] = $launcher_item;
			$seen_slugs[$launcher_slug] = true;
		}

		if (!empty($launcher_items)) {
			$submenu['vms-dashboard'] = $launcher_items;
		}
	}
}

// Run after WordPress has completed page-access checks. Running this on
// admin_menu removes/rewrites submenu pages too early and can make valid
// direct URLs look unauthorized. admin_head fires before the left admin menu
// is rendered, so the visible flyout can be rebuilt without breaking access.
add_action('admin_head', 'vms_admin_ui_compact_left_menu', 1);

if (!function_exists('vms_admin_ui_render_compact_left_menu_styles')) {
	function vms_admin_ui_render_compact_left_menu_styles(): void
	{
		return;
	}
}
add_action('admin_head', 'vms_admin_ui_render_compact_left_menu_styles', 2);


if (!function_exists('vms_admin_ui_current_page_registry_section')) {
	function vms_admin_ui_current_page_registry_section(): string
	{
		$page = vms_admin_ui_get_page_slug();
		$post_type = vms_admin_ui_get_post_type();

		if ($post_type === 'vms_event_plan') {
			return 'events_schedule';
		}
		if (in_array($post_type, array('vms_vendor', 'vms_staff', 'vms_comp_package', 'vms_rating', 'vms_vendor_app', 'vms_vendor_application'), true)) {
			return 'vendors_staff';
		}
		if ($post_type === 'vms_venue') {
			return 'venue_setup';
		}

		if ($page !== '' && function_exists('vms_admin_menu_registry')) {
			$registry = vms_admin_menu_registry();
			if (isset($registry[$page]['section']) && is_string($registry[$page]['section']) && $registry[$page]['section'] !== '') {
				return sanitize_key((string) $registry[$page]['section']);
			}
		}

		return '';
	}
}

if (!function_exists('vms_admin_ui_current_left_rail_slug')) {
	function vms_admin_ui_current_left_rail_slug(): string
	{
		$current_page = vms_admin_ui_get_page_slug();
		$current_post_type = vms_admin_ui_get_post_type();

		$current_menu_slug = $current_page;
		if ($current_menu_slug === '' && $current_post_type !== '') {
			$current_menu_slug = 'edit.php?post_type=' . $current_post_type;
		}

		global $submenu;
		$items_by_slug = array();
		if (isset($submenu['vms-dashboard']) && is_array($submenu['vms-dashboard'])) {
			foreach ($submenu['vms-dashboard'] as $item) {
				if (is_array($item) && isset($item[2])) {
					$items_by_slug[(string) $item[2]] = true;
				}
			}
		}

		// When the current page is actually present in the grouped VMS flyout,
		// highlight that page. Only fall back to the category launcher if the
		// current route is hidden/virtual.
		if ($current_menu_slug !== '' && isset($items_by_slug[$current_menu_slug])) {
			return $current_menu_slug;
		}

		$section = vms_admin_ui_current_page_registry_section();
		if ($section === '' || !function_exists('vms_admin_menu_left_rail_specs')) {
			return $current_menu_slug;
		}

		foreach (vms_admin_menu_left_rail_specs() as $spec) {
			if (!is_array($spec)) {
				continue;
			}

			$matches_section = sanitize_key((string) ($spec['section'] ?? '')) === $section;
			if (!$matches_section && isset($spec['sections']) && is_array($spec['sections'])) {
				$spec_sections = array_map('sanitize_key', array_map('strval', $spec['sections']));
				$matches_section = in_array($section, $spec_sections, true);
			}

			if (!$matches_section) {
				continue;
			}

			$slugs = isset($spec['slugs']) && is_array($spec['slugs']) ? $spec['slugs'] : array();
			foreach ($slugs as $slug) {
				$slug = (string) $slug;
				if ($slug !== '' && (empty($items_by_slug) || isset($items_by_slug[$slug]))) {
					return $slug;
				}
			}
		}

		return $current_menu_slug;
	}
}

if (!function_exists('vms_admin_ui_force_vms_parent_file')) {
	function vms_admin_ui_force_vms_parent_file($parent_file)
	{
		if (is_admin() && function_exists('vms_admin_ui_is_vms_screen') && vms_admin_ui_is_vms_screen()) {
			return 'vms-dashboard';
		}

		return $parent_file;
	}
}
add_filter('parent_file', 'vms_admin_ui_force_vms_parent_file', 99);

if (!function_exists('vms_admin_ui_force_vms_submenu_file')) {
	function vms_admin_ui_force_vms_submenu_file($submenu_file, $parent_file)
	{
		if (is_admin() && function_exists('vms_admin_ui_is_vms_screen') && vms_admin_ui_is_vms_screen()) {
			$slug = vms_admin_ui_current_left_rail_slug();
			if ($slug !== '') {
				return $slug;
			}
		}

		return $submenu_file;
	}
}
add_filter('submenu_file', 'vms_admin_ui_force_vms_submenu_file', 99, 2);

if (!function_exists('vms_admin_ui_remove_legacy_data_tools_tools_menu')) {
	function vms_admin_ui_remove_legacy_data_tools_tools_menu(): void
	{
		remove_submenu_page('tools.php', 'vms-data-tools');
	}
}
add_action('admin_menu', 'vms_admin_ui_remove_legacy_data_tools_tools_menu', 1001);

if (!function_exists('vms_admin_ui_handle_legacy_ops_console_slug')) {
	function vms_admin_ui_handle_legacy_ops_console_slug(): void
	{
		if (!is_admin()) {
			return;
		}

		$page = sanitize_key(vms_admin_ui_query_arg('page'));
		if ($page !== 'vms-ops-console') {
			return;
		}

		if (!vms_admin_ui_current_user_can_ops()) {
			return;
		}

		// Preserve the premium settings screen when it is available.
		if (function_exists('vms_ops_admin_render_settings_page')) {
			return;
		}

		$target = vms_admin_ui_page_url('vms-ops-console-hub');
		wp_safe_redirect($target);
		exit;
	}
}
add_action('admin_init', 'vms_admin_ui_handle_legacy_ops_console_slug', 5);

if (!function_exists('vms_admin_ui_handle_ops_console_hub_alias')) {
	function vms_admin_ui_handle_ops_console_hub_alias(): void
	{
		if (!is_admin() || !vms_admin_ui_current_user_can_ops()) {
			return;
		}

		$page = sanitize_key(vms_admin_ui_query_arg('page'));
		if ($page !== 'vms-ops-console-hub' || !function_exists('vms_ops_admin_render_settings_page')) {
			return;
		}

		wp_safe_redirect(vms_admin_ui_page_url('vms-ops-console'));
		exit;
	}
}
add_action('admin_init', 'vms_admin_ui_handle_ops_console_hub_alias', 6);

if (!function_exists('vms_admin_ui_maybe_redirect_ops_alias_pages')) {
	function vms_admin_ui_maybe_redirect_ops_alias_pages(): void
	{
		if (!is_admin() || !vms_admin_ui_current_user_can_ops()) {
			return;
		}

		$page = sanitize_key(vms_admin_ui_query_arg('page'));
		if ($page === '') {
			return;
		}

		$aliases = array(
			'vms-ops-console-teams' => 'vms-teams',
			'vms-ops-console-presets' => 'vms-alert-presets',
		);
		if (!isset($aliases[$page])) {
			return;
		}

		$target_slug = (string) $aliases[$page];
		$target = vms_admin_ui_registered_page_url($target_slug);
		if ($target === '') {
			$target = vms_admin_ui_page_url($target_slug);
		}
		if ($target === '') {
			return;
		}

		wp_safe_redirect($target);
		exit;
	}
}
add_action('admin_init', 'vms_admin_ui_maybe_redirect_ops_alias_pages', 6);

if (!function_exists('vms_admin_ui_render_marketing_social_hub_page')) {
	function vms_admin_ui_render_marketing_social_hub_page(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die('Insufficient permissions.');
		}

		$meta_ads_urls = vms_admin_ui_meta_ads_urls(vms_admin_ui_page_url('vms-marketing-social'));
		$meta_ads_builder_registered = (vms_admin_ui_registered_page_url('vms-ma-ads-builder') !== '');

		$render_content = static function () use ($meta_ads_urls, $meta_ads_builder_registered): void {
			echo '<p class="vms-admin-hub-intro">Open the core marketing tools from one place.</p>';
			if (!$meta_ads_builder_registered) {
				echo '<div class="notice notice-warning inline"><p>Meta Ads Builder screens are currently unavailable on this site. Use Social Sharing tools below, or enable the Meta Ads module.</p></div>';
			}
			echo '<div class="vms-admin-hub-grid">';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-social-sharing')) . '"><strong>Social Sharing</strong><span>Queue, account mapping, templates, and logs.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-email-followups')) . '"><strong>Email Follow-Ups</strong><span>Preview event-aware buyer reminders, send tests, and manage follow-up templates.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url($meta_ads_urls['builder']) . '"><strong>Meta Ads Builder</strong><span>Create or edit ad build drafts and copy packs.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url($meta_ads_urls['promote']) . '"><strong>Promotable Events</strong><span>Review upcoming plans and jump into promotion workflows.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url($meta_ads_urls['performance']) . '"><strong>Meta Ads Performance</strong><span>Inspect ad-delivery metrics and campaign health.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url($meta_ads_urls['logs']) . '"><strong>Meta Ads Logs</strong><span>Review ad sync logs and diagnostics.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url($meta_ads_urls['settings']) . '"><strong>Meta Ads Settings</strong><span>Manage credentials and guardrails for ad workflows.</span></a>';
			echo '</div>';
		};

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array('title' => __('Marketing & Social', 'backstage-venue-manager')),
				$render_content
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Marketing & Social', 'backstage-venue-manager') . '</h1>';
		$render_content();
		echo '</div>';
	}
}

if (!function_exists('vms_admin_ui_render_data_tools_page')) {
	function vms_admin_ui_render_data_tools_page(): void
	{
		if (!vms_admin_ui_current_user_can_data_tools()) {
			wp_die('Insufficient permissions.');
		}

		if (function_exists('vms_dt_render_tools_home')) {
			$render_content = static function (): void {
				vms_dt_render_tools_home();
			};

			if (function_exists('vms_admin_ui_render_shell')) {
				vms_admin_ui_render_shell(
					array(
						'title' => __('Data Tools', 'backstage-venue-manager'),
						'subtitle' => __('Importers, reports, reconciliation, and maintenance tools inside the Backstage Venue Manager admin shell.', 'backstage-venue-manager'),
						'shell_id' => 'vms-data-tools-wrap',
					),
					$render_content
				);
				return;
			}

			vms_dt_render_tools_home();
			return;
		}

		$render_content = static function (): void {
			echo '<p class="vms-admin-hub-intro">Run integrity and operational maintenance workflows.</p>';
			echo '<div class="vms-admin-hub-grid">';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-settings')) . '"><strong>Settings</strong><span>Global Backstage Venue Manager settings and integrity scan controls.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-import-event-plans')) . '"><strong>Import Event Plans (CSV)</strong><span>Preview and commit VMS-only Event Plan upserts from CSV.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-integrity-venue-links')) . '"><strong>Integrity: Venue Links</strong><span>Reconcile broken Event Plan venue links.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-integrity-calendar-links')) . '"><strong>Integrity: Calendar Links</strong><span>Reconcile missing or stale calendar event links.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-guided-tours')) . '"><strong>Guided Tours</strong><span>Manage global tour defaults, per-user resets, and run tours on demand.</span></a>';
			echo '</div>';
		};

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array('title' => __('Data Tools', 'backstage-venue-manager')),
				$render_content
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Data Tools', 'backstage-venue-manager') . '</h1>';
		$render_content();
		echo '</div>';
	}
}

if (!function_exists('vms_admin_ui_render_teams_page')) {
	function vms_admin_ui_render_teams_page(): void
	{
		if (!vms_admin_ui_current_user_can_ops()) {
			wp_die('Insufficient permissions.');
		}

		if (function_exists('vms_ops_admin_render_teams_page')) {
			vms_ops_admin_render_teams_page();
			return;
		}

		$render_content = static function (): void {
			echo '<p class="vms-admin-hub-intro">Teams configuration is staged for a later phase.</p>';
			echo '<div class="vms-admin-hub-grid">';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-staffing-templates')) . '"><strong>Staffing Templates</strong><span>Manage reusable staffing templates and slot structures.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-staffing-rollups')) . '"><strong>Staffing Rollups</strong><span>Review staffing readiness rollups and rebuild jobs.</span></a>';
			echo '</div>';
		};

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array('title' => __('Teams', 'backstage-venue-manager')),
				$render_content
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Teams', 'backstage-venue-manager') . '</h1>';
		$render_content();
		echo '</div>';
	}
}

if (!function_exists('vms_admin_ui_render_alert_presets_page')) {
	function vms_admin_ui_render_alert_presets_page(): void
	{
		if (!vms_admin_ui_current_user_can_ops()) {
			wp_die('Insufficient permissions.');
		}

		if (function_exists('vms_ops_admin_render_presets_page')) {
			vms_ops_admin_render_presets_page();
			return;
		}

		$render_content = static function (): void {
			echo '<p class="vms-admin-hub-intro">Alert Presets are staged for a later phase.</p>';
			echo '<div class="vms-admin-hub-grid">';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-staffing-rollups')) . '"><strong>Staffing Rollups</strong><span>Use rollup rebuild and readiness diagnostics today.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-data-tools')) . '"><strong>Data Tools</strong><span>Run integrity and operational checks while presets are phased in.</span></a>';
			echo '</div>';
		};

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array('title' => __('Alert Presets', 'backstage-venue-manager')),
				$render_content
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Alert Presets', 'backstage-venue-manager') . '</h1>';
		$render_content();
		echo '</div>';
	}
}

if (!function_exists('vms_admin_ui_render_ops_console_page')) {
	function vms_admin_ui_render_ops_console_page(): void
	{
		if (!vms_admin_ui_current_user_can_ops()) {
			wp_die('Insufficient permissions.');
		}

		$render_content = static function (): void {
			echo '<p class="vms-admin-hub-intro">Operational tools and integrity workflows.</p>';
			echo '<div class="vms-admin-hub-grid">';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-guided-tours')) . '"><strong>Guided Tours</strong><span>Manage tour defaults and launch tours directly from the registry.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-integrity-venue-links')) . '"><strong>Integrity: Venue Links</strong><span>Resolve broken Event Plan venue references.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-integrity-calendar-links')) . '"><strong>Integrity: Calendar Links</strong><span>Resolve missing or stale calendar event links.</span></a>';
			echo '<a class="vms-admin-hub-card" href="' . esc_url(vms_admin_ui_page_url('vms-data-tools')) . '"><strong>Data Tools</strong><span>Run integrity scan and global operational checks.</span></a>';
			echo '</div>';
		};

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array('title' => __('Backstage Venue Manager Ops Console', 'backstage-venue-manager')),
				$render_content
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Backstage Venue Manager Ops Console', 'backstage-venue-manager') . '</h1>';
		$render_content();
		echo '</div>';
	}
}

if (!function_exists('vms_admin_ui_render_ops_console_settings_page')) {
	function vms_admin_ui_render_ops_console_settings_page(): void
	{
		if (!vms_admin_ui_current_user_can_ops()) {
			wp_die('Insufficient permissions.');
		}

		if (function_exists('vms_ops_admin_render_settings_page')) {
			vms_ops_admin_render_settings_page();
			return;
		}

		vms_admin_ui_render_ops_console_page();
	}
}
