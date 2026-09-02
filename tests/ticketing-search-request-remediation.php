<?php
declare(strict_types=1);

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_normalize_code(string $code): string
{
	$normalized = preg_replace('/\s+/', ' ', $code);
	return is_string($normalized) ? trim($normalized) : trim($code);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos(vms_test_normalize_code($haystack), vms_test_normalize_code($needle)) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos(vms_test_normalize_code($haystack), vms_test_normalize_code($needle)) === false, $message . "\nUnexpected: " . $needle);
}

function vms_test_assert_code_order(string $first, string $second, string $haystack, string $message): void
{
	$haystack = vms_test_normalize_code($haystack);
	$first = vms_test_normalize_code($first);
	$second = vms_test_normalize_code($second);

	$firstPos = strpos($haystack, $first);
	$secondPos = strpos($haystack, $second);

	vms_test_assert($firstPos !== false, $message . "\nMissing first token: " . $first);
	vms_test_assert($secondPos !== false, $message . "\nMissing second token: " . $second);
	vms_test_assert($firstPos < $secondPos, $message);
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name . '(';
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
			continue;
		}
		if ($source[$i] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	throw new RuntimeException('Unable to locate closing brace for ' . $name . '.');
}

$ticketingFile = dirname(__DIR__) . '/includes/integrations/ticketing.php';
$source = (string) file_get_contents($ticketingFile);
vms_test_assert($source !== '', 'Ticketing integration source should be readable.');

$tecSearchBody = vms_test_extract_function($source, 'bvmgr_ticketing_ajax_search_tec_events');
$productSearchBody = vms_test_extract_function($source, 'bvmgr_ticketing_ajax_search_products');

vms_test_assert_contains(
	"\$q = bvmgr_request_read_text_field(\$_POST, 'q');",
	$tecSearchBody,
	'TEC event search should read q through the shared scalar request helper.'
);
vms_test_assert_contains(
	"\$q = bvmgr_request_read_text_field(\$_POST, 'q');",
	$productSearchBody,
	'Product search should read q through the shared scalar request helper.'
);
vms_test_assert_not_contains(
	"sanitize_text_field((string) \$_POST['q'])",
	$tecSearchBody,
	'TEC event search should no longer cast raw POST q values directly to strings.'
);
vms_test_assert_not_contains(
	"sanitize_text_field((string) \$_POST['q'])",
	$productSearchBody,
	'Product search should no longer cast raw POST q values directly to strings.'
);
vms_test_assert_code_order(
	'if (!bvmgr_ticketing_is_tec_active()) {',
	"\$q = bvmgr_request_read_text_field(\$_POST, 'q');",
	$tecSearchBody,
	'TEC event search should continue to read q only after the nonce, capability, and integration gates pass.'
);
vms_test_assert_code_order(
	'if (!bvmgr_ticketing_is_woo_active()) {',
	"\$q = bvmgr_request_read_text_field(\$_POST, 'q');",
	$productSearchBody,
	'Product search should continue to read q only after the nonce, capability, and integration gates pass.'
);

fwrite(STDOUT, "ticketing search request remediation: PASS\n");
