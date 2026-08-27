<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
	throw new RuntimeException($message . ' @ ' . $file . ':' . $line, $severity);
});

function vms_t5_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_t5_assert_same($expected, $actual, string $message): void
{
	vms_t5_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function vms_t5_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_t5_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_t5_assert_order(string $first, string $second, string $haystack, string $message): void
{
	$first_pos = strpos($haystack, $first);
	$second_pos = strpos($haystack, $second);
	vms_t5_assert($first_pos !== false && $second_pos !== false && $first_pos < $second_pos, $message);
}

function vms_t5_read_file(string $path): string
{
	$contents = file_get_contents($path);
	if (!is_string($contents) || $contents === '') {
		throw new RuntimeException('Unable to read ' . $path);
	}

	return $contents;
}

function vms_t5_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name . '(';
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Function not found: ' . $name);
	}

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Function opening brace not found: ' . $name);
	}

	$depth = 1;
	$length = strlen($source);
	$in_single = false;
	$in_double = false;
	$in_line_comment = false;
	$in_block_comment = false;

	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		$next = ($i + 1 < $length) ? $source[$i + 1] : '';
		$prev = ($i > 0) ? $source[$i - 1] : '';

		if ($in_line_comment) {
			if ($char === "\n") {
				$in_line_comment = false;
			}
			continue;
		}
		if ($in_block_comment) {
			if ($char === '*' && $next === '/') {
				$in_block_comment = false;
				$i++;
			}
			continue;
		}
		if ($in_single) {
			if ($char === "'" && $prev !== '\\') {
				$in_single = false;
			}
			continue;
		}
		if ($in_double) {
			if ($char === '"' && $prev !== '\\') {
				$in_double = false;
			}
			continue;
		}

		if ($char === '/' && $next === '/') {
			$in_line_comment = true;
			$i++;
			continue;
		}
		if ($char === '/' && $next === '*') {
			$in_block_comment = true;
			$i++;
			continue;
		}
		if ($char === "'") {
			$in_single = true;
			continue;
		}
		if ($char === '"') {
			$in_double = true;
			continue;
		}
		if ($char === '{') {
			$depth++;
			continue;
		}
		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	throw new RuntimeException('Function closing brace not found: ' . $name);
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = stripslashes((string) $value);
	$value = preg_replace('/[\x00-\x1F\x7F]+/', '', $value);
	return is_string($value) ? trim($value) : '';
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[^a-z0-9_-]+/i', '', strtolower((string) $value));
	return is_string($sanitized) ? $sanitized : '';
}

function absint($value): int
{
	return abs((int) $value);
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	return is_string($value) ? stripslashes($value) : $value;
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_t5_is_admin']);
}

function wp_doing_ajax(): bool
{
	return !empty($GLOBALS['vms_t5_doing_ajax']);
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($hook, $callback, $priority, $accepted_args);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($hook, $callback, $priority, $accepted_args);
	return true;
}

$root = dirname(__DIR__);
require $root . '/includes/runtime-guards.php';

$paths = array(
	'event_import' => $root . '/includes/admin/data-tools/actions-event-plan-import.php',
	'phase_b' => $root . '/includes/integrations/ticketing-phase-b.php',
	'rules_v2' => $root . '/includes/integrations/ticketing-rules-v2.php',
	'vendor_guest' => $root . '/includes/modules/admissions/vendor-guest-portal.php',
	'social_admin' => $root . '/includes/social-share/admin.php',
	'vendor_category' => $root . '/includes/taxonomies/vendor-category.php',
	'ticket_integrity' => $root . '/includes/ticketing/ticket-integrity-cron.php',
	'tours_service' => $root . '/includes/tours/class-vms-tours-service.php',
);

$sources = array();
foreach ($paths as $key => $path) {
	$sources[$key] = vms_t5_read_file($path);
}

eval(vms_t5_extract_function($sources['event_import'], 'vms_event_plan_import_read_selected_rows_from_post'));
eval(vms_t5_extract_function($sources['phase_b'], 'vms_ticketing_b_request_payload_value'));
eval(vms_t5_extract_function($sources['rules_v2'], 'vms_ticketing_v2_read_form_request_payload'));
eval(vms_t5_extract_function($sources['vendor_guest'], 'vms_admission_vendor_guest_rules_from_post'));
eval(vms_t5_extract_function($sources['vendor_guest'], 'vms_admission_vendor_guest_flag_value'));
eval(vms_t5_extract_function($sources['vendor_guest'], 'vms_admission_vendor_guest_absint_value'));
eval(vms_t5_extract_function($sources['social_admin'], 'vms_social_template_body_from_post'));
eval(vms_t5_extract_function($sources['vendor_category'], 'vms_vendor_type_category_label_from_post'));
eval(vms_t5_extract_function($sources['ticket_integrity'], 'vms_ticket_integrity_plan_save_request_action'));

