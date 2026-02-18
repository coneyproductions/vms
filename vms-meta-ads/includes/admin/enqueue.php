<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Meta_Ads_Admin_Enqueue')) {
	class VMS_Meta_Ads_Admin_Enqueue {
		public static function init(): void
		{
			add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_builder_assets'), 20);
		}

		public static function enqueue_builder_assets(string $hook): void
		{
			$page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
			if (!in_array($page, array('vms-ma-ads-builder', 'vms-ma-ads-promote', 'vms-ma-ads-performance', 'vms-ma-ads-settings', 'vms-ma-ads-logs'), true)) {
				return;
			}
			if (in_array($page, array('vms-ma-ads-builder', 'vms-ma-ads-settings'), true) && function_exists('vms_enqueue_tour_assets')) {
				vms_enqueue_tour_assets();
			}
				$settings = VMS_Meta_Ads_Utils::get_settings();
				$token_present = VMS_Meta_Ads_Utils::decrypt_token((string) ($settings['meta_access_token_encrypted'] ?? '')) !== '';
				$api_ready = !empty($settings['enable_api_create']) && $token_present && trim((string) ($settings['meta_page_id'] ?? '')) !== '' && trim((string) ($settings['meta_ad_account_id'] ?? '')) !== '';
				$tours_payload = VMS_Meta_Ads_Tours::get_tours_payload();
			$user_state = VMS_Meta_Ads_Utils::get_user_guidance_state(get_current_user_id());
			$guidance = $user_state['guidance_level'];
			$tour_autorun = (int) $user_state['tour_autorun'];
			$help_mode = (int) $user_state['help_mode'];
				wp_localize_script('vms-ma-ads-builder', 'VMS_MA', array(
				'restRoot' => esc_url_raw(rest_url('vms-ma/v1')),
				'nonce' => wp_create_nonce('wp_rest'),
				'ajaxUrl' => admin_url('admin-ajax.php'),
					'eventPlansUrl' => esc_url_raw(rest_url('vms-ma/v1/event-plans')),
					'postAssetsUrl' => esc_url_raw(rest_url('vms-ma/v1/post-assets')),
					'builderUrl' => admin_url('admin.php?page=vms-ma-ads-builder'),
					'settingsUrl' => admin_url('admin.php?page=vms-ma-ads-settings'),
					'tourAutostart' => !empty($settings['tour_autostart']),
					'apiEnabled' => !empty($settings['enable_api_create']),
					'apiReady' => $api_ready ? 1 : 0,
					'budgetMaxLifetimeMinor' => absint($settings['budget_max_lifetime_minor']),
					'budgetMaxDailyMinor' => absint($settings['budget_max_daily_minor']),
					'maxRadiusMiles' => max(1, absint($settings['max_radius_miles'] ?? 100)),
					'phase' => max(1, absint($settings['vms_ma_phase'] ?? 1)),
					'defaultPreset' => sanitize_key((string) ($settings['vms_ma_default_preset'] ?? 'flat_run')),
					'tourSteps' => VMS_Meta_Ads_Tours::get_steps_payload(),
				));
			if (in_array($page, array('vms-ma-ads-builder', 'vms-ma-ads-settings'), true)) {
				wp_localize_script('vms-ma-tours', 'vmsMaTours', $tours_payload);
				wp_localize_script('vms-ma-tours', 'vmsMaTour', array(
					'autorun' => $tour_autorun,
					'helpMode' => $help_mode,
					'nonce' => wp_create_nonce('wp_rest'),
					'restUrl' => esc_url_raw(rest_url('vms-ma/v1/tour-pref')),
					'guidance' => $guidance,
					'screen' => ($page === 'vms-ma-ads-settings') ? 'settings' : 'builder',
				));
				wp_localize_script('vms-ma-tours', 'vmsMaGuidance', array(
					'guidanceLevel' => $guidance,
					'helpMode' => $help_mode,
				));
				wp_localize_script('vms-ma-help-mode', 'vmsMaHelp', array(
					'helpMode' => $help_mode,
					'guidanceLevel' => $guidance,
					'registry' => VMS_Meta_Ads_Help::get_payload(),
					'nonce' => wp_create_nonce('wp_rest'),
					'restUrl' => esc_url_raw(rest_url('vms-ma/v1/tour-pref')),
					'screen' => ($page === 'vms-ma-ads-settings') ? 'settings' : 'builder',
				));
				wp_localize_script('vms-ma-guidance', 'vmsMaGuidanceUi', array(
					'guidanceLevel' => $guidance,
					'helpMode' => $help_mode,
					'autorun' => $tour_autorun,
				));
				if ($page === 'vms-ma-ads-settings') {
					wp_localize_script('vms-ma-settings', 'vmsMaSettings', array(
						'nonce' => wp_create_nonce('wp_rest'),
						'tokenInspectUrl' => esc_url_raw(rest_url('vms-ma/v1/token-inspect')),
						'metaPagesUrl' => esc_url_raw(rest_url('vms-ma/v1/meta-pages')),
					));
				}
			}
		}
	}
}

VMS_Meta_Ads_Admin_Enqueue::init();
