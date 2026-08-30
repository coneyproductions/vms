<?php
declare(strict_types=1);

/**
 * Historical official-five BVM runtime contracts.
 *
 * These names are intentionally explicit. The runtime probe validates live PHP
 * declarations after WordPress has loaded the requested plugin order; it does
 * not infer compatibility from source-string matches.
 */
return array(
	'functions' => array(
		'events-slider' => array(
			'vms_calendar_feed_cache_bust',
			'vms_event_plan_get_public_reschedule_destination',
			'vms_event_plan_get_status',
			'vms_get_event_plan_for_tec_event',
			'vms_meta_key',
			'vms_resolve_event_plan_for_tec_event',
			'vms_tec_is_cancelled_event',
			'vms_ticketing_b_meta_key',
			'vms_ticketing_v2_find_plan_id_by_tec_event_id',
		),
		'fill-dates' => array(
			'vms_admin_ui_render_shell',
			'vms_calendar_assignment_status_for_plan',
			'vms_calendar_feed_cache_bust',
			'vms_calendar_get_event_slot_limits',
			'vms_calendar_plan_vendor_ids',
			'vms_calendar_vendor_primary_type',
			'vms_event_plan_get_status',
			'vms_event_plan_review_clean_text',
			'vms_event_plan_review_get_changes',
			'vms_event_plan_review_source_label',
			'vms_event_plan_review_touch',
			'vms_event_plan_set_secondary_vendors',
			'vms_get_calendar_events',
			'vms_meta_key',
			'vms_register_module',
			'vms_render_help_button',
		),
		'data-tools' => array(
			'vms_admin_guard_current_screen_id',
			'vms_admin_guard_request_uri',
			'vms_calculate_attendance_bonus_payout',
			'vms_calendar_get_event_slot_limits',
			'vms_core',
			'vms_event_plan_get_status',
			'vms_event_plan_set_secondary_vendors',
			'vms_event_plan_status_label',
			'vms_event_plan_status_normalize',
			'vms_get_event_plan_comp_terms',
			'vms_get_timezone',
			'vms_meta_key',
			'vms_normalize_email_cell',
			'vms_payables_build_bills_for_export',
			'vms_portal_notice',
			'vms_pretty_structure_label',
			'vms_resource_fingerprint_add_marker',
			'vms_resource_fingerprint_flag',
			'vms_resource_fingerprint_span_finish',
			'vms_resource_fingerprint_span_start',
			'vms_staffing_get_event_slots',
			'vms_staffing_resolve_slot_window',
			'vms_ticket_revenue_available_statuses',
			'vms_ticket_revenue_build_report',
			'vms_ticket_revenue_cents_to_decimal',
			'vms_ticket_revenue_event_key',
			'vms_ticket_revenue_is_valid_ymd',
			'vms_ticket_revenue_normalize_args',
			'vms_ticket_revenue_normalize_ymd',
			'vms_ticket_revenue_wp_now_ymd',
			'vms_vendor_portal_get_count_breakdown',
			'vms_vendor_portal_get_progress_headcount_context',
			'vms_vendor_schema',
		),
		'express-bar' => array(
			'vms_get_event_plan_for_tec_event',
			'vms_resolve_event_plan_for_tec_event',
		),
		'refer-a-friend' => array(
			'vms_admin_ui_render_shell',
			'vms_get_public_event_calendar_url',
			'vms_register_admin_page',
		),
	),
	'classes' => array(
		'events-slider' => array(),
		'fill-dates' => array('VMS_Tours_Service'),
		'data-tools' => array('VMS_Vendor_Schema_Registry'),
		'express-bar' => array(),
		'refer-a-friend' => array(),
	),
	'constants' => array(
		'events-slider' => array('VMS_CALENDAR_FEED_CACHE_BUST_OPTION'),
		'fill-dates' => array(),
		'data-tools' => array(
			'VMS_USER_PRIMARY_VENDOR_META_KEY',
			'VMS_VENDOR_PRIMARY_USER_META_KEY',
			'VMS_VENUE_CPT',
		),
		'express-bar' => array('VMS_PLUGIN_FILE', 'VMS_VERSION'),
		'refer-a-friend' => array(),
	),
	'hooks' => array(
		'events-slider' => array(),
		'fill-dates' => array(
			'vms_admin_ui_active_cluster',
			'vms_admin_ui_nav_clusters',
			'vms_admin_ui_shell_pages',
			'vms_register_tours',
		),
		'data-tools' => array(
			'vms_register_docs_sources',
			'vms_register_tours',
			'vms_vendor_portal_nav_links',
			'vms_vendor_portal_render_custom_tab',
		),
		'express-bar' => array('vms_admin_ui_nav_cluster_items'),
		'refer-a-friend' => array('vms_admin_register_pages'),
	),
	'hook_callbacks' => array(
		'events-slider' => array(),
		'fill-dates' => array(
			'vms_admin_ui_active_cluster' => 'vms_fd_register_active_cluster',
			'vms_admin_ui_nav_clusters' => 'vms_fd_register_vms_admin_nav',
			'vms_admin_ui_shell_pages' => 'vms_fd_register_vms_shell_page',
			'vms_register_tours' => 'vms_fd_register_tours',
		),
		'data-tools' => array(
			'vms_register_docs_sources' => 'vms_dt_register_docs_sources',
			'vms_register_tours' => 'vms_dt_vio_register_tours',
			'vms_vendor_portal_nav_links' => 'vms_dt_vio_vendor_portal_nav_link',
			'vms_vendor_portal_render_custom_tab' => 'vms_dt_vio_vendor_portal_render_custom_tab',
		),
		'express-bar' => array('vms_admin_ui_nav_cluster_items' => 'vmseb_register_planning_nav_items'),
		'refer-a-friend' => array('vms_admin_register_pages' => array('VMS_RAF_Plugin', 'register_vms_admin_pages')),
	),
);
