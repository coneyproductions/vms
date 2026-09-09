<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

final class WP_Error
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

final class VMS_Test_WPDB
{
	/** @var array<int,array<string,mixed>> */
	public array $rows = array();

	public function prepare(string $query, ...$args): string
	{
		unset($args);
		return $query;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function get_results(string $query, $output = ARRAY_A): array
	{
		unset($query, $output);
		return $this->rows;
	}
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
}

function wp_json_encode($value)
{
	$json = json_encode($value);
	return is_string($json) ? $json : false;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function update_post_meta(...$args): bool
{
	$GLOBALS['vms_test_post_meta_updates'][] = $args;
	return true;
}

function is_wp_error($thing): bool
{
	return $thing instanceof WP_Error;
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	throw new RuntimeException(
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) === false, $message);
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name;
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
	}

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	$inSingleQuote = false;
	$inDoubleQuote = false;
	$inLineComment = false;
	$inBlockComment = false;
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		$nextChar = $i + 1 < $length ? $source[$i + 1] : '';
		$previousChar = $i > 0 ? $source[$i - 1] : '';

		if ($inLineComment) {
			if ($char === "\n") {
				$inLineComment = false;
			}
			continue;
		}
		if ($inBlockComment) {
			if ($char === '*' && $nextChar === '/') {
				$inBlockComment = false;
				$i++;
			}
			continue;
		}
		if ($inSingleQuote) {
			if ($char === "'" && $previousChar !== '\\') {
				$inSingleQuote = false;
			}
			continue;
		}
		if ($inDoubleQuote) {
			if ($char === '"' && $previousChar !== '\\') {
				$inDoubleQuote = false;
			}
			continue;
		}

		if ($char === '/' && $nextChar === '/') {
			$inLineComment = true;
			$i++;
			continue;
		}
		if ($char === '/' && $nextChar === '*') {
			$inBlockComment = true;
			$i++;
			continue;
		}
		if ($char === "'") {
			$inSingleQuote = true;
			continue;
		}
		if ($char === '"') {
			$inDoubleQuote = true;
			continue;
		}

		if ($char === '{') {
			$depth++;
		} elseif ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	throw new RuntimeException('Unable to locate closing brace for ' . $name . '.');
}

function vms_test_call_without_warnings(callable $callback): array
{
	$warnings = array();
	set_error_handler(
		static function (int $errno, string $errstr, string $errfile, int $errline) use (&$warnings): bool {
			$warnings[] = array(
				'errno' => $errno,
				'errstr' => $errstr,
				'errfile' => $errfile,
				'errline' => $errline,
			);
			return true;
		}
	);

	try {
		$result = $callback();
	} finally {
		restore_error_handler();
	}

	return array(
		'result' => $result,
		'warnings' => $warnings,
	);
}

function bvmgr_tasks_table_name(string $kind): string
{
	return 'wp_' . $kind;
}

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname($pluginRoot, 2) . '/vms';
$storePath = $pluginRoot . '/includes/modules/staff-tasks/store.php';
$liveStorePath = $livePluginRoot . '/includes/modules/staff-tasks/store.php';
$generatorPath = $pluginRoot . '/includes/modules/staff-tasks/generator.php';
$liveGeneratorPath = $livePluginRoot . '/includes/modules/staff-tasks/generator.php';
$dbPath = $pluginRoot . '/includes/modules/staff-tasks/db.php';
$adminUiPath = $pluginRoot . '/includes/modules/staff-tasks/admin-ui.php';

$storeSource = (string) file_get_contents($storePath);
$liveStoreSource = (string) file_get_contents($liveStorePath);
$generatorSource = (string) file_get_contents($generatorPath);
$liveGeneratorSource = (string) file_get_contents($liveGeneratorPath);
$dbSource = (string) file_get_contents($dbPath);
$adminUiSource = (string) file_get_contents($adminUiPath);

