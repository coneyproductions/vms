<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('bvmgr_vendor_booking_onboarding_redirect_admin')) {
    function bvmgr_vendor_booking_onboarding_redirect_admin(array $args = array()): void
    {
        $base = array('page' => function_exists('bvmgr_vendor_command_center_page_slug') ? bvmgr_vendor_command_center_page_slug() : 'vms-vendor-command-center');
        wp_safe_redirect(add_query_arg(array_merge($base, $args), admin_url('admin.php')));
        exit;
    }
}

if (!function_exists('bvmgr_vendor_booking_onboarding_render_settings_panel')) {
    function bvmgr_vendor_booking_onboarding_render_settings_panel(): void
    {
        if (!function_exists('bvmgr_vendor_booking_onboarding_get_settings')) {
            return;
        }

        $settings = bvmgr_vendor_booking_onboarding_get_settings();
        $help = function_exists('bvmgr_vendor_booking_onboarding_placeholder_help')
            ? bvmgr_vendor_booking_onboarding_placeholder_help()
            : array();

        echo '<details class="vms-vcc-compose vms-vcc-panel" data-vms-tour="vendor-command.booked-automation" data-vms-persist-key="vcc-booked-automation" open>';
        echo '<summary class="vms-vcc-panel__summary">';
        echo '<span class="vms-vcc-panel__summary-text">';
        echo '<span class="vms-vcc-panel__title">' . esc_html__('Booked vendor automation', 'backstage-venue-manager') . '</span>';
        echo '<span class="vms-vcc-panel__description">' . esc_html__('This automates the “you’ve been booked” email, the account-link reminder, and the soft-requested headliner promo video workflow. Operators can configure the message, merge tokens, and reminder timing here.', 'backstage-venue-manager') . '</span>';
        echo '</span>';
        echo '<span class="vms-vcc-panel__toggle" aria-hidden="true"></span>';
        echo '</summary>';
        echo '<div class="vms-vcc-panel__body">';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-vcc-compose__form">';
        wp_nonce_field('bvmgr_vendor_booking_onboarding_save_settings', 'bvmgr_vendor_booking_onboarding_nonce');
        echo '<input type="hidden" name="action" value="vms_vendor_booking_onboarding_save_settings">';

        echo '<div class="vms-vcc-compose__grid">';
        echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked(!empty($settings['enabled']), true, false) . '> <strong>' . esc_html__('Enable automatic booked-vendor emails', 'backstage-venue-manager') . '</strong></label><br><span class="description">' . esc_html__('When enabled, Backstage Venue Manager sends the booked email automatically when an Event Plan with scheduled vendors is saved into one of the selected workflow states.', 'backstage-venue-manager') . '</span></p>';
        echo '<p><strong>' . esc_html__('Send automatically when Event Plan status is', 'backstage-venue-manager') . '</strong><br>';
        foreach (array('ready' => __('Ready', 'backstage-venue-manager'), 'published' => __('Published', 'backstage-venue-manager')) as $status => $label) {
            echo '<label>';
            echo '<input type="checkbox" name="trigger_statuses[]" value="' . esc_attr($status) . '"' . checked(in_array($status, (array) ($settings['trigger_statuses'] ?? array()), true), true, false) . '> ' . esc_html($label);
            echo '</label>';
        }
        echo '</p>';
        echo '</div>';

        echo '<div class="vms-vcc-compose__grid">';
        echo '<p><label><input type="checkbox" name="video_soft_requirement" value="1"' . checked(!empty($settings['video_soft_requirement']), true, false) . '> <strong>' . esc_html__('Request headliner promo videos as a soft requirement', 'backstage-venue-manager') . '</strong></label><br><span class="description">' . esc_html__('Headliners get the promo-video request block, upload link, reminder emails, and waiver support. Supporting and secondary vendors do not get the video ask.', 'backstage-venue-manager') . '</span></p>';
        echo '<p><label for="vms-vbo-reminder-after"><strong>' . esc_html__('First reminder after booking email', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<input type="number" min="0" max="60" id="vms-vbo-reminder-after" name="reminder_after_days" value="' . esc_attr((string) ((int) ($settings['reminder_after_days'] ?? 3))) . '"> <span class="description">' . esc_html__('days later (0 disables this first reminder)', 'backstage-venue-manager') . '</span></p>';
        echo '<p><label for="vms-vbo-reminder-before"><strong>' . esc_html__('Final reminder window before event', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<input type="number" min="0" max="60" id="vms-vbo-reminder-before" name="reminder_before_days" value="' . esc_attr((string) ((int) ($settings['reminder_before_days'] ?? 7))) . '"> <span class="description">' . esc_html__('days before the event (0 disables this reminder window)', 'backstage-venue-manager') . '</span></p>';
        echo '</div>';

        echo '<p>';
        echo '<label for="vms-vbo-subject"><strong>' . esc_html__('Booked email subject', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<input type="text" class="large-text" id="vms-vbo-subject" name="subject" value="' . esc_attr((string) ($settings['subject'] ?? '')) . '" required>';
        echo '</p>';

        echo '<p>';
        echo '<label for="vms-vbo-body"><strong>' . esc_html__('Booked email body', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<textarea id="vms-vbo-body" name="body" rows="12" required>' . esc_textarea((string) ($settings['body'] ?? '')) . '</textarea>';
        echo '</p>';

        echo '<p>';
        echo '<label for="vms-vbo-script"><strong>' . esc_html__('Suggested promo-video script', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<textarea id="vms-vbo-script" name="promo_video_script" rows="5">' . esc_textarea((string) ($settings['promo_video_script'] ?? '')) . '</textarea>';
        echo '<span class="description">' . esc_html__('This script is only inserted for headliners when the soft promo-video request is enabled.', 'backstage-venue-manager') . '</span>';
        echo '</p>';

        if (!empty($help)) {
            echo '<div class="vms-vcc-token-grid">';
            foreach ($help as $token => $desc) {
                echo '<div class="vms-vcc-token-item"><code>' . esc_html($token) . '</code><span>' . esc_html((string) $desc) . '</span></div>';
            }
            echo '</div>';
        }

        echo '<p class="vms-vcc-compose__actions">';
        submit_button(__('Save booked-vendor automation', 'backstage-venue-manager'), 'primary', 'submit', false);
        echo '</p>';
        echo '</form>';
        echo '</div>';
        echo '</details>';
    }
}

add_action('admin_post_vms_vendor_booking_onboarding_save_settings', 'bvmgr_vendor_booking_onboarding_handle_save_settings');
if (!function_exists('bvmgr_vendor_booking_onboarding_handle_save_settings')) {
    function bvmgr_vendor_booking_onboarding_handle_save_settings(): void
	    {
	        if (!current_user_can('manage_options')) {
	            wp_die(esc_html__('You do not have permission to perform this action.', 'backstage-venue-manager'));
	        }
	        check_admin_referer(bvmgr_nonce_action_for_request('bvmgr_vendor_booking_onboarding_save_settings', 'bvmgr_vendor_booking_onboarding_nonce'), 'bvmgr_vendor_booking_onboarding_nonce');

	        $settings = function_exists('bvmgr_vendor_booking_onboarding_normalize_settings')
	            ? bvmgr_vendor_booking_onboarding_normalize_settings(array(
	                'enabled' => !empty($_POST['enabled']),
	                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Structured onboarding settings are normalized by vms_vendor_booking_onboarding_normalize_settings().
	                'trigger_statuses' => isset($_POST['trigger_statuses']) ? (array) wp_unslash($_POST['trigger_statuses']) : array(),
	                'video_soft_requirement' => !empty($_POST['video_soft_requirement']),
	                'reminder_after_days' => isset($_POST['reminder_after_days']) ? (int) $_POST['reminder_after_days'] : 3,
	                'reminder_before_days' => isset($_POST['reminder_before_days']) ? (int) $_POST['reminder_before_days'] : 7,
	                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Subject text is normalized by vms_vendor_booking_onboarding_normalize_settings().
	                'subject' => isset($_POST['subject']) ? (string) wp_unslash($_POST['subject']) : '',
	                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Body text is normalized by vms_vendor_booking_onboarding_normalize_settings().
	                'body' => isset($_POST['body']) ? (string) wp_unslash($_POST['body']) : '',
	                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Script text is normalized by vms_vendor_booking_onboarding_normalize_settings().
	                'promo_video_script' => isset($_POST['promo_video_script']) ? (string) wp_unslash($_POST['promo_video_script']) : '',
	            ))
	            : array();

        update_option(bvmgr_vendor_booking_onboarding_settings_option_key(), $settings, false);
        if (function_exists('bvmgr_add_admin_notice')) {
            bvmgr_add_admin_notice(__('Booked vendor automation settings saved.', 'backstage-venue-manager'), 'success');
        }
        bvmgr_vendor_booking_onboarding_redirect_admin();
    }
}

add_action('admin_post_vms_vendor_booking_onboarding_resend', 'bvmgr_vendor_booking_onboarding_handle_resend');
if (!function_exists('bvmgr_vendor_booking_onboarding_handle_resend')) {
    function bvmgr_vendor_booking_onboarding_handle_resend(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'backstage-venue-manager'));
        }

        $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
        $vendor_id = isset($_POST['vendor_id']) ? absint($_POST['vendor_id']) : 0;
        check_admin_referer(bvmgr_nonce_action_for_request('bvmgr_vendor_booking_onboarding_resend_' . $plan_id . '_' . $vendor_id, '_wpnonce'), '_wpnonce');

        $result = function_exists('bvmgr_vendor_booking_onboarding_send_booked_email')
            ? bvmgr_vendor_booking_onboarding_send_booked_email($plan_id, $vendor_id, 'manual_resend', (int) get_current_user_id())
            : array('success' => false, 'error_message' => __('Booked-vendor automation is unavailable.', 'backstage-venue-manager'));

        if (!empty($result['success'])) {
            if (function_exists('bvmgr_add_admin_notice')) {
                bvmgr_add_admin_notice(__('Booked vendor email resent.', 'backstage-venue-manager'), 'success');
            }
        } else {
            if (function_exists('bvmgr_add_admin_notice')) {
                bvmgr_add_admin_notice((string) ($result['error_message'] ?? __('Booked vendor email could not be resent.', 'backstage-venue-manager')), 'error');
            }
        }

        bvmgr_vendor_booking_onboarding_redirect_admin(array('vendor_id' => $vendor_id));
    }
}

add_action('admin_post_vms_vendor_booking_onboarding_toggle_waiver', 'bvmgr_vendor_booking_onboarding_handle_toggle_waiver');
if (!function_exists('bvmgr_vendor_booking_onboarding_handle_toggle_waiver')) {
    function bvmgr_vendor_booking_onboarding_handle_toggle_waiver(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'backstage-venue-manager'));
        }

        $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
        $vendor_id = isset($_POST['vendor_id']) ? absint($_POST['vendor_id']) : 0;
        $waive = !empty($_POST['waive']);
        check_admin_referer(bvmgr_nonce_action_for_request('bvmgr_vendor_booking_onboarding_toggle_waiver_' . $plan_id . '_' . $vendor_id, '_wpnonce'), '_wpnonce');

        if (function_exists('bvmgr_vendor_booking_onboarding_set_video_waiver')) {
            bvmgr_vendor_booking_onboarding_set_video_waiver($plan_id, $vendor_id, $waive, (int) get_current_user_id());
        }

        if (function_exists('bvmgr_add_admin_notice')) {
            bvmgr_add_admin_notice($waive ? __('Promo video waived for this booking.', 'backstage-venue-manager') : __('Promo video waiver removed for this booking.', 'backstage-venue-manager'), 'success');
        }

        bvmgr_vendor_booking_onboarding_redirect_admin(array('vendor_id' => $vendor_id));
    }
}
