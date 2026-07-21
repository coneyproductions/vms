<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
	throw new RuntimeException($message . ' @ ' . $file . ':' . $line, $severity);
});

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = stripslashes((string) $value);
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $value);
	$value = str_replace(array("\r", "\n", "\t"), ' ', (string) $value);
	return trim((string) $value);
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

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name . '(';
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Could not find function ' . $name . '.');
	}

	$braceStart = strpos($source, '{', $start);
	if ($braceStart === false) {
		throw new RuntimeException('Could not find function body for ' . $name . '.');
	}

	$depth = 0;
	$length = strlen($source);
	for ($index = $braceStart; $index < $length; $index++) {
		$char = $source[$index];
		if ($char === '{') {
			$depth++;
		} elseif ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, $index - $start + 1);
			}
		}
	}

	throw new RuntimeException('Could not isolate function ' . $name . '.');
}

function vms_test_assert_order(string $source, array $needles, string $message): void
{
	$offset = -1;
	foreach ($needles as $needle) {
		$position = strpos($source, $needle);
		if ($position === false) {
			throw new RuntimeException('Missing expected source fragment: ' . $needle);
		}
		if ($position <= $offset) {
			throw new RuntimeException($message);
		}
		$offset = $position;
	}
}

final class VmsTestStringablePostMethod
{
	public function __toString(): string
	{
		return 'PoSt';
	}
}

final class VmsTestNonStringablePostMethod
{
}

$pluginRoot = dirname(__DIR__);
$seasonSource = (string) file_get_contents($pluginRoot . '/includes/admin/season-dates.php');
$tasksSource = (string) file_get_contents($pluginRoot . '/includes/modules/staff-tasks/admin-ui.php');
$portalSource = (string) file_get_contents($pluginRoot . '/includes/portal/staff-portal.php');

// Vendor Tax Profile remains mirror-only and deferred in this slice; no mirror/live cmp is required here.
$vendorTaxSource = (string) file_get_contents($pluginRoot . '/includes/portal/vendor-tax-profile.php');

$seasonHelperSource = vms_test_extract_function($seasonSource, 'vms_sd_is_exact_post_request');
$tasksHelperSource = vms_test_extract_function($tasksSource, 'vms_tasks_admin_is_exact_post_request');
$portalHelperSource = vms_test_extract_function($portalSource, 'vms_staff_portal_is_exact_post_request');

vms_test_assert(substr_count($seasonSource, 'function vms_sd_is_exact_post_request(') === 1, 'Season Dates should define exactly one exact POST helper.');
vms_test_assert(substr_count($tasksSource, 'function vms_tasks_admin_is_exact_post_request(') === 1, 'Staff Tasks should define exactly one exact POST helper.');
vms_test_assert(substr_count($portalSource, 'function vms_staff_portal_is_exact_post_request(') === 1, 'Staff Portal should define exactly one exact POST helper.');

vms_test_assert(substr_count($seasonSource, '$_SERVER[\'REQUEST_METHOD\']') === 1, 'Season Dates should retain only one guarded REQUEST_METHOD read.');
vms_test_assert(substr_count($tasksSource, '$_SERVER[\'REQUEST_METHOD\']') === 1, 'Staff Tasks should retain only one guarded REQUEST_METHOD read.');
vms_test_assert(substr_count($portalSource, '$_SERVER[\'REQUEST_METHOD\']') === 1, 'Staff Portal should retain only one guarded REQUEST_METHOD read.');

vms_test_assert(strpos($seasonSource, "if ((\$_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;") === false, 'Season Dates should no longer use the direct REQUEST_METHOD gate.');
vms_test_assert(strpos($seasonSource, 'if (!vms_sd_is_exact_post_request()) return;') !== false, 'Season Dates should gate through the exact POST helper.');

