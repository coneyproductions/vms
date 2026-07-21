<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_slow_request_logger_option_key')) {
	function vms_slow_request_logger_option_key(): string
	{
		return 'vms_slow_request_logger_enabled';
	}
}

if (!function_exists('vms_slow_request_logger_enabled')) {
	function vms_slow_request_logger_enabled(): bool
	{
		if (defined('VMS_SLOW_REQUEST_LOGGER_ENABLED')) {
			return (bool) VMS_SLOW_REQUEST_LOGGER_ENABLED;
		}

		return !empty(get_option(vms_slow_request_logger_option_key(), 0));
	}
}

if (!function_exists('vms_slow_request_logger_time_threshold_seconds')) {
	function vms_slow_request_logger_time_threshold_seconds(): float
	{
		if (defined('VMS_SLOW_REQUEST_LOGGER_TIME_THRESHOLD')) {
			return max(0.1, (float) VMS_SLOW_REQUEST_LOGGER_TIME_THRESHOLD);
		}

		return 2.0;
	}
}

if (!function_exists('vms_slow_request_logger_memory_threshold_bytes')) {
	function vms_slow_request_logger_memory_threshold_bytes(): int
	{
		if (defined('VMS_SLOW_REQUEST_LOGGER_MEMORY_THRESHOLD')) {
			return max(1, (int) VMS_SLOW_REQUEST_LOGGER_MEMORY_THRESHOLD);
		}

		return 192 * 1024 * 1024;
	}
}

if (!function_exists('vms_slow_request_logger_max_bytes')) {
	function vms_slow_request_logger_max_bytes(): int
	{
		if (defined('VMS_SLOW_REQUEST_LOGGER_MAX_BYTES')) {
			return max(1024, (int) VMS_SLOW_REQUEST_LOGGER_MAX_BYTES);
		}

		return 5 * 1024 * 1024;
	}
}

if (!function_exists('vms_slow_request_logger_log_path')) {
	function vms_slow_request_logger_log_path(): string
	{
		if (defined('VMS_SLOW_REQUEST_LOGGER_PATH') && is_string(VMS_SLOW_REQUEST_LOGGER_PATH) && VMS_SLOW_REQUEST_LOGGER_PATH !== '') {
			return VMS_SLOW_REQUEST_LOGGER_PATH;
		}

		return defined('WP_CONTENT_DIR')
			? WP_CONTENT_DIR . '/vms-slow-request.log'
			: dirname(__DIR__, 3) . '/vms-slow-request.log';
	}
}

if (!function_exists('vms_slow_request_logger_parse_request_uri')) {
	function vms_slow_request_logger_parse_request_uri(): array
	{
		$request_uri = vms_request_server_value('REQUEST_URI');
		if ($request_uri === '') {
			$request_uri = '/';
		}
		$path = function_exists('wp_parse_url') ? (string) wp_parse_url($request_uri, PHP_URL_PATH) : (string) parse_url($request_uri, PHP_URL_PATH);
		$query_string = function_exists('wp_parse_url') ? (string) wp_parse_url($request_uri, PHP_URL_QUERY) : (string) parse_url($request_uri, PHP_URL_QUERY);
		$query = array();
		if ($query_string !== '') {
			parse_str($query_string, $query);
		}
		if (!isset($query['action']) && isset($_REQUEST['action'])) {
			$query['action'] = (string) wp_unslash($_REQUEST['action']);
		}

		return array(
			'path' => $path !== '' ? $path : '/',
			'query' => is_array($query) ? $query : array(),
		);
	}
}

if (!function_exists('vms_slow_request_logger_source_ip_hash')) {
	function vms_slow_request_logger_source_ip_hash(): string
	{
		$ip = '';
		foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $key) {
			$raw = vms_request_server_value($key);
			if ($raw === '') {
				continue;
			}
			$parts = explode(',', $raw);
			$ip = trim((string) ($parts[0] ?? ''));
			if ($ip !== '') {
				break;
			}
		}
		if ($ip === '') {
			return '';
		}

		$salt = function_exists('wp_salt') ? wp_salt('auth') : 'vms-slow-request';
		return substr(hash_hmac('sha256', strtolower($ip), $salt), 0, 12);
	}
}

if (!function_exists('vms_slow_request_logger_user_agent')) {
	function vms_slow_request_logger_user_agent(): string
	{
		return substr(vms_request_server_value('HTTP_USER_AGENT'), 0, 255);
	}
}

