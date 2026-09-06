<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', 'vms_dt_register_rest_routes');

function vms_dt_register_rest_routes(): void
{
	$ns = vms_dt_rest_namespace();

	register_rest_route($ns, '/vendor-import/preview', [
		'methods'             => 'POST',
		'permission_callback' => 'vms_dt_rest_can_import',
		'callback'            => 'vms_dt_rest_vendor_import_preview',
	]);

	register_rest_route($ns, '/vendor-import/commit', [
		'methods'             => 'POST',
		'permission_callback' => 'vms_dt_rest_can_import',
		'callback'            => 'vms_dt_rest_vendor_import_commit',
	]);
}

function vms_dt_rest_can_import(): bool
{
	return is_user_logged_in() && vms_dt_current_user_can_import();
}

function vms_dt_rest_vendor_import_preview(WP_REST_Request $request): WP_REST_Response
{
	require_once __DIR__ . '/../services/index.php';

	$files = $request->get_file_params();
	$file  = isset($files['file']) ? $files['file'] : null;

	if (!is_array($file)) {
		return new WP_REST_Response(
			vms_dt_result_error('file_missing', 'Upload a CSV file using form field name "file".'),
			400
		);
	}

	$payload = vms_dt_vendor_import_preview($file);

	$status = !empty($payload['ok']) ? 200 : 400;
	return new WP_REST_Response($payload, $status);
}

function vms_dt_rest_vendor_import_commit(WP_REST_Request $request): WP_REST_Response {
	require_once __DIR__ . '/../services/index.php';

	$files = $request->get_file_params();
	$file  = isset($files['file']) ? $files['file'] : null;

	if (!is_array($file)) {
		return new WP_REST_Response(
			vms_dt_result_error('file_missing', 'Upload a CSV file using form field name "file".'),
			400
		);
	}

	$payload = vms_dt_vendor_import_commit($file);

	$status = !empty($payload['ok']) ? 200 : 400;
	return new WP_REST_Response($payload, $status);
}

