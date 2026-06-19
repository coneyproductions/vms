<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_email_followups_builtin_template_definitions')) {
	function vms_email_followups_builtin_template_definitions(): array
	{
		return array(
			'know_before' => array(
				'label' => __('Know Before You Go', 'vms'),
				'description' => __('Default reminder sent before an event. Scheduled from the show date, not purchase date.', 'vms'),
				'offset_days' => -3,
				'send_hour' => 9,
				'hour_key' => 'send_hour',
				'kind' => 'scheduled',
				'custom' => false,
			),
			'day_of' => array(
				'label' => __('Day-of Reminder', 'vms'),
				'description' => __('Morning-of reminder with simple arrival and venue details.', 'vms'),
				'offset_days' => 0,
				'send_hour' => 9,
				'hour_key' => 'send_hour',
				'kind' => 'scheduled',
				'custom' => false,
			),
			'post_event' => array(
				'label' => __('Post-Event Thank You', 'vms'),
				'description' => __('Follow-up after the show with a private feedback survey link when Event Feedback is available.', 'vms'),
				'offset_days' => 1,
				'send_hour' => 10,
				'hour_key' => 'post_event_hour',
				'kind' => 'scheduled',
				'custom' => false,
			),
			'weather_update' => array(
				'label' => __('Weather / Event Update', 'vms'),
				'description' => __('Manual-only message for event-specific updates.', 'vms'),
				'offset_days' => 0,
				'send_hour' => 9,
				'hour_key' => 'send_hour',
				'kind' => 'manual',
				'custom' => false,
			),
		);
	}
}

if (!function_exists('vms_email_followups_template_definitions')) {
	function vms_email_followups_template_definitions(): array
	{
		$defs = vms_email_followups_builtin_template_definitions();
		$stored = get_option(function_exists('vms_email_followups_option_key') ? vms_email_followups_option_key() : 'vms_email_followups_settings', array());
		if (!is_array($stored)) {
			$stored = array();
		}

		$meta = isset($stored['template_meta']) && is_array($stored['template_meta']) ? (array) $stored['template_meta'] : array();
		foreach ($defs as $key => $def) {
			$incoming = isset($meta[$key]) && is_array($meta[$key]) ? (array) $meta[$key] : array();
			$defs[$key]['kind'] = !empty($incoming['kind']) && $incoming['kind'] === 'manual' ? 'manual' : (string) ($def['kind'] ?? 'scheduled');
			if (isset($incoming['offset_days'])) {
				$defs[$key]['offset_days'] = max(-60, min(60, (int) $incoming['offset_days']));
			}
			if (isset($incoming['send_hour'])) {
				$defs[$key]['send_hour'] = min(23, max(0, (int) $incoming['send_hour']));
			}
		}

		$custom = isset($stored['custom_templates']) && is_array($stored['custom_templates']) ? (array) $stored['custom_templates'] : array();
		foreach ($custom as $key => $def) {
			$key = sanitize_key((string) $key);
			if ($key === '' || isset($defs[$key]) || !is_array($def)) {
				continue;
			}
			$label = sanitize_text_field((string) ($def['label'] ?? ''));
			if ($label === '') {
				$label = __('Custom Follow-Up', 'vms');
			}
			$defs[$key] = array(
				'label' => $label,
				'description' => sanitize_text_field((string) ($def['description'] ?? '')),
				'offset_days' => max(-60, min(60, (int) ($def['offset_days'] ?? 0))),
				'send_hour' => min(23, max(0, (int) ($def['send_hour'] ?? 9))),
				'hour_key' => '',
				'kind' => !empty($def['kind']) && $def['kind'] === 'manual' ? 'manual' : 'scheduled',
				'custom' => true,
			);
		}

		return $defs;
	}
}

