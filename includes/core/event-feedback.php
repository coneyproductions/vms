<?php
/**
 * VMS Event Feedback MVP.
 *
 * Private, event-scoped customer survey responses for post-event review.
 */

defined('ABSPATH') || exit;

if (!defined('BVMGR_CPT_FEEDBACK_RESPONSE')) {
	define('BVMGR_CPT_FEEDBACK_RESPONSE', 'vms_feedback');
}

if (!function_exists('vms_register_feedback_response_cpt')) {
	function vms_register_feedback_response_cpt(): void
	{
		register_post_type(BVMGR_CPT_FEEDBACK_RESPONSE, array(
			'labels' => array(
				'name' => __('Event Feedback', 'backstage-venue-manager'),
				'singular_name' => __('Event Feedback Response', 'backstage-venue-manager'),
			),
			'public' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'exclude_from_search' => true,
			'supports' => array('title', 'editor'),
			'capability_type' => 'post',
		));
	}
}
add_action('init', 'vms_register_feedback_response_cpt');

if (!function_exists('vms_feedback_meta_key')) {
	function vms_feedback_meta_key(string $key): string
	{
		$key = sanitize_key($key);
		return $key !== '' ? '_vms_feedback_' . $key : '_vms_feedback';
	}
}

if (!function_exists('vms_feedback_public_token')) {
	function vms_feedback_public_token(int $event_plan_id): string
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return '';
		}

		$salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_SALT') ? AUTH_SALT : 'vms-feedback');
		return substr(hash_hmac('sha256', 'vms-feedback|' . $event_plan_id, $salt), 0, 24);
	}
}

if (!function_exists('vms_feedback_verify_public_token')) {
	function vms_feedback_verify_public_token(int $event_plan_id, string $token): bool
	{
		$expected = vms_feedback_public_token($event_plan_id);
		$token = trim($token);
		if ($expected === '' || $token === '') {
			return false;
		}

		return function_exists('hash_equals') ? hash_equals($expected, $token) : ($expected === $token);
	}
}

if (!function_exists('vms_feedback_invitation_source')) {
	function vms_feedback_invitation_source(string $source): string
	{
		$source = sanitize_key($source);
		return $source !== '' ? $source : 'manual';
	}
}

if (!function_exists('vms_feedback_recipient_hash')) {
	function vms_feedback_recipient_hash(string $email): string
	{
		$email = strtolower(sanitize_email($email));
		if (!is_email($email)) {
			return '';
		}

		$salt = function_exists('wp_salt') ? wp_salt('nonce') : (defined('NONCE_SALT') ? NONCE_SALT : 'vms-feedback-recipient');
		return substr(hash_hmac('sha256', $email, $salt), 0, 24);
	}
}

if (!function_exists('vms_feedback_invitation_token')) {
	/**
	 * Build a deterministic, non-guessable invitation marker for post-event email links.
	 *
	 * The public event survey key remains the access gate. This token lets VMS tie a
	 * submission back to the invite path without exposing raw order/customer data in
	 * the URL.
	 *
	 * @param array<string,mixed> $recipient
	 */
	function vms_feedback_invitation_token(int $event_plan_id, array $recipient = array()): string
	{
		$event_plan_id = absint($event_plan_id);
		$email = strtolower(sanitize_email((string) ($recipient['email'] ?? '')));
		if ($event_plan_id <= 0 || !is_email($email)) {
			return '';
		}

		$order_ids = isset($recipient['order_ids']) && is_array($recipient['order_ids']) ? array_map('absint', (array) $recipient['order_ids']) : array();
		$order_ids = array_values(array_unique(array_filter($order_ids)));
		sort($order_ids);

		$salt = function_exists('wp_salt') ? wp_salt('secure_auth') : (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : 'vms-feedback-invite');
		$payload = 'vms-feedback-invite|' . $event_plan_id . '|' . $email . '|' . implode(',', $order_ids);
		return substr(hash_hmac('sha256', $payload, $salt), 0, 32);
	}
}

