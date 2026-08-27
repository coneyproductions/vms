<?php

defined('ABSPATH') || exit;

if (!class_exists('BVMGR_Settings_Notifications')) {
	class BVMGR_Settings_Notifications
	{
		public static function init(): void
		{
			add_action('admin_init', array(__CLASS__, 'register_settings'));
		}

		public static function register_settings(): void
		{
			register_setting('vms_settings_group', vms_notify_digest_enabled_option_key(), array(
				'type' => 'integer',
				'sanitize_callback' => array(__CLASS__, 'sanitize_bool_int'),
				'default' => 0,
			));
			register_setting('vms_settings_group', vms_notify_digest_time_option_key(), array(
				'type' => 'string',
				'sanitize_callback' => array(__CLASS__, 'sanitize_digest_time'),
				'default' => '08:00',
			));
			register_setting('vms_settings_group', vms_notify_digest_window_option_key(), array(
				'type' => 'string',
				'sanitize_callback' => array(__CLASS__, 'sanitize_digest_window'),
				'default' => 'next3',
			));

			add_settings_section(
				'vms_notifications_section',
				__('Notifications', 'backstage-venue-manager'),
				array(__CLASS__, 'render_section_intro'),
				'vms-settings'
			);

			add_settings_field(
				'vms_notifications_providers',
				__('Providers Status', 'backstage-venue-manager'),
				array(__CLASS__, 'render_providers_status_field'),
				'vms-settings',
				'vms_notifications_section'
			);
			add_settings_field(
				'vms_notifications_digest_enabled',
				__('Daily Digest Enabled', 'backstage-venue-manager'),
				array(__CLASS__, 'render_digest_enabled_field'),
				'vms-settings',
				'vms_notifications_section'
			);
			add_settings_field(
				'vms_notifications_digest_time',
				__('Digest Time', 'backstage-venue-manager'),
				array(__CLASS__, 'render_digest_time_field'),
				'vms-settings',
				'vms_notifications_section'
			);
			add_settings_field(
				'vms_notifications_digest_window',
				__('Digest Window', 'backstage-venue-manager'),
				array(__CLASS__, 'render_digest_window_field'),
				'vms-settings',
				'vms_notifications_section'
			);
			add_settings_field(
				'vms_notifications_recent',
				__('Recent Delivery Log', 'backstage-venue-manager'),
				array(__CLASS__, 'render_recent_log_field'),
				'vms-settings',
				'vms_notifications_section'
			);
		}

		public static function render_section_intro(): void
		{
			echo '<p>' . esc_html__('Core notification delivery settings (Email baseline, provider-ready for SMS/WhatsApp add-ons).', 'backstage-venue-manager') . '</p>';
		}

		public static function render_providers_status_field(): void
		{
			$providers = bvmgr_notify_get_providers();
			$sms_provider = vms_notify_channel_provider_key('sms');
			$wa_provider = vms_notify_channel_provider_key('whatsapp');

			echo '<ul style="margin:0;">';
			echo '<li><strong>' . esc_html__('Email', 'backstage-venue-manager') . ':</strong> ' . esc_html__('Ready (core_email)', 'backstage-venue-manager') . '</li>';

			if ($sms_provider !== '' && isset($providers[$sms_provider])) {
				/* translators: %s: active SMS provider key. */
				echo '<li><strong>' . esc_html__('SMS', 'backstage-venue-manager') . ':</strong> ' . esc_html(sprintf(__('Ready (%s)', 'backstage-venue-manager'), $sms_provider)) . '</li>';
			} else {
				echo '<li><strong>' . esc_html__('SMS', 'backstage-venue-manager') . ':</strong> ' . esc_html__('Provider not installed', 'backstage-venue-manager') . '</li>';
			}

			if ($wa_provider !== '' && isset($providers[$wa_provider])) {
				/* translators: %s: active WhatsApp provider key. */
				echo '<li><strong>' . esc_html__('WhatsApp', 'backstage-venue-manager') . ':</strong> ' . esc_html(sprintf(__('Ready (%s)', 'backstage-venue-manager'), $wa_provider)) . '</li>';
			} else {
				echo '<li><strong>' . esc_html__('WhatsApp', 'backstage-venue-manager') . ':</strong> ' . esc_html__('Provider not installed', 'backstage-venue-manager') . '</li>';
			}
			echo '</ul>';
		}

		public static function render_digest_enabled_field(): void
		{
			$enabled = !empty(get_option(vms_notify_digest_enabled_option_key(), 0));
			echo '<label><input type="checkbox" name="' . esc_attr(vms_notify_digest_enabled_option_key()) . '" value="1" ' . checked($enabled, true, false) . '> ' . esc_html__('Enable core daily digest scheduler', 'backstage-venue-manager') . '</label>';
		}

		public static function render_digest_time_field(): void
		{
			$time = self::sanitize_digest_time((string) get_option(vms_notify_digest_time_option_key(), '08:00'));
			echo '<input type="time" name="' . esc_attr(vms_notify_digest_time_option_key()) . '" value="' . esc_attr($time) . '">';
		}

		public static function render_digest_window_field(): void
		{
			$window = self::sanitize_digest_window((string) get_option(vms_notify_digest_window_option_key(), 'next3'));
			echo '<select name="' . esc_attr(vms_notify_digest_window_option_key()) . '">';
			echo '<option value="today" ' . selected($window, 'today', false) . '>' . esc_html__('Today', 'backstage-venue-manager') . '</option>';
			echo '<option value="next3" ' . selected($window, 'next3', false) . '>' . esc_html__('Next 3 days', 'backstage-venue-manager') . '</option>';
			echo '<option value="next7" ' . selected($window, 'next7', false) . '>' . esc_html__('Next 7 days', 'backstage-venue-manager') . '</option>';
			echo '</select>';
		}

		public static function render_recent_log_field(): void
		{
			$rows = vms_notify_recent_logs(10);
			if (empty($rows)) {
				echo '<p>' . esc_html__('No notification attempts logged yet.', 'backstage-venue-manager') . '</p>';
				return;
			}

			echo '<table class="widefat striped" style="max-width:960px;"><thead><tr>';
			echo '<th>' . esc_html__('Time (UTC)', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Channel', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Event Key', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Status', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Provider', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Error', 'backstage-venue-manager') . '</th>';
			echo '</tr></thead><tbody>';
			foreach ($rows as $row) {
				echo '<tr>';
				echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($row['channel'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($row['event_key'] ?? '')) . '</td>';
				echo '<td>' . esc_html(strtoupper((string) ($row['status'] ?? ''))) . '</td>';
				echo '<td>' . esc_html((string) ($row['provider'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($row['error_message'] ?? '')) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		public static function sanitize_bool_int($value): int
		{
			return !empty($value) ? 1 : 0;
		}

		public static function sanitize_digest_time($value): string
		{
			$value = sanitize_text_field((string) $value);
			if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
				return '08:00';
			}
			return $value;
		}

		public static function sanitize_digest_window($value): string
		{
			return vms_notify_valid_digest_window((string) $value);
		}
	}
}

BVMGR_Settings_Notifications::init();
