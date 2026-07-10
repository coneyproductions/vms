<?php
/**
 * Admin page for private Event Feedback responses.
 */

defined('ABSPATH') || exit;

if (!function_exists('vms_feedback_admin_register_page')) {
	function vms_feedback_admin_register_page(): void
	{
		if (!function_exists('vms_register_admin_page')) {
			return;
		}

		vms_register_admin_page(array(
			'id' => 'event_feedback',
			'slug' => 'vms-event-feedback',
			'page_title' => __('Event Feedback', 'backstage-venue-manager'),
			'menu_title' => __('Event Feedback', 'backstage-venue-manager'),
			'capability' => 'manage_options',
			'callback' => 'vms_render_event_feedback_admin_page',
			'section' => 'reports_finance',
			'order' => 35,
			'source' => 'vms-core',
			'description' => __('Private post-event survey links and response summaries.', 'backstage-venue-manager'),
			'top_nav' => true,
			'directory' => true,
			'register' => true,
			'shell' => true,
			'left_menu' => false,
		));
	}
}
add_action('vms_admin_register_pages', 'vms_feedback_admin_register_page', 30);

if (!function_exists('vms_feedback_admin_enqueue_assets')) {
	function vms_feedback_admin_enqueue_assets(string $hook): void
	{
		unset($hook);
		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		if ($page !== 'vms-event-feedback') {
			return;
		}
		wp_enqueue_style('vms-event-feedback', VMS_PLUGIN_URL . 'assets/css/vms-event-feedback.css', array(), vms_asset_version());
	}
}
add_action('admin_enqueue_scripts', 'vms_feedback_admin_enqueue_assets');


if (!function_exists('vms_feedback_admin_redirect_url')) {
	function vms_feedback_admin_redirect_url(int $event_plan_id = 0): string
	{
		$args = array('page' => 'vms-event-feedback');
		if ($event_plan_id > 0) {
			$args['event_plan_id'] = absint($event_plan_id);
		}
		return add_query_arg($args, admin_url('admin.php'));
	}
}

