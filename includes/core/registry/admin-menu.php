<?php
/**
 * VMS Admin Menu Registry.
 *
 * First-pass goal: give VMS core and future add-ons one predictable contract
 * for discoverable admin pages without requiring each add-on to know or patch
 * the current VMS left-menu/top-nav internals.
 */

defined('ABSPATH') || exit;

if (!function_exists('vms_admin_menu_parent_slug')) {
	function vms_admin_menu_parent_slug(): string
	{
		return 'vms-dashboard';
	}
}

if (!function_exists('vms_i18n_runtime')) {
	function vms_i18n_runtime(string $text, string $domain = 'backstage-venue-manager'): string
	{
		if ($text === '') {
			return '';
		}

		if (doing_action('after_setup_theme') || did_action('after_setup_theme')) {
			return __($text, $domain);
		}

		return $text;
	}
}

if (!function_exists('vms_safe_label')) {
	function vms_safe_label($value, string $fallback = ''): string
	{
		$label = '';
		if (is_scalar($value)) {
			$label = trim(wp_strip_all_tags((string) $value));
		}

		if ($label !== '') {
			return $label;
		}

		$fallback = trim(wp_strip_all_tags($fallback));
		return $fallback;
	}
}

if (!function_exists('vms_admin_menu_default_label_from_slug')) {
	function vms_admin_menu_default_label_from_slug(string $slug, string $fallback = 'VMS'): string
	{
		$slug = sanitize_key($slug);
		if ($slug === '') {
			$fallback_label = vms_safe_label($fallback, 'VMS');
			return $fallback_label !== '' ? $fallback_label : 'VMS';
		}

		$label = str_replace(array('-', '_'), ' ', $slug);
		$label = preg_replace('/\s+/', ' ', $label);
		$label = is_string($label) ? ucwords(trim($label)) : '';
		if ($label !== '') {
			return $label;
		}

		$fallback_label = vms_safe_label($fallback, 'VMS');
		return $fallback_label !== '' ? $fallback_label : 'VMS';
	}
}

if (!function_exists('vms_admin_menu_default_sections')) {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	function vms_admin_menu_default_sections(): array
	{
		return array(
			'dashboard' => array(
				'section' => 'dashboard',
				'label' => vms_i18n_runtime('Dashboard', 'backstage-venue-manager'),
				'cluster' => 'dashboard',
				'order' => 10,
			),
			'events_schedule' => array(
				'section' => 'events_schedule',
				'label' => vms_i18n_runtime('Events & Schedule', 'backstage-venue-manager'),
				'cluster' => 'planning',
				'order' => 20,
			),
			'tickets_admissions' => array(
				'section' => 'tickets_admissions',
				'label' => vms_i18n_runtime('Tickets & Admissions', 'backstage-venue-manager'),
				'cluster' => 'planning',
				'order' => 30,
			),
			'vendors_staff' => array(
				'section' => 'vendors_staff',
				'label' => vms_i18n_runtime('Vendors & Staff', 'backstage-venue-manager'),
				'cluster' => 'vendors_staff',
				'order' => 40,
			),
			'marketing_sales' => array(
				'section' => 'marketing_sales',
				'label' => vms_i18n_runtime('Marketing & Sales', 'backstage-venue-manager'),
				'cluster' => 'marketing_social',
				'order' => 50,
			),
			'reports_finance' => array(
				'section' => 'reports_finance',
				'label' => vms_i18n_runtime('Reports & Finance', 'backstage-venue-manager'),
				'cluster' => 'tools',
				'order' => 60,
			),
			'venue_setup' => array(
				'section' => 'venue_setup',
				'label' => vms_i18n_runtime('Venue Setup', 'backstage-venue-manager'),
				'cluster' => 'venues',
				'order' => 70,
			),
			'tools_integrity' => array(
				'section' => 'tools_integrity',
				'label' => vms_i18n_runtime('Tools & Integrity', 'backstage-venue-manager'),
				'cluster' => 'tools',
				'order' => 80,
			),
			'settings_addons' => array(
				'section' => 'settings_addons',
				'label' => vms_i18n_runtime('Settings & Add-ons', 'backstage-venue-manager'),
				'cluster' => 'settings',
				'order' => 90,
			),
			'unclassified' => array(
				'label' => vms_i18n_runtime('Other / Unclassified', 'backstage-venue-manager'),
				'cluster' => 'tools',
				'order' => 999,
			),
		);
	}
}

if (!function_exists('vms_admin_menu_sections')) {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	function vms_admin_menu_sections(): array
	{
		$sections = vms_admin_menu_default_sections();
		$sections = apply_filters('vms_admin_menu_sections', $sections);
		return is_array($sections) ? $sections : array();
	}
}

