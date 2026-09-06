<?php
defined('ABSPATH') || exit;

/**
 * VMS Data Tools - Admin Menu
 *
 * Goal:
 * - Register "Data Tools" under WordPress Tools (slug: vms-data-tools)
 * - Keep DT subpage slugs unchanged for deep-link compatibility
 * - Remove any legacy top-level "Data Tools" entry
 */

add_action('admin_menu', 'vms_dt_register_admin_menu', 30);
add_action('admin_menu', 'vms_dt_remove_legacy_top_level_menu', 99);

add_action('admin_init', 'vms_dt_track_recent_tool_visit');

function vms_dt_track_recent_tool_visit(): void
{
    if (!is_admin()) return;
    if (!is_user_logged_in()) return;

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page === '') return;

    // Track only DT tools (not every admin page).
    $known = [
        function_exists('vms_dt_get_menu_slug_events_import') ? vms_dt_get_menu_slug_events_import() : 'vms-dt-events-import',
        function_exists('vms_dt_get_menu_slug_vendor_import') ? vms_dt_get_menu_slug_vendor_import() : 'vms-data-tools-vendor-import',
        function_exists('vms_dt_get_menu_slug_vendor_invites') ? vms_dt_get_menu_slug_vendor_invites() : 'vms-dt-vendor-invites',
        function_exists('vms_dt_get_menu_slug_holidays_import') ? vms_dt_get_menu_slug_holidays_import() : 'vms-dt-holidays-import',
        function_exists('vms_dt_get_menu_slug_payables_export') ? vms_dt_get_menu_slug_payables_export() : 'vms-dt-payables-export',
        function_exists('vms_dt_get_menu_slug_ticket_revenue_export') ? vms_dt_get_menu_slug_ticket_revenue_export() : 'vms-dt-ticket-revenue-export',
        function_exists('vms_dt_get_menu_slug_square_ticket_merge') ? vms_dt_get_menu_slug_square_ticket_merge() : 'vms-dt-square-ticket-merge',
        function_exists('vms_dt_get_menu_slug_revenue_intelligence') ? vms_dt_get_menu_slug_revenue_intelligence() : 'vms-dt-revenue-intelligence',
        function_exists('vms_dt_get_menu_slug_reporting_single_event') ? vms_dt_get_menu_slug_reporting_single_event() : 'vms-dt-report-single-event',
        function_exists('vms_dt_get_menu_slug_reporting_compare_events') ? vms_dt_get_menu_slug_reporting_compare_events() : 'vms-dt-report-compare-events',
        function_exists('vms_dt_get_menu_slug_reporting_season_year') ? vms_dt_get_menu_slug_reporting_season_year() : 'vms-dt-report-season-year',
        function_exists('vms_dt_get_menu_slug_reporting_performer_payouts') ? vms_dt_get_menu_slug_reporting_performer_payouts() : 'vms-dt-report-performer-payouts',
        function_exists('vms_dt_get_menu_slug_reporting_profitability') ? vms_dt_get_menu_slug_reporting_profitability() : 'vms-dt-report-profitability',
        function_exists('vms_dt_get_menu_slug_reporting_ticket_pace') ? vms_dt_get_menu_slug_reporting_ticket_pace() : 'vms-dt-report-ticket-pace',
    ];

    if (!in_array($page, $known, true)) return;

    $user_id = get_current_user_id();
    if (!$user_id) return;

    $key = 'vms_dt_recent_tools';
    $existing = get_user_meta($user_id, $key, true);
    $existing = is_array($existing) ? $existing : [];

    // Store as slug => timestamp (most recent wins)
    $existing[$page] = time();

    // Keep only the 5 most recent
    arsort($existing);
    $existing = array_slice($existing, 0, 5, true);

    update_user_meta($user_id, $key, $existing);
}

function vms_dt_get_recent_tools(): array
{
    if (!is_user_logged_in()) return [];

    $user_id = get_current_user_id();
    if (!$user_id) return [];

    $key = 'vms_dt_recent_tools';
    $recent = get_user_meta($user_id, $key, true);
    $recent = is_array($recent) ? $recent : [];

    arsort($recent);
    return $recent;
}

