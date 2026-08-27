<?php
/**
 * Public one-page Event Feedback survey.
 */

defined('ABSPATH') || exit;

if (!function_exists('bvmgr_feedback_public_query_vars')) {
	function bvmgr_feedback_public_query_vars(array $vars): array
	{
		$vars[] = 'vms_event_feedback';
		$vars[] = 'event_plan_id';
		$vars[] = 'key';
		$vars[] = 'invite';
		$vars[] = 'recipient';
		$vars[] = 'source';
		$vars[] = 'vms_feedback_submitted';
		return $vars;
	}
}
add_filter('query_vars', 'bvmgr_feedback_public_query_vars');

if (!function_exists('bvmgr_feedback_public_query_value')) {
	function bvmgr_feedback_public_query_value(string $key): string
	{
		$value = get_query_var($key, '');
		if (is_scalar($value) && (string) $value !== '') {
			return trim((string) $value);
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$query_value = array_key_exists($key, $_GET) ? bvmgr_request_read_scalar($_GET, $key) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ($query_value !== null) {
			return $query_value;
		}
		return '';
	}
}

if (!function_exists('bvmgr_feedback_post_array')) {
	function bvmgr_feedback_post_array(string $key): array
	{
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = isset($_POST[$key]) && is_array($_POST[$key]) ? wp_unslash($_POST[$key]) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return is_array($value) ? $value : array();
	}
}

if (!function_exists('bvmgr_feedback_is_public_survey_request')) {
	function bvmgr_feedback_is_public_survey_request(): bool
	{
		return bvmgr_feedback_public_query_value('vms_event_feedback') === '1';
	}
}

if (!function_exists('bvmgr_feedback_enqueue_public_assets')) {
	function bvmgr_feedback_enqueue_public_assets(): void
	{
		if (!bvmgr_feedback_is_public_survey_request()) {
			return;
		}
		wp_enqueue_style('vms-event-feedback', BVMGR_PLUGIN_URL . 'assets/css/vms-event-feedback.css', array(), bvmgr_asset_version());
		wp_enqueue_script('vms-event-feedback', BVMGR_PLUGIN_URL . 'assets/js/vms-event-feedback.js', array(), bvmgr_asset_version(), true);
	}
}
add_action('wp_enqueue_scripts', 'bvmgr_feedback_enqueue_public_assets');

if (!function_exists('bvmgr_feedback_render_rating_field')) {
	function bvmgr_feedback_render_rating_field(string $name, string $label, int $value = 0, bool $required = false): void
	{
		$options = bvmgr_feedback_rating_options();
		$field_id = 'vms-feedback-' . sanitize_html_class(str_replace(array('[', ']'), '-', $name));
		echo '<div class="vms-feedback-field vms-feedback-field--rating">';
		echo '<label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>';
		echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($name) . '"' . ($required ? ' required' : '') . '>';
		echo '<option value="">' . esc_html__('Choose...', 'backstage-venue-manager') . '</option>';
		foreach ($options as $rating => $rating_label) {
			echo '<option value="' . esc_attr((string) $rating) . '"' . selected($value, (int) $rating, false) . '>' . esc_html((string) $rating . ' - ' . $rating_label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_feedback_render_choice_field')) {
	function bvmgr_feedback_render_choice_field(string $name, string $label, array $options, string $value = ''): void
	{
		$field_id = 'vms-feedback-' . sanitize_html_class(str_replace(array('[', ']'), '-', $name));
		echo '<div class="vms-feedback-field">';
		echo '<label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>';
		echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($name) . '">';
		echo '<option value="">' . esc_html__('Choose...', 'backstage-venue-manager') . '</option>';
		foreach ($options as $option_value => $option_label) {
			echo '<option value="' . esc_attr((string) $option_value) . '"' . selected($value, (string) $option_value, false) . '>' . esc_html((string) $option_label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_feedback_render_checkbox_group')) {
	function bvmgr_feedback_render_checkbox_group(string $name, string $label, array $options): void
	{
		echo '<fieldset class="vms-feedback-checkbox-group">';
		echo '<legend>' . esc_html($label) . '</legend>';
		echo '<div class="vms-feedback-checkbox-grid">';
		foreach ($options as $value => $option_label) {
			echo '<label><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr((string) $value) . '"> ' . esc_html((string) $option_label) . '</label>';
		}
		echo '</div>';
		echo '</fieldset>';
	}
}

if (!function_exists('bvmgr_feedback_render_textarea_field')) {
	function bvmgr_feedback_render_textarea_field(string $name, string $label, string $placeholder = ''): void
	{
		$field_id = 'vms-feedback-' . sanitize_html_class(str_replace(array('[', ']'), '-', $name));
		echo '<div class="vms-feedback-field vms-feedback-field--textarea">';
		echo '<label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>';
		echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($name) . '" rows="3" placeholder="' . esc_attr($placeholder) . '"></textarea>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_feedback_render_public_survey')) {
	/**
	 * @param array<string,string> $invitation
	 */
	function bvmgr_feedback_render_public_survey(int $event_plan_id, string $token, array $invitation = array()): void
	{
		$context = bvmgr_feedback_get_event_context($event_plan_id);
		if (empty($context) || !bvmgr_feedback_verify_public_token($event_plan_id, $token)) {
			echo '<main class="vms-feedback-page"><section class="vms-feedback-card"><h1>' . esc_html__('Feedback link unavailable', 'backstage-venue-manager') . '</h1><p>' . esc_html__('This feedback link is not valid. Please use the survey link provided by the venue.', 'backstage-venue-manager') . '</p></section></main>';
			return;
		}

		$submitted = bvmgr_feedback_public_query_value('vms_feedback_submitted') === '1';
		$event_title = (string) ($context['event_title'] ?? '');
		$event_date = (string) ($context['event_date'] ?? '');
		$venue_title = (string) ($context['venue_title'] ?? get_bloginfo('name'));
		$date_label = '';
		if ($event_date !== '') {
			$ts = strtotime($event_date . ' 12:00:00');
			$date_label = $ts ? date_i18n(get_option('date_format'), $ts) : $event_date;
		}

		echo '<main class="vms-feedback-page">';
		echo '<section class="vms-feedback-card">';
		if ($submitted) {
			echo '<div class="vms-feedback-success"><h1>' . esc_html__('Thank you for the feedback!', 'backstage-venue-manager') . '</h1><p>' . esc_html__('Your response was submitted privately and will help us improve future events.', 'backstage-venue-manager') . '</p></div>';
			echo '</section></main>';
			return;
		}

		echo '<header class="vms-feedback-header">';
		echo '<p class="vms-feedback-eyebrow">' . esc_html__('Private post-event survey', 'backstage-venue-manager') . '</p>';
		/* translators: %s: human-readable value used in this message. */
		echo '<h1>' . esc_html(sprintf(__('How was your night at %s?', 'backstage-venue-manager'), $venue_title)) . '</h1>';
		if ($event_title !== '') {
			echo '<p class="vms-feedback-event-title">' . esc_html($event_title . ($date_label !== '' ? ' · ' . $date_label : '')) . '</p>';
		}
		echo '<p class="vms-feedback-intro">' . esc_html__('This should take less than 2 minutes. Your feedback is private and helps us make future nights better.', 'backstage-venue-manager') . '</p>';
		echo '</header>';

		echo '<form class="vms-feedback-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="vms_submit_event_feedback">';
		echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '">';
		echo '<input type="hidden" name="key" value="' . esc_attr($token) . '">';
		echo '<input type="hidden" name="vms_feedback_submission_uid" value="' . esc_attr(function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('vms-feedback-', true)) . '">';
		if (!empty($invitation['invite'])) {
			echo '<input type="hidden" name="invite" value="' . esc_attr((string) $invitation['invite']) . '">';
		}
		if (!empty($invitation['recipient'])) {
			echo '<input type="hidden" name="recipient" value="' . esc_attr((string) $invitation['recipient']) . '">';
		}
		if (!empty($invitation['source'])) {
			echo '<input type="hidden" name="source" value="' . esc_attr((string) $invitation['source']) . '">';
		}
		echo '<div class="vms-feedback-hp" aria-hidden="true"><label>Leave this field blank <input type="text" name="vms_feedback_company" tabindex="-1" autocomplete="off"></label></div>';
		wp_nonce_field('vms_feedback_submit_' . $event_plan_id, 'vms_feedback_nonce');

		echo '<section class="vms-feedback-section">';
		echo '<h2>' . esc_html__('Quick info', 'backstage-venue-manager') . '</h2>';
		echo '<div class="vms-feedback-grid">';
		echo '<div class="vms-feedback-field"><label for="vms-feedback-name">' . esc_html__('Name (optional)', 'backstage-venue-manager') . '</label><input id="vms-feedback-name" type="text" name="attendee_name" autocomplete="name"></div>';
		echo '<div class="vms-feedback-field"><label for="vms-feedback-email">' . esc_html__('Email (optional)', 'backstage-venue-manager') . '</label><input id="vms-feedback-email" type="email" name="attendee_email" autocomplete="email"></div>';
		echo '</div>';
		bvmgr_feedback_render_rating_field('overall[event_rating]', __('Overall, how was your night?', 'backstage-venue-manager'), 0, true);
		echo '<div class="vms-feedback-grid">';
		bvmgr_feedback_render_choice_field('overall[attend_again]', __('Would you attend another event here?', 'backstage-venue-manager'), bvmgr_feedback_yes_maybe_no_options());
		bvmgr_feedback_render_choice_field('overall[recommend]', __('Would you recommend this event/venue to a friend?', 'backstage-venue-manager'), bvmgr_feedback_yes_maybe_no_options());
		echo '</div>';
		echo '</section>';

		echo '<section class="vms-feedback-section">';
		echo '<h2>' . esc_html($venue_title) . '</h2>';
		echo '<p class="vms-feedback-section-note">' . esc_html__('Quick ratings first. Add details only where you want to.', 'backstage-venue-manager') . '</p>';
		echo '<div class="vms-feedback-grid">';
		bvmgr_feedback_render_rating_field('venue[overall]', __('Overall venue experience', 'backstage-venue-manager'));
		bvmgr_feedback_render_rating_field('venue[bar]', __('Bar experience', 'backstage-venue-manager'));
		bvmgr_feedback_render_rating_field('venue[bathrooms]', __('Bathrooms', 'backstage-venue-manager'));
		bvmgr_feedback_render_rating_field('venue[arrival]', __('Arrival / check-in', 'backstage-venue-manager'));
		bvmgr_feedback_render_rating_field('venue[sound]', __('Sound quality', 'backstage-venue-manager'));
		echo '</div>';
		echo '<details class="vms-feedback-details"><summary>' . esc_html__('Additional bar feedback', 'backstage-venue-manager') . '</summary>';
		bvmgr_feedback_render_checkbox_group('venue[bar_details]', __('What stood out about the bar? Select anything that applies.', 'backstage-venue-manager'), bvmgr_feedback_bar_detail_options());
		bvmgr_feedback_render_textarea_field('venue[bar_comment]', __('Bar comments', 'backstage-venue-manager'), __('Anything we should know about bar service, selection, pricing, or ordering?', 'backstage-venue-manager'));
		echo '</details>';
		echo '<details class="vms-feedback-details"><summary>' . esc_html__('Additional bathroom feedback', 'backstage-venue-manager') . '</summary>';
		bvmgr_feedback_render_checkbox_group('venue[bathroom_details]', __('What stood out about the bathrooms? Select anything that applies.', 'backstage-venue-manager'), bvmgr_feedback_bathroom_detail_options());
		bvmgr_feedback_render_textarea_field('venue[bathroom_comment]', __('Bathroom comments', 'backstage-venue-manager'), __('Anything we should know about cleanliness, supplies, access, or lines?', 'backstage-venue-manager'));
		echo '</details>';
		echo '</section>';

		echo '<section class="vms-feedback-section" data-vms-feedback-role="website">';
		echo '<h2>' . esc_html__('Website / Ticket Purchase Experience', 'backstage-venue-manager') . '</h2>';
		echo '<p class="vms-feedback-section-note">' . esc_html__('Only answer this section if you used the website for this event.', 'backstage-venue-manager') . '</p>';
		bvmgr_feedback_render_choice_field('website[website_used]', __('Did you use our website to buy or attempt to buy tickets for this event?', 'backstage-venue-manager'), bvmgr_feedback_website_usage_options());
		echo '<div class="vms-feedback-conditional-block" data-vms-feedback-website-details="1" hidden>';
		echo '<div class="vms-feedback-grid">';
		bvmgr_feedback_render_rating_field('website[website_find_event]', __('How easy was it to find this event on the website?', 'backstage-venue-manager'));
		bvmgr_feedback_render_rating_field('website[website_ticket_selection]', __('How clear was the ticket selection process?', 'backstage-venue-manager'));
		bvmgr_feedback_render_rating_field('website[website_checkout_smoothness]', __('How smooth was checkout/payment?', 'backstage-venue-manager'));
		bvmgr_feedback_render_rating_field('website[website_confirmation]', __('Were your confirmation email / tickets easy to understand and access?', 'backstage-venue-manager'));
		echo '</div>';
		bvmgr_feedback_render_choice_field('website[website_payment_issues]', __('Did you experience any issues with Apple Pay, card payment, loading, or checkout?', 'backstage-venue-manager'), bvmgr_feedback_website_payment_issue_options());
		bvmgr_feedback_render_textarea_field('website[website_comments]', __('Website / checkout comments', 'backstage-venue-manager'), __('Tell us what worked well or what caused trouble.', 'backstage-venue-manager'));
		echo '</div>';
		echo '</section>';

		$primary = isset($context['primary_vendor']) && is_array($context['primary_vendor']) ? $context['primary_vendor'] : null;
		if ($primary && !empty($primary['id'])) {
			echo '<section class="vms-feedback-section">';
			echo '<h2>' . esc_html((string) $primary['name']) . '</h2>';
			echo '<p class="vms-feedback-section-note">' . esc_html__('Performance / primary vendor feedback.', 'backstage-venue-manager') . '</p>';
			echo '<input type="hidden" name="primary_vendor[id]" value="' . esc_attr((string) absint($primary['id'])) . '">';
			echo '<div class="vms-feedback-grid">';
			bvmgr_feedback_render_rating_field('primary_vendor[performance]', __('How was the performance?', 'backstage-venue-manager'));
			bvmgr_feedback_render_choice_field('primary_vendor[bring_back]', __('Would you like to see them return?', 'backstage-venue-manager'), bvmgr_feedback_yes_maybe_no_options());
			echo '</div>';
			bvmgr_feedback_render_textarea_field('primary_vendor[comment]', __('Any comments about the performance?', 'backstage-venue-manager'), __('What did you like, or what could have been better?', 'backstage-venue-manager'));
			echo '</section>';
		}

		$secondary_vendors = isset($context['secondary_vendors']) && is_array($context['secondary_vendors']) ? $context['secondary_vendors'] : array();
		foreach ($secondary_vendors as $vendor) {
			$vendor_id = absint($vendor['id'] ?? 0);
			if ($vendor_id <= 0) {
				continue;
			}
			$vendor_name = (string) ($vendor['name'] ?? get_the_title($vendor_id));
			$type_label = (string) ($vendor['type_label'] ?? __('Vendor', 'backstage-venue-manager'));
			echo '<section class="vms-feedback-section" data-vms-feedback-role="secondary-vendor">';
			echo '<h2>' . esc_html($vendor_name) . '</h2>';
			/* translators: %s: human-readable value used in this message. */
			echo '<p class="vms-feedback-section-note">' . esc_html(sprintf(__('Feedback for this %s.', 'backstage-venue-manager'), strtolower($type_label))) . '</p>';
			echo '<input type="hidden" name="secondary_vendors[' . esc_attr((string) $vendor_id) . '][id]" value="' . esc_attr((string) $vendor_id) . '">';
			bvmgr_feedback_render_choice_field('secondary_vendors[' . $vendor_id . '][did_order]', __('Did you order from them?', 'backstage-venue-manager'), bvmgr_feedback_secondary_vendor_order_options());
			echo '<div class="vms-feedback-conditional-block" data-vms-feedback-vendor-details="1" hidden>';
			echo '<div class="vms-feedback-grid">';
			bvmgr_feedback_render_rating_field('secondary_vendors[' . $vendor_id . '][wait_time]', __('Wait time / speed', 'backstage-venue-manager'));
			bvmgr_feedback_render_rating_field('secondary_vendors[' . $vendor_id . '][friendliness]', __('Friendliness', 'backstage-venue-manager'));
			bvmgr_feedback_render_rating_field('secondary_vendors[' . $vendor_id . '][selection]', __('Menu / selection', 'backstage-venue-manager'));
			bvmgr_feedback_render_rating_field('secondary_vendors[' . $vendor_id . '][value]', __('Price / value', 'backstage-venue-manager'));
			bvmgr_feedback_render_rating_field('secondary_vendors[' . $vendor_id . '][quality]', __('Quality', 'backstage-venue-manager'));
			bvmgr_feedback_render_rating_field('secondary_vendors[' . $vendor_id . '][accuracy]', __('Order accuracy', 'backstage-venue-manager'));
			echo '</div>';
			bvmgr_feedback_render_choice_field('secondary_vendors[' . $vendor_id . '][bring_back]', __('Would you like us to bring them back?', 'backstage-venue-manager'), bvmgr_feedback_yes_maybe_no_options());
			echo '<details class="vms-feedback-details"><summary>' . esc_html__('If there was a wait, what seemed to cause it?', 'backstage-venue-manager') . '</summary>';
			bvmgr_feedback_render_checkbox_group('secondary_vendors[' . $vendor_id . '][wait_causes]', __('Select any that apply', 'backstage-venue-manager'), bvmgr_feedback_vendor_wait_cause_options());
			echo '</details>';
			bvmgr_feedback_render_textarea_field('secondary_vendors[' . $vendor_id . '][comment]', __('Vendor comments', 'backstage-venue-manager'), __('What was good, and what could have been better?', 'backstage-venue-manager'));
			echo '</div>';
			echo '</section>';
		}

		echo '<section class="vms-feedback-section">';
		echo '<h2>' . esc_html__('Anything else?', 'backstage-venue-manager') . '</h2>';
		bvmgr_feedback_render_textarea_field('final_comment', __('Final comments', 'backstage-venue-manager'), __('Anything else we should know about your night?', 'backstage-venue-manager'));
		echo '</section>';

		echo '<div class="vms-feedback-submit-row"><button type="submit" class="vms-feedback-submit" data-vms-feedback-submit="1" data-vms-submitting-label="' . esc_attr__('Submitting…', 'backstage-venue-manager') . '">' . esc_html__('Submit private feedback', 'backstage-venue-manager') . '</button></div>';
		echo '</form>';
		echo '</section>';
		echo '</main>';
	}
}

if (!function_exists('bvmgr_feedback_template_redirect')) {
	function bvmgr_feedback_template_redirect(): void
	{
		if (!bvmgr_feedback_is_public_survey_request()) {
			return;
		}

		$event_plan_id = absint(bvmgr_feedback_public_query_value('event_plan_id'));
		$token = sanitize_text_field(bvmgr_feedback_public_query_value('key'));
		$invitation = array(
			'invite' => sanitize_text_field(bvmgr_feedback_public_query_value('invite')),
			'recipient' => sanitize_text_field(bvmgr_feedback_public_query_value('recipient')),
			'source' => function_exists('bvmgr_feedback_invitation_source') ? bvmgr_feedback_invitation_source(bvmgr_feedback_public_query_value('source')) : sanitize_key(bvmgr_feedback_public_query_value('source')),
		);

		status_header(200);
		nocache_headers();
		get_header();
		bvmgr_feedback_render_public_survey($event_plan_id, $token, $invitation);
		get_footer();
		exit;
	}
}
add_action('template_redirect', 'bvmgr_feedback_template_redirect');

if (!function_exists('bvmgr_feedback_handle_submit')) {
	function bvmgr_feedback_handle_submit(): void
	{
		$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
		$token = isset($_POST['key']) ? sanitize_text_field(wp_unslash((string) $_POST['key'])) : '';
		$redirect = $event_plan_id > 0 ? bvmgr_feedback_survey_url($event_plan_id) : home_url('/');

		if ($event_plan_id <= 0 || !bvmgr_feedback_verify_public_token($event_plan_id, $token)) {
			wp_die(esc_html__('Invalid feedback link.', 'backstage-venue-manager'));
		}
		if (
			!isset($_POST['vms_feedback_nonce'])
			|| is_array($_POST['vms_feedback_nonce'])
			|| !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['vms_feedback_nonce'])), 'vms_feedback_submit_' . $event_plan_id)
		) {
			wp_die(esc_html__('Feedback form expired. Please refresh and try again.', 'backstage-venue-manager'));
		}
		$honeypot = bvmgr_request_read_scalar($_POST, 'vms_feedback_company');
		if ($honeypot !== '') {
			wp_safe_redirect(add_query_arg('vms_feedback_submitted', '1', $redirect));
			exit;
		}

		$context = bvmgr_feedback_get_event_context($event_plan_id);
		if (empty($context)) {
			wp_die(esc_html__('Event feedback form unavailable.', 'backstage-venue-manager'));
		}

		$allowed_yes_maybe_no = bvmgr_feedback_yes_maybe_no_options();
		$invite = isset($_POST['invite']) ? sanitize_text_field(wp_unslash((string) $_POST['invite'])) : '';
		$recipient_hash = isset($_POST['recipient']) ? sanitize_text_field(wp_unslash((string) $_POST['recipient'])) : '';
		$invite_source = function_exists('bvmgr_feedback_invitation_source') ? bvmgr_feedback_invitation_source(bvmgr_request_read_scalar($_POST, 'source')) : 'manual';
		$submission_uid = isset($_POST['vms_feedback_submission_uid']) ? sanitize_text_field(wp_unslash((string) $_POST['vms_feedback_submission_uid'])) : '';
		$submission_uid_hash = function_exists('bvmgr_feedback_submission_uid_hash') ? bvmgr_feedback_submission_uid_hash($event_plan_id, $submission_uid) : '';
		$request_hash = function_exists('bvmgr_feedback_request_hash') ? bvmgr_feedback_request_hash() : '';
		$submission_locks = array();

		if ($submission_uid_hash !== '' && function_exists('bvmgr_feedback_existing_response_by_meta') && bvmgr_feedback_existing_response_by_meta($event_plan_id, 'submission_uid_hash', $submission_uid_hash) > 0) {
			bvmgr_feedback_dedupe_redirect($redirect, 'submission_uid');
		}
		if ($recipient_hash !== '' && function_exists('bvmgr_feedback_existing_response_by_meta') && bvmgr_feedback_existing_response_by_meta($event_plan_id, 'recipient', $recipient_hash) > 0) {
			bvmgr_feedback_dedupe_redirect($redirect, 'recipient');
		}
		if ($submission_uid_hash !== '') {
			$submission_locks[] = 'uid_' . substr($submission_uid_hash, 0, 64);
		}
		if ($recipient_hash !== '') {
			$submission_locks[] = 'recipient_' . substr(hash('sha256', $event_plan_id . '|' . $recipient_hash), 0, 64);
		}

		$payload = array(
			'context' => array(
				'event_plan_id' => $event_plan_id,
				'event_title' => (string) ($context['event_title'] ?? ''),
				'event_date' => (string) ($context['event_date'] ?? ''),
				'venue_title' => (string) ($context['venue_title'] ?? ''),
			),
			'attendee' => array(
				'name' => isset($_POST['attendee_name']) ? sanitize_text_field(wp_unslash((string) $_POST['attendee_name'])) : '',
				'email' => isset($_POST['attendee_email']) ? sanitize_email(wp_unslash((string) $_POST['attendee_email'])) : '',
			),
			'invitation' => array(
				'invite' => $invite,
				'recipient' => $recipient_hash,
				'source' => $invite_source,
			),
			'overall' => array(),
			'venue' => array(),
			'website' => array(),
			'primary_vendor' => array(),
			'secondary_vendors' => array(),
			'final_comment' => isset($_POST['final_comment']) ? sanitize_textarea_field(wp_unslash((string) $_POST['final_comment'])) : '',
			'submitted_at_gmt' => current_time('mysql', true),
		);

		$overall = bvmgr_feedback_post_array('overall');
		$payload['overall'] = array(
			'event_rating' => bvmgr_feedback_sanitize_rating($overall['event_rating'] ?? 0),
			'attend_again' => bvmgr_feedback_sanitize_choice($overall['attend_again'] ?? '', $allowed_yes_maybe_no),
			'recommend' => bvmgr_feedback_sanitize_choice($overall['recommend'] ?? '', $allowed_yes_maybe_no),
		);

		$venue = bvmgr_feedback_post_array('venue');
		$payload['venue'] = array(
			'overall' => bvmgr_feedback_sanitize_rating($venue['overall'] ?? 0),
			'bar' => bvmgr_feedback_sanitize_rating($venue['bar'] ?? 0),
			'bathrooms' => bvmgr_feedback_sanitize_rating($venue['bathrooms'] ?? 0),
			'arrival' => bvmgr_feedback_sanitize_rating($venue['arrival'] ?? 0),
			'sound' => bvmgr_feedback_sanitize_rating($venue['sound'] ?? 0),
			'bar_details' => bvmgr_feedback_sanitize_checkbox_list($venue['bar_details'] ?? array(), function_exists('bvmgr_feedback_bar_detail_allowed_options') ? bvmgr_feedback_bar_detail_allowed_options() : bvmgr_feedback_bar_detail_options()),
			'bar_comment' => sanitize_textarea_field((string) ($venue['bar_comment'] ?? '')),
			'bathroom_details' => bvmgr_feedback_sanitize_checkbox_list($venue['bathroom_details'] ?? array(), function_exists('bvmgr_feedback_bathroom_detail_allowed_options') ? bvmgr_feedback_bathroom_detail_allowed_options() : bvmgr_feedback_bathroom_detail_options()),
			'bathroom_comment' => sanitize_textarea_field((string) ($venue['bathroom_comment'] ?? '')),
		);

		$website = bvmgr_feedback_post_array('website');
		$website_used = bvmgr_feedback_sanitize_choice($website['website_used'] ?? '', bvmgr_feedback_website_usage_options());
		$website_details_enabled = function_exists('bvmgr_feedback_website_details_enabled') ? bvmgr_feedback_website_details_enabled($website_used) : ($website_used !== '' && $website_used !== 'did_not_use');
		$payload['website'] = array(
			'website_used' => $website_used,
			'website_find_event' => $website_details_enabled ? bvmgr_feedback_sanitize_rating($website['website_find_event'] ?? 0) : 0,
			'website_ticket_selection' => $website_details_enabled ? bvmgr_feedback_sanitize_rating($website['website_ticket_selection'] ?? 0) : 0,
			'website_checkout_smoothness' => $website_details_enabled ? bvmgr_feedback_sanitize_rating($website['website_checkout_smoothness'] ?? 0) : 0,
			'website_payment_issues' => $website_details_enabled ? bvmgr_feedback_sanitize_choice($website['website_payment_issues'] ?? '', bvmgr_feedback_website_payment_issue_options()) : '',
			'website_confirmation' => $website_details_enabled ? bvmgr_feedback_sanitize_rating($website['website_confirmation'] ?? 0) : 0,
			'website_comments' => $website_details_enabled ? sanitize_textarea_field((string) ($website['website_comments'] ?? '')) : '',
		);

		$primary = bvmgr_feedback_post_array('primary_vendor');
		$primary_id = absint($primary['id'] ?? 0);
		if ($primary_id > 0) {
			$payload['primary_vendor'] = array(
				'id' => $primary_id,
				'name' => get_the_title($primary_id),
				'performance' => bvmgr_feedback_sanitize_rating($primary['performance'] ?? 0),
				'bring_back' => bvmgr_feedback_sanitize_choice($primary['bring_back'] ?? '', $allowed_yes_maybe_no),
				'comment' => sanitize_textarea_field((string) ($primary['comment'] ?? '')),
			);
		}

		$posted_secondary = bvmgr_feedback_post_array('secondary_vendors');
		$allowed_wait_causes = bvmgr_feedback_vendor_wait_cause_options();
		$allowed_order_choices = bvmgr_feedback_secondary_vendor_order_options();
		foreach ($posted_secondary as $vendor_id => $row) {
			$vendor_id = absint($vendor_id);
			if ($vendor_id <= 0 || !is_array($row)) {
				continue;
			}
			$did_order = bvmgr_feedback_sanitize_choice($row['did_order'] ?? '', $allowed_order_choices);
			$details_enabled = function_exists('bvmgr_feedback_secondary_vendor_details_enabled') ? bvmgr_feedback_secondary_vendor_details_enabled($did_order) : ($did_order === 'yes');
			$payload['secondary_vendors'][$vendor_id] = array(
				'id' => $vendor_id,
				'name' => get_the_title($vendor_id),
				'did_order' => $did_order,
				'wait_time' => $details_enabled ? bvmgr_feedback_sanitize_rating($row['wait_time'] ?? 0) : 0,
				'friendliness' => $details_enabled ? bvmgr_feedback_sanitize_rating($row['friendliness'] ?? 0) : 0,
				'selection' => $details_enabled ? bvmgr_feedback_sanitize_rating($row['selection'] ?? 0) : 0,
				'value' => $details_enabled ? bvmgr_feedback_sanitize_rating($row['value'] ?? 0) : 0,
				'quality' => $details_enabled ? bvmgr_feedback_sanitize_rating($row['quality'] ?? 0) : 0,
				'accuracy' => $details_enabled ? bvmgr_feedback_sanitize_rating($row['accuracy'] ?? 0) : 0,
				'bring_back' => $details_enabled ? bvmgr_feedback_sanitize_choice($row['bring_back'] ?? '', $allowed_yes_maybe_no) : '',
				'wait_causes' => $details_enabled ? bvmgr_feedback_sanitize_checkbox_list($row['wait_causes'] ?? array(), $allowed_wait_causes) : array(),
				'comment' => $details_enabled ? sanitize_textarea_field((string) ($row['comment'] ?? '')) : '',
			);
		}

		$attendee_email_hash = !empty($payload['attendee']['email']) && function_exists('bvmgr_feedback_recipient_hash') ? bvmgr_feedback_recipient_hash((string) $payload['attendee']['email']) : '';
		if ($attendee_email_hash !== '' && function_exists('bvmgr_feedback_existing_response_by_meta') && bvmgr_feedback_existing_response_by_meta($event_plan_id, 'attendee_email_hash', $attendee_email_hash) > 0) {
			bvmgr_feedback_dedupe_redirect($redirect, 'attendee_email');
		}
		if ($attendee_email_hash !== '') {
			$submission_locks[] = 'email_' . substr(hash('sha256', $event_plan_id . '|' . $attendee_email_hash), 0, 64);
		}

		$duplicate_fingerprint = function_exists('bvmgr_feedback_payload_duplicate_key') ? bvmgr_feedback_payload_duplicate_key($payload) : '';
		if ($duplicate_fingerprint !== '' && $request_hash !== '' && function_exists('bvmgr_feedback_existing_recent_duplicate') && bvmgr_feedback_existing_recent_duplicate($event_plan_id, $duplicate_fingerprint, $request_hash) > 0) {
			bvmgr_feedback_dedupe_redirect($redirect, 'fingerprint');
		}
		if ($duplicate_fingerprint !== '' && $request_hash !== '') {
			$submission_locks[] = 'request_' . substr(hash('sha256', $event_plan_id . '|' . $duplicate_fingerprint . '|' . $request_hash), 0, 64);
		}

		$claimed_locks = array();
		foreach (array_values(array_unique($submission_locks)) as $lock_key) {
			if (function_exists('bvmgr_feedback_claim_submission_lock') && !bvmgr_feedback_claim_submission_lock($lock_key, 300)) {
				bvmgr_feedback_dedupe_redirect($redirect, 'locked');
			}
			$claimed_locks[] = $lock_key;
		}

		$title = sprintf(
			/* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
			__('Feedback: %1$s - %2$s', 'backstage-venue-manager'),
			(string) ($context['event_title'] ?? ('Event Plan #' . $event_plan_id)),
			wp_date('M j, Y g:i a')
		);
		$response_id = wp_insert_post(array(
			'post_type' => BVMGR_CPT_FEEDBACK_RESPONSE,
			'post_status' => 'private',
			'post_title' => $title,
			'post_content' => (string) $payload['final_comment'],
		), true);

		if (is_wp_error($response_id) || absint($response_id) <= 0) {
			foreach ($claimed_locks as $lock_key) {
				if (function_exists('bvmgr_feedback_release_submission_lock')) {
					bvmgr_feedback_release_submission_lock($lock_key);
				}
			}
			wp_die(esc_html__('Feedback could not be saved. Please try again.', 'backstage-venue-manager'));
		}

		$response_id = absint($response_id);
		update_post_meta($response_id, bvmgr_feedback_meta_key('event_plan_id'), $event_plan_id);
		update_post_meta($response_id, bvmgr_feedback_meta_key('payload'), $payload);
		update_post_meta($response_id, bvmgr_feedback_meta_key('attendee_email'), $payload['attendee']['email']);
		update_post_meta($response_id, bvmgr_feedback_meta_key('submitted_at_gmt'), $payload['submitted_at_gmt']);
		if ($submission_uid_hash !== '') {
			update_post_meta($response_id, bvmgr_feedback_meta_key('submission_uid_hash'), $submission_uid_hash);
		}
		if ($attendee_email_hash !== '') {
			update_post_meta($response_id, bvmgr_feedback_meta_key('attendee_email_hash'), $attendee_email_hash);
		}
		if ($duplicate_fingerprint !== '') {
			update_post_meta($response_id, bvmgr_feedback_meta_key('duplicate_fingerprint'), $duplicate_fingerprint);
		}
		if ($request_hash !== '') {
			update_post_meta($response_id, bvmgr_feedback_meta_key('request_hash'), $request_hash);
		}
		foreach ($claimed_locks as $lock_key) {
			if (function_exists('bvmgr_feedback_release_submission_lock')) {
				bvmgr_feedback_release_submission_lock($lock_key);
			}
		}
		if ($invite !== '') {
			update_post_meta($response_id, bvmgr_feedback_meta_key('invite'), $invite);
		}
		if ($recipient_hash !== '') {
			update_post_meta($response_id, bvmgr_feedback_meta_key('recipient'), $recipient_hash);
		}
		if (function_exists('vms_email_followups_log')) {
			vms_email_followups_log(array(
				'action' => 'feedback_submission',
				'email_key' => 'post_event',
				'event_plan_id' => $event_plan_id,
				'recipient' => (string) ($payload['attendee']['email'] ?? ''),
				'status' => 'submitted',
				'message' => 'feedback_submitted',
				'meta' => array(
					'response_id' => $response_id,
					'invite' => $invite !== '',
					'source' => $invite_source,
				),
			));
		}
		if (function_exists('bvmgr_feedback_send_new_submission_notification')) {
			bvmgr_feedback_send_new_submission_notification($response_id, $payload);
		}

		wp_safe_redirect(add_query_arg('vms_feedback_submitted', '1', $redirect));
		exit;
	}
}
add_action('admin_post_nopriv_vms_submit_event_feedback', 'bvmgr_feedback_handle_submit');
add_action('admin_post_vms_submit_event_feedback', 'bvmgr_feedback_handle_submit');

# load
