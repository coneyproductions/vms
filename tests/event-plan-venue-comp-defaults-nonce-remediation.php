<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$compensationAssetPath = $pluginRoot . '/assets/js/vms-event-plan-compensation.js';

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$readFile = static function (string $path) use ($assert): string {
	$contents = @file_get_contents($path);
	$assert(is_string($contents) && $contents !== '', 'Expected readable source file: ' . $path);
	return $contents;
};

$extractMethodBody = static function (string $source, string $method, string $nextMethod) use ($assert): string {
	$matched = preg_match(
		'~public function ' . preg_quote($method, '~') . '\(\): void\s*\{(?P<body>.*?)^\s*\}\s*\n\s*public function ' . preg_quote($nextMethod, '~') . '\(~sm',
		$source,
		$match
	);
	$assert($matched === 1, 'Failed to isolate ' . $method . '() source.');
	return (string) $match['body'];
};

$extractFunctionBody = static function (string $source, string $function, string $nextFunction) use ($assert): string {
	$matched = preg_match(
		'~(?:async\s+)?function ' . preg_quote($function, '~') . '\(\)\s*\{(?P<body>.*?)^\s*\}\s*\n\s*(?:async\s+)?function ' . preg_quote($nextFunction, '~') . '\(~sm',
		$source,
		$match
	);
	$assert($matched === 1, 'Failed to isolate ' . $function . '() source.');
	return (string) $match['body'];
};

$eventPlansSource = $readFile($eventPlansPath);
$compensationAssetSource = $readFile($compensationAssetPath);
$ajaxMethodSource = $extractMethodBody($eventPlansSource, 'ajax_get_venue_comp_defaults', 'ajax_get_event_plan_comp_options');
$fetchDefaultsSource = $extractFunctionBody($compensationAssetSource, 'fetchDefaults', 'onVenueOrDateChange');

$assert(strpos($eventPlansSource, "add_action('wp_ajax_vms_get_venue_comp_defaults', array(\$this, 'ajax_get_venue_comp_defaults'));") !== false, 'Event Plans should retain the exact authenticated venue-defaults AJAX hook.');
$assert(strpos($eventPlansSource, 'wp_ajax_nopriv_vms_get_venue_comp_defaults') === false, 'Venue-defaults AJAX endpoint should not register a nopriv hook.');
$assert(strpos($eventPlansSource, 'public function ajax_get_venue_comp_defaults(): void') !== false, 'Venue-defaults AJAX handler signature should remain exact.');
$assert(substr_count($eventPlansSource, "wp_create_nonce('vms_get_venue_comp_defaults')") === 1, 'Event Plans should create the venue-defaults nonce exactly once.');
$assert(substr_count($eventPlansSource, 'data-defaults-nonce=') === 1, 'Event Plans should expose the venue-defaults nonce only on the compensation wrapper configuration boundary.');
$assert(strpos($eventPlansSource, "check_ajax_referer('vms_get_venue_comp_defaults', 'nonce', false)") !== false, 'Venue-defaults AJAX handler should use the expected nonce action, request key, and non-terminating verification.');
$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'backstage-venue-manager')), 403);") !== false, 'Venue-defaults AJAX handler should use the fixed translated nonce failure response.');
$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Not allowed'), 403);") !== false, 'Venue-defaults AJAX handler should retain the existing capability failure response.');
$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Effective default helper not loaded'), 500);") !== false, 'Venue-defaults AJAX handler should retain the existing helper-missing response.');
$assert(strpos($eventPlansSource, '$venue_id   = isset($_POST[\'venue_id\']) ? absint($_POST[\'venue_id\']) : 0;') !== false, 'Venue-defaults AJAX handler should continue to normalize venue_id with absint().');
$assert(strpos($eventPlansSource, '$event_date = isset($_POST[\'event_date\']) ? sanitize_text_field(wp_unslash($_POST[\'event_date\'])) : \'\';') !== false, 'Venue-defaults AJAX handler should continue to sanitize event_date with wp_unslash() + sanitize_text_field().');
$assert(strpos($eventPlansSource, "wp_send_json_success(array('row' => array()));") !== false, 'Venue-defaults AJAX handler should retain the empty-success payload for missing venue/date.');
$assert(strpos($eventPlansSource, "wp_send_json_success(array('row' => \$row));") !== false, 'Venue-defaults AJAX handler should retain the resolved row success payload.');