if (!function_exists('vms_slow_request_logger_user_agent_class')) {
	function vms_slow_request_logger_user_agent_class(): string
	{
		$user_agent = strtolower(vms_slow_request_logger_user_agent());
		if ($user_agent === '') {
			return 'browser';
		}
		if (strpos($user_agent, 'wordpress/') !== false) {
			return 'WordPress loopback';
		}
		if (strpos($user_agent, 'tcpdf') !== false) {
			return 'tcpdf';
		}
		if (strpos($user_agent, 'facebookexternalhit') !== false || strpos($user_agent, 'facebot') !== false || strpos($user_agent, 'meta-externalagent') !== false) {
			return 'Meta crawler';
		}
		if (strpos($user_agent, 'googlebot') !== false || strpos($user_agent, 'adsbot-google') !== false) {
			return 'Googlebot';
		}
		if (preg_match('/bot|crawler|spider|slurp|wget|curl/i', $user_agent)) {
			return 'other bot';
		}

		return 'browser';
	}
}

if (!function_exists('vms_slow_request_logger_normalize_order_received_uri')) {
	function vms_slow_request_logger_normalize_order_received_uri(string $path, array $query): string
	{
		$normalized = preg_replace('#^/checkout/order-received/[^/]+#', '/checkout/order-received/{order_id}', $path);
		if (!is_string($normalized) || $normalized === '') {
			$normalized = '/checkout/order-received/{order_id}/';
		}
		if (substr($normalized, -1) !== '/') {
			$normalized .= '/';
		}

		return $normalized . (!empty($query['key']) ? '?key=[redacted]' : '');
	}
}

if (!function_exists('vms_slow_request_logger_normalize_wallet_pdf_uri')) {
	function vms_slow_request_logger_normalize_wallet_pdf_uri(string $path, array $query): string
	{
		$parts = array('tec-tickets-wallet-plus-pdf=1');
		if (!empty($query['attendee_id'])) {
			$parts[] = 'attendee_id={id}';
		}
		if (array_key_exists('security_code', $query)) {
			$parts[] = 'security_code=[redacted]';
		}

		return ($path !== '' ? $path : '/') . '?' . implode('&', $parts);
	}
}

if (!function_exists('vms_slow_request_logger_normalize_admin_action_uri')) {
	function vms_slow_request_logger_normalize_admin_action_uri(string $path, string $action): string
	{
		$action = sanitize_key($action);
		return $path . ($action !== '' ? '?action=' . $action : '');
	}
}

if (!function_exists('vms_slow_request_logger_normalize_loopback_uri')) {
	function vms_slow_request_logger_normalize_loopback_uri(string $path, array $query): string
	{
		$parts = array();
		if (!empty($query['action'])) {
			$parts[] = 'action=' . sanitize_key((string) $query['action']);
		}
		if (array_key_exists('doing_wp_cron', $query)) {
			$parts[] = 'doing_wp_cron=[redacted]';
		}

		return $path . (!empty($parts) ? '?' . implode('&', $parts) : '');
	}
}

if (!function_exists('vms_slow_request_logger_normalize_tcpdf_asset_uri')) {
	function vms_slow_request_logger_normalize_tcpdf_asset_uri(string $path): string
	{
		$asset_type = 'asset';
		$lower = strtolower($path);
		if (strpos($lower, 'qr') !== false) {
			$asset_type = 'qr_asset';
		} elseif (strpos($lower, 'logo') !== false) {
			$asset_type = 'logo_asset';
		} elseif (strpos($lower, 'hero') !== false || strpos($lower, 'banner') !== false) {
			$asset_type = 'hero_asset';
		} elseif (strpos($lower, '/wp-content/uploads/') !== false) {
			$asset_type = 'uploads_asset';
		}

		return '/wp-content/uploads/{' . $asset_type . '}';
	}
}

