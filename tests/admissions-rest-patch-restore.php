<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');

final class WP_REST_Response
{
	private array $data;
	private int $status;

	public function __construct(array $data = array(), int $status = 200)
	{
		$this->data = $data;
		$this->status = $status;
	}

	public function get_data(): array
	{
		return $this->data;
	}

	public function get_status(): int
	{
		return $this->status;
	}
}

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

final class VMS_Test_WPDB
{
	public string $prefix = 'wp_';
	public string $last_error = '';
	/** @var array<int,array<string,mixed>> */
	public array $rows = array();

	public function prepare(string $query, ...$args): string
	{
		$arg_index = 0;
		return (string) preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[ids]/',
			static function (array $matches) use (&$arg_index, $args): string {
				$placeholder = $matches[0];
				$value = $args[$arg_index] ?? null;
				$arg_index++;

				$type = substr($placeholder, -1);
				if ($type === 'd') {
					return (string) (int) $value;
				}
				if ($type === 'i') {
					return '`' . str_replace('`', '``', (string) $value) . '`';
				}

				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$query
		);
	}

	public function get_row(string $query, string $output = ARRAY_A): ?array
	{
		unset($output);
		if (!preg_match('/WHERE id = (\d+)/', $query, $matches)) {
			return null;
		}

		$id = (int) ($matches[1] ?? 0);
		return isset($this->rows[$id]) ? $this->rows[$id] : null;
	}

	public function update(string $table, array $updates, array $where, array $formats = array(), array $whereFormats = array())
	{
		unset($table, $formats, $whereFormats);
		$id = (int) ($where['id'] ?? 0);
		if ($id <= 0 || !isset($this->rows[$id])) {
			return 0;
		}

		$this->rows[$id] = array_merge($this->rows[$id], $updates);
		return 1;
	}
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function sanitize_text_field(string $value): string
{
	return trim($value);
}

function sanitize_textarea_field(string $value): string
{
	return trim($value);
}

function sanitize_email(string $value): string
{
	return trim($value);
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
}

function absint($value): int
{
	return max(0, (int) $value);
}

function rest_ensure_response(array $data): WP_REST_Response
{
	return new WP_REST_Response($data, 200);
}

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function get_current_user_id(): int
{
	return 777;
}

function vms_admission_current_user_can_manage(): bool
{
	return true;
}

function vms_admission_settings(): array
{
	return array('max_party_size' => 8);
}

function vms_admission_table_entries(): string
{
	return 'wp_vms_admission_entries';
}

function vms_admission_now_mysql(): string
{
	return '2026-06-19 01:03:00';
}

function vms_admission_prepare_row(array $row): array
{
	return $row;
}

function vms_admission_audit_log(int $event_plan_id, ?int $entry_id, string $action, int $actor_user_id, string $actor_context, array $details = array()): bool
{
	$GLOBALS['vms_test_admission_audit'][] = array(
		'event_plan_id' => $event_plan_id,
		'entry_id' => $entry_id,
		'action' => $action,
		'actor_user_id' => $actor_user_id,
		'actor_context' => $actor_context,
		'details' => $details,
	);
	return true;
}

function vms_admission_normalize_name(string $value): string
{
	return strtolower(trim($value));
}

function vms_admission_normalize_email(string $value): string
{
	return strtolower(trim($value));
}

function vms_admission_normalize_phone(string $value): string
{
	return preg_replace('/\D+/', '', $value) ?? '';
}

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

require dirname(__DIR__) . '/includes/modules/admissions/rest.php';

$wpdb->rows[77] = array(
	'id' => 77,
	'event_plan_id' => 1430,
	'status' => 'canceled',
	'party_size' => 2,
	'checked_in_qty' => 0,
	'guest_name' => 'Canceled guest',
	'guest_name_norm' => 'canceled guest',
);

$restoreResponse = vms_admission_rest_patch(new WP_REST_Request(array(), array(
	'id' => 77,
	'status' => 'active',
)));
$restoreData = $restoreResponse instanceof WP_REST_Response ? $restoreResponse->get_data() : array();

$assert($restoreResponse instanceof WP_REST_Response, 'Canceled-entry restore should return a REST response.');
$assert($restoreResponse->get_status() === 200, 'Canceled-entry restore should succeed.');
$assert(($restoreData['ok'] ?? false) === true, 'Canceled-entry restore should return an ok payload.');
$assert((string) ($wpdb->rows[77]['status'] ?? '') === 'active', 'Canceled-entry restore should reactivate the row.');

$wpdb->rows[78] = array(
	'id' => 78,
	'event_plan_id' => 1430,
	'status' => 'canceled',
	'party_size' => 2,
	'checked_in_qty' => 0,
	'guest_name' => 'Still canceled',
	'guest_name_norm' => 'still canceled',
);

$blockedEdit = vms_admission_rest_patch(new WP_REST_Request(array(), array(
	'id' => 78,
	'notes' => 'should stay blocked',
)));
$blockedEditData = $blockedEdit instanceof WP_REST_Response ? $blockedEdit->get_data() : array();

$assert($blockedEdit instanceof WP_REST_Response, 'Canceled-entry edit should return a REST response.');
$assert($blockedEdit->get_status() === 409, 'Canceled-entry edit should remain blocked.');
$assert((string) (($blockedEditData['error']['code'] ?? '')) === 'cannot_edit_canceled', 'Canceled-entry edit should keep the cannot_edit_canceled error.');

$wpdb->rows[79] = array(
	'id' => 79,
	'event_plan_id' => 1430,
	'status' => 'canceled',
	'party_size' => 2,
	'checked_in_qty' => 0,
	'guest_name' => 'Canceled combo',
	'guest_name_norm' => 'canceled combo',
);

$blockedCombo = vms_admission_rest_patch(new WP_REST_Request(array(), array(
	'id' => 79,
	'status' => 'active',
	'notes' => 'should stay blocked',
)));
$blockedComboData = $blockedCombo instanceof WP_REST_Response ? $blockedCombo->get_data() : array();

$assert($blockedCombo instanceof WP_REST_Response, 'Canceled-entry restore-plus-edit should return a REST response.');
$assert($blockedCombo->get_status() === 409, 'Canceled-entry restore-plus-edit should remain blocked.');
$assert((string) (($blockedComboData['error']['code'] ?? '')) === 'cannot_edit_canceled', 'Canceled-entry restore-plus-edit should keep the cannot_edit_canceled error.');

fwrite(STDOUT, "Admissions REST patch restore OK.\n");
