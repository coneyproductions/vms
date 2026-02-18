<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Meta_Ads_Rest')) {
	class VMS_Meta_Ads_Rest {
		public static function init(): void
		{
			require_once VMS_MA_PATH . 'includes/rest/controllers/class-ad-builds-controller.php';
			require_once VMS_MA_PATH . 'includes/rest/controllers/class-post-assets-controller.php';
			require_once VMS_MA_PATH . 'includes/rest/controllers/class-event-plans-controller.php';
			add_action('rest_api_init', array(__CLASS__, 'register_routes'));
		}

		public static function register_routes(): void
		{
			VMS_Meta_Ads_Ad_Builds_Controller::register_routes();
			VMS_Meta_Ads_Post_Assets_Controller::register_routes();
			VMS_Meta_Ads_Event_Plans_Controller::register_routes();
			register_rest_route('vms-ma/v1', '/tour-pref', array(
				array(
					'methods' => 'POST',
					'callback' => array(__CLASS__, 'save_tour_pref'),
					'permission_callback' => 'vms_ma_current_user_can_manage',
				),
			));
			register_rest_route('vms-ma/v1', '/token-inspect', array(
				array(
					'methods' => 'GET',
					'callback' => array(__CLASS__, 'inspect_token'),
					'permission_callback' => 'vms_ma_current_user_can_manage',
				),
			));
			register_rest_route('vms-ma/v1', '/meta-pages', array(
				array(
					'methods' => 'GET',
					'callback' => array(__CLASS__, 'list_meta_pages'),
					'permission_callback' => 'vms_ma_current_user_can_manage',
				),
			));
		}

		public static function save_tour_pref(WP_REST_Request $request)
		{
			$nonce = (string) $request->get_header('X-WP-Nonce');
			if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
				return new WP_Error('invalid_nonce', __('Invalid request nonce.', 'vms-meta-ads'), array('status' => 403));
			}

			$user_id = get_current_user_id();
			$autorun_param = $request->get_param('autorun');
			$autorun = null;
			if ($autorun_param !== null) {
				$autorun = ((int) $autorun_param) ? 1 : 0;
				update_user_meta($user_id, 'vms_ma_tour_autorun', $autorun);
				update_user_meta($user_id, 'vms_ma_tour_autorun_locked', 1);
			}

			$help_mode_param = $request->get_param('help_mode');
			$help_mode = null;
			if ($help_mode_param !== null) {
				$help_mode = ((int) $help_mode_param) ? 1 : 0;
				update_user_meta($user_id, 'vms_ma_help_mode', $help_mode);
				update_user_meta($user_id, 'vms_ma_help_mode_locked', 1);
			}

			$guidance = null;
			$guidance_raw = (string) $request->get_param('guidance_level');
			if ($guidance_raw !== '') {
				$guidance_candidate = sanitize_key($guidance_raw);
				if (!in_array($guidance_candidate, VMS_Meta_Ads_Utils::GUIDANCE_LEVELS, true)) {
					return new WP_Error('invalid_guidance_level', __('Invalid guidance level.', 'vms-meta-ads'), array('status' => 400));
				}
				$guidance_param = VMS_Meta_Ads_Utils::normalize_guidance_level($guidance_raw);
				$guidance = $guidance_param;
				update_user_meta($user_id, 'vms_ma_guidance_level', $guidance);
				$defaults = VMS_Meta_Ads_Utils::get_guidance_defaults($guidance);
				$tour_locked = (int) get_user_meta($user_id, 'vms_ma_tour_autorun_locked', true);
				$help_locked = (int) get_user_meta($user_id, 'vms_ma_help_mode_locked', true);
				if ($autorun_param === null && !$tour_locked) {
					update_user_meta($user_id, 'vms_ma_tour_autorun', (int) $defaults['tour_autorun']);
				}
				if ($help_mode_param === null && !$help_locked) {
					update_user_meta($user_id, 'vms_ma_help_mode', (int) $defaults['help_mode']);
				}
			}

			$state = VMS_Meta_Ads_Utils::get_user_guidance_state($user_id);
			$autorun = ($autorun === null) ? (int) $state['tour_autorun'] : $autorun;
			$help_mode = ($help_mode === null) ? (int) $state['help_mode'] : $help_mode;
			$guidance = ($guidance === null) ? (string) $state['guidance_level'] : $guidance;

			return rest_ensure_response(array(
				'autorun' => $autorun,
				'help_mode' => $help_mode,
				'guidance_level' => $guidance,
				'tour_autorun_locked' => (int) $state['tour_autorun_locked'],
				'help_mode_locked' => (int) $state['help_mode_locked'],
			));
		}

		public static function inspect_token(WP_REST_Request $request)
		{
			$nonce = (string) $request->get_header('X-WP-Nonce');
			if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
				return new WP_Error('invalid_nonce', __('Invalid request nonce.', 'vms-meta-ads'), array('status' => 403));
			}
			if (!vms_ma_current_user_can_manage()) {
				return new WP_Error('forbidden', __('You do not have permission to inspect token values.', 'vms-meta-ads'), array('status' => 403));
			}

			$settings = VMS_Meta_Ads_Utils::get_settings();
			$token = VMS_Meta_Ads_Utils::decrypt_token((string) ($settings['meta_access_token_encrypted'] ?? ''));
			$suffix = ($token !== '') ? substr($token, -6) : '';
			return rest_ensure_response(array(
				'present' => $token !== '',
				'token' => $token,
				'suffix' => $suffix,
			));
		}

		public static function list_meta_pages(WP_REST_Request $request)
		{
			$nonce = (string) $request->get_header('X-WP-Nonce');
			if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
				return new WP_Error('invalid_nonce', __('Invalid request nonce.', 'vms-meta-ads'), array('status' => 403));
			}
			if (!vms_ma_current_user_can_manage()) {
				return new WP_Error('forbidden', __('You do not have permission to inspect page values.', 'vms-meta-ads'), array('status' => 403));
			}

			$result = VMS_Meta_Ads_Meta_Client::list_pages();
			if (is_wp_error($result)) {
				return $result;
			}
			return rest_ensure_response($result);
		}
	}
}