if (!function_exists('vms_feedback_survey_url')) {
	/**
	 * @param array<string,mixed> $recipient Optional recipient context for email invitations.
	 */
	function vms_feedback_survey_url(int $event_plan_id, array $recipient = array(), string $source = 'manual'): string
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return '';
		}

		$args = array(
			'vms_event_feedback' => '1',
			'event_plan_id' => $event_plan_id,
			'key' => vms_feedback_public_token($event_plan_id),
		);

		$email = sanitize_email((string) ($recipient['email'] ?? ''));
		if (is_email($email)) {
			$invite = vms_feedback_invitation_token($event_plan_id, $recipient);
			$recipient_hash = vms_feedback_recipient_hash($email);
			if ($invite !== '') {
				$args['invite'] = $invite;
			}
			if ($recipient_hash !== '') {
				$args['recipient'] = $recipient_hash;
			}
			$args['source'] = vms_feedback_invitation_source($source);
		}

		return add_query_arg($args, home_url('/'));
	}
}

if (!function_exists('vms_feedback_get_event_plan_date')) {
	function vms_feedback_get_event_plan_date(int $event_plan_id): string
	{
		$key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'date') : '_vms_event_date';
		$date = trim((string) get_post_meta($event_plan_id, $key, true));
		if ($date === '') {
			$date = trim((string) get_post_meta($event_plan_id, '_vms_event_date', true));
		}
		return $date;
	}
}

if (!function_exists('vms_feedback_get_event_plan_venue_id')) {
	function vms_feedback_get_event_plan_venue_id(int $event_plan_id): int
	{
		$key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'venue_id') : '_vms_venue_id';
		$venue_id = absint(get_post_meta($event_plan_id, $key, true));
		if ($venue_id <= 0) {
			$venue_id = absint(get_post_meta($event_plan_id, '_vms_event_plan_venue_id', true));
		}
		return $venue_id;
	}
}

if (!function_exists('vms_feedback_get_primary_vendor_id')) {
	function vms_feedback_get_primary_vendor_id(int $event_plan_id): int
	{
		$key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
		$vendor_id = absint(get_post_meta($event_plan_id, $key, true));
		if ($vendor_id > 0) {
			return $vendor_id;
		}

		$lineup_entries_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'lineup_entries_v1') : '_vms_lineup_entries_v1';
		$entries = get_post_meta($event_plan_id, $lineup_entries_key, true);
		if (is_array($entries)) {
			foreach ($entries as $entry) {
				if (!is_array($entry)) {
					continue;
				}
				$is_primary = !empty($entry['is_primary']) || (isset($entry['role']) && sanitize_key((string) $entry['role']) === 'primary');
				$entry_vendor_id = absint($entry['vendor_id'] ?? 0);
				if ($is_primary && $entry_vendor_id > 0) {
					return $entry_vendor_id;
				}
			}
		}

		return 0;
	}
}

if (!function_exists('vms_feedback_get_secondary_vendor_ids')) {
	/**
	 * @return int[]
	 */
	function vms_feedback_get_secondary_vendor_ids(int $event_plan_id): array
	{
		$key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
		$ids = get_post_meta($event_plan_id, $key, true);
		if (!is_array($ids)) {
			$ids = get_post_meta($event_plan_id, '_vms_secondary_vendor_id', false);
		}

		$ids = array_values(array_unique(array_filter(array_map('absint', (array) $ids), static function ($vendor_id): bool {
			return $vendor_id > 0 && get_post_type($vendor_id) === 'vms_vendor' && get_post_status($vendor_id) !== 'trash';
		})));

		$primary_id = vms_feedback_get_primary_vendor_id($event_plan_id);
		if ($primary_id > 0) {
			$ids = array_values(array_filter($ids, static function ($vendor_id) use ($primary_id): bool {
				return (int) $vendor_id !== (int) $primary_id;
			}));
		}

		return $ids;
	}
}

if (!function_exists('vms_feedback_vendor_type_label')) {
	function vms_feedback_vendor_type_label(int $vendor_id, string $fallback = ''): string
	{
		$vendor_id = absint($vendor_id);
		$labels = array();
		if ($vendor_id > 0) {
			$terms = get_the_terms($vendor_id, 'vms_vendor_type');
			if (is_array($terms)) {
				foreach ($terms as $term) {
					if (isset($term->name) && $term->name !== '') {
						$labels[] = (string) $term->name;
					}
				}
			}
		}
		$label = trim(implode(', ', array_unique($labels)));
		return $label !== '' ? $label : $fallback;
	}
}

