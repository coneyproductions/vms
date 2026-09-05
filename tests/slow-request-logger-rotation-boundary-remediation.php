<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('VMS_SLOW_REQUEST_LOGGER_ENABLED', false);
define('VMS_SLOW_REQUEST_LOGGER_TIME_THRESHOLD', 999999.0);
define('VMS_SLOW_REQUEST_LOGGER_MEMORY_THRESHOLD', 1073741824);
define('VMS_SLOW_REQUEST_LOGGER_MAX_BYTES', 1024);

function vms_test_rotation_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_rotation_assert_same($expected, $actual, string $message): void
{
	vms_test_rotation_assert(
		$expected === $actual,
		$message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
	);
}

function vms_test_rotation_capture(callable $callback): array
{
	$warnings = array();
	set_error_handler(
		static function (int $severity, string $message, string $file = '', int $line = 0) use (&$warnings): bool {
			$warnings[] = array(
				'severity' => $severity,
				'message' => $message,
				'file' => $file,
				'line' => $line,
			);
			return true;
		}
	);

	try {
		$value = $callback();
	} finally {
		restore_error_handler();
	}

	return array(
		'value' => $value,
		'warnings' => $warnings,
	);
}

function vms_test_rotation_assert_no_warnings(array $warnings, string $message): void
{
	vms_test_rotation_assert_same(0, count($warnings), $message . ' should not emit warnings.');
}

function get_option(string $key, $default = false)
{
	unset($key);
	return $default;
}

function wp_mkdir_p(string $target): bool
{
	return is_dir($target) || mkdir($target, 0777, true);
}

function wp_is_writable(string $path): bool
{
	return is_writable($path);
}

function wp_delete_file(string $path): bool
{
	$GLOBALS['vms_test_rotation_calls']['wp_delete_file'][] = $path;
	return @unlink($path);
}

function wp_delete_file_from_directory(string $path, string $directory): bool
{
	$GLOBALS['vms_test_rotation_calls']['wp_delete_file_from_directory'][] = array($path, $directory);

	if (!empty($GLOBALS['vms_test_rotation_force_delete_failure'])) {
		return false;
	}

	$realFile = realpath($path);
	$realDirectory = realpath($directory);
	if ($realFile === false || $realDirectory === false) {
		return false;
	}

	$normalizedFile = str_replace('\\', '/', $realFile);
	$normalizedDirectory = rtrim(str_replace('\\', '/', $realDirectory), '/') . '/';
	if (!str_starts_with($normalizedFile, $normalizedDirectory)) {
		return false;
	}

	return wp_delete_file($path);
}