$decoderBody = vms_test_extract_function($storeSource, 'bvmgr_tasks_decode_checklist_overrides');
$getChecklistBody = vms_test_extract_function($storeSource, 'bvmgr_tasks_get_checklist_items');
$writerBody = vms_test_extract_function($storeSource, 'bvmgr_tasks_replace_checklist_items');
$dueBody = vms_test_extract_function($generatorSource, 'bvmgr_tasks_compute_due_at_local');
$mergeBody = vms_test_extract_function($generatorSource, 'bvmgr_tasks_merge_template_with_overrides');
$resolveBody = vms_test_extract_function($generatorSource, 'bvmgr_tasks_resolve_assignment_for_instance');
$generateBody = vms_test_extract_function($generatorSource, 'bvmgr_tasks_generate_for_event');
$signatureHelperBody = vms_test_extract_function($generatorSource, 'bvmgr_tasks_decode_stored_event_signature');

vms_test_assert_contains('function bvmgr_tasks_decode_checklist_overrides', $storeSource, 'Specialized checklist-overrides decoder should exist.');
vms_test_assert_not_contains('json_decode(', $getChecklistBody, 'bvmgr_tasks_get_checklist_items() should no longer decode JSON directly.');
vms_test_assert_same(1, substr_count($storeSource, 'json_decode('), 'store.php should retain exactly one raw json_decode() call.');
vms_test_assert_same(1, substr_count($decoderBody, 'json_decode('), 'Checklist-overrides decoder should own the single raw json_decode() call.');
vms_test_assert_contains("'overrides_state'", $getChecklistBody, 'Checklist items should expose overrides_state.');
vms_test_assert_contains("'overrides_reason'", $getChecklistBody, 'Checklist items should expose overrides_reason.');
vms_test_assert_contains("\$payload['required_default'] = !empty(\$overrides['required_default']) ? 1 : 0;", $writerBody, 'Checklist writer should preserve required_default normalization.');
vms_test_assert_contains("\$payload['priority'] = bvmgr_tasks_sanitize_priority((string) \$overrides['priority']);", $writerBody, 'Checklist writer should preserve priority normalization.');
vms_test_assert_contains("\$payload['assignment_mode'] = bvmgr_tasks_sanitize_assignment_mode((string) \$overrides['assignment_mode']);", $writerBody, 'Checklist writer should preserve assignment_mode normalization.');
vms_test_assert_contains("\$payload['role_key'] = sanitize_key((string) \$overrides['role_key']);", $writerBody, 'Checklist writer should preserve role_key normalization.');
vms_test_assert_contains("\$payload['assignee_user_id'] = absint(\$overrides['assignee_user_id']);", $writerBody, 'Checklist writer should preserve assignee_user_id normalization.');
vms_test_assert_contains("\$payload['due_offset_minutes'] = (int) \$overrides['due_offset_minutes'];", $writerBody, 'Checklist writer should preserve due_offset_minutes normalization.');
vms_test_assert_contains('overrides_json LONGTEXT NULL,', $dbSource, 'Checklist storage schema should remain overrides_json LONGTEXT NULL.');
vms_test_assert_contains("if (!bvmgr_tasks_current_user_can_manage_checklists()) {", $adminUiSource, 'Checklist capability guard should remain unchanged.');
vms_test_assert_contains("if (bvmgr_tasks_admin_is_exact_post_request() && isset(\$_POST['vms_tasks_checklist_action'])) {", $adminUiSource, 'Checklist exact POST gate should remain unchanged.');
vms_test_assert_contains("check_admin_referer(bvmgr_nonce_action_for_request('bvmgr_tasks_save_checklist', '_wpnonce'), '_wpnonce');", $adminUiSource, 'Checklist nonce check should remain unchanged.');
vms_test_assert_contains("if (!bvmgr_tasks_current_user_can_manage_all()) {", $adminUiSource, 'One-off capability guard should remain unchanged.');
vms_test_assert_contains("!wp_verify_nonce(\$nonce, bvmgr_nonce_action_for_value(\$nonce, 'bvmgr_tasks_create_one_off'))", $adminUiSource, 'One-off nonce boundary should remain unchanged.');
vms_test_assert_same(hash('sha256', $storeSource), hash('sha256', $liveStoreSource), 'Mirror and live store files should be byte-identical.');
vms_test_assert_same(hash('sha256', $generatorSource), hash('sha256', $liveGeneratorSource), 'Mirror and live generator files should be byte-identical.');
vms_test_assert_same(1, substr_count($generatorSource, 'json_decode('), 'Generator file should retain exactly one raw json_decode() call for the signature helper.');
vms_test_assert_same(1, substr_count($signatureHelperBody, 'json_decode('), 'Stored-signature helper should remain the only raw decoder in generator.php.');
vms_test_assert_true(strpos($storeSource, 'bvmgr_tasks_decode_stored_event_signature') === false, 'store.php should remain outside the stored-signature slice.');
vms_test_assert_true(strpos($generatorSource, 'overrides_json') === false, 'Generator source should continue consuming decoded overrides instead of raw overrides_json.');