vms_t5_assert_same(null, bvmgr_request_read_array(array(), 'rows'), 'Array reader should distinguish missing arrays.');
vms_t5_assert_same(null, bvmgr_request_read_array(array('rows' => '1'), 'rows'), 'Array reader should reject scalar where array is expected.');
vms_t5_assert_same(array('name' => "Band's", 'nested' => array('A\\B')), bvmgr_request_read_array(array('rows' => array('name' => 'Band\\\'s', 'nested' => array('A\\\\B'))), 'rows'), 'Array reader should unslash nested arrays exactly once.');

$_SERVER = array('REQUEST_METHOD' => 'PoSt', 'HTTP_ACCEPT' => ' application/json\\, text/html ', 'UNEXPECTED_KEY' => 'value');
vms_t5_assert_same('PoSt', bvmgr_request_server_value('REQUEST_METHOD'), 'Server helper should preserve allowlisted scalar method values.');
vms_t5_assert_same('application/json, text/html', bvmgr_request_server_value('HTTP_ACCEPT'), 'Server helper should preserve allowlisted diagnostic header values after unslashing.');
vms_t5_assert_same('', bvmgr_request_server_value('UNEXPECTED_KEY'), 'Server helper should reject dynamic keys outside the finite allowlist.');
$_SERVER['REQUEST_METHOD'] = array('POST');
vms_t5_assert_same('', bvmgr_request_server_value('REQUEST_METHOD'), 'Server helper should reject array-shaped allowed keys.');
$_SERVER['REQUEST_METHOD'] = new stdClass();
vms_t5_assert_same('', bvmgr_request_server_value('REQUEST_METHOD'), 'Server helper should reject object-shaped allowed keys.');

$_POST = array();
vms_t5_assert_same(false, bvmgr_request_has_post_data(), 'POST probe should preserve the empty passive-request result.');
$_POST = array('unexpected' => array('nested'));
vms_t5_assert_same(true, bvmgr_request_has_post_data(), 'POST probe should reject nonempty POST traffic without inspecting values.');

vms_t5_assert_same(array(2, 5), vms_event_plan_import_read_selected_rows_from_post(array('selected_rows' => array('2', 'bad', '2', '-3', array('4'), '5'))), 'Selected rows should preserve first positive unique order and skip malformed values.');
vms_t5_assert_same(array(), vms_event_plan_import_read_selected_rows_from_post(array('selected_rows' => '2')), 'Selected rows should reject scalar top-level values.');

$present = null;
$valid = null;
$payload = vms_ticketing_b_request_payload_value(array('tiers' => array(array('tier_key' => 'ga\\\'1'))), 'tiers', $present, $valid);
vms_t5_assert_same(true, $present, 'Payload reader should report present array keys.');
vms_t5_assert_same(true, $valid, 'Payload reader should accept array-shaped payloads.');
vms_t5_assert_same(array(array('tier_key' => "ga'1")), $payload, 'Payload reader should unslash array payloads exactly once.');
$payload = vms_ticketing_b_request_payload_value(array('tiers' => '[{\"tier_key\":\"ga\"}]'), 'tiers', $present, $valid);
vms_t5_assert_same(true, $valid, 'Payload reader should accept scalar JSON payloads.');
vms_t5_assert_same('[{"tier_key":"ga"}]', $payload, 'Payload reader should unslash scalar JSON payloads exactly once.');
$raw_string_bytes = null;
$payload = vms_ticketing_b_request_payload_value(array('config' => '[{\"ticket_key\":\"ga\"}]'), 'config', $present, $valid, $raw_string_bytes);
vms_t5_assert_same(true, $valid, 'Payload reader should accept scalar config payloads.');
vms_t5_assert_same(strlen('[{\"ticket_key\":\"ga\"}]'), $raw_string_bytes, 'Payload reader should preserve pre-unslash raw string byte counts.');
vms_t5_assert_same('[{"ticket_key":"ga"}]', $payload, 'Payload reader should still return unslashed scalar strings.');
$payload = vms_ticketing_b_request_payload_value(array('tiers' => new stdClass()), 'tiers', $present, $valid);
vms_t5_assert_same(true, $present, 'Payload reader should report present malformed keys.');
vms_t5_assert_same(false, $valid, 'Payload reader should reject object payloads.');
vms_t5_assert_same(null, $payload, 'Payload reader should return null for malformed payloads.');

vms_t5_assert_same(array('nonce' => "good'value", 'ticket_lines' => array(array('qty' => '1'))), vms_ticketing_v2_read_form_request_payload(array('nonce' => 'good\\\'value', 'ticket_lines' => array(array('qty' => '1')))), 'Form fallback should unslash whole request arrays once.');
vms_t5_assert_same(array(), vms_ticketing_v2_read_form_request_payload(array()), 'Form fallback should preserve empty POST fallback.');

