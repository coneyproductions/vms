<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);

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

function add_shortcode(string $tag, $callback): bool
{
	return true;
}

function apply_filters(string $hook, $value, ...$args)
{
	return $value;
}

function home_url(string $path = '/'): string
{
	return $path;
}

function wp_validate_redirect(string $candidate, string $fallback): string
{
	return $candidate !== '' ? $candidate : $fallback;
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

function sanitize_title(string $value): string
{
	return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
}

function is_admin(): bool
{
	return false;
}

function wp_doing_ajax(): bool
{
	return false;
}

function wp_doing_cron(): bool
{
	return false;
}

function current_filter(): string
{
	return 'vms_test_filter';
}

if (!class_exists('WP_Error')) {
	class WP_Error
	{
		private string $code;
		private string $message;
		/** @var mixed */
		private $data;

		/** @param mixed $data */
		public function __construct(string $code = '', string $message = '', $data = null)
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

		/** @return mixed */
		public function get_error_data()
		{
			return $this->data;
		}
	}
}

if (!function_exists('is_wp_error')) {
	function is_wp_error($thing): bool
	{
		return $thing instanceof WP_Error;
	}
}

if (!class_exists('WP_REST_Response')) {
	class WP_REST_Response
	{
		/** @var mixed */
		private $data;
		private int $status;

		/** @param mixed $data */
		public function __construct($data = null, int $status = 200)
		{
			$this->data = $data;
			$this->status = $status;
		}

		/** @return mixed */
		public function get_data()
		{
			return $this->data;
		}

		public function get_status(): int
		{
			return $this->status;
		}
	}
}

if (!function_exists('rest_ensure_response')) {
	function rest_ensure_response($value): WP_REST_Response
	{
		if ($value instanceof WP_REST_Response) {
			return $value;
		}

		return new WP_REST_Response($value, 200);
	}
}

if (!class_exists('WP_REST_Request')) {
	class WP_REST_Request
	{
		private string $body;
		/** @var mixed */
		private $json_params = null;
		private bool $parsed = false;

		public function __construct(string $body = '')
		{
			$this->body = $body;
		}

		public function get_body(): string
		{
			return $this->body;
		}

		public function has_valid_params()
		{
			$raw = trim($this->body);
			if ($raw === '') {
				$this->parsed = true;
				$this->json_params = null;
				return true;
			}

			$decoded = vms_json_decode_associative($this->body, 32);
			if (empty($decoded['ok'])) {
				return new WP_Error(
					'rest_invalid_json',
					'Invalid JSON body passed.',
					array('status' => 400)
				);
			}

			$this->parsed = true;
			$this->json_params = $decoded['value'];
			return true;
		}

		public function get_json_params()
		{
			if (!$this->parsed) {
				$valid = $this->has_valid_params();
				if (is_wp_error($valid)) {
					return null;
				}
			}

			return $this->json_params;
		}
	}
}

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['vms_test_current_user_caps'][$capability]);
}

function get_option(string $key, $default = false)
{
	return array_key_exists($key, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$key] : $default;
}

