<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 4) . '/');
}

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}

$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_transients'] = array();

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		return $text;
	}
}

if (!function_exists('absint')) {
	function absint($maybeint): int
	{
		return abs((int) $maybeint);
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($key): string
	{
		$key = strtolower((string) $key);
		$key = preg_replace('/[^a-z0-9_\-]/', '', $key);
		return is_string($key) ? $key : '';
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		return trim((string) $value);
	}
}

if (!function_exists('sanitize_email')) {
	function sanitize_email($email): string
	{
		$email = trim((string) $email);
		return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
	}
}

if (!function_exists('sanitize_html_class')) {
	function sanitize_html_class($class): string
	{
		return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $class) ?: '';
	}
}

if (!function_exists('add_query_arg')) {
	function add_query_arg(array $args, string $url): string
	{
		return $url . '?' . http_build_query($args);
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('add_action')) {
	function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
	{
		unset($hook_name, $callback, $priority, $accepted_args);
	}
}

if (!function_exists('add_filter')) {
	function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
	{
		unset($hook_name, $callback, $priority, $accepted_args);
	}
}

if (!function_exists('get_current_user_id')) {
	function get_current_user_id(): int
	{
		return 1;
	}
}

if (!function_exists('wp_json_encode')) {
	function wp_json_encode($value): string
	{
		return json_encode($value) ?: '[]';
	}
}

if (!function_exists('get_option')) {
	function get_option(string $option, $default = false)
	{
		return array_key_exists($option, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$option] : $default;
	}
}

if (!function_exists('update_option')) {
	function update_option(string $option, $value, bool $autoload = false): bool
	{
		unset($autoload);
		$GLOBALS['vms_test_options'][$option] = $value;
		return true;
	}
}

if (!function_exists('get_transient')) {
	function get_transient(string $key)
	{
		return $GLOBALS['vms_test_transients'][$key] ?? false;
	}
}

if (!function_exists('set_transient')) {
	function set_transient(string $key, $value, int $expiration): bool
	{
		unset($expiration);
		$GLOBALS['vms_test_transients'][$key] = $value;
		return true;
	}
}

if (!function_exists('delete_transient')) {
	function delete_transient(string $key): bool
	{
		unset($GLOBALS['vms_test_transients'][$key]);
		return true;
	}
}

require_once dirname(__DIR__) . '/includes/ticketing/ticket-integrity-monitor.php';

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

try {
	$staleStartedAt = time() - ((15 * MINUTE_IN_SECONDS) + MINUTE_IN_SECONDS + 30);
	set_transient(
		vms_ticket_integrity_scan_lock_key(),
		array(
			'owner' => 'stale_job',
			'started_at_gmt' => $staleStartedAt,
		),
		15 * MINUTE_IN_SECONDS
	);

	$acquired = vms_ticket_integrity_acquire_scan_lock('fresh_job');
	$current = get_transient(vms_ticket_integrity_scan_lock_key());
	$logs = vms_ticket_integrity_get_logs();

	$assert($acquired === true, 'Expected a stale scan lock to be cleared and reacquired.');
	$assert(is_array($current), 'Expected a fresh scan lock array after reacquiring.');
	$assert(($current['owner'] ?? '') === 'fresh_job', 'Expected the new scan lock owner to replace the stale lock.');
	$assert(absint($current['started_at_gmt'] ?? 0) > $staleStartedAt, 'Expected the reacquired scan lock to refresh started_at_gmt.');
	$assert(!empty($logs), 'Expected stale-lock recovery to be logged.');
	$assert((string) ($logs[0]['type'] ?? '') === 'scan_lock_cleared', 'Expected the newest log entry to record scan_lock_cleared.');

	fwrite(STDOUT, "Ticket integrity scan-lock recovery test passed.\n");
	exit(0);
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . "\n");
	exit(1);
}