if (!function_exists('vms_feedback_get_event_context')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_feedback_get_event_context(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$plan = $event_plan_id > 0 ? get_post($event_plan_id) : null;
		if (!$plan || $plan->post_type !== 'vms_event_plan') {
			return array();
		}

		$venue_id = vms_feedback_get_event_plan_venue_id($event_plan_id);
		$primary_vendor_id = vms_feedback_get_primary_vendor_id($event_plan_id);
		$secondary_vendor_ids = vms_feedback_get_secondary_vendor_ids($event_plan_id);
		$venue_title = $venue_id > 0 ? get_the_title($venue_id) : '';
		if (!is_string($venue_title) || trim($venue_title) === '') {
			$venue_title = get_bloginfo('name');
		}

		$secondary_vendors = array();
		foreach ($secondary_vendor_ids as $vendor_id) {
			$secondary_vendors[] = array(
				'id' => $vendor_id,
				'name' => get_the_title($vendor_id),
				'type_label' => vms_feedback_vendor_type_label($vendor_id, __('Vendor', 'backstage-venue-manager')),
			);
		}

		return array(
			'event_plan_id' => $event_plan_id,
			'event_title' => get_the_title($event_plan_id),
			'event_date' => vms_feedback_get_event_plan_date($event_plan_id),
			'venue_id' => $venue_id,
			'venue_title' => $venue_title,
			'primary_vendor' => $primary_vendor_id > 0 ? array(
				'id' => $primary_vendor_id,
				'name' => get_the_title($primary_vendor_id),
				'type_label' => vms_feedback_vendor_type_label($primary_vendor_id, __('Primary vendor', 'backstage-venue-manager')),
			) : null,
			'secondary_vendors' => $secondary_vendors,
		);
	}
}