function vms_dt_register_admin_menu(): void
{
    if (!vms_dt_current_user_can_manage_tools()) {
        return;
    }

    $cap = vms_dt_manage_capability();
    $primary_parent = apply_filters('vms_dt_admin_parent_slug', 'tools.php');
    $primary_parent = sanitize_text_field((string) $primary_parent);
    if ($primary_parent === '') {
        $primary_parent = 'tools.php';
    }

    $parents = array_values(array_unique(array_filter(array(
        $primary_parent,
        'tools.php',
    ))));

    foreach ($parents as $parent) {
        add_submenu_page(
            $parent,
            __('VMS Data Tools', 'vms-data-tools'),
            __('VMS Data Tools', 'vms-data-tools'),
            $cap,
            'vms-data-tools',
            'vms_dt_render_tools_home'
        );
    }

    // Register known DT pages (only if their register functions exist)
    if (function_exists('vms_dt_register_events_import_page')) {
        vms_dt_register_events_import_page();
    }

    // Vendor Import
    if (function_exists('vms_dt_register_vendor_import_page')) {
        vms_dt_register_vendor_import_page();
    }

    // Vendor Invites
    if (function_exists('vms_dt_register_vendor_invites_page')) {
        vms_dt_register_vendor_invites_page();
    }

    // Upload Holidays
    if (function_exists('vms_dt_register_holidays_import_menu')) {
        vms_dt_register_holidays_import_menu();
    }

    // Payables Export
    if (function_exists('vms_dt_register_payables_export_page')) {
        vms_dt_register_payables_export_page();
    }

    // Ticket Revenue Export
    if (function_exists('vms_dt_register_ticket_revenue_export_page')) {
        vms_dt_register_ticket_revenue_export_page();
    }

    // Square + Ticket Merge
    if (function_exists('vms_dt_register_square_ticket_merge_page')) {
        vms_dt_register_square_ticket_merge_page();
    }

    if (function_exists('vms_dt_register_reporting_pages')) {
        vms_dt_register_reporting_pages();
    }

    if (function_exists('vms_dt_register_revenue_intelligence_page')) {
        vms_dt_register_revenue_intelligence_page();
    }

    // If/when these exist, they’ll appear under Data Tools automatically:
    // if (function_exists('vms_dt_register_vendor_import_page')) { vms_dt_register_vendor_import_page(); }
    // if (function_exists('vms_dt_register_holidays_import_page')) { vms_dt_register_holidays_import_page(); }
    // if (function_exists('vms_dt_register_keys_identifiers_page')) { vms_dt_register_keys_identifiers_page(); }
    // if (function_exists('vms_dt_register_docs_page')) { vms_dt_register_docs_page(); }
}