if (!function_exists('vms_slow_request_logger_match_request')) {
	function vms_slow_request_logger_match_request(): array
	{
		$request = vms_slow_request_logger_parse_request_uri();
		$path = rtrim((string) ($request['path'] ?? '/'), '/');
		$path = $path === '' ? '/' : $path;
		$query = is_array($request['query'] ?? null) ? $request['query'] : array();
		$action = sanitize_key((string) ($query['action'] ?? ''));
		$ua_class = vms_slow_request_logger_user_agent_class();

		if (($query['tec-tickets-wallet-plus-pdf'] ?? '') === '1') {
			return array(
				'matched' => true,
				'scope' => 'tec_wallet_pdf',
				'reason' => 'wallet/pdf request',
				'normalized_uri' => vms_slow_request_logger_normalize_wallet_pdf_uri($path, $query),
			);
		}

		if (isset($query['wc-ajax'])) {
			return array(
				'matched' => true,
				'scope' => 'wc_ajax',
				'reason' => 'wc-ajax request',
				'normalized_uri' => '/?wc-ajax=' . sanitize_key((string) $query['wc-ajax']),
			);
		}

		if (preg_match('#^/checkout/order-received/[^/]+$#', $path)) {
			return array(
				'matched' => true,
				'scope' => 'checkout_order_received',
				'reason' => 'order received request',
				'normalized_uri' => vms_slow_request_logger_normalize_order_received_uri($path, $query),
			);
		}

		if ($path === '/checkout') {
			return array(
				'matched' => true,
				'scope' => 'checkout',
				'reason' => 'checkout request',
				'normalized_uri' => '/checkout/',
			);
		}

		if ($path === '/wp-json/wc/store/v1/cart') {
			return array(
				'matched' => true,
				'scope' => 'wc_store_cart',
				'reason' => 'Woo Store API cart request',
				'normalized_uri' => '/wp-json/wc/store/v1/cart',
			);
		}

		if ($path === '/wp-json/wc/store/v1/batch') {
			return array(
				'matched' => true,
				'scope' => 'wc_store_batch',
				'reason' => 'Woo Store API batch request',
				'normalized_uri' => '/wp-json/wc/store/v1/batch',
			);
		}

		if ($path === '/wp-admin/admin-post.php') {
			return array(
				'matched' => true,
				'scope' => 'admin_post',
				'reason' => 'admin-post request',
				'normalized_uri' => vms_slow_request_logger_normalize_admin_action_uri('/wp-admin/admin-post.php', $action),
			);
		}

		if ($path === '/wp-admin/admin-ajax.php' && $action === 'as_async_request_queue_runner') {
			return array(
				'matched' => true,
				'scope' => 'action_scheduler_async',
				'reason' => 'Action Scheduler async loopback',
				'normalized_uri' => '/wp-admin/admin-ajax.php?action=as_async_request_queue_runner',
			);
		}

		if ($path === '/wp-admin/admin-ajax.php' && $action === 'vms_ticketing_v2_cart_context') {
			return array(
				'matched' => true,
				'scope' => 'cart_context_ajax',
				'reason' => 'VMS cart context AJAX request',
				'normalized_uri' => '/wp-admin/admin-ajax.php?action=vms_ticketing_v2_cart_context',
			);
		}

		if ($ua_class === 'WordPress loopback') {
			return array(
				'matched' => true,
				'scope' => 'wp_loopback',
				'reason' => 'WordPress/7.0 loopback',
				'normalized_uri' => vms_slow_request_logger_normalize_loopback_uri($path, $query),
			);
		}

		if ($ua_class === 'tcpdf' && preg_match('#\.(png|jpe?g|gif|webp|svg)$#i', $path)) {
			return array(
				'matched' => true,
				'scope' => 'tcpdf_asset',
				'reason' => 'TCPDF asset fetch',
				'normalized_uri' => vms_slow_request_logger_normalize_tcpdf_asset_uri($path),
			);
		}

		return array('matched' => false);
	}
}

if (!function_exists('vms_slow_request_logger_capture_status_header')) {
	function vms_slow_request_logger_capture_status_header(string $status_header, int $code, string $description = '', string $protocol = ''): string
	{
		unset($description, $protocol);
		if (!isset($GLOBALS['vms_slow_request_logger']) || !is_array($GLOBALS['vms_slow_request_logger'])) {
			return $status_header;
		}

		$GLOBALS['vms_slow_request_logger']['response_status'] = $code;
		return $status_header;
	}
}

if (!function_exists('vms_slow_request_logger_trigger_list')) {
	function vms_slow_request_logger_trigger_list(float $elapsed_seconds, int $peak_memory_bytes, string $fatal_summary): array
	{
		$triggers = array();
		if ($elapsed_seconds >= vms_slow_request_logger_time_threshold_seconds()) {
			$triggers[] = 'slow';
		}
		if ($peak_memory_bytes >= vms_slow_request_logger_memory_threshold_bytes()) {
			$triggers[] = 'high_memory';
		}
		if ($fatal_summary !== '') {
			$triggers[] = 'fatal';
		}

		return $triggers;
	}
}

