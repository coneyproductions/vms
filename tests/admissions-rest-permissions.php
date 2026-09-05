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

	public function get_header(string $name): string
	{
		return (string) ($this->headers[$name] ?? '');
	}

	public function get_param(string $key)
	{
		return $this->params[$key] ?? null;
	}
}

final class WP_REST_Server
{
	public const READABLE = 'GET';
	public const CREATABLE = 'POST';
}

function __(string $text, string $domain = ''): string
{
	return $text;
}

function sanitize_text_field(string $value): string
{
	return trim($value);
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
}

function wp_unslash($value)
{
	return $value;
}

function wp_verify_nonce(string $nonce, string $action): bool
{
	return $nonce === 'good-rest-nonce' && $action === 'wp_rest';
}

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	$GLOBALS['vms_test_actions'][$hook][] = $callback;
	return true;
}

function register_rest_route(string $namespace, string $route, array $args): bool
{
	$GLOBALS['vms_test_routes'][] = array(
		'namespace' => $namespace,
		'route' => $route,
		'args' => $args,
	);
	return true;
}

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['vms_test_caps'][$capability]);
}

function bvmgr_admission_manage_capability(): string
{
	return 'vms_admission_manage';
}

function bvmgr_admission_door_capability(): string
{
	return 'vms_door_checkin';
}

function bvmgr_admission_current_user_can_manage(): bool
{
	return current_user_can(bvmgr_admission_manage_capability());
}

function bvmgr_admission_current_user_can_checkin(): bool
{
	return current_user_can(bvmgr_admission_door_capability()) || bvmgr_admission_current_user_can_manage();
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

require dirname(__DIR__) . '/includes/modules/admissions/rest.php';

foreach ((array) ($GLOBALS['vms_test_actions']['rest_api_init'] ?? array()) as $callback) {
	$callback();
}

$findRoute = static function (string $route, string $method) use ($assert): array {
	foreach ((array) ($GLOBALS['vms_test_routes'] ?? array()) as $entry) {
		if (($entry['route'] ?? '') !== $route) {
			continue;
		}
		if (($entry['args']['methods'] ?? '') !== $method) {
			continue;
		}
		return $entry;
	}

	throw new RuntimeException('Route not registered: ' . $route . ' [' . $method . ']');
};

$listRoute = $findRoute('/admissions', WP_REST_Server::READABLE);
$createRoute = $findRoute('/admissions', WP_REST_Server::CREATABLE);
$scanRoute = $findRoute('/admissions/scan', WP_REST_Server::CREATABLE);

$assert(($listRoute['args']['permission_callback'] ?? null) === 'bvmgr_admission_rest_can_checkin_request', 'Admissions list route should use explicit check-in permission callback.');
$assert(($createRoute['args']['permission_callback'] ?? null) === 'bvmgr_admission_rest_can_manage_request', 'Admissions create route should use explicit manage permission callback.');
$assert(($scanRoute['args']['permission_callback'] ?? null) === 'bvmgr_admission_rest_can_checkin_request', 'Admissions scan route should not remain publicly registered.');

$GLOBALS['vms_test_caps'] = array();
$forbidden = call_user_func($listRoute['args']['permission_callback'], new WP_REST_Request());
$assert($forbidden instanceof WP_Error, 'Admissions list should block unauthenticated/non-door access at the route boundary.');
$assert($forbidden->get_error_code() === 'vms_admission_forbidden', 'Admissions list should return the hardened forbidden error code.');

$GLOBALS['vms_test_caps'] = array(bvmgr_admission_door_capability() => true);
$badNonce = call_user_func($listRoute['args']['permission_callback'], new WP_REST_Request(array('X-WP-Nonce' => 'expired')));
$assert($badNonce instanceof WP_Error, 'Admissions list should reject invalid REST nonces.');
$assert($badNonce->get_error_code() === 'vms_admission_bad_nonce', 'Admissions list should surface the hardened bad-nonce code.');
$assert($badNonce->get_error_message() === 'Your Admissions session expired. Refresh the page and try again.', 'Admissions list should return the refreshed expired-session guidance.');

$goodCheckin = call_user_func($listRoute['args']['permission_callback'], new WP_REST_Request(array('X-WP-Nonce' => 'good-rest-nonce')));
$assert($goodCheckin === true, 'Admissions list should allow door users with a valid REST nonce.');

$GLOBALS['vms_test_caps'] = array(bvmgr_admission_manage_capability() => true);
$goodManage = call_user_func($createRoute['args']['permission_callback'], new WP_REST_Request(array('X-WP-Nonce' => 'good-rest-nonce')));
$assert($goodManage === true, 'Admissions create should allow managers with a valid REST nonce.');

fwrite(STDOUT, "Admissions REST permissions OK.\n");