if (!function_exists('vms_admin_menu_default_left_rail_specs')) {
	/**
	 * Durable WordPress left-rail section launchers.
	 *
	 * The WordPress left rail should mirror the primary VMS top navigation. It is
	 * intentionally a concise set of launchers, not a list of every registered
	 * screen. Use `sections` to allow several detailed registry sections to share
	 * the same visible launcher and active-state behavior.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	function vms_admin_menu_default_left_rail_specs(): array
	{
		return array(
			array(
				'section' => 'dashboard',
				'sections' => array('dashboard'),
				'cluster' => 'dashboard',
				'label' => vms_i18n_runtime('Dashboard', 'backstage-venue-manager'),
				'slugs' => array('vms-dashboard'),
			),
			array(
				'section' => 'planning',
				'sections' => array('events_schedule', 'tickets_admissions'),
				'cluster' => 'planning',
				'label' => vms_i18n_runtime('Planning', 'backstage-venue-manager'),
				'slugs' => array('vms-schedule', 'edit.php?post_type=vms_event_plan', 'vms-season-dates', 'vms-ticket-integrity', 'vms-passes'),
			),
			array(
				'section' => 'vendors_staff',
				'sections' => array('vendors_staff'),
				'cluster' => 'vendors_staff',
				'label' => vms_i18n_runtime('Vendors & Staff', 'backstage-venue-manager'),
				'slugs' => array('vms-vendor-command-center', 'vms-vendor-availability', 'edit.php?post_type=vms_vendor'),
			),
			array(
				'section' => 'marketing_social',
				'sections' => array('marketing_sales'),
				'cluster' => 'marketing_social',
				'label' => vms_i18n_runtime('Marketing & Social', 'backstage-venue-manager'),
				'slugs' => array('vms-marketing-social', 'vms-social-sharing', 'vms-email-followups'),
			),
			array(
				'section' => 'venues',
				'sections' => array('venue_setup'),
				'cluster' => 'venues',
				'label' => vms_i18n_runtime('Venues', 'backstage-venue-manager'),
				'slugs' => array('edit.php?post_type=vms_venue'),
			),
			array(
				'section' => 'settings',
				'sections' => array('settings_addons'),
				'cluster' => 'settings',
				'label' => vms_i18n_runtime('Settings', 'backstage-venue-manager'),
				'slugs' => array('vms-settings'),
			),
			array(
				'section' => 'tools',
				'sections' => array('reports_finance', 'tools_integrity'),
				'cluster' => 'tools',
				'label' => vms_i18n_runtime('Tools', 'backstage-venue-manager'),
				'slugs' => array('vms-admin-pages', 'vms-data-tools', 'vms-square-sync-protection', 'vms-ticket-integrity', 'vms-ops-console', 'vms-ops-console-hub'),
			),
		);
	}
}

if (!function_exists('vms_admin_menu_left_rail_specs')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_admin_menu_left_rail_specs(): array
	{
		$specs = vms_admin_menu_default_left_rail_specs();
		$specs = apply_filters('vms_admin_menu_left_rail_specs', $specs);
		return is_array($specs) ? array_values($specs) : array();
	}
}

if (!function_exists('vms_admin_menu_section_label')) {
	function vms_admin_menu_section_label(string $section): string
	{
		$section = sanitize_key($section);
		$sections = vms_admin_menu_sections();
		if (isset($sections[$section]['label']) && is_string($sections[$section]['label'])) {
			return vms_safe_label($sections[$section]['label'], vms_admin_menu_default_label_from_slug($section, 'Other / Unclassified'));
		}
		return vms_i18n_runtime('Other / Unclassified', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_admin_menu_cluster_for_section')) {
	function vms_admin_menu_cluster_for_section(string $section): string
	{
		$section = sanitize_key($section);
		$sections = vms_admin_menu_sections();
		if (isset($sections[$section]['cluster']) && is_string($sections[$section]['cluster']) && $sections[$section]['cluster'] !== '') {
			return sanitize_key((string) $sections[$section]['cluster']);
		}
		return 'tools';
	}
}

if (!function_exists('vms_admin_menu_registry')) {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	function vms_admin_menu_registry(): array
	{
		if (!isset($GLOBALS['vms_admin_menu_registry']) || !is_array($GLOBALS['vms_admin_menu_registry'])) {
			$GLOBALS['vms_admin_menu_registry'] = array();
		}

		if (function_exists('vms_admin_menu_boot_registry') && empty($GLOBALS['vms_admin_menu_registry_booted'])) {
			vms_admin_menu_boot_registry();
		}

		return $GLOBALS['vms_admin_menu_registry'];
	}
}

if (!function_exists('vms_admin_menu_left_menu_visible_slugs')) {
	/**
	 * Legacy compatibility shim.
	 *
	 * The compact WordPress left rail is intentionally controlled only by
	 * vms_admin_menu_left_rail_specs(). Individual pages should be discovered
	 * through the VMS top navigation, All VMS Pages, and direct URLs unless they
	 * become a durable section launcher through the left-rail spec filter.
	 *
	 * @return string[]
	 */
	function vms_admin_menu_left_menu_visible_slugs(): array
	{
		return array();
	}
}