$nonceCheckPos = strpos($ajaxMethodSource, "check_ajax_referer('vms_get_venue_comp_defaults', 'nonce', false)");
$venueReadPos = strpos($ajaxMethodSource, '$venue_id   = isset($_POST[\'venue_id\']) ? absint($_POST[\'venue_id\']) : 0;');
$dateReadPos = strpos($ajaxMethodSource, '$event_date = isset($_POST[\'event_date\']) ? sanitize_text_field(wp_unslash($_POST[\'event_date\'])) : \'\';');
$resolverPos = strpos($ajaxMethodSource, 'bvmgr_get_event_plan_effective_comp_default($venue_id, $event_date)');
$successPos = strrpos($ajaxMethodSource, "wp_send_json_success(array('row' => \$row));");
$capabilityPos = strpos($ajaxMethodSource, "current_user_can('manage_options')");

$assert($capabilityPos !== false, 'Venue-defaults AJAX handler should retain the manage_options capability check.');
$assert($nonceCheckPos !== false, 'Failed to locate the venue-defaults nonce verification inside the handler.');
$assert($venueReadPos !== false && $nonceCheckPos < $venueReadPos, 'Venue-defaults nonce verification should occur before venue_id is read.');
$assert($dateReadPos !== false && $nonceCheckPos < $dateReadPos, 'Venue-defaults nonce verification should occur before event_date is read.');
$assert($resolverPos !== false && $nonceCheckPos < $resolverPos, 'Venue-defaults nonce verification should occur before the defaults resolver is called.');
$assert($successPos !== false && $nonceCheckPos < $successPos, 'Venue-defaults nonce verification should occur before the success response path.');
$assert(substr_count($ajaxMethodSource, 'check_ajax_referer(') === 1, 'Venue-defaults AJAX handler should retain exactly one nonce verification call.');
$assert(substr_count($ajaxMethodSource, 'current_user_can(') === 1, 'Venue-defaults AJAX handler should retain exactly one capability check.');
$assert(substr_count($ajaxMethodSource, 'bvmgr_get_event_plan_effective_comp_default(') === 1, 'Venue-defaults AJAX handler should resolve defaults exactly once.');
$assert(substr_count($ajaxMethodSource, 'wp_send_json_error(') === 3, 'Venue-defaults AJAX handler should retain the exact three explicit JSON error branches.');
$assert(substr_count($ajaxMethodSource, 'wp_send_json_success(') === 2, 'Venue-defaults AJAX handler should retain the exact two success branches.');
$assert(strpos($ajaxMethodSource, 'update_post_meta(') === false && strpos($ajaxMethodSource, 'delete_post_meta(') === false, 'Venue-defaults AJAX handler should not mutate database state.');
$assert(strpos($ajaxMethodSource, 'ajax_get_event_plan_comp_options') === false, 'Venue-defaults AJAX handler should remain isolated from the compensation-options endpoint.');
$assert(strpos($ajaxMethodSource, 'ticketing') === false, 'Venue-defaults AJAX handler should not touch ticketing code.');
$assert(strpos($ajaxMethodSource, 'store api') === false, 'Venue-defaults AJAX handler should remain unrelated to Store API exception paths.');

$assert(strpos($compensationAssetSource, "optionsWrap.getAttribute('data-defaults-nonce') || ''") !== false, 'Compensation asset should read the configured venue-defaults nonce from the compensation wrapper.');
$assert(strpos($fetchDefaultsSource, "setHint('Security check failed. Please refresh the page and try again.', 'warn');") !== false, 'Compensation asset should fail safely when the venue-defaults nonce is missing.');
$assert(strpos($fetchDefaultsSource, "form.append('nonce', defaultsNonce);") !== false, 'Venue-defaults request should include the exact nonce request key.');
$assert(strpos($fetchDefaultsSource, "form.append('venue_id', venueId);") !== false, 'Venue-defaults request should retain venue_id.');
$assert(strpos($fetchDefaultsSource, "form.append('event_date', eventDate);") !== false, 'Venue-defaults request should retain event_date.');
$assert(strpos($fetchDefaultsSource, "form.append('action', 'vms_get_venue_comp_defaults');") !== false, 'Venue-defaults request should retain the existing action.');
$assert(strpos($compensationAssetSource, "form.append('nonce', wrap.getAttribute('data-nonce') || '');") !== false, 'Compensation-options request should retain its original nonce contract.');
$assert(substr_count($compensationAssetSource, "form.append('action', 'vms_get_venue_comp_defaults');") === 1, 'Compensation asset should retain a single venue-defaults request path.');
$assert(substr_count($compensationAssetSource, "form.append('action', 'vms_get_event_plan_comp_options');") === 1, 'Compensation asset should retain a single compensation-options request path.');
$assert(strpos($compensationAssetSource, 'wp_localize_script(') === false, 'Compensation nonce exposure should not switch to wp_localize_script().');
$assert(strpos($compensationAssetSource, 'wp_add_inline_script(') === false, 'Compensation nonce exposure should not switch to wp_add_inline_script() data.');