function wp_json_encode($value)
{
	return json_encode($value);
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function bvmgr_request_server_value(string $key): string
{
	unset($key);
	return '';
}

function bvmgr_request_method(): string
{
	return 'GET';
}

function wp_parse_url(string $url, int $component = -1)
{
	if ($component === -1) {
		return parse_url($url);
	}

	return parse_url($url, $component);
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = strtolower((string) $value);
	return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	if (is_string($value)) {
		return stripslashes($value);
	}

	return $value;
}

function wp_salt(string $scheme = 'auth'): string
{
	return 'slow-request-rotation-test-' . $scheme;
}

try {
	$pluginRoot = dirname(__DIR__);
	$loggerPath = $pluginRoot . '/includes/core/slow-request-logger.php';
	$source = (string) file_get_contents($loggerPath);
	vms_test_rotation_assert($source !== '', 'Logger source should be readable.');

	vms_test_rotation_assert(
		substr_count($source, 'phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename') === 1,
		'Rotation boundary should contain exactly one line-specific rename suppression.'
	);
	vms_test_rotation_assert(
		strpos($source, 'wp_delete_file_from_directory($rotated, dirname($path));') !== false,
		'Rotation boundary should use wp_delete_file_from_directory() for the retained log generation.'
	);
	vms_test_rotation_assert(
		strpos($source, '!is_dir($directory) || !wp_is_writable($directory)') !== false,
		'Write path should use wp_is_writable() for the logger directory check.'
	);
	vms_test_rotation_assert(
		preg_match('/(?<!wp_)is_writable\s*\(/', $source) !== 1,
		'Logger source should not retain bare is_writable() calls.'
	);
	vms_test_rotation_assert(
		strpos($source, '@unlink(') === false,
		'Logger source should not retain native @unlink() cleanup.'
	);
	vms_test_rotation_assert(
		strpos($source, 'WP_Filesystem(') === false && strpos($source, 'WP_Filesystem_Direct') === false && strpos($source, '->move(') === false,
		'Logger source should not initialize or invoke WP_Filesystem for rotation.'
	);

	$tempRoot = sys_get_temp_dir() . '/vms-slow-request-rotation-' . getmypid() . '-' . bin2hex(random_bytes(4));
	vms_test_rotation_assert(mkdir($tempRoot, 0777, true), 'Failed to create the temporary rotation test directory.');
	define('VMS_SLOW_REQUEST_LOGGER_PATH', $tempRoot . '/vms-slow-request.log');

	require $loggerPath;

	$belowThreshold = vms_test_rotation_capture(
		static function (): void {
			file_put_contents(VMS_SLOW_REQUEST_LOGGER_PATH, 'small log');
			bvmgr_slow_request_logger_rotate_file(VMS_SLOW_REQUEST_LOGGER_PATH);
		}
	);
	vms_test_rotation_assert_no_warnings($belowThreshold['warnings'], 'Below-threshold rotation');
	vms_test_rotation_assert_same(true, file_exists(VMS_SLOW_REQUEST_LOGGER_PATH), 'Below-threshold rotation should leave the active log in place.');
	vms_test_rotation_assert_same(false, file_exists(VMS_SLOW_REQUEST_LOGGER_PATH . '.1'), 'Below-threshold rotation should not create a retained generation.');

	$oversizedActive = str_repeat('A', 1200);
	file_put_contents(VMS_SLOW_REQUEST_LOGGER_PATH, $oversizedActive);
	file_put_contents(VMS_SLOW_REQUEST_LOGGER_PATH . '.1', 'old generation');
	$GLOBALS['vms_test_rotation_calls'] = array(
		'wp_delete_file' => array(),
		'wp_delete_file_from_directory' => array(),
	);
	$GLOBALS['vms_test_rotation_force_delete_failure'] = false;
	$rotation = vms_test_rotation_capture(
		static function (): void {
			bvmgr_slow_request_logger_write_entry(array('entry' => 'next'));
		}
	);
	vms_test_rotation_assert_no_warnings($rotation['warnings'], 'Successful rotation write');
	vms_test_rotation_assert_same(
		array(array(VMS_SLOW_REQUEST_LOGGER_PATH . '.1', dirname(VMS_SLOW_REQUEST_LOGGER_PATH))),
		$GLOBALS['vms_test_rotation_calls']['wp_delete_file_from_directory'],
		'Rotation cleanup should target only the retained same-directory generation.'
	);
	vms_test_rotation_assert_same(
		array(VMS_SLOW_REQUEST_LOGGER_PATH . '.1'),
		$GLOBALS['vms_test_rotation_calls']['wp_delete_file'],
		'Successful rotation cleanup should delete only the previous retained generation.'
	);
	$rotatedPayload = (string) file_get_contents(VMS_SLOW_REQUEST_LOGGER_PATH . '.1');
	$activePayload = (string) file_get_contents(VMS_SLOW_REQUEST_LOGGER_PATH);
	vms_test_rotation_assert_same($oversizedActive, $rotatedPayload, 'Rotation should promote the previous active log into the retained generation.');
	vms_test_rotation_assert(str_contains($activePayload, '"entry":"next"'), 'Rotation should append the new JSON line to the active log.');
	@unlink(VMS_SLOW_REQUEST_LOGGER_PATH . '.1');

	$oversizedRecoverable = str_repeat('B', 1200);
	file_put_contents(VMS_SLOW_REQUEST_LOGGER_PATH, $oversizedRecoverable);
	file_put_contents(VMS_SLOW_REQUEST_LOGGER_PATH . '.1', 'stale generation');
	$GLOBALS['vms_test_rotation_calls'] = array(
		'wp_delete_file' => array(),
		'wp_delete_file_from_directory' => array(),
	);
	$GLOBALS['vms_test_rotation_force_delete_failure'] = true;
	$failedDelete = vms_test_rotation_capture(
		static function (): void {
			bvmgr_slow_request_logger_write_entry(array('entry' => 'after-failed-delete'));
		}
	);
	vms_test_rotation_assert_no_warnings($failedDelete['warnings'], 'Failed retained-generation delete');
	$activeAfterFailure = (string) file_get_contents(VMS_SLOW_REQUEST_LOGGER_PATH);
	$retainedAfterFailure = (string) file_get_contents(VMS_SLOW_REQUEST_LOGGER_PATH . '.1');
	$activePreserved = str_contains($activeAfterFailure, $oversizedRecoverable) && str_contains($activeAfterFailure, '"entry":"after-failed-delete"');
	$rotatedReplaced = $retainedAfterFailure === $oversizedRecoverable && str_contains($activeAfterFailure, '"entry":"after-failed-delete"');
	vms_test_rotation_assert(
		$activePreserved || $rotatedReplaced,
		'Failed retained-generation cleanup should still leave logging functional, whether the active file remains in place or the same-directory rename replaces the retained generation.'
	);
	vms_test_rotation_assert_same(
		array(array(VMS_SLOW_REQUEST_LOGGER_PATH . '.1', dirname(VMS_SLOW_REQUEST_LOGGER_PATH))),
		$GLOBALS['vms_test_rotation_calls']['wp_delete_file_from_directory'],
		'Failed retained-generation cleanup should still target only the bounded retained generation.'
	);
	vms_test_rotation_assert_same(
		array(),
		$GLOBALS['vms_test_rotation_calls']['wp_delete_file'],
		'Forced retained-generation delete failure should not fall through to wp_delete_file().'
	);

	echo "slow-request logger rotation boundary remediation: OK\n";
} finally {
	if (defined('VMS_SLOW_REQUEST_LOGGER_PATH')) {
		@unlink(VMS_SLOW_REQUEST_LOGGER_PATH . '.1');
		@unlink(VMS_SLOW_REQUEST_LOGGER_PATH);
		@rmdir(dirname(VMS_SLOW_REQUEST_LOGGER_PATH));
	}
}
