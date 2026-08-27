<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
	throw new RuntimeException($message . ' @ ' . $file . ':' . $line, $severity);
});

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

function vms_test_rename_function(string $functionSource, string $newName): string
{
	$renamed = preg_replace(
		'/function\s+bvmgr_vendor_tax_is_exact_post_request\s*\(/',
		'function ' . $newName . '(',
		$functionSource,
		1
	);
	if (!is_string($renamed)) {
		throw new RuntimeException('Could not rename helper source.');
	}

	return $renamed;
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

function vms_test_strip_comments(string $source): string
{
	$tokens = token_get_all("<?php\n" . $source);
	$out = '';
	foreach ($tokens as $token) {
		if (is_string($token)) {
			$out .= $token;
			continue;
		}
		if (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
			continue;
		}
		$out .= $token[1];
	}

	return preg_replace('/^\<\?php\s*/', '', $out, 1) ?? $out;
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
$mirrorPath = $pluginRoot . '/includes/portal/vendor-tax-profile.php';
$livePath = dirname($pluginRoot, 2) . '/vms/includes/portal/vendor-tax-profile.php';

$mirrorSource = (string) file_get_contents($mirrorPath);
$liveSource = (string) file_get_contents($livePath);

// Mirror/live full-file equality is intentionally not asserted here; this is a drift-aware test.

$mirrorHelperSource = vms_test_extract_function($mirrorSource, 'bvmgr_vendor_tax_is_exact_post_request');
$liveHelperSource = vms_test_extract_function($liveSource, 'bvmgr_vendor_tax_is_exact_post_request');

vms_test_assert(substr_count($mirrorSource, 'function bvmgr_vendor_tax_is_exact_post_request(') === 1, 'Mirror Vendor Tax Profile should define exactly one exact POST helper.');
vms_test_assert(substr_count($liveSource, 'function bvmgr_vendor_tax_is_exact_post_request(') === 1, 'Live Vendor Tax Profile should define exactly one exact POST helper.');
vms_test_assert(substr_count($mirrorSource, '$_SERVER[\'REQUEST_METHOD\']') === 1, 'Mirror Vendor Tax Profile should retain only one direct REQUEST_METHOD read.');
vms_test_assert(substr_count($liveSource, '$_SERVER[\'REQUEST_METHOD\']') === 1, 'Live Vendor Tax Profile should retain only one direct REQUEST_METHOD read.');

vms_test_assert(
	strpos(vms_test_strip_comments($mirrorHelperSource), 'wp_unslash(') !== false
		&& strpos(vms_test_strip_comments($liveHelperSource), 'wp_unslash(') !== false,
	'Mirror and live Vendor Tax POST helpers should both unslash the request method before comparison.'
);

vms_test_assert(strpos($mirrorSource, "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['vms_vendor_tax_save'])) {") === false, 'Mirror Vendor Tax Profile should no longer use the direct POST gate.');
vms_test_assert(strpos($liveSource, "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['vms_vendor_tax_save'])) {") === false, 'Live Vendor Tax Profile should no longer use the direct POST gate.');
vms_test_assert(strpos($mirrorSource, "if (bvmgr_vendor_tax_is_exact_post_request() && isset(\$_POST['vms_vendor_tax_save'])) {") !== false, 'Mirror Vendor Tax Profile should use the exact POST helper in its save gate.');
vms_test_assert(strpos($liveSource, "if (bvmgr_vendor_tax_is_exact_post_request() && isset(\$_POST['vms_vendor_tax_save'])) {") !== false, 'Live Vendor Tax Profile should use the exact POST helper in its save gate.');
vms_test_assert(strpos($mirrorSource, 'bvmgr_request_method()') === false, 'Mirror Vendor Tax Profile should not use bvmgr_request_method().');
vms_test_assert(strpos($liveSource, 'bvmgr_request_method()') === false, 'Live Vendor Tax Profile should not use bvmgr_request_method().');
vms_test_assert(strpos($mirrorSource, "bvmgr_request_server_value('REQUEST_METHOD')") === false, 'Mirror Vendor Tax Profile should not use bvmgr_request_server_value().');
vms_test_assert(strpos($liveSource, "bvmgr_request_server_value('REQUEST_METHOD')") === false, 'Live Vendor Tax Profile should not use bvmgr_request_server_value().');

$mirrorWithoutHelper = str_replace($mirrorHelperSource, '', $mirrorSource);
$liveWithoutHelper = str_replace($liveHelperSource, '', $liveSource);

vms_test_assert(strpos($mirrorWithoutHelper, '$_SERVER[\'REQUEST_METHOD\']') === false, 'Mirror Vendor Tax Profile should not read REQUEST_METHOD outside its local helper.');
vms_test_assert(strpos($liveWithoutHelper, '$_SERVER[\'REQUEST_METHOD\']') === false, 'Live Vendor Tax Profile should not read REQUEST_METHOD outside its local helper.');

vms_test_assert_order(
	$mirrorSource,
	array(
		'if ($vendor_id <= 0) {',
		'if (bvmgr_vendor_tax_is_exact_post_request() && isset($_POST[\'vms_vendor_tax_save\'])) {',
		'if ($nonce === \'\' || !wp_verify_nonce($nonce, \'vms_vendor_tax_save\')) {',
		'update_post_meta($vendor_id, $k_legal,  $t(\'vms_payee_legal_name\'));',
	),
	'Mirror Vendor Tax Profile changed vendor validation, request-method, or nonce ordering unexpectedly.'
);
vms_test_assert_order(
	$liveSource,
	array(
		'if ($vendor_id <= 0) {',
		'if (bvmgr_vendor_tax_is_exact_post_request() && isset($_POST[\'vms_vendor_tax_save\'])) {',
		'if ($nonce === \'\' || !wp_verify_nonce($nonce, \'vms_vendor_tax_save\')) {',
		'update_post_meta($vendor_id, $k_legal,  $t(\'vms_payee_legal_name\'));',
	),
	'Live Vendor Tax Profile changed vendor validation, request-method, or nonce ordering unexpectedly.'
);
vms_test_assert_order(
	$mirrorSource,
	array(
		'$vendor_update_context = \'\';',
		'if (bvmgr_upload_request_has_file($_FILES, \'vms_w9_upload\')) {',
		'if ($vendor_update_context !== \'\' && function_exists(\'bvmgr_vendor_flag_vendor_update\')) {',
		'if (bvmgr_vendor_tax_profile_is_complete($vendor_id)) {',
	),
	'Mirror Vendor Tax Profile moved upload handling, vendor-update context, or completion stamping unexpectedly.'
);
vms_test_assert_order(
	$liveSource,
	array(
		'$vendor_update_context = \'\';',
		'if (!empty($_FILES[\'vms_w9_upload\'][\'name\'])) {',
		'if ($vendor_update_context !== \'\' && function_exists(\'bvmgr_vendor_flag_vendor_update\')) {',
		'if (bvmgr_vendor_tax_profile_is_complete($vendor_id)) {',
	),
	'Live Vendor Tax Profile moved upload handling, vendor-update context, or completion stamping unexpectedly.'
);

vms_test_assert(strpos($mirrorSource, 'wp_safe_redirect(') === false && strpos($mirrorSource, 'wp_redirect(') === false, 'Mirror Vendor Tax Profile should not introduce redirects.');
vms_test_assert(strpos($liveSource, 'wp_safe_redirect(') === false && strpos($liveSource, 'wp_redirect(') === false, 'Live Vendor Tax Profile should not introduce redirects.');

vms_test_assert(strpos($mirrorSource, "if (bvmgr_vendor_tax_is_exact_post_request() && isset(\$_POST['vms_vendor_tax_save'])) {") !== false, 'Mirror Vendor Tax form marker should remain required.');
vms_test_assert(strpos($liveSource, "if (bvmgr_vendor_tax_is_exact_post_request() && isset(\$_POST['vms_vendor_tax_save'])) {") !== false, 'Live Vendor Tax form marker should remain required.');

vms_test_assert(strpos($mirrorSource, "bvmgr_upload_request_has_file(\$_FILES, 'vms_w9_upload')") !== false, 'Mirror Vendor Tax Profile should retain the private-file upload request guard.');
vms_test_assert(strpos($mirrorSource, 'bvmgr_private_w9_store_upload($vendor_id, $_FILES)') !== false, 'Mirror Vendor Tax Profile should retain the private-file W-9 storage path.');
vms_test_assert(strpos($mirrorSource, 'bvmgr_private_files_delete($previous_upload_id);') !== false, 'Mirror Vendor Tax Profile should retain replacement cleanup.');
vms_test_assert(strpos($mirrorSource, 'wp_kses_post(vms_portal_notice(') !== false, 'Mirror Vendor Tax Profile should retain the wrapped portal notice sink pattern.');
vms_test_assert(strpos($mirrorSource, 'bvmgr_private_w9_download_url($vendor_id)') !== false, 'Mirror Vendor Tax Profile should retain private-download URL behavior.');
vms_test_assert(strpos($mirrorSource, 'bvmgr_private_w9_file_label($vendor_id)') !== false, 'Mirror Vendor Tax Profile should retain private-download label behavior.');
vms_test_assert(strpos($mirrorSource, "media_handle_upload('vms_w9_upload', 0)") === false, 'Mirror Vendor Tax Profile should not regress to the live legacy upload path.');
vms_test_assert(strpos($mirrorSource, 'wp_get_attachment_url($w9_upload_id)') === false, 'Mirror Vendor Tax Profile should not regress to live attachment URL behavior.');

vms_test_assert(strpos($liveSource, "!empty(\$_FILES['vms_w9_upload']['name'])") !== false, 'Live Vendor Tax Profile should retain its legacy upload entry condition.');
vms_test_assert(strpos($liveSource, "media_handle_upload('vms_w9_upload', 0)") !== false, 'Live Vendor Tax Profile should retain its legacy upload implementation.');
vms_test_assert(strpos($liveSource, 'echo vms_portal_notice(') !== false, 'Live Vendor Tax Profile should retain its direct notice output pattern.');
vms_test_assert(strpos($liveSource, 'wp_get_attachment_url($w9_upload_id)') !== false, 'Live Vendor Tax Profile should retain attachment URL behavior.');
vms_test_assert(strpos($liveSource, 'bvmgr_private_w9_store_upload($vendor_id, $_FILES)') === false, 'Live Vendor Tax Profile should not receive mirror-only private-file upload logic.');
vms_test_assert(strpos($liveSource, 'bvmgr_private_files_delete($previous_upload_id);') === false, 'Live Vendor Tax Profile should not receive mirror-only cleanup logic.');
vms_test_assert(strpos($liveSource, 'wp_kses_post(vms_portal_notice(') === false, 'Live Vendor Tax Profile should not receive the mirror notice sink pattern.');
vms_test_assert(strpos($liveSource, 'bvmgr_private_w9_download_url($vendor_id)') === false, 'Live Vendor Tax Profile should not receive mirror private-download behavior.');
vms_test_assert(strpos($liveSource, 'bvmgr_private_w9_file_label($vendor_id)') === false, 'Live Vendor Tax Profile should not receive mirror private-download labels.');

$mirrorEvalSource = vms_test_rename_function($mirrorHelperSource, 'vms_vendor_tax_is_exact_post_request_mirror');
$liveEvalSource = vms_test_rename_function($liveHelperSource, 'vms_vendor_tax_is_exact_post_request_live');

eval($mirrorEvalSource);
eval($liveEvalSource);

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

	vms_test_assert(vms_vendor_tax_is_exact_post_request_mirror() === $expected, 'Mirror helper mismatch for case: ' . $label);
	vms_test_assert(vms_vendor_tax_is_exact_post_request_live() === $expected, 'Live helper mismatch for case: ' . $label);
}

fclose($resource);

echo "Vendor Tax Profile strict POST remediation OK.\n";
