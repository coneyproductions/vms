<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_email_followups_option_key')) {
	function bvmgr_email_followups_option_key(): string
	{
		return 'vms_email_followups_settings';
	}
}

if (!function_exists('bvmgr_email_followups_default_signature')) {
	function bvmgr_email_followups_default_signature(): string
	{
		return "See you at the show,\nSerenade Range";
	}
}

if (!function_exists('bvmgr_email_followups_default_template_meta')) {
	function bvmgr_email_followups_default_template_meta(): array
	{
		return array(
			'know_before' => array('kind' => 'scheduled', 'offset_days' => -3, 'send_hour' => 9),
			'day_of' => array('kind' => 'scheduled', 'offset_days' => 0, 'send_hour' => 9),
			'post_event' => array('kind' => 'scheduled', 'offset_days' => 1, 'send_hour' => 10),
			'weather_update' => array('kind' => 'manual', 'offset_days' => 0, 'send_hour' => 9),
		);
	}
}

if (!function_exists('bvmgr_email_followups_default_settings')) {
	function bvmgr_email_followups_default_settings(): array
	{
		$admin_email = sanitize_email((string) get_option('admin_email'));
		return array(
			'enabled' => 1,
			'auto_send_enabled' => 0,
			'mailpoet_sync_enabled' => 0,
			'mailpoet_list_id' => '',
			'from_name' => sanitize_text_field((string) get_bloginfo('name')),
			'from_email' => $admin_email,
			'reply_to_email' => $admin_email,
			'test_recipient' => $admin_email,
			'send_hour' => 9,
			'post_event_hour' => 10,
			'reminder_window_hours' => 24,
			'signature' => bvmgr_email_followups_default_signature(),
			'templates_enabled' => array(
				'know_before' => 1,
				'day_of' => 1,
				'post_event' => 1,
				'weather_update' => 1,
			),
			'template_meta' => bvmgr_email_followups_default_template_meta(),
			'custom_templates' => array(),
			'templates' => function_exists('bvmgr_email_followups_default_templates') ? bvmgr_email_followups_default_templates() : array(),
		);
	}
}

if (!function_exists('bvmgr_email_followups_settings')) {
	function bvmgr_email_followups_settings(): array
	{
		$defaults = bvmgr_email_followups_default_settings();
		$stored = get_option(bvmgr_email_followups_option_key(), array());
		if (!is_array($stored)) {
			$stored = array();
		}
		$settings = array_replace_recursive($defaults, $stored);
		$settings['enabled'] = !empty($settings['enabled']) ? 1 : 0;
		$settings['auto_send_enabled'] = !empty($settings['auto_send_enabled']) ? 1 : 0;
		$settings['mailpoet_sync_enabled'] = !empty($settings['mailpoet_sync_enabled']) ? 1 : 0;
		$settings['send_hour'] = min(23, max(0, (int) ($settings['send_hour'] ?? 9)));
		$settings['post_event_hour'] = min(23, max(0, (int) ($settings['post_event_hour'] ?? 10)));
		$settings['reminder_window_hours'] = min(72, max(1, (int) ($settings['reminder_window_hours'] ?? 24)));
		$settings['signature'] = (string) ($settings['signature'] ?? '');
		$settings['templates_enabled'] = is_array($settings['templates_enabled'] ?? null) ? (array) $settings['templates_enabled'] : array();
		$settings['template_meta'] = is_array($settings['template_meta'] ?? null) ? (array) $settings['template_meta'] : array();
		$settings['custom_templates'] = is_array($settings['custom_templates'] ?? null) ? (array) $settings['custom_templates'] : array();
		$settings['templates'] = is_array($settings['templates'] ?? null) ? (array) $settings['templates'] : array();
		return $settings;
	}
}

if (!function_exists('bvmgr_email_followups_unique_custom_key')) {
	function bvmgr_email_followups_unique_custom_key(string $label, array $existing_keys): string
	{
		$base = sanitize_key($label);
		if ($base === '') {
			$base = 'custom_template';
		}
		$base = 'custom_' . preg_replace('/^custom_/', '', $base);
		$key = $base;
		$i = 2;
		while (in_array($key, $existing_keys, true)) {
			$key = $base . '_' . $i;
			$i++;
		}
		return $key;
	}
}