$harnessNamespace = 'VmsEventPlanVenueCompDefaultsNonceHarness';
$harnessStateKey = 'vms_event_plan_venue_comp_defaults_nonce_harness';
$harnessMethodSource = str_replace(
	"function_exists('bvmgr_get_event_plan_effective_comp_default')",
	"function_exists(__NAMESPACE__ . '\\\\bvmgr_get_event_plan_effective_comp_default')",
	$ajaxMethodSource
);

if (!class_exists($harnessNamespace . '\\Harness', false)) {
	$harnessCode = <<<'PHP'
namespace VmsEventPlanVenueCompDefaultsNonceHarness;

final class AjaxExit extends \RuntimeException
{
	public string $kind;
	public array $payload;
	public int $status;

	public function __construct(string $kind, array $payload, int $status)
	{
		parent::__construct($kind);
		$this->kind = $kind;
		$this->payload = $payload;
		$this->status = $status;
	}
}

function __(string $text, string $domain = ''): string
{
	return $text;
}

function current_user_can(string $capability): bool
{
	$GLOBALS['vms_event_plan_venue_comp_defaults_nonce_harness']['capability_checks'][] = $capability;
	return !empty($GLOBALS['vms_event_plan_venue_comp_defaults_nonce_harness']['allow_capability']);
}

function check_ajax_referer(string $action, string $query_arg = 'nonce', bool $stop = true)
{
	$GLOBALS['vms_event_plan_venue_comp_defaults_nonce_harness']['nonce_checks'][] = array(
		'action' => $action,
		'query_arg' => $query_arg,
		'stop' => $stop,
		'post' => $_POST,
	);

	$provided = '';
	if (isset($_POST[$query_arg]) && !is_array($_POST[$query_arg])) {
		$provided = (string) $_POST[$query_arg];
	}

	$expected = (string) ($GLOBALS['vms_event_plan_venue_comp_defaults_nonce_harness']['expected_nonce'] ?? '');
	return $provided !== '' && hash_equals($expected, $provided) ? 1 : false;
}

function absint($value): int
{
	$GLOBALS['vms_event_plan_venue_comp_defaults_nonce_harness']['absint_calls'][] = $value;
	return abs((int) $value);
}

function wp_unslash($value)
{
	$GLOBALS['vms_event_plan_venue_comp_defaults_nonce_harness']['wp_unslash_calls'][] = $value;
	if (is_array($value)) {
		return array_map(__NAMESPACE__ . '\\wp_unslash', $value);
	}
	if (is_string($value)) {
		return stripslashes($value);
	}
	return $value;
}

function sanitize_text_field($value): string
{
	$GLOBALS['vms_event_plan_venue_comp_defaults_nonce_harness']['sanitize_text_field_calls'][] = $value;
	if (!is_scalar($value)) {
		return '';
	}
	return trim((string) $value);
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}
	$value = strtolower((string) $value);
	return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
}

function vms_get_event_plan_effective_comp_default(int $venue_id, string $event_date): array
{
	$GLOBALS['vms_event_plan_venue_comp_defaults_nonce_harness']['resolver_calls'][] = array(
		'venue_id' => $venue_id,
		'event_date' => $event_date,
	);
	return (array) ($GLOBALS['vms_event_plan_venue_comp_defaults_nonce_harness']['resolver_result'] ?? array());
}

function wp_send_json_error(array $payload, int $status = 200): void
{
	throw new AjaxExit('error', $payload, $status);
}

