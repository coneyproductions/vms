<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class WP_Error
{
	private string $code;
	private string $message;
	private array $data;

	public function __construct(string $code = '', string $message = '', array $data = array())
	{
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}

	public function get_error_code(): string
	{
		return $this->code;
	}

	public function get_error_message(): string
	{
		return $this->message;
	}

	public function get_error_data(): array
	{
		return $this->data;
	}
}

final class WP_REST_Request
{
	private array $headers = array();
	private array $params = array();

	public function __construct(array $headers = array(), array $params = array())
	{
		$this->headers = $headers;
		$this->params = $params;
	}

	public function get_header(string $name)
	{
		return $this->headers[$name] ?? '';
	}

	public function get_param(string $key)
	{
		return $this->params[$key] ?? null;
	}
}

function __(string $text, string $domain = ''): string
{
	return $text;
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	return trim(stripslashes((string) $value));
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

function wp_verify_nonce(string $nonce, string $action): bool
{
	$valid = array(
		'wp_rest' => 'good nonce',
		'test_action' => 'good nonce',
		'test_unslashed_action' => 'good nonce',
	);

	return isset($valid[$action]) && $nonce === $valid[$action];
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	return true;
}

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['vms_test_caps'][$capability]);
}

function vms_admission_manage_capability(): string
{
	return 'vms_admission_manage';
}

function vms_admission_door_capability(): string
{
	return 'vms_door_checkin';
}

function vms_admission_current_user_can_manage(): bool
{
	return current_user_can(vms_admission_manage_capability());
}

function vms_admission_current_user_can_checkin(): bool
{
	return current_user_can(vms_admission_door_capability()) || vms_admission_current_user_can_manage();
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

require dirname(__DIR__) . '/includes/modules/admissions/rest.php';
require dirname(__DIR__) . '/includes/core/tours/class-vms-tours.php';

$GLOBALS['vms_test_caps'] = array(vms_admission_door_capability() => true);

$goodHeaderRequest = new WP_REST_Request(array('X-WP-Nonce' => ' good nonce '));
$assert(vms_admission_rest_can_checkin_request($goodHeaderRequest) === true, 'Admissions REST should accept sanitized valid header nonces.');

$missingNonceRequest = new WP_REST_Request();
$assert(vms_admission_rest_can_checkin_request($missingNonceRequest) === true, 'Admissions REST should preserve missing-nonce behavior when the caller has capability.');

$invalidHeaderRequest = new WP_REST_Request(array('X-WP-Nonce' => 'expired'));
$invalidHeaderResult = vms_admission_rest_can_checkin_request($invalidHeaderRequest);
$assert($invalidHeaderResult instanceof WP_Error, 'Admissions REST should reject invalid header nonces.');
$assert($invalidHeaderResult->get_error_code() === 'vms_admission_bad_nonce', 'Admissions REST invalid nonce code should remain stable.');
$assert($invalidHeaderResult->get_error_message() === 'Your Admissions session expired. Refresh the page and try again.', 'Admissions REST invalid nonce message should remain stable.');

$arrayParamRequest = new WP_REST_Request(array(), array('_wpnonce' => array('expired')));
$assert(vms_admission_rest_request_nonce($arrayParamRequest) === '', 'Admissions REST should drop array-shaped _wpnonce params.');
$assert(vms_admission_rest_can_checkin_request($arrayParamRequest) === true, 'Admissions REST should treat array-shaped _wpnonce params like missing nonces, preserving existing flow.');

$objectParamRequest = new WP_REST_Request(array(), array('_wpnonce' => (object) array('bad' => 'nonce')));
$assert(vms_admission_rest_request_nonce($objectParamRequest) === '', 'Admissions REST should drop non-scalar _wpnonce params.');

$GLOBALS['vms_test_caps'] = array();
$forbiddenResult = vms_admission_rest_can_checkin_request($goodHeaderRequest);
$assert($forbiddenResult instanceof WP_Error, 'Admissions REST should preserve capability gating.');
$assert($forbiddenResult->get_error_code() === 'vms_admission_forbidden', 'Admissions REST forbidden code should remain stable.');

$tourHeaderRequest = new WP_REST_Request(array('X-WP-Nonce' => ' good nonce '));
$assert(BVMGR_Tours::verify_rest_nonce($tourHeaderRequest) === true, 'Tours REST verification should accept sanitized header nonces.');

$tourParamRequest = new WP_REST_Request(array(), array('_wpnonce' => 'good nonce'));
$assert(BVMGR_Tours::verify_rest_nonce($tourParamRequest) === true, 'Tours REST verification should accept valid _wpnonce params.');

$tourMissingRequest = new WP_REST_Request();
$assert(BVMGR_Tours::verify_rest_nonce($tourMissingRequest) === false, 'Tours REST verification should continue failing closed for missing nonces.');

$tourArrayRequest = new WP_REST_Request(array(), array('_wpnonce' => array('bad')));
$assert(BVMGR_Tours::verify_rest_nonce($tourArrayRequest) === false, 'Tours REST verification should reject array-shaped _wpnonce params.');

$tourObjectRequest = new WP_REST_Request(array(), array('_wpnonce' => (object) array('bad' => 'nonce')));
$assert(BVMGR_Tours::verify_rest_nonce($tourObjectRequest) === false, 'Tours REST verification should reject non-scalar _wpnonce params.');

$extract_request_nonce = static function (array $request, string $key): string {
	return (isset($request[$key]) && !is_array($request[$key]))
		? sanitize_text_field(wp_unslash((string) $request[$key]))
		: '';
};

$extract_unslashed_nonce = static function (array $post, string $key): string {
	return (isset($post[$key]) && !is_array($post[$key]))
		? sanitize_text_field((string) $post[$key])
		: '';
};

$verify_inline_post_nonce = static function (array $post, string $key, string $action): bool {
	return isset($post[$key])
		&& !is_array($post[$key])
		&& wp_verify_nonce(sanitize_text_field(wp_unslash((string) $post[$key])), $action);
};

$assert($extract_request_nonce(array('nonce' => 'good\\ nonce'), 'nonce') === 'good nonce', 'Request-derived nonce extraction should unslash valid nonce strings.');
$assert($extract_request_nonce(array(), 'nonce') === '', 'Request-derived nonce extraction should return empty for missing nonces.');
$assert($extract_request_nonce(array('nonce' => array('good nonce')), 'nonce') === '', 'Request-derived nonce extraction should return empty for array-shaped input.');

$assert($extract_unslashed_nonce(array('nonce' => 'good nonce'), 'nonce') === 'good nonce', 'Unslashed nonce extraction should preserve valid nonce strings.');
$assert($extract_unslashed_nonce(array('nonce' => array('good nonce')), 'nonce') === '', 'Unslashed nonce extraction should return empty for array-shaped input.');

$assert($verify_inline_post_nonce(array('nonce' => 'good\\ nonce'), 'nonce', 'test_action') === true, 'Inline nonce verification should accept valid slashed strings.');
$assert($verify_inline_post_nonce(array(), 'nonce', 'test_action') === false, 'Inline nonce verification should preserve missing-nonce failure flow.');
$assert($verify_inline_post_nonce(array('nonce' => 'expired'), 'nonce', 'test_action') === false, 'Inline nonce verification should preserve invalid-nonce failure flow.');
$assert($verify_inline_post_nonce(array('nonce' => array('good nonce')), 'nonce', 'test_action') === false, 'Inline nonce verification should reject array-shaped input without changing the failure branch.');

fwrite(STDOUT, "Nonce input normalization OK.\n");