if (!function_exists('vms_slow_request_logger_fatal_summary')) {
	function vms_slow_request_logger_fatal_summary(): string
	{
		$error = error_get_last();
		if (!is_array($error) || empty($error['type']) || empty($error['message'])) {
			return '';
		}

		$fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR);
		if (!in_array((int) $error['type'], $fatal_types, true)) {
			return '';
		}

		$message = preg_replace('/\s+/', ' ', trim((string) $error['message']));
		if (!is_string($message)) {
			$message = 'fatal_error';
		}

		return substr($message, 0, 240);
	}
}

if (!function_exists('vms_slow_request_logger_rotate_file')) {
	function vms_slow_request_logger_rotate_file(string $path): void
	{
		$max_bytes = vms_slow_request_logger_max_bytes();
		$current_size = file_exists($path) ? (int) @filesize($path) : 0;
		if ($current_size < $max_bytes) {
			return;
		}

		$rotated = $path . '.1';
		if (file_exists($rotated)) {
			@unlink($rotated);
		}
		@rename($path, $rotated);
	}
}

if (!function_exists('vms_slow_request_logger_write_entry')) {
	function vms_slow_request_logger_write_entry(array $entry): void
	{
		$path = vms_slow_request_logger_log_path();
		$directory = dirname($path);
		if (!is_dir($directory) && function_exists('wp_mkdir_p')) {
			wp_mkdir_p($directory);
		}
		if (!is_dir($directory) || !is_writable($directory)) {
			return;
		}

		vms_slow_request_logger_rotate_file($path);
		$line = function_exists('wp_json_encode') ? wp_json_encode($entry) : json_encode($entry);
		if (!is_string($line) || $line === '') {
			return;
		}

		file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
	}
}

if (!function_exists('vms_slow_request_logger_shutdown')) {
	function vms_slow_request_logger_shutdown(): void
	{
		$state = $GLOBALS['vms_slow_request_logger'] ?? null;
		if (!is_array($state) || empty($state['matched'])) {
			return;
		}

		$elapsed_seconds = microtime(true) - (float) ($state['started_at'] ?? microtime(true));
		$peak_memory_bytes = memory_get_peak_usage(true);
		$fatal_summary = vms_slow_request_logger_fatal_summary();
		$triggers = vms_slow_request_logger_trigger_list($elapsed_seconds, $peak_memory_bytes, $fatal_summary);
		if (empty($triggers)) {
			return;
		}

		$response_status = (int) ($state['response_status'] ?? 0);
		if ($response_status <= 0 && function_exists('http_response_code')) {
			$response_status = (int) http_response_code();
		}

		$entry = array(
			'timestamp' => gmdate('c'),
			'method' => (string) ($state['method'] ?? 'GET'),
			'normalized_uri' => (string) ($state['normalized_uri'] ?? '/'),
			'scope' => (string) ($state['scope'] ?? ''),
			'reason' => (string) ($state['reason'] ?? ''),
			'trigger' => implode(',', $triggers),
			'elapsed_seconds' => round($elapsed_seconds, 3),
			'peak_memory_bytes' => $peak_memory_bytes,
			'peak_memory_mb' => round($peak_memory_bytes / 1048576, 1),
			'memory_limit' => (string) ini_get('memory_limit'),
			'response_status' => $response_status > 0 ? $response_status : '',
			'fatal_error' => $fatal_summary,
			'user_agent_class' => (string) ($state['user_agent_class'] ?? 'browser'),
			'ip_hash' => (string) ($state['ip_hash'] ?? ''),
		);

		vms_slow_request_logger_write_entry($entry);
	}
}

if (!function_exists('vms_slow_request_logger_bootstrap')) {
	function vms_slow_request_logger_bootstrap(): void
	{
		static $booted = false;
		if ($booted || !vms_slow_request_logger_enabled()) {
			return;
		}
		if (defined('WP_CLI') && WP_CLI) {
			return;
		}

		$match = vms_slow_request_logger_match_request();
		if (empty($match['matched'])) {
			return;
		}

		$booted = true;
		$GLOBALS['vms_slow_request_logger'] = array(
			'matched' => true,
			'started_at' => isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true),
			'method' => strtoupper(vms_request_method()),
			'normalized_uri' => (string) ($match['normalized_uri'] ?? '/'),
			'scope' => (string) ($match['scope'] ?? ''),
			'reason' => (string) ($match['reason'] ?? ''),
			'user_agent_class' => vms_slow_request_logger_user_agent_class(),
			'ip_hash' => vms_slow_request_logger_source_ip_hash(),
			'response_status' => 0,
		);

		add_filter('status_header', 'vms_slow_request_logger_capture_status_header', 10, 4);
		register_shutdown_function('vms_slow_request_logger_shutdown');
	}
}

vms_slow_request_logger_bootstrap();