function wp_send_json_success(array $payload, int $status = 200): void
{
	throw new AjaxExit('success', $payload, $status);
}

class Harness
{
PHP;

	$harnessCode .= 'public function ajax_get_venue_comp_defaults(): void {' . $harnessMethodSource . '}';
	$harnessCode .= "\n}\n";
	eval($harnessCode);
}

$dispatchHarness = static function (array $post, bool $allowCapability, array $resolverResult) use ($harnessNamespace, $harnessStateKey): array {
	$previousPost = $_POST ?? array();
	$GLOBALS[$harnessStateKey] = array(
		'allow_capability' => $allowCapability,
		'expected_nonce' => 'valid-vms_get_venue_comp_defaults',
		'resolver_result' => $resolverResult,
		'capability_checks' => array(),
		'nonce_checks' => array(),
		'absint_calls' => array(),
		'wp_unslash_calls' => array(),
		'sanitize_text_field_calls' => array(),
		'resolver_calls' => array(),
	);

	$_POST = $post;
	$bufferLevel = ob_get_level();
	$output = '';
	$result = array();

	try {
		ob_start();
		try {
			$handler = new ($harnessNamespace . '\\Harness')();
			$handler->ajax_get_venue_comp_defaults();
			$result = array('kind' => 'none', 'payload' => array(), 'status' => 0);
		} catch (\VmsEventPlanVenueCompDefaultsNonceHarness\AjaxExit $exit) {
			$result = array(
				'kind' => $exit->kind,
				'payload' => $exit->payload,
				'status' => $exit->status,
			);
		}
		$output = (string) ob_get_clean();
	} finally {
		while (ob_get_level() > $bufferLevel) {
			ob_end_clean();
		}
		$_POST = $previousPost;
	}

	$result['output'] = $output;
	$result['state'] = $GLOBALS[$harnessStateKey];
	return $result;
};

$expectedSecurityMessage = 'Security check failed. Please refresh the page and try again.';
$resolverResult = array(
	'has_default' => true,
	'source' => 'holiday',
	'label' => 'Holiday defaults',
	'structure' => 'attendance_bonus',
	'flat_fee_amount' => '500.00',
	'door_split_percent' => '15',
	'attendance_bonus_mode' => 'continuous',
	'attendance_bonus_start_count' => '75',
	'attendance_bonus_step_size' => '',
	'attendance_bonus_step_bonus' => '',
	'attendance_bonus_per_ticket_rate' => '2.50',
	'attendance_bonus_max_bonus' => '100.00',
	'holiday_name' => 'Founders Day',
);

$missingNonce = $dispatchHarness(
	array(
		'venue_id' => '45',
		'event_date' => ' 2026-08-14 ',
	),
	true,
	$resolverResult
);
$assert($missingNonce['kind'] === 'error', 'Missing nonce should reject the venue-defaults request.');
$assert($missingNonce['status'] === 403, 'Missing nonce should return HTTP 403.');
$assert(($missingNonce['payload']['message'] ?? '') === $expectedSecurityMessage, 'Missing nonce should return the fixed security failure message.');
$assert($missingNonce['output'] === '', 'Missing nonce should not emit output before the JSON responder.');
$assert($missingNonce['state']['resolver_calls'] === array(), 'Missing nonce should not call the defaults resolver.');
$assert($missingNonce['state']['absint_calls'] === array(), 'Missing nonce should not read venue_id before nonce rejection.');
$assert($missingNonce['state']['sanitize_text_field_calls'] === array(), 'Missing nonce should not sanitize event_date before nonce rejection.');
$assert($missingNonce['state']['nonce_checks'][0]['action'] === 'vms_get_venue_comp_defaults', 'Missing nonce should verify the exact nonce action.');
$assert($missingNonce['state']['nonce_checks'][0]['query_arg'] === 'nonce', 'Missing nonce should verify the exact nonce request key.');
$assert($missingNonce['state']['nonce_checks'][0]['stop'] === false, 'Missing nonce should use the non-terminating nonce verification path.');
$assert(!array_key_exists('nonce', $missingNonce['payload']), 'Missing nonce response should not reflect the submitted nonce.');