if (!function_exists('vms_feedback_admin_handle_save_settings')) {
	function vms_feedback_admin_handle_save_settings(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
		if (!isset($_POST['vms_feedback_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['vms_feedback_settings_nonce'])), 'vms_feedback_save_settings')) {
			wp_die(esc_html__('Settings form expired. Please try again.', 'backstage-venue-manager'));
		}
		$enabled = !empty($_POST['notify_enabled']);
		$recipients = isset($_POST['notify_recipients']) ? sanitize_textarea_field(wp_unslash((string) $_POST['notify_recipients'])) : '';
		if (function_exists('vms_feedback_save_notification_settings')) {
			vms_feedback_save_notification_settings($enabled, $recipients);
		}
		wp_safe_redirect(add_query_arg('vms_feedback_settings_saved', '1', vms_feedback_admin_redirect_url($event_plan_id)));
		exit;
	}
}
add_action('admin_post_vms_save_event_feedback_settings', 'vms_feedback_admin_handle_save_settings');

if (!function_exists('vms_feedback_admin_handle_delete_response')) {
	function vms_feedback_admin_handle_delete_response(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		$response_id = isset($_POST['response_id']) ? absint($_POST['response_id']) : 0;
		$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
		if ($response_id <= 0 || get_post_type($response_id) !== VMS_CPT_FEEDBACK_RESPONSE) {
			wp_safe_redirect(add_query_arg('vms_feedback_deleted', 'missing', vms_feedback_admin_redirect_url($event_plan_id)));
			exit;
		}
		if (!isset($_POST['vms_feedback_delete_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['vms_feedback_delete_nonce'])), 'vms_feedback_delete_response_' . $response_id)) {
			wp_die(esc_html__('Delete request expired. Please try again.', 'backstage-venue-manager'));
		}
		if ($event_plan_id <= 0) {
			$event_plan_id = absint(get_post_meta($response_id, vms_feedback_meta_key('event_plan_id'), true));
		}
		$deleted = wp_delete_post($response_id, true);
		wp_safe_redirect(add_query_arg('vms_feedback_deleted', $deleted ? '1' : '0', vms_feedback_admin_redirect_url($event_plan_id)));
		exit;
	}
}
add_action('admin_post_vms_delete_event_feedback_response', 'vms_feedback_admin_handle_delete_response');


if (!function_exists('vms_feedback_admin_render_notices')) {
	function vms_feedback_admin_render_notices(): void
	{
		if (!empty($_GET['vms_feedback_settings_saved'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Event Feedback notification settings saved.', 'backstage-venue-manager') . '</p></div>';
		}
		if (isset($_GET['vms_feedback_deleted'])) {
			$status = sanitize_key((string) $_GET['vms_feedback_deleted']);
			if ($status === '1') {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Feedback response deleted.', 'backstage-venue-manager') . '</p></div>';
			} elseif ($status === 'missing') {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Feedback response could not be found.', 'backstage-venue-manager') . '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Feedback response could not be deleted.', 'backstage-venue-manager') . '</p></div>';
			}
		}
	}
}

if (!function_exists('vms_feedback_admin_render_notification_settings')) {
	function vms_feedback_admin_render_notification_settings(int $event_plan_id): void
	{
		$settings = function_exists('vms_feedback_get_notification_settings') ? vms_feedback_get_notification_settings() : array('enabled' => false, 'recipients' => array());
		$recipients = isset($settings['recipients']) && is_array($settings['recipients']) ? implode(', ', $settings['recipients']) : '';
		echo '<hr class="vms-feedback-sidebar-divider">';
		echo '<h3>' . esc_html__('New submission notifications', 'backstage-venue-manager') . '</h3>';
		echo '<p class="description">' . esc_html__('Optionally email an operator whenever a new private feedback response is submitted.', 'backstage-venue-manager') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-feedback-settings-form">';
		echo '<input type="hidden" name="action" value="vms_save_event_feedback_settings">';
		echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) absint($event_plan_id)) . '">';
		wp_nonce_field('vms_feedback_save_settings', 'vms_feedback_settings_nonce');
		echo '<label class="vms-feedback-checkbox-inline"><input type="checkbox" name="notify_enabled" value="1"' . checked(!empty($settings['enabled']), true, false) . '> ' . esc_html__('Email me new submissions', 'backstage-venue-manager') . '</label>';
		echo '<label class="vms-feedback-settings-label" for="vms-feedback-notify-recipients">' . esc_html__('Recipient emails', 'backstage-venue-manager') . '</label>';
		echo '<textarea id="vms-feedback-notify-recipients" name="notify_recipients" rows="3" class="widefat code" placeholder="' . esc_attr((string) get_option('admin_email')) . '">' . esc_textarea($recipients) . '</textarea>';
		echo '<p class="description">' . esc_html__('Separate multiple emails with commas or line breaks. Leave blank to use the site admin email.', 'backstage-venue-manager') . '</p>';
		echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Save Notification Settings', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';
	}
}

if (!function_exists('vms_feedback_recent_event_plans')) {
	/**
	 * @return WP_Post[]
	 */
	function vms_feedback_recent_event_plans(int $limit = 75): array
	{
		$date_key = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'date') : '_vms_event_date';
		return get_posts(array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
			'posts_per_page' => $limit,
			'meta_key' => $date_key,
			'orderby' => 'meta_value',
			'order' => 'DESC',
			'no_found_rows' => true,
		));
	}
}

if (!function_exists('vms_feedback_admin_event_label')) {
	function vms_feedback_admin_event_label(int $event_plan_id): string
	{
		$title = get_the_title($event_plan_id);
		$date = vms_feedback_get_event_plan_date($event_plan_id);
		if ($date !== '') {
			$ts = strtotime($date . ' 12:00:00');
			$title .= ' - ' . ($ts ? date_i18n(get_option('date_format'), $ts) : $date);
		}
		return $title;
	}
}

if (!function_exists('vms_feedback_admin_render_event_selector')) {
	function vms_feedback_admin_render_event_selector(int $selected_event_plan_id): void
	{
		$events = vms_feedback_recent_event_plans();
		echo '<form class="vms-feedback-admin-selector" method="get" action="' . esc_url(admin_url('admin.php')) . '" data-vms-tour="event-feedback.selector">';
		echo '<input type="hidden" name="page" value="vms-event-feedback">';
		echo '<label for="vms-feedback-event-plan-id"><strong>' . esc_html__('Choose event', 'backstage-venue-manager') . '</strong></label>';
		echo '<select id="vms-feedback-event-plan-id" name="event_plan_id">';
		echo '<option value="0">' . esc_html__('Select an Event Plan...', 'backstage-venue-manager') . '</option>';
		foreach ($events as $event) {
			$event_id = absint($event->ID);
			echo '<option value="' . esc_attr((string) $event_id) . '"' . selected($selected_event_plan_id, $event_id, false) . '>' . esc_html(vms_feedback_admin_event_label($event_id)) . '</option>';
		}
		echo '</select> ';
		submit_button(__('View Feedback', 'backstage-venue-manager'), 'primary', '', false);
		echo '</form>';
	}
}

if (!function_exists('vms_feedback_admin_rating_label')) {
	function vms_feedback_admin_rating_label($rating): string
	{
		$rating = vms_feedback_sanitize_rating($rating);
		if ($rating <= 0) {
			return '--';
		}
		$options = vms_feedback_rating_options();
		return (string) $rating . '/5' . (isset($options[$rating]) ? ' - ' . $options[$rating] : '');
	}
}

if (!function_exists('vms_feedback_admin_average_label')) {
	function vms_feedback_admin_average_label(array $avg): string
	{
		$count = isset($avg['count']) ? (int) $avg['count'] : 0;
		if ($count <= 0 || !isset($avg['average']) || $avg['average'] === null) {
			return '--';
		}
		return number_format_i18n((float) $avg['average'], 2) . '/5 (' . number_format_i18n($count) . ')';
	}
}

if (!function_exists('vms_feedback_admin_render_summary_card')) {
	/**
	 * @param WP_Post[] $responses
	 */
	function vms_feedback_admin_render_summary_card(array $responses): void
	{
		$metrics = array(
			__('Overall night', 'backstage-venue-manager') => 'overall.event_rating',
			__('Venue overall', 'backstage-venue-manager') => 'venue.overall',
			__('Bar', 'backstage-venue-manager') => 'venue.bar',
			__('Bathrooms', 'backstage-venue-manager') => 'venue.bathrooms',
			__('Arrival / check-in', 'backstage-venue-manager') => 'venue.arrival',
			__('Sound', 'backstage-venue-manager') => 'venue.sound',
			__('Primary vendor', 'backstage-venue-manager') => 'primary_vendor.performance',
		);

		echo '<div class="vms-feedback-admin-card" data-vms-tour="event-feedback.summary">';
		echo '<h2>' . esc_html__('At-a-glance ratings', 'backstage-venue-manager') . '</h2>';
		echo '<div class="vms-feedback-metric-grid">';
		foreach ($metrics as $label => $path) {
			$avg = vms_feedback_average($responses, $path);
			echo '<div class="vms-feedback-metric"><span>' . esc_html((string) $label) . '</span><strong>' . esc_html(vms_feedback_admin_average_label($avg)) . '</strong></div>';
		}
		echo '</div>';
		echo '</div>';
	}
}

if (!function_exists('vms_feedback_admin_choice_label')) {
	function vms_feedback_admin_choice_label(string $value): string
	{
		return vms_feedback_admin_option_label($value, vms_feedback_yes_maybe_no_options());
	}
}

if (!function_exists('vms_feedback_admin_option_label')) {
	function vms_feedback_admin_option_label(string $value, array $options): string
	{
		$value = sanitize_key($value);
		return isset($options[$value]) ? (string) $options[$value] : ($value !== '' ? $value : '--');
	}
}

if (!function_exists('vms_feedback_admin_classify_saved_option_labels')) {
	/**
	 * @param string[] $values
	 * @return array{current:string[],legacy:string[],unknown:string[]}
	 */
	function vms_feedback_admin_classify_saved_option_labels(array $values, array $current_options, array $legacy_options): array
	{
		$classified = array(
			'current' => array(),
			'legacy' => array(),
			'unknown' => array(),
		);
		foreach ($values as $value) {
			$key = sanitize_key((string) $value);
			if ($key === '') {
				continue;
			}
			if (isset($current_options[$key])) {
				$classified['current'][] = wp_strip_all_tags((string) $current_options[$key]);
				continue;
			}
			if (isset($legacy_options[$key])) {
				$classified['legacy'][] = wp_strip_all_tags((string) $legacy_options[$key]);
				continue;
			}
			$classified['unknown'][] = $key;
		}
		foreach ($classified as $group => $labels) {
			$classified[$group] = array_values(array_unique(array_filter(array_map('strval', (array) $labels))));
		}
		return $classified;
	}
}


if (!function_exists('vms_feedback_admin_payload_path_value')) {
	/**
	 * @return mixed
	 */
	function vms_feedback_admin_payload_path_value(array $payload, string $path)
	{
		return function_exists('vms_feedback_payload_path_value') ? vms_feedback_payload_path_value($payload, $path) : null;
	}
}

if (!function_exists('vms_feedback_admin_choice_counts')) {
	/**
	 * @param WP_Post[] $responses
	 * @return array<string,int>
	 */
	function vms_feedback_admin_choice_counts(array $responses, string $path, ?array $allowed = null, ?callable $include_response = null): array
	{
		$allowed = (!empty($allowed) && is_array($allowed)) ? $allowed : vms_feedback_yes_maybe_no_options();
		$counts = array_fill_keys(array_keys($allowed), 0);
		foreach ($responses as $response) {
			if (!$response instanceof WP_Post) {
				continue;
			}
			$payload = vms_feedback_get_response_payload((int) $response->ID);
			if ($include_response && !$include_response($payload)) {
				continue;
			}
			$value = sanitize_key((string) vms_feedback_admin_payload_path_value($payload, $path));
			if (isset($counts[$value])) {
				$counts[$value]++;
			}
		}
		return $counts;
	}
}

if (!function_exists('vms_feedback_admin_choice_counts_label')) {
	function vms_feedback_admin_choice_counts_label(array $counts, ?array $options = null): string
	{
		$options = (!empty($options) && is_array($options)) ? $options : vms_feedback_yes_maybe_no_options();
		$total = array_sum(array_map('intval', $counts));
		if ($total <= 0) {
			return '--';
		}
		$parts = array();
		foreach ($options as $key => $label) {
			$parts[] = wp_strip_all_tags((string) $label) . ' ' . (int) ($counts[$key] ?? 0);
		}
		return implode(' / ', $parts);
	}
}

if (!function_exists('vms_feedback_admin_vendor_has_detail_data')) {
	function vms_feedback_admin_vendor_has_detail_data(array $vendor): bool
	{
		foreach (array('wait_time', 'friendliness', 'selection', 'value', 'quality', 'accuracy') as $key) {
			if (vms_feedback_sanitize_rating($vendor[$key] ?? 0) > 0) {
				return true;
			}
		}
		return !empty($vendor['bring_back']) || !empty($vendor['wait_causes']) || !empty($vendor['comment']);
	}
}

if (!function_exists('vms_feedback_admin_render_website_summary')) {
	/**
	 * @param WP_Post[] $responses
	 */
	function vms_feedback_admin_render_website_summary(array $responses): void
	{
		$usage_options = vms_feedback_website_usage_options();
		$payment_issue_options = vms_feedback_website_payment_issue_options();
		$website_detail_filter = static function (array $payload): bool {
			$website_used = sanitize_key((string) vms_feedback_admin_payload_path_value($payload, 'website.website_used'));
			return function_exists('vms_feedback_website_details_enabled') ? vms_feedback_website_details_enabled($website_used) : ($website_used !== '' && $website_used !== 'did_not_use');
		};

		echo '<div class="vms-feedback-admin-card">';
		echo '<h2>' . esc_html__('Website / Ticket Purchase Experience', 'backstage-venue-manager') . '</h2>';
		echo '<p><strong>' . esc_html__('Website usage', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_choice_counts_label(vms_feedback_admin_choice_counts($responses, 'website.website_used', $usage_options), $usage_options)) . '</p>';
		echo '<p><strong>' . esc_html__('Reported issues', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_choice_counts_label(vms_feedback_admin_choice_counts($responses, 'website.website_payment_issues', $payment_issue_options, $website_detail_filter), $payment_issue_options)) . '</p>';
		echo '<div class="vms-feedback-metric-grid">';
		echo '<div class="vms-feedback-metric"><span>' . esc_html__('Find Event', 'backstage-venue-manager') . '</span><strong>' . esc_html(vms_feedback_admin_average_label(vms_feedback_average_filtered($responses, 'website.website_find_event', $website_detail_filter))) . '</strong></div>';
		echo '<div class="vms-feedback-metric"><span>' . esc_html__('Ticket Selection', 'backstage-venue-manager') . '</span><strong>' . esc_html(vms_feedback_admin_average_label(vms_feedback_average_filtered($responses, 'website.website_ticket_selection', $website_detail_filter))) . '</strong></div>';
		echo '<div class="vms-feedback-metric"><span>' . esc_html__('Checkout', 'backstage-venue-manager') . '</span><strong>' . esc_html(vms_feedback_admin_average_label(vms_feedback_average_filtered($responses, 'website.website_checkout_smoothness', $website_detail_filter))) . '</strong></div>';
		echo '<div class="vms-feedback-metric"><span>' . esc_html__('Confirmation', 'backstage-venue-manager') . '</span><strong>' . esc_html(vms_feedback_admin_average_label(vms_feedback_average_filtered($responses, 'website.website_confirmation', $website_detail_filter))) . '</strong></div>';
		echo '</div>';
		echo '</div>';
	}
}

if (!function_exists('vms_feedback_admin_render_primary_vendor_summary')) {
	/**
	 * @param WP_Post[] $responses
	 * @param array<string,mixed> $context
	 */
	function vms_feedback_admin_render_primary_vendor_summary(array $responses, array $context): void
	{
		$primary = isset($context['primary_vendor']) && is_array($context['primary_vendor']) ? $context['primary_vendor'] : array();
		$vendor_id = absint($primary['id'] ?? 0);
		if ($vendor_id <= 0) {
			return;
		}

		echo '<div class="vms-feedback-admin-card" data-vms-tour="event-feedback.primary-vendor">';
		echo '<h2>' . esc_html__('Primary vendor details', 'backstage-venue-manager') . '</h2>';
		echo '<section class="vms-feedback-vendor-summary">';
		echo '<h3>' . esc_html((string) ($primary['name'] ?? get_the_title($vendor_id))) . '</h3>';
		echo '<div class="vms-feedback-metric-grid">';
		echo '<div class="vms-feedback-metric"><span>' . esc_html__('Performance', 'backstage-venue-manager') . '</span><strong>' . esc_html(vms_feedback_admin_average_label(vms_feedback_average($responses, 'primary_vendor.performance'))) . '</strong></div>';
		echo '<div class="vms-feedback-metric"><span>' . esc_html__('Would like them back', 'backstage-venue-manager') . '</span><strong>' . esc_html(vms_feedback_admin_choice_counts_label(vms_feedback_admin_choice_counts($responses, 'primary_vendor.bring_back'))) . '</strong></div>';
		echo '</div>';
		echo '</section>';
		echo '</div>';
	}
}

if (!function_exists('vms_feedback_admin_render_vendor_summary')) {
	/**
	 * @param WP_Post[] $responses
	 * @param array<string,mixed> $context
	 */
	function vms_feedback_admin_render_vendor_summary(array $responses, array $context): void
	{
		$secondary = isset($context['secondary_vendors']) && is_array($context['secondary_vendors']) ? $context['secondary_vendors'] : array();
		if (empty($secondary)) {
			return;
		}

		echo '<div class="vms-feedback-admin-card" data-vms-tour="event-feedback.secondary-vendors">';
		echo '<h2>' . esc_html__('Secondary vendor details', 'backstage-venue-manager') . '</h2>';
		foreach ($secondary as $vendor) {
			$vendor_id = absint($vendor['id'] ?? 0);
			if ($vendor_id <= 0) {
				continue;
			}
			$paths = array(
				__('Wait time / speed', 'backstage-venue-manager') => 'secondary_vendors.' . $vendor_id . '.wait_time',
				__('Friendliness', 'backstage-venue-manager') => 'secondary_vendors.' . $vendor_id . '.friendliness',
				__('Selection', 'backstage-venue-manager') => 'secondary_vendors.' . $vendor_id . '.selection',
				__('Price / value', 'backstage-venue-manager') => 'secondary_vendors.' . $vendor_id . '.value',
				__('Quality', 'backstage-venue-manager') => 'secondary_vendors.' . $vendor_id . '.quality',
				__('Accuracy', 'backstage-venue-manager') => 'secondary_vendors.' . $vendor_id . '.accuracy',
			);
			$order_options = vms_feedback_secondary_vendor_order_options();
			$ordered_filter = static function (array $payload) use ($vendor_id): bool {
				$did_order = sanitize_key((string) vms_feedback_admin_payload_path_value($payload, 'secondary_vendors.' . $vendor_id . '.did_order'));
				if (function_exists('vms_feedback_secondary_vendor_details_enabled') && vms_feedback_secondary_vendor_details_enabled($did_order)) {
					return true;
				}
				if ($did_order === '') {
					$vendor_payload = vms_feedback_admin_payload_path_value($payload, 'secondary_vendors.' . $vendor_id);
					return is_array($vendor_payload) && vms_feedback_admin_vendor_has_detail_data($vendor_payload);
				}
				return $did_order === 'yes';
			};
			echo '<section class="vms-feedback-vendor-summary">';
			echo '<h3>' . esc_html((string) ($vendor['name'] ?? get_the_title($vendor_id))) . '</h3>';
			echo '<p><strong>' . esc_html__('Did you order from them?', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_choice_counts_label(vms_feedback_admin_choice_counts($responses, 'secondary_vendors.' . $vendor_id . '.did_order', $order_options), $order_options)) . '</p>';
			echo '<p><strong>' . esc_html__('Would bring back', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_choice_counts_label(vms_feedback_admin_choice_counts($responses, 'secondary_vendors.' . $vendor_id . '.bring_back', vms_feedback_yes_maybe_no_options(), $ordered_filter))) . '</p>';
			echo '<div class="vms-feedback-metric-grid">';
			foreach ($paths as $label => $path) {
				$avg = vms_feedback_average_filtered($responses, $path, $ordered_filter);
				echo '<div class="vms-feedback-metric"><span>' . esc_html((string) $label) . '</span><strong>' . esc_html(vms_feedback_admin_average_label($avg)) . '</strong></div>';
			}
			echo '</div>';
			echo '</section>';
		}
		echo '</div>';
	}
}

if (!function_exists('vms_feedback_admin_render_link_card')) {
	function vms_feedback_admin_render_link_card(int $event_plan_id): void
	{
		$survey_url = vms_feedback_survey_url($event_plan_id);
		$edit_url = get_edit_post_link($event_plan_id, 'raw');
		echo '<div class="vms-feedback-admin-card" data-vms-tour="event-feedback.link">';
		echo '<h2>' . esc_html__('Private survey link', 'backstage-venue-manager') . '</h2>';
		echo '<p>' . esc_html__('Share this link with ticket buyers or attendees. Responses stay private in VMS.', 'backstage-venue-manager') . '</p>';
		echo '<div class="vms-feedback-link-row"><input type="text" class="large-text code" readonly value="' . esc_attr($survey_url) . '"></div>';
		echo '<p class="description">' . esc_html__('Tip: copy the full URL above and send it by email, text, or a private post-event message.', 'backstage-venue-manager') . '</p>';
		$email_preview_url = add_query_arg(array(
			'page' => function_exists('vms_email_followups_admin_slug') ? vms_email_followups_admin_slug() : 'vms-email-followups',
			'tab' => 'preview',
			'event_plan_id' => $event_plan_id,
			'email_key' => 'post_event',
		), admin_url('admin.php'));
		echo '<p>';
		if (is_string($edit_url) && $edit_url !== '') {
			echo '<a class="button" href="' . esc_url($edit_url) . '">' . esc_html__('Edit Event Plan', 'backstage-venue-manager') . '</a> ';
		}
		echo '<a class="button button-secondary" href="' . esc_url($email_preview_url) . '">' . esc_html__('Open Post-Event Email Preview', 'backstage-venue-manager') . '</a>';
		echo '</p>';
		echo '</div>';
	}
}

if (!function_exists('vms_feedback_admin_render_response')) {
	function vms_feedback_admin_render_response(WP_Post $response, bool $is_duplicate = false): void
	{
		$payload = vms_feedback_get_response_payload((int) $response->ID);
		$attendee = isset($payload['attendee']) && is_array($payload['attendee']) ? $payload['attendee'] : array();
		$overall = isset($payload['overall']) && is_array($payload['overall']) ? $payload['overall'] : array();
		$venue = isset($payload['venue']) && is_array($payload['venue']) ? $payload['venue'] : array();
		$website = isset($payload['website']) && is_array($payload['website']) ? $payload['website'] : array();
		$primary = isset($payload['primary_vendor']) && is_array($payload['primary_vendor']) ? $payload['primary_vendor'] : array();
		$secondary = isset($payload['secondary_vendors']) && is_array($payload['secondary_vendors']) ? $payload['secondary_vendors'] : array();
		$name = trim((string) ($attendee['name'] ?? ''));
		$email = trim((string) ($attendee['email'] ?? ''));
		$submitted = get_date_from_gmt((string) get_post_meta((int) $response->ID, vms_feedback_meta_key('submitted_at_gmt'), true), get_option('date_format') . ' ' . get_option('time_format'));
		if ($submitted === '') {
			$submitted = get_the_date('', $response);
		}

		echo '<details id="vms-feedback-response-' . esc_attr((string) absint($response->ID)) . '" class="vms-feedback-response"' . ($is_duplicate ? ' data-vms-duplicate="1"' : '') . ' open>';
		echo '<summary><strong>' . esc_html($submitted) . '</strong> - ' . esc_html($name !== '' ? $name : __('Anonymous', 'backstage-venue-manager')) . ($email !== '' ? ' - ' . esc_html($email) : '');
		if ($is_duplicate) {
			echo ' <span class="vms-feedback-duplicate-badge">' . esc_html__('Likely duplicate', 'backstage-venue-manager') . '</span>';
		}
		echo '</summary>';
		echo '<div class="vms-feedback-response-body">';
		$event_plan_id = absint(get_post_meta((int) $response->ID, vms_feedback_meta_key('event_plan_id'), true));
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-feedback-delete-form">';
		echo '<input type="hidden" name="action" value="vms_delete_event_feedback_response">';
		echo '<input type="hidden" name="response_id" value="' . esc_attr((string) absint($response->ID)) . '">';
		echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '">';
		wp_nonce_field('vms_feedback_delete_response_' . absint($response->ID), 'vms_feedback_delete_nonce');
		$confirm = "return confirm('" . esc_js(__('Delete this feedback response? This cannot be undone.', 'backstage-venue-manager')) . "');";
		echo '<button type="submit" class="button button-link-delete" onclick="' . esc_attr($confirm) . '">' . esc_html__('Delete response', 'backstage-venue-manager') . '</button>';
		echo '</form>';
		echo '<div class="vms-feedback-response-grid">';
		echo '<p><strong>' . esc_html__('Overall night', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_rating_label($overall['event_rating'] ?? 0)) . '</p>';
		echo '<p><strong>' . esc_html__('Attend again', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_choice_label((string) ($overall['attend_again'] ?? ''))) . '</p>';
		echo '<p><strong>' . esc_html__('Recommend', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_choice_label((string) ($overall['recommend'] ?? ''))) . '</p>';
		echo '</div>';

		echo '<h4>' . esc_html__('Venue', 'backstage-venue-manager') . '</h4>';
		echo '<div class="vms-feedback-response-grid">';
		foreach (array('overall' => __('Overall', 'backstage-venue-manager'), 'bar' => __('Bar', 'backstage-venue-manager'), 'bathrooms' => __('Bathrooms', 'backstage-venue-manager'), 'arrival' => __('Arrival', 'backstage-venue-manager'), 'sound' => __('Sound', 'backstage-venue-manager')) as $key => $label) {
			echo '<p><strong>' . esc_html((string) $label) . ':</strong> ' . esc_html(vms_feedback_admin_rating_label($venue[$key] ?? 0)) . '</p>';
		}
		echo '</div>';
		if (!empty($venue['bar_details']) || !empty($venue['bar_comment'])) {
			$bar_details = vms_feedback_admin_classify_saved_option_labels(
				(array) ($venue['bar_details'] ?? array()),
				vms_feedback_bar_detail_options(),
				function_exists('vms_feedback_legacy_bar_detail_options') ? vms_feedback_legacy_bar_detail_options() : array()
			);
			if (!empty($bar_details['current'])) {
				echo '<p><strong>' . esc_html__('Bar details', 'backstage-venue-manager') . ':</strong> ' . esc_html(implode(', ', $bar_details['current'])) . '</p>';
			}
			$bar_legacy_labels = array_merge($bar_details['legacy'], $bar_details['unknown']);
			if (!empty($bar_legacy_labels)) {
				echo '<p><strong>' . esc_html__('Bar details', 'backstage-venue-manager') . ':</strong> ' . esc_html(__('Legacy selections:', 'backstage-venue-manager') . ' ' . implode(', ', $bar_legacy_labels)) . '</p>';
			}
			if (!empty($venue['bar_comment'])) {
				echo '<p>' . nl2br(esc_html((string) $venue['bar_comment'])) . '</p>';
			}
		}
		if (!empty($venue['bathroom_details']) || !empty($venue['bathroom_comment'])) {
			$bathroom_details = vms_feedback_admin_classify_saved_option_labels(
				(array) ($venue['bathroom_details'] ?? array()),
				vms_feedback_bathroom_detail_options(),
				function_exists('vms_feedback_legacy_bathroom_detail_options') ? vms_feedback_legacy_bathroom_detail_options() : array()
			);
			if (!empty($bathroom_details['current'])) {
				echo '<p><strong>' . esc_html__('Bathroom details', 'backstage-venue-manager') . ':</strong> ' . esc_html(implode(', ', $bathroom_details['current'])) . '</p>';
			}
			$bathroom_legacy_labels = array_merge($bathroom_details['legacy'], $bathroom_details['unknown']);
			if (!empty($bathroom_legacy_labels)) {
				echo '<p><strong>' . esc_html__('Bathroom details', 'backstage-venue-manager') . ':</strong> ' . esc_html(__('Legacy selections:', 'backstage-venue-manager') . ' ' . implode(', ', $bathroom_legacy_labels)) . '</p>';
			}
			if (!empty($venue['bathroom_comment'])) {
				echo '<p>' . nl2br(esc_html((string) $venue['bathroom_comment'])) . '</p>';
			}
		}

		$website_used = sanitize_key((string) ($website['website_used'] ?? ''));
		$has_website_data = ($website_used !== '')
			|| !empty($website['website_comments'])
			|| !empty($website['website_payment_issues'])
			|| vms_feedback_sanitize_rating($website['website_find_event'] ?? 0) > 0
			|| vms_feedback_sanitize_rating($website['website_ticket_selection'] ?? 0) > 0
			|| vms_feedback_sanitize_rating($website['website_checkout_smoothness'] ?? 0) > 0
			|| vms_feedback_sanitize_rating($website['website_confirmation'] ?? 0) > 0;
		if ($has_website_data) {
			$website_details_enabled = function_exists('vms_feedback_website_details_enabled') ? vms_feedback_website_details_enabled($website_used) : ($website_used !== '' && $website_used !== 'did_not_use');
			echo '<h4>' . esc_html__('Website / Ticket Purchase Experience', 'backstage-venue-manager') . '</h4>';
			echo '<p><strong>' . esc_html__('Website usage', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_option_label($website_used, vms_feedback_website_usage_options())) . '</p>';
			if ($website_details_enabled) {
				$website_rows = array();
				foreach (array(
					__('Find event', 'backstage-venue-manager') => array('type' => 'rating', 'value' => $website['website_find_event'] ?? 0),
					__('Ticket selection', 'backstage-venue-manager') => array('type' => 'rating', 'value' => $website['website_ticket_selection'] ?? 0),
					__('Checkout', 'backstage-venue-manager') => array('type' => 'rating', 'value' => $website['website_checkout_smoothness'] ?? 0),
					__('Confirmation', 'backstage-venue-manager') => array('type' => 'rating', 'value' => $website['website_confirmation'] ?? 0),
					__('Payment issues', 'backstage-venue-manager') => array('type' => 'choice', 'value' => (string) ($website['website_payment_issues'] ?? '')),
				) as $label => $row) {
					if ($row['type'] === 'rating') {
						$rating = vms_feedback_sanitize_rating($row['value'] ?? 0);
						if ($rating <= 0) {
							continue;
						}
						$website_rows[] = '<p><strong>' . esc_html((string) $label) . ':</strong> ' . esc_html(vms_feedback_admin_rating_label($rating)) . '</p>';
						continue;
					}
					$value = sanitize_key((string) ($row['value'] ?? ''));
					if ($value === '') {
						continue;
					}
					$website_rows[] = '<p><strong>' . esc_html((string) $label) . ':</strong> ' . esc_html(vms_feedback_admin_option_label($value, vms_feedback_website_payment_issue_options())) . '</p>';
				}
				if (!empty($website_rows)) {
					echo '<div class="vms-feedback-response-grid">' . implode('', $website_rows) . '</div>';
				}
				if (!empty($website['website_comments'])) {
					echo '<p>' . nl2br(esc_html((string) $website['website_comments'])) . '</p>';
				}
			}
		}

		if (!empty($primary)) {
			echo '<h4>' . esc_html((string) ($primary['name'] ?? __('Primary vendor', 'backstage-venue-manager'))) . '</h4>';
			echo '<p><strong>' . esc_html__('Performance', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_rating_label($primary['performance'] ?? 0)) . '</p>';
			echo '<p><strong>' . esc_html__('Bring back', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_choice_label((string) ($primary['bring_back'] ?? ''))) . '</p>';
			if (!empty($primary['comment'])) {
				echo '<p>' . nl2br(esc_html((string) $primary['comment'])) . '</p>';
			}
		}

		foreach ($secondary as $vendor) {
			if (!is_array($vendor)) {
				continue;
			}
			$did_order = sanitize_key((string) ($vendor['did_order'] ?? ''));
			$show_details = function_exists('vms_feedback_secondary_vendor_details_enabled') ? vms_feedback_secondary_vendor_details_enabled($did_order) : ($did_order === 'yes');
			if (!$show_details && $did_order === '' && vms_feedback_admin_vendor_has_detail_data($vendor)) {
				$show_details = true;
			}
			echo '<h4>' . esc_html((string) ($vendor['name'] ?? __('Secondary vendor', 'backstage-venue-manager'))) . '</h4>';
			if ($did_order !== '') {
				echo '<p><strong>' . esc_html__('Ordered?', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_option_label($did_order, vms_feedback_secondary_vendor_order_options())) . '</p>';
			}
			if ($show_details) {
				echo '<div class="vms-feedback-response-grid">';
				foreach (array('wait_time' => __('Wait', 'backstage-venue-manager'), 'friendliness' => __('Friendliness', 'backstage-venue-manager'), 'selection' => __('Selection', 'backstage-venue-manager'), 'value' => __('Value', 'backstage-venue-manager'), 'quality' => __('Quality', 'backstage-venue-manager'), 'accuracy' => __('Accuracy', 'backstage-venue-manager')) as $key => $label) {
					echo '<p><strong>' . esc_html((string) $label) . ':</strong> ' . esc_html(vms_feedback_admin_rating_label($vendor[$key] ?? 0)) . '</p>';
				}
				echo '<p><strong>' . esc_html__('Bring back', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_feedback_admin_choice_label((string) ($vendor['bring_back'] ?? ''))) . '</p>';
				echo '</div>';
				if (!empty($vendor['wait_causes'])) {
					echo '<p><strong>' . esc_html__('Wait causes', 'backstage-venue-manager') . ':</strong> ' . esc_html(implode(', ', (array) $vendor['wait_causes'])) . '</p>';
				}
				if (!empty($vendor['comment'])) {
					echo '<p>' . nl2br(esc_html((string) $vendor['comment'])) . '</p>';
				}
			} elseif ($did_order !== '') {
				echo '<p>' . esc_html__('Detailed ratings skipped.', 'backstage-venue-manager') . '</p>';
			} else {
				echo '<p>' . esc_html__('No detailed food vendor ratings submitted.', 'backstage-venue-manager') . '</p>';
			}
		}

		if (!empty($payload['final_comment'])) {
			echo '<h4>' . esc_html__('Final comments', 'backstage-venue-manager') . '</h4>';
			echo '<p>' . nl2br(esc_html((string) $payload['final_comment'])) . '</p>';
		}
		echo '</div>';
		echo '</details>';
	}
}

if (!function_exists('vms_feedback_admin_render_content')) {
	function vms_feedback_admin_render_content(): void
	{
		$selected_event_plan_id = isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0;
		if (function_exists('vms_feedback_admin_render_notices')) {
			vms_feedback_admin_render_notices();
		}
		vms_feedback_admin_render_event_selector($selected_event_plan_id);

		if ($selected_event_plan_id <= 0) {
			echo '<div class="vms-feedback-admin-card"><h2>' . esc_html__('Get started', 'backstage-venue-manager') . '</h2><p>' . esc_html__('Choose an Event Plan to generate a private survey link and review responses.', 'backstage-venue-manager') . '</p></div>';
			return;
		}

		$context = vms_feedback_get_event_context($selected_event_plan_id);
		if (empty($context)) {
			echo '<div class="notice notice-error"><p>' . esc_html__('That Event Plan could not be found.', 'backstage-venue-manager') . '</p></div>';
			return;
		}

		$responses = vms_feedback_get_responses($selected_event_plan_id);
		$partition = function_exists('vms_feedback_partition_duplicate_responses') ? vms_feedback_partition_duplicate_responses($responses) : array('unique' => $responses, 'duplicate_ids' => array());
		$unique_responses = (array) ($partition['unique'] ?? $responses);
		$duplicate_ids = array_map('absint', (array) ($partition['duplicate_ids'] ?? array()));
		echo '<div class="vms-feedback-admin-layout">';
		echo '<div>';
		vms_feedback_admin_render_link_card($selected_event_plan_id);
		vms_feedback_admin_render_summary_card($unique_responses);
		vms_feedback_admin_render_website_summary($unique_responses);
		vms_feedback_admin_render_primary_vendor_summary($unique_responses, $context);
		vms_feedback_admin_render_vendor_summary($unique_responses, $context);
		echo '</div>';
		echo '<aside class="vms-feedback-admin-card vms-feedback-admin-sidebar" data-vms-tour="event-feedback.response-count">';
		echo '<h2>' . esc_html__('Response count', 'backstage-venue-manager') . '</h2>';
		echo '<p class="vms-feedback-big-number">' . esc_html(number_format_i18n(count($unique_responses))) . '</p>';
		if (!empty($duplicate_ids)) {
			echo '<p class="description">' . esc_html(sprintf(__('%1$d stored response(s); %2$d likely duplicate(s) excluded from averages.', 'backstage-venue-manager'), count($responses), count($duplicate_ids))) . '</p>';
		}
		echo '<p class="description">' . esc_html__('Private/internal only. Do not share raw comments with vendors unless you intentionally curate or anonymize them.', 'backstage-venue-manager') . '</p>';
		if (function_exists('vms_feedback_admin_render_notification_settings')) {
			vms_feedback_admin_render_notification_settings($selected_event_plan_id);
		}
		echo '</aside>';
		echo '</div>';

		echo '<div class="vms-feedback-admin-card" data-vms-tour="event-feedback.responses">';
		echo '<h2>' . esc_html__('Responses', 'backstage-venue-manager') . '</h2>';
		if (empty($responses)) {
			echo '<p>' . esc_html__('No responses yet. Share the private survey link above to begin collecting feedback.', 'backstage-venue-manager') . '</p>';
		} else {
			foreach ($responses as $response) {
				vms_feedback_admin_render_response($response, in_array((int) $response->ID, $duplicate_ids, true));
			}
		}
		echo '</div>';
	}
}

if (!function_exists('vms_render_event_feedback_admin_page')) {
	function vms_render_event_feedback_admin_page(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array(
					'title' => __('Event Feedback', 'backstage-venue-manager'),
					'subtitle' => __('Private one-stop post-event surveys for venue, bar, bathrooms, primary vendors, and secondary vendors.', 'backstage-venue-manager'),
					'shell_id' => 'vms-event-feedback-admin',
				),
				'vms_feedback_admin_render_content'
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Event Feedback', 'backstage-venue-manager') . '</h1>';
		vms_feedback_admin_render_content();
		echo '</div>';
	}
}

if (!function_exists('vms_feedback_add_event_plan_metabox')) {
	function vms_feedback_add_event_plan_metabox(): void
	{
		add_meta_box(
			'vms_event_feedback_link',
			__('Post-Event Feedback', 'backstage-venue-manager'),
			'vms_feedback_render_event_plan_metabox',
			'vms_event_plan',
			'side',
			'default'
		);
	}
}
add_action('add_meta_boxes_vms_event_plan', 'vms_feedback_add_event_plan_metabox');

if (!function_exists('vms_feedback_render_event_plan_metabox')) {
	function vms_feedback_render_event_plan_metabox(WP_Post $post): void
	{
		$event_plan_id = absint($post->ID);
		if ($event_plan_id <= 0 || $post->post_status === 'auto-draft') {
			echo '<p>' . esc_html__('Save the Event Plan before sharing a feedback survey.', 'backstage-venue-manager') . '</p>';
			return;
		}
		$survey_url = vms_feedback_survey_url($event_plan_id);
		$admin_url = add_query_arg(array('page' => 'vms-event-feedback', 'event_plan_id' => $event_plan_id), admin_url('admin.php'));
		echo '<p>' . esc_html__('Private survey link for collecting post-event feedback.', 'backstage-venue-manager') . '</p>';
		echo '<input type="text" class="widefat code" readonly value="' . esc_attr($survey_url) . '">';
		$email_preview_url = add_query_arg(array('page' => 'vms-email-followups', 'tab' => 'preview', 'event_plan_id' => $event_plan_id, 'email_key' => 'post_event'), admin_url('admin.php'));
		echo '<p><a class="button button-secondary" href="' . esc_url($admin_url) . '">' . esc_html__('View Responses', 'backstage-venue-manager') . '</a></p>';
		echo '<p><a class="button button-secondary" href="' . esc_url($email_preview_url) . '">' . esc_html__('Preview Feedback Email', 'backstage-venue-manager') . '</a></p>';
	}
}


if (!function_exists('vms_feedback_register_tours')) {
	function vms_feedback_register_tours(array $tours): array
	{
		$tours[] = array(
			'id' => 'vms.event_feedback.basics',
			'title' => __('Event Feedback', 'backstage-venue-manager'),
			'screen' => 'admin:vms-event-feedback',
			'version' => '1.0.1',
			'level' => 'beginner',
			'audience' => array('admin'),
			'steps' => array(
				array(
					'id' => 'event-selector',
					'selector' => '[data-vms-tour="event-feedback.selector"]',
					'title' => __('Start with the event', 'backstage-venue-manager'),
					'body' => __('Pick the Event Plan you want feedback for. The survey pulls the event, primary vendor, and secondary vendors from that plan so you do not rebuild a form by hand.', 'backstage-venue-manager'),
					'position' => 'bottom',
				),
				array(
					'id' => 'survey-link',
					'selector' => '[data-vms-tour="event-feedback.link"]',
					'title' => __('Share one private link', 'backstage-venue-manager'),
					'body' => __('Copy this event-specific survey link into a post-event email, text, or private message. Customer responses stay internal unless you intentionally curate or anonymize them.', 'backstage-venue-manager'),
					'position' => 'bottom',
				),
				array(
					'id' => 'summary',
					'selector' => '[data-vms-tour="event-feedback.summary"]',
					'title' => __('Read the quick signal first', 'backstage-venue-manager'),
					'body' => __('These cards average the key ratings and ignore likely duplicate submissions so double-clicks, refreshes, or email-link retries do not distort the picture.', 'backstage-venue-manager'),
					'position' => 'bottom',
				),
				array(
					'id' => 'vendor-details',
					'selector' => '[data-vms-tour="event-feedback.primary-vendor"]',
					'title' => __('Check vendor-specific patterns', 'backstage-venue-manager'),
					'body' => __('Primary vendor and secondary vendor cards help separate the overall night from specific performance, food vendor, or vendor issues.', 'backstage-venue-manager'),
					'position' => 'bottom',
				),
				array(
					'id' => 'responses',
					'selector' => '[data-vms-tour="event-feedback.responses"]',
					'title' => __('Keep raw comments private', 'backstage-venue-manager'),
					'body' => __('Use the detailed responses for operator review. Avoid sharing raw customer-identifiable comments with vendors unless you choose to summarize or anonymize them. If an early duplicate slips through, use Delete response to remove it from this view.', 'backstage-venue-manager'),
					'position' => 'top',
				),
			),
		);
		return $tours;
	}
}
add_filter('vms_tours_register', 'vms_feedback_register_tours');
