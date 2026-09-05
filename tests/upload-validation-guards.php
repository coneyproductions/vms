<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!class_exists('WP_Error')) {
	class WP_Error
	{
		private string $code;
		private string $message;

		public function __construct(string $code = '', string $message = '')
		{
			$this->code = $code;
			$this->message = $message;
		}

		public function get_error_code(): string
		{
			return $this->code;
		}

		public function get_error_message(): string
		{
			return $this->message;
		}
	}
}

if (!function_exists('is_wp_error')) {
	function is_wp_error($thing): bool
	{
		return $thing instanceof WP_Error;
	}
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		return $text;
	}
}

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		return true;
	}
}

if (!function_exists('apply_filters')) {
	function apply_filters(string $hook, $value)
	{
		return $value;
	}
}

if (!function_exists('is_admin')) {
	function is_admin(): bool
	{
		return false;
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field(string $value): string
	{
		return trim($value);
	}
}

if (!function_exists('sanitize_textarea_field')) {
	function sanitize_textarea_field(string $value): string
	{
		return trim($value);
	}
}

if (!function_exists('sanitize_email')) {
	function sanitize_email(string $value): string
	{
		return trim($value);
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key(string $value): string
	{
		return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
	}
}

if (!function_exists('sanitize_file_name')) {
	function sanitize_file_name(string $value): string
	{
		$value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
		return trim((string) $value, '-');
	}
}

if (!function_exists('wp_unslash')) {
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
}

require dirname(__DIR__) . '/includes/runtime-guards.php';

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$single = bvmgr_upload_read_file(array(
	'proof' => array(
		'name' => 'notes.csv',
		'type' => 'text/plain',
		'tmp_name' => '/tmp/test.csv',
		'error' => UPLOAD_ERR_OK,
		'size' => 128,
	),
), 'proof');
$assert(!is_wp_error($single), 'Single upload payload should normalize cleanly.');
$assert(is_array($single) && $single['name'] === 'notes.csv', 'Single upload normalization should preserve the original filename.');

$malformed = bvmgr_upload_read_file(array(
	'proof' => array(
		'name' => array('nested'),
		'type' => 'text/plain',
		'tmp_name' => '/tmp/test.csv',
		'error' => UPLOAD_ERR_OK,
		'size' => 128,
	),
), 'proof');
$assert(is_wp_error($malformed) && $malformed->get_error_code() === 'upload_invalid_shape', 'Malformed single upload payloads should be rejected.');

$multi = bvmgr_upload_normalize_multi_file_array(array(
	'docs' => array(
		'name' => array('one.csv', 'two.csv'),
		'type' => array('text/plain', 'text/csv'),
		'tmp_name' => array('/tmp/one.csv', '/tmp/two.csv'),
		'error' => array(UPLOAD_ERR_OK, UPLOAD_ERR_OK),
		'size' => array(100, 200),
	),
), 'docs');
$assert(!is_wp_error($multi), 'Multi-file uploads should normalize cleanly.');
$assert(is_array($multi) && count($multi) === 2, 'Multi-file normalization should preserve every row.');

$validated = bvmgr_validate_uploaded_file(
	array(
		'name' => 'Quarterly Notes.csv',
		'type' => 'text/plain',
		'tmp_name' => '/tmp/test.csv',
		'error' => UPLOAD_ERR_OK,
		'size' => 64,
	),
	array(
		'allowed_mimes' => array(
			'csv' => array('text/csv', 'text/plain'),
		),
		'max_bytes' => 4096,
		'is_uploaded_file_callback' => static function (string $path): bool {
			return $path === '/tmp/test.csv';
		},
		'file_exists_callback' => static function (string $path): bool {
			return $path === '/tmp/test.csv';
		},
		'filesize_callback' => static function (string $path): int {
			return $path === '/tmp/test.csv' ? 512 : 0;
		},
		'type_check_callback' => static function (string $tmp_name, string $name, array $allowed): array {
			return array(
				'ext' => 'csv',
				'type' => 'text/plain',
			);
		},
	)
);
$assert(!is_wp_error($validated), 'Validation should accept allowed MIME variants for the same extension.');
$assert(is_array($validated) && ($validated['mime'] ?? '') === 'text/plain', 'Validation should return the detected MIME type.');
$assert(is_array($validated) && ($validated['size'] ?? 0) === 512, 'Validation should prefer the measured file size.');
$assert(is_array($validated) && ($validated['sanitized_name'] ?? '') === 'Quarterly-Notes.csv', 'Validation should sanitize the display filename.');

$rejected = bvmgr_validate_uploaded_file(
	array(
		'name' => 'bad.csv',
		'type' => 'text/plain',
		'tmp_name' => '/tmp/bad.csv',
		'error' => UPLOAD_ERR_OK,
		'size' => 64,
	),
	array(
		'allowed_mimes' => array(
			'csv' => array('text/csv', 'text/plain'),
		),
		'is_uploaded_file_callback' => static function (): bool {
			return true;
		},
		'file_exists_callback' => static function (): bool {
			return true;
		},
		'filesize_callback' => static function (): int {
			return 64;
		},
		'type_check_callback' => static function (): array {
			return array(
				'ext' => 'csv',
				'type' => 'application/pdf',
			);
		},
	)
);
$assert(is_wp_error($rejected) && $rejected->get_error_code() === 'upload_type_not_allowed', 'Validation should still reject MIME values outside the route allowlist.');

fwrite(STDOUT, "upload-validation-guards: OK\n");