if (!function_exists('vms_register_admin_page')) {
	/**
	 * Register a VMS admin page for add-ons and future core pages.
	 *
	 * Minimum recommended fields: slug, menu_title, page_title, section, callback.
	 *
	 * @param array<string,mixed> $args
	 */
	function vms_register_admin_page(array $args): bool
	{
		$slug = isset($args['slug']) ? sanitize_key((string) $args['slug']) : '';
		if ($slug === '' && isset($args['id'])) {
			$slug = sanitize_key((string) $args['id']);
		}
		if ($slug === '') {
			return false;
		}

		$section = isset($args['section']) ? sanitize_key((string) $args['section']) : 'unclassified';
		$sections = vms_admin_menu_sections();
		if ($section === '' || !isset($sections[$section])) {
			$section = 'unclassified';
		}

		$default_label = vms_admin_menu_default_label_from_slug($slug, 'VMS');
		$menu_title = isset($args['menu_title']) ? vms_safe_label($args['menu_title'], '') : '';
		$page_title = isset($args['page_title']) ? vms_safe_label($args['page_title'], '') : '';
		if ($menu_title === '' && $page_title !== '') {
			$menu_title = $page_title;
		}
		if ($page_title === '' && $menu_title !== '') {
			$page_title = $menu_title;
		}
		if ($menu_title === '') {
			$menu_title = $default_label;
		}
		if ($page_title === '') {
			$page_title = $menu_title;
		}

		$entry = array(
			'id' => isset($args['id']) ? sanitize_key((string) $args['id']) : $slug,
			'slug' => $slug,
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => isset($args['capability']) && is_string($args['capability']) && $args['capability'] !== '' ? (string) $args['capability'] : 'manage_options',
			'callback' => $args['callback'] ?? '',
			'section' => $section,
			'order' => isset($args['order']) ? (int) $args['order'] : 100,
			'source' => isset($args['source']) && is_string($args['source']) && $args['source'] !== '' ? sanitize_text_field((string) $args['source']) : 'vms-core',
			'left_menu' => array_key_exists('left_menu', $args) ? (bool) $args['left_menu'] : false,
			'top_nav' => array_key_exists('top_nav', $args) ? (bool) $args['top_nav'] : true,
			'directory' => array_key_exists('directory', $args) ? (bool) $args['directory'] : true,
			'shell' => array_key_exists('shell', $args) ? (bool) $args['shell'] : false,
			'register' => array_key_exists('register', $args) ? (bool) $args['register'] : true,
			'external_url' => isset($args['external_url']) && is_string($args['external_url']) ? (string) $args['external_url'] : '',
			'description' => isset($args['description']) ? vms_safe_label($args['description'], '') : '',
			'badge_callback' => $args['badge_callback'] ?? null,
		);

		$GLOBALS['vms_admin_menu_registry'][$slug] = $entry;
		return true;
	}
}

