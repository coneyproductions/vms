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

fwrite(STDOUT, "decoded JSON validation helpers OK.\n");