if (!function_exists('bvmgr_email_followups_sanitize_template_schedule')) {
	function bvmgr_email_followups_sanitize_template_schedule(array $input, array $existing = array()): array
	{
		$mode = isset($input['schedule_mode']) ? sanitize_key((string) wp_unslash($input['schedule_mode'])) : '';
		if (!in_array($mode, array('manual', 'before', 'day_of', 'after'), true)) {
			$mode = !empty($existing['kind']) && $existing['kind'] === 'manual' ? 'manual' : 'day_of';
			$existing_offset = (int) ($existing['offset_days'] ?? 0);
			if ($mode !== 'manual') {
				$mode = $existing_offset < 0 ? 'before' : ($existing_offset > 0 ? 'after' : 'day_of');
			}
		}

		$days = isset($input['schedule_days']) ? absint($input['schedule_days']) : abs((int) ($existing['offset_days'] ?? 0));
		$days = min(60, max(0, $days));
		$hour = isset($input['send_hour']) ? (int) $input['send_hour'] : (int) ($existing['send_hour'] ?? 9);
		$hour = min(23, max(0, $hour));

		if ($mode === 'manual') {
			return array('kind' => 'manual', 'offset_days' => 0, 'send_hour' => $hour);
		}
		if ($mode === 'before') {
			return array('kind' => 'scheduled', 'offset_days' => -max(1, $days), 'send_hour' => $hour);
		}
		if ($mode === 'after') {
			return array('kind' => 'scheduled', 'offset_days' => max(1, $days), 'send_hour' => $hour);
		}
		return array('kind' => 'scheduled', 'offset_days' => 0, 'send_hour' => $hour);
	}
}


if (!function_exists('bvmgr_email_followups_clean_template_text')) {
	function bvmgr_email_followups_clean_template_text(string $text): string
	{
		$text = wp_check_invalid_utf8((string) $text, true);
		$map = array(
			'Ã¢Â€Â¢' => '-',
			'â€¢' => '-',
			'â¢' => '-',
			'•' => '-',
			'Ã¢Â€Â™' => "'",
			'â€™' => "'",
			'â' => "'",
			'’' => "'",
			'Ã¢Â€Â˜' => "'",
			'â€˜' => "'",
			'â˜' => "'",
			'‘' => "'",
			'Ã¢Â€Âœ' => '"',
			'â€œ' => '"',
			'â' => '"',
			'“' => '"',
			'Ã¢Â€Â' => '"',
			'â€�' => '"',
			'â' => '"',
			'”' => '"',
			'Ã¢Â€Â“' => '-',
			'â€“' => '-',
			'â' => '-',
			'–' => '-',
			'Ã¢Â€Â”' => '-',
			'â€”' => '-',
			'â' => '-',
			'—' => '-',
			'Ã¢Â€Â¦' => '...',
			'â€¦' => '...',
			'â¦' => '...',
			'…' => '...',
			'Â ' => ' ',
			'Â·' => ' - ',
			'Â' => '',
		);
		$text = str_replace(array_keys($map), array_values($map), $text);
		$text = preg_replace('/\r\n?/', "\n", (string) $text);
		$text = preg_replace('/[ \t]+\n/', "\n", (string) $text);
		$text = preg_replace('/\n{4,}/', "\n\n\n", (string) $text);
		return trim((string) $text);
	}
}

