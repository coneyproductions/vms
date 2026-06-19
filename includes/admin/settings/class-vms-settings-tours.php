<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Settings_Tours')) {
	class VMS_Settings_Tours
	{
		public static function init(): void
		{
			add_action('admin_init', array(__CLASS__, 'register_settings'));
			add_action('admin_post_vms_tours_reset_current_user', array(__CLASS__, 'handle_reset_current_user'));
		}

		public static function register_settings(): void
		{
			register_setting('vms_settings_group', VMS_Tours::OPT_ENABLED, array(
				'type'              => 'integer',
				'sanitize_callback' => array(__CLASS__, 'sanitize_bool_int'),
				'default'           => 1,
			));
			register_setting('vms_settings_group', VMS_Tours::OPT_AUTOSTART, array(
				'type'              => 'integer',
				'sanitize_callback' => array(__CLASS__, 'sanitize_bool_int'),
				'default'           => 1,
			));
			register_setting('vms_settings_group', VMS_Tours::OPT_DRIFT_NOTICE_ENABLED, array(
				'type'              => 'integer',
				'sanitize_callback' => array(__CLASS__, 'sanitize_bool_int'),
				'default'           => 1,
			));
			register_setting('vms_settings_group', VMS_Tours::OPT_DRIFT_BADGE_ENABLED, array(
				'type'              => 'integer',
				'sanitize_callback' => array(__CLASS__, 'sanitize_bool_int'),
				'default'           => 1,
			));
			register_setting('vms_settings_group', VMS_Tours::OPT_AUTO_SCAN_ON_UPDATE, array(
				'type'              => 'integer',
				'sanitize_callback' => array(__CLASS__, 'sanitize_bool_int'),
				'default'           => 1,
			));

			add_settings_section(
				'vms_tours_section',
				__('Guided Tours', 'vms'),
				array(__CLASS__, 'render_section_intro'),
				'vms-settings'
			);

			add_settings_field('vms_tours_enabled', __('Tours Enabled', 'vms'), array(__CLASS__, 'render_enabled_field'), 'vms-settings', 'vms_tours_section');
			add_settings_field('vms_tours_autostart', __('Auto-launch Tours On First Visit', 'vms'), array(__CLASS__, 'render_autostart_field'), 'vms-settings', 'vms_tours_section');
			add_settings_field('vms_tours_drift_notice_enabled', __('Drift Notice Enabled', 'vms'), array(__CLASS__, 'render_notice_field'), 'vms-settings', 'vms_tours_section');
			add_settings_field('vms_tours_drift_badge_enabled', __('Menu Badge Enabled', 'vms'), array(__CLASS__, 'render_badge_field'), 'vms-settings', 'vms_tours_section');
			add_settings_field('vms_tours_auto_scan_on_update', __('Auto Scan On Update', 'vms'), array(__CLASS__, 'render_autoscan_field'), 'vms-settings', 'vms_tours_section');
			add_settings_field('vms_tours_reset_current_user', __('Reset Tours For Current User', 'vms'), array(__CLASS__, 'render_reset_field'), 'vms-settings', 'vms_tours_section');
		}

		public static function render_section_intro(): void
		{
			echo '<p>Controls for VMS guided tours and drift health surfacing.</p>';
			if (!empty($_GET['vms_tours_reset'])) {
				echo '<p><strong>' . esc_html__('Tour progress reset for current user.', 'vms') . '</strong></p>';
			}
		}

		public static function render_enabled_field(): void
		{
			self::render_checkbox(VMS_Tours::OPT_ENABLED, 'Enable guided tours across VMS admin screens.');
		}

		public static function render_autostart_field(): void
		{
			self::render_checkbox(VMS_Tours::OPT_AUTOSTART, 'Auto-launch guided tours when a page tour version has not been seen yet.');
		}

		public static function render_notice_field(): void
		{
			self::render_checkbox(VMS_Tours::OPT_DRIFT_NOTICE_ENABLED, 'Show admin drift notice on VMS pages when anchors are missing.');
		}

		public static function render_badge_field(): void
		{
			self::render_checkbox(VMS_Tours::OPT_DRIFT_BADGE_ENABLED, 'Show red badge on VMS menu when anchor drift exists.');
		}

		public static function render_autoscan_field(): void
		{
			self::render_checkbox(VMS_Tours::OPT_AUTO_SCAN_ON_UPDATE, 'Set pending scan automatically when VMS version changes.');
		}

		public static function render_reset_field(): void
		{
			$url = wp_nonce_url(
				admin_url('admin-post.php?action=vms_tours_reset_current_user'),
				'vms_tours_reset_current_user'
			);
			echo '<a class="button button-secondary" href="' . esc_url($url) . '">' . esc_html__('Reset Tour Progress', 'vms') . '</a>';
			echo '<p class="description">' . esc_html__('Clears current user tour progress and version-seen state so tours can auto-launch again.', 'vms') . '</p>';
		}

		public static function handle_reset_current_user(): void
		{
			if (!current_user_can('manage_options')) {
				wp_die(esc_html__('Insufficient permissions.', 'vms'));
			}
			check_admin_referer('vms_tours_reset_current_user');

			$user_id = get_current_user_id();
			if ($user_id > 0) {
				delete_user_meta($user_id, VMS_Tours::USER_META_STATE);
				delete_user_meta($user_id, VMS_Tours::USER_META_NOTICE_DISMISSED);

				global $wpdb;
				if (isset($wpdb->usermeta)) {
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
							$user_id,
							$wpdb->esc_like('vms_tour_seen_') . '%'
						)
					);
				}
			}

			$redirect = add_query_arg(
				array(
					'page' => 'vms-settings',
					'vms_tours_reset' => '1',
				),
				admin_url('admin.php')
			);
			wp_safe_redirect($redirect);
			exit;
		}

		public static function sanitize_bool_int($value): int
		{
			return !empty($value) ? 1 : 0;
		}

		private static function render_checkbox(string $key, string $label): void
		{
			$checked = !empty(get_option($key, 1));
			echo '<label><input type="checkbox" name="' . esc_attr($key) . '" value="1" ' . checked($checked, true, false) . '> ' . esc_html($label) . '</label>';
		}
	}
}

VMS_Settings_Tours::init();
