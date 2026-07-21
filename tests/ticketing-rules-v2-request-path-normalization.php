<?php
declare(strict_types=1);

function vms_test_ticketing_request_path_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_ticketing_request_path_find_matching_brace(string $code, int $openBracePos): int
{
	$depth = 0;
	$length = strlen($code);
	for ($i = $openBracePos; $i < $length; $i++) {
		$char = $code[$i];
		if ($char === '{') {
			$depth++;
			continue;
		}

		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return $i;
			}
		}
	}

	throw new RuntimeException('Matching brace not found.');
}

function vms_test_ticketing_request_path_extract_named_function(string $path, string $functionName): string
{
	$code = (string) file_get_contents($path);
	$marker = 'function ' . $functionName . '(';
	$functionPos = strpos($code, $marker);
	if ($functionPos === false) {
		throw new RuntimeException('Function not found: ' . $functionName);
	}

	$bracePos = strpos($code, '{', $functionPos);
	if ($bracePos === false) {
		throw new RuntimeException('Function brace not found: ' . $functionName);
	}

	$endPos = vms_test_ticketing_request_path_find_matching_brace($code, $bracePos);
	return substr($code, $functionPos, $endPos - $functionPos + 1);
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[\x00-\x1F\x7F]+/', '', strip_tags(stripslashes((string) $value)));
	return is_string($sanitized) ? trim($sanitized) : '';
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	return is_string($value) ? stripslashes($value) : $value;
}

function wp_parse_url(string $url, int $component = -1)
{
	return parse_url($url, $component);
}

$GLOBALS['vms_test_ticketing_request_path_current_uri'] = '';
$GLOBALS['vms_test_ticketing_request_path_current_uri_calls'] = 0;

function vms_request_current_uri(string $fallback = ''): string
{
	$GLOBALS['vms_test_ticketing_request_path_current_uri_calls']++;
	$current = $GLOBALS['vms_test_ticketing_request_path_current_uri'];
	if (!is_string($current) || $current === '') {
		return $fallback;
	}

	return $current;
}

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';
$mirrorPath = $pluginRoot . '/includes/integrations/ticketing-rules-v2.php';
$livePath = $livePluginRoot . '/includes/integrations/ticketing-rules-v2.php';

$mirrorSource = file_get_contents($mirrorPath);
$liveSource = file_get_contents($livePath);

vms_test_ticketing_request_path_assert(is_string($mirrorSource) && $mirrorSource !== '', 'Mirror Ticketing Rules V2 source should be readable.');
vms_test_ticketing_request_path_assert(is_string($liveSource) && $liveSource !== '', 'Live Ticketing Rules V2 source should be readable.');

vms_test_ticketing_request_path_assert(strpos($mirrorSource, '$_SERVER') === false, 'Mirror Ticketing Rules V2 source should not retain direct $_SERVER reads.');
vms_test_ticketing_request_path_assert(strpos($liveSource, '$_SERVER') === false, 'Live Ticketing Rules V2 source should not retain direct $_SERVER reads.');
vms_test_ticketing_request_path_assert(substr_count($mirrorSource, 'vms_request_current_uri()') === 1, 'Mirror Ticketing Rules V2 should own one helper-backed URI fallback.');
vms_test_ticketing_request_path_assert(substr_count($liveSource, 'vms_request_current_uri()') === 1, 'Live Ticketing Rules V2 should own one helper-backed URI fallback.');
vms_test_ticketing_request_path_assert(strpos($mirrorSource, "if (isset(\$_GET['rest_route'])) {") !== false, 'Mirror Ticketing Rules V2 should preserve the rest_route precedence branch.');
vms_test_ticketing_request_path_assert(strpos($liveSource, "if (isset(\$_GET['rest_route'])) {") !== false, 'Live Ticketing Rules V2 should preserve the rest_route precedence branch.');
vms_test_ticketing_request_path_assert(strpos($mirrorSource, "sanitize_text_field(wp_unslash((string) \$_GET['rest_route']))") !== false, 'Mirror Ticketing Rules V2 should preserve rest_route sanitization.');
vms_test_ticketing_request_path_assert(strpos($liveSource, "sanitize_text_field(wp_unslash((string) \$_GET['rest_route']))") !== false, 'Live Ticketing Rules V2 should preserve rest_route sanitization.');
vms_test_ticketing_request_path_assert(strpos($mirrorSource, 'wp_parse_url(vms_request_current_uri(), PHP_URL_PATH)') !== false, 'Mirror Ticketing Rules V2 should derive the fallback path from the shared request URI helper.');
vms_test_ticketing_request_path_assert(strpos($liveSource, 'wp_parse_url(vms_request_current_uri(), PHP_URL_PATH)') !== false, 'Live Ticketing Rules V2 should derive the fallback path from the shared request URI helper.');
vms_test_ticketing_request_path_assert(strpos($mirrorSource, 'return strtolower(trim((string) $route));') !== false, 'Mirror Ticketing Rules V2 should preserve lowercase and trim normalization.');
vms_test_ticketing_request_path_assert(strpos($liveSource, 'return strtolower(trim((string) $route));') !== false, 'Live Ticketing Rules V2 should preserve lowercase and trim normalization.');

eval(vms_test_ticketing_request_path_extract_named_function($mirrorPath, 'vms_ticketing_v2_store_api_request_path'));

$resetRuntime = static function (): void {
	$_GET = array();
	$GLOBALS['vms_test_ticketing_request_path_current_uri'] = '';
	$GLOBALS['vms_test_ticketing_request_path_current_uri_calls'] = 0;
};

$resetRuntime();
$_GET['rest_route'] = ' /WC/Store/V1/Cart ';
$GLOBALS['vms_test_ticketing_request_path_current_uri'] = '/wc/store/v1/checkout?step=1';
vms_test_ticketing_request_path_assert(
	vms_ticketing_v2_store_api_request_path() === '/wc/store/v1/cart',
	'Ticketing Rules V2 should prefer rest_route over the helper-backed URI fallback.'
);
vms_test_ticketing_request_path_assert(
	$GLOBALS['vms_test_ticketing_request_path_current_uri_calls'] === 0,
	'Ticketing Rules V2 should not consult the helper-backed URI fallback when rest_route is present.'
);

$resetRuntime();
$GLOBALS['vms_test_ticketing_request_path_current_uri'] = '/WC/Store/V1/Checkout?step=1&mode=fast';
vms_test_ticketing_request_path_assert(
	vms_ticketing_v2_store_api_request_path() === '/wc/store/v1/checkout',
	'Ticketing Rules V2 should derive a lowercase path-only route from the helper-backed URI fallback.'
);
vms_test_ticketing_request_path_assert(
	$GLOBALS['vms_test_ticketing_request_path_current_uri_calls'] === 1,
	'Ticketing Rules V2 should consult the helper-backed URI fallback once when rest_route is absent.'
);

$resetRuntime();
$GLOBALS['vms_test_ticketing_request_path_current_uri'] = '';
vms_test_ticketing_request_path_assert(
	vms_ticketing_v2_store_api_request_path() === '',
	'Ticketing Rules V2 should preserve the empty fallback result when no route source yields a usable path.'
);
vms_test_ticketing_request_path_assert(
	$GLOBALS['vms_test_ticketing_request_path_current_uri_calls'] === 1,
	'Ticketing Rules V2 should still consult the helper once before preserving the empty fallback result.'
);

fwrite(STDOUT, "Ticketing Rules V2 request path normalization OK.\n");
