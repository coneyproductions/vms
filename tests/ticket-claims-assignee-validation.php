<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class WP_User
{
	public int $ID;
	public string $user_email;

	public function __construct(int $id, string $email)
	{
		$this->ID = $id;
		$this->user_email = $email;
	}
}

final class Vms_Test_Product
{
	private string $name;

	public function __construct(string $name)
	{
		$this->name = $name;
	}

	public function get_name(): string
	{
		return $this->name;
	}
}

final class Vms_Test_Json_Response extends RuntimeException
{
	public bool $success;
	public array $data;
	public int $status;
	public int $flags;
	public int $numArgs;

	public function __construct(bool $success, array $data, int $status, int $flags = 0, int $numArgs = 2)
	{
		parent::__construct('JSON response captured.');
		$this->success = $success;
		$this->data = $data;
		$this->status = $status;
		$this->flags = $flags;
		$this->numArgs = $numArgs;
	}
}

function __(string $text, string $domain = ''): string
{
	return $text;
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	return true;
}

function check_ajax_referer(string $action, $queryArg = false, bool $stop = true): bool
{
	return true;
}

function bvmgr_check_ajax_referer_compat(string $action, $queryArg = false, bool $stop = true): bool
{
	return check_ajax_referer($action, $queryArg, $stop);
}

function is_user_logged_in(): bool
{
	return !empty($GLOBALS['vms_test_logged_in']);
}

function wp_unslash($value)
{
	return $value;
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
}

function sanitize_text_field(string $value): string
{
	return trim($value);
}

function sanitize_email(string $value): string
{
	return strtolower(trim($value));
}

function bvmgr_request_read_absint(array $source, string $key): int
{
	if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
		return 0;
	}

	$value = wp_unslash($source[$key]);
	if (!is_scalar($value)) {
		return 0;
	}

	return absint($value);
}

function get_user_by(string $field, string $value)
{
	if ($field !== 'email') {
		return false;
	}
	if ($value === 'approved@example.com') {
		return new WP_User(27, $value);
	}
	return false;
}

function wc_get_product(int $productId)
{
	return new Vms_Test_Product('Qualified Guest Ticket');
}

function wp_send_json_error(array $data, int $statusCode = 200, int $flags = 0): void
{
	throw new Vms_Test_Json_Response(false, $data, $statusCode, $flags, func_num_args());
}

function wp_send_json_success(array $data, int $statusCode = 200, int $flags = 0): void
{
	throw new Vms_Test_Json_Response(true, $data, $statusCode, $flags, func_num_args());
}

function bvmgr_ticketing_v2_ajax_send_error(array $data, ?int $httpStatus = null, int $flags = 0): void
{
	if (func_num_args() >= 3) {
		wp_send_json_error($data, $httpStatus ?? 200, $flags);
		return;
	}

	if (func_num_args() === 2) {
		wp_send_json_error($data, $httpStatus ?? 200);
		return;
	}

	wp_send_json_error($data);
}

function bvmgr_ticketing_v2_ajax_send_success(array $data, ?int $httpStatus = null, int $flags = 0): void
{
	if (func_num_args() >= 3) {
		wp_send_json_success($data, $httpStatus ?? 200, $flags);
		return;
	}

	if (func_num_args() === 2) {
		wp_send_json_success($data, $httpStatus ?? 200);
		return;
	}

	wp_send_json_success($data);
}

function bvmgr_ticketing_v2_resolve_verified_ticket_context(int $productId): array
{
	return array(
		'visibility_mode' => 'verified',
		'program' => 'veteran',
		'allowed_programs' => array('veteran'),
		'allow_direct_grants' => false,
		'claim_grant_type' => 'event_ticket_eligibility',
		'event_id' => 88,
		'ticket_key' => 'qualified_guest',
		'claims_per_assignee' => 2,
	);
}

function bvmgr_ticketing_claims_normalize_allowed_programs(array $allowedPrograms, string $legacyProgram): array
{
	return $allowedPrograms;
}

function bvmgr_ticketing_claims_truthy($value, bool $default = false): bool
{
	return (bool) $value;
}

function bvmgr_ticketing_v2_ticket_group_product_ids_from_context(array $context, int $productId): array
{
	return array($productId);
}

function bvmgr_ticketing_claims_resolve_eligibility(array $args): array
{
	return array(
		'eligible' => true,
		'reason_code' => 'ok',
		'message' => '',
		'matched_rule_path' => 'direct_grant',
		'matched_grant_id' => 123,
	);
}

function bvmgr_ticketing_v2_assignee_claims_per_event_limit(array $context, WP_User $user, array $resolved): int
{
	return 2;
}

function bvmgr_ticketing_v2_assignee_consumed_qty_for_event(int $eventId, string $assigneeEmail, array $groupProductIds): int
{
	return 0;
}

function bvmgr_ticketing_v2_cart_assignee_usage_for_event(int $eventId, string $ticketKey): array
{
	return array();
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

require dirname(__DIR__) . '/includes/integrations/ticketing-claims-customer.php';

$_GET = array();
$assert(bvmgr_ticketing_claims_account_should_expand() === false, 'Benefits panel should stay collapsed when the flag is missing.');

$_GET['vms_benefits'] = '1';
$assert(bvmgr_ticketing_claims_account_should_expand() === true, 'Benefits panel should expand when the scalar benefits flag is 1.');

$_GET['vms_benefits'] = array('1');
$assert(bvmgr_ticketing_claims_account_should_expand() === false, 'Benefits panel should reject array-shaped benefits flags.');

$runHandler = static function (array $post, bool $loggedIn): Vms_Test_Json_Response {
	$_POST = $post;
	$GLOBALS['vms_test_logged_in'] = $loggedIn;

	try {
		bvmgr_ticketing_claims_handle_validate_assignee();
	} catch (Vms_Test_Json_Response $response) {
		return $response;
	}

	throw new RuntimeException('Expected JSON response was not sent.');
};

$loggedOut = $runHandler(array(
	'product_id' => '41',
	'event_id' => '88',
	'ticket_key' => 'qualified_guest',
	'assignee_email' => 'approved@example.com',
), false);

$assert($loggedOut->success === false, 'Logged-out validation should fail.');
$assert($loggedOut->status === 401, 'Logged-out validation should return HTTP 401.');
$assert(($loggedOut->data['reason_code'] ?? '') === 'login_required', 'Logged-out validation should use the login_required reason code.');

$loggedIn = $runHandler(array(
	'product_id' => '41',
	'event_id' => '88',
	'ticket_key' => 'qualified_guest',
	'assignee_email' => 'approved@example.com',
), true);

$assert($loggedIn->success === true, 'Approved assignee validation should still succeed for logged-in buyers.');
$assert(($loggedIn->data['assignee_email'] ?? '') === 'approved@example.com', 'Approved assignee email should still be returned to the buyer.');
$assert(($loggedIn->data['ticket_label'] ?? '') === 'Qualified Guest Ticket', 'Ticket label should still be returned for buyer-side confirmation copy.');
$assert(!array_key_exists('assignee_user_id', $loggedIn->data), 'Buyer-facing assignee validation should not leak internal user IDs.');
$assert(!array_key_exists('rule_path', $loggedIn->data), 'Buyer-facing assignee validation should not leak rule-path internals.');
$assert(!array_key_exists('direct_grant_id', $loggedIn->data), 'Buyer-facing assignee validation should not leak direct grant IDs.');
$assert(!array_key_exists('claims_per_assignee', $loggedIn->data), 'Buyer-facing assignee validation should not leak detailed eligibility counters.');

fwrite(STDOUT, "Ticket claims assignee validation OK.\n");