if (!function_exists('bvmgr_email_followups_sanitize_settings')) {
	function bvmgr_email_followups_sanitize_settings(array $input): array
	{
		$defaults = bvmgr_email_followups_default_settings();
		$current = bvmgr_email_followups_settings();
		$out = $current;
		$form_tab = isset($input['_tab']) ? sanitize_key((string) $input['_tab']) : '';
		$updating_templates = $form_tab === 'templates';
		$updating_global = !$updating_templates;

		if ($updating_global) {
			$out['enabled'] = !empty($input['enabled']) ? 1 : 0;
			$out['auto_send_enabled'] = !empty($input['auto_send_enabled']) ? 1 : 0;
			$out['mailpoet_sync_enabled'] = !empty($input['mailpoet_sync_enabled']) ? 1 : 0;
			$out['mailpoet_list_id'] = isset($input['mailpoet_list_id']) ? sanitize_text_field(bvmgr_email_followups_clean_template_text((string) wp_unslash($input['mailpoet_list_id']))) : '';
			$out['from_name'] = isset($input['from_name']) ? sanitize_text_field(bvmgr_email_followups_clean_template_text((string) wp_unslash($input['from_name']))) : (string) $defaults['from_name'];
			$out['from_email'] = isset($input['from_email']) ? sanitize_email((string) wp_unslash($input['from_email'])) : (string) $defaults['from_email'];
			$out['reply_to_email'] = isset($input['reply_to_email']) ? sanitize_email((string) wp_unslash($input['reply_to_email'])) : (string) $defaults['reply_to_email'];
			$out['test_recipient'] = isset($input['test_recipient']) ? sanitize_email((string) wp_unslash($input['test_recipient'])) : (string) $defaults['test_recipient'];
			$out['reminder_window_hours'] = isset($input['reminder_window_hours']) ? min(72, max(1, (int) $input['reminder_window_hours'])) : 24;
			$out['signature'] = isset($input['signature']) ? wp_kses_post(bvmgr_email_followups_clean_template_text((string) wp_unslash($input['signature']))) : (string) $defaults['signature'];
		}

		if ($updating_templates) {
			$builtin_defs = function_exists('bvmgr_email_followups_builtin_template_definitions') ? bvmgr_email_followups_builtin_template_definitions() : array();
			$current_defs = function_exists('bvmgr_email_followups_template_definitions') ? bvmgr_email_followups_template_definitions() : $builtin_defs;
			$out['templates_enabled'] = is_array($out['templates_enabled'] ?? null) ? (array) $out['templates_enabled'] : array();
			$out['template_meta'] = is_array($out['template_meta'] ?? null) ? (array) $out['template_meta'] : array();
			$out['custom_templates'] = is_array($out['custom_templates'] ?? null) ? (array) $out['custom_templates'] : array();
			$out['templates'] = is_array($out['templates'] ?? null) ? (array) $out['templates'] : (array) $defaults['templates'];

			$enabled_in = isset($input['templates_enabled']) && is_array($input['templates_enabled']) ? (array) $input['templates_enabled'] : array();
			$meta_in = isset($input['template_meta']) && is_array($input['template_meta']) ? (array) $input['template_meta'] : array();
			$template_input = isset($input['templates']) && is_array($input['templates']) ? (array) $input['templates'] : array();
			$delete_in = isset($input['delete_custom_templates']) && is_array($input['delete_custom_templates']) ? (array) $input['delete_custom_templates'] : array();

			foreach ($current_defs as $key => $def) {
				$key = sanitize_key((string) $key);
				if ($key === '') {
					continue;
				}

				$is_custom = !empty($def['custom']);
				if ($is_custom && !empty($delete_in[$key])) {
					unset($out['custom_templates'][$key], $out['templates'][$key], $out['templates_enabled'][$key], $out['template_meta'][$key]);
					continue;
				}

				$out['templates_enabled'][$key] = !empty($enabled_in[$key]) ? 1 : 0;
				$existing_schedule = array(
					'kind' => (string) ($def['kind'] ?? 'scheduled'),
					'offset_days' => (int) ($def['offset_days'] ?? 0),
					'send_hour' => (int) ($def['send_hour'] ?? 9),
				);
				$out['template_meta'][$key] = bvmgr_email_followups_sanitize_template_schedule(isset($meta_in[$key]) && is_array($meta_in[$key]) ? (array) $meta_in[$key] : array(), $existing_schedule);

				if ($is_custom) {
					$incoming_meta = isset($meta_in[$key]) && is_array($meta_in[$key]) ? (array) $meta_in[$key] : array();
					$label = isset($incoming_meta['label']) ? sanitize_text_field(bvmgr_email_followups_clean_template_text((string) wp_unslash($incoming_meta['label']))) : (string) ($def['label'] ?? '');
					$description = isset($incoming_meta['description']) ? sanitize_text_field(bvmgr_email_followups_clean_template_text((string) wp_unslash($incoming_meta['description']))) : (string) ($def['description'] ?? '');
					if ($label === '') {
						$label = __('Custom Follow-Up', 'backstage-venue-manager');
					}
					$out['custom_templates'][$key] = array_merge($out['template_meta'][$key], array('label' => $label, 'description' => $description));
				}

				$existing = is_array($out['templates'][$key] ?? null) ? (array) $out['templates'][$key] : (array) ($defaults['templates'][$key] ?? array());
				$incoming = is_array($template_input[$key] ?? null) ? (array) $template_input[$key] : array();
				$out['templates'][$key] = array(
					'subject' => isset($incoming['subject']) ? sanitize_text_field(bvmgr_email_followups_clean_template_text((string) wp_unslash($incoming['subject']))) : (string) ($existing['subject'] ?? ''),
					'body' => isset($incoming['body']) ? wp_kses_post(bvmgr_email_followups_clean_template_text((string) wp_unslash($incoming['body']))) : (string) ($existing['body'] ?? ''),
				);
			}

			$new = isset($input['new_template']) && is_array($input['new_template']) ? (array) $input['new_template'] : array();
			$new_requested = !empty($input['create_new_template']);
			$new_label = isset($new['label']) ? sanitize_text_field(bvmgr_email_followups_clean_template_text((string) wp_unslash($new['label']))) : '';
			$new_subject = isset($new['subject']) ? sanitize_text_field(bvmgr_email_followups_clean_template_text((string) wp_unslash($new['subject']))) : '';
			$new_body = isset($new['body']) ? wp_kses_post(bvmgr_email_followups_clean_template_text((string) wp_unslash($new['body']))) : '';
			if ($new_requested && ($new_label !== '' || $new_subject !== '' || $new_body !== '')) {
				if ($new_label === '') {
					$new_label = __('Custom Follow-Up', 'backstage-venue-manager');
				}
				$key = bvmgr_email_followups_unique_custom_key($new_label, array_keys((array) $out['templates']));
				$schedule = bvmgr_email_followups_sanitize_template_schedule($new, array('kind' => 'manual', 'offset_days' => 0, 'send_hour' => 9));
				$description = isset($new['description']) ? sanitize_text_field(bvmgr_email_followups_clean_template_text((string) wp_unslash($new['description']))) : '';
				$out['custom_templates'][$key] = array_merge($schedule, array('label' => $new_label, 'description' => $description));
				$out['template_meta'][$key] = $schedule;
				$out['templates_enabled'][$key] = 1;
				$out['templates'][$key] = array(
					'subject' => $new_subject !== '' ? $new_subject : $new_label,
					'body' => $new_body !== '' ? $new_body : "{customer_greeting}\n\n\n\n{signature}",
				);
			}
		}

		if (!is_email($out['from_email'])) {
			$out['from_email'] = (string) $defaults['from_email'];
		}
		if (!is_email($out['reply_to_email'])) {
			$out['reply_to_email'] = (string) $out['from_email'];
		}
		if (!is_email($out['test_recipient'])) {
			$out['test_recipient'] = (string) $defaults['test_recipient'];
		}
		if (trim((string) $out['from_name']) === '') {
			$out['from_name'] = (string) $defaults['from_name'];
		}

		return $out;
	}
}


