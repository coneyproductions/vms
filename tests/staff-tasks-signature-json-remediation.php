<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
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

function get_post_meta(int $post_id, string $key, bool $single = true)
{
	unset($single);
	return $GLOBALS['vms_test_post_meta'][$post_id][$key] ?? '';
}

function update_post_meta(...$args): bool
{
	$GLOBALS['vms_test_mutation_calls'][] = array('update', $args);
	return true;
}

function delete_post_meta(...$args): bool
{
	$GLOBALS['vms_test_mutation_calls'][] = array('delete', $args);
	return true;
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
	for ($i = $brace + 1; $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
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

function vms_test_set_signature_meta($value, bool $present = true): void
{
	$GLOBALS['vms_test_post_meta'] = array();
	if ($present) {
		$GLOBALS['vms_test_post_meta'][77]['_vms_tasks_event_signature_v1'] = $value;
	}
}

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname($pluginRoot, 2) . '/vms';
$mirrorPath = $pluginRoot . '/includes/modules/staff-tasks/generator.php';
$livePath = $livePluginRoot . '/includes/modules/staff-tasks/generator.php';
$storePath = $pluginRoot . '/includes/modules/staff-tasks/store.php';

$source = (string) file_get_contents($mirrorPath);
$liveSource = (string) file_get_contents($livePath);
$storeSource = (string) file_get_contents($storePath);

$helperBody = vms_test_extract_function($source, 'bvmgr_tasks_decode_stored_event_signature');
$decisionBody = vms_test_extract_function($source, 'bvmgr_tasks_should_allow_supersede');
$writerBody = vms_test_extract_function($source, 'bvmgr_tasks_build_event_signature');

vms_test_assert_contains('function bvmgr_tasks_decode_stored_event_signature', $source, 'Specialized decoder should exist.');
vms_test_assert_contains('bvmgr_tasks_decode_stored_event_signature(', $decisionBody, 'Decision function should use the specialized decoder.');
vms_test_assert_true(strpos($decisionBody, 'json_decode(') === false, 'Decision function should no longer decode JSON directly.');
vms_test_assert_same(1, substr_count($source, 'json_decode('), 'Generator file should retain exactly one raw json_decode() call.');
vms_test_assert_same(1, substr_count($helperBody, 'json_decode('), 'Helper should retain the single raw json_decode() call.');
vms_test_assert_contains("return '_vms_tasks_event_signature_v1';", $source, 'Storage key should remain unchanged.');
vms_test_assert_contains("'date_ymd' => (string) (\$event_context['date_ymd'] ?? '')", $writerBody, 'Writer should preserve date_ymd normalization.');
vms_test_assert_contains("'venue_id' => absint(\$event_context['venue_id'] ?? 0)", $writerBody, 'Writer should preserve venue_id normalization.');
vms_test_assert_contains("'event_type' => sanitize_key((string) (\$event_context['event_type'] ?? ''))", $writerBody, 'Writer should preserve event_type normalization.');
vms_test_assert_contains('($current_signature === $saved_signature || $current_signature === $pending_signature)', $source, 'Pending-signature skip code should remain unchanged.');
vms_test_assert_same(hash('sha256', $source), hash('sha256', $liveSource), 'Mirror and live generator files should be byte-identical.');
vms_test_assert_true(strpos($storeSource, 'bvmgr_tasks_decode_stored_event_signature') === false, 'store.php should remain outside this slice.');
vms_test_assert_contains('overrides_json', $storeSource, 'store.php overrides_json boundary should remain untouched.');
vms_test_assert_true(strpos($source, 'overrides_json') === false, 'Generator source should remain outside overrides_json scope.');

require $mirrorPath;

$largeExtras = array();
for ($i = 0; $i < 180; $i++) {
	$largeExtras['extra_' . $i] = str_repeat(chr(97 + ($i % 26)), 6);
}

$excessiveDepth = '0';
for ($i = 0; $i < 40; $i++) {
	$excessiveDepth = '{"nested":' . $excessiveDepth . '}';
}

$decoderCases = array(
	'valid_object' => array(
		'raw' => '{"date_ymd":"2026-07-22","venue_id":17,"event_type":"concert"}',
		'state' => 'valid',
		'signature' => array('date_ymd' => '2026-07-22', 'venue_id' => 17, 'event_type' => 'concert'),
	),
	'valid_object_whitespace' => array(
		'raw' => " \n\t {\"date_ymd\":\" 2026-07-22 \",\"venue_id\":\"23\",\"event_type\":\" Live Show! \"} \t",
		'state' => 'valid',
		'signature' => array('date_ymd' => '2026-07-22', 'venue_id' => 23, 'event_type' => 'liveshow'),
	),
	'missing_required_fields' => array(
		'raw' => '{"date_ymd":"2026-07-22","venue_id":17}',
		'state' => 'invalid',
		'reason' => 'missing_required_fields',
	),
	'empty_object' => array(
		'raw' => '{}',
		'state' => 'invalid',
		'reason' => 'missing_required_fields',
	),
	'list_json' => array(
		'raw' => '[1,2,3]',
		'state' => 'invalid',
		'reason' => 'non_object_json',
	),
	'empty_list_json' => array(
		'raw' => '[]',
		'state' => 'invalid',
		'reason' => 'non_object_json',
	),
	'scalar_string_json' => array(
		'raw' => '"hello"',
		'state' => 'invalid',
		'reason' => 'non_object_json',
	),
	'number_json' => array(
		'raw' => '9',
		'state' => 'invalid',
		'reason' => 'non_object_json',
	),
	'boolean_true_json' => array(
		'raw' => 'true',
		'state' => 'invalid',
		'reason' => 'non_object_json',
	),
	'boolean_false_json' => array(
		'raw' => 'false',
		'state' => 'invalid',
		'reason' => 'non_object_json',
	),
	'json_null' => array(
		'raw' => 'null',
		'state' => 'invalid',
		'reason' => 'non_object_json',
	),
	'input_null' => array(
		'raw' => null,
		'state' => 'missing',
		'reason' => 'missing_value',
	),
	'empty_string' => array(
		'raw' => '',
		'state' => 'missing',
		'reason' => 'blank_value',
	),
	'whitespace_only' => array(
		'raw' => " \n\t ",
		'state' => 'missing',
		'reason' => 'blank_value',
	),
	'malformed_json' => array(
		'raw' => '{"date_ymd":',
		'state' => 'invalid',
		'reason' => 'json_syntax',
	),
	'truncated_json' => array(
		'raw' => '{"date_ymd":"2026-07-22","venue_id":17,"event_type":"concert"',
		'state' => 'invalid',
		'reason' => 'json_syntax',
	),
	'invalid_utf8' => array(
		'raw' => "{\"date_ymd\":\"2026-07-22\",\"venue_id\":17,\"event_type\":\"bad\xB1\x31\"}",
		'state' => 'invalid',
		'reason' => 'json_utf8',
	),
	'excessive_depth' => array(
		'raw' => $excessiveDepth,
		'state' => 'invalid',
		'reason' => 'json_depth',
	),
	'duplicate_keys' => array(
		'raw' => '{"date_ymd":"2026-07-22","venue_id":17,"event_type":"concert","event_type":"Rock-Show"}',
		'state' => 'valid',
		'signature' => array('date_ymd' => '2026-07-22', 'venue_id' => 17, 'event_type' => 'rock-show'),
	),
	'numeric_string_venue_id' => array(
		'raw' => '{"date_ymd":"2026-07-22","venue_id":"45","event_type":"concert"}',
		'state' => 'valid',
		'signature' => array('date_ymd' => '2026-07-22', 'venue_id' => 45, 'event_type' => 'concert'),
	),
	'invalid_venue_id' => array(
		'raw' => '{"date_ymd":"2026-07-22","venue_id":"abc","event_type":"concert"}',
		'state' => 'invalid',
		'reason' => 'venue_id_type',
	),
	'event_type_sanitization' => array(
		'raw' => '{"date_ymd":"2026-07-22","venue_id":17,"event_type":" Rock Show / VIP "}',
		'state' => 'valid',
		'signature' => array('date_ymd' => '2026-07-22', 'venue_id' => 17, 'event_type' => 'rockshowvip'),
	),
	'invalid_field_types' => array(
		'raw' => '{"date_ymd":["2026-07-22"],"venue_id":17,"event_type":"concert"}',
		'state' => 'invalid',
		'reason' => 'date_ymd_type',
	),
	'large_object' => array(
		'raw' => (string) wp_json_encode(array_merge(
			array('date_ymd' => '2026-07-22', 'venue_id' => 17, 'event_type' => 'concert'),
			$largeExtras
		)),
		'state' => 'valid',
		'signature' => array('date_ymd' => '2026-07-22', 'venue_id' => 17, 'event_type' => 'concert'),
	),
	'unknown_extra_keys' => array(
		'raw' => '{"date_ymd":"2026-07-22","venue_id":17,"event_type":"concert","debug":{"flag":1},"extra":"value"}',
		'state' => 'valid',
		'signature' => array('date_ymd' => '2026-07-22', 'venue_id' => 17, 'event_type' => 'concert'),
	),
);

foreach ($decoderCases as $name => $case) {
	$call = vms_test_call_without_warnings(
		static function () use ($case) {
			return bvmgr_tasks_decode_stored_event_signature($case['raw']);
		}
	);
	$result = $call['result'];

	vms_test_assert_same(array(), $call['warnings'], 'Decoder case ' . $name . ' should not emit warnings.');
	vms_test_assert_same($case['state'], $result['state'] ?? null, 'Decoder case ' . $name . ' should return the expected state.');
	vms_test_assert_true(is_string($result['reason'] ?? null), 'Decoder case ' . $name . ' should return a string reason.');
	vms_test_assert_true(strpos((string) ($result['reason'] ?? ''), '{') === false, 'Decoder case ' . $name . ' reason should not include raw JSON objects.');
	vms_test_assert_true(strpos((string) ($result['reason'] ?? ''), '[') === false, 'Decoder case ' . $name . ' reason should not include raw JSON arrays.');
	if (isset($case['reason'])) {
		vms_test_assert_same($case['reason'], $result['reason'] ?? null, 'Decoder case ' . $name . ' should return the expected reason.');
	}
	if (isset($case['signature'])) {
		vms_test_assert_same($case['signature'], $result['signature'] ?? null, 'Decoder case ' . $name . ' should normalize the signature.');
	}
}

$currentContext = array(
	'date_ymd' => '2026-07-22',
	'venue_id' => 17,
	'event_type' => 'concert',
);
$allOn = array(
	'regenerate_on_event_date_change' => 1,
	'regenerate_on_venue_change' => 1,
	'regenerate_on_event_type_change' => 1,
);
$allOff = array(
	'regenerate_on_event_date_change' => 0,
	'regenerate_on_venue_change' => 0,
	'regenerate_on_event_type_change' => 0,
);

$writerJson = bvmgr_tasks_event_signature_json($currentContext);
$roundTrip = vms_test_call_without_warnings(
	static function () use ($writerJson) {
		return bvmgr_tasks_decode_stored_event_signature($writerJson);
	}
);
vms_test_assert_same(array(), $roundTrip['warnings'], 'Writer round-trip should not emit warnings.');
vms_test_assert_same('valid', $roundTrip['result']['state'] ?? null, 'Writer output should decode as valid.');
vms_test_assert_same(bvmgr_tasks_build_event_signature($currentContext), $roundTrip['result']['signature'] ?? null, 'Writer output should round-trip to the current signature shape.');

$decisionCases = array(
	'missing_meta' => array(
		'present' => false,
		'raw' => null,
		'settings' => $allOn,
		'context' => $currentContext,
		'expected' => true,
	),
	'empty_meta' => array(
		'present' => true,
		'raw' => '',
		'settings' => $allOn,
		'context' => $currentContext,
		'expected' => true,
	),
	'whitespace_meta' => array(
		'present' => true,
		'raw' => " \n\t ",
		'settings' => $allOn,
		'context' => $currentContext,
		'expected' => true,
	),
	'valid_unchanged_signature' => array(
		'present' => true,
		'raw' => $writerJson,
		'settings' => $allOn,
		'context' => $currentContext,
		'expected' => false,
	),
	'changed_date_enabled' => array(
		'present' => true,
		'raw' => $writerJson,
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-23', 'venue_id' => 17, 'event_type' => 'concert'),
		'expected' => true,
	),
	'changed_date_disabled' => array(
		'present' => true,
		'raw' => $writerJson,
		'settings' => array_merge($allOn, array('regenerate_on_event_date_change' => 0)),
		'context' => array('date_ymd' => '2026-07-23', 'venue_id' => 17, 'event_type' => 'concert'),
		'expected' => false,
	),
	'changed_venue_enabled' => array(
		'present' => true,
		'raw' => $writerJson,
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-22', 'venue_id' => 18, 'event_type' => 'concert'),
		'expected' => true,
	),
	'changed_venue_disabled' => array(
		'present' => true,
		'raw' => $writerJson,
		'settings' => array_merge($allOn, array('regenerate_on_venue_change' => 0)),
		'context' => array('date_ymd' => '2026-07-22', 'venue_id' => 18, 'event_type' => 'concert'),
		'expected' => false,
	),
	'changed_event_type_enabled' => array(
		'present' => true,
		'raw' => $writerJson,
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-22', 'venue_id' => 17, 'event_type' => 'festival'),
		'expected' => true,
	),
	'changed_event_type_disabled' => array(
		'present' => true,
		'raw' => $writerJson,
		'settings' => array_merge($allOn, array('regenerate_on_event_type_change' => 0)),
		'context' => array('date_ymd' => '2026-07-22', 'venue_id' => 17, 'event_type' => 'festival'),
		'expected' => false,
	),
	'multiple_field_change_existing_settings' => array(
		'present' => true,
		'raw' => $writerJson,
		'settings' => array(
			'regenerate_on_event_date_change' => 0,
			'regenerate_on_venue_change' => 1,
			'regenerate_on_event_type_change' => 0,
		),
		'context' => array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'),
		'expected' => true,
	),
	'malformed_meta_fails_closed' => array(
		'present' => true,
		'raw' => '{"date_ymd":',
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'),
		'expected' => false,
	),
	'list_meta_fails_closed' => array(
		'present' => true,
		'raw' => '[1,2,3]',
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'),
		'expected' => false,
	),
	'scalar_meta_fails_closed' => array(
		'present' => true,
		'raw' => '"hello"',
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'),
		'expected' => false,
	),
	'json_null_meta_fails_closed' => array(
		'present' => true,
		'raw' => 'null',
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'),
		'expected' => false,
	),
	'schema_invalid_meta_fails_closed' => array(
		'present' => true,
		'raw' => '{"date_ymd":"2026-07-22","venue_id":17}',
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'),
		'expected' => false,
	),
	'invalid_utf8_meta_fails_closed' => array(
		'present' => true,
		'raw' => "{\"date_ymd\":\"2026-07-22\",\"venue_id\":17,\"event_type\":\"bad\xB1\x31\"}",
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'),
		'expected' => false,
	),
	'excessive_depth_meta_fails_closed' => array(
		'present' => true,
		'raw' => $excessiveDepth,
		'settings' => $allOn,
		'context' => array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'),
		'expected' => false,
	),
	'all_settings_off_changed_signature' => array(
		'present' => true,
		'raw' => $writerJson,
		'settings' => $allOff,
		'context' => array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'),
		'expected' => false,
	),
);

foreach ($decisionCases as $name => $case) {
	vms_test_set_signature_meta($case['raw'], $case['present']);
	$GLOBALS['vms_test_mutation_calls'] = array();
	$call = vms_test_call_without_warnings(
		static function () use ($case) {
			return bvmgr_tasks_should_allow_supersede(77, $case['context'], $case['settings']);
		}
	);

	vms_test_assert_same(array(), $call['warnings'], 'Decision case ' . $name . ' should not emit warnings.');
	vms_test_assert_same($case['expected'], $call['result'], 'Decision case ' . $name . ' should preserve the expected allow_supersede behavior.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_mutation_calls'], 'Decision case ' . $name . ' should not mutate task state.');
}

vms_test_set_signature_meta($writerJson, true);
$firstSequential = bvmgr_tasks_should_allow_supersede(77, array('date_ymd' => '2026-07-23', 'venue_id' => 17, 'event_type' => 'concert'), $allOn);
vms_test_set_signature_meta('{"date_ymd":', true);
$secondSequential = bvmgr_tasks_should_allow_supersede(77, array('date_ymd' => '2026-07-24', 'venue_id' => 25, 'event_type' => 'festival'), $allOn);
vms_test_set_signature_meta($writerJson, true);
$thirdSequential = bvmgr_tasks_should_allow_supersede(77, $currentContext, $allOn);

vms_test_assert_same(true, $firstSequential, 'Sequential valid changed signature call should allow supersede.');
vms_test_assert_same(false, $secondSequential, 'Sequential invalid signature call should fail closed.');
vms_test_assert_same(false, $thirdSequential, 'Sequential unchanged signature call should not leak prior state.');

fwrite(STDOUT, "staff tasks signature json remediation: PASS\n");