if (!function_exists('vms_feedback_rating_options')) {
	/**
	 * @return array<int,string>
	 */
	function vms_feedback_rating_options(): array
	{
		return array(
			5 => __('Excellent', 'backstage-venue-manager'),
			4 => __('Good', 'backstage-venue-manager'),
			3 => __('Okay', 'backstage-venue-manager'),
			2 => __('Needs work', 'backstage-venue-manager'),
			1 => __('Poor', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_feedback_yes_maybe_no_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_feedback_yes_maybe_no_options(): array
	{
		return array(
			'yes' => __('Yes', 'backstage-venue-manager'),
			'maybe' => __('Maybe', 'backstage-venue-manager'),
			'no' => __('No', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_feedback_secondary_vendor_order_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_feedback_secondary_vendor_order_options(): array
	{
		return array(
			'yes' => __('Yes', 'backstage-venue-manager'),
			'no' => __('No', 'backstage-venue-manager'),
			'wanted_to_but_did_not' => __('I wanted to, but did not', 'backstage-venue-manager'),
			'not_sure' => __("I'm not sure / don't remember", 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_feedback_website_usage_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_feedback_website_usage_options(): array
	{
		return array(
			'bought_online' => __('Yes, I bought tickets online', 'backstage-venue-manager'),
			'tried_issue' => __('I tried, but had an issue', 'backstage-venue-manager'),
			'looked_bought_door' => __('I looked online but bought at the door', 'backstage-venue-manager'),
			'did_not_use' => __('No, I did not use the website', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_feedback_website_payment_issue_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_feedback_website_payment_issue_options(): array
	{
		return array(
			'no_issues' => __('No issues', 'backstage-venue-manager'),
			'apple_pay_issue' => __('Apple Pay issue', 'backstage-venue-manager'),
			'card_payment_issue' => __('Card/payment issue', 'backstage-venue-manager'),
			'page_slow_or_stuck' => __('Page was slow or stuck', 'backstage-venue-manager'),
			'ticket_selection_or_cart_issue' => __('Ticket selection/cart issue', 'backstage-venue-manager'),
			'other' => __('Other', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_feedback_website_details_enabled')) {
	function vms_feedback_website_details_enabled(string $website_used): bool
	{
		$website_used = sanitize_key($website_used);
		return $website_used !== '' && $website_used !== 'did_not_use';
	}
}

if (!function_exists('vms_feedback_secondary_vendor_details_enabled')) {
	function vms_feedback_secondary_vendor_details_enabled(string $did_order): bool
	{
		return sanitize_key($did_order) === 'yes';
	}
}

if (!function_exists('vms_feedback_sanitize_rating')) {
	function vms_feedback_sanitize_rating($value): int
	{
		$value = absint($value);
		return ($value >= 1 && $value <= 5) ? $value : 0;
	}
}

if (!function_exists('vms_feedback_sanitize_choice')) {
	function vms_feedback_sanitize_choice($value, array $allowed): string
	{
		$value = sanitize_key((string) $value);
		return isset($allowed[$value]) ? $value : '';
	}
}

if (!function_exists('vms_feedback_sanitize_checkbox_list')) {
	/**
	 * @return string[]
	 */
	function vms_feedback_sanitize_checkbox_list($values, array $allowed): array
	{
		$clean = array();
		foreach ((array) $values as $value) {
			$value = sanitize_key((string) $value);
			if ($value !== '' && isset($allowed[$value])) {
				$clean[] = $value;
			}
		}
		return array_values(array_unique($clean));
	}
}

if (!function_exists('vms_feedback_bar_detail_options')) {
	/** @return array<string,string> */
	function vms_feedback_bar_detail_options(): array
	{
		return array(
			'fast_service' => __('Fast service / short wait', 'backstage-venue-manager'),
			'slow_service' => __('Long wait / slow service', 'backstage-venue-manager'),
			'friendly_staff' => __('Friendly bar staff', 'backstage-venue-manager'),
			'less_friendly_staff' => __('Staff could have been friendlier', 'backstage-venue-manager'),
			'good_selection' => __('Good drink selection', 'backstage-venue-manager'),
			'limited_selection' => __('Limited drink selection', 'backstage-venue-manager'),
			'fair_pricing' => __('Fair pricing / good value', 'backstage-venue-manager'),
			'high_pricing' => __('Pricing felt high', 'backstage-venue-manager'),
			'easy_ordering_payment' => __('Easy ordering / payment', 'backstage-venue-manager'),
			'confusing_ordering_payment' => __('Ordering or payment was confusing', 'backstage-venue-manager'),
			'other_bar_feedback' => __('Other bar feedback', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_feedback_legacy_bar_detail_options')) {
	/** @return array<string,string> */
	function vms_feedback_legacy_bar_detail_options(): array
	{
		return array(
			'wait_time' => __('Wait time', 'backstage-venue-manager'),
			'friendliness' => __('Friendliness', 'backstage-venue-manager'),
			'selection' => __('Selection', 'backstage-venue-manager'),
			'pricing' => __('Pricing / value', 'backstage-venue-manager'),
			'payment' => __('Payment / ordering', 'backstage-venue-manager'),
			'other' => __('Other', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_feedback_bar_detail_allowed_options')) {
	/** @return array<string,string> */
	function vms_feedback_bar_detail_allowed_options(): array
	{
		return vms_feedback_bar_detail_options() + vms_feedback_legacy_bar_detail_options();
	}
}

if (!function_exists('vms_feedback_bathroom_detail_options')) {
	/** @return array<string,string> */
	function vms_feedback_bathroom_detail_options(): array
	{
		return array(
			'clean_and_well_kept' => __('Clean and well-kept', 'backstage-venue-manager'),
			'needed_cleaning' => __('Needed cleaning', 'backstage-venue-manager'),
			'supplies_stocked' => __('Supplies were stocked', 'backstage-venue-manager'),
			'supplies_low_or_missing' => __('Supplies were low or missing', 'backstage-venue-manager'),
			'lighting_good' => __('Lighting was good', 'backstage-venue-manager'),
			'lighting_needed_attention' => __('Lighting needed attention', 'backstage-venue-manager'),
			'easy_to_find_access' => __('Easy to find / access', 'backstage-venue-manager'),
			'hard_to_find_access' => __('Hard to find / access', 'backstage-venue-manager'),
			'little_or_no_wait' => __('Little or no wait', 'backstage-venue-manager'),
			'long_line_wait' => __('Long line / wait', 'backstage-venue-manager'),
			'other_bathroom_feedback' => __('Other bathroom feedback', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_feedback_legacy_bathroom_detail_options')) {
	/** @return array<string,string> */
	function vms_feedback_legacy_bathroom_detail_options(): array
	{
		return array(
			'cleanliness' => __('Cleanliness', 'backstage-venue-manager'),
			'supplies' => __('Supplies stocked', 'backstage-venue-manager'),
			'lighting' => __('Lighting', 'backstage-venue-manager'),
			'access' => __('Location / access', 'backstage-venue-manager'),
			'line' => __('Line / wait', 'backstage-venue-manager'),
			'other' => __('Other', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_feedback_bathroom_detail_allowed_options')) {
	/** @return array<string,string> */
	function vms_feedback_bathroom_detail_allowed_options(): array
	{
		return vms_feedback_bathroom_detail_options() + vms_feedback_legacy_bathroom_detail_options();
	}
}

if (!function_exists('vms_feedback_vendor_wait_cause_options')) {
	/** @return array<string,string> */
	function vms_feedback_vendor_wait_cause_options(): array
	{
		return array(
			'line_before_ordering' => __('Long line before ordering', 'backstage-venue-manager'),
			'after_ordering' => __('Long wait after ordering', 'backstage-venue-manager'),
			'understaffed' => __('Seemed understaffed', 'backstage-venue-manager'),
			'payment_ordering' => __('Ordering / payment was slow', 'backstage-venue-manager'),
			'menu_complexity' => __('Menu seemed complicated', 'backstage-venue-manager'),
			'ran_out' => __('They ran out of items', 'backstage-venue-manager'),
			'not_sure' => __('Not sure', 'backstage-venue-manager'),
			'other' => __('Other', 'backstage-venue-manager'),
		);
	}
}



if (!function_exists('vms_feedback_notification_defaults')) {
	/**
	 * @return array{enabled:bool,recipients:string[]}
	 */
	function vms_feedback_notification_defaults(): array
	{
		$admin_email = sanitize_email((string) get_option('admin_email'));
		return array(
			'enabled' => false,
			'recipients' => is_email($admin_email) ? array($admin_email) : array(),
		);
	}
}

if (!function_exists('vms_feedback_parse_notification_recipients')) {
	/**
	 * @return string[]
	 */
	function vms_feedback_parse_notification_recipients($raw): array
	{
		$parts = is_array($raw) ? $raw : preg_split('/[\s,;]+/', (string) $raw);
		$emails = array();
		foreach ((array) $parts as $part) {
			$email = sanitize_email(trim((string) $part));
			if (is_email($email)) {
				$emails[] = strtolower($email);
			}
		}
		return array_values(array_unique($emails));
	}
}

if (!function_exists('vms_feedback_get_notification_settings')) {
	/**
	 * @return array{enabled:bool,recipients:string[]}
	 */
	function vms_feedback_get_notification_settings(): array
	{
		$defaults = vms_feedback_notification_defaults();
		$enabled = (string) get_option('vms_feedback_notify_enabled', '0') === '1';
		$stored_recipients = get_option('vms_feedback_notify_recipients', '');
		$recipients = vms_feedback_parse_notification_recipients($stored_recipients);
		if (empty($recipients)) {
			$recipients = $defaults['recipients'];
		}
		return array(
			'enabled' => $enabled,
			'recipients' => $recipients,
		);
	}
}

if (!function_exists('vms_feedback_save_notification_settings')) {
	function vms_feedback_save_notification_settings(bool $enabled, $recipients): bool
	{
		$emails = vms_feedback_parse_notification_recipients($recipients);
		if (empty($emails)) {
			$emails = vms_feedback_notification_defaults()['recipients'];
		}
		update_option('vms_feedback_notify_enabled', $enabled ? '1' : '0', false);
		update_option('vms_feedback_notify_recipients', implode(', ', $emails), false);
		return true;
	}
}

if (!function_exists('vms_feedback_response_admin_url')) {
	function vms_feedback_response_admin_url(int $event_plan_id, int $response_id = 0): string
	{
		$args = array(
			'page' => 'vms-event-feedback',
			'event_plan_id' => absint($event_plan_id),
		);
		$url = add_query_arg($args, admin_url('admin.php'));
		if ($response_id > 0) {
			$url .= '#vms-feedback-response-' . absint($response_id);
		}
		return $url;
	}
}

if (!function_exists('vms_feedback_send_new_submission_notification')) {
	/**
	 * @param array<string,mixed> $payload
	 */
	function vms_feedback_send_new_submission_notification(int $response_id, array $payload): bool
	{
		$settings = vms_feedback_get_notification_settings();
		if (empty($settings['enabled']) || empty($settings['recipients'])) {
			return false;
		}

		$context = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : array();
		$attendee = isset($payload['attendee']) && is_array($payload['attendee']) ? $payload['attendee'] : array();
		$event_plan_id = absint($context['event_plan_id'] ?? get_post_meta($response_id, vms_feedback_meta_key('event_plan_id'), true));
		$event_title = trim((string) ($context['event_title'] ?? ''));
		if ($event_title === '' && $event_plan_id > 0) {
			$event_title = get_the_title($event_plan_id);
		}
		if ($event_title === '') {
			$event_title = __('an event', 'backstage-venue-manager');
		}
		$attendee_name = trim((string) ($attendee['name'] ?? ''));
		$attendee_email = trim((string) ($attendee['email'] ?? ''));
		$overall_rating = vms_feedback_payload_rating($payload, 'overall.event_rating');
		$admin_url = vms_feedback_response_admin_url($event_plan_id, $response_id);
		/* translators: %s: new event feedback. */
		$subject = sprintf(__('New event feedback: %s', 'backstage-venue-manager'), wp_strip_all_tags($event_title));
		$lines = array(
			/* translators: %s: event title receiving the feedback response. */
			sprintf(__('A new private Event Feedback response was submitted for %s.', 'backstage-venue-manager'), wp_strip_all_tags($event_title)),
			'',
			/* translators: %s: submitted by. */
			sprintf(__('Submitted by: %s', 'backstage-venue-manager'), $attendee_name !== '' ? $attendee_name : __('Anonymous', 'backstage-venue-manager')),
		);
		if ($attendee_email !== '') {
			/* translators: %s: email address. */
			$lines[] = sprintf(__('Email: %s', 'backstage-venue-manager'), $attendee_email);
		}
		if ($overall_rating > 0) {
			/* translators: %d: overall rating. */
			$lines[] = sprintf(__('Overall rating: %d/5', 'backstage-venue-manager'), $overall_rating);
		}
		$final_comment = trim((string) ($payload['final_comment'] ?? ''));
		if ($final_comment !== '') {
			$lines[] = '';
			$lines[] = __('Final comment:', 'backstage-venue-manager');
			$lines[] = wp_strip_all_tags($final_comment);
		}
		$lines[] = '';
		$lines[] = __('Review privately in Backstage Venue Manager:', 'backstage-venue-manager');
		$lines[] = $admin_url;
		$lines[] = '';
		$lines[] = __('Reminder: keep raw comments private unless you intentionally curate or anonymize them.', 'backstage-venue-manager');

		return wp_mail($settings['recipients'], $subject, implode("\n", $lines));
	}
}

if (!function_exists('vms_feedback_get_responses')) {
	/**
	 * @return WP_Post[]
	 */
	function vms_feedback_get_responses(int $event_plan_id = 0, int $limit = 200): array
	{
		$args = array(
			'post_type' => BVMGR_CPT_FEEDBACK_RESPONSE,
			'post_status' => array('private', 'publish'),
			'posts_per_page' => $limit,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
		);
		if ($event_plan_id > 0) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The response list applies this exact Event Plan metadata filter only when requested; the caller-supplied limit continues to control result scope.
				array(
					'key' => vms_feedback_meta_key('event_plan_id'),
					'value' => absint($event_plan_id),
					'compare' => '=',
				),
			);
		}
		return get_posts($args);
	}
}

if (!function_exists('vms_feedback_get_response_payload')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_feedback_get_response_payload(int $response_id): array
	{
		$payload = get_post_meta($response_id, vms_feedback_meta_key('payload'), true);
		return is_array($payload) ? $payload : array();
	}
}

if (!function_exists('vms_feedback_payload_path_value')) {
	/**
	 * @return mixed
	 */
	function vms_feedback_payload_path_value(array $payload, string $path)
	{
		$value = $payload;
		foreach (explode('.', $path) as $part) {
			if (!is_array($value) || !array_key_exists($part, $value)) {
				return null;
			}
			$value = $value[$part];
		}
		return $value;
	}
}

if (!function_exists('vms_feedback_payload_rating')) {
	function vms_feedback_payload_rating(array $payload, string $path): int
	{
		$value = vms_feedback_payload_path_value($payload, $path);
		if ($value === null) {
			return 0;
		}
		return vms_feedback_sanitize_rating($value);
	}
}

if (!function_exists('vms_feedback_average')) {
	/**
	 * @param WP_Post[] $responses
	 */
	function vms_feedback_average(array $responses, string $path): array
	{
		$sum = 0;
		$count = 0;
		foreach ($responses as $response) {
			$payload = vms_feedback_get_response_payload((int) $response->ID);
			$rating = vms_feedback_payload_rating($payload, $path);
			if ($rating > 0) {
				$sum += $rating;
				$count++;
			}
		}
		return array(
			'average' => $count > 0 ? round($sum / $count, 2) : null,
			'count' => $count,
		);
	}
}

if (!function_exists('vms_feedback_average_filtered')) {
	/**
	 * @param WP_Post[] $responses
	 * @param callable  $include_response Receives the response payload and returns true when it should count.
	 * @return array{average:float|null,count:int}
	 */
	function vms_feedback_average_filtered(array $responses, string $path, callable $include_response): array
	{
		$sum = 0;
		$count = 0;
		foreach ($responses as $response) {
			$payload = vms_feedback_get_response_payload((int) $response->ID);
			if (!$include_response($payload)) {
				continue;
			}
			$rating = vms_feedback_payload_rating($payload, $path);
			if ($rating > 0) {
				$sum += $rating;
				$count++;
			}
		}
		return array(
			'average' => $count > 0 ? round($sum / $count, 2) : null,
			'count' => $count,
		);
	}
}


if (!function_exists('vms_feedback_array_for_hash')) {
	/**
	 * @param mixed $value
	 * @return mixed
	 */
	function vms_feedback_array_for_hash($value)
	{
		if (!is_array($value)) {
			return $value;
		}
		ksort($value);
		foreach ($value as $key => $child) {
			$value[$key] = vms_feedback_array_for_hash($child);
		}
		return $value;
	}
}

if (!function_exists('vms_feedback_payload_duplicate_key')) {
	function vms_feedback_payload_duplicate_key(array $payload): string
	{
		$copy = $payload;
		unset($copy['submitted_at_gmt']);
		if (isset($copy['context']) && is_array($copy['context'])) {
			unset($copy['context']['event_title'], $copy['context']['event_date'], $copy['context']['venue_title']);
		}
		$json = wp_json_encode(vms_feedback_array_for_hash($copy));
		return is_string($json) && $json !== '' ? hash('sha256', $json) : '';
	}
}

if (!function_exists('vms_feedback_request_hash')) {
	function vms_feedback_request_hash(): string
	{
		$ip = '';
		foreach (array(
			vms_request_server_value('HTTP_CF_CONNECTING_IP'),
			vms_request_server_value('HTTP_X_FORWARDED_FOR'),
			vms_request_server_value('REMOTE_ADDR'),
		) as $raw) {
			if ($raw === '') {
				continue;
			}
			$ip = trim(explode(',', $raw)[0]);
			break;
		}
		$user_agent = substr(vms_request_server_value('HTTP_USER_AGENT'), 0, 255);
		$language = substr(vms_request_server_value('HTTP_ACCEPT_LANGUAGE'), 0, 80);
		$salt = function_exists('wp_salt') ? wp_salt('logged_in') : (defined('LOGGED_IN_SALT') ? LOGGED_IN_SALT : 'vms-feedback-request');
		return substr(hash_hmac('sha256', strtolower($ip) . '|' . $user_agent . '|' . $language, $salt), 0, 32);
	}
}

if (!function_exists('vms_feedback_response_duplicate_key')) {
	function vms_feedback_response_duplicate_key(WP_Post $response): string
	{
		$stored = (string) get_post_meta((int) $response->ID, vms_feedback_meta_key('duplicate_fingerprint'), true);
		if ($stored !== '') {
			return $stored;
		}
		$payload = vms_feedback_get_response_payload((int) $response->ID);
		return !empty($payload) ? vms_feedback_payload_duplicate_key($payload) : '';
	}
}

if (!function_exists('vms_feedback_partition_duplicate_responses')) {
	/**
	 * @param WP_Post[] $responses
	 * @return array{unique:WP_Post[], duplicate_ids:int[]}
	 */
	function vms_feedback_partition_duplicate_responses(array $responses): array
	{
		$seen = array();
		$unique = array();
		$duplicate_ids = array();
		foreach ($responses as $response) {
			if (!$response instanceof WP_Post) {
				continue;
			}
			$key = vms_feedback_response_duplicate_key($response);
			if ($key !== '' && isset($seen[$key])) {
				$duplicate_ids[] = (int) $response->ID;
				continue;
			}
			if ($key !== '') {
				$seen[$key] = true;
			}
			$unique[] = $response;
		}
		return array('unique' => $unique, 'duplicate_ids' => $duplicate_ids);
	}
}

if (!function_exists('vms_feedback_submission_uid_hash')) {
	function vms_feedback_submission_uid_hash(int $event_plan_id, string $submission_uid): string
	{
		$event_plan_id = absint($event_plan_id);
		$submission_uid = sanitize_text_field($submission_uid);
		if ($event_plan_id <= 0 || $submission_uid === '') {
			return '';
		}
		$salt = function_exists('wp_salt') ? wp_salt('nonce') : (defined('NONCE_SALT') ? NONCE_SALT : 'vms-feedback-submit');
		return hash_hmac('sha256', 'vms-feedback-submit|' . $event_plan_id . '|' . $submission_uid, $salt);
	}
}

if (!function_exists('vms_feedback_existing_response_by_meta')) {
	function vms_feedback_existing_response_by_meta(int $event_plan_id, string $meta_key, string $meta_value): int
	{
		$event_plan_id = absint($event_plan_id);
		$meta_key = sanitize_key($meta_key);
		$meta_value = trim($meta_value);
		if ($event_plan_id <= 0 || $meta_key === '' || $meta_value === '') {
			return 0;
		}
		$matches = get_posts(array(
			'post_type' => BVMGR_CPT_FEEDBACK_RESPONSE,
			'post_status' => array('private', 'publish'),
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Submission deduplication performs one single-ID response lookup by the exact Event Plan and selected metadata token.
				'relation' => 'AND',
				array(
					'key' => vms_feedback_meta_key('event_plan_id'),
					'value' => $event_plan_id,
					'compare' => '=',
				),
				array(
					'key' => vms_feedback_meta_key($meta_key),
					'value' => $meta_value,
					'compare' => '=',
				),
			),
		));
		return !empty($matches[0]) ? absint($matches[0]) : 0;
	}
}

if (!function_exists('vms_feedback_existing_recent_duplicate')) {
	function vms_feedback_existing_recent_duplicate(int $event_plan_id, string $duplicate_fingerprint, string $request_hash, int $window_seconds = 7200): int
	{
		$event_plan_id = absint($event_plan_id);
		$duplicate_fingerprint = trim($duplicate_fingerprint);
		$request_hash = trim($request_hash);
		if ($event_plan_id <= 0 || $duplicate_fingerprint === '' || $request_hash === '') {
			return 0;
		}

		$matches = get_posts(array(
			'post_type' => BVMGR_CPT_FEEDBACK_RESPONSE,
			'post_status' => array('private', 'publish'),
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Recent-submission deduplication performs one single-ID lookup by exact identity fingerprints inside the configured UTC window.
				'relation' => 'AND',
				array(
					'key' => vms_feedback_meta_key('event_plan_id'),
					'value' => $event_plan_id,
					'compare' => '=',
				),
				array(
					'key' => vms_feedback_meta_key('duplicate_fingerprint'),
					'value' => $duplicate_fingerprint,
					'compare' => '=',
				),
				array(
					'key' => vms_feedback_meta_key('request_hash'),
					'value' => $request_hash,
					'compare' => '=',
				),
				array(
					'key' => vms_feedback_meta_key('submitted_at_gmt'),
					'value' => gmdate('Y-m-d H:i:s', time() - max(60, $window_seconds)),
					'compare' => '>=',
					'type' => 'DATETIME',
				),
			),
		));
		return !empty($matches[0]) ? absint($matches[0]) : 0;
	}
}

if (!function_exists('vms_feedback_dedupe_redirect')) {
	function vms_feedback_dedupe_redirect(string $redirect, string $reason = 'duplicate'): void
	{
		wp_safe_redirect(add_query_arg(array(
			'vms_feedback_submitted' => '1',
			'vms_feedback_dedupe' => sanitize_key($reason),
		), $redirect));
		exit;
	}
}

if (!function_exists('vms_feedback_claim_submission_lock')) {
	function vms_feedback_claim_submission_lock(string $lock_key, int $ttl = 300): bool
	{
		$lock_key = preg_replace('/[^a-zA-Z0-9_\-]/', '', $lock_key);
		if (!is_string($lock_key) || $lock_key === '') {
			return true;
		}
		$option = 'vms_feedback_lock_' . substr($lock_key, 0, 120);
		$expires = time() + max(60, $ttl);
		$existing = get_option($option, 0);
		if ($existing && (int) $existing < time()) {
			delete_option($option);
		}
		return add_option($option, $expires, '', false);
	}
}

if (!function_exists('vms_feedback_release_submission_lock')) {
	function vms_feedback_release_submission_lock(string $lock_key): void
	{
		$lock_key = preg_replace('/[^a-zA-Z0-9_\-]/', '', $lock_key);
		if (!is_string($lock_key) || $lock_key === '') {
			return;
		}
		delete_option('vms_feedback_lock_' . substr($lock_key, 0, 120));
	}
}
