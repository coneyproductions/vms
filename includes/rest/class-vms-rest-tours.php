<?php

defined('ABSPATH') || exit;

if (!class_exists('BVMGR_REST_Tours')) {
	class BVMGR_REST_Tours
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
		if (!BVMGR_Tours::can_run_tours()) {
			return new WP_Error('forbidden', 'Tours are disabled or capability denied.', array('status' => 403));
			}
			if (!BVMGR_Tours::verify_rest_nonce($request)) {
				return new WP_Error('forbidden', 'Invalid nonce.', array('status' => 403));
			}
			if (!(bool) get_option(BVMGR_Tours::OPT_ENABLED, 1)) {
				return new WP_Error('forbidden', 'Tours are disabled.', array('status' => 403));
		}
		return true;
	}

	public static function can_manage_tours(WP_REST_Request $request)
	{
		if (!current_user_can('manage_options')) {
			return new WP_Error('forbidden', 'Insufficient capability.', array('status' => 403));
		}
		if (!BVMGR_Tours::verify_rest_nonce($request)) {
			return new WP_Error('forbidden', 'Invalid nonce.', array('status' => 403));
		}
		return true;
	}

	private static function invalid_json_error(string $code, string $message): WP_Error
	{
		return new WP_Error($code, $message, array('status' => 400));
	}

	private static function read_object_json_params(WP_REST_Request $request): array|WP_Error
	{
		if (method_exists($request, 'has_valid_params')) {
			$valid = $request->has_valid_params();
			if (is_wp_error($valid)) {
				return self::invalid_json_error('vms_tours_invalid_json_body', 'Invalid JSON body.');
			}
		}

		$raw_body = method_exists($request, 'get_body') ? (string) $request->get_body() : '';
		$top_level_token = vms_json_top_level_token($raw_body);
		$params = $request->get_json_params();
		if (!is_array($params) || !vms_json_decoded_is_object($params, $top_level_token)) {
			return self::invalid_json_error('vms_tours_invalid_json_payload', 'Request body must be a JSON object.');
		}

		return $params;
	}

	private static function validate_runtime_drift_payload(array $params): bool
	{
		foreach (array('context_key', 'anchor', 'tour_id', 'severity') as $scalar_key) {
			if (isset($params[$scalar_key]) && (is_array($params[$scalar_key]) || is_object($params[$scalar_key]))) {
				return false;
			}
		}

		$severity = sanitize_key((string) ($params['severity'] ?? 'required'));
		return ($severity === '' || in_array($severity, array('required', 'optional'), true));
	}

	private static function validate_drift_scan_payload(array $params): bool
	{
		if (isset($params['source']) && (is_array($params['source']) || is_object($params['source']))) {
			return false;
		}
		if (!isset($params['contexts'])) {
			return true;
		}
		if (!is_array($params['contexts']) || (!empty($params['contexts']) && vms_array_is_list_compat($params['contexts'])) || count($params['contexts']) > 200) {
			return false;
		}

		foreach ($params['contexts'] as $row) {
			if (!is_array($row) || (!empty($row) && vms_array_is_list_compat($row))) {
				return false;
			}
			if (isset($row['scan_error']) && (is_array($row['scan_error']) || is_object($row['scan_error']))) {
				return false;
			}
			if (isset($row['missing_anchors'])) {
				if (!is_array($row['missing_anchors']) || (!empty($row['missing_anchors']) && vms_array_is_list_compat($row['missing_anchors'])) || count($row['missing_anchors']) > 200) {
					return false;
				}
				foreach ($row['missing_anchors'] as $entry) {
					if (!is_array($entry) || (!empty($entry) && vms_array_is_list_compat($entry))) {
						return false;
					}
				}
			}
		}

		return true;
	}

	public static function post_runtime_drift(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$params = self::read_object_json_params($request);
		if (is_wp_error($params)) {
			return $params;
		}
		if (!self::validate_runtime_drift_payload($params)) {
			return new WP_REST_Response(array(
				'ok' => false,
				'message' => 'invalid_payload',
			), 400);
		}
		$report = BVMGR_Tours::merge_runtime_report($params);
		return rest_ensure_response(array(
			'ok' => true,
			'report' => $report,
		));
	}

	public static function post_drift_scan(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$params = self::read_object_json_params($request);
		if (is_wp_error($params)) {
			return $params;
		}
		if (!self::validate_drift_scan_payload($params)) {
			return new WP_REST_Response(array(
				'ok' => false,
				'message' => 'invalid_payload',
			), 400);
		}
		$source = sanitize_key((string) ($params['source'] ?? 'scan'));
		$report = BVMGR_Tours::replace_scan_report($params, $source === 'auto-update' ? 'auto-update' : 'scan');
		return rest_ensure_response(array(
			'ok' => true,
			'report' => $report,
		));
	}

		public static function get_drift_report(): WP_REST_Response
		{
			return rest_ensure_response(BVMGR_Tours::get_report());
		}

		public static function get_tile_data(): WP_REST_Response
		{
			return rest_ensure_response(BVMGR_Tours::get_tile_data());
		}

		public static function get_anchor_contract(): WP_REST_Response
		{
			return rest_ensure_response(array(
				'contract' => BVMGR_Tours::get_anchor_contract(),
			));
		}
	}
}

BVMGR_REST_Tours::init();