if (!function_exists('bvmgr_email_followups_migrate_template_encoding_609')) {
	function bvmgr_email_followups_migrate_template_encoding_609(): void
	{
		$flag = 'vms_email_followups_template_encoding_migrated_609';
		if (get_option($flag, '') === '1') {
			return;
		}

		$settings = get_option(bvmgr_email_followups_option_key(), array());
		if (!is_array($settings)) {
			update_option($flag, '1', false);
			return;
		}

		$changed = false;
		$clean_value = static function ($value) use (&$changed) {
			if (!is_string($value)) {
				return $value;
			}
			$cleaned = bvmgr_email_followups_clean_template_text($value);
			if ($cleaned !== $value) {
				$changed = true;
			}
			return $cleaned;
		};

		foreach (array('from_name', 'signature') as $field) {
			if (isset($settings[$field]) && is_string($settings[$field])) {
				$settings[$field] = $clean_value($settings[$field]);
			}
		}

		if (!empty($settings['templates']) && is_array($settings['templates'])) {
			foreach ($settings['templates'] as $key => $template) {
				if (!is_array($template)) {
					continue;
				}
				foreach (array('subject', 'body') as $field) {
					if (isset($template[$field]) && is_string($template[$field])) {
						$template[$field] = $clean_value($template[$field]);
					}
				}
				$settings['templates'][$key] = $template;
			}
		}

		if (!empty($settings['custom_templates']) && is_array($settings['custom_templates'])) {
			foreach ($settings['custom_templates'] as $key => $meta) {
				if (!is_array($meta)) {
					continue;
				}
				foreach (array('label', 'description') as $field) {
					if (isset($meta[$field]) && is_string($meta[$field])) {
						$meta[$field] = $clean_value($meta[$field]);
					}
				}
				$settings['custom_templates'][$key] = $meta;
			}
		}

		if ($changed) {
			update_option(bvmgr_email_followups_option_key(), $settings, false);
		}
		update_option($flag, '1', false);
	}
}
add_action('init', 'bvmgr_email_followups_migrate_template_encoding_609', 24);