function vms_dt_render_tools_home(): void
{
	if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
		vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_report', array('page' => 'data_tools_home'));
	}
	if (vms_dt_has_core_function('vms_resource_fingerprint_span_start')) {
		vms_dt_call_core_function('vms_resource_fingerprint_span_start', 'dt.tools_home', array('page' => 'vms-data-tools'));
	}

	$default_cap = vms_dt_manage_capability();

	// Base cards (DT core)
	$items = [
		[
			'id'      => 'events_import',
			'group'   => 'importers',
			'title'   => 'Events + Tickets CSV Import',
			'desc'    => 'Import events and tickets using Preview → Commit with validation and reporting.',
			'icon'    => 'dashicons-calendar-alt',
			'type'    => 'tool',
			'slug'    => function_exists('vms_dt_get_menu_slug_events_import') ? vms_dt_get_menu_slug_events_import() : 'vms-dt-events-import',
			'exists'  => function_exists('vms_dt_register_events_import_page'),
			'cap'     => $default_cap,
			'badges'  => ['Importer', 'Preview → Commit', 'Audit trail'],
			'danger'  => true,
			'learn_more_url' => '',
		],
			[
				'id'      => 'vendor_import',
			'group'   => 'importers',
			'title'   => 'Vendor Import (CSV)',
			'desc'    => 'Create and update vendors from CSV using Preview → Commit. Includes row-level results and reports.',
			'icon'    => 'dashicons-groups',
			'type'    => 'tool',
			'slug'    => function_exists('vms_dt_get_menu_slug_vendor_import') ? vms_dt_get_menu_slug_vendor_import() : 'vms-data-tools-vendor-import',
			'exists'  => function_exists('vms_dt_register_vendor_import_page'),
			'cap'     => $default_cap,
			'badges'  => ['Importer', 'Preview → Commit', 'Schema-aligned'],
				'danger'  => true,
				'learn_more_url' => '',
			],
			[
				'id'      => 'vendor_invites',
				'group'   => 'importers',
				'title'   => __('Vendor Invites', 'vms-data-tools'),
				'desc'    => __('Preview and commit vendor claim invites, surface portal opportunities, and manage the vendor interest queue from one Data Tools workflow.', 'vms-data-tools'),
				'icon'    => 'dashicons-email-alt',
				'type'    => 'tool',
				'slug'    => function_exists('vms_dt_get_menu_slug_vendor_invites') ? vms_dt_get_menu_slug_vendor_invites() : 'vms-dt-vendor-invites',
				'exists'  => function_exists('vms_dt_register_vendor_invites_page'),
				'cap'     => $default_cap,
				'badges'  => [__('Invites', 'vms-data-tools'), __('Opportunities', 'vms-data-tools'), __('Claim Tokens', 'vms-data-tools'), __('Audit trail', 'vms-data-tools')],
				'danger'  => true,
				'learn_more_url' => '',
			],
			[
				'id'      => 'holidays_import',
				'group'   => 'importers',
			'title'   => 'Upload Holidays (CSV)',
			'desc'    => 'Add holidays safely with strict validation. Designed to avoid destructive overwrites.',
				'icon'    => 'dashicons-palmtree',
				'type'    => 'tool',
				'slug'    => function_exists('vms_dt_get_menu_slug_holidays_import') ? vms_dt_get_menu_slug_holidays_import() : 'vms-dt-holidays-import',
				'exists'  => function_exists('vms_dt_register_holidays_import_menu'),
				'cap'     => $default_cap,
				'badges'  => ['Importer', 'Venue-aware', 'Safe defaults'],
			'danger'  => true,
			'learn_more_url' => '',
		],
		[
			'id'      => 'payables_export',
			'title'   => 'Payables Export',
			'desc'    => 'Generate QBO Bills CSV for contractor payables (importer-friendly).',
			'slug'    => function_exists('vms_dt_get_menu_slug_payables_export') ? vms_dt_get_menu_slug_payables_export() : 'vms-dt-payables-export',
			'group'   => 'exports',
			'icon'    => 'money',
		],

		[
			'id'      => 'ticket_revenue_export',
			'title'   => 'Ticket Revenue Export',
			'desc'    => 'Build an earned-vs-deferred ledger for event-linked Woo + TEC ticket revenue.',
			'slug'    => function_exists('vms_dt_get_menu_slug_ticket_revenue_export') ? vms_dt_get_menu_slug_ticket_revenue_export() : 'vms-dt-ticket-revenue-export',
			'group'   => 'exports',
			'icon'    => 'chart-bar',
			'type'    => 'tool',
			'exists'  => function_exists('vms_dt_register_ticket_revenue_export_page'),
			'cap'     => $default_cap,
			'badges'  => ['Export', 'Deferred revenue', 'Woo + TEC'],
		],
		[
			'id'      => 'square_ticket_merge',
			'title'   => 'Square + Ticket Merge',
			'desc'    => 'Overlay Square payout gross, fees, net deposits, and timing on top of ticket revenue truth.',
			'slug'    => function_exists('vms_dt_get_menu_slug_square_ticket_merge') ? vms_dt_get_menu_slug_square_ticket_merge() : 'vms-dt-square-ticket-merge',
			'group'   => 'exports',
			'icon'    => 'money-alt',
			'type'    => 'tool',
			'exists'  => function_exists('vms_dt_register_square_ticket_merge_page'),
			'cap'     => $default_cap,
			'badges'  => ['Reconciliation', 'Square payouts', 'Deposits + fees'],
		],
		[
			'id'      => 'report_single_event',
			'title'   => 'Single Event Report',
			'desc'    => 'Clean one-night summary: ticket sales, on-site sales, Square processor cash, website attribution, known costs, bonus, and coverage tests.',
			'slug'    => function_exists('vms_dt_get_menu_slug_reporting_single_event') ? vms_dt_get_menu_slug_reporting_single_event() : 'vms-dt-report-single-event',
			'group'   => 'exports',
			'icon'    => 'chart-pie',
			'type'    => 'tool',
			'exists'  => function_exists('vms_dt_register_reporting_pages'),
			'cap'     => $default_cap,
			'badges'  => ['Summary', 'Tickets', 'Costs'],
		],
		[
			'id'      => 'report_compare_events',
			'title'   => 'Compare Events',
			'desc'    => 'Side-by-side comparisons for events, ticket mix, on-site sales, and known direct costs.',
			'slug'    => function_exists('vms_dt_get_menu_slug_reporting_compare_events') ? vms_dt_get_menu_slug_reporting_compare_events() : 'vms-dt-report-compare-events',
			'group'   => 'exports',
			'icon'    => 'chart-line',
			'type'    => 'tool',
			'exists'  => function_exists('vms_dt_register_reporting_pages'),
			'cap'     => $default_cap,
			'badges'  => ['Compare', 'Side-by-side'],
		],
		[
			'id'      => 'report_season_year',
			'title'   => 'Season / Year',
			'desc'    => 'Leaderboard and trend views across the selected date range with known-cost net snapshots.',
			'slug'    => function_exists('vms_dt_get_menu_slug_reporting_season_year') ? vms_dt_get_menu_slug_reporting_season_year() : 'vms-dt-report-season-year',
			'group'   => 'exports',
			'icon'    => 'calendar-alt',
			'type'    => 'tool',
			'exists'  => function_exists('vms_dt_register_reporting_pages'),
			'cap'     => $default_cap,
			'badges'  => ['Trend', 'Leaderboard'],
		],
		[
			'id'      => 'report_performer_payouts',
			'title'   => 'Performer Payouts',
			'desc'    => 'Band-facing payout view: ticket sales, ticket counts, bonus thresholds, and estimated payout.',
			'slug'    => function_exists('vms_dt_get_menu_slug_reporting_performer_payouts') ? vms_dt_get_menu_slug_reporting_performer_payouts() : 'vms-dt-report-performer-payouts',
			'group'   => 'exports',
			'icon'    => 'groups',
			'type'    => 'tool',
			'exists'  => function_exists('vms_dt_register_reporting_pages'),
			'cap'     => $default_cap,
			'badges'  => ['Payout', 'Bonus'],
		],
		[
			'id'      => 'report_profitability',
			'title'   => 'Event Profitability',
			'desc'    => 'Mobile-friendly event list for quick good / decent / bust reads across tickets, concessions, labor OH, and night score.',
			'slug'    => function_exists('vms_dt_get_menu_slug_reporting_profitability') ? vms_dt_get_menu_slug_reporting_profitability() : 'vms-dt-report-profitability',
			'group'   => 'exports',
			'icon'    => 'money-alt',
			'type'    => 'tool',
			'exists'  => function_exists('vms_dt_register_reporting_pages'),
			'cap'     => $default_cap,
			'badges'  => ['Coverage', 'Known costs'],
		],

		[
			'id'      => 'report_ticket_pace',
			'title'   => 'Ticket Pace',
			'desc'    => 'Track website ticket sales N days out so you can judge pacing a month out, a week out, the day before, and on show day.',
			'slug'    => function_exists('vms_dt_get_menu_slug_reporting_ticket_pace') ? vms_dt_get_menu_slug_reporting_ticket_pace() : 'vms-dt-report-ticket-pace',
			'group'   => 'exports',
			'icon'    => 'chart-line',
			'type'    => 'tool',
			'exists'  => function_exists('vms_dt_register_reporting_pages'),
			'cap'     => $default_cap,
			'badges'  => ['Pacing', 'N days out', 'Forecasting'],
		],
		[
			'id'      => 'revenue_intelligence',
			'title'   => 'Audit Tools',
			'desc'    => 'Dense diagnostics, reconciliation, overlap review, mapper, and raw audit tools for when a number still needs proof.',
			'slug'    => function_exists('vms_dt_get_menu_slug_revenue_intelligence') ? vms_dt_get_menu_slug_revenue_intelligence() : 'vms-dt-revenue-intelligence',
			'group'   => 'exports',
			'icon'    => 'chart-bar',
			'type'    => 'tool',
			'exists'  => function_exists('vms_dt_register_revenue_intelligence_page'),
			'cap'     => $default_cap,
			'badges'  => ['Audit', 'Mapper', 'Diagnostics'],
		],


		// Example add-on placeholder
		[
			'id'      => 'accounting_sync',
			'group'   => 'addons',
			'title'   => 'Accounting Sync (QuickBooks, Tax1099)',
			'desc'    => 'Sync vendor payments, tax info, and reporting with accounting providers.',
			'icon'    => 'dashicons-chart-line',
			'type'    => 'addon',
			'slug'    => '',
			'exists'  => false,
			'cap'     => $default_cap,
			'badges'  => ['Available add-on', 'Payments', '1099-ready'],
			'danger'  => false,
			'learn_more_url' => '',
		],
	];

	/**
	 * Allow add-ons to inject cards on the Data Tools landing page.
	 */
	$items = apply_filters('vms_dt_tools_cards', $items);

	// Organize into groups
	$groups = [
		'importers' => [
			'title' => 'Importers',
			'desc'  => 'These tools modify data. Use Preview → Commit and review results carefully.',
		],
		'exports' => [
			'title' => 'Exports',
			'desc'  => 'These tools generate files for external systems (accounting, payroll, reporting).',
		],
		'addons' => [
			'title' => 'Available Add-Ons',
			'desc'  => 'Optional extensions can add new workflows and integrations.',
		],
	];

	$grouped = [
		'importers' => [],
		'exports' => [],
		'addons' => [],
		'other' => [],
	];

	foreach ((array) $items as $it) {
		$g = isset($it['group']) ? (string) $it['group'] : 'other';
		if (!isset($grouped[$g])) $g = 'other';
		$grouped[$g][] = $it;
	}

	echo '<div class="wrap">';
	echo '<h1>Data Tools</h1>';
	echo '<p>Importers, exports, and admin utilities live here.</p>';

	echo '<div class="vms-dt-callout">';
	echo '<strong>Safety first</strong>';
	echo '<div>Most tools use <em>Preview → Commit</em>. Always preview before committing and keep exports/backups for anything money-related.</div>';
	echo '</div>';

	// Recently used (by user)
	$recent = vms_dt_get_recent_tools();
	if (!empty($recent)) {
		echo '<div class="vms-dt-section">';
		echo '<h2>Recently used</h2>';
		echo '<p class="vms-dt-section-desc">Quick links to your most recently opened tools.</p>';
		echo '<div class="vms-dt-recent">';

			foreach ($recent as $slug => $ts) {
				$label = $slug;
				if (function_exists('vms_dt_get_menu_slug_events_import') && $slug === vms_dt_get_menu_slug_events_import()) $label = 'Events Import';
				if (function_exists('vms_dt_get_menu_slug_vendor_import') && $slug === vms_dt_get_menu_slug_vendor_import()) $label = 'Vendor Import';
				if (function_exists('vms_dt_get_menu_slug_vendor_invites') && $slug === vms_dt_get_menu_slug_vendor_invites()) $label = __('Vendor Invites', 'vms-data-tools');
				if (function_exists('vms_dt_get_menu_slug_holidays_import') && $slug === vms_dt_get_menu_slug_holidays_import()) $label = 'Upload Holidays';
				if (function_exists('vms_dt_get_menu_slug_payables_export') && $slug === vms_dt_get_menu_slug_payables_export()) $label = 'Payables Export';
				if (function_exists('vms_dt_get_menu_slug_ticket_revenue_export') && $slug === vms_dt_get_menu_slug_ticket_revenue_export()) $label = 'Ticket Revenue Export';
				if (function_exists('vms_dt_get_menu_slug_square_ticket_merge') && $slug === vms_dt_get_menu_slug_square_ticket_merge()) $label = 'Square + Ticket Merge';
				if (function_exists('vms_dt_get_menu_slug_revenue_intelligence') && $slug === vms_dt_get_menu_slug_revenue_intelligence()) $label = 'Audit Tools';
			if (function_exists('vms_dt_get_menu_slug_reporting_single_event') && $slug === vms_dt_get_menu_slug_reporting_single_event()) $label = 'Single Event';
			if (function_exists('vms_dt_get_menu_slug_reporting_compare_events') && $slug === vms_dt_get_menu_slug_reporting_compare_events()) $label = 'Compare Events';
			if (function_exists('vms_dt_get_menu_slug_reporting_season_year') && $slug === vms_dt_get_menu_slug_reporting_season_year()) $label = 'Season / Year';
			if (function_exists('vms_dt_get_menu_slug_reporting_performer_payouts') && $slug === vms_dt_get_menu_slug_reporting_performer_payouts()) $label = 'Performer Payouts';
			if (function_exists('vms_dt_get_menu_slug_reporting_profitability') && $slug === vms_dt_get_menu_slug_reporting_profitability()) $label = 'Event Profitability';

			echo '<a class="button" href="' . esc_url(vms_dt_admin_url((string) $slug)) . '">' . esc_html($label) . '</a>';
		}

		echo '</div>';
		echo '</div>';
	}

	// Render groups in order
	foreach (['importers', 'exports', 'addons'] as $gkey) {
		$list = isset($grouped[$gkey]) ? $grouped[$gkey] : [];
		if (empty($list)) continue;

		$gt = isset($groups[$gkey]['title']) ? $groups[$gkey]['title'] : 'Tools';
		$gd = isset($groups[$gkey]['desc']) ? $groups[$gkey]['desc'] : '';

		echo '<div class="vms-dt-section">';
		echo '<h2>' . esc_html($gt) . '</h2>';
		if ($gd !== '') {
			echo '<p class="vms-dt-section-desc">' . esc_html($gd) . '</p>';
		}

		echo '<div class="vms-dt-grid">';

		foreach ((array) $list as $it) {
			$title = isset($it['title']) ? (string) $it['title'] : '';
			$desc  = isset($it['desc']) ? (string) $it['desc'] : '';
			$icon  = isset($it['icon']) ? (string) $it['icon'] : 'dashicons-admin-tools';
			$type  = isset($it['type']) ? (string) $it['type'] : 'tool';
			$slug  = isset($it['slug']) ? (string) $it['slug'] : '';
			$exists = !empty($it['exists']);
			$cap   = isset($it['cap']) ? (string) $it['cap'] : $default_cap;
			$badges = isset($it['badges']) && is_array($it['badges']) ? $it['badges'] : [];
			$danger = !empty($it['danger']);
			$learn_more_url = isset($it['learn_more_url']) ? (string) $it['learn_more_url'] : '';

			// Permission-based hiding for tools
			if ($type === 'tool' && !current_user_can($cap)) {
				continue;
			}

			$card_cls = 'vms-dt-card';
			if ($danger && $type === 'tool') $card_cls .= ' vms-dt-card-danger';

			$card_title_attr = '';
			if ($danger && $type === 'tool') {
				$card_title_attr = ' title="' . esc_attr('Uses Preview → Commit. Review the preview carefully before committing.') . '"';
			} elseif ($type === 'addon') {
				$card_title_attr = ' title="' . esc_attr('Not installed. This is an available add-on.') . '"';
			}

			echo '<div class="' . esc_attr($card_cls) . '"' . $card_title_attr . '>';
			echo '<div class="vms-dt-card-top">';
			echo '<span class="dashicons ' . esc_attr($icon) . ' vms-dt-icon" aria-hidden="true"></span>';
			echo '<div>';
			echo '<p class="vms-dt-title"><strong>' . esc_html($title) . '</strong></p>';
			echo '<p class="vms-dt-desc">' . esc_html($desc) . '</p>';
			echo '</div>';
			echo '</div>';

			// Badges
			if (!empty($badges)) {
				echo '<div class="vms-dt-meta">';
				foreach ($badges as $b) {
					$label = (string) $b;
					$cls = 'vms-dt-pill';
					if ($danger && (stripos($label, 'Preview') !== false || stripos($label, 'Commit') !== false || stripos($label, 'Importer') !== false)) {
						$cls .= ' vms-dt-pill-warn';
					}
					echo '<span class="' . esc_attr($cls) . '">' . esc_html($label) . '</span>';
				}
				echo '</div>';
			}

			// Actions pinned to bottom via CSS
			echo '<div class="vms-dt-actions">';

			if ($type === 'tool' && $exists && $slug !== '') {
				echo '<a class="button button-primary" href="' . esc_url(vms_dt_admin_url($slug)) . '">Open</a>';
			} else {
				echo '<span class="button disabled" aria-disabled="true">Available Add-On</span>';
			}

			if ($learn_more_url !== '') {
				echo '<a class="button" href="' . esc_url($learn_more_url) . '" target="_blank" rel="noopener">Learn more</a>';
			}

			echo '</div>';

			// Footer note for dangerous tools
			if ($danger && $type === 'tool') {
				echo '<div class="vms-dt-card-footer">Preview first. Commit only when the preview results look correct.</div>';
			}

			echo '</div>';
		}

		echo '</div>'; // grid
		echo '</div>'; // section
	}

	/**
	 * Allow add-ons to render extra content under the cards grid.
	 */
	do_action('vms_dt_tools_home_render_after');

	echo '</div>'; // wrap
	if (vms_dt_has_core_function('vms_resource_fingerprint_span_finish')) {
		vms_dt_call_core_function('vms_resource_fingerprint_span_finish', 'dt.tools_home', array('page' => 'vms-data-tools'));
	}
}



function vms_dt_remove_legacy_top_level_menu(): void
{
    remove_menu_page('vms-data-tools');
}
