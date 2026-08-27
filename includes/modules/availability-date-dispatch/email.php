<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_add_dispatch_email_subject')) {
	function bvmgr_add_dispatch_email_subject(array $request, array $context): string
	{
		$title = trim((string) ($context['event_title'] ?? ''));
		$date = trim((string) bvmgr_add_dispatch_format_date((string) ($context['event_date'] ?? '')));
		if ($title !== '' && $date !== '') {
			/* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
			return sprintf(__('Availability Request: %1$s on %2$s', 'backstage-venue-manager'), $title, $date);
		}
		if ($title !== '') {
			/* translators: %s: availability request. */
			return sprintf(__('Availability Request: %s', 'backstage-venue-manager'), $title);
		}

		return __('Availability Request', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_add_dispatch_email_body_text')) {
	function bvmgr_add_dispatch_email_body_text(array $request, array $response, array $context): string
	{
		$title = trim((string) ($context['event_title'] ?? ''));
		$date = trim((string) bvmgr_add_dispatch_format_date((string) ($context['event_date'] ?? '')));
		$venue = trim((string) ($context['venue_name'] ?? ''));
		$message = trim((string) ($request['message'] ?? ''));
		$yes_url = bvmgr_add_dispatch_build_response_url($response, 'available');
		$no_url = bvmgr_add_dispatch_build_response_url($response, 'unavailable');

		$lines = array(
			__('Availability request from Backstage Venue Manager', 'backstage-venue-manager'),
			'',
		);

		if ($title !== '') {
			$lines[] = __('Event:', 'backstage-venue-manager') . ' ' . $title;
		}
		if ($date !== '') {
			$lines[] = __('Date:', 'backstage-venue-manager') . ' ' . $date;
		}
		if ($venue !== '') {
			$lines[] = __('Venue:', 'backstage-venue-manager') . ' ' . $venue;
		}
		if ($message !== '') {
			$lines[] = '';
			$lines[] = $message;
		}
		if (!empty($request['include_unknown']) || !empty($request['include_no_response'])) {
			$lines[] = '';
			$lines[] = function_exists('bvmgr_add_dispatch_no_response_explanation')
				? bvmgr_add_dispatch_no_response_explanation()
				: __('We’re reaching out because your availability for this date is not currently marked unavailable in the vendor portal.', 'backstage-venue-manager');
		}

		$lines[] = '';
		$lines[] = __('Yes, I am available:', 'backstage-venue-manager') . ' ' . $yes_url;
		$lines[] = __('No, I am not available:', 'backstage-venue-manager') . ' ' . $no_url;
		$lines[] = '';
		$lines[] = __('These links are unique to you and expire automatically.', 'backstage-venue-manager');

		return implode("\n", $lines);
	}
}

if (!function_exists('bvmgr_add_dispatch_email_body_html')) {
	function bvmgr_add_dispatch_email_body_html(array $request, array $response, array $context): string
	{
		$title = trim((string) ($context['event_title'] ?? ''));
		$date = trim((string) bvmgr_add_dispatch_format_date((string) ($context['event_date'] ?? '')));
		$venue = trim((string) ($context['venue_name'] ?? ''));
		$message = trim((string) ($request['message'] ?? ''));
		$yes_url = bvmgr_add_dispatch_build_response_url($response, 'available');
		$no_url = bvmgr_add_dispatch_build_response_url($response, 'unavailable');

		$html = '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#15243d;line-height:1.5;">';
		$html .= '<h2 style="margin:0 0 12px;">' . esc_html__('Availability Request', 'backstage-venue-manager') . '</h2>';
		$html .= '<div style="background:#f5f8fc;border:1px solid #d7e1ee;border-radius:12px;padding:14px 16px;margin:0 0 14px;">';
		if ($title !== '') {
			$html .= '<div><strong>' . esc_html__('Event:', 'backstage-venue-manager') . '</strong> ' . esc_html($title) . '</div>';
		}
		if ($date !== '') {
			$html .= '<div><strong>' . esc_html__('Date:', 'backstage-venue-manager') . '</strong> ' . esc_html($date) . '</div>';
		}
		if ($venue !== '') {
			$html .= '<div><strong>' . esc_html__('Venue:', 'backstage-venue-manager') . '</strong> ' . esc_html($venue) . '</div>';
		}
		$html .= '</div>';
		if ($message !== '') {
			$html .= '<p>' . nl2br(esc_html($message)) . '</p>';
		}
		if (!empty($request['include_unknown']) || !empty($request['include_no_response'])) {
			$html .= '<p>' . esc_html(function_exists('bvmgr_add_dispatch_no_response_explanation')
				? bvmgr_add_dispatch_no_response_explanation()
				: __('We’re reaching out because your availability for this date is not currently marked unavailable in the vendor portal.', 'backstage-venue-manager')) . '</p>';
		}
		$html .= '<div style="display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 10px;">';
		$html .= '<a href="' . esc_url($yes_url) . '" style="display:inline-block;background:#1f7a4c;color:#fff;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:600;">' . esc_html__('YES - I am available', 'backstage-venue-manager') . '</a>';
		$html .= '<a href="' . esc_url($no_url) . '" style="display:inline-block;background:#8b2d2d;color:#fff;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:600;">' . esc_html__('NO - I am not available', 'backstage-venue-manager') . '</a>';
		$html .= '</div>';
		$html .= '<p style="color:#55657d;font-size:13px;margin:10px 0 0;">' . esc_html__('These secure links are unique to you and expire automatically.', 'backstage-venue-manager') . '</p>';
		$html .= '</div>';

		return $html;
	}
}

if (!function_exists('bvmgr_add_dispatch_operator_interest_recipients')) {
	function bvmgr_add_dispatch_operator_interest_recipients(): array
	{
		$emails = array();
		$admin_email = sanitize_email((string) get_option('admin_email', ''));
		if (is_email($admin_email)) {
			$emails[] = $admin_email;
		}

		$emails = array_values(array_unique(array_filter(array_map('sanitize_email', $emails), 'is_email')));
		return (array) apply_filters('vms_add_dispatch_operator_interest_recipients', $emails);
	}
}

if (!function_exists('bvmgr_add_dispatch_send_operator_interest_notification')) {
	function bvmgr_add_dispatch_send_operator_interest_notification(array $request, array $response, array $context): array
	{
		$recipients = bvmgr_add_dispatch_operator_interest_recipients();
		if (empty($recipients)) {
			return array('success' => false, 'reason' => 'no_recipients');
		}

		$vendor_name = (string) get_the_title((int) ($response['vendor_id'] ?? 0));
		$event_title = trim((string) ($context['event_title'] ?? ''));
		$event_date = trim((string) bvmgr_add_dispatch_format_date((string) ($context['event_date'] ?? '')));
		$venue_name = trim((string) ($context['venue_name'] ?? ''));
		$subject = $event_title !== ''
			/* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
			? sprintf(__('Vendor Interest: %1$s for %2$s', 'backstage-venue-manager'), $vendor_name !== '' ? $vendor_name : __('Vendor', 'backstage-venue-manager'), $event_title)
			: __('Vendor Interest Submitted', 'backstage-venue-manager');

		$add_url = bvmgr_add_dispatch_admin_url(array(
			'event_plan_id' => (int) ($context['event_plan_id'] ?? 0),
		));

		$lines = array(
			__('A vendor submitted interest through the vendor portal.', 'backstage-venue-manager'),
			'',
		);
		if ($vendor_name !== '') {
			$lines[] = __('Vendor:', 'backstage-venue-manager') . ' ' . $vendor_name;
		}
		if ($event_title !== '') {
			$lines[] = __('Event:', 'backstage-venue-manager') . ' ' . $event_title;
		}
		if ($event_date !== '') {
			$lines[] = __('Date:', 'backstage-venue-manager') . ' ' . $event_date;
		}
		if ($venue_name !== '') {
			$lines[] = __('Venue:', 'backstage-venue-manager') . ' ' . $venue_name;
		}
		$lines[] = '';
		$lines[] = __('Review in ADD:', 'backstage-venue-manager') . ' ' . $add_url;

		$body_text = implode("
", $lines);
		$body_html = '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#15243d;line-height:1.5;">';
		$body_html .= '<h2 style="margin:0 0 12px;">' . esc_html__('Vendor Interest Submitted', 'backstage-venue-manager') . '</h2>';
		$body_html .= '<div style="background:#f5f8fc;border:1px solid #d7e1ee;border-radius:12px;padding:14px 16px;margin:0 0 14px;">';
		if ($vendor_name !== '') {
			$body_html .= '<div><strong>' . esc_html__('Vendor:', 'backstage-venue-manager') . '</strong> ' . esc_html($vendor_name) . '</div>';
		}
		if ($event_title !== '') {
			$body_html .= '<div><strong>' . esc_html__('Event:', 'backstage-venue-manager') . '</strong> ' . esc_html($event_title) . '</div>';
		}
		if ($event_date !== '') {
			$body_html .= '<div><strong>' . esc_html__('Date:', 'backstage-venue-manager') . '</strong> ' . esc_html($event_date) . '</div>';
		}
		if ($venue_name !== '') {
			$body_html .= '<div><strong>' . esc_html__('Venue:', 'backstage-venue-manager') . '</strong> ' . esc_html($venue_name) . '</div>';
		}
		$body_html .= '</div>';
		$body_html .= '<p><a href="' . esc_url($add_url) . '" style="display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:700;">' . esc_html__('Review in ADD', 'backstage-venue-manager') . '</a></p>';
		$body_html .= '</div>';

		$success = true;
		foreach ($recipients as $email) {
			$result = function_exists('bvmgr_notify_provider_core_email_send')
				? (array) bvmgr_notify_provider_core_email_send(array(
					'to' => $email,
					'subject' => $subject,
					'body_text' => $body_text,
					'body_html' => $body_html,
				))
				: array(
					'success' => wp_mail($email, $subject, $body_text),
					'provider_message_id' => null,
					'error_message' => '',
				);

			$success = $success && !empty($result['success']);

			if (function_exists('bvmgr_notify_insert_log')) {
				bvmgr_notify_insert_log(array(
					'source' => 'availability_date_dispatch',
					'event_key' => 'portal_interest',
					'recipient_user_id' => 0,
					'recipient_address' => $email,
					'channel' => 'email',
					'locale' => get_locale(),
					'template_key' => 'availability_date_dispatch.portal_interest',
					'payload' => array(
						'request_id' => (int) ($request['id'] ?? 0),
						'response_id' => (int) ($response['id'] ?? 0),
						'event_plan_id' => (int) ($request['event_plan_id'] ?? 0),
					),
					'provider' => 'core_email',
					'provider_message_id' => (string) ($result['provider_message_id'] ?? ''),
					'status' => !empty($result['success']) ? 'sent' : 'failed',
					'error_message' => (string) ($result['error_message'] ?? ''),
				));
			}
		}

		return array('success' => $success, 'count' => count($recipients));
	}
}

if (!function_exists('bvmgr_add_dispatch_send_operator_interest_withdraw_notification')) {
	function bvmgr_add_dispatch_send_operator_interest_withdraw_notification(array $request, array $response, array $context): array
	{
		$recipients = bvmgr_add_dispatch_operator_interest_recipients();
		if (empty($recipients)) {
			return array('success' => false, 'reason' => 'no_recipients');
		}

		$vendor_name = (string) get_the_title((int) ($response['vendor_id'] ?? 0));
		$event_title = trim((string) ($context['event_title'] ?? ''));
		$event_date = trim((string) bvmgr_add_dispatch_format_date((string) ($context['event_date'] ?? '')));
		$venue_name = trim((string) ($context['venue_name'] ?? ''));
		$subject = $event_title !== ''
			/* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
			? sprintf(__('Vendor Interest Withdrawn: %1$s for %2$s', 'backstage-venue-manager'), $vendor_name !== '' ? $vendor_name : __('Vendor', 'backstage-venue-manager'), $event_title)
			: __('Vendor Interest Withdrawn', 'backstage-venue-manager');

		$add_url = bvmgr_add_dispatch_admin_url(array(
			'event_plan_id' => (int) ($context['event_plan_id'] ?? 0),
		));

		$lines = array(
			__('A vendor withdrew a previously submitted portal interest request.', 'backstage-venue-manager'),
			'',
		);
		if ($vendor_name !== '') {
			$lines[] = __('Vendor:', 'backstage-venue-manager') . ' ' . $vendor_name;
		}
		if ($event_title !== '') {
			$lines[] = __('Event:', 'backstage-venue-manager') . ' ' . $event_title;
		}
		if ($event_date !== '') {
			$lines[] = __('Date:', 'backstage-venue-manager') . ' ' . $event_date;
		}
		if ($venue_name !== '') {
			$lines[] = __('Venue:', 'backstage-venue-manager') . ' ' . $venue_name;
		}
		$lines[] = '';
		$lines[] = __('Review in ADD:', 'backstage-venue-manager') . ' ' . $add_url;

		$body_text = implode("
", $lines);
		$body_html = '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#15243d;line-height:1.5;">';
		$body_html .= '<h2 style="margin:0 0 12px;">' . esc_html__('Vendor Interest Withdrawn', 'backstage-venue-manager') . '</h2>';
		$body_html .= '<div style="background:#f5f8fc;border:1px solid #d7e1ee;border-radius:12px;padding:14px 16px;margin:0 0 14px;">';
		if ($vendor_name !== '') {
			$body_html .= '<div><strong>' . esc_html__('Vendor:', 'backstage-venue-manager') . '</strong> ' . esc_html($vendor_name) . '</div>';
		}
		if ($event_title !== '') {
			$body_html .= '<div><strong>' . esc_html__('Event:', 'backstage-venue-manager') . '</strong> ' . esc_html($event_title) . '</div>';
		}
		if ($event_date !== '') {
			$body_html .= '<div><strong>' . esc_html__('Date:', 'backstage-venue-manager') . '</strong> ' . esc_html($event_date) . '</div>';
		}
		if ($venue_name !== '') {
			$body_html .= '<div><strong>' . esc_html__('Venue:', 'backstage-venue-manager') . '</strong> ' . esc_html($venue_name) . '</div>';
		}
		$body_html .= '</div>';
		$body_html .= '<p><a href="' . esc_url($add_url) . '" style="display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:700;">' . esc_html__('Review in ADD', 'backstage-venue-manager') . '</a></p>';
		$body_html .= '</div>';

		$success = true;
		foreach ($recipients as $email) {
			$result = function_exists('bvmgr_notify_provider_core_email_send')
				? (array) bvmgr_notify_provider_core_email_send(array(
					'to' => $email,
					'subject' => $subject,
					'body_text' => $body_text,
					'body_html' => $body_html,
				))
				: array(
					'success' => wp_mail($email, $subject, $body_text),
					'provider_message_id' => null,
					'error_message' => '',
				);

			$success = $success && !empty($result['success']);

			if (function_exists('bvmgr_notify_insert_log')) {
				bvmgr_notify_insert_log(array(
					'source' => 'availability_date_dispatch',
					'event_key' => 'portal_interest_withdrawn',
					'recipient_user_id' => 0,
					'recipient_address' => $email,
					'channel' => 'email',
					'locale' => get_locale(),
					'template_key' => 'availability_date_dispatch.portal_interest_withdrawn',
					'payload' => array(
						'request_id' => (int) ($request['id'] ?? 0),
						'response_id' => (int) ($response['id'] ?? 0),
						'event_plan_id' => (int) ($request['event_plan_id'] ?? 0),
					),
					'provider' => 'core_email',
					'provider_message_id' => (string) ($result['provider_message_id'] ?? ''),
					'status' => !empty($result['success']) ? 'sent' : 'failed',
					'error_message' => (string) ($result['error_message'] ?? ''),
				));
			}
		}

		return array('success' => $success, 'count' => count($recipients));
	}
}

if (!function_exists('bvmgr_add_dispatch_send_response_email')) {
	function bvmgr_add_dispatch_send_response_email(array $request, array $response, array $context): array
	{
		$email = sanitize_email((string) ($response['vendor_email'] ?? ''));
		$subject = bvmgr_add_dispatch_email_subject($request, $context);
		$body_text = bvmgr_add_dispatch_email_body_text($request, $response, $context);
		$body_html = bvmgr_add_dispatch_email_body_html($request, $response, $context);

		$result = function_exists('bvmgr_notify_provider_core_email_send')
			? (array) bvmgr_notify_provider_core_email_send(array(
				'to' => $email,
				'subject' => $subject,
				'body_text' => $body_text,
				'body_html' => $body_html,
			))
			: array(
				'success' => wp_mail($email, $subject, $body_text),
				'provider_message_id' => null,
				'error_message' => '',
			);

		if (function_exists('bvmgr_notify_insert_log')) {
			bvmgr_notify_insert_log(array(
				'source' => 'availability_date_dispatch',
				'event_key' => 'availability_request',
				'recipient_user_id' => 0,
				'recipient_address' => $email,
				'channel' => 'email',
				'locale' => get_locale(),
				'template_key' => 'availability_date_dispatch.request',
				'payload' => array(
					'request_id' => (int) ($request['id'] ?? 0),
					'response_id' => (int) ($response['id'] ?? 0),
					'event_plan_id' => (int) ($request['event_plan_id'] ?? 0),
					'choice_url_available' => bvmgr_add_dispatch_build_response_url($response, 'available'),
					'choice_url_unavailable' => bvmgr_add_dispatch_build_response_url($response, 'unavailable'),
				),
				'provider' => 'core_email',
				'provider_message_id' => (string) ($result['provider_message_id'] ?? ''),
				'status' => !empty($result['success']) ? 'sent' : 'failed',
				'error_message' => (string) ($result['error_message'] ?? ''),
			));
		}

		bvmgr_add_dispatch_log(!empty($result['success']) ? 'email_sent' : 'email_failed', array(
			'request_id' => (int) ($request['id'] ?? 0),
			'response_id' => (int) ($response['id'] ?? 0),
			'vendor_id' => (int) ($response['vendor_id'] ?? 0),
			'event_plan_id' => (int) ($request['event_plan_id'] ?? 0),
			'event_date' => (string) ($request['event_date'] ?? ''),
			'source' => 'email',
			'actor_user_id' => get_current_user_id(),
			'details' => array(
				'email' => $email,
				'provider_message_id' => (string) ($result['provider_message_id'] ?? ''),
				'error_message' => (string) ($result['error_message'] ?? ''),
			),
		));

		return $result;
	}
}
