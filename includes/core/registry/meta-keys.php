<?php
defined('ABSPATH') || exit;

/**
 * VMS Meta Keys Registry
 * Single source of truth for all meta keys used by VMS.
 */

function bvmgr_meta_keys(): array
{
	return [
		'vendor' => [
			// Canonical primary contact (single source of truth)
			'primary_email' => '_vms_vendor_primary_email',
			'primary_phone' => '_vms_vendor_primary_phone',

			// Contact (vendor-level channel; may differ from Contact Person fields)
			// Contact Person (specific person; used for admin list contact display)

			// Contact
			'email' => '_vms_vendor_email',
			'phone' => '_vms_vendor_phone',
			'website' => '_vms_vendor_website',

			// The Events Calendar mapping (optional integration)
			'tec_organizer_id' => '_vms_tec_organizer_id',


			// Public Profile (front-end shareable pages)
			'public_profile_enabled'      => '_vms_vendor_public_profile_enabled',
			'public_profile_show_email'   => '_vms_vendor_public_profile_show_email',
			'public_profile_show_phone'   => '_vms_vendor_public_profile_show_phone',
			'public_profile_show_website' => '_vms_vendor_public_profile_show_website',
			'public_profile_show_location'=> '_vms_vendor_public_profile_show_location',

			// Contact Person (explicit)
			'contact_name'  => '_vms_contact_name',
			'contact_email' => '_vms_contact_email',
			'contact_phone' => '_vms_contact_phone',

			// Portal/User linking

			// Manual onboarding outreach log
			'onboarding_last_contacted_at' => '_vms_vendor_onboarding_last_contacted_at',
			'onboarding_last_contacted_by' => '_vms_vendor_onboarding_last_contacted_by',
			'onboarding_last_contact_email' => '_vms_vendor_onboarding_last_contact_email',
			'onboarding_last_contact_subject' => '_vms_vendor_onboarding_last_contact_subject',
			'onboarding_contact_count' => '_vms_vendor_onboarding_contact_count',
			
			// Dual-hat linking (optional): vendor that is also a staff worker
			'linked_staff_id' => '_vms_linked_staff_id',

				// Availability
				'availability' => '_vms_availability',

				// Notes
				'notes_internal' => '_vms_vendor_notes_internal',

				// Legacy/aux pay preference fields on vendor edit
				'fee_min'       => '_vms_fee_min',
				'fee_max'       => '_vms_fee_max',
				'min_show_rate' => '_vms_min_show_rate',

				// Event Plan default compensation profile (vendor-scoped)
					'default_comp_structure'     => '_vms_default_comp_structure',
					'default_flat_fee_amount'    => '_vms_default_flat_fee_amount',
					'default_supporting_flat_fee_amount' => '_vms_default_supporting_flat_fee_amount',
					'default_door_split_percent' => '_vms_default_door_split_percent',
					'default_attendance_bonus_mode' => '_vms_default_attendance_bonus_mode',
					'default_attendance_bonus_start_count' => '_vms_default_attendance_bonus_start_count',
					'default_attendance_bonus_step_size' => '_vms_default_attendance_bonus_step_size',
					'default_attendance_bonus_step_bonus' => '_vms_default_attendance_bonus_step_bonus',
					'default_attendance_bonus_per_ticket_rate' => '_vms_default_attendance_bonus_per_ticket_rate',
					'default_attendance_bonus_max_bonus' => '_vms_default_attendance_bonus_max_bonus',
					'default_comp_package_id'    => '_vms_default_comp_package_id',
					'default_comp_by_venue'      => '_vms_vendor_default_comp_by_venue',
					'default_comp_by_venue_dow'  => '_vms_vendor_default_comp_by_venue_dow',
					'default_commission_percent' => '_vms_default_commission_percent',
					'default_commission_mode'    => '_vms_default_commission_mode',

				// Tax Profile (NO SSN/EIN stored in VMS)
				'payee_legal_name'          => '_vms_payee_legal_name',
				'payee_dba'                 => '_vms_payee_dba',
				'entity_type'               => '_vms_entity_type',
				'tax_profile_type'          => '_vms_tax_type',
				'tax_tin_type'              => '_vms_tax_tin_type',
				'tax_business_or_last_name' => '_vms_tax_last_name',
				'tax_first_name'            => '_vms_tax_first_name',
				'tax_middle_name'           => '_vms_tax_middle_name',
				'tax_suffix'                => '_vms_tax_suffix',
				'tax_attention_to'          => '_vms_vendor_attention_to',
				'tax_email'                 => '_vms_tax_email',
				'tax_phone'                 => '_vms_tax_phone',
				'tax_country'               => '_vms_tax_country',

			// Mailing Address
			'addr1' => '_vms_addr1',
			'addr2' => '_vms_addr2',
			'city'  => '_vms_city',
			'state' => '_vms_state',
			'zip'   => '_vms_zip',

			// W-9 workflow (upload mode)
			'w9_upload_id'           => '_vms_w9_upload_id',
			'w9_upload_storage_kind' => '_vms_w9_upload_storage_kind',
			'w9_received_date'       => '_vms_w9_received_date',

			// W-9 workflow (off-site email modes: QuickBooks/Tax1099)
			'w9_attested_at' => '_vms_w9_external_vendor_attested_at',
			'w9_provider'    => '_vms_w9_offsite_provider',

			// Observed values for tax1099_w9_status (as of YYYY-MM-DD):
			// - requested
			// NOTE: Additional values may be introduced by integrations (Tax1099, QBO).

			// Tax1099 status + sync tracking (off-site provider)
			'tax1099_w9_status'          => '_vms_tax1099_w9_status',
			'tax1099_w9_request_sent_at' => '_vms_tax1099_w9_request_sent_at',
			'tax1099_w9_last_sync_at'    => '_vms_tax1099_w9_last_sync_at',


			// Overall completion stamp (truthy indicator for exports/admin)
			'tax_profile_completed_at' => '_vms_tax_profile_completed_at',
			'tax_admin_confirmed_at'  => '_vms_tax_admin_confirmed_at',
			'tax_admin_confirmed_by'  => '_vms_tax_admin_confirmed_by',

			// Tax compliance bypass (temporary, admin-only override)
			'tax_bypass_enabled' => '_vms_tax_bypass_enabled',
			'tax_bypass_until'   => '_vms_tax_bypass_until',   // YYYY-mm-dd
			'tax_bypass_reason'  => '_vms_tax_bypass_reason',
			'tax_bypass_set_by'  => '_vms_tax_bypass_set_by',  // WP user ID
			'tax_bypass_set_at'  => '_vms_tax_bypass_set_at',  // audit only

		],

		'vendor_application' => [
			'submitted_user_id' => '_vms_app_submitted_user_id',
			'contact_name' => '_vms_app_contact_name',
			'email' => '_vms_app_email',
			'phone' => '_vms_app_phone',
			'location' => '_vms_app_location',
			'website' => '_vms_app_website',
			'confirmation_state' => '_vms_app_confirmation_state',
			'email_confirmed_at' => '_vms_app_email_confirmed_at',
			'review_ready_at' => '_vms_app_review_ready_at',
			'confirmation_last_sent_at' => '_vms_app_confirmation_last_sent_at',
			'confirmation_send_count' => '_vms_app_confirmation_send_count',
			'confirmation_send_window_started_at' => '_vms_app_confirmation_send_window_started_at',
			'confirmation_source' => '_vms_app_confirmation_source',
			'public_lookup_key' => '_vms_app_public_lookup_key',
			'review_ready_notified_at' => '_vms_app_review_ready_notified_at',
			'social_facebook' => '_vms_app_social_facebook',
			'social_instagram' => '_vms_app_social_instagram',
			'social_x' => '_vms_app_social_x',
			'social_tiktok' => '_vms_app_social_tiktok',
			'social_youtube' => '_vms_app_social_youtube',
			'social_spotify' => '_vms_app_social_spotify',
		],

		'staff' => [
			// Worker classification
			'worker_type' => '_vms_staff_worker_type',

			// Dual-hat linking (optional): staff worker that also has a vendor profile
			'linked_vendor_id' => '_vms_linked_vendor_id',

			// Employee packet (W-2) tracking (no SSN stored)
			'employee_w4_received'                => '_vms_employee_w4_received',
			'employee_i9_verified'                => '_vms_employee_i9_verified',
			'employee_direct_deposit_received'    => '_vms_employee_direct_deposit_received',
		],

		'venue' => [
			// The Events Calendar mapping (optional integration)
			'tec_venue_id' => '_vms_tec_venue_id',

			// Physical location (used by Meta Ads targeting and venue syncs)
			'address' => '_vms_address',
			'address_2' => '_vms_address_2',
			'city' => '_vms_city',
			'state' => '_vms_state',
			'zip' => '_vms_zip',
			'country' => '_vms_country',
			'latitude' => '_vms_latitude',
			'longitude' => '_vms_longitude',
		],

		'product' => [
		// Linkage markers for VMS-managed products (tickets and entitlements)
		'event_plan_id' => '_vms_event_plan_id',   // int (Event Plan post ID)
		'tec_event_id'  => '_vms_tec_event_id',    // int (TEC event post ID, when linked)
		'product_role'  => '_vms_product_role',    // string: ga_ticket|entitlement|legacy_ticket|other

			// Ticketing (Phase B v2) entitlement identifier
			'ticketing_entitlement_id'   => '_vms_ticketing_entitlement_id',   // string (stable entitlement id, e.g., ent_abc123)
			'ticketing_verified_program' => '_vms_ticketing_verified_program', // string (legacy single program slug)
			'ticketing_allowed_programs' => '_vms_ticketing_allowed_programs', // csv (program slugs)
			'ticketing_allow_direct_grants' => '_vms_ticketing_allow_direct_grants', // bool-ish string
			'ticketing_claim_grant_type' => '_vms_ticketing_claim_grant_type', // string (event_ticket_eligibility|event_free_admit)
		'ticketing_claims_per_assignee' => '_vms_ticketing_claims_per_assignee', // int (0 = unlimited)
		'ticketing_require_assignee_email' => '_vms_ticketing_require_assignee_email', // bool-ish string
		'ticketing_ratio_rule_enabled' => '_vms_ticketing_ratio_rule_enabled', // bool-ish string
		'ticketing_ratio_rule_max_per_qualifying' => '_vms_ticketing_ratio_rule_max_per_qualifying', // int
		'ticketing_ratio_rule_qualifier_mode' => '_vms_ticketing_ratio_rule_qualifier_mode', // string
		'ticketing_ratio_rule_group' => '_vms_ticketing_ratio_rule_group', // string
		'ticketing_marker_version'   => '_vms_ticketing_marker_version',   // int (marker schema version)
		'ticketing_source_plan_id'   => '_vms_ticketing_source_plan_id',   // int (plan that last wrote the markers)
		'ticketing_source_provider'  => '_vms_ticketing_source_provider',  // string (woo|tec_tickets_woo|square)
		'square_mirror_mode' => '_vms_square_mirror_mode',
		'square_mirror_status' => '_vms_square_mirror_status',
		'square_mirror_item_id' => '_vms_square_mirror_item_id',
		'square_mirror_variation_id' => '_vms_square_mirror_variation_id',
		'square_mirror_category_id' => '_vms_square_mirror_category_id',
		'square_mirror_location_id' => '_vms_square_mirror_location_id',
		'square_mirror_catalog_version' => '_vms_square_mirror_catalog_version',
		'square_mirror_source_hash' => '_vms_square_mirror_source_hash',
		'square_mirror_last_sync_gmt' => '_vms_square_mirror_last_sync_gmt',
		'square_mirror_last_error_code' => '_vms_square_mirror_last_error_code',
		'square_mirror_last_error_message' => '_vms_square_mirror_last_error_message',
		'square_mirror_last_retired_gmt' => '_vms_square_mirror_last_retired_gmt',
		'square_mirror_last_order_stamp_gmt' => '_vms_square_mirror_last_order_stamp_gmt',
		],
		'comp_package' => [
			'venue_id' => '_vms_venue_id',
			'comp_type' => '_vms_comp_type',
			'flat_fee' => '_vms_flat_fee',
			'split_basis' => '_vms_split_basis',
			'split_percent_artist' => '_vms_split_percent_artist',
			'commission_percent' => '_vms_commission_percent',
			'commission_mode' => '_vms_commission_mode',
			'commission_base' => '_vms_commission_base',
			'min_guarantee' => '_vms_min_guarantee',
			'cap_amount' => '_vms_cap_amount',
			'notes' => '_vms_notes',
			'attendance_bonus_mode' => '_vms_attendance_bonus_mode',
			'attendance_bonus_start_count' => '_vms_attendance_bonus_start_count',
			'attendance_bonus_step_size' => '_vms_attendance_bonus_step_size',
			'attendance_bonus_step_bonus' => '_vms_attendance_bonus_step_bonus',
			'attendance_bonus_per_ticket_rate' => '_vms_attendance_bonus_per_ticket_rate',
			'attendance_bonus_max_bonus' => '_vms_attendance_bonus_max_bonus',
		],
			'event_plan' => [
			// Schedule keys (critical)
			'date'     => '_vms_event_date', // YYYY-mm-dd
			'venue_id' => '_vms_venue_id',   // int (venue CPT post ID)
			// CSV import upsert identifier (Event Plan CSV Import)
			'import_key' => '_vms_import_event_key',


			// External integrations (optional)
			'tec_event_id'  => '_vms_tec_event_id',
			'tec_event_url' => '_vms_tec_event_url',
			'square_location_id' => '_vms_square_location_id',
			// Headliner promo video (event-linked media for public event promotion)
			'headliner_promo_video_attachment_id' => '_vms_headliner_promo_video_attachment_id',
			'headliner_promo_video_hidden' => '_vms_headliner_promo_video_hidden',
			'headliner_promo_video_uploaded_at_gmt' => '_vms_headliner_promo_video_uploaded_at_gmt',
			'headliner_promo_video_uploaded_by' => '_vms_headliner_promo_video_uploaded_by',
			'headliner_promo_video_source_type' => '_vms_headliner_promo_video_source_type',
			'headliner_promo_video_external_url' => '_vms_headliner_promo_video_external_url',
			'headliner_promo_video_pending_attachment_id' => '_vms_headliner_promo_video_pending_attachment_id',
			'headliner_promo_video_pending_uploaded_at_gmt' => '_vms_headliner_promo_video_pending_uploaded_at_gmt',
			'headliner_promo_video_pending_uploaded_by' => '_vms_headliner_promo_video_pending_uploaded_by',
			// Operator override: suppress only the calendar-unpublished integrity warning for this plan
			'calendar_unpublished_suppress' => '_vms_calendar_unpublished_suppress',

			'wc_product_map' => '_vms_wc_product_map',

			// Ticketing integration (Phase A)
			// Cached list of Woo ticket product IDs detected for the linked TEC event
			'ticket_product_ids' => '_vms_ticket_product_ids_v1',
			// Cached stats payload (provider, qty_sold, revenue, computed_at_gmt, etc.)
			'ticket_stats'       => '_vms_ticket_stats_v1',
			// Calendar cache field: total tickets sold for calendar visibility contexts.
			'tickets_sold_count' => '_vms_tickets_sold_count',

			// Ticketing integration (Phase B)
			// Mode: none | read_only | vms_managed
			'ticketing_mode'     => '_vms_ticketing_mode_v1',
			// Operator-defined tiers (array)
			'ticket_tiers'       => '_vms_ticket_tiers_v1',
			// Tier mapping + sync status (tier_key => ids, hashes, timestamps)
			'ticket_tier_map'    => '_vms_ticket_tier_map_v1',

// Ticketing integration (Phase B v2 — GA attendance + entitlements + rules)
'ticketing_config_v2' => '_vms_ticketing_config_v2',
'ticketing_sync_v2'   => '_vms_ticketing_sync_v2',
'ticketing_stats_v2'  => '_vms_ticketing_stats_v2',
			// Public sales destination: serenade_range | external (missing = serenade_range)
			'ticketing_sales_mode' => '_vms_ticketing_sales_mode',
			'external_ticket_url' => '_vms_external_ticket_url',
			'external_ticket_provider' => '_vms_external_ticket_provider',
			// Public presentation: serenade_range_produced | hosted_third_party
			'event_relationship' => '_vms_event_relationship',
			'external_event_producer' => '_vms_external_event_producer',
			'external_event_producer_website' => '_vms_external_event_producer_website',
			// Ticketing enabled override: on | off | inherit (missing)
			'ticketing_enabled_override' => '_vms_ticketing_enabled_override',
// One-time snapshot used only when migrating legacy tier-based payloads to v2
'ticketing_migration_snapshot_v1' => '_vms_ticketing_migration_snapshot_v1',
			// Legacy: manual Woo products attached for stats (Woo-only tickets)
			'ticket_manual_product_ids' => '_vms_ticket_manual_product_ids_v1',

			// Optional timing keys (datetime)
			'start_datetime' => '_vms_event_plan_start_datetime',
			'end_datetime'   => '_vms_event_plan_end_datetime',
			'checkin_close_at' => '_checkin_close_at',

			// Status + notes (if you want these centralized too)
			'status'         => '_vms_event_plan_status',
			// Social sharing (Phase 0/1 foundation)
			'do_not_post' => '_vms_social_do_not_post',
			'platform_overrides' => '_vms_social_platform_overrides',
			'template_overrides' => '_vms_social_template_overrides',
			'unpublished_after_post' => '_vms_social_unpublished_after_post',
			'cancel_policy'  => '_vms_cancel_policy',
			'cancel_reason_code' => '_vms_cancel_reason_code',
			'cancel_reason_note' => '_vms_cancel_reason_note',
			'cancel_vendor_message' => '_vms_cancel_vendor_message',
			'cancelled_at_gmt' => '_vms_cancelled_at_gmt',
			'cancelled_by_user_id' => '_vms_cancelled_by_user_id',
			'cancel_job_id' => '_vms_cancel_job_id',
			'cancel_job_state' => '_vms_cancel_job_state',
			'cancel_job_summary' => '_vms_cancel_job_summary',
			'cancel_requires_operator_review' => '_vms_cancel_requires_operator_review',
			'rescheduled_from_plan_id' => '_vms_rescheduled_from_plan_id',
			'rescheduled_to_plan_ids' => '_vms_rescheduled_to_plan_ids',
			'notes_internal' => '_vms_event_plan_notes_internal',
			'notes_public'   => '_vms_event_plan_public_notes',

			// Goals / Forecast / Event Profitability (provider-neutral)
			'forecast_headcount' => '_vms_forecast_headcount',
			'door_sales_mode' => '_vms_door_sales_mode',
			'door_sales_percent' => '_vms_door_sales_percent',
			'door_sales_count' => '_vms_door_sales_count',
			'comp_headcount_forecast' => '_vms_comp_headcount_forecast',
			'true_headcount' => '_vms_true_headcount',
			'comp_headcount_true' => '_vms_comp_headcount_true',
			'concessions_actual_cents' => '_vms_concessions_actual_cents',
			'concessions_actual_source' => '_vms_concessions_actual_source',
			'event_direct_costs_cents' => '_vms_event_direct_costs_cents',
			'event_processing_fees_cents' => '_vms_event_processing_fees_cents',
			'event_actuals_totals' => '_vms_event_actuals_totals',
			'event_actuals_pulled_at_utc' => '_vms_event_actuals_pulled_at_utc',
			'event_actuals_provider' => '_vms_event_actuals_provider',

			// Compensation (for exports/automation)
			'band_vendor_id'  => '_vms_band_vendor_id',
			'lineup_entries_v1' => '_vms_lineup_entries_v1',
			'lineup_entry_vendor_id' => '_vms_lineup_entry_vendor_id',
			'lineup_primary_entry_id' => '_vms_lineup_primary_entry_id',
			'vendor_category_term_ids' => '_vms_vendor_category_term_ids',
			'vendor_category_names' => '_vms_vendor_category_names',
			'vendor_category_snapshot' => '_vms_vendor_category_snapshot',

			// Secondary vendors (non-performer vendors, e.g., food trucks)
			'secondary_vendor_assignments_v1' => '_vms_secondary_vendor_assignments_v1',
			// Canonical list of vendor IDs assigned to this Event Plan (array)
			'secondary_vendor_ids' => '_vms_secondary_vendor_ids',
			// Derived index meta: one row per vendor ID for fast meta_query filtering
			// IMPORTANT: this is NOT the source of truth; it is rebuilt on every save.
			'secondary_vendor_id'  => '_vms_secondary_vendor_id',
			'secondary_vendor_type' => '_vms_secondary_vendor_type',
			'secondary_vendor_unqualified' => '_vms_secondary_vendor_unqualified',
			'secondary_vendor_unqualified_ids' => '_vms_secondary_vendor_unqualified_ids',
			// Per-event per-vendor-type slot limits map (slug => max count).
				'slot_limits' => '_vms_slot_limits',
				'comp_structure'  => '_vms_comp_structure',
				'flat_fee_amount' => '_vms_flat_fee_amount',
				'door_split_percent' => '_vms_door_split_percent',
				'attendance_bonus_mode' => '_vms_attendance_bonus_mode',
				'attendance_bonus_start_count' => '_vms_attendance_bonus_start_count',
				'attendance_bonus_step_size' => '_vms_attendance_bonus_step_size',
				'attendance_bonus_step_bonus' => '_vms_attendance_bonus_step_bonus',
				'attendance_bonus_per_ticket_rate' => '_vms_attendance_bonus_per_ticket_rate',
				'attendance_bonus_max_bonus' => '_vms_attendance_bonus_max_bonus',
				'commission_percent' => '_vms_commission_percent',
				'commission_mode' => '_vms_commission_mode',
				'commission_override_none' => '_vms_commission_override_none',
                'deposit_amount' => '_vms_deposit_amount',
                'deposit_status' => '_vms_deposit_status',
                'deposit_treatment' => '_vms_deposit_treatment',
                'deposit_due_date' => '_vms_deposit_due_date',
                'deposit_paid_date' => '_vms_deposit_paid_date',
                'deposit_notes' => '_vms_deposit_notes',
                'final_payment_timing' => '_vms_final_payment_timing',
                'final_payment_days_after' => '_vms_final_payment_days_after',
                'final_payment_date' => '_vms_final_payment_date',
                'final_payment_custom_text' => '_vms_final_payment_custom_text',
                'final_payment_method' => '_vms_final_payment_method',
                'final_payment_method_other' => '_vms_final_payment_method_other',
				'comp_package_id' => '_vms_comp_package_id',
			'comp_selected_option' => '_vms_comp_selected_option',
			'comp_snapshot' => '_vms_comp_snapshot',
			'vendor_guest_rules' => '_vms_vendor_guest_rules',


			// Pay override acknowledgment (defaults vs draft pay)
			'pay_override_ack' => '_vms_pay_override_ack',
			'pay_override_ack_ts' => '_vms_pay_override_ack_ts',
			'pay_override_ack_user_id' => '_vms_pay_override_ack_user_id',
			'pay_override_ack_default_snapshot' => '_vms_pay_override_ack_default_snapshot',
			'pay_override_ack_actual_snapshot' => '_vms_pay_override_ack_actual_snapshot',

			// Cached computed defaults for venue/date (used for diff + enforcement UX)
				'pay_default_source' => '_vms_pay_default_source',
				'pay_default_structure' => '_vms_pay_default_structure',
				'pay_default_flat_fee_amount' => '_vms_pay_default_flat_fee_amount',
				'pay_default_door_split_percent' => '_vms_pay_default_door_split_percent',
				'pay_default_attendance_bonus_mode' => '_vms_pay_default_attendance_bonus_mode',
				'pay_default_attendance_bonus_start_count' => '_vms_pay_default_attendance_bonus_start_count',
				'pay_default_attendance_bonus_step_size' => '_vms_pay_default_attendance_bonus_step_size',
				'pay_default_attendance_bonus_step_bonus' => '_vms_pay_default_attendance_bonus_step_bonus',
				'pay_default_attendance_bonus_per_ticket_rate' => '_vms_pay_default_attendance_bonus_per_ticket_rate',
				'pay_default_attendance_bonus_max_bonus' => '_vms_pay_default_attendance_bonus_max_bonus',
				'pay_default_holiday_name' => '_vms_pay_default_holiday_name',

			// Low-guarantee structure acknowledgment (highest guaranteed comparison)
			'low_guarantee_ack' => '_vms_low_guarantee_ack',
			'low_guarantee_ack_ts' => '_vms_low_guarantee_ack_ts',
			'low_guarantee_ack_user_id' => '_vms_low_guarantee_ack_user_id',
			'low_guarantee_ack_snapshot' => '_vms_low_guarantee_ack_snapshot',

			// Integrity flags (set when something breaks after publish)
			'integrity_issue'       => '_vms_integrity_issue',
			'integrity_vendor_id'   => '_vms_integrity_vendor_id',
			'integrity_vendor_title'=> '_vms_integrity_vendor_title',
			'integrity_venue_id'    => '_vms_integrity_venue_id',
			'integrity_venue_title' => '_vms_integrity_venue_title',
			'integrity_ts'          => '_vms_integrity_ts',
		],
	];
}

/**
 * Convenience getter: vms_meta_key('vendor', 'email')
 */
function bvmgr_meta_key(string $entity, string $field): string
{
	$map = bvmgr_meta_keys();
	return $map[$entity][$field] ?? '';
}

/**
 * Export helper for Truth Report (stable shape; no duplication elsewhere).
 */
function bvmgr_meta_keys_export(): array
{
	return bvmgr_meta_keys();
}
