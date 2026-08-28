<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_nonce_legacy_action')) {
	function bvmgr_nonce_legacy_action(string $canonical_action): string
	{
		return str_starts_with($canonical_action, 'bvmgr_')
			? 'vms_' . substr($canonical_action, 6)
			: $canonical_action;
	}
}

if (!function_exists('bvmgr_verify_nonce_compat')) {
	/** @return int|false */
	function bvmgr_verify_nonce_compat($nonce, $canonical_action)
	{
		$result = wp_verify_nonce($nonce, $canonical_action);
		if ($result !== false || !is_string($canonical_action)) {
			return $result;
		}

		$legacy_action = bvmgr_nonce_legacy_action($canonical_action);
		return $legacy_action !== $canonical_action
			? wp_verify_nonce($nonce, $legacy_action)
			: false;
	}
}

if (!function_exists('bvmgr_nonce_request_value')) {
	function bvmgr_nonce_request_value(string $key): string
	{
		if (!isset($_REQUEST[$key]) || is_array($_REQUEST[$key])) {
			return '';
		}
		return (string) wp_unslash($_REQUEST[$key]);
	}
}

if (!function_exists('bvmgr_check_admin_referer_compat')) {
	/** @return int|false */
	function bvmgr_check_admin_referer_compat($canonical_action = -1, $query_arg = '_wpnonce')
	{
		$nonce = is_string($query_arg) ? bvmgr_nonce_request_value($query_arg) : '';
		$result = bvmgr_verify_nonce_compat($nonce, $canonical_action);
		do_action('check_admin_referer', $canonical_action, $result);
		if ($result === false) {
			wp_nonce_ays($canonical_action);
		}
		return $result;
	}
}

if (!function_exists('bvmgr_check_ajax_referer_compat')) {
	/** @return int|false */
	function bvmgr_check_ajax_referer_compat($canonical_action = -1, $query_arg = false, $stop = true)
	{
		$nonce = '';
		if (is_string($query_arg) && $query_arg !== '') {
			$nonce = bvmgr_nonce_request_value($query_arg);
		} elseif (isset($_REQUEST['_ajax_nonce'])) {
			$nonce = bvmgr_nonce_request_value('_ajax_nonce');
		} elseif (isset($_REQUEST['_wpnonce'])) {
			$nonce = bvmgr_nonce_request_value('_wpnonce');
		}

		$result = bvmgr_verify_nonce_compat($nonce, $canonical_action);
		do_action('check_ajax_referer', $canonical_action, $result);
		if ($stop && $result === false) {
			if (wp_doing_ajax()) {
				wp_die(-1, 403);
			}
			die('-1');
		}
		return $result;
	}
}