// Generation now delegates to transactional authority; covered in tests/staff-tasks/*.php.
vms_test_assert_contains('bvmgr_tasks_generate_committed_event', $generateBody, 'Legacy generator must delegate to canonical transactional generation.');

eval(vms_test_extract_function($storeSource, 'bvmgr_tasks_allowed_priorities'));
eval(vms_test_extract_function($storeSource, 'bvmgr_tasks_sanitize_priority'));
eval(vms_test_extract_function($storeSource, 'bvmgr_tasks_sanitize_due_mode'));
eval(vms_test_extract_function($storeSource, 'bvmgr_tasks_sanitize_assignment_mode'));
eval($decoderBody);
eval($getChecklistBody);





$largeObject = array();
for ($i = 0; $i < 50; $i++) {
	$largeObject['unknown_' . $i] = $i;
}

$excessiveDepth = '0';
for ($i = 0; $i < 20; $i++) {
	$excessiveDepth = '{"nested":' . $excessiveDepth . '}';
}

$decoderCases = array(
	'db_null' => array('raw' => null, 'state' => 'missing', 'reason' => 'missing_value', 'overrides' => array()),
	'empty_string' => array('raw' => '', 'state' => 'missing', 'reason' => 'blank_value', 'overrides' => array()),
	'whitespace_only' => array('raw' => " \n\t ", 'state' => 'missing', 'reason' => 'blank_value', 'overrides' => array()),
	'valid_empty_object' => array('raw' => '{}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array()),
	'valid_full_object' => array(
		'raw' => '{"required_default":0,"priority":"low","assignment_mode":"person","role_key":"","assignee_user_id":88,"due_offset_minutes":-30}',
		'state' => 'valid',
		'reason' => 'valid',
		'overrides' => array(
			'required_default' => 0,
			'priority' => 'low',
			'assignment_mode' => 'person',
			'role_key' => '',
			'assignee_user_id' => 88,
			'due_offset_minutes' => -30,
		),
	),
	'valid_subset_object' => array('raw' => '{"priority":"low"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'low')),
	'required_default_zero' => array('raw' => '{"required_default":0}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('required_default' => 0)),
	'required_default_one' => array('raw' => '{"required_default":1}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('required_default' => 1)),
	'priority_low' => array('raw' => '{"priority":"low"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'low')),
	'priority_normal' => array('raw' => '{"priority":"normal"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'normal')),
	'priority_high' => array('raw' => '{"priority":"high"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'high')),
	'invalid_priority' => array('raw' => '{"priority":"urgent"}', 'state' => 'invalid', 'reason' => 'priority_value', 'overrides' => array()),
	'assignment_role' => array('raw' => '{"assignment_mode":"role"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignment_mode' => 'role')),
	'assignment_person' => array('raw' => '{"assignment_mode":"person"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignment_mode' => 'person')),
	'assignment_scheduled_role' => array('raw' => '{"assignment_mode":"scheduled_role"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignment_mode' => 'scheduled_role')),
	'invalid_assignment_mode' => array('raw' => '{"assignment_mode":"bogus"}', 'state' => 'invalid', 'reason' => 'assignment_mode_value', 'overrides' => array()),
	'blank_role_key' => array('raw' => '{"role_key":""}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('role_key' => '')),
	'canonical_role_key' => array('raw' => '{"role_key":"lead-tech"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('role_key' => 'lead-tech')),
	'material_role_key_sanitization' => array('raw' => '{"role_key":"Lead Tech"}', 'state' => 'invalid', 'reason' => 'role_key_value', 'overrides' => array()),
	'assignee_zero' => array('raw' => '{"assignee_user_id":0}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignee_user_id' => 0)),
	'assignee_positive' => array('raw' => '{"assignee_user_id":88}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignee_user_id' => 88)),
	'assignee_numeric_string' => array('raw' => '{"assignee_user_id":"88"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignee_user_id' => 88)),
	'assignee_negative' => array('raw' => '{"assignee_user_id":-3}', 'state' => 'invalid', 'reason' => 'assignee_user_id_value', 'overrides' => array()),
	'assignee_float' => array('raw' => '{"assignee_user_id":12.5}', 'state' => 'invalid', 'reason' => 'assignee_user_id_value', 'overrides' => array()),
	'assignee_boolean' => array('raw' => '{"assignee_user_id":true}', 'state' => 'invalid', 'reason' => 'assignee_user_id_value', 'overrides' => array()),
	'due_offset_negative' => array('raw' => '{"due_offset_minutes":-30}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('due_offset_minutes' => -30)),
	'due_offset_zero' => array('raw' => '{"due_offset_minutes":0}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('due_offset_minutes' => 0)),
	'due_offset_positive' => array('raw' => '{"due_offset_minutes":45}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('due_offset_minutes' => 45)),
	'invalid_due_offset' => array('raw' => '{"due_offset_minutes":"1.5"}', 'state' => 'invalid', 'reason' => 'due_offset_minutes_value', 'overrides' => array()),
	'unknown_key' => array('raw' => '{"legacy_priority":"low"}', 'state' => 'invalid', 'reason' => 'unknown_key', 'overrides' => array()),
	'numeric_key' => array('raw' => '{"0":"low"}', 'state' => 'invalid', 'reason' => 'numeric_key', 'overrides' => array()),
	'nested_value' => array('raw' => '{"priority":["low"]}', 'state' => 'invalid', 'reason' => 'nested_priority', 'overrides' => array()),
	'list_json' => array('raw' => '["priority","low"]', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'empty_list_json' => array('raw' => '[]', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'scalar_string_json' => array('raw' => '"hello"', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'number_json' => array('raw' => '7', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'boolean_true_json' => array('raw' => 'true', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'boolean_false_json' => array('raw' => 'false', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'json_null' => array('raw' => 'null', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'malformed_json' => array('raw' => '{"priority":', 'state' => 'invalid', 'reason' => 'json_syntax', 'overrides' => array()),
	'truncated_json' => array('raw' => '{"priority":"low"', 'state' => 'invalid', 'reason' => 'json_syntax', 'overrides' => array()),
	'invalid_utf8' => array('raw' => "{\"priority\":\"bad\xB1\x31\"}", 'state' => 'invalid', 'reason' => 'json_utf8', 'overrides' => array()),
	'excessive_depth' => array('raw' => $excessiveDepth, 'state' => 'invalid', 'reason' => 'json_depth', 'overrides' => array()),
	'duplicate_keys' => array('raw' => '{"priority":"low","priority":"high"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'high')),
	'large_object' => array('raw' => (string) wp_json_encode($largeObject), 'state' => 'invalid', 'reason' => 'unknown_key', 'overrides' => array()),
	'unsupported_legacy_value' => array('raw' => '{"required":0}', 'state' => 'invalid', 'reason' => 'unknown_key', 'overrides' => array()),
);

$seenStates = array();
foreach ($decoderCases as $name => $case) {
	$call = vms_test_call_without_warnings(
		static function () use ($case) {
			return bvmgr_tasks_decode_checklist_overrides($case['raw']);
		}
	);
	$result = $call['result'];

	vms_test_assert_same(array(), $call['warnings'], 'Decoder case ' . $name . ' should not emit warnings.');
	vms_test_assert_same($case['state'], $result['state'] ?? null, 'Decoder case ' . $name . ' should return the expected state.');
	vms_test_assert_same($case['reason'], $result['reason'] ?? null, 'Decoder case ' . $name . ' should return the expected reason.');
	vms_test_assert_same($case['overrides'], $result['overrides'] ?? null, 'Decoder case ' . $name . ' should return the expected normalized overrides.');
	vms_test_assert_true(is_string($result['reason'] ?? null), 'Decoder case ' . $name . ' should return a string reason.');
	vms_test_assert_true(strpos((string) ($result['reason'] ?? ''), '{') === false, 'Decoder case ' . $name . ' reason should not include raw object JSON.');
	vms_test_assert_true(strpos((string) ($result['reason'] ?? ''), '[') === false, 'Decoder case ' . $name . ' reason should not include raw list JSON.');
	$seenStates[(string) ($result['state'] ?? '')] = true;
}
ksort($seenStates);
vms_test_assert_same(array('invalid' => true, 'missing' => true, 'valid' => true), $seenStates, 'Decoder should distinguish missing, valid, and invalid states.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->rows = array(
	array('id' => 1, 'task_template_id' => 10, 'overrides_json' => null),
	array('id' => 2, 'task_template_id' => 11, 'overrides_json' => '{}'),
	array('id' => 3, 'task_template_id' => 12, 'overrides_json' => '{"priority":"low"}'),
	array('id' => 4, 'task_template_id' => 13, 'overrides_json' => '{"priority":"urgent"}'),
);

$checklistRowsCall = vms_test_call_without_warnings(
	static function () {
		return bvmgr_tasks_get_checklist_items(55);
	}
);
$checklistRows = $checklistRowsCall['result'];
vms_test_assert_same(array(), $checklistRowsCall['warnings'], 'Checklist item reader should not emit warnings.');
vms_test_assert_same('missing', $checklistRows[0]['overrides_state'] ?? null, 'Missing overrides_json should surface missing state.');
vms_test_assert_same('valid', $checklistRows[1]['overrides_state'] ?? null, 'Empty object overrides_json should surface valid state.');
vms_test_assert_same('valid', $checklistRows[2]['overrides_state'] ?? null, 'Valid overrides_json should surface valid state.');
vms_test_assert_same('invalid', $checklistRows[3]['overrides_state'] ?? null, 'Invalid overrides_json should surface invalid state.');
vms_test_assert_same(array(), $checklistRows[0]['overrides'] ?? null, 'Missing overrides_json should expose empty overrides.');
vms_test_assert_same(array(), $checklistRows[1]['overrides'] ?? null, 'Valid empty object should expose empty overrides.');
vms_test_assert_same(array('priority' => 'low'), $checklistRows[2]['overrides'] ?? null, 'Valid overrides_json should expose normalized overrides.');
vms_test_assert_same(array(), $checklistRows[3]['overrides'] ?? null, 'Invalid overrides_json should not expose usable overrides.');
vms_test_assert_same('priority_value', $checklistRows[3]['overrides_reason'] ?? null, 'Invalid overrides_json should expose a concise reason code.');

fwrite(STDOUT, 'PASS: '.count($decoderCases)." checklist override decoder cases, canonical reader and nonce boundaries.\n");
