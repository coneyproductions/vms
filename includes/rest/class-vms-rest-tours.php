<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_REST_Tours')) {
	class VMS_REST_Tours
	{
		public static function init(): void
		{
			add_action('rest_api_init', array(__CLASS__, 'register_routes'));
		}

		public static function register_routes(): void
		{
			register_rest_route('vms/v1', '/tours/drift', array(
				'methods'             => 'POST',
				'permission_callback' => array(__CLASS__, 'can_post_runtime_drift'),
				'callback'            => array(__CLASS__, 'post_runtime_drift'),
			));

			register_rest_route('vms/v1', '/tours/drift-scan', array(
				'methods'             => 'POST',
				'permission_callback' => array(__CLASS__, 'can_manage_tours'),
				'callback'            => array(__CLASS__, 'post_drift_scan'),
			));

			register_rest_route('vms/v1', '/tours/drift-report', array(
				'methods'             => 'GET',
				'permission_callback' => array(__CLASS__, 'can_manage_tours'),
				'callback'            => array(__CLASS__, 'get_drift_report'),
			));

			register_rest_route('vms/v1', '/tours/tile-data', array(
				'methods'             => 'GET',
				'permission_callback' => array(__CLASS__, 'can_manage_tours'),
				'callback'            => array(__CLASS__, 'get_tile_data'),
			));

			register_rest_route('vms/v1', '/tours/anchor-contract', array(
				'methods'             => 'GET',
				'permission_callback' => array(__CLASS__, 'can_manage_tours'),
				'callback'            => array(__CLASS__, 'get_anchor_contract'),
			));
		}

		public static function can_post_runtime_drift(WP_REST_Request $request)
		{
			if (!VMS_Tours::can_run_tours()) {
				return new WP_Error('forbidden', 'Tours are disabled or capability denied.', array('status' => 403));
			}
			if (!VMS_Tours::verify_rest_nonce($request)) {
				return new WP_Error('forbidden', 'Invalid nonce.', array('status' => 403));
			}
			if (!(bool) get_option(VMS_Tours::OPT_ENABLED, 1)) {
				return new WP_Error('forbidden', 'Tours are disabled.', array('status' => 403));
			}
			return true;
		}

		public static function can_manage_tours(WP_REST_Request $request)
		{
			if (!current_user_can('manage_options')) {
				return new WP_Error('forbidden', 'Insufficient capability.', array('status' => 403));
			}
			if (!VMS_Tours::verify_rest_nonce($request)) {
				return new WP_Error('forbidden', 'Invalid nonce.', array('status' => 403));
			}
			return true;
		}

		public static function post_runtime_drift(WP_REST_Request $request): WP_REST_Response
		{
			$params = $request->get_json_params();
			if (!is_array($params)) {
				$params = array();
			}
			$report = VMS_Tours::merge_runtime_report($params);
			return rest_ensure_response(array(
				'ok'      => true,
				'report'  => $report,
			));
		}

		public static function post_drift_scan(WP_REST_Request $request): WP_REST_Response
		{
			$params = $request->get_json_params();
			if (!is_array($params)) {
				$params = array();
			}
			$source = sanitize_key((string) ($params['source'] ?? 'scan'));
			$report = VMS_Tours::replace_scan_report($params, $source === 'auto-update' ? 'auto-update' : 'scan');
			return rest_ensure_response(array(
				'ok'     => true,
				'report' => $report,
			));
		}

		public static function get_drift_report(): WP_REST_Response
		{
			return rest_ensure_response(VMS_Tours::get_report());
		}

		public static function get_tile_data(): WP_REST_Response
		{
			return rest_ensure_response(VMS_Tours::get_tile_data());
		}

		public static function get_anchor_contract(): WP_REST_Response
		{
			return rest_ensure_response(array(
				'contract' => VMS_Tours::get_anchor_contract(),
			));
		}
	}
}

VMS_REST_Tours::init();