vms_test_assert(strpos($tasksSource, "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['vms_tasks_template_action'])) {") === false, 'Staff Tasks template gate should no longer read REQUEST_METHOD directly.');
vms_test_assert(strpos($tasksSource, "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['vms_tasks_checklist_action'])) {") === false, 'Staff Tasks checklist gate should no longer read REQUEST_METHOD directly.');
vms_test_assert(strpos($tasksSource, "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['vms_tasks_settings_action'])) {") === false, 'Staff Tasks settings gate should no longer read REQUEST_METHOD directly.');
vms_test_assert(substr_count($tasksSource, "if (vms_tasks_admin_is_exact_post_request() && isset(\$_POST['vms_tasks_template_action'])) {") === 1, 'Staff Tasks template gate should use the exact POST helper.');
vms_test_assert(substr_count($tasksSource, "if (vms_tasks_admin_is_exact_post_request() && isset(\$_POST['vms_tasks_checklist_action'])) {") === 1, 'Staff Tasks checklist gate should use the exact POST helper.');
vms_test_assert(substr_count($tasksSource, "if (vms_tasks_admin_is_exact_post_request() && isset(\$_POST['vms_tasks_settings_action'])) {") === 1, 'Staff Tasks settings gate should use the exact POST helper.');

vms_test_assert(strpos($portalSource, "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['vms_employee_packet_ack'])) {") === false, 'Staff Portal employee packet gate should no longer read REQUEST_METHOD directly.');
vms_test_assert(strpos($portalSource, "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['vms_staff_tax_save'])) {") === false, 'Staff Portal tax-profile gate should no longer read REQUEST_METHOD directly.');
vms_test_assert(strpos($portalSource, "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {") === false, 'Staff Portal availability gate should no longer read REQUEST_METHOD directly.');
vms_test_assert(substr_count($portalSource, "if (vms_staff_portal_is_exact_post_request() && isset(\$_POST['vms_employee_packet_ack'])) {") === 1, 'Staff Portal employee packet gate should use the exact POST helper.');
vms_test_assert(substr_count($portalSource, "if (vms_staff_portal_is_exact_post_request() && isset(\$_POST['vms_staff_tax_save'])) {") === 1, 'Staff Portal tax-profile gate should use the exact POST helper.');
vms_test_assert(substr_count($portalSource, 'if (vms_staff_portal_is_exact_post_request()) {') === 1, 'Staff Portal availability gate should use the exact POST helper.');

vms_test_assert(strpos($seasonSource, "vms_request_method() === 'post'") === false, 'Season Dates should not use vms_request_method() for strict POST gating.');
vms_test_assert(strpos($tasksSource, "vms_request_method() === 'post'") === false, 'Staff Tasks should not use vms_request_method() for strict POST gating.');
vms_test_assert(strpos($portalSource, "vms_request_method() === 'post'") === false, 'Staff Portal should not use vms_request_method() for strict POST gating.');
vms_test_assert(strpos($seasonSource, "vms_request_server_value('REQUEST_METHOD')") === false, 'Season Dates should not use vms_request_server_value() for strict POST gating.');
vms_test_assert(strpos($tasksSource, "vms_request_server_value('REQUEST_METHOD')") === false, 'Staff Tasks should not use vms_request_server_value() for strict POST gating.');
vms_test_assert(strpos($portalSource, "vms_request_server_value('REQUEST_METHOD')") === false, 'Staff Portal should not use vms_request_server_value() for strict POST gating.');

$seasonWithoutHelper = str_replace($seasonHelperSource, '', $seasonSource);
$tasksWithoutHelper = str_replace($tasksHelperSource, '', $tasksSource);
$portalWithoutHelper = str_replace($portalHelperSource, '', $portalSource);

vms_test_assert(strpos($seasonWithoutHelper, '$_SERVER[\'REQUEST_METHOD\']') === false, 'Season Dates should not read REQUEST_METHOD outside its local helper.');
vms_test_assert(strpos($tasksWithoutHelper, '$_SERVER[\'REQUEST_METHOD\']') === false, 'Staff Tasks should not read REQUEST_METHOD outside its local helper.');
vms_test_assert(strpos($portalWithoutHelper, '$_SERVER[\'REQUEST_METHOD\']') === false, 'Staff Portal should not read REQUEST_METHOD outside its local helper.');