if (!function_exists('vms_email_followups_default_templates')) {
	function vms_email_followups_default_templates(): array
	{
		return array(
			'know_before' => array(
				'subject' => 'Everything you need to know for {event_name}',
				'body' => "{customer_greeting}\n\nWe're looking forward to seeing you at {venue_name} for {event_name}.\n\nGates open at {gates_time}. Music starts at {start_time}.\n\nBring lawn chairs or a blanket and settle in for a beautiful East Texas evening.\n\nQuick reminders:\n- No outside food, drinks, or coolers\n- Beer and Kiepersol wine are available at the bar\n- Well-behaved pets are welcome if physically tethered\n- Children 12 & under are free\n- Veterans and active service members are free\n\nYour tickets are attached to your order email, but we can also look you up at the gate if needed.\n\nEvent details: {event_url}\n\n{signature}",
			),
			'day_of' => array(
				'subject' => 'Tonight at {venue_name}: {event_name}',
				'body' => "{customer_greeting}\n\nTonight's the night.\n\nWe'll see you at {venue_name} for {event_name}. Gates open at {gates_time}, and music starts at {start_time}.\n\nBring your lawn chairs or blanket, grab a drink, and enjoy a beautiful East Texas evening with us.\n\nEvent details: {event_url}\n\n{signature}",
			),
			'post_event' => array(
				'subject' => 'Thanks for spending your evening with us',
				'body' => "{customer_greeting}\n\nThank you for coming out to {venue_name} for {event_name}.\n\nWe hope you had a great night with us. If you have a minute, we'd love to hear how the evening went.\n\nYour feedback helps us improve every show - the music, the sound, the check-in process, the bar, the food vendor experience, and everything in between.\n\nLeave private feedback: {feedback_url}\n\nSee upcoming shows: {site_url}\n\n{signature}",
			),
			'weather_update' => array(
				'subject' => 'Update for {event_name}',
				'body' => "{customer_greeting}\n\nHere's the latest update for {event_name} at {venue_name}.\n\nEvent date: {event_date}\nGates open: {gates_time}\nMusic starts: {start_time}\n\nWe'll keep this simple and update you again only if something changes.\n\nEvent details: {event_url}\n\n{signature}",
			),
		);
	}
}

if (!function_exists('vms_email_followups_tokens_help')) {
	function vms_email_followups_tokens_help(): array
	{
		return array(
			'{event_name}' => __('Event title', 'vms'),
			'{event_date}' => __('Formatted event date', 'vms'),
			'{start_time}' => __('Event start time', 'vms'),
			'{end_time}' => __('Event end time', 'vms'),
			'{gates_time}' => __('One hour before event start when possible', 'vms'),
			'{venue_name}' => __('Linked venue name or site name fallback', 'vms'),
			'{event_url}' => __('Public event URL when available', 'vms'),
			'{feedback_url}' => __('Private Event Feedback survey URL when available', 'vms'),
			'{site_url}' => __('Site home URL', 'vms'),
			'{site_name}' => __('Site name', 'vms'),
			'{signature}' => __('Global signature from the Overview tab; blank when no signature is configured', 'vms'),
			'{customer_name}' => __('Recipient/customer full name when known', 'vms'),
			'{customer_first_name}' => __('Recipient/customer first name only when known; blank if unavailable', 'vms'),
			'{customer_greeting}' => __('Safe greeting: "Hi First," when known or "Hi there," when no first name is available', 'vms'),
		);
	}
}

if (!function_exists('vms_email_followups_template_schedule_mode')) {
	function vms_email_followups_template_schedule_mode(array $def): string
	{
		if (($def['kind'] ?? '') === 'manual') {
			return 'manual';
		}
		$offset = (int) ($def['offset_days'] ?? 0);
		if ($offset < 0) {
			return 'before';
		}
		if ($offset > 0) {
			return 'after';
		}
		return 'day_of';
	}
}

if (!function_exists('vms_email_followups_template_timing_label')) {
	function vms_email_followups_template_timing_label(array $def): string
	{
		$mode = vms_email_followups_template_schedule_mode($def);
		$days = abs((int) ($def['offset_days'] ?? 0));
		$hour = min(23, max(0, (int) ($def['send_hour'] ?? 9)));
		$display_hour = $hour % 12;
		if ($display_hour === 0) {
			$display_hour = 12;
		}
		$time = sprintf('%d:00%s', $display_hour, $hour < 12 ? 'am' : 'pm');
		if ($mode === 'manual') {
			return __('Manual only', 'vms');
		}
		if ($mode === 'day_of') {
			return sprintf(__('Day of event at %s', 'vms'), $time);
		}
		if ($mode === 'after') {
			return sprintf(_n('%1$d day after event at %2$s', '%1$d days after event at %2$s', max(1, $days), 'vms'), max(1, $days), $time);
		}
		return sprintf(_n('%1$d day before event at %2$s', '%1$d days before event at %2$s', max(1, $days), 'vms'), max(1, $days), $time);
	}
}
