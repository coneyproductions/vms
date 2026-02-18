<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Meta_Ads_Utils')) {
	class VMS_Meta_Ads_Utils {
		public const SETTINGS_OPTION = 'vms_ma_settings';
		public const GUIDANCE_LEVELS = array('beginner', 'standard', 'expert');

		public static function encrypt_token(string $value): string
		{
			$key = hash('sha256', wp_salt('vms_ma_token_key'), true);
			$iv = substr(hash('md5', wp_salt('vms_ma_token_iv')), 0, 16);
			if (!function_exists('openssl_encrypt')) {
				return $value;
			}
			return base64_encode(openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));
		}

		public static function decrypt_token(string $value): string
		{
			$key = hash('sha256', wp_salt('vms_ma_token_key'), true);
			$iv = substr(hash('md5', wp_salt('vms_ma_token_iv')), 0, 16);
			if (!function_exists('openssl_decrypt')) {
				return $value;
			}
			$decoded = base64_decode($value, true);
			if ($decoded === false) {
				return '';
			}
			return (string) openssl_decrypt($decoded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
		}

		public static function get_settings(): array
		{
				$defaults = array(
					'budget_max_daily_minor' => 5000,
					'budget_max_lifetime_minor' => 20000,
					'default_radius_miles' => 15,
					'max_radius_miles' => 100,
					'default_age_min' => 21,
				'default_age_max' => 65,
				'enable_api_create' => 0,
				'vms_ma_phase' => 1,
				'vms_ma_default_preset' => 'flat_run',
				'meta_graph_version' => 'v24.0',
				'meta_ad_account_id' => '',
				'meta_page_id' => '',
				'facebook_page_url' => '',
				'meta_ig_actor_id' => '',
				'meta_access_token_encrypted' => '',
				'meta_ad_account_override' => 0,
				'tour_autostart' => 1,
			);
			return wp_parse_args((array) get_option(self::SETTINGS_OPTION, array()), $defaults);
		}

		public static function update_settings(array $settings): bool
		{
			return update_option(self::SETTINGS_OPTION, $settings, false);
		}

		public static function normalize_guidance_level(string $guidance): string
		{
			$guidance = sanitize_key($guidance);
			return in_array($guidance, self::GUIDANCE_LEVELS, true) ? $guidance : 'beginner';
		}

		public static function get_guidance_defaults(string $guidance): array
		{
			$guidance = self::normalize_guidance_level($guidance);
			return array(
				'tour_autorun' => ($guidance === 'expert') ? 0 : 1,
				'help_mode' => ($guidance === 'beginner') ? 1 : 0,
			);
		}

		public static function get_user_guidance_state(int $user_id): array
		{
			$guidance = self::normalize_guidance_level((string) get_user_meta($user_id, 'vms_ma_guidance_level', true));
			$defaults = self::get_guidance_defaults($guidance);

			$tour_raw = get_user_meta($user_id, 'vms_ma_tour_autorun', true);
			$help_raw = get_user_meta($user_id, 'vms_ma_help_mode', true);
			$tour_locked = (int) get_user_meta($user_id, 'vms_ma_tour_autorun_locked', true);
			$help_locked = (int) get_user_meta($user_id, 'vms_ma_help_mode_locked', true);

			return array(
				'guidance_level' => $guidance,
				'tour_autorun' => ($tour_raw === '') ? $defaults['tour_autorun'] : (((int) $tour_raw) ? 1 : 0),
				'help_mode' => ($help_raw === '') ? $defaults['help_mode'] : (((int) $help_raw) ? 1 : 0),
				'tour_autorun_locked' => $tour_locked ? 1 : 0,
				'help_mode_locked' => $help_locked ? 1 : 0,
			);
		}
	}
}
