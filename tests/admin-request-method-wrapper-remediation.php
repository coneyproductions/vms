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

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = strtolower((string) $value);
	return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
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

function bvmgr_request_server_value(string $key): string
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

function vms_test_budget_route(array $post): bool
{
	if ('POST' !== vms_budget_request_method()) {
		return false;
	}

	$action = isset($post['vms_budget_action']) ? sanitize_key((string) wp_unslash($post['vms_budget_action'])) : '';
	return 'calculate' === $action;
}

function vms_test_staffing_template_save_route(array $post): bool
{
	$request_method = vms_staffing_admin_request_method();
	$post_data = 'POST' === $request_method ? wp_unslash($post) : array();
	$post_action = isset($post_data['vms_tpl_action']) ? sanitize_key((string) $post_data['vms_tpl_action']) : '';

	return 'POST' === $request_method && 'save' === $post_action;
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
$budgetSource = (string) file_get_contents($pluginRoot . '/includes/admin/budget-calculator.php');
$staffingSource = (string) file_get_contents($pluginRoot . '/includes/admin/staffing.php');
$seasonDatesSource = (string) file_get_contents($pluginRoot . '/includes/admin/season-dates.php');
$staffTasksSource = (string) file_get_contents($pluginRoot . '/includes/modules/staff-tasks/admin-ui.php');
$vendorTaxSource = (string) file_get_contents($pluginRoot . '/includes/portal/vendor-tax-profile.php');
$staffPortalSource = (string) file_get_contents($pluginRoot . '/includes/portal/staff-portal.php');

$budgetFunctionSource = vms_test_extract_function($budgetSource, 'vms_budget_request_method');
$staffingFunctionSource = vms_test_extract_function($staffingSource, 'vms_staffing_admin_request_method');

vms_test_assert(strpos($budgetSource, '$_SERVER[\'REQUEST_METHOD\']') === false, 'Budget Calculator should no longer read $_SERVER[\'REQUEST_METHOD\'] directly.');
vms_test_assert(strpos($staffingSource, '$_SERVER[\'REQUEST_METHOD\']') === false, 'Staffing admin should no longer read $_SERVER[\'REQUEST_METHOD\'] directly.');
vms_test_assert(substr_count($budgetFunctionSource, "bvmgr_request_server_value('REQUEST_METHOD')") === 1, 'Budget Calculator wrapper should source REQUEST_METHOD through bvmgr_request_server_value().');
vms_test_assert(substr_count($staffingFunctionSource, "bvmgr_request_server_value('REQUEST_METHOD')") === 1, 'Staffing admin wrapper should source REQUEST_METHOD through bvmgr_request_server_value().');
vms_test_assert(strpos($budgetSource, "if ('POST' === vms_budget_request_method()) {") !== false, 'Budget Calculator POST route should continue to gate on the local wrapper.');
vms_test_assert(strpos($staffingSource, "\$request_method = vms_staffing_admin_request_method();") !== false, 'Staffing admin should continue to capture the local request-method wrapper result.');
vms_test_assert(strpos($staffingSource, "if ('POST' === \$request_method && 'save' === \$post_action) {") !== false, 'Staffing templates save route should continue to gate on POST and save action.');

eval($budgetFunctionSource);
eval($staffingFunctionSource);

$resource = fopen('php://temp', 'r');
if (!is_resource($resource)) {
	throw new RuntimeException('Could not create test resource.');
}

$cases = array(
	array(
		'label' => 'missing',
		'server' => array(),
		'budget' => '',
		'staffing' => '',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'GET',
		'server' => array('REQUEST_METHOD' => 'GET'),
		'budget' => 'GET',
		'staffing' => 'GET',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'get',
		'server' => array('REQUEST_METHOD' => 'get'),
		'budget' => 'GET',
		'staffing' => 'GET',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'PoSt',
		'server' => array('REQUEST_METHOD' => 'PoSt'),
		'budget' => 'POST',
		'staffing' => 'POST',
		'budget_route' => true,
		'staffing_route' => true,
	),
	array(
		'label' => 'empty',
		'server' => array('REQUEST_METHOD' => ''),
		'budget' => '',
		'staffing' => '',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'whitespace',
		'server' => array('REQUEST_METHOD' => '   '),
		'budget' => '',
		'staffing' => '',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'numeric',
		'server' => array('REQUEST_METHOD' => 123),
		'budget' => '123',
		'staffing' => '123',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'true',
		'server' => array('REQUEST_METHOD' => true),
		'budget' => '1',
		'staffing' => '1',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'false',
		'server' => array('REQUEST_METHOD' => false),
		'budget' => '',
		'staffing' => '',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'array',
		'server' => array('REQUEST_METHOD' => array('POST')),
		'budget' => '',
		'staffing' => '',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'stringable object',
		'server' => array('REQUEST_METHOD' => new VmsTestStringablePostMethod()),
		'budget' => '',
		'staffing' => '',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'non-stringable object',
		'server' => array('REQUEST_METHOD' => new VmsTestNonStringablePostMethod()),
		'budget' => '',
		'staffing' => '',
		'budget_route' => false,
		'staffing_route' => false,
	),
	array(
		'label' => 'resource',
		'server' => array('REQUEST_METHOD' => $resource),
		'budget' => '',
		'staffing' => '',
		'budget_route' => false,
		'staffing_route' => false,
	),
);

$originalServer = $_SERVER ?? array();
$postPayloads = array(
	'budget' => array('vms_budget_action' => 'calculate'),
	'staffing' => array('vms_tpl_action' => 'save'),
);

foreach ($cases as $case) {
	$_SERVER = $case['server'];

	$budgetMethod = vms_budget_request_method();
	$staffingMethod = vms_staffing_admin_request_method();

	vms_test_assert($budgetMethod === $case['budget'], 'Unexpected Budget Calculator method result for ' . $case['label'] . '.');
	vms_test_assert($staffingMethod === $case['staffing'], 'Unexpected Staffing admin method result for ' . $case['label'] . '.');
	vms_test_assert(vms_test_budget_route($postPayloads['budget']) === $case['budget_route'], 'Unexpected Budget Calculator POST routing result for ' . $case['label'] . '.');
	vms_test_assert(vms_test_staffing_template_save_route($postPayloads['staffing']) === $case['staffing_route'], 'Unexpected Staffing admin POST routing result for ' . $case['label'] . '.');
}

$_SERVER = $originalServer;
fclose($resource);

fwrite(STDOUT, "Admin request-method wrapper remediation OK.\n");
