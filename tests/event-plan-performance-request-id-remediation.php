<?php
declare(strict_types=1);

function vms_test_event_plan_perf_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_event_plan_perf_assert_same($expected, $actual, string $message): void
{
	vms_test_event_plan_perf_assert(
		$expected === $actual,
		$message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
	);
}

function vms_test_event_plan_perf_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_event_plan_perf_assert(
		strpos($haystack, $needle) !== false,
		$message . ' Missing substring: ' . $needle
	);
}

function vms_test_event_plan_perf_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_event_plan_perf_assert(
		strpos($haystack, $needle) === false,
		$message . ' Unexpected substring: ' . $needle
	);
}

function vms_test_event_plan_perf_find_matching_brace(string $code, int $openBracePos): int
{
	$depth  = 0;
	$length = strlen($code);
	for ($index = $openBracePos; $index < $length; $index++) {
		$char = $code[$index];
		if ($char === '{') {
			$depth++;
			continue;
		}

		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return $index;
			}
		}
	}

	throw new RuntimeException('Matching brace not found.');
}

function vms_test_event_plan_perf_extract_named_function(string $path, string $functionName): string
{
	$code        = (string) file_get_contents($path);
	$marker      = 'function ' . $functionName . '(';
	$functionPos = strpos($code, $marker);
	if ($functionPos === false) {
		throw new RuntimeException('Function not found: ' . $functionName);
	}

	$bracePos = strpos($code, '{', $functionPos);
	if ($bracePos === false) {
		throw new RuntimeException('Function brace not found: ' . $functionName);
	}

	$endPos = vms_test_event_plan_perf_find_matching_brace($code, $bracePos);
	return substr($code, $functionPos, $endPos - $functionPos + 1);
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

function vms_request_server_value(string $key): string
{
	if (!isset($_SERVER[$key]) || !is_scalar($_SERVER[$key])) {
		return '';
	}

	$value = wp_unslash($_SERVER[$key]);
	if (!is_scalar($value)) {
		return '';
	}

	return trim((string) $value);
}

function wp_rand(int $min = 0, int $max = 0): int
{
	$GLOBALS['vms_test_event_plan_perf_wp_rand_calls'][] = array($min, $max);
	return (int) ($GLOBALS['vms_test_event_plan_perf_wp_rand_value'] ?? 12345);
}

function vms_request_current_uri(string $fallback = ''): string
{
	$uri = $GLOBALS['vms_test_event_plan_perf_request_uri'] ?? '';
	if (!is_string($uri) || $uri === '') {
		return $fallback;
	}

	return $uri;
}

function vms_test_event_plan_perf_microtime(): float
{
	return (float) ($GLOBALS['vms_test_event_plan_perf_microtime_value'] ?? 1000.25);
}

function vms_test_event_plan_perf_expected_id(string $seed): string
{
	$seedParts = array(
		'1000.25',
		'12345',
		$seed,
		'/wp-admin/post.php?post=42&action=edit',
	);

	return substr(hash('sha256', implode('|', $seedParts)), 0, 12);
}

function vms_test_event_plan_perf_instrument_request_id(string $functionSource, string $functionName): string
{
	$instrumented = preg_replace(
		'/function\s+vms_event_plan_perf_request_id\s*\(/',
		'function ' . $functionName . '(',
		$functionSource,
		1
	);
	if (!is_string($instrumented) || $instrumented === '') {
		throw new RuntimeException('Unable to rename the request ID function for testing.');
	}

	$instrumented = str_replace(
		'(string) microtime(true)',
		'(string) vms_test_event_plan_perf_microtime()',
		$instrumented
	);
	$instrumented = str_replace(
		'wp_rand(1000, 999999)',
		'wp_rand(1000, 999999)',
		$instrumented
	);

	return $instrumented;
}

function vms_test_event_plan_perf_run_case(string $functionSource, string $label, bool $setKey, $value): array
{
	$_SERVER = array();
	if ($setKey) {
		$_SERVER['REQUEST_TIME_FLOAT'] = $value;
	}

	$GLOBALS['vms_test_event_plan_perf_request_uri']    = '/wp-admin/post.php?post=42&action=edit';
	$GLOBALS['vms_test_event_plan_perf_microtime_value'] = 1000.25;
	$GLOBALS['vms_test_event_plan_perf_wp_rand_value']   = 12345;
	$GLOBALS['vms_test_event_plan_perf_wp_rand_calls']   = array();

	$functionName = 'vms_test_event_plan_perf_request_id_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($label));
	$instrumented = vms_test_event_plan_perf_instrument_request_id($functionSource, $functionName);
	eval($instrumented);

	$warnings = array();
	set_error_handler(
		static function (int $severity, string $message) use (&$warnings): bool {
			$warnings[] = array(
				'severity' => $severity,
				'message'  => $message,
			);
			return true;
		}
	);

	try {
		$expectedSeed = vms_request_server_value('REQUEST_TIME_FLOAT');
		$first        = $functionName();

		$GLOBALS['vms_test_event_plan_perf_request_uri']     = '/changed/request';
		$GLOBALS['vms_test_event_plan_perf_microtime_value'] = 2000.5;
		$GLOBALS['vms_test_event_plan_perf_wp_rand_value']   = 999999;
		$_SERVER['REQUEST_TIME_FLOAT']                       = 'changed-seed';
		$second                                             = $functionName();
	} finally {
		restore_error_handler();
	}

	return array(
		'first'         => $first,
		'second'        => $second,
		'expected_seed' => $expectedSeed,
		'warnings'      => $warnings,
		'rand_calls'    => $GLOBALS['vms_test_event_plan_perf_wp_rand_calls'],
	);
}

final class VmsTestEventPlanPerfStringableValue
{
	public function __toString(): string
	{
		return '123.75';
	}
}

final class VmsTestEventPlanPerfNonStringableValue
{
}

$pluginRoot     = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$mirrorPath        = $pluginRoot . '/includes/core/event-plan-performance.php';
$livePath          = $livePluginRoot . '/includes/core/event-plan-performance.php';
$loggerPath        = $pluginRoot . '/includes/core/slow-request-logger.php';
$runtimeGuardsPath = $pluginRoot . '/includes/runtime-guards.php';
$ticketingPath     = $pluginRoot . '/includes/integrations/ticketing-phase-b.php';

$mirrorSource = (string) file_get_contents($mirrorPath);
$liveSource   = (string) file_get_contents($livePath);
$loggerSource = (string) file_get_contents($loggerPath);
$guardsSource = (string) file_get_contents($runtimeGuardsPath);
$ticketSource = (string) file_get_contents($ticketingPath);

vms_test_event_plan_perf_assert($mirrorSource !== '', 'Mirror Event Plan performance source should be readable.');
vms_test_event_plan_perf_assert($liveSource !== '', 'Live Event Plan performance source should be readable.');
vms_test_event_plan_perf_assert($loggerSource !== '', 'Logger source should be readable.');
vms_test_event_plan_perf_assert($guardsSource !== '', 'Runtime Guards source should be readable.');
vms_test_event_plan_perf_assert($ticketSource !== '', 'Ticketing source should be readable.');

vms_test_event_plan_perf_assert_same($mirrorSource, $liveSource, 'Mirror/live Event Plan performance files should remain byte-identical.');

$requestIdSource = vms_test_event_plan_perf_extract_named_function($mirrorPath, 'vms_event_plan_perf_request_id');
vms_test_event_plan_perf_assert_contains('function vms_event_plan_perf_request_id(): string', $requestIdSource, 'Request ID function should exist.');
vms_test_event_plan_perf_assert_contains('static $request_id = \'\';', $requestIdSource, 'Request ID function should preserve static caching.');
vms_test_event_plan_perf_assert_contains('(string) microtime(true)', $requestIdSource, 'Request ID function should preserve the microtime seed.');
vms_test_event_plan_perf_assert_contains('(string) wp_rand(1000, 999999)', $requestIdSource, 'Request ID function should preserve the wp_rand seed.');
vms_test_event_plan_perf_assert_contains("vms_request_server_value('REQUEST_TIME_FLOAT')", $requestIdSource, 'Request ID function should use the helper-backed request-time seed.');
vms_test_event_plan_perf_assert_contains('vms_request_current_uri()', $requestIdSource, 'Request ID function should preserve the current URI seed.');
vms_test_event_plan_perf_assert_contains("substr(hash('sha256', implode('|', \$seed)), 0, 12)", $requestIdSource, 'Request ID function should preserve the hash and truncation algorithm.');
vms_test_event_plan_perf_assert_not_contains("\$_SERVER['REQUEST_TIME_FLOAT']", $requestIdSource, 'Request ID function should not retain a direct request-time server read.');
vms_test_event_plan_perf_assert_not_contains("isset(\$_SERVER['REQUEST_TIME_FLOAT'])", $mirrorSource, 'Mirror Event Plan performance source should no longer contain a direct request-time server read.');
vms_test_event_plan_perf_assert_not_contains("isset(\$_SERVER['REQUEST_TIME_FLOAT'])", $liveSource, 'Live Event Plan performance source should no longer contain a direct request-time server read.');
vms_test_event_plan_perf_assert(substr_count($mirrorSource, "vms_request_server_value('REQUEST_TIME_FLOAT')") === 1, 'Mirror Event Plan performance source should contain one helper-backed request-time seed.');
vms_test_event_plan_perf_assert(substr_count($liveSource, "vms_request_server_value('REQUEST_TIME_FLOAT')") === 1, 'Live Event Plan performance source should contain one helper-backed request-time seed.');

vms_test_event_plan_perf_assert(substr_count($mirrorSource, "'request_id' => vms_event_plan_perf_request_id()") === 2, 'Event Plan performance should preserve the two downstream request_id payload assignments.');
vms_test_event_plan_perf_assert_contains("'request_id' => vms_event_plan_perf_request_id(),", $mirrorSource, 'Trace logging should still receive the derived request ID.');
vms_test_event_plan_perf_assert_contains("'state' => sanitize_key(\$state),", $mirrorSource, 'Transient lock payload should still preserve the state key.');
vms_test_event_plan_perf_assert_contains("'updated_at_gmt' => gmdate('Y-m-d H:i:s'),", $mirrorSource, 'Transient lock payload should still preserve the update timestamp key.');

vms_test_event_plan_perf_assert_contains("isset(\$_SERVER['REQUEST_TIME_FLOAT']) ? (float) \$_SERVER['REQUEST_TIME_FLOAT'] : microtime(true)", $loggerSource, 'Logger should remain unchanged by this Event Plan request-ID test.');
vms_test_event_plan_perf_assert_contains("isset(\$_SERVER['REQUEST_TIME_FLOAT']) ? (float) \$_SERVER['REQUEST_TIME_FLOAT'] : microtime(true)", $guardsSource, 'Runtime Guards should remain unchanged by this Event Plan request-ID test.');
vms_test_event_plan_perf_assert(substr_count($ticketSource, "isset(\$_SERVER['REQUEST_TIME_FLOAT']) ? (float) \$_SERVER['REQUEST_TIME_FLOAT'] : 0.0") === 2, 'Ticketing Phase B should retain its two direct request-age timing reads in this slice.');

$cases = array(
	'missing'              => array('set' => false, 'value' => null),
	'numeric_string'       => array('set' => true, 'value' => '123.456789'),
	'numeric_string_again' => array('set' => true, 'value' => '123.456789'),
	'integer_scalar'       => array('set' => true, 'value' => 123),
	'empty_string'         => array('set' => true, 'value' => ''),
	'whitespace_string'    => array('set' => true, 'value' => '   '),
	'zero'                 => array('set' => true, 'value' => '0'),
	'negative'             => array('set' => true, 'value' => '-42.5'),
	'very_large'           => array('set' => true, 'value' => '9999999999999'),
	'non_numeric'          => array('set' => true, 'value' => 'banana'),
	'array'                => array('set' => true, 'value' => array('bad')),
	'stringable_object'    => array('set' => true, 'value' => new VmsTestEventPlanPerfStringableValue()),
	'non_stringable'       => array('set' => true, 'value' => new VmsTestEventPlanPerfNonStringableValue()),
	'resource'             => array('set' => true, 'value' => fopen('php://memory', 'r')),
);

$results = array();
foreach ($cases as $label => $case) {
	$results[$label] = vms_test_event_plan_perf_run_case($requestIdSource, $label, (bool) $case['set'], $case['value']);
	$expectedId      = vms_test_event_plan_perf_expected_id($results[$label]['expected_seed']);

	vms_test_event_plan_perf_assert_same($expectedId, $results[$label]['first'], $label . ' should preserve the exact derived request ID.');
	vms_test_event_plan_perf_assert_same($results[$label]['first'], $results[$label]['second'], $label . ' should preserve static request-local caching across later input changes.');
	vms_test_event_plan_perf_assert(
		strlen($results[$label]['first']) === 12 && ctype_xdigit($results[$label]['first']),
		$label . ' should preserve the 12-character hexadecimal request ID format.'
	);
	vms_test_event_plan_perf_assert_same(array(array(1000, 999999)), $results[$label]['rand_calls'], $label . ' should preserve one seeded wp_rand() call before static caching.');
	vms_test_event_plan_perf_assert_same(array(), $results[$label]['warnings'], $label . ' should not emit warnings or notices.');
}

if (is_resource($cases['resource']['value'])) {
	fclose($cases['resource']['value']);
}

vms_test_event_plan_perf_assert_same('123.456789', $results['numeric_string']['expected_seed'], 'Ordinary numeric strings should remain part of the request-ID seed.');
vms_test_event_plan_perf_assert_same('123', $results['integer_scalar']['expected_seed'], 'Integer scalars should remain part of the request-ID seed as strings.');
vms_test_event_plan_perf_assert_same('', $results['missing']['expected_seed'], 'Missing request-time input should fail closed to an empty seed.');
vms_test_event_plan_perf_assert_same('', $results['empty_string']['expected_seed'], 'Empty-string request-time input should fail closed to an empty seed.');
vms_test_event_plan_perf_assert_same('', $results['whitespace_string']['expected_seed'], 'Whitespace-only request-time input should fail closed to an empty seed.');
vms_test_event_plan_perf_assert_same('0', $results['zero']['expected_seed'], 'Zero should remain a valid scalar seed.');
vms_test_event_plan_perf_assert_same('-42.5', $results['negative']['expected_seed'], 'Negative numeric strings should remain scalar seeds.');
vms_test_event_plan_perf_assert_same('9999999999999', $results['very_large']['expected_seed'], 'Very large numeric strings should remain scalar seeds.');
vms_test_event_plan_perf_assert_same('banana', $results['non_numeric']['expected_seed'], 'Non-numeric strings should remain scalar seeds.');
vms_test_event_plan_perf_assert_same('', $results['array']['expected_seed'], 'Arrays should fail closed to an empty seed.');
vms_test_event_plan_perf_assert_same('', $results['stringable_object']['expected_seed'], 'Stringable objects should fail closed to an empty seed.');
vms_test_event_plan_perf_assert_same('', $results['non_stringable']['expected_seed'], 'Non-stringable objects should fail closed to an empty seed.');
vms_test_event_plan_perf_assert_same('', $results['resource']['expected_seed'], 'Resources should fail closed to an empty seed.');

vms_test_event_plan_perf_assert_same($results['numeric_string']['first'], $results['numeric_string_again']['first'], 'Identical scalar seeds should remain deterministic under identical surrounding inputs.');
vms_test_event_plan_perf_assert($results['numeric_string']['first'] !== $results['integer_scalar']['first'], 'Different valid scalar seeds should still affect the derived request ID.');
vms_test_event_plan_perf_assert($results['numeric_string']['first'] !== $results['zero']['first'], 'Different valid scalar seeds should still affect the derived request ID.');
vms_test_event_plan_perf_assert($results['non_numeric']['first'] !== $results['missing']['first'], 'Non-empty scalar seeds should remain distinct from the empty fallback seed.');
vms_test_event_plan_perf_assert_same($results['missing']['first'], $results['empty_string']['first'], 'Missing and empty-string seeds should collapse to the same derived ID.');
vms_test_event_plan_perf_assert_same($results['missing']['first'], $results['whitespace_string']['first'], 'Whitespace-only seeds should collapse to the same derived ID.');
vms_test_event_plan_perf_assert_same($results['missing']['first'], $results['array']['first'], 'Array seeds should collapse to the same derived ID as the empty helper fallback.');
vms_test_event_plan_perf_assert_same($results['missing']['first'], $results['stringable_object']['first'], 'Stringable object seeds should collapse to the same derived ID as the empty helper fallback.');
vms_test_event_plan_perf_assert_same($results['missing']['first'], $results['non_stringable']['first'], 'Non-stringable object seeds should collapse to the same derived ID as the empty helper fallback.');
vms_test_event_plan_perf_assert_same($results['missing']['first'], $results['resource']['first'], 'Resource seeds should collapse to the same derived ID as the empty helper fallback.');

fwrite(STDOUT, "Event Plan performance request ID remediation OK.\n");