vms_t5_assert_same(array('enabled' => '1'), vms_admission_vendor_guest_rules_from_post(array('vms_vendor_guest_rules' => array('enabled' => '1'))), 'Vendor Guest rules should accept array-shaped top-level rules.');
vms_t5_assert_same(null, vms_admission_vendor_guest_rules_from_post(array('vms_vendor_guest_rules' => '1')), 'Vendor Guest rules should reject scalar top-level rules.');
vms_t5_assert_same(1, vms_admission_vendor_guest_flag_value('1'), 'Vendor Guest flags should accept scalar truthy values.');
vms_t5_assert_same(0, vms_admission_vendor_guest_flag_value(array('1')), 'Vendor Guest flags should reject nested arrays.');
vms_t5_assert_same(42, vms_admission_vendor_guest_absint_value('42'), 'Vendor Guest allotments should accept unsigned integer strings.');
vms_t5_assert_same(0, vms_admission_vendor_guest_absint_value('-42'), 'Vendor Guest allotments should reject negative strings instead of flipping sign.');
vms_t5_assert_same(0, vms_admission_vendor_guest_absint_value(array('42')), 'Vendor Guest allotments should reject arrays.');

vms_t5_assert_same("Line 1\n{{event_title}}\\n", vms_social_template_body_from_post(array('body' => "Line 1\n{{event_title}}\\\\n")), 'Social template body should preserve text, newlines, and tokens after unslashing.');
vms_t5_assert_same('', vms_social_template_body_from_post(array('body' => array('bad'))), 'Social template body should reject array-shaped values.');
vms_t5_assert_same('Genre Label', vms_vendor_type_category_label_from_post(array('vms_vendor_type_category_label' => ' Genre\\ Label ')), 'Vendor category labels should use scalar text normalization.');
vms_t5_assert_same('', vms_vendor_type_category_label_from_post(array('vms_vendor_type_category_label' => array('bad'))), 'Vendor category labels should reject arrays.');
vms_t5_assert_same('publish_now', vms_ticket_integrity_plan_save_request_action(array('vms_event_plan_action' => 'Publish_Now')), 'Ticket Integrity action should preserve key normalization.');
vms_t5_assert_same('', vms_ticket_integrity_plan_save_request_action(array('vms_event_plan_action' => array('publish_now'))), 'Ticket Integrity action should reject arrays.');

require $paths['tours_service'];
$tour_reflection = new ReflectionClass('BVMGR_Tours_Service');
$tour_service = $tour_reflection->newInstanceWithoutConstructor();
$prefs_method = $tour_reflection->getMethod('read_ajax_prefs_from_request');
vms_t5_assert_same(array(), $prefs_method->invoke($tour_service, array('prefs' => 'bad')), 'Tours prefs reader should reject scalar top-level prefs.');
vms_t5_assert_same(array('level' => 'advanced', 'dismissed_tours' => array('intro' => '1')), $prefs_method->invoke($tour_service, array('prefs' => array('level' => 'advanced', 'dismissed_tours' => array('intro' => '1')))), 'Tours prefs reader should unslash and preserve array-shaped prefs.');

vms_t5_assert_order("check_admin_referer('vms_event_plan_import_commit');", 'vms_event_plan_import_read_selected_rows_from_post($_POST)', $sources['event_import'], 'Event Plan Import should keep nonce verification before selected-row reads.');
vms_t5_assert_order("check_ajax_referer('vms_ticketing_nonce', 'nonce', false)", 'vms_ticketing_b_request_payload_value($_POST, \'tiers\'', $sources['phase_b'], 'Phase-B tiers should keep nonce verification before payload reads.');
vms_t5_assert_order("check_ajax_referer('vms_ticketing_nonce', 'nonce', false)", 'vms_ticketing_b_request_payload_value($_POST, \'config\'', $sources['phase_b'], 'Ticketing V2 config should keep nonce verification before payload reads.');
vms_t5_assert_order("check_ajax_referer('vms_tours', 'nonce');", '$this->read_ajax_prefs_from_request($_POST)', $sources['tours_service'], 'Tours prefs should keep nonce verification before prefs reads.');
vms_t5_assert_contains('vms_ticketing_v2_read_form_request_payload($_POST)', $sources['rules_v2'], 'Ticketing Rules V2 should route form fallback through the normalized form-payload helper.');
vms_t5_assert_contains('bvmgr_request_has_post_data()', $sources['event_import'] . $sources['phase_b'] . $sources['rules_v2'] . $sources['vendor_guest'] . $sources['social_admin'] . $sources['vendor_category'] . $sources['ticket_integrity'] . $sources['tours_service'] . vms_t5_read_file($root . '/includes/runtime-guards.php'), 'Runtime guard passive POST probes should route through the helper.');

fwrite(STDOUT, "WPORG-28R-G6-T5 mutation request boundaries: PASS\n");