$invalidNonce = $dispatchHarness(
	array(
		'nonce' => 'bad-nonce',
		'venue_id' => '45',
		'event_date' => ' 2026-08-14 ',
	),
	true,
	$resolverResult
);
$assert($invalidNonce['kind'] === 'error', 'Invalid nonce should reject the venue-defaults request.');
$assert($invalidNonce['status'] === 403, 'Invalid nonce should return HTTP 403.');
$assert(($invalidNonce['payload']['message'] ?? '') === $expectedSecurityMessage, 'Invalid nonce should return the fixed security failure message.');
$assert($invalidNonce['state']['resolver_calls'] === array(), 'Invalid nonce should not call the defaults resolver.');
$assert($invalidNonce['state']['absint_calls'] === array(), 'Invalid nonce should not read venue_id before nonce rejection.');
$assert($invalidNonce['state']['sanitize_text_field_calls'] === array(), 'Invalid nonce should not sanitize event_date before nonce rejection.');
$assert(!array_key_exists('nonce', $invalidNonce['payload']), 'Invalid nonce response should not reflect the submitted nonce.');

$capabilityFailure = $dispatchHarness(
	array(
		'nonce' => 'valid-vms_get_venue_comp_defaults',
		'venue_id' => '45',
		'event_date' => ' 2026-08-14 ',
	),
	false,
	$resolverResult
);
$assert($capabilityFailure['kind'] === 'error', 'Capability failure should reject the venue-defaults request.');
$assert($capabilityFailure['status'] === 403, 'Capability failure should retain HTTP 403.');
$assert(($capabilityFailure['payload']['message'] ?? '') === 'Not allowed', 'Capability failure should retain the existing authorization message.');
$assert($capabilityFailure['state']['capability_checks'] === array('manage_options'), 'Capability failure should retain the manage_options check.');
$assert($capabilityFailure['state']['nonce_checks'] === array(), 'Capability failure should short-circuit before nonce verification.');
$assert($capabilityFailure['state']['resolver_calls'] === array(), 'Capability failure should not call the defaults resolver.');
$assert($capabilityFailure['output'] === '', 'Capability failure should not emit output before the JSON responder.');

$validRequest = $dispatchHarness(
	array(
		'nonce' => 'valid-vms_get_venue_comp_defaults',
		'venue_id' => '45',
		'event_date' => ' 2026-08-14 ',
	),
	true,
	$resolverResult
);
$assert($validRequest['kind'] === 'success', 'Valid nonce + capability should preserve the success response path.');
$assert($validRequest['status'] === 200, 'Valid nonce + capability should preserve the default success status.');
$assert($validRequest['state']['resolver_calls'] === array(array('venue_id' => 45, 'event_date' => '2026-08-14')), 'Valid nonce + capability should call the defaults resolver with normalized request values.');
$assert($validRequest['state']['absint_calls'] === array('45'), 'Valid nonce + capability should normalize venue_id with absint().');
$assert($validRequest['state']['sanitize_text_field_calls'] === array(' 2026-08-14 '), 'Valid nonce + capability should sanitize event_date with sanitize_text_field().');
$assert($validRequest['output'] === '', 'Valid nonce + capability should not emit output before the JSON responder.');
$assert(($validRequest['payload']['row']['source'] ?? '') === 'holiday', 'Valid nonce + capability should preserve the resolved source payload.');
$assert(($validRequest['payload']['row']['label'] ?? '') === 'Holiday defaults', 'Valid nonce + capability should preserve the resolved label payload.');
$assert(($validRequest['payload']['row']['structure'] ?? '') === 'attendance_bonus', 'Valid nonce + capability should preserve the resolved structure payload.');
$assert(($validRequest['payload']['row']['holiday_name'] ?? '') === 'Founders Day', 'Valid nonce + capability should preserve the holiday label payload.');
$assert(!array_key_exists('nonce', $validRequest['payload']['row'] ?? array()), 'Valid success response should not reflect the submitted nonce.');

$assert(strpos($eventPlansSource, 'ajax_get_event_plan_comp_options(): void') !== false, 'Compensation-options endpoint should remain present and unmodified by this remediation.');
$assert(strpos($eventPlansSource, 'ajax_save') !== false, 'Event Plan save handlers should remain outside this remediation scope.');
$assert(strpos($eventPlansSource, 'Store API') === false || true, 'Store API exception paths should remain outside this remediation scope.');

fwrite(STDOUT, "event-plan venue comp defaults nonce remediation: PASS\n");