if (!function_exists('bvmgr_prefix_b4_nonce_field_map')) {
	/** @return array<string,string> */
	function bvmgr_prefix_b4_nonce_field_map(): array
	{
		return array(
			'vms_admin_heavy_nonce' => '_bvmgr_admin_heavy_nonce',
			'_vms_admin_heavy_nonce' => '_bvmgr_admin_heavy_nonce',
			'_vms_cb_nonce' => '_bvmgr_cb_nonce',
			'_vms_cc_promo_nonce' => '_bvmgr_cc_promo_nonce',
			'_vms_ep_list_nonce' => '_bvmgr_ep_list_nonce',
			'_vms_headliner_promo_video_nonce' => '_bvmgr_headliner_promo_video_nonce',
			'_vms_headliner_promo_video_remove_nonce' => '_bvmgr_headliner_promo_video_remove_nonce',
			'_vms_pass_claim_nonce' => '_bvmgr_pass_claim_nonce',
			'_vms_resync_calendar_nonce' => '_bvmgr_resync_calendar_nonce',
			'_vms_vendor_app_resend_nonce' => '_bvmgr_vendor_app_resend_nonce',
			'_vms_vendor_guest_nonce' => '_bvmgr_vendor_guest_nonce',
			'_vms_vendor_interest_nonce' => '_bvmgr_vendor_interest_nonce',
			'_vms_vendor_link_request_nonce' => '_bvmgr_vendor_link_request_nonce',
			'_vms_vendor_withdraw_nonce' => '_bvmgr_vendor_withdraw_nonce',
			'vms_add_dispatch_assignment_nonce' => 'bvmgr_add_dispatch_assignment_nonce',
			'vms_add_dispatch_nonce' => 'bvmgr_add_dispatch_nonce',
			'vms_avail_nonce' => 'bvmgr_avail_nonce',
			'vms_comp_package_nonce' => 'bvmgr_comp_package_nonce',
			'vms_create_venue_from_template_nonce' => 'bvmgr_create_venue_from_template_nonce',
			'vms_current_venue_nonce' => 'bvmgr_current_venue_nonce',
			'vms_dash_venue_nonce' => 'bvmgr_dash_venue_nonce',
			'vms_employee_packet_nonce' => 'bvmgr_employee_packet_nonce',
			'vms_event_credit_nonce' => 'bvmgr_event_credit_nonce',
			'vms_event_plan_details_nonce' => 'bvmgr_event_plan_details_nonce',
			'vms_express_bar_nonce' => 'bvmgr_express_bar_nonce',
			'vms_express_bar_order_nonce' => 'bvmgr_express_bar_order_nonce',
			'vms_feedback_delete_nonce' => 'bvmgr_feedback_delete_nonce',
			'vms_feedback_nonce' => 'bvmgr_feedback_nonce',
			'vms_feedback_settings_nonce' => 'bvmgr_feedback_settings_nonce',
			'vms_goals_event_finance_nonce' => 'bvmgr_goals_event_finance_nonce',
			'vms_holidays_nonce' => 'bvmgr_holidays_nonce',
			'vms_ics_nonce' => 'bvmgr_ics_nonce',
			'vms_pattern_nonce' => 'bvmgr_pattern_nonce',
			'vms_preview_nonce' => 'bvmgr_preview_nonce',
			'vms_rating_details_nonce' => 'bvmgr_rating_details_nonce',
			'vms_rating_nonce' => 'bvmgr_rating_nonce',
			'vms_season_dates_nonce' => 'bvmgr_season_dates_nonce',
			'vms_social_event_panel_nonce' => 'bvmgr_social_event_panel_nonce',
			'vms_staff_avail_nonce' => 'bvmgr_staff_avail_nonce',
			'vms_staff_certification_nonce' => 'bvmgr_staff_certification_nonce',
			'vms_staff_employee_packet_nonce' => 'bvmgr_staff_employee_packet_nonce',
			'vms_staff_ics_nonce' => 'bvmgr_staff_ics_nonce',
			'vms_staff_pattern_nonce' => 'bvmgr_staff_pattern_nonce',
			'vms_staff_qualifications_nonce' => 'bvmgr_staff_qualifications_nonce',
			'vms_staff_tax_nonce' => 'bvmgr_staff_tax_nonce',
			'vms_staff_user_link_nonce' => 'bvmgr_staff_user_link_nonce',
			'vms_staff_vendor_link_nonce' => 'bvmgr_staff_vendor_link_nonce',
			'vms_staff_worker_type_nonce' => 'bvmgr_staff_worker_type_nonce',
			'vms_tax_admin_nonce' => 'bvmgr_tax_admin_nonce',
			'vms_tax_bypass_nonce' => 'bvmgr_tax_bypass_nonce',
			'vms_techdocs_nonce' => 'bvmgr_techdocs_nonce',
			'vms_vendor_app_decision_nonce' => 'bvmgr_vendor_app_decision_nonce',
			'vms_vendor_apply_nonce' => 'bvmgr_vendor_apply_nonce',
			'vms_vendor_booking_onboarding_nonce' => 'bvmgr_vendor_booking_onboarding_nonce',
			'vms_vendor_command_center_link_nonce' => 'bvmgr_vendor_command_center_link_nonce',
			'vms_vendor_command_center_nonce' => 'bvmgr_vendor_command_center_nonce',
			'vms_vendor_command_center_template_nonce' => 'bvmgr_vendor_command_center_template_nonce',
			'vms_vendor_defaults_nonce' => 'bvmgr_vendor_defaults_nonce',
			'vms_vendor_details_nonce' => 'bvmgr_vendor_details_nonce',
			'vms_vendor_profile_nonce' => 'bvmgr_vendor_profile_nonce',
			'vms_vendor_public_profile_nonce' => 'bvmgr_vendor_public_profile_nonce',
			'vms_vendor_staff_link_nonce' => 'bvmgr_vendor_staff_link_nonce',
			'vms_vendor_tax_nonce' => 'bvmgr_vendor_tax_nonce',
			'vms_vendor_user_links_nonce' => 'bvmgr_vendor_user_links_nonce',
			'vms_venue_comp_defaults_nonce' => 'bvmgr_venue_comp_defaults_nonce',
			'vms_venue_default_times_nonce' => 'bvmgr_venue_default_times_nonce',
			'vms_venue_location_nonce' => 'bvmgr_venue_location_nonce',
			'vms_venue_schedule_nonce' => 'bvmgr_venue_schedule_nonce',
			'vms_venue_template_nonce' => 'bvmgr_venue_template_nonce',
			'vms_verification_allowances_nonce' => 'bvmgr_verification_allowances_nonce',
			'vms_verification_nonce' => 'bvmgr_verification_nonce',
			'vms_verification_programs_nonce' => 'bvmgr_verification_programs_nonce',
			'vms_verification_upload_settings_nonce' => 'bvmgr_verification_upload_settings_nonce',
		);
	}
}