if (!function_exists('bvmgr_email_followups_migrate_feedback_template_605')) {
	function bvmgr_email_followups_migrate_feedback_template_605(): void
	{
		$flag = 'vms_email_followups_feedback_template_migrated_605';
		if (get_option($flag, '') === '1') {
			return;
		}

		$settings = bvmgr_email_followups_settings();
		$templates = is_array($settings['templates'] ?? null) ? (array) $settings['templates'] : array();
		$post_event = is_array($templates['post_event'] ?? null) ? (array) $templates['post_event'] : array();
		$body = (string) ($post_event['body'] ?? '');
		if ($body !== '' && strpos($body, '{feedback_url}') === false && strpos($body, 'bvmgr_event_feedback') === false && strpos($body, 'vms_event_feedback') === false) {
			$body = rtrim($body) . "\n\nLeave private feedback: {feedback_url}";
			$post_event['body'] = $body;
			$templates['post_event'] = $post_event;
			$settings['templates'] = $templates;
			update_option(bvmgr_email_followups_option_key(), $settings, false);
		}

		update_option($flag, '1', false);
	}
}
add_action('init', 'bvmgr_email_followups_migrate_feedback_template_605', 25);

if (!function_exists('bvmgr_email_followups_migrate_customer_greeting_template_607')) {
	function bvmgr_email_followups_migrate_customer_greeting_template_607(): void
	{
		$flag = 'vms_email_followups_customer_greeting_template_migrated_607';
		if (get_option($flag, '') === '1') {
			return;
		}

		$settings = bvmgr_email_followups_settings();
		$templates = is_array($settings['templates'] ?? null) ? (array) $settings['templates'] : array();
		$changed = false;
		foreach (array_keys(function_exists('bvmgr_email_followups_template_definitions') ? bvmgr_email_followups_template_definitions() : array()) as $key) {
			$template = is_array($templates[$key] ?? null) ? (array) $templates[$key] : array();
			$body = (string) ($template['body'] ?? '');
			if ($body === '') {
				continue;
			}
			if (strpos($body, '{customer_greeting}') !== false || strpos($body, '{customer_first_name}') !== false || strpos($body, '{customer_name}') !== false) {
				continue;
			}
			$template['body'] = "{customer_greeting}\n\n" . ltrim($body);
			$templates[$key] = $template;
			$changed = true;
		}

		if ($changed) {
			$settings['templates'] = $templates;
			update_option(bvmgr_email_followups_option_key(), $settings, false);
		}

		update_option($flag, '1', false);
	}
}
add_action('init', 'bvmgr_email_followups_migrate_customer_greeting_template_607', 26);

