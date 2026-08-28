<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_is_public_frontend_request')) {
	function bvmgr_is_public_frontend_request(): bool
	{
		if (is_admin()) {
			return false;
		}
		if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
			return false;
		}
		if (function_exists('wp_doing_cron') && wp_doing_cron()) {
			return false;
		}
		if (defined('REST_REQUEST') && REST_REQUEST) {
			return false;
		}
		if (defined('WP_CLI') && WP_CLI) {
			return false;
		}

		return true;
	}
}

if (!function_exists('bvmgr_should_run_runtime_maintenance')) {
	function bvmgr_should_run_runtime_maintenance(): bool
	{
		if (defined('WP_INSTALLING') && WP_INSTALLING) {
			return false;
		}

		if (defined('WP_CLI') && WP_CLI) {
			return true;
		}
		if (function_exists('wp_doing_cron') && wp_doing_cron()) {
			return true;
		}
		if (is_admin()) {
			return true;
		}

		return (bool) apply_filters('vms_should_run_runtime_maintenance', false);
	}
}

if (!function_exists('bvmgr_request_read_scalar')) {
	function bvmgr_request_read_scalar(array $source, string $key): string
	{
		if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
			return '';
		}

		$value = wp_unslash($source[$key]);
		if (!is_scalar($value)) {
			return '';
		}

		return trim((string) $value);
	}
}

if (!function_exists('bvmgr_request_read_text_field')) {
	function bvmgr_request_read_text_field(array $source, string $key): string
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_text_field($value);
	}
}

if (!function_exists('bvmgr_request_read_textarea_field')) {
	function bvmgr_request_read_textarea_field(array $source, string $key): string
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_textarea_field($value);
	}
}

if (!function_exists('bvmgr_request_read_email')) {
	function bvmgr_request_read_email(array $source, string $key): string
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_email($value);
	}
}

if (!function_exists('bvmgr_request_read_key')) {
	function bvmgr_request_read_key(array $source, string $key): string
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_key($value);
	}
}

if (!function_exists('bvmgr_request_read_absint')) {
	function bvmgr_request_read_absint(array $source, string $key): int
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? 0 : absint($value);
	}
}

if (!function_exists('bvmgr_request_read_bool_flag')) {
	function bvmgr_request_read_bool_flag(array $source, string $key): bool
	{
		if (!array_key_exists($key, $source)) {
			return false;
		}

		$value = $source[$key];
		if (is_array($value) || is_object($value)) {
			return false;
		}

		$value = wp_unslash($value);
		if (is_bool($value)) {
			return $value;
		}
		if (!is_scalar($value)) {
			return false;
		}

		$value = strtolower(trim((string) $value));
		if ($value === '') {
			return false;
		}

		return !in_array($value, array('0', 'false', 'off', 'no'), true);
	}
}

if (!function_exists('bvmgr_request_read_array')) {
	/**
	 * @return array<mixed>|null
	 */
	function bvmgr_request_read_array(array $source, string $key): ?array
	{
		if (!array_key_exists($key, $source) || !is_array($source[$key])) {
			return null;
		}

		$value = wp_unslash($source[$key]);
		return is_array($value) ? $value : null;
	}
}