if (!function_exists('bvmgr_prefix_b4_normalize_nonce_fields')) {
	function bvmgr_prefix_b4_normalize_nonce_fields(): void
	{
		foreach (bvmgr_prefix_b4_nonce_field_map() as $legacy => $canonical) {
			foreach (array('_GET', '_POST', '_REQUEST') as $bag_name) {
				if (!isset($GLOBALS[$bag_name]) || !is_array($GLOBALS[$bag_name])) {
					continue;
				}
				if (!array_key_exists($canonical, $GLOBALS[$bag_name]) && array_key_exists($legacy, $GLOBALS[$bag_name])) {
					$GLOBALS[$bag_name][$canonical] = $GLOBALS[$bag_name][$legacy];
				}
			}
		}
	}
}

bvmgr_prefix_b4_normalize_nonce_fields();

if (!function_exists('bvmgr_get_query_var_compat')) {
	function bvmgr_get_query_var_compat(string $canonical, string $default = ''): string
	{
		$legacy = str_starts_with($canonical, 'bvmgr_')
			? 'vms_' . substr($canonical, 6)
			: $canonical;

		foreach (array_unique(array($canonical, $legacy)) as $key) {
			$value = get_query_var($key, null);
			if (is_scalar($value) && (string) $value !== '') {
				return (string) $value;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- B4 compatibility reads public routing values without mutating state.
			if (array_key_exists($key, $_GET) && is_scalar($_GET[$key])) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Callers retain their context-specific token/text sanitization after canonical-first lookup.
				return (string) wp_unslash($_GET[$key]);
			}
		}

		return $default;
	}
}

if (!function_exists('bvmgr_prefix_b4_maybe_flush_rewrite_rules')) {
	function bvmgr_prefix_b4_maybe_flush_rewrite_rules(): void
	{
		$marker = 'bvmgr_prefix_b4_rewrite_version';
		$target = '1';
		if ((string) get_option($marker, '') === $target) {
			return;
		}

		flush_rewrite_rules(false);
		update_option($marker, $target, false);
	}
}

if (function_exists('add_action')) {
	add_action('init', 'bvmgr_prefix_b4_maybe_flush_rewrite_rules', 100);
}