vms_test_assert_order(
	$seasonSource,
	array(
		'if (!vms_sd_is_exact_post_request()) return;',
		'if (empty($_POST[\'vms_season_dates_nonce\']) || empty($_POST[\'vms_action\'])) return;',
		'$page_slug = isset($_GET[\'page\']) ? sanitize_key((string)$_GET[\'page\']) : \'\';',
		'$cap = apply_filters(\'vms_admin_capability\', \'manage_options\');',
		'if (!current_user_can($cap)) return;',
	),
	'Season Dates method, page, and capability ordering changed unexpectedly.'
);
vms_test_assert(strpos($seasonSource, 'if (!$venue_id || !wp_verify_nonce($nonce, \'vms_season_dates_\' . $venue_id)) {') !== false, 'Season Dates nonce verification should remain after the exact POST gate.');

vms_test_assert_order(
	$tasksSource,
	array(
		'if (!vms_tasks_current_user_can_manage_templates()) {',
		'if (vms_tasks_admin_is_exact_post_request() && isset($_POST[\'vms_tasks_template_action\'])) {',
		'check_admin_referer(\'vms_tasks_save_template\');',
		'$action = sanitize_key((string) wp_unslash($_POST[\'vms_tasks_template_action\']));',
	),
	'Staff Tasks template capability, method, and nonce ordering changed unexpectedly.'
);
vms_test_assert_order(
	$tasksSource,
	array(
		'if (!vms_tasks_current_user_can_manage_checklists()) {',
		'if (vms_tasks_admin_is_exact_post_request() && isset($_POST[\'vms_tasks_checklist_action\'])) {',
		'check_admin_referer(\'vms_tasks_save_checklist\');',
		'$action = sanitize_key((string) wp_unslash($_POST[\'vms_tasks_checklist_action\']));',
	),
	'Staff Tasks checklist capability, method, and nonce ordering changed unexpectedly.'
);
vms_test_assert_order(
	$tasksSource,
	array(
		'if (!vms_tasks_current_user_can_manage_all()) {',
		'if (vms_tasks_admin_is_exact_post_request() && isset($_POST[\'vms_tasks_settings_action\'])) {',
		'check_admin_referer(\'vms_tasks_save_settings\');',
		'$input = array(',
	),
	'Staff Tasks settings capability, method, and nonce ordering changed unexpectedly.'
);

vms_test_assert(strpos($portalSource, 'if (!is_user_logged_in()) {') !== false, 'Staff Portal should still require login.');
vms_test_assert(strpos($portalSource, '$staff_id = (int) get_user_meta($user_id, \'_vms_staff_id\', true);') !== false, 'Staff Portal should still resolve ownership through _vms_staff_id.');
vms_test_assert_order(
	$portalSource,
	array(
		'if (vms_staff_portal_is_exact_post_request() && isset($_POST[\'vms_employee_packet_ack\'])) {',
		'if ($nonce === \'\' || !wp_verify_nonce($nonce, \'vms_employee_packet_ack\')) {',
		'update_post_meta($staff_id, \'_vms_employee_packet_attested_at\', $now);',
	),
	'Staff Portal employee packet method, nonce, and mutation ordering changed unexpectedly.'
);
vms_test_assert_order(
	$portalSource,
	array(
		'if (vms_staff_portal_is_exact_post_request() && isset($_POST[\'vms_staff_tax_save\'])) {',
		'if ($nonce === \'\' || !wp_verify_nonce($nonce, \'vms_staff_tax_save\')) {',
		'update_post_meta($staff_id, \'_vms_payee_legal_name\', $payee_legal);',
	),
	'Staff Portal tax-profile method, nonce, and mutation ordering changed unexpectedly.'
);
vms_test_assert_order(
	$portalSource,
	array(
		'if (vms_staff_portal_is_exact_post_request()) {',
		'if (isset($_POST[\'vms_save_staff_ics_settings\'])) {',
		'if ($nonce === \'\' || !wp_verify_nonce($nonce, \'vms_staff_ics_settings\')) {',
		'update_post_meta($staff_id, \'_vms_ics_url\', $new_url);',
	),
	'Staff Portal ICS settings method, nonce, and mutation ordering changed unexpectedly.'
);
vms_test_assert_order(
	$portalSource,
	array(
		'if (isset($_POST[\'vms_save_staff_pattern\'])) {',
		'if ($nonce === \'\' || !wp_verify_nonce($nonce, \'vms_staff_pattern_settings\')) {',
		'update_post_meta($staff_id, \'_vms_pattern_enabled\', $enabled);',
	),
	'Staff Portal pattern settings nonce and mutation ordering changed unexpectedly.'
);
vms_test_assert_order(
	$portalSource,
	array(
		'$has_manual_submission = isset($_POST[\'vms_staff_save_availability\'])',
		'if ($has_manual_submission) {',
		'if ($nonce === \'\' || !wp_verify_nonce($nonce, \'vms_staff_save_availability\')) {',
		'update_post_meta($staff_id, \'_vms_availability_manual\', $clean);',
	),
	'Staff Portal manual availability nonce and mutation ordering changed unexpectedly.'
);