if (!class_exists('VMS_Tours')) {
	class VMS_Tours
	{
		public const OPT_ENABLED = 'vms_tours_enabled';

		public static bool $can_run = true;
		public static bool $nonce_valid = true;
		public static int $merge_calls = 0;
		public static int $replace_calls = 0;
		/** @var array<string,mixed> */
		public static array $last_merge_payload = array();
		/** @var array<string,mixed> */
		public static array $last_replace_payload = array();
		public static string $last_replace_source = '';

		public static function reset(): void
		{
			self::$can_run = true;
			self::$nonce_valid = true;
			self::$merge_calls = 0;
			self::$replace_calls = 0;
			self::$last_merge_payload = array();
			self::$last_replace_payload = array();
			self::$last_replace_source = '';
		}

		public static function can_run_tours(): bool
		{
			return self::$can_run;
		}

		public static function verify_rest_nonce(WP_REST_Request $request): bool
		{
			return self::$nonce_valid;
		}

		public static function merge_runtime_report(array $payload): array
		{
			self::$merge_calls++;
			self::$last_merge_payload = $payload;
			if (empty($payload['context_key']) || empty($payload['anchor'])) {
				return self::get_report();
			}

			return array(
				'source' => 'runtime',
				'payload' => $payload,
			);
		}

		public static function replace_scan_report(array $payload, string $source = 'scan'): array
		{
			self::$replace_calls++;
			self::$last_replace_payload = $payload;
			self::$last_replace_source = $source;

			return array(
				'source' => $source,
				'contexts' => isset($payload['contexts']) && is_array($payload['contexts']) ? $payload['contexts'] : array(),
			);
		}

		public static function get_report(): array
		{
			return array(
				'source' => 'runtime',
				'contexts' => array(),
				'summary' => array('missing_anchor_count' => 0),
			);
		}

		public static function get_tile_data(): array
		{
			return array();
		}

		public static function get_anchor_contract(): array
		{
			return array();
		}
	}
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

require dirname(__DIR__) . '/includes/runtime-guards.php';
require dirname(__DIR__) . '/includes/integrations/ticketing-phase-b.php';
require dirname(__DIR__) . '/includes/integrations/ticketing-rules-v2.php';
require dirname(__DIR__) . '/includes/services/event-plan-import/event-plan-import-engine.php';
require dirname(__DIR__) . '/includes/integrations/ticketing-claims-customer.php';
require dirname(__DIR__) . '/includes/modules/admissions/pass-claims.php';
require dirname(__DIR__) . '/includes/rest/class-vms-rest-tours.php';

$decodedObject = vms_json_decode_associative('{"ok":true}', 8);
$assert(!empty($decodedObject['ok']), 'Expected object JSON to decode successfully.');
$assert(vms_json_decoded_is_object((array) $decodedObject['value'], (string) ($decodedObject['top_level_token'] ?? '')), 'Expected top-level object detection to succeed.');

$decodedList = vms_json_decode_associative('[1,2,3]', 8);
$assert(!empty($decodedList['ok']), 'Expected list JSON to decode successfully.');
$assert(vms_json_decoded_is_list((array) $decodedList['value'], (string) ($decodedList['top_level_token'] ?? '')), 'Expected top-level list detection to succeed.');

$assert(
	vms_pass_claims_decode_venue_ids_json('[4,"2",4,0]') === array(2, 4),
	'Venue ID JSON should normalize a JSON list of venue IDs.'
);
$assert(
	vms_pass_claims_decode_venue_ids_json('{"venue_id":4}') === array(),
	'Venue ID JSON should reject object-shaped payloads.'
);

$existingCounts = vms_ticketing_claims_parse_existing_counts_payload('{"buyer@example.com":2,"Guest@example.com":"3","bad":[] }');
$assert(
	$existingCounts === array(
		'buyer@example.com' => 2,
		'guest@example.com' => 3,
	),
	'Existing-count payloads should accept only scalar email=>count objects.'
);

$assert(
	vms_ticketing_b_validate_tier_rows_payload(array(
		array(
			'tier_key' => 'ga',
			'name' => 'General Admission',
			'price' => '15.00',
		),
	)),
	'Phase B tier payload validator should accept a well-formed tier list.'
);
$assert(
	!vms_ticketing_b_validate_tier_rows_payload(array(
		array(
			'name' => array('bad'),
		),
	)),
	'Phase B tier payload validator should reject nested array values in scalar fields.'
);

$assert(
	vms_ticketing_b_validate_commit_items_payload(array(
		array(
			'tier_key' => 'ga',
			'action' => 'create',
			'woo_product_id' => '71',
		),
	)),
	'Phase B commit payload validator should accept a well-formed commit item list.'
);
$assert(
	!vms_ticketing_b_validate_commit_items_payload(array(
		array(
			'tier_key' => 'ga',
			'action' => 'explode',
		),
	)),
	'Phase B commit payload validator should reject unknown actions.'
);

$assert(
	vms_ticketing_v2_validate_config_payload(array(
		'mode' => 'vms_managed',
		'tickets' => array(
			array(
				'ticket_key' => 'ga',
				'title' => 'General Admission',
			),
		),
		'entitlements' => array(
			array(
				'entitlement_id' => 'vip_parking',
			),
		),
	)),
	'Ticketing v2 config validator should accept object payloads with list-based tickets and entitlements.'
);
$assert(
	!vms_ticketing_v2_validate_config_payload(array(
		'tickets' => array(
			'not_a_list' => array('ticket_key' => 'ga'),
		),
	)),
	'Ticketing v2 config validator should reject non-list ticket collections.'
);

$assert(
	vms_ticketing_v2_validate_atomic_add_payload(array(
		'nonce' => 'abc123',
		'ticket_lines' => array(
			array(
				'product_id' => 55,
				'qty' => 2,
				'variation' => array(
					'attribute_size' => 'large',
				),
				'claim_assignments' => array(
					array(
						'seat' => 1,
						'assignee_email' => 'guest@example.com',
					),
				),
			),
		),
		'addon_lines' => array(
			array(
				'product_id' => 88,
				'qty' => 1,
			),
		),
	)),
	'Atomic add payload validator should accept well-formed ticket and add-on lines.'
);
$assert(
	!vms_ticketing_v2_validate_atomic_add_payload(array(
		'ticket_lines' => array(
			array(
				'product_id' => 55,
				'qty' => 1,
				'claim_assignments' => array(
					'bad' => 'shape',
				),
			),
		),
	)),
	'Atomic add payload validator should reject non-list claim assignment payloads.'
);

$assert(
	vms_ticketing_v2_validate_silent_add_payload(array(
		'items' => array(
			array(
				'productId' => 77,
				'qty' => 2,
			),
		),
	)),
	'Silent-add payload validator should accept a list of simple add-on rows.'
);
$assert(
	!vms_ticketing_v2_validate_silent_add_payload(array(
		'items' => array(
			'broken' => array(
				'productId' => 77,
				'qty' => 2,
			),
		),
	)),
	'Silent-add payload validator should reject non-list item collections.'
);

$assert(
	vms_event_plan_import_validate_rows_payload(
		array(
			'columns' => array(
				'has_agenda_text' => true,
				'secondary_vendor_columns' => array('dessert_vendor'),
			),
			'rows' => array(
				array(
					'row_number' => 2,
					'event_key' => 'summer-fest',
					'warnings' => array(),
					'errors' => array(),
					'secondary_vendor_ids' => array(15),
					'secondary_vendor_create_names' => array('Dessert Cart'),
				),
			),
		),
		'{'
	),
	'Importer preview payload validator should accept the expected object-with-rows shape.'
);
$assert(
	!vms_event_plan_import_validate_rows_payload(
		array(
			'columns' => array('has_agenda_text' => true),
			'rows' => array(
				'bad' => array('row_number' => 1),
			),
		),
		'{'
	),
	'Importer preview payload validator should reject non-list rows collections.'
);

$assert(
	vms_event_plan_import_validate_snapshot_payload(
		array(
			'created_at' => time(),
			'entries' => array(
				array(
					'plan_id' => 99,
					'before' => array('title' => 'Before'),
				),
			),
		),
		'{'
	),
	'Importer snapshot validator should accept the expected object-with-entries shape.'
);
$assert(
	!vms_event_plan_import_validate_snapshot_payload(
		array(
			'entries' => array(
				'bad' => array('plan_id' => 99),
			),
		),
		'{'
	),
	'Importer snapshot validator should reject non-list entry collections.'
);

$GLOBALS['vms_test_current_user_caps'] = array('manage_options' => true);
$GLOBALS['vms_test_options'] = array(
	VMS_Tours::OPT_ENABLED => 1,
);
VMS_Tours::reset();

$assertToursError = static function ($result, string $expectedCode, int $expectedStatus, string $message) use ($assert): void {
	$assert(is_wp_error($result), $message . ' Expected a WP_Error response.');
	/** @var WP_Error $result */
	$assert($result->get_error_code() === $expectedCode, $message . ' Unexpected error code.');
	$errorData = $result->get_error_data();
	$status = is_array($errorData) && isset($errorData['status']) ? (int) $errorData['status'] : 0;
	$assert($status === $expectedStatus, $message . ' Unexpected error status.');
};

$assertToursResponse = static function ($result, int $expectedStatus, string $message) use ($assert): array {
	$assert($result instanceof WP_REST_Response, $message . ' Expected a REST response.');
	$assert($result->get_status() === $expectedStatus, $message . ' Unexpected response status.');
	$data = $result->get_data();
	$assert(is_array($data), $message . ' Expected an array response payload.');
	return $data;
};

$invalidToursBodies = array(
	array(
		'label' => 'Malformed tours JSON should be rejected.',
		'body' => '{"broken":',
		'code' => 'vms_tours_invalid_json_body',
	),
	array(
		'label' => 'JSON null should be rejected.',
		'body' => 'null',
		'code' => 'vms_tours_invalid_json_payload',
	),
	array(
		'label' => 'JSON strings should be rejected.',
		'body' => '"hello"',
		'code' => 'vms_tours_invalid_json_payload',
	),
	array(
		'label' => 'JSON numbers should be rejected.',
		'body' => '42',
		'code' => 'vms_tours_invalid_json_payload',
	),
	array(
		'label' => 'JSON booleans should be rejected.',
		'body' => 'true',
		'code' => 'vms_tours_invalid_json_payload',
	),
	array(
		'label' => 'Non-empty JSON lists should be rejected.',
		'body' => '[{"context_key":"dashboard"}]',
		'code' => 'vms_tours_invalid_json_payload',
	),
	array(
		'label' => 'Empty JSON lists should be rejected.',
		'body' => '[]',
		'code' => 'vms_tours_invalid_json_payload',
	),
);

foreach ($invalidToursBodies as $case) {
	VMS_Tours::reset();
	$runtimeResult = VMS_REST_Tours::post_runtime_drift(new WP_REST_Request($case['body']));
	$assertToursError($runtimeResult, $case['code'], 400, $case['label'] . ' Runtime drift.');
	$assert(VMS_Tours::$merge_calls === 0, $case['label'] . ' Runtime drift must not run merge work.');
	$assert(VMS_Tours::$replace_calls === 0, $case['label'] . ' Runtime drift must not replace scan reports.');

	VMS_Tours::reset();
	$scanResult = VMS_REST_Tours::post_drift_scan(new WP_REST_Request($case['body']));
	$assertToursError($scanResult, $case['code'], 400, $case['label'] . ' Drift scan.');
	$assert(VMS_Tours::$merge_calls === 0, $case['label'] . ' Drift scan must not run merge work.');
	$assert(VMS_Tours::$replace_calls === 0, $case['label'] . ' Drift scan must not replace scan reports.');
}

VMS_Tours::reset();
$runtimeEmptyObject = $assertToursResponse(
	VMS_REST_Tours::post_runtime_drift(new WP_REST_Request('{}')),
	200,
	'Empty object runtime-drift payload should remain accepted.'
);
$assert(VMS_Tours::$merge_calls === 1, 'Empty object runtime-drift payload should still reach the runtime handler.');
$assert(($runtimeEmptyObject['report']['source'] ?? '') === 'runtime', 'Empty object runtime-drift payload should preserve the existing report contract.');

VMS_Tours::reset();
$scanEmptyObject = $assertToursResponse(
	VMS_REST_Tours::post_drift_scan(new WP_REST_Request('{}')),
	200,
	'Empty object drift-scan payload should remain accepted.'
);
$assert(VMS_Tours::$replace_calls === 1, 'Empty object drift-scan payload should still reach the scan replacement handler.');
$assert(($scanEmptyObject['report']['source'] ?? '') === 'scan', 'Empty object drift-scan payload should preserve the default scan source.');

VMS_Tours::reset();
$validRuntimePayload = $assertToursResponse(
	VMS_REST_Tours::post_runtime_drift(new WP_REST_Request('{"context_key":"dashboard","anchor":"tour-anchor","tour_id":"intro","severity":"optional"}')),
	200,
	'Valid runtime-drift object payload should remain accepted.'
);
$assert(VMS_Tours::$merge_calls === 1, 'Valid runtime-drift payload should invoke runtime merge work once.');
$assert(
	VMS_Tours::$last_merge_payload === array(
		'context_key' => 'dashboard',
		'anchor' => 'tour-anchor',
		'tour_id' => 'intro',
		'severity' => 'optional',
	),
	'Valid runtime-drift payload should be passed through unchanged as an object payload.'
);
$assert(($validRuntimePayload['report']['source'] ?? '') === 'runtime', 'Valid runtime-drift payload should return a runtime report.');

VMS_Tours::reset();
$validScanPayload = $assertToursResponse(
	VMS_REST_Tours::post_drift_scan(new WP_REST_Request('{"source":"auto-update","contexts":{"dashboard":{"scan_error":"","missing_anchors":{}}}}')),
	200,
	'Valid drift-scan object payload should remain accepted.'
);
$assert(VMS_Tours::$replace_calls === 1, 'Valid drift-scan payload should invoke scan replacement once.');
$assert(VMS_Tours::$last_replace_source === 'auto-update', 'Valid drift-scan payload should preserve the auto-update source contract.');
$assert(isset($validScanPayload['report']['contexts']['dashboard']), 'Valid drift-scan payload should preserve object-shaped contexts.');

VMS_Tours::reset();
$GLOBALS['vms_test_current_user_caps'] = array();
$capabilityFailure = VMS_REST_Tours::can_manage_tours(new WP_REST_Request('{}'));
$assertToursError($capabilityFailure, 'forbidden', 403, 'Tours management capability protection should remain intact.');

$GLOBALS['vms_test_current_user_caps'] = array('manage_options' => true);
VMS_Tours::reset();
VMS_Tours::$nonce_valid = false;
$nonceFailure = VMS_REST_Tours::can_manage_tours(new WP_REST_Request('{}'));
$assertToursError($nonceFailure, 'forbidden', 403, 'Tours management nonce protection should remain intact.');

VMS_Tours::reset();
VMS_Tours::$nonce_valid = true;
VMS_Tours::$can_run = false;
$runtimeCapabilityFailure = VMS_REST_Tours::can_post_runtime_drift(new WP_REST_Request('{}'));
$assertToursError($runtimeCapabilityFailure, 'forbidden', 403, 'Runtime-drift capability protection should remain intact.');

VMS_Tours::reset();
VMS_Tours::$nonce_valid = false;
$runtimeNonceFailure = VMS_REST_Tours::can_post_runtime_drift(new WP_REST_Request('{}'));
$assertToursError($runtimeNonceFailure, 'forbidden', 403, 'Runtime-drift nonce protection should remain intact.');

fwrite(STDOUT, "decoded JSON validation helpers OK.\n");