if (!function_exists('bvmgr_request_server_value')) {
	function bvmgr_request_server_value(string $key): string
	{
		$allowed_keys = array(
			'CONTENT_TYPE',
			'HTTP_ACCEPT',
			'HTTP_ACCEPT_LANGUAGE',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_USER_AGENT',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
			'REQUEST_METHOD',
			'REQUEST_TIME_FLOAT',
			'REQUEST_URI',
		);
		if (!in_array($key, $allowed_keys, true)) {
			return '';
		}

		if (!isset($_SERVER[$key]) || !is_scalar($_SERVER[$key])) {
			return '';
		}

		$value = wp_unslash($_SERVER[$key]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server key is finite allowlisted and callers apply context-specific validation or escaping.
		if (!is_scalar($value)) {
			return '';
		}

		return trim((string) $value);
	}
}

if (!function_exists('bvmgr_request_has_post_data')) {
	function bvmgr_request_has_post_data(): bool
	{
		return !empty($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Passive request-shape probe only rejects POST-like traffic before read-only admin diagnostics; it does not consume submitted values.
	}
}

if (!function_exists('bvmgr_request_method')) {
	function bvmgr_request_method(string $fallback = 'get'): string
	{
		$fallback = sanitize_key($fallback);
		if ($fallback === '') {
			$fallback = 'get';
		}

		$method = sanitize_key(bvmgr_request_server_value('REQUEST_METHOD'));
		return $method !== '' ? $method : $fallback;
	}
}

if (!function_exists('bvmgr_request_current_uri')) {
	function bvmgr_request_current_uri(string $fallback = ''): string
	{
		$request_uri = bvmgr_request_server_value('REQUEST_URI');
		if ($request_uri === '') {
			return $fallback;
		}

		$request_uri = (string) preg_replace('/[\x00-\x1F\x7F]+/', '', $request_uri);
		$request_uri = '/' . ltrim($request_uri, '/');
		if (strlen($request_uri) > 2048) {
			$request_uri = substr($request_uri, 0, 2048);
		}

		return $request_uri;
	}
}

if (!function_exists('bvmgr_request_local_redirect')) {
	function bvmgr_request_local_redirect(string $fallback, $raw = null): string
	{
		$fallback = trim($fallback);
		if ($fallback === '') {
			$fallback = function_exists('home_url') ? home_url('/') : '/';
		}

		$candidate = '';
		if (is_scalar($raw)) {
			$candidate = trim((string) wp_unslash($raw));
		}

		return wp_validate_redirect($candidate, $fallback);
	}
}

if (!function_exists('bvmgr_array_is_list_compat')) {
	function bvmgr_array_is_list_compat(array $value): bool
	{
		$index = 0;
		foreach ($value as $key => $_unused) {
			if ($key !== $index) {
				return false;
			}
			$index++;
		}

		return true;
	}
}

if (!function_exists('bvmgr_json_top_level_token')) {
	function bvmgr_json_top_level_token(string $raw): string
	{
		$raw = ltrim($raw);
		if ($raw === '') {
			return '';
		}

		return substr($raw, 0, 1);
	}
}

if (!function_exists('bvmgr_json_decode_associative')) {
	/**
	 * @return array{ok:bool,value:mixed,error_code:int,error_message:string,top_level_token:string}
	 */
	function bvmgr_json_decode_associative(string $raw, int $depth = 32): array
	{
		$top_level_token = bvmgr_json_top_level_token($raw);
		$raw = trim($raw);
		if ($raw === '') {
			return array(
				'ok' => false,
				'value' => null,
				'error_code' => JSON_ERROR_SYNTAX,
				'error_message' => 'Empty JSON payload.',
				'top_level_token' => '',
			);
		}

		$depth = max(1, min(128, $depth));
		$decoded = json_decode($raw, true, $depth);
		$json_error_code = json_last_error();
		if ($json_error_code !== JSON_ERROR_NONE) {
			return array(
				'ok' => false,
				'value' => null,
				'error_code' => $json_error_code,
				'error_message' => json_last_error_msg(),
				'top_level_token' => $top_level_token,
			);
		}

		return array(
			'ok' => true,
			'value' => $decoded,
			'error_code' => JSON_ERROR_NONE,
			'error_message' => '',
			'top_level_token' => $top_level_token,
		);
	}
}

if (!function_exists('bvmgr_json_decoded_is_list')) {
	function bvmgr_json_decoded_is_list(array $decoded, string $top_level_token): bool
	{
		if ($top_level_token !== '[') {
			return false;
		}

		return empty($decoded) || bvmgr_array_is_list_compat($decoded);
	}
}

if (!function_exists('bvmgr_json_decoded_is_object')) {
	function bvmgr_json_decoded_is_object(array $decoded, string $top_level_token): bool
	{
		if ($top_level_token !== '{') {
			return false;
		}

		return empty($decoded) || !bvmgr_array_is_list_compat($decoded);
	}
}

if (!function_exists('bvmgr_read_limited_stream')) {
	/**
	 * @return array{ok:bool,data:string,too_large:bool}
	 */
	function bvmgr_read_limited_stream(string $stream_uri, int $max_bytes): array
	{
		$max_bytes = max(1, $max_bytes);
		$handle = @fopen($stream_uri, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- The only current caller passes hardcoded php://input; this helper must open a bounded local request-body stream before any path-based WP_Filesystem API would apply.
		if (!is_resource($handle)) {
			return array(
				'ok' => false,
				'data' => '',
				'too_large' => false,
			);
		}

		$data = '';
		$too_large = false;
		while (!feof($handle)) {
			$remaining = ($max_bytes + 1) - strlen($data);
			if ($remaining <= 0) {
				$too_large = true;
				break;
			}

			$chunk = fread($handle, min(8192, $remaining)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Keep the local stream read bounded to 8 KB chunks and at most $max_bytes + 1 bytes so oversized JSON request bodies fail closed without buffering the full body.
			if (!is_string($chunk)) {
				fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the same bounded local request-body stream on read failure before returning the safe failure payload.
				return array(
					'ok' => false,
					'data' => '',
					'too_large' => false,
				);
			}

			if ($chunk === '') {
				break;
			}

			$data .= $chunk;
			if (strlen($data) > $max_bytes) {
				$too_large = true;
				break;
			}
		}

		fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the successfully opened bounded local request-body stream after the capped read loop completes.

		return array(
			'ok' => true,
			'data' => $data,
			'too_large' => $too_large,
		);
	}
}

if (!function_exists('bvmgr_upload_request_has_file')) {
	function bvmgr_upload_request_has_file(array $files, string $field): bool
	{
		if (!array_key_exists($field, $files) || !is_array($files[$field])) {
			return false;
		}

		$upload = $files[$field];
		if (!array_key_exists('name', $upload) || !is_scalar($upload['name'])) {
			return false;
		}

		return trim((string) $upload['name']) !== '';
	}
}

if (!function_exists('bvmgr_upload_read_file')) {
	/**
	 * @return array<string,mixed>|WP_Error
	 */
	function bvmgr_upload_read_file(array $files, string $field)
	{
		if (!array_key_exists($field, $files) || !is_array($files[$field])) {
			return new WP_Error('upload_missing', __('Please choose a file to upload.', 'backstage-venue-manager'));
		}

		$upload = $files[$field];
		foreach (array('name', 'type', 'tmp_name', 'error', 'size') as $required_key) {
			if (!array_key_exists($required_key, $upload)) {
				return new WP_Error('upload_invalid_shape', __('The uploaded file payload is malformed.', 'backstage-venue-manager'));
			}
			if (is_array($upload[$required_key]) || is_object($upload[$required_key])) {
				return new WP_Error('upload_invalid_shape', __('The uploaded file payload is malformed.', 'backstage-venue-manager'));
			}
		}

		return array(
			'name' => trim((string) $upload['name']),
			'type' => trim((string) $upload['type']),
			'tmp_name' => trim((string) $upload['tmp_name']),
			'error' => (int) $upload['error'],
			'size' => max(0, (int) $upload['size']),
		);
	}
}

if (!function_exists('bvmgr_upload_normalize_multi_file_array')) {
	/**
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	function bvmgr_upload_normalize_multi_file_array(array $files, string $field)
	{
		if (!array_key_exists($field, $files) || !is_array($files[$field])) {
			return new WP_Error('upload_missing', __('Please choose a file to upload.', 'backstage-venue-manager'));
		}

		$upload = $files[$field];
		foreach (array('name', 'type', 'tmp_name', 'error', 'size') as $required_key) {
			if (!isset($upload[$required_key]) || !is_array($upload[$required_key])) {
				return new WP_Error('upload_invalid_shape', __('The uploaded file payload is malformed.', 'backstage-venue-manager'));
			}
		}

		$normalized = array();
		foreach ($upload['name'] as $index => $name) {
			$row = array(
				'name' => $name,
				'type' => $upload['type'][$index] ?? null,
				'tmp_name' => $upload['tmp_name'][$index] ?? null,
				'error' => $upload['error'][$index] ?? null,
				'size' => $upload['size'][$index] ?? null,
			);

			foreach ($row as $value) {
				if (is_array($value) || is_object($value)) {
					return new WP_Error('upload_invalid_shape', __('The uploaded file payload is malformed.', 'backstage-venue-manager'));
				}
			}

			$normalized[] = array(
				'name' => trim((string) $row['name']),
				'type' => trim((string) $row['type']),
				'tmp_name' => trim((string) $row['tmp_name']),
				'error' => (int) $row['error'],
				'size' => max(0, (int) $row['size']),
			);
		}

		return $normalized;
	}
}

if (!function_exists('bvmgr_upload_error_message')) {
	function bvmgr_upload_error_message(int $error_code, array $messages = array()): string
	{
		$defaults = array(
			UPLOAD_ERR_INI_SIZE => __('The uploaded file is larger than the server allows.', 'backstage-venue-manager'),
			UPLOAD_ERR_FORM_SIZE => __('The uploaded file is larger than this form allows.', 'backstage-venue-manager'),
			UPLOAD_ERR_PARTIAL => __('The upload did not finish. Please try again.', 'backstage-venue-manager'),
			UPLOAD_ERR_NO_FILE => __('Please choose a file to upload.', 'backstage-venue-manager'),
			UPLOAD_ERR_NO_TMP_DIR => __('The server could not create a temporary upload file.', 'backstage-venue-manager'),
			UPLOAD_ERR_CANT_WRITE => __('The server could not save the uploaded file.', 'backstage-venue-manager'),
			UPLOAD_ERR_EXTENSION => __('A server extension blocked the upload.', 'backstage-venue-manager'),
		);

		if (isset($messages[$error_code]) && is_string($messages[$error_code]) && trim($messages[$error_code]) !== '') {
			return $messages[$error_code];
		}

		if (isset($defaults[$error_code])) {
			return $defaults[$error_code];
		}

		return __('The uploaded file could not be processed.', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_validate_uploaded_file')) {
	/**
	 * @param array<string,mixed> $upload
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>|WP_Error
	 */
	function bvmgr_validate_uploaded_file(array $upload, array $args = array())
	{
		$allowed_mimes = isset($args['allowed_mimes']) && is_array($args['allowed_mimes'])
			? $args['allowed_mimes']
			: array();
		if (empty($allowed_mimes)) {
			return new WP_Error('upload_type_not_allowed', __('This file type is not allowed here.', 'backstage-venue-manager'));
		}

		$name = isset($upload['name']) && is_scalar($upload['name']) ? trim((string) $upload['name']) : '';
		$tmp_name = isset($upload['tmp_name']) && is_scalar($upload['tmp_name']) ? trim((string) $upload['tmp_name']) : '';
		$reported_mime = isset($upload['type']) && is_scalar($upload['type']) ? sanitize_text_field((string) $upload['type']) : '';
		$error_code = isset($upload['error']) && is_scalar($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
		$declared_size = isset($upload['size']) && is_scalar($upload['size']) ? max(0, (int) $upload['size']) : 0;
		$messages = isset($args['upload_error_messages']) && is_array($args['upload_error_messages'])
			? $args['upload_error_messages']
			: array();

		if ($name === '') {
			return new WP_Error('upload_missing', __('Please choose a file to upload.', 'backstage-venue-manager'));
		}
		if ($error_code !== UPLOAD_ERR_OK) {
			return new WP_Error('upload_error_' . $error_code, bvmgr_upload_error_message($error_code, $messages));
		}
		if ($tmp_name === '') {
			return new WP_Error(
				'upload_tmp_missing',
				isset($args['tmp_missing_message']) && is_string($args['tmp_missing_message']) && trim($args['tmp_missing_message']) !== ''
					? $args['tmp_missing_message']
					: __('The uploaded file is missing its temporary source.', 'backstage-venue-manager')
			);
		}

		$is_uploaded_file_callback = isset($args['is_uploaded_file_callback']) && is_callable($args['is_uploaded_file_callback'])
			? $args['is_uploaded_file_callback']
			: 'is_uploaded_file';
		if (!call_user_func($is_uploaded_file_callback, $tmp_name)) {
			return new WP_Error(
				'upload_tmp_invalid',
				isset($args['tmp_invalid_message']) && is_string($args['tmp_invalid_message']) && trim($args['tmp_invalid_message']) !== ''
					? $args['tmp_invalid_message']
					: __('The uploaded file could not be verified.', 'backstage-venue-manager')
			);
		}

		$file_exists_callback = isset($args['file_exists_callback']) && is_callable($args['file_exists_callback'])
			? $args['file_exists_callback']
			: 'file_exists';
		if (!call_user_func($file_exists_callback, $tmp_name)) {
			return new WP_Error(
				'upload_tmp_missing',
				isset($args['tmp_missing_message']) && is_string($args['tmp_missing_message']) && trim($args['tmp_missing_message']) !== ''
					? $args['tmp_missing_message']
					: __('The uploaded file is no longer available.', 'backstage-venue-manager')
			);
		}

		$filesize_callback = isset($args['filesize_callback']) && is_callable($args['filesize_callback'])
			? $args['filesize_callback']
			: 'filesize';
		$actual_size = max(0, $declared_size);
		$measured_size = call_user_func($filesize_callback, $tmp_name);
		if (is_numeric($measured_size)) {
			$actual_size = max(0, (int) $measured_size);
		}

		if ($actual_size <= 0) {
			return new WP_Error(
				'upload_empty',
				isset($args['empty_message']) && is_string($args['empty_message']) && trim($args['empty_message']) !== ''
					? $args['empty_message']
					: __('The uploaded file is empty.', 'backstage-venue-manager')
			);
		}

		$max_bytes = isset($args['max_bytes']) ? max(0, (int) $args['max_bytes']) : 0;
		if ($max_bytes > 0 && $actual_size > $max_bytes) {
			return new WP_Error(
				'upload_too_large',
				isset($args['too_large_message']) && is_string($args['too_large_message']) && trim($args['too_large_message']) !== ''
					? $args['too_large_message']
					: __('The uploaded file is too large.', 'backstage-venue-manager')
			);
		}

		$sanitized_name = sanitize_file_name($name);
		if ($sanitized_name === '') {
			$sanitized_name = 'upload';
		}

		$type_check_callback = isset($args['type_check_callback']) && is_callable($args['type_check_callback'])
			? $args['type_check_callback']
			: 'wp_check_filetype_and_ext';
		$checked = call_user_func($type_check_callback, $tmp_name, $sanitized_name, $allowed_mimes);
		$checked = is_array($checked) ? $checked : array();
		$ext = isset($checked['ext']) ? sanitize_key((string) $checked['ext']) : '';
		$mime = isset($checked['type']) ? sanitize_text_field((string) $checked['type']) : '';

		$allowed_for_extension = array();
		if ($ext !== '' && isset($allowed_mimes[$ext])) {
			$raw_allowed = $allowed_mimes[$ext];
			$raw_allowed = is_array($raw_allowed) ? $raw_allowed : array($raw_allowed);
			foreach ($raw_allowed as $allowed_mime) {
				$allowed_mime = sanitize_text_field((string) $allowed_mime);
				if ($allowed_mime !== '') {
					$allowed_for_extension[] = $allowed_mime;
				}
			}
			$allowed_for_extension = array_values(array_unique($allowed_for_extension));
		}

		if ($ext === '' || $mime === '' || empty($allowed_for_extension) || !in_array($mime, $allowed_for_extension, true)) {
			return new WP_Error(
				'upload_type_not_allowed',
				isset($args['type_message']) && is_string($args['type_message']) && trim($args['type_message']) !== ''
					? $args['type_message']
					: __('This file type is not allowed here.', 'backstage-venue-manager')
			);
		}

		$content_validator = isset($args['content_validator']) && is_callable($args['content_validator'])
			? $args['content_validator']
			: null;
		if ($content_validator !== null) {
			$result = call_user_func($content_validator, $tmp_name, $sanitized_name, $ext, $mime, $actual_size, $upload);
			if (is_wp_error($result)) {
				return $result;
			}
			if ($result === false) {
				return new WP_Error(
					'upload_content_invalid',
					isset($args['content_message']) && is_string($args['content_message']) && trim($args['content_message']) !== ''
						? $args['content_message']
						: __('The uploaded file contents are not valid for this upload.', 'backstage-venue-manager')
				);
			}
			if (is_string($result) && trim($result) !== '') {
				return new WP_Error('upload_content_invalid', $result);
			}
		}

		return array(
			'name' => $name,
			'sanitized_name' => $sanitized_name,
			'tmp_name' => $tmp_name,
			'reported_mime' => $reported_mime,
			'reported_size' => $declared_size,
			'size' => $actual_size,
			'ext' => $ext,
			'mime' => $mime,
		);
	}
}

if (!function_exists('bvmgr_request_remote_addr')) {
	function bvmgr_request_remote_addr(): string
	{
		$ip = bvmgr_request_server_value('REMOTE_ADDR');
		if ($ip === '') {
			return '';
		}

		return substr(sanitize_text_field($ip), 0, 64);
	}
}

if (!function_exists('bvmgr_request_user_agent')) {
	function bvmgr_request_user_agent(): string
	{
		$user_agent = bvmgr_request_server_value('HTTP_USER_AGENT');
		if ($user_agent === '') {
			return '';
		}

		return substr(sanitize_text_field($user_agent), 0, 255);
	}
}

if (!function_exists('bvmgr_queue_admin_diagnostic')) {
	function bvmgr_queue_admin_diagnostic(string $code, string $message): void
	{
		$code = sanitize_key($code);
		$message = trim($message);
		if ($code === '' || $message === '') {
			return;
		}

		$seen = get_option('vms_admin_diagnostic_seen', array());
		$seen = is_array($seen) ? $seen : array();
		if (!empty($seen[$code])) {
			return;
		}

		$queue = get_transient('vms_admin_diagnostic_queue');
		$queue = is_array($queue) ? $queue : array();
		if (isset($queue[$code])) {
			return;
		}

		$queue[$code] = array(
			'message' => $message,
			'queued_at' => time(),
		);
		set_transient('vms_admin_diagnostic_queue', $queue, WEEK_IN_SECONDS);
	}
}

if (!function_exists('bvmgr_render_admin_diagnostics')) {
	function bvmgr_render_admin_diagnostics(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		if (!function_exists('bvmgr_admin_ui_is_admin_notice_screen') || !bvmgr_admin_ui_is_admin_notice_screen()) {
			return;
		}

		$queue = get_transient('vms_admin_diagnostic_queue');
		if (!is_array($queue) || empty($queue)) {
			return;
		}

		delete_transient('vms_admin_diagnostic_queue');

		$seen = get_option('vms_admin_diagnostic_seen', array());
		$seen = is_array($seen) ? $seen : array();

		foreach ($queue as $code => $entry) {
			$message = trim((string) ($entry['message'] ?? ''));
			if ($message === '') {
				continue;
			}

			bvmgr_record_operational_issue('admin_diagnostic', array('diagnostic_code' => sanitize_key((string) $code)), $message);
			echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
			$seen[sanitize_key((string) $code)] = time();
		}

		update_option('vms_admin_diagnostic_seen', $seen, false);
	}
}
add_action('admin_notices', 'bvmgr_render_admin_diagnostics');

if (!function_exists('bvmgr_require_internal_file')) {
	function bvmgr_require_internal_file(string $relative_path, string $diagnostic_code = '', string $feature_label = ''): bool
	{
		if (!defined('BVMGR_PLUGIN_PATH')) {
			return false;
		}

		$relative_path = ltrim($relative_path, '/');
		if ($relative_path === '') {
			return false;
		}

		$absolute_path = BVMGR_PLUGIN_PATH . $relative_path;
		if (is_readable($absolute_path)) {
			require_once $absolute_path;
			return true;
		}

		$feature_label = trim($feature_label);
		if ($feature_label === '') {
			$feature_label = 'The related Backstage Venue Manager routine';
		}

		if ($diagnostic_code === '') {
			$diagnostic_code = 'missing_' . md5($relative_path);
		}

		bvmgr_queue_admin_diagnostic(
			$diagnostic_code,
			sprintf(
				'Missing internal Backstage Venue Manager file `%s`. %s has been disabled to avoid a public fatal.',
				$relative_path,
				$feature_label
			)
		);

		return false;
	}
}

if (!function_exists('bvmgr_schedule_exists')) {
	function bvmgr_schedule_exists(string $schedule): bool
	{
		$schedule = trim($schedule);
		if ($schedule === '') {
			return false;
		}

		$schedules = wp_get_schedules();
		return isset($schedules[$schedule]);
	}
}

if (!function_exists('bvmgr_is_owned_cron_hook')) {
	function bvmgr_is_owned_cron_hook(string $hook): bool
	{
		$hook = trim($hook);
		return $hook !== '' && strpos($hook, 'vms_') === 0;
	}
}

if (!function_exists('bvmgr_unschedule_all_owned_cron_hooks')) {
	function bvmgr_unschedule_all_owned_cron_hooks(): void
	{
		if (!function_exists('_get_cron_array') || !function_exists('wp_clear_scheduled_hook')) {
			return;
		}

		$cron = _get_cron_array();
		if (!is_array($cron) || empty($cron)) {
			return;
		}

		$seen = array();
		foreach ($cron as $events) {
			if (!is_array($events)) {
				continue;
			}

			foreach ($events as $hook => $instances) {
				if (!bvmgr_is_owned_cron_hook((string) $hook) || !is_array($instances)) {
					continue;
				}

				foreach ($instances as $instance) {
					$args = isset($instance['args']) && is_array($instance['args']) ? array_values($instance['args']) : array();
					$key = $hook . '|' . md5(serialize($args));
					if (isset($seen[$key])) {
						continue;
					}

					$seen[$key] = true;
					if (!empty($args)) {
						wp_clear_scheduled_hook($hook, $args);
						continue;
					}

					wp_clear_scheduled_hook($hook);
				}
			}
		}
	}
}

if (!function_exists('bvmgr_resource_fingerprint_option_key')) {
	function bvmgr_resource_fingerprint_option_key(): string
	{
		return 'vms_resource_fingerprint_log';
	}
}

if (!function_exists('bvmgr_resource_fingerprint_threshold_seconds')) {
	function bvmgr_resource_fingerprint_threshold_seconds(): float
	{
		$threshold = (float) apply_filters('vms_resource_fingerprint_threshold_seconds', 3.0);
		return $threshold > 0 ? $threshold : 3.0;
	}
}

if (!function_exists('bvmgr_resource_fingerprint_memory_threshold_bytes')) {
	function bvmgr_resource_fingerprint_memory_threshold_bytes(): int
	{
		$threshold = (int) apply_filters('vms_resource_fingerprint_memory_threshold_bytes', 128 * 1024 * 1024);
		return $threshold > 0 ? $threshold : (128 * 1024 * 1024);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_log_limit')) {
	function bvmgr_resource_fingerprint_log_limit(): int
	{
		$limit = (int) apply_filters('vms_resource_fingerprint_log_limit', 60);
		return max(10, $limit);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_max_markers')) {
	function bvmgr_resource_fingerprint_max_markers(): int
	{
		$limit = (int) apply_filters('vms_resource_fingerprint_marker_limit', 24);
		return max(6, $limit);
	}
}

if (!function_exists('bvmgr_operational_issue_value_is_tainted')) {
	function bvmgr_operational_issue_value_is_tainted(string $value): bool
	{
		$value = trim($value);
		if ($value === '') {
			return false;
		}

		$normalized = str_replace('\\', '/', $value);
		foreach (array(defined('ABSPATH') ? ABSPATH : '', defined('BVMGR_PLUGIN_PATH') ? BVMGR_PLUGIN_PATH : '') as $root) {
			$root = rtrim(str_replace('\\', '/', (string) $root), '/');
			if ($root !== '' && $root !== '/' && strpos($normalized, $root . '/') === 0) {
				return true;
			}
		}

		if (preg_match('#^(?:[a-z]:/|/(?:users|home|private|var|tmp|etc|usr|opt|srv|volumes|applications)(?:/|$))#i', $normalized)) {
			return true;
		}
		if (preg_match('/(?:^|[^a-z])(token|secret|nonce|cookie|password|authorization|bearer|recipient[_-]?sentinel|email[_-]?sentinel|user[_-]?agent|ua[_-]?sentinel)(?:[^a-z]|$)/i', $value)) {
			return true;
		}
		if (strpos($value, '@') !== false || strpos($value, '?') !== false || strpos($value, '&') !== false || strpos($value, '=') !== false) {
			return true;
		}
		if (preg_match('#(?:[a-z][a-z0-9+.-]*:)?//#i', $value)) {
			return true;
		}
		if (preg_match('/(?<![0-9])(?:[0-9]{1,3}\.){3}[0-9]{1,3}(?![0-9])/', $value) || filter_var($value, FILTER_VALIDATE_IP)) {
			return true;
		}
		if (preg_match('/(?:^|[^a-z0-9])(?:sk|pk)_(?:live|test)_[a-z0-9]{8,}(?:[^a-z0-9]|$)/i', $value)) {
			return true;
		}
		if (preg_match('/(?:^|[^a-z0-9_-])[a-z0-9_-]{8,}\.[a-z0-9_-]{8,}\.[a-z0-9_-]{8,}(?:[^a-z0-9_-]|$)/i', $value)) {
			return true;
		}
		if (preg_match('/(?:^|[^a-f0-9])[a-f0-9]{32,}(?:[^a-f0-9]|$)/i', $value)) {
			return true;
		}
		if (preg_match('/(?:^|[^a-z0-9+_-])[a-z0-9+_-]{40,}={0,2}(?:[^a-z0-9+_=-]|$)/i', $value)) {
			return true;
		}

		return false;
	}
}

if (!function_exists('bvmgr_operational_issue_request_path')) {
	function bvmgr_operational_issue_request_path(string $request_uri = ''): string
	{
		$request_uri = trim($request_uri !== '' ? $request_uri : bvmgr_request_current_uri());
		if ($request_uri === '') {
			return '';
		}

		$normalized = str_replace('\\', '/', $request_uri);
		if (preg_match('#^(?:[a-z]:/|/(?:users|home|private|var|tmp|etc|usr|opt|srv|volumes|applications)(?:/|$))#i', $normalized)) {
			return '';
		}
		if (preg_match('#(?:^|/)\.\.(?:/|$)#', $normalized)) {
			return '';
		}

		$encoded_path = wp_parse_url($request_uri, PHP_URL_PATH);
		if (!is_string($encoded_path) || $encoded_path === '') {
			return '';
		}
		$path = rawurldecode($encoded_path);
		if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
			return '';
		}
		$path = (string) preg_replace('/[\x00-\x1F\x7F]+/', '', $path);
		$path = '/' . ltrim($path, '/');
		if (preg_match('#(?:^|/)\.\.(?:/|$)#', $path) || bvmgr_operational_issue_value_is_tainted($path)) {
			return '';
		}
		$decoded_preview = rawurldecode($path);
		if (
			stripos($path, '%25') !== false
			|| preg_match('/[\x00-\x1F\x7F]/', $decoded_preview)
			|| preg_match('#(?:^|/)\.\.(?:/|$)#', $decoded_preview)
			|| bvmgr_operational_issue_value_is_tainted($decoded_preview)
		) {
			return '';
		}

		return substr($path, 0, 180);
	}
}

if (!function_exists('bvmgr_operational_issue_error_identity')) {
	function bvmgr_operational_issue_error_identity($error): array
	{
		$error_class = '';
		$error_code = '';
		$fingerprint_source = '';

		$is_wp_error = function_exists('is_wp_error') && is_wp_error($error);
		if ($is_wp_error && is_object($error)) {
			$error_class = get_class($error);
			$raw_code = method_exists($error, 'get_error_code') ? $error->get_error_code() : '';
			$error_code = is_scalar($raw_code) ? (string) $raw_code : '';
			$messages = method_exists($error, 'get_error_messages') ? $error->get_error_messages() : array();
			if (is_array($messages)) {
				foreach ($messages as $message) {
					if (is_scalar($message)) {
						$fingerprint_source .= (string) $message . "\n";
					}
				}
			}
		} elseif ($error instanceof Throwable) {
			$error_class = get_class($error);
			$error_code = (string) $error->getCode();
			$fingerprint_source = $error->getMessage();
		} elseif (is_string($error)) {
			$error_class = 'string';
			$fingerprint_source = $error;
		} else {
			return array();
		}

		$error_class = substr(sanitize_key(str_replace('\\', '_', $error_class)), 0, 64);
		$raw_error_code = trim($error_code);
		$error_code = strtolower($raw_error_code);
		if ($error_code === '' || bvmgr_operational_issue_value_is_tainted($error_code) || !preg_match('/^[a-z0-9_-]+$/', $error_code)) {
			$error_code = '';
		} else {
			$error_code = substr($error_code, 0, 64);
		}
		$identity = array(
			'error_class' => $error_class,
			'error_fingerprint' => substr(hash('sha256', $error_class . "\n" . $raw_error_code . "\n" . $fingerprint_source), 0, 24),
		);
		if ($error_code !== '') {
			$identity['error_code'] = $error_code;
		}
		return $identity;
	}
}

if (!function_exists('bvmgr_operational_issue_context')) {
	function bvmgr_operational_issue_context(array $context): array
	{
		$string_keys = array(
			'hook',
			'action',
			'decision',
			'reason',
			'diagnostic_code',
			'admin_page',
			'screen_id',
			'service',
			'operation',
			'stage',
			'status',
			'provider',
			'entity_type',
			'trigger',
			'mode',
			'source_scope',
			'event_key',
			'correlation',
		);
		$path_keys = array('request_path', 'request_uri', 'route');
		$integer_keys = array(
			'http_status',
			'attempt',
			'retry_count',
			'count',
			'line',
			'entity_id',
			'vendor_id',
			'event_id',
			'plan_id',
			'product_id',
			'post_id',
			'fatal_type',
			'memory_exhausted',
		);
		$decimal_keys = array('elapsed_ms', 'runtime_ms', 'memory_mb', 'peak_memory_mb');
		$clean = array();

		foreach ($context as $key => $value) {
			$key = sanitize_key((string) $key);
			if ($key === '' || !is_scalar($value)) {
				continue;
			}

			if (in_array($key, $path_keys, true)) {
				$path = bvmgr_operational_issue_request_path((string) $value);
				if ($path !== '') {
					$clean[$key] = $path;
				}
				continue;
			}

			if (in_array($key, $integer_keys, true) || in_array($key, $decimal_keys, true)) {
				if (!is_numeric($value)) {
					continue;
				}
				$number = (float) $value;
				if (!is_finite($number) || $number < 0) {
					continue;
				}
				$number = min($number, 1000000000);
				$clean[$key] = in_array($key, $integer_keys, true) ? (int) $number : round($number, 1);
				continue;
			}

			if (!in_array($key, $string_keys, true)) {
				continue;
			}
			$value = strtolower(trim((string) $value));
			if ($value === '' || bvmgr_operational_issue_value_is_tainted($value)) {
				continue;
			}
			if (!preg_match('/^[a-z0-9_-]+$/', $value)) {
				continue;
			}
			$clean[$key] = substr($value, 0, 80);
		}

		return $clean;
	}
}

if (!function_exists('bvmgr_resource_fingerprint_compact_value_with_limit')) {
	function bvmgr_resource_fingerprint_compact_value_with_limit($value, int $depth = 0, int $max_depth = 3)
	{
		if ($depth >= $max_depth) {
			return '...';
		}

		if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
			return $value;
		}

		if (is_string($value)) {
			$value = trim(wp_strip_all_tags($value));
			if ($value === '') {
				return '';
			}
			return (strlen($value) > 180) ? (substr($value, 0, 177) . '...') : $value;
		}

		if (is_array($value)) {
			$out = array();
			$count = 0;
			foreach ($value as $key => $item) {
				if ($count >= 8) {
					$out['...'] = 'truncated';
					break;
				}
				$out[is_int($key) ? $key : sanitize_key((string) $key)] = bvmgr_resource_fingerprint_compact_value_with_limit($item, $depth + 1, $max_depth);
				$count++;
			}
			return $out;
		}

		if (is_object($value)) {
			return 'object:' . get_class($value);
		}

		$value = trim((string) $value);
		return (strlen($value) > 180) ? (substr($value, 0, 177) . '...') : $value;
	}
}

if (!function_exists('bvmgr_resource_fingerprint_compact_value')) {
	function bvmgr_resource_fingerprint_compact_value($value, int $depth = 0)
	{
		return bvmgr_resource_fingerprint_compact_value_with_limit($value, $depth, 3);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_compact_value_deep')) {
	function bvmgr_resource_fingerprint_compact_value_deep($value, int $depth = 0)
	{
		return bvmgr_resource_fingerprint_compact_value_with_limit($value, $depth, 5);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_current_admin_page')) {
	function bvmgr_resource_fingerprint_current_admin_page(): string
	{
		$page = bvmgr_request_read_key($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive admin fingerprinting only reads page scope for diagnostics and remains nonce-free.
		if ($page !== '') {
			return $page;
		}

		$post_type = bvmgr_request_read_key($_GET, 'post_type'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive admin fingerprinting only reads post-type scope for diagnostics and remains nonce-free.
		$post_id = bvmgr_request_read_absint($_GET, 'post'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive admin fingerprinting only reads post IDs to derive read-only screen scope and remains nonce-free.
		if ($post_type === '' && $post_id > 0) {
			$post_type = sanitize_key((string) get_post_type($post_id));
		}
		if ($post_type !== '') {
			return $post_type;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (is_object($screen) && !empty($screen->id)) {
			return sanitize_key((string) $screen->id);
		}

		return '';
	}
}

if (!function_exists('bvmgr_admin_guard_current_screen_id')) {
	function bvmgr_admin_guard_current_screen_id(): string
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (is_object($screen) && !empty($screen->id)) {
			return sanitize_key((string) $screen->id);
		}

		$post_type = bvmgr_request_read_key($_GET, 'post_type'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive admin-screen detection only reads post-type scope for diagnostics and remains nonce-free.
		$post_id = bvmgr_request_read_absint($_GET, 'post'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive admin-screen detection only reads post IDs to derive read-only screen scope and remains nonce-free.
		if ($post_type === '' && $post_id > 0) {
			$post_type = sanitize_key((string) get_post_type($post_id));
		}
		if ($post_type !== '') {
			$pagenow = isset($GLOBALS['pagenow']) ? sanitize_key((string) $GLOBALS['pagenow']) : '';
			if ($pagenow === 'edit.php') {
				return 'edit-' . $post_type;
			}
		}

		return '';
	}
}

if (!function_exists('bvmgr_admin_guard_request_uri')) {
	function bvmgr_admin_guard_request_uri(): string
	{
		return bvmgr_request_current_uri();
	}
}

if (!function_exists('bvmgr_admin_guard_request_method')) {
	function bvmgr_admin_guard_request_method(): string
	{
		return bvmgr_request_method();
	}
}

if (!function_exists('bvmgr_admin_guard_request_value')) {
	function bvmgr_admin_guard_request_value(string $key): string
	{
		static $allowed_keys = array(
			'action',
			'action2',
			'vms_admin_heavy_action',
			'_bvmgr_admin_heavy_nonce',
			'_bvmgr_admin_heavy_nonce',
			'_wpnonce',
		);

		if (!in_array($key, $allowed_keys, true)) {
			return '';
		}

		return bvmgr_request_read_scalar($_REQUEST, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Shared admin guard request keys are allowlisted and only gate passive admin probes plus existing nonce lookup.
	}
}

if (!function_exists('bvmgr_admin_guard_heavy_hooks_disabled')) {
	function bvmgr_admin_guard_heavy_hooks_disabled(): bool
	{
		$disabled = defined('VMS_DISABLE_HEAVY_ADMIN_HOOKS') && VMS_DISABLE_HEAVY_ADMIN_HOOKS;
		return (bool) apply_filters('vms_disable_heavy_admin_hooks', $disabled);
	}
}

if (!function_exists('bvmgr_admin_guard_is_tec_admin_request')) {
	function bvmgr_admin_guard_is_tec_admin_request(): bool
	{
		if (!is_admin()) {
			return false;
		}

		$page = bvmgr_resource_fingerprint_current_admin_page();
		if ($page === 'tribe_events') {
			return true;
		}

		$screen_id = bvmgr_admin_guard_current_screen_id();
		if ($screen_id !== '' && strpos($screen_id, 'tribe_events') !== false) {
			return true;
		}

		$post_type = bvmgr_request_read_key($_GET, 'post_type'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive TEC admin detection only reads post-type scope for diagnostics and remains nonce-free.
		if ($post_type === 'tribe_events') {
			return true;
		}

		$post_id = bvmgr_request_read_absint($_GET, 'post'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive TEC admin detection only reads post IDs to derive screen scope and remains nonce-free.
		return $post_id > 0 && get_post_type($post_id) === 'tribe_events';
	}
}

if (!function_exists('bvmgr_admin_guard_is_passive_admin_list_request')) {
	function bvmgr_admin_guard_is_passive_admin_list_request(): bool
	{
		if (!is_admin()) {
			return false;
		}
		if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('REST_REQUEST') && REST_REQUEST)) {
			return false;
		}
		if (bvmgr_admin_guard_request_method() !== 'get' || bvmgr_request_has_post_data()) {
			return false;
		}

		$action = sanitize_key(bvmgr_admin_guard_request_value('action'));
		$action_2 = sanitize_key(bvmgr_admin_guard_request_value('action2'));
		if (($action !== '' && $action !== '-1') || ($action_2 !== '' && $action_2 !== '-1')) {
			return false;
		}

		$pagenow = isset($GLOBALS['pagenow']) ? sanitize_key((string) $GLOBALS['pagenow']) : '';
		if (in_array($pagenow, array('edit.php', 'upload.php'), true)) {
			return true;
		}

		$screen_id = bvmgr_admin_guard_current_screen_id();
		return $screen_id !== '' && strpos($screen_id, 'edit-') === 0;
	}
}

if (!function_exists('bvmgr_admin_guard_is_vms_admin_page')) {
	function bvmgr_admin_guard_is_vms_admin_page(): bool
	{
		$page = bvmgr_resource_fingerprint_current_admin_page();
		if ($page === 'vms_event_plan') {
			return true;
		}

		if ($page !== '' && (strpos($page, 'vms') === 0 || strpos($page, 'vms-') === 0)) {
			return true;
		}

		$screen_id = bvmgr_admin_guard_current_screen_id();
		return $screen_id !== '' && strpos($screen_id, 'vms_') !== false;
	}
}

if (!function_exists('bvmgr_admin_guard_is_verified_action')) {
	function bvmgr_admin_guard_is_verified_action(string $expected_action = ''): bool
	{
		$request_action = sanitize_key(bvmgr_admin_guard_request_value('vms_admin_heavy_action'));
		if ($request_action === '') {
			return false;
		}

		$expected_action = sanitize_key($expected_action);
		if ($expected_action !== '' && $request_action !== $expected_action) {
			return false;
		}

		$nonce = bvmgr_admin_guard_request_value('_bvmgr_admin_heavy_nonce');
		if ($nonce === '') {
			$nonce = bvmgr_admin_guard_request_value('_bvmgr_admin_heavy_nonce');
		}
		if ($nonce === '') {
			$nonce = bvmgr_admin_guard_request_value('_wpnonce');
		}
		$nonce = sanitize_text_field($nonce);

		if ($nonce === '' || !function_exists('wp_verify_nonce')) {
			return false;
		}

		if (!bvmgr_verify_nonce_compat($nonce, 'bvmgr_admin_heavy:' . $request_action)) {
			return false;
		}

		return current_user_can('manage_options');
	}
}

if (!function_exists('bvmgr_admin_guard_should_allow_heavy_block')) {
	function bvmgr_admin_guard_should_allow_heavy_block(string $hook_name, array $context = array()): array
	{
		$task = sanitize_key((string) ($context['task'] ?? $hook_name));
		$allow_action = sanitize_key((string) ($context['allow_action'] ?? $task));
		$verified_action = bvmgr_admin_guard_is_verified_action($allow_action);
		$result = array(
			'allowed' => false,
			'reason' => 'unknown',
			'task' => $task,
			'allow_action' => $allow_action,
		);

		if ((defined('WP_CLI') && WP_CLI) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
			$result['allowed'] = true;
			$result['reason'] = 'non_admin_runtime';
			return $result;
		}

		if (!is_admin()) {
			$result['reason'] = 'not_admin';
			return $result;
		}

		if (bvmgr_admin_guard_heavy_hooks_disabled()) {
			$result['reason'] = 'constant_disabled';
			return $result;
		}

		if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('REST_REQUEST') && REST_REQUEST)) {
			$result['allowed'] = $verified_action;
			$result['reason'] = $verified_action ? 'verified_async_action' : 'unverified_async_request';
			return $result;
		}

		if (bvmgr_admin_guard_is_tec_admin_request() && !$verified_action) {
			$result['reason'] = 'passive_tec_admin';
			return $result;
		}

		if (bvmgr_admin_guard_is_passive_admin_list_request() && !$verified_action) {
			$result['reason'] = 'passive_admin_list';
			return $result;
		}

		if ($verified_action) {
			$result['allowed'] = true;
			$result['reason'] = 'verified_admin_action';
			return $result;
		}

		$result['reason'] = bvmgr_admin_guard_is_vms_admin_page() ? 'passive_vms_admin' : 'unscoped_admin';
		return $result;
	}
}

if (!function_exists('bvmgr_admin_guard_trace')) {
	function bvmgr_admin_guard_trace(string $hook_name, string $decision, array $context = array(), float $started_at = 0.0): void
	{
		$trace_context = bvmgr_operational_issue_context(array(
			'hook' => $hook_name,
			'action' => (string) ($context['task'] ?? ''),
			'decision' => $decision,
			'reason' => (string) ($context['reason'] ?? ''),
			'admin_page' => bvmgr_resource_fingerprint_current_admin_page(),
			'screen_id' => bvmgr_admin_guard_current_screen_id(),
		));
		$hook_name = (string) ($trace_context['hook'] ?? 'heavy_admin_block');
		$action = (string) ($trace_context['action'] ?? '');
		$decision = (string) ($trace_context['decision'] ?? '');
		$reason = (string) ($trace_context['reason'] ?? '');
		$admin_page = (string) ($trace_context['admin_page'] ?? '');
		$screen_id = (string) ($trace_context['screen_id'] ?? '');
		$elapsed_ms = $started_at > 0 ? max(0.0, round((microtime(true) - $started_at) * 1000, 1)) : 0.0;
		$payload = array(
			'hook' => $hook_name,
			'action' => $action,
			'decision' => $decision,
			'reason' => $reason,
			'request_uri' => bvmgr_operational_issue_request_path(bvmgr_admin_guard_request_uri()),
			'admin_page' => $admin_page,
			'screen_id' => $screen_id,
			'elapsed_ms' => $elapsed_ms,
			'memory_mb' => round(((int) memory_get_usage(true)) / 1048576, 1),
		);

		bvmgr_resource_fingerprint_flag('heavy_admin_guard', $payload);
		bvmgr_resource_fingerprint_add_marker('heavy_admin_guard.' . $hook_name, $elapsed_ms, $payload);
		bvmgr_record_operational_issue('admin_guard_trace', $payload);
	}
}

if (!function_exists('bvmgr_admin_guard_should_probe_passive_tec_request')) {
	function bvmgr_admin_guard_should_probe_passive_tec_request(): bool
	{
		if (!is_admin()) {
			return false;
		}
		if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('REST_REQUEST') && REST_REQUEST)) {
			return false;
		}
		if (bvmgr_admin_guard_request_method() !== 'get' || bvmgr_request_has_post_data()) {
			return false;
		}
		if (!bvmgr_admin_guard_is_tec_admin_request()) {
			return false;
		}

		$action = sanitize_key(bvmgr_admin_guard_request_value('action'));
		$action_2 = sanitize_key(bvmgr_admin_guard_request_value('action2'));
		return (($action === '' || $action === '-1') && ($action_2 === '' || $action_2 === '-1'));
	}
}

if (!function_exists('bvmgr_admin_guard_hook_probe_top_hooks')) {
	function bvmgr_admin_guard_hook_probe_top_hooks(array $counts, int $limit = 12): array
	{
		if (empty($counts)) {
			return array();
		}

		arsort($counts);
		return array_slice($counts, 0, max(1, $limit), true);
	}
}

if (!function_exists('bvmgr_admin_guard_hook_probe_watch_hooks')) {
	function bvmgr_admin_guard_hook_probe_watch_hooks(): array
	{
		return array(
			'admin_init',
			'current_screen',
			'load-edit.php',
			'pre_get_posts',
			'parse_query',
			'wp',
			'admin_head',
			'admin_enqueue_scripts',
			'wp_ajax_heartbeat',
			'transition_post_status',
			'add_post_metadata',
			'update_post_metadata',
			'delete_post_metadata',
			'added_post_meta',
			'updated_post_meta',
			'deleted_post_meta',
		);
	}
}

if (!function_exists('bvmgr_admin_guard_hook_probe_track')) {
	function bvmgr_admin_guard_hook_probe_track(): void
	{
		$state = $GLOBALS['bvmgr_admin_guard_hook_probe'] ?? array();
		if (empty($state['enabled']) || !is_array($state)) {
			return;
		}

		$hook_name = sanitize_key((string) current_filter());
		if ($hook_name === '') {
			return;
		}

		$state['total_hooks'] = (int) ($state['total_hooks'] ?? 0) + 1;
		if (($state['last_hook'] ?? '') === $hook_name) {
			$state['same_hook_streak'] = (int) ($state['same_hook_streak'] ?? 0) + 1;
		} else {
			$state['same_hook_streak'] = 1;
		}
		$state['last_hook'] = $hook_name;
		$state['max_same_hook_streak'] = max((int) ($state['max_same_hook_streak'] ?? 0), (int) ($state['same_hook_streak'] ?? 0));

		if (isset($state['counts'][$hook_name])) {
			$state['counts'][$hook_name]++;
		} elseif (count((array) ($state['counts'] ?? array())) < 64) {
			$state['counts'][$hook_name] = 1;
		} else {
			$state['overflow_hooks'] = (int) ($state['overflow_hooks'] ?? 0) + 1;
		}

		$state['recent_hooks'][] = $hook_name;
		$state['recent_hooks'] = array_slice((array) $state['recent_hooks'], -16);

		$current_memory_mb = round(((int) memory_get_usage(true)) / 1048576, 1);
		if ($current_memory_mb > (float) ($state['peak_memory_mb'] ?? 0.0)) {
			$state['peak_memory_mb'] = $current_memory_mb;
			$state['high_water_hook'] = $hook_name;
		}

		$GLOBALS['bvmgr_admin_guard_hook_probe'] = $state;
	}
}

if (!function_exists('bvmgr_admin_guard_hook_probe_shutdown')) {
	function bvmgr_admin_guard_hook_probe_shutdown(): void
	{
		$state = $GLOBALS['bvmgr_admin_guard_hook_probe'] ?? array();
		if (empty($state['enabled']) || !is_array($state) || !empty($state['finalized'])) {
			return;
		}

		$state['finalized'] = true;
		$GLOBALS['bvmgr_admin_guard_hook_probe'] = $state;

		$error = error_get_last();
		$fatal = is_array($error) && in_array((int) ($error['type'] ?? 0), array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR), true);
		$filter_stack = $GLOBALS['wp_current_filter'] ?? array();
		$payload = array(
			'task' => 'passive_tec_admin',
			'reason' => $fatal ? 'passive_tec_admin_fatal' : 'passive_tec_admin_trace',
			'total_hooks' => (int) ($state['total_hooks'] ?? 0),
			'last_hook' => sanitize_key((string) ($state['last_hook'] ?? '')),
			'max_same_hook_streak' => (int) ($state['max_same_hook_streak'] ?? 0),
			'high_water_hook' => sanitize_key((string) ($state['high_water_hook'] ?? '')),
			'peak_memory_mb' => round((float) ($state['peak_memory_mb'] ?? 0.0), 1),
			'overflow_hooks' => (int) ($state['overflow_hooks'] ?? 0),
			'top_hooks' => bvmgr_admin_guard_hook_probe_top_hooks((array) ($state['counts'] ?? array())),
			'recent_hooks' => array_values((array) ($state['recent_hooks'] ?? array())),
			'filter_stack_tail' => array_values(array_slice(array_map('sanitize_key', is_array($filter_stack) ? $filter_stack : array()), -10)),
		);

		if ($fatal) {
			$payload['fatal_type'] = (int) ($error['type'] ?? 0);
			$payload['fatal_file'] = str_replace(ABSPATH, '', (string) ($error['file'] ?? ''));
			$payload['fatal_line'] = (int) ($error['line'] ?? 0);
			$payload['fatal_message'] = trim((string) ($error['message'] ?? ''));
		}

		bvmgr_admin_guard_trace(
			'tec_hook_probe',
			$fatal ? 'fatal' : 'observed',
			$payload,
			(float) ($state['started_at'] ?? 0.0)
		);
	}
}

if (!function_exists('bvmgr_admin_guard_hook_probe_bootstrap')) {
	function bvmgr_admin_guard_hook_probe_bootstrap(): void
	{
		if (!bvmgr_admin_guard_should_probe_passive_tec_request()) {
			return;
		}
		if (!empty($GLOBALS['bvmgr_admin_guard_hook_probe']) && is_array($GLOBALS['bvmgr_admin_guard_hook_probe'])) {
			return;
		}

		$GLOBALS['bvmgr_admin_guard_hook_probe'] = array(
			'enabled' => true,
			'started_at' => microtime(true),
			'total_hooks' => 0,
			'last_hook' => '',
			'same_hook_streak' => 0,
			'max_same_hook_streak' => 0,
			'peak_memory_mb' => round(((int) memory_get_usage(true)) / 1048576, 1),
			'high_water_hook' => '',
			'overflow_hooks' => 0,
			'counts' => array(),
			'recent_hooks' => array(),
			'finalized' => false,
		);

		foreach (bvmgr_admin_guard_hook_probe_watch_hooks() as $hook_name) {
			add_action($hook_name, 'bvmgr_admin_guard_hook_probe_track', 999);
		}
		register_shutdown_function('bvmgr_admin_guard_hook_probe_shutdown');
	}
}

if (!function_exists('bvmgr_admin_guard_acquire_lock')) {
	function bvmgr_admin_guard_acquire_lock(string $lock_name, int $ttl_seconds = 60): string
	{
		$lock_name = sanitize_key($lock_name);
		if ($lock_name === '') {
			return '';
		}

		$option_key = 'vms_admin_heavy_lock_' . $lock_name;
		$now = time();
		$expires_at = $now + max(5, $ttl_seconds);
		if (add_option($option_key, $expires_at, '', false)) {
			return $option_key;
		}

		$current = (int) get_option($option_key, 0);
		if ($current > $now) {
			return '';
		}

		update_option($option_key, $expires_at, false);
		return $option_key;
	}
}

if (!function_exists('bvmgr_admin_guard_release_lock')) {
	function bvmgr_admin_guard_release_lock(string $option_key): void
	{
		$option_key = sanitize_key($option_key);
		if ($option_key === '') {
			return;
		}
		delete_option($option_key);
	}
}

if (!function_exists('bvmgr_admin_guard_begin')) {
	function bvmgr_admin_guard_begin(string $hook_name, array $context = array())
	{
		$decision = bvmgr_admin_guard_should_allow_heavy_block($hook_name, $context);
		$context = array_merge($context, $decision);
		if (empty($decision['allowed'])) {
			bvmgr_admin_guard_trace($hook_name, 'skipped', $context);
			return false;
		}

		$lock_key = '';
		$lock_ttl = isset($context['lock_ttl']) ? max(0, (int) $context['lock_ttl']) : 0;
		if ($lock_ttl > 0) {
			$lock_key = bvmgr_admin_guard_acquire_lock((string) ($context['lock_name'] ?? ($decision['task'] ?? $hook_name)), $lock_ttl);
			if ($lock_key === '') {
				$context['reason'] = 'lock_busy';
				bvmgr_admin_guard_trace($hook_name, 'skipped', $context);
				return false;
			}
		}

		$token = array(
			'hook_name' => $hook_name,
			'lock_key' => $lock_key,
			'started_at' => microtime(true),
			'context' => $context,
		);
		bvmgr_admin_guard_trace($hook_name, 'allowed', $context);
		return $token;
	}
}

if (!function_exists('bvmgr_admin_guard_finish')) {
	function bvmgr_admin_guard_finish($token, array $context = array()): void
	{
		if (!is_array($token)) {
			return;
		}

		$lock_key = isset($token['lock_key']) ? (string) $token['lock_key'] : '';
		if ($lock_key !== '') {
			bvmgr_admin_guard_release_lock($lock_key);
		}

		$base_context = isset($token['context']) && is_array($token['context']) ? $token['context'] : array();
		bvmgr_admin_guard_trace(
			(string) ($token['hook_name'] ?? 'heavy_admin_block'),
			'finished',
			array_merge($base_context, $context),
			(float) ($token['started_at'] ?? 0.0)
		);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_sensitive_admin_scope')) {
	function bvmgr_resource_fingerprint_sensitive_admin_scope(): array
	{
		if (!is_admin()) {
			return array();
		}

		$page = bvmgr_resource_fingerprint_current_admin_page();
		$scoped_pages = (array) apply_filters('vms_resource_fingerprint_sensitive_admin_pages', array(
			'vms-event-command-center' => 'event_command_center',
			'vms-data-tools' => 'data_tools_root',
			'vms-dt-report-single-event' => 'data_tools_single_event',
			'vms-dt-report-compare-events' => 'data_tools_compare_events',
			'vms-dt-report-season-year' => 'data_tools_season_year',
			'vms-dt-report-performer-payouts' => 'data_tools_performer_payouts',
			'vms-dt-report-profitability' => 'data_tools_profitability',
			'vms-dt-report-ticket-pace' => 'data_tools_ticket_pace',
			'vms-dt-revenue-intelligence' => 'data_tools_revenue_intelligence',
			'vms_event_plan' => 'event_plan_editor',
		));

		if ($page !== '' && isset($scoped_pages[$page])) {
			return array(
				'page' => $page,
				'page_slug' => $page,
				'scope_reason' => sanitize_key((string) $scoped_pages[$page]),
			);
		}

		$post_id = bvmgr_request_read_absint($_GET, 'post'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive sensitive-scope detection only reads post IDs to derive admin scope and remains nonce-free.
		if ($post_id > 0 && get_post_type($post_id) === 'vms_event_plan') {
			return array(
				'page' => ($page !== '') ? $page : 'vms_event_plan',
				'page_slug' => ($page !== '') ? $page : 'vms_event_plan',
				'scope_reason' => 'event_plan_editor',
				'post_id' => $post_id,
			);
		}

		if (function_exists('bvmgr_admin_guard_is_tec_admin_request') && bvmgr_admin_guard_is_tec_admin_request()) {
			return array(
				'page' => ($page !== '') ? $page : 'tribe_events',
				'page_slug' => ($page !== '') ? $page : 'tribe_events',
				'scope_reason' => 'tec_admin_request',
				'screen_id' => bvmgr_admin_guard_current_screen_id(),
			);
		}

		return array();
	}
}

if (!function_exists('bvmgr_resource_fingerprint_is_sensitive_admin_request')) {
	function bvmgr_resource_fingerprint_is_sensitive_admin_request(): bool
	{
		return !empty(bvmgr_resource_fingerprint_sensitive_admin_scope());
	}
}

if (!function_exists('bvmgr_resource_fingerprint_bootstrap')) {
	function bvmgr_resource_fingerprint_bootstrap(): void
	{
		if (!empty($GLOBALS['bvmgr_resource_fingerprint']) && is_array($GLOBALS['bvmgr_resource_fingerprint'])) {
			return;
		}

		$started_at = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
		$GLOBALS['bvmgr_resource_fingerprint'] = array(
			'started_at' => $started_at,
			'flags' => array(),
			'markers' => array(),
			'open_spans' => array(),
			'notes' => array(),
			'finalized' => false,
		);

		register_shutdown_function('bvmgr_resource_fingerprint_shutdown');
	}
}

if (!function_exists('bvmgr_resource_fingerprint_flag')) {
	function bvmgr_resource_fingerprint_flag(string $flag, $value = true): void
	{
		$flag = sanitize_key($flag);
		if ($flag === '') {
			return;
		}

		bvmgr_resource_fingerprint_bootstrap();
		$state = is_array($GLOBALS['bvmgr_resource_fingerprint'] ?? null) ? $GLOBALS['bvmgr_resource_fingerprint'] : array();
		$clean_value = bvmgr_resource_fingerprint_compact_value($value);
		if (!isset($state['flags'][$flag])) {
			$state['flags'][$flag] = array();
		}
		if (!is_array($state['flags'][$flag])) {
			$state['flags'][$flag] = array($state['flags'][$flag]);
		}
		$state['flags'][$flag][] = $clean_value;
		$state['flags'][$flag] = array_slice($state['flags'][$flag], -8);
		$GLOBALS['bvmgr_resource_fingerprint'] = $state;
	}
}

if (!function_exists('bvmgr_resource_fingerprint_note')) {
	function bvmgr_resource_fingerprint_note(string $message): void
	{
		$message = trim(wp_strip_all_tags($message));
		if ($message === '') {
			return;
		}

		bvmgr_resource_fingerprint_bootstrap();
		$state = is_array($GLOBALS['bvmgr_resource_fingerprint'] ?? null) ? $GLOBALS['bvmgr_resource_fingerprint'] : array();
		$state['notes'][] = bvmgr_resource_fingerprint_compact_value($message);
		$state['notes'] = array_slice((array) $state['notes'], -8);
		$GLOBALS['bvmgr_resource_fingerprint'] = $state;
	}
}

if (!function_exists('bvmgr_resource_fingerprint_add_marker')) {
	function bvmgr_resource_fingerprint_add_marker(string $label, float $elapsed_ms, array $context = array()): void
	{
		$label = trim($label);
		if ($label === '') {
			return;
		}

		bvmgr_resource_fingerprint_bootstrap();
		$state = is_array($GLOBALS['bvmgr_resource_fingerprint'] ?? null) ? $GLOBALS['bvmgr_resource_fingerprint'] : array();
		$state['markers'][] = array(
			'label' => $label,
			'elapsed_ms' => max(0.0, round($elapsed_ms, 1)),
			'context' => bvmgr_resource_fingerprint_compact_value($context),
		);
		$state['markers'] = array_slice((array) $state['markers'], -1 * bvmgr_resource_fingerprint_max_markers());
		$GLOBALS['bvmgr_resource_fingerprint'] = $state;
	}
}

if (!function_exists('bvmgr_resource_fingerprint_span_start')) {
	function bvmgr_resource_fingerprint_span_start(string $label, array $context = array()): void
	{
		$label = trim($label);
		if ($label === '') {
			return;
		}

		bvmgr_resource_fingerprint_bootstrap();
		$state = is_array($GLOBALS['bvmgr_resource_fingerprint'] ?? null) ? $GLOBALS['bvmgr_resource_fingerprint'] : array();
		$state['open_spans'][$label] = array(
			'started_at' => microtime(true),
			'context' => bvmgr_resource_fingerprint_compact_value($context),
		);
		$GLOBALS['bvmgr_resource_fingerprint'] = $state;
	}
}

if (!function_exists('bvmgr_resource_fingerprint_span_finish')) {
	function bvmgr_resource_fingerprint_span_finish(string $label, array $context = array()): void
	{
		$label = trim($label);
		if ($label === '') {
			return;
		}

		bvmgr_resource_fingerprint_bootstrap();
		$state = is_array($GLOBALS['bvmgr_resource_fingerprint'] ?? null) ? $GLOBALS['bvmgr_resource_fingerprint'] : array();
		$open = isset($state['open_spans'][$label]) && is_array($state['open_spans'][$label]) ? $state['open_spans'][$label] : null;
		if (!is_array($open) || empty($open['started_at'])) {
			return;
		}

		$merged = array();
		if (!empty($open['context']) && is_array($open['context'])) {
			$merged = $open['context'];
		}
		foreach ($context as $key => $value) {
			$merged[sanitize_key((string) $key)] = bvmgr_resource_fingerprint_compact_value($value);
		}

		unset($state['open_spans'][$label]);
		$GLOBALS['bvmgr_resource_fingerprint'] = $state;
		bvmgr_resource_fingerprint_add_marker($label, (microtime(true) - (float) $open['started_at']) * 1000, $merged);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_wp_cron_counts')) {
	function bvmgr_resource_fingerprint_wp_cron_counts(): array
	{
		if (!function_exists('_get_cron_array')) {
			return array();
		}

		$cron = _get_cron_array();
		if (!is_array($cron) || empty($cron)) {
			return array(
				'due_event_count' => 0,
				'due_hook_count' => 0,
				'due_vms_event_count' => 0,
			);
		}

		$now = time();
		$due_event_count = 0;
		$due_hook_count = 0;
		$due_vms_event_count = 0;
		foreach ($cron as $timestamp => $events) {
			if ((int) $timestamp > $now || !is_array($events)) {
				continue;
			}

			foreach ($events as $hook => $instances) {
				if (!is_array($instances)) {
					continue;
				}
				$due_hook_count++;
				$instance_count = count($instances);
				$due_event_count += $instance_count;
				if (bvmgr_is_owned_cron_hook((string) $hook)) {
					$due_vms_event_count += $instance_count;
				}
			}
		}

		return array(
			'due_event_count' => $due_event_count,
			'due_hook_count' => $due_hook_count,
			'due_vms_event_count' => $due_vms_event_count,
		);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_action_scheduler_counts')) {
	function bvmgr_resource_fingerprint_action_scheduler_counts(): array
	{
		if (!class_exists('ActionScheduler') || !class_exists('ActionScheduler_Store') || !method_exists('ActionScheduler', 'store')) {
			return array();
		}

		try {
			$store = ActionScheduler::store();
			if (!is_object($store) || !method_exists($store, 'query_actions')) {
				return array();
			}

			return array(
				'pending_count' => (int) $store->query_actions(array(
					'status' => ActionScheduler_Store::STATUS_PENDING,
					'per_page' => 1,
					'orderby' => 'none',
				), 'count'),
				'running_count' => (int) $store->query_actions(array(
					'status' => ActionScheduler_Store::STATUS_RUNNING,
					'per_page' => 1,
					'orderby' => 'none',
				), 'count'),
			);
		} catch (Throwable $e) {
			return array(
				'error' => bvmgr_operational_issue_error_identity($e),
			);
		}
	}
}

if (!function_exists('bvmgr_resource_fingerprint_store_entry')) {
	function bvmgr_resource_fingerprint_store_entry(array $entry): void
	{
		$entries = get_option(bvmgr_resource_fingerprint_option_key(), array());
		$entries = is_array($entries) ? $entries : array();
		$entries[] = $entry;
		$entries = array_slice($entries, -1 * bvmgr_resource_fingerprint_log_limit());
		update_option(bvmgr_resource_fingerprint_option_key(), $entries, false);
	}
}

if (!function_exists('bvmgr_record_operational_issue')) {
	function bvmgr_record_operational_issue(string $event_code, array $context = array(), $error = null): bool
	{
		static $in_progress = false;

		$event_code = strtolower(trim($event_code));
		if (
			$event_code === ''
			|| strlen($event_code) > 64
			|| !preg_match('/^[a-z0-9_-]+$/', $event_code)
			|| preg_match('/(?:^|[_-])(?:token|secret|nonce|cookie|password|authorization|bearer)(?:[_-]|$)/', $event_code)
			|| preg_match('/^(?:sk|pk)_(?:live|test)_/', $event_code)
			|| preg_match('/^[a-z0-9]{40,64}$/', $event_code)
		) {
			return false;
		}
		if (
			$event_code === ''
			|| !function_exists('get_option')
			|| !function_exists('update_option')
			|| !function_exists('bvmgr_resource_fingerprint_store_entry')
		) {
			return false;
		}
		if ($in_progress) {
			return false;
		}

		$in_progress = true;
		try {
			$safe_context = bvmgr_operational_issue_context($context);
			$error_identity = bvmgr_operational_issue_error_identity($error);
			$issue = array(
				'event_code' => $event_code,
				'context' => $safe_context,
			);
			if (!empty($error_identity)) {
				$issue['error'] = $error_identity;
			}

			$request_path = '';
			foreach (array('request_uri', 'request_path', 'route') as $path_key) {
				if (!empty($safe_context[$path_key])) {
					$request_path = (string) $safe_context[$path_key];
					break;
				}
			}
			$admin_page = isset($safe_context['admin_page']) ? (string) $safe_context['admin_page'] : bvmgr_resource_fingerprint_current_admin_page();
			$screen_id = isset($safe_context['screen_id']) ? (string) $safe_context['screen_id'] : bvmgr_admin_guard_current_screen_id();
			$runtime_ms = (float) ($safe_context['runtime_ms'] ?? ($safe_context['elapsed_ms'] ?? 0.0));
			$memory_mb = (float) ($safe_context['peak_memory_mb'] ?? ($safe_context['memory_mb'] ?? 0.0));

			bvmgr_resource_fingerprint_store_entry(array(
				'captured_at_gmt' => gmdate('Y-m-d H:i:s'),
				'runtime_ms' => (int) round(max(0.0, $runtime_ms)),
				'peak_memory_mb' => round(max(0.0, $memory_mb), 1),
				'request_uri' => $request_path,
				'request_method' => bvmgr_admin_guard_request_method(),
				'admin_page' => substr(sanitize_key($admin_page), 0, 80),
				'screen_id' => substr(sanitize_key($screen_id), 0, 80),
				'user_id' => 0,
				'context' => array(
					'admin' => function_exists('is_admin') && is_admin() ? 1 : 0,
					'ajax' => function_exists('wp_doing_ajax') && wp_doing_ajax() ? 1 : 0,
					'rest' => defined('REST_REQUEST') && REST_REQUEST ? 1 : 0,
					'cron' => function_exists('wp_doing_cron') && wp_doing_cron() ? 1 : 0,
					'wp_cli' => defined('WP_CLI') && WP_CLI ? 1 : 0,
				),
				'flags' => array('operational_issue' => array($issue)),
				'markers' => array(),
				'notes' => array(),
				'due_wp_cron' => array(),
				'action_scheduler' => array(),
			));

			return true;
		} catch (Throwable $e) {
			return false;
		} finally {
			$in_progress = false;
		}
	}
}

if (!function_exists('bvmgr_resource_fingerprint_should_log')) {
	function bvmgr_resource_fingerprint_should_log(array $state, float $runtime_seconds, int $peak_memory_bytes): bool
	{
		if ($runtime_seconds >= bvmgr_resource_fingerprint_threshold_seconds()) {
			return true;
		}

		if ($peak_memory_bytes >= bvmgr_resource_fingerprint_memory_threshold_bytes()) {
			return true;
		}

		foreach (array('plugin_activation', 'plugin_deactivation', 'plugin_update', 'ecc_calculation', 'dt_report', 'vms_queue', 'action_scheduler_run', 'cron_run', 'action_scheduler_async_blocked', 'heavy_admin_guard', 'dt_admin_trace', 'tec_hook_probe') as $flag) {
			if (!empty($state['flags'][$flag])) {
				return true;
			}
		}

		if ((defined('WP_CLI') && WP_CLI) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
			return true;
		}

		return false;
	}
}

if (!function_exists('bvmgr_resource_fingerprint_shutdown')) {
	function bvmgr_resource_fingerprint_shutdown(): void
	{
		$state = is_array($GLOBALS['bvmgr_resource_fingerprint'] ?? null) ? $GLOBALS['bvmgr_resource_fingerprint'] : array();
		if (empty($state) || !empty($state['finalized'])) {
			return;
		}

		foreach ((array) ($state['open_spans'] ?? array()) as $label => $open) {
			if (!is_array($open) || empty($open['started_at'])) {
				continue;
			}
			bvmgr_resource_fingerprint_span_finish((string) $label, array('auto_closed' => true));
		}

		$state = is_array($GLOBALS['bvmgr_resource_fingerprint'] ?? null) ? $GLOBALS['bvmgr_resource_fingerprint'] : array();
		$state['finalized'] = true;
		$GLOBALS['bvmgr_resource_fingerprint'] = $state;

		$runtime_seconds = max(0.0, microtime(true) - (float) ($state['started_at'] ?? microtime(true)));
		$peak_memory_bytes = function_exists('memory_get_peak_usage') ? (int) memory_get_peak_usage(true) : 0;
		if (!bvmgr_resource_fingerprint_should_log($state, $runtime_seconds, $peak_memory_bytes)) {
			return;
		}

			$screen = function_exists('get_current_screen') ? get_current_screen() : null;
			$entry = array(
				'captured_at_gmt' => gmdate('Y-m-d H:i:s'),
				'runtime_ms' => (int) round($runtime_seconds * 1000),
				'peak_memory_mb' => round($peak_memory_bytes / 1048576, 1),
				'request_uri' => bvmgr_operational_issue_request_path(bvmgr_admin_guard_request_uri()),
				'request_method' => bvmgr_admin_guard_request_method(),
				'admin_page' => bvmgr_resource_fingerprint_current_admin_page(),
				'screen_id' => (is_object($screen) && !empty($screen->id)) ? sanitize_key((string) $screen->id) : '',
				'user_id' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
				'context' => array(
					'admin' => is_admin() ? 1 : 0,
					'ajax' => (function_exists('wp_doing_ajax') && wp_doing_ajax()) ? 1 : 0,
					'rest' => (defined('REST_REQUEST') && REST_REQUEST) ? 1 : 0,
					'cron' => (function_exists('wp_doing_cron') && wp_doing_cron()) ? 1 : 0,
					'wp_cli' => (defined('WP_CLI') && WP_CLI) ? 1 : 0,
				),
				'flags' => bvmgr_resource_fingerprint_compact_value_deep((array) ($state['flags'] ?? array())),
				'markers' => bvmgr_resource_fingerprint_compact_value_deep((array) ($state['markers'] ?? array())),
				'notes' => bvmgr_resource_fingerprint_compact_value_deep((array) ($state['notes'] ?? array())),
				'due_wp_cron' => bvmgr_resource_fingerprint_wp_cron_counts(),
			'action_scheduler' => bvmgr_resource_fingerprint_action_scheduler_counts(),
		);

		bvmgr_resource_fingerprint_store_entry($entry);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_recent_entries')) {
	function bvmgr_resource_fingerprint_recent_entries(int $limit = 25): array
	{
		$entries = get_option(bvmgr_resource_fingerprint_option_key(), array());
		$entries = is_array($entries) ? $entries : array();
		return array_reverse(array_slice($entries, -1 * max(1, $limit)));
	}
}

if (!function_exists('bvmgr_resource_fingerprint_clear_entries')) {
	function bvmgr_resource_fingerprint_clear_entries(): void
	{
		update_option(bvmgr_resource_fingerprint_option_key(), array(), false);
	}
}

if (!function_exists('bvmgr_render_resource_fingerprint_admin_screen')) {
	function bvmgr_render_resource_fingerprint_admin_screen(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this screen.', 'backstage-venue-manager'));
		}

		$cleared = false;
		if (bvmgr_admin_guard_request_method() === 'post' && isset($_POST['vms_clear_resource_fingerprints'])) {
			bvmgr_check_admin_referer_compat('bvmgr_clear_resource_fingerprints');
			bvmgr_resource_fingerprint_clear_entries();
			$cleared = true;
		}

		$entries = bvmgr_resource_fingerprint_recent_entries(25);
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Backstage Venue Manager Resource Fingerprints', 'backstage-venue-manager') . '</h1>';
		echo '<p>' . esc_html__('Threshold-based request and task snapshots for slow/heavy admin, cron, Action Scheduler, ECC, and DT work.', 'backstage-venue-manager') . '</p>';
		if ($cleared) {
			echo '<div class="notice notice-success"><p>' . esc_html__('Resource fingerprints cleared.', 'backstage-venue-manager') . '</p></div>';
		}
		echo '<p>';
		echo esc_html(sprintf(
			/* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
			__('Logging threshold: %1$ss or %2$s MB peak memory. Slow/heavy request context also records WP-Cron, Action Scheduler, and calculation markers.', 'backstage-venue-manager'),
			number_format(bvmgr_resource_fingerprint_threshold_seconds(), 1),
			number_format(bvmgr_resource_fingerprint_memory_threshold_bytes() / 1048576, 0)
		));
		echo '</p>';
		echo '<form method="post" action="">';
		wp_nonce_field('bvmgr_clear_resource_fingerprints');
		echo '<p><button type="submit" class="button" name="vms_clear_resource_fingerprints" value="1">' . esc_html__('Clear Log', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';

		if (empty($entries)) {
			echo '<p>' . esc_html__('No fingerprint entries recorded yet.', 'backstage-venue-manager') . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('When', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Runtime', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Memory', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Context', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Request', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Queues / Markers', 'backstage-venue-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($entries as $entry) {
			$when = trim((string) ($entry['captured_at_gmt'] ?? ''));
			$context = is_array($entry['context'] ?? null) ? (array) $entry['context'] : array();
			$flags = is_array($entry['flags'] ?? null) ? (array) $entry['flags'] : array();
			$markers = is_array($entry['markers'] ?? null) ? (array) $entry['markers'] : array();
			$cron_counts = is_array($entry['due_wp_cron'] ?? null) ? (array) $entry['due_wp_cron'] : array();
			$as_counts = is_array($entry['action_scheduler'] ?? null) ? (array) $entry['action_scheduler'] : array();
			$context_bits = array();
			foreach (array('admin', 'ajax', 'rest', 'cron', 'wp_cli') as $key) {
				if (!empty($context[$key])) {
					$context_bits[] = $key;
				}
			}
			if (empty($context_bits)) {
				$context_bits[] = 'frontend';
			}
			echo '<tr>';
			echo '<td>' . esc_html($when !== '' ? $when . ' GMT' : '—') . '<br /><span class="description">User ' . esc_html((string) absint($entry['user_id'] ?? 0)) . '</span></td>';
			echo '<td>' . esc_html(number_format((int) ($entry['runtime_ms'] ?? 0) / 1000, 2)) . 's</td>';
			echo '<td>' . esc_html(number_format((float) ($entry['peak_memory_mb'] ?? 0), 1)) . ' MB</td>';
			echo '<td>' . esc_html(implode(', ', $context_bits)) . '</td>';
			echo '<td><strong>' . esc_html((string) ($entry['admin_page'] ?? '')) . '</strong><br /><span class="description">' . esc_html((string) ($entry['request_uri'] ?? '')) . '</span></td>';
			echo '<td>';
			echo '<div><strong>' . esc_html__('WP-Cron due', 'backstage-venue-manager') . ':</strong> ' . esc_html((string) absint($cron_counts['due_event_count'] ?? 0)) . '</div>';
			if (!empty($as_counts)) {
				echo '<div><strong>' . esc_html__('AS pending/running', 'backstage-venue-manager') . ':</strong> ' . esc_html((string) absint($as_counts['pending_count'] ?? 0)) . ' / ' . esc_html((string) absint($as_counts['running_count'] ?? 0)) . '</div>';
			}
			if (!empty($flags)) {
				echo '<details><summary>' . esc_html__('Flags', 'backstage-venue-manager') . '</summary><pre style="white-space:pre-wrap;">' . esc_html(wp_json_encode($flags, JSON_PRETTY_PRINT)) . '</pre></details>';
			}
			if (!empty($markers)) {
				echo '<details><summary>' . esc_html__('Markers', 'backstage-venue-manager') . '</summary><pre style="white-space:pre-wrap;">' . esc_html(wp_json_encode($markers, JSON_PRETTY_PRINT)) . '</pre></details>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_resource_fingerprint_track_sensitive_admin_scope')) {
	function bvmgr_resource_fingerprint_track_sensitive_admin_scope(): void
	{
		$scope = bvmgr_resource_fingerprint_sensitive_admin_scope();
		if (empty($scope)) {
			return;
		}

		$scope['mode'] = 'scope_active';
		bvmgr_resource_fingerprint_flag('action_scheduler_async_blocked', $scope);
		bvmgr_resource_fingerprint_add_marker('action_scheduler_async_blocked', 0.0, $scope);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_disable_action_scheduler_async_runner')) {
	function bvmgr_resource_fingerprint_disable_action_scheduler_async_runner(bool $allow): bool
	{
		if (!$allow) {
			return false;
		}

		$scope = bvmgr_resource_fingerprint_sensitive_admin_scope();
		if (empty($scope)) {
			return true;
		}

		$scope['mode'] = 'filter_blocked';
		$scope['reason'] = 'sensitive_admin_request';
		bvmgr_resource_fingerprint_flag('action_scheduler_async_blocked', $scope);
		bvmgr_resource_fingerprint_add_marker('action_scheduler_async_blocked', 0.0, $scope);
		return false;
	}
}

if (!function_exists('bvmgr_resource_fingerprint_track_plugin_lifecycle')) {
	function bvmgr_resource_fingerprint_track_plugin_lifecycle(string $flag, string $plugin_file): void
	{
		$plugin_file = trim($plugin_file);
		$recognized_plugin_files = function_exists('bvmgr_recognized_plugin_lifecycle_basenames')
			? bvmgr_recognized_plugin_lifecycle_basenames()
			: array(
				'backstage-venue-manager/backstage-venue-manager.php',
				'backstage-venue-manager/vendor-management-system.php',
				'vms/backstage-venue-manager.php',
				'vms/vendor-management-system.php',
			);
		$recognized_plugin_files[] = 'vms-data-tools/vms-data-tools.php';
		if (!in_array($plugin_file, array_values(array_unique($recognized_plugin_files)), true)) {
			return;
		}

		bvmgr_resource_fingerprint_flag($flag, $plugin_file);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_track_plugin_activation')) {
	function bvmgr_resource_fingerprint_track_plugin_activation(string $plugin, ?bool $network_wide = null): void
	{
		bvmgr_resource_fingerprint_track_plugin_lifecycle('plugin_activation', $plugin);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_track_plugin_deactivation')) {
	function bvmgr_resource_fingerprint_track_plugin_deactivation(string $plugin, ?bool $network_wide = null): void
	{
		bvmgr_resource_fingerprint_track_plugin_lifecycle('plugin_deactivation', $plugin);
	}
}

if (!function_exists('bvmgr_resource_fingerprint_track_plugin_update')) {
	function bvmgr_resource_fingerprint_track_plugin_update($upgrader, array $hook_extra): void
	{
		if (($hook_extra['type'] ?? '') !== 'plugin') {
			return;
		}

		foreach ((array) ($hook_extra['plugins'] ?? array()) as $plugin_file) {
			bvmgr_resource_fingerprint_track_plugin_lifecycle('plugin_update', (string) $plugin_file);
		}
	}
}

if (!function_exists('bvmgr_resource_fingerprint_track_action_scheduler_context')) {
	function bvmgr_resource_fingerprint_track_action_scheduler_context(string $context = 'WP Cron'): void
	{
		bvmgr_resource_fingerprint_flag('action_scheduler_run', array('context' => $context));
	}
}

if (!function_exists('bvmgr_resource_fingerprint_before_action_scheduler_queue')) {
	function bvmgr_resource_fingerprint_before_action_scheduler_queue(): void
	{
		bvmgr_resource_fingerprint_span_start('action_scheduler.queue', array('page' => bvmgr_resource_fingerprint_current_admin_page()));
	}
}

if (!function_exists('bvmgr_resource_fingerprint_after_action_scheduler_queue')) {
	function bvmgr_resource_fingerprint_after_action_scheduler_queue(): void
	{
		bvmgr_resource_fingerprint_span_finish('action_scheduler.queue');
	}
}

bvmgr_resource_fingerprint_bootstrap();
bvmgr_admin_guard_hook_probe_bootstrap();
add_action('admin_init', 'bvmgr_resource_fingerprint_track_sensitive_admin_scope', 1);
add_filter('action_scheduler_allow_async_request_runner', 'bvmgr_resource_fingerprint_disable_action_scheduler_async_runner', 999);
add_action('activated_plugin', 'bvmgr_resource_fingerprint_track_plugin_activation', 10, 2);
add_action('deactivated_plugin', 'bvmgr_resource_fingerprint_track_plugin_deactivation', 10, 2);
add_action('upgrader_process_complete', 'bvmgr_resource_fingerprint_track_plugin_update', 10, 2);
add_action('action_scheduler_run_queue', 'bvmgr_resource_fingerprint_track_action_scheduler_context', 1, 1);
add_action('action_scheduler_before_process_queue', 'bvmgr_resource_fingerprint_before_action_scheduler_queue', 1);
add_action('action_scheduler_after_process_queue', 'bvmgr_resource_fingerprint_after_action_scheduler_queue', 999);