eval($seasonHelperSource);
eval($tasksHelperSource);
eval($portalHelperSource);

$resource = fopen('php://temp', 'r');
if (!is_resource($resource)) {
	throw new RuntimeException('Could not create test resource.');
}

$cases = array(
	array('label' => 'missing', 'server' => array(), 'expected' => false),
	array('label' => 'POST', 'server' => array('REQUEST_METHOD' => 'POST'), 'expected' => true),
	array('label' => 'post', 'server' => array('REQUEST_METHOD' => 'post'), 'expected' => false),
	array('label' => 'PoSt', 'server' => array('REQUEST_METHOD' => 'PoSt'), 'expected' => false),
	array('label' => ' POST ', 'server' => array('REQUEST_METHOD' => ' POST '), 'expected' => false),
	array('label' => 'GET', 'server' => array('REQUEST_METHOD' => 'GET'), 'expected' => false),
	array('label' => 'HEAD', 'server' => array('REQUEST_METHOD' => 'HEAD'), 'expected' => false),
	array('label' => 'OPTIONS', 'server' => array('REQUEST_METHOD' => 'OPTIONS'), 'expected' => false),
	array('label' => 'empty', 'server' => array('REQUEST_METHOD' => ''), 'expected' => false),
	array('label' => 'whitespace', 'server' => array('REQUEST_METHOD' => '   '), 'expected' => false),
	array('label' => 'numeric', 'server' => array('REQUEST_METHOD' => 123), 'expected' => false),
	array('label' => 'true', 'server' => array('REQUEST_METHOD' => true), 'expected' => false),
	array('label' => 'false', 'server' => array('REQUEST_METHOD' => false), 'expected' => false),
	array('label' => 'array', 'server' => array('REQUEST_METHOD' => array('POST')), 'expected' => false),
	array('label' => 'stringable object', 'server' => array('REQUEST_METHOD' => new VmsTestStringablePostMethod()), 'expected' => false),
	array('label' => 'non-stringable object', 'server' => array('REQUEST_METHOD' => new VmsTestNonStringablePostMethod()), 'expected' => false),
	array('label' => 'resource', 'server' => array('REQUEST_METHOD' => $resource), 'expected' => false),
);

foreach ($cases as $case) {
	$_SERVER = $case['server'];
	$expected = $case['expected'];
	$label = $case['label'];

	vms_test_assert(vms_sd_is_exact_post_request() === $expected, 'Season Dates helper mismatch for case: ' . $label);
	vms_test_assert(vms_tasks_admin_is_exact_post_request() === $expected, 'Staff Tasks helper mismatch for case: ' . $label);
	vms_test_assert(vms_staff_portal_is_exact_post_request() === $expected, 'Staff Portal helper mismatch for case: ' . $label);
}

fclose($resource);

echo "Strict POST gate remediation OK.\n";