if (!function_exists('bvmgr_email_followups_migrate_signature_template_608')) {
	function bvmgr_email_followups_migrate_signature_template_608(): void
	{
		$flag = 'vms_email_followups_signature_template_migrated_608';
		if (get_option($flag, '') === '1') {
			return;
		}

		$settings = bvmgr_email_followups_settings();
		if (!isset($settings['signature']) || trim((string) $settings['signature']) === '') {
			$settings['signature'] = bvmgr_email_followups_default_signature();
		}
		$templates = is_array($settings['templates'] ?? null) ? (array) $settings['templates'] : array();
		$changed = false;
		foreach (array_keys(function_exists('bvmgr_email_followups_template_definitions') ? bvmgr_email_followups_template_definitions() : array()) as $key) {
			$template = is_array($templates[$key] ?? null) ? (array) $templates[$key] : array();
			$body = (string) ($template['body'] ?? '');
			if ($body === '' || strpos($body, '{signature}') !== false) {
				continue;
			}
			$template['body'] = rtrim($body) . "\n\n{signature}";
			$templates[$key] = $template;
			$changed = true;
		}
		if ($changed) {
			$settings['templates'] = $templates;
		}
		update_option(bvmgr_email_followups_option_key(), $settings, false);
		update_option($flag, '1', false);
	}
}
add_action('init', 'bvmgr_email_followups_migrate_signature_template_608', 27);

if (!function_exists('bvmgr_email_followups_migrate_duplicate_empty_custom_templates_610')) {
	function bvmgr_email_followups_migrate_duplicate_empty_custom_templates_610(): void
	{
		$flag = 'vms_email_followups_duplicate_empty_custom_templates_migrated_610';
		if (get_option($flag, '') === '1') {
			return;
		}

		$settings = get_option(bvmgr_email_followups_option_key(), array());
		if (!is_array($settings)) {
			update_option($flag, '1', false);
			return;
		}

		$custom = isset($settings['custom_templates']) && is_array($settings['custom_templates']) ? (array) $settings['custom_templates'] : array();
		$templates = isset($settings['templates']) && is_array($settings['templates']) ? (array) $settings['templates'] : array();
		$enabled = isset($settings['templates_enabled']) && is_array($settings['templates_enabled']) ? (array) $settings['templates_enabled'] : array();
		$meta = isset($settings['template_meta']) && is_array($settings['template_meta']) ? (array) $settings['template_meta'] : array();
		$changed = false;

		foreach ($custom as $key => $def) {
			$key = sanitize_key((string) $key);
			if ($key === '' || !is_array($def)) {
				continue;
			}
			$label = trim((string) ($def['label'] ?? ''));
			$description = trim((string) ($def['description'] ?? ''));
			$template = is_array($templates[$key] ?? null) ? (array) $templates[$key] : array();
			$subject = trim((string) ($template['subject'] ?? ''));
			$body = trim(str_replace(array("\r\n", "\r"), "\n", (string) ($template['body'] ?? '')));
			$is_empty_placeholder = $label === __('Custom Follow-Up', 'backstage-venue-manager')
				&& $description === ''
				&& ($subject === '' || $subject === __('Custom Follow-Up', 'backstage-venue-manager'))
				&& ($body === '' || $body === "{customer_greeting}\n\n\n\n{signature}");
			if (!$is_empty_placeholder) {
				continue;
			}
			unset($custom[$key], $templates[$key], $enabled[$key], $meta[$key]);
			$changed = true;
		}

		if ($changed) {
			$settings['custom_templates'] = $custom;
			$settings['templates'] = $templates;
			$settings['templates_enabled'] = $enabled;
			$settings['template_meta'] = $meta;
			update_option(bvmgr_email_followups_option_key(), $settings, false);
		}
		update_option($flag, '1', false);
	}
}
add_action('init', 'bvmgr_email_followups_migrate_duplicate_empty_custom_templates_610', 28);