if (!function_exists('vms_admin_menu_page_exists')) {
	function vms_admin_menu_page_exists(string $slug): bool
	{
		global $submenu;
		$slug = (string) $slug;
		if ($slug === '' || !isset($submenu[vms_admin_menu_parent_slug()]) || !is_array($submenu[vms_admin_menu_parent_slug()])) {
			return false;
		}
		foreach ($submenu[vms_admin_menu_parent_slug()] as $item) {
			if (is_array($item) && isset($item[2]) && (string) $item[2] === $slug) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('vms_admin_menu_boot_registry')) {
	function vms_admin_menu_boot_registry(): void
	{
		if (!empty($GLOBALS['vms_admin_menu_registry_booted'])) {
			return;
		}
		$GLOBALS['vms_admin_menu_registry_booted'] = true;

		if (!isset($GLOBALS['vms_admin_menu_registry']) || !is_array($GLOBALS['vms_admin_menu_registry'])) {
			$GLOBALS['vms_admin_menu_registry'] = array();
		}

		vms_register_admin_page(array(
			'id' => 'admin_pages',
			'slug' => 'vms-admin-pages',
			'page_title' => vms_i18n_runtime('All VMS Pages', 'backstage-venue-manager'),
			'menu_title' => vms_i18n_runtime('All VMS Pages', 'backstage-venue-manager'),
			'section' => 'tools_integrity',
			'capability' => 'manage_options',
			'callback' => 'vms_admin_menu_render_page_directory',
			'order' => 995,
			'source' => 'vms-core',
			'description' => vms_i18n_runtime('Discoverable directory and health check for VMS core and add-on admin pages.', 'backstage-venue-manager'),
			'left_menu' => true,
		));

		/**
		 * Add-ons should register VMS pages here using vms_register_admin_page().
		 */
		do_action('vms_admin_register_pages');
	}
}
add_action('admin_menu', 'vms_admin_menu_boot_registry', 4);

if (!function_exists('vms_admin_menu_register_core_page_metadata')) {
	/**
	 * Catalog existing core/admin/module pages in the registry without changing
	 * their current direct add_submenu_page() callbacks yet.
	 *
	 * The registry becomes the source of discovery and section metadata while
	 * legacy direct page registration continues to own actual rendering until
	 * each feature can be migrated safely in smaller passes.
	 */
	function vms_admin_menu_register_core_page_metadata(): void
	{
		$entries = array(
			array('vms-dashboard', 'Dashboard', 'dashboard', 10, 'vms-core', 'Main VMS operational overview and quick actions.', true),
			array('vms-dashboard-operations', 'Dashboard: Operations', 'dashboard', 20, 'vms-core'),
			array('vms-dashboard-finance', 'Dashboard: Finance', 'dashboard', 30, 'vms-core'),
			array('vms-dashboard-health', 'Dashboard: Onboarding & Health', 'dashboard', 40, 'vms-core'),
			array('vms-approvals', 'Approvals', 'dashboard', 50, 'vms-core'),
			array('vms-due-dates', 'Due Dates', 'dashboard', 60, 'vms-core'),

			array('vms-schedule', 'Schedule', 'events_schedule', 10, 'vms-core', '', true),
			array('vms-event-command-center', 'Event Command Center', 'events_schedule', 20, 'vms-core', '', false, true),
			array('vms-season-dates', 'Season Dates', 'events_schedule', 30, 'vms-core'),
			array('vms-holidays', 'Holidays', 'events_schedule', 40, 'vms-core'),

			array('vms-ticket-integrity', 'Ticket Integrity', 'tickets_admissions', 10, 'vms-core', 'Ticketing integrity monitor and repair tools.'),
			array('vms-passes', 'Guest Passes', 'tickets_admissions', 20, 'vms-core'),
			array('vms-verifications', 'Eligibility Approvals', 'tickets_admissions', 30, 'vms-core'),
			array('vms-credential-claims', 'Credential Claims', 'tickets_admissions', 40, 'vms-core'),

			array('vms-vendor-command-center', 'Vendor Command Center', 'vendors_staff', 10, 'vms-core', '', true),
			array('vms-vendor-availability', 'Vendor Availability', 'vendors_staff', 20, 'vms-core'),
			array('vms-staffing-templates', 'Staffing Templates', 'vendors_staff', 40, 'vms-core'),
			array('vms-staffing-rollups', 'Staffing Rollups', 'vendors_staff', 50, 'vms-core'),
			array('vms-tasks', 'Staff Tasks', 'vendors_staff', 60, 'vms-core'),
			array('vms-task-templates', 'Task Templates', 'vendors_staff', 70, 'vms-core'),
			array('vms-checklist-templates', 'Checklist Templates', 'vendors_staff', 80, 'vms-core'),
			array('vms-task-settings', 'Task Settings', 'vendors_staff', 90, 'vms-core'),
			array('vms-my-tasks', 'My Tasks', 'vendors_staff', 100, 'vms-core'),
			array('vms-teams', 'Teams', 'vendors_staff', 110, 'vms-ops'),
			array('vms-alert-presets', 'Alert Presets', 'vendors_staff', 120, 'vms-ops'),

			array('vms-marketing-social', 'Marketing & Social', 'marketing_sales', 10, 'vms-core', '', true),
			array('vms-social-sharing', 'Social Sharing', 'marketing_sales', 20, 'vms-core'),
			array('vms-email-followups', 'Email Follow-Ups', 'marketing_sales', 30, 'vms-core'),

			array('vms-budget-calculator', 'Budget Calculator', 'reports_finance', 10, 'vms-core'),
			array('vms-event-profitability', 'Reporting: Event Profitability', 'reports_finance', 20, 'vms-core'),
			array('vms-goals-forecast', 'Goals & Forecasting', 'reports_finance', 30, 'vms-core'),
			array('vms-data-tools', 'Data Tools', 'reports_finance', 40, 'vms-data-tools', 'Data Tools bridge page when the companion Data Tools plugin is active or available.'),

			array('vms-integrity-venue-links', 'Integrity: Venue Links', 'venue_setup', 20, 'vms-core'),
			array('vms-integrity-calendar-links', 'Integrity: Calendar Links', 'venue_setup', 30, 'vms-core'),

			array('vms-square-sync-protection', 'Square Sync Protection', 'tools_integrity', 10, 'vms-core', 'Firewall/status page for protecting VMS-owned Woo products from accidental Square catalog sync.', false, true),
			array('vms-ops-console', 'VMS Ops Console', 'tools_integrity', 20, 'vms-ops'),
			array('vms-ops-console-hub', 'VMS Ops Console Hub', 'tools_integrity', 25, 'vms-ops'),
			array('vms-add-dispatch', 'ADD Dispatch', 'tools_integrity', 30, 'vms-core'),
			array('vms-import-event-plans', 'Import Event Plans (CSV)', 'tools_integrity', 40, 'vms-core'),
			array('vms-reference-keys-map', 'Reference: Keys + Identifiers', 'tools_integrity', 50, 'vms-core'),
			array('vms-continuity-binder', 'Continuity Binder', 'tools_integrity', 60, 'vms-core'),

			array('vms-settings', 'Settings', 'settings_addons', 10, 'vms-core', '', true),
			array('vms-guided-tours', 'Guided Tours', 'settings_addons', 30, 'vms-core'),
			array('vms-tour-maintenance', 'Tour Maintenance', 'settings_addons', 40, 'vms-core'),
			array('vms-status-notices', 'Status Notices', 'settings_addons', 50, 'vms-core'),
			array('vms-docs', 'Docs', 'settings_addons', 60, 'vms-core'),
		);

		foreach ($entries as $entry) {
			$slug = isset($entry[0]) ? (string) $entry[0] : '';
			$label = isset($entry[1]) ? (string) $entry[1] : $slug;
			if ($slug === '') {
				continue;
			}

			vms_register_admin_page(array(
				'id' => $slug,
				'slug' => $slug,
				'page_title' => $label,
				'menu_title' => $label,
				'capability' => 'manage_options',
				'section' => isset($entry[2]) ? (string) $entry[2] : 'unclassified',
				'order' => isset($entry[3]) ? (int) $entry[3] : 100,
				'source' => isset($entry[4]) ? (string) $entry[4] : 'vms-core',
				'description' => isset($entry[5]) ? (string) $entry[5] : '',
				'left_menu' => isset($entry[6]) ? (bool) $entry[6] : false,
				'top_nav' => isset($entry[7]) ? (bool) $entry[7] : false,
				'directory' => true,
				'register' => false,
			));
		}
	}
}
add_action('vms_admin_register_pages', 'vms_admin_menu_register_core_page_metadata', 5);

if (!function_exists('vms_admin_menu_render_missing_callback_page')) {
	function vms_admin_menu_render_missing_callback_page(): void
	{
		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		echo '<div class="wrap">';
		echo '<h1>' . esc_html(vms_i18n_runtime('VMS Page Unavailable', 'backstage-venue-manager')) . '</h1>';
		echo '<p>' . esc_html(vms_i18n_runtime('This VMS admin page is registered, but its page renderer is not currently available.', 'backstage-venue-manager')) . '</p>';
		if ($page !== '') {
			echo '<p><code>' . esc_html($page) . '</code></p>';
		}
		echo '</div>';
	}
}

if (!function_exists('vms_admin_menu_emit_registered_pages')) {
	function vms_admin_menu_emit_registered_pages(): void
	{
		$pages = vms_admin_menu_registry();
		if (empty($pages)) {
			return;
		}

		uasort($pages, static function (array $a, array $b): int {
			$section_order = vms_admin_menu_sections();
			$a_section = isset($a['section']) ? sanitize_key((string) $a['section']) : 'unclassified';
			$b_section = isset($b['section']) ? sanitize_key((string) $b['section']) : 'unclassified';
			$a_section_order = isset($section_order[$a_section]['order']) ? (int) $section_order[$a_section]['order'] : 999;
			$b_section_order = isset($section_order[$b_section]['order']) ? (int) $section_order[$b_section]['order'] : 999;
			if ($a_section_order !== $b_section_order) {
				return $a_section_order <=> $b_section_order;
			}
			return ((int) ($a['order'] ?? 100)) <=> ((int) ($b['order'] ?? 100));
		});

		foreach ($pages as $entry) {
			if (empty($entry['register'])) {
				continue;
			}

			$slug = isset($entry['slug']) ? sanitize_key((string) $entry['slug']) : '';
			if ($slug === '' || vms_admin_menu_page_exists($slug)) {
				continue;
			}

			$callback = $entry['callback'] ?? '';
			if (!is_callable($callback)) {
				$callback = 'vms_admin_menu_render_missing_callback_page';
			}

			$slug_label = vms_admin_menu_default_label_from_slug($slug, 'VMS');
			$page_title = vms_safe_label($entry['page_title'] ?? '', $slug_label);
			$menu_title = vms_safe_label($entry['menu_title'] ?? '', $page_title);
			$parent = vms_admin_menu_parent_slug();
			add_submenu_page(
				$parent,
				$page_title,
				$menu_title,
				(string) ($entry['capability'] ?? 'manage_options'),
				$slug,
				$callback
			);
		}
	}
}
add_action('admin_menu', 'vms_admin_menu_emit_registered_pages', 98);

if (!function_exists('vms_admin_menu_guess_section')) {
	function vms_admin_menu_guess_section(string $slug, string $label = ''): string
	{
		$slug = strtolower($slug);
		$label_lc = strtolower($label);
		$haystack = $slug . ' ' . $label_lc;

		if ($slug === 'vms-dashboard' || strpos($slug, 'dashboard') !== false || strpos($slug, 'approval') !== false || strpos($slug, 'due-date') !== false) {
			return 'dashboard';
		}
		if (strpos($haystack, 'ticket') !== false || strpos($haystack, 'pass') !== false || strpos($haystack, 'admission') !== false || strpos($haystack, 'verification') !== false || strpos($haystack, 'claim') !== false || strpos($haystack, 'credit') !== false) {
			return 'tickets_admissions';
		}
		if (strpos($haystack, 'schedule') !== false || strpos($haystack, 'event') !== false || strpos($haystack, 'season') !== false || strpos($haystack, 'holiday') !== false) {
			return 'events_schedule';
		}
		if (strpos($haystack, 'vendor') !== false || strpos($haystack, 'staff') !== false || strpos($haystack, 'task') !== false || strpos($haystack, 'team') !== false || strpos($haystack, 'rating') !== false || strpos($haystack, 'comp package') !== false) {
			return 'vendors_staff';
		}
		if (strpos($haystack, 'social') !== false || strpos($haystack, 'marketing') !== false || strpos($haystack, 'meta') !== false || strpos($haystack, 'ads') !== false || strpos($haystack, 'email') !== false || strpos($haystack, 'referral') !== false) {
			return 'marketing_sales';
		}
		if (strpos($haystack, 'budget') !== false || strpos($haystack, 'finance') !== false || strpos($haystack, 'profit') !== false || strpos($haystack, 'goal') !== false || strpos($haystack, 'data tools') !== false || strpos($haystack, 'report') !== false) {
			return 'reports_finance';
		}
		if (strpos($haystack, 'venue') !== false) {
			return 'venue_setup';
		}
		if (strpos($haystack, 'setting') !== false || strpos($haystack, 'addon') !== false || strpos($haystack, 'add-on') !== false || strpos($haystack, 'docs') !== false || strpos($haystack, 'guided') !== false || strpos($haystack, 'status') !== false) {
			return 'settings_addons';
		}
		if (strpos($haystack, 'integrity') !== false || strpos($haystack, 'tool') !== false || strpos($haystack, 'sync') !== false || strpos($haystack, 'import') !== false || strpos($haystack, 'ops') !== false || strpos($haystack, 'reference') !== false || strpos($haystack, 'binder') !== false) {
			return 'tools_integrity';
		}

		return 'unclassified';
	}
}

if (!function_exists('vms_admin_menu_url_for_slug')) {
	function vms_admin_menu_url_for_slug(string $slug): string
	{
		if (strpos($slug, '.php') !== false) {
			return admin_url($slug);
		}
		return admin_url('admin.php?page=' . rawurlencode($slug));
	}
}

if (!function_exists('vms_admin_menu_collect_directory_pages')) {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	function vms_admin_menu_collect_directory_pages(): array
	{
		global $submenu;
		$pages = array();
		$registry = vms_admin_menu_registry();

		foreach ($registry as $slug => $entry) {
			if (empty($entry['directory'])) {
				continue;
			}
			$slug = sanitize_key((string) ($entry['slug'] ?? $slug));
			if ($slug === '') {
				continue;
			}
			$pages[$slug] = array(
				'slug' => $slug,
				'label' => vms_safe_label($entry['menu_title'] ?? '', vms_admin_menu_default_label_from_slug($slug, 'VMS')),
				'page_title' => vms_safe_label($entry['page_title'] ?? '', vms_safe_label($entry['menu_title'] ?? '', vms_admin_menu_default_label_from_slug($slug, 'VMS'))),
				'section' => isset($entry['section']) ? sanitize_key((string) $entry['section']) : 'unclassified',
				'source' => isset($entry['source']) ? (string) $entry['source'] : 'vms-core',
				'url' => isset($entry['external_url']) && is_string($entry['external_url']) && $entry['external_url'] !== '' ? (string) $entry['external_url'] : vms_admin_menu_url_for_slug($slug),
				'left_menu' => !empty($entry['left_menu']),
				'hidden_left_menu' => false,
				'registered_by' => 'registry',
				'callback_missing' => !empty($entry['register']) && !is_callable($entry['callback'] ?? null),
				'description' => isset($entry['description']) ? (string) $entry['description'] : '',
				'duplicates' => 0,
			);
		}

		$parent_slug = vms_admin_menu_parent_slug();
		$visible_menu_items = (isset($submenu[$parent_slug]) && is_array($submenu[$parent_slug]))
			? (array) $submenu[$parent_slug]
			: array();

		$visible_slugs = array();
		foreach ($visible_menu_items as $visible_item) {
			if (is_array($visible_item) && isset($visible_item[2]) && (string) $visible_item[2] !== '') {
				$visible_slugs[(string) $visible_item[2]] = true;
			}
		}

		$all_menu_items = $visible_menu_items;
		if (isset($GLOBALS['vms_admin_menu_all_submenu_items']) && is_array($GLOBALS['vms_admin_menu_all_submenu_items'])) {
			$all_menu_items = (array) $GLOBALS['vms_admin_menu_all_submenu_items'];
		}

		foreach ($all_menu_items as $item) {
			if (!is_array($item) || !isset($item[2])) {
				continue;
			}
			$slug = (string) $item[2];
			if ($slug === '') {
				continue;
			}

			$is_visible = isset($visible_slugs[$slug]);
			$label = isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : $slug;
			$classes = isset($item[4]) && is_string($item[4]) ? (string) $item[4] : '';
			$is_hidden = !$is_visible || (strpos(' ' . $classes . ' ', ' vms-admin-ui-menu-hidden ') !== false);

			if (isset($pages[$slug])) {
				$pages[$slug]['left_menu'] = $is_visible;
				$pages[$slug]['hidden_left_menu'] = $is_hidden;
				$pages[$slug]['duplicates'] = (int) ($pages[$slug]['duplicates'] ?? 0) + 1;
				continue;
			}

			$section = vms_admin_menu_guess_section($slug, $label);
			$pages[$slug] = array(
				'slug' => $slug,
				'label' => $label,
				'page_title' => isset($item[3]) ? wp_strip_all_tags((string) $item[3]) : $label,
				'section' => $section,
				'source' => strpos($slug, 'vms-') === 0 || strpos($slug, 'edit.php?post_type=vms_') === 0 ? 'vms-admin-menu' : 'wp-admin-menu',
				'url' => vms_admin_menu_url_for_slug($slug),
				'left_menu' => $is_visible,
				'hidden_left_menu' => $is_hidden,
				'registered_by' => 'wordpress-menu',
				'callback_missing' => false,
				'description' => '',
				'duplicates' => 0,
			);
		}

		uasort($pages, static function (array $a, array $b): int {
			$sections = vms_admin_menu_sections();
			$a_section = sanitize_key((string) ($a['section'] ?? 'unclassified'));
			$b_section = sanitize_key((string) ($b['section'] ?? 'unclassified'));
			$a_order = isset($sections[$a_section]['order']) ? (int) $sections[$a_section]['order'] : 999;
			$b_order = isset($sections[$b_section]['order']) ? (int) $sections[$b_section]['order'] : 999;
			if ($a_order !== $b_order) {
				return $a_order <=> $b_order;
			}
			return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
		});

		return $pages;
	}
}

if (!function_exists('vms_admin_menu_render_page_directory_content')) {
	function vms_admin_menu_render_page_directory_content(): void
	{
		$pages = vms_admin_menu_collect_directory_pages();
		$total = count($pages);
		$hidden = 0;
		$missing_callbacks = 0;
		$unclassified = 0;
		foreach ($pages as $page) {
			$hidden += !empty($page['hidden_left_menu']) ? 1 : 0;
			$missing_callbacks += !empty($page['callback_missing']) ? 1 : 0;
			$unclassified += (isset($page['section']) && $page['section'] === 'unclassified') ? 1 : 0;
		}

		echo '<p>' . esc_html__('This directory is the safety net for VMS core pages and add-ons. A page does not have to appear in the left WordPress menu to remain discoverable here.', 'backstage-venue-manager') . '</p>';
		echo '<div class="vms-admin-menu-health-cards">';
		echo '<div class="vms-admin-menu-health-card"><strong>' . esc_html((string) $total) . '</strong><span>' . esc_html__('Registered/Detected Pages', 'backstage-venue-manager') . '</span></div>';
		echo '<div class="vms-admin-menu-health-card"><strong>' . esc_html((string) $hidden) . '</strong><span>' . esc_html__('Hidden from Left Menu', 'backstage-venue-manager') . '</span></div>';
		echo '<div class="vms-admin-menu-health-card"><strong>' . esc_html((string) $missing_callbacks) . '</strong><span>' . esc_html__('Missing Registry Callbacks', 'backstage-venue-manager') . '</span></div>';
		echo '<div class="vms-admin-menu-health-card"><strong>' . esc_html((string) $unclassified) . '</strong><span>' . esc_html__('Unclassified', 'backstage-venue-manager') . '</span></div>';
		echo '</div>';

		echo '<div class="vms-admin-menu-directory-tools">';
		echo '<label for="vms-admin-menu-directory-search">' . esc_html__('Search VMS pages', 'backstage-venue-manager') . '</label>';
		echo '<input type="search" id="vms-admin-menu-directory-search" class="regular-text" placeholder="' . esc_attr__('Type a page name, section, source, or slug...', 'backstage-venue-manager') . '" data-vms-admin-menu-directory-search>';
		echo '</div>';

		echo '<table class="widefat striped vms-admin-menu-directory" data-vms-admin-menu-directory>';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Page', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Section', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Source', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Menu Status', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Slug', 'backstage-venue-manager') . '</th>';
		echo '</tr></thead><tbody>';

		if (empty($pages)) {
			echo '<tr><td colspan="5">' . esc_html__('No VMS admin pages were detected.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($pages as $page) {
				$label = (string) ($page['label'] ?? $page['slug'] ?? '');
				$url = (string) ($page['url'] ?? '');
				$section = vms_admin_menu_section_label((string) ($page['section'] ?? 'unclassified'));
				$status = __('Visible in left menu', 'backstage-venue-manager');
				if (!empty($page['hidden_left_menu'])) {
					$status = __('Directory/top-nav only', 'backstage-venue-manager');
				} elseif (empty($page['left_menu'])) {
					$status = __('Directory only', 'backstage-venue-manager');
				}
				if (!empty($page['callback_missing'])) {
					$status .= ' - ' . __('callback missing', 'backstage-venue-manager');
				}

				$search_blob = strtolower(trim($label . ' ' . $section . ' ' . (string) ($page['source'] ?? '') . ' ' . (string) ($page['slug'] ?? '') . ' ' . $status));

				echo '<tr data-vms-admin-menu-directory-row data-vms-admin-menu-directory-search-text="' . esc_attr($search_blob) . '">';
				echo '<td><a href="' . esc_url($url) . '"><strong>' . esc_html($label) . '</strong></a>';
				if (!empty($page['description'])) {
					echo '<br><span class="description">' . esc_html((string) $page['description']) . '</span>';
				}
				echo '</td>';
				echo '<td>' . esc_html($section) . '</td>';
				echo '<td>' . esc_html((string) ($page['source'] ?? '')) . '</td>';
				echo '<td>' . esc_html($status) . '</td>';
				echo '<td><code>' . esc_html((string) ($page['slug'] ?? '')) . '</code></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}
}

if (!function_exists('vms_admin_menu_render_page_directory')) {
	function vms_admin_menu_render_page_directory(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array(
					'title' => __('All VMS Pages', 'backstage-venue-manager'),
					'subtitle' => __('Discoverable safety net for VMS core pages, module pages, and add-on admin screens.', 'backstage-venue-manager'),
				),
				'vms_admin_menu_render_page_directory_content'
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('All VMS Pages', 'backstage-venue-manager') . '</h1>';
		vms_admin_menu_render_page_directory_content();
		echo '</div>';
	}
}

if (!function_exists('vms_admin_menu_apply_registry_to_nav_clusters')) {
	/**
	 * @param array<string,array<string,mixed>> $clusters
	 * @return array<string,array<string,mixed>>
	 */
	function vms_admin_menu_apply_registry_to_nav_clusters(array $clusters): array
	{
		$pages = vms_admin_menu_registry();
		if (empty($pages)) {
			return $clusters;
		}

		foreach ($pages as $entry) {
			if (empty($entry['top_nav']) || empty($entry['directory'])) {
				continue;
			}

			$slug = isset($entry['slug']) ? sanitize_key((string) $entry['slug']) : '';
			if ($slug === '') {
				continue;
			}

			$section = isset($entry['section']) ? sanitize_key((string) $entry['section']) : 'unclassified';
			$cluster_key = vms_admin_menu_cluster_for_section($section);
			if (!isset($clusters[$cluster_key]) || !is_array($clusters[$cluster_key])) {
				continue;
			}

			$items = isset($clusters[$cluster_key]['items']) && is_array($clusters[$cluster_key]['items']) ? $clusters[$cluster_key]['items'] : array();
			$url = isset($entry['external_url']) && is_string($entry['external_url']) && $entry['external_url'] !== '' ? (string) $entry['external_url'] : vms_admin_menu_url_for_slug($slug);
			$exists = false;
			foreach ($items as $item) {
				if (!is_array($item) || empty($item['url'])) {
					continue;
				}
				if ((string) $item['url'] === $url || strpos((string) $item['url'], 'page=' . rawurlencode($slug)) !== false || strpos((string) $item['url'], 'page=' . $slug) !== false) {
					$exists = true;
					break;
				}
			}
			if ($exists) {
				continue;
			}

			$items[] = array(
				'label' => vms_safe_label($entry['menu_title'] ?? '', vms_safe_label($entry['page_title'] ?? '', vms_admin_menu_default_label_from_slug($slug, 'VMS'))),
				'url' => $url,
			);
			$clusters[$cluster_key]['items'] = $items;
		}

		return $clusters;
	}
}

if (!function_exists('vms_admin_menu_shell_pages_from_registry')) {
	/**
	 * @param string[] $pages
	 * @return string[]
	 */
	function vms_admin_menu_shell_pages_from_registry(array $pages): array
	{
		foreach (vms_admin_menu_registry() as $entry) {
			if (empty($entry['shell']) || empty($entry['slug'])) {
				continue;
			}
			$pages[] = sanitize_key((string) $entry['slug']);
		}
		return array_values(array_unique(array_filter($pages)));
	}
}
add_filter('vms_admin_ui_shell_pages', 'vms_admin_menu_shell_pages_from_registry', 20);

if (!function_exists('vms_admin_menu_active_cluster_from_registry')) {
	function vms_admin_menu_active_cluster_from_registry(string $cluster, string $page, string $post_type): string
	{
		unset($post_type);
		if ($cluster !== '' || $page === '') {
			return $cluster;
		}
		$registry = vms_admin_menu_registry();
		if (isset($registry[$page]) && is_array($registry[$page])) {
			return vms_admin_menu_cluster_for_section((string) ($registry[$page]['section'] ?? 'unclassified'));
		}
		return $cluster;
	}
}
add_filter('vms_admin_ui_active_cluster', 'vms_admin_menu_active_cluster_from_registry', 20, 3);
