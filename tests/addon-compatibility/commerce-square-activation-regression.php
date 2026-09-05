<?php
declare(strict_types=1);

$expectedVersion = getenv('BVM_COMMERCE_EXPECTED_VERSION') ?: '0.2.12';
$sourceDir = getenv('BVM_COMMERCE_SOURCE_DIR') ?: '';
$temporaryRoot = '';

if ($sourceDir === '') {
	$archive = dirname(__DIR__, 2) . '/docs/addon-compatibility/artifacts/vms-commerce-discounts-0.2.12.zip';
	if (!is_file($archive)) {
		fwrite(STDERR, "Set BVM_COMMERCE_SOURCE_DIR or provide the committed corrected Commerce archive.\n");
		exit(2);
	}
	$temporaryRoot = sys_get_temp_dir() . '/bvm-commerce-regression-' . bin2hex(random_bytes(6));
	if (!mkdir($temporaryRoot, 0700, true)) {
		fwrite(STDERR, "Could not create the Commerce regression extraction directory.\n");
		exit(2);
	}
	$zip = new ZipArchive();
	if ($zip->open($archive) !== true || !$zip->extractTo($temporaryRoot)) {
		fwrite(STDERR, "Could not extract the committed corrected Commerce archive.\n");
		exit(2);
	}
	$zip->close();
	$sourceDir = $temporaryRoot . '/vms-commerce-discounts';
}

$cleanup = static function () use (&$temporaryRoot): void {
	if ($temporaryRoot === '' || !is_dir($temporaryRoot)) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($temporaryRoot, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($iterator as $item) {
		$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
	}
	rmdir($temporaryRoot);
};
register_shutdown_function($cleanup);

$sourceDir = realpath($sourceDir) ?: '';
$entryFile = $sourceDir . '/vms-commerce-discounts.php';
$loaderFile = $sourceDir . '/includes/class-vms-discounts-loader.php';
$bridgeFile = $sourceDir . '/includes/class-vms-discounts-square-bridge.php';
foreach (array($entryFile, $loaderFile, $bridgeFile) as $requiredFile) {
	if (!is_file($requiredFile)) {
		fwrite(STDERR, "Missing Commerce regression source file: {$requiredFile}\n");
		exit(2);
	}
}

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$fixture = __DIR__ . '/commerce-square-activation-fixture.php';
$runFixture = static function (string $scenario) use ($fixture, $sourceDir, &$failures): ?array {
	$command = array(PHP_BINARY, $fixture, $sourceDir, $scenario);
	$descriptors = array(
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$process = proc_open($command, $descriptors, $pipes);
	if (!is_resource($process)) {
		$failures[] = "Could not start Commerce fixture scenario {$scenario}.";
		return null;
	}
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exitCode = proc_close($process);
	if ($exitCode !== 0) {
		$failures[] = "Commerce fixture {$scenario} exited {$exitCode}:\n" . trim((string) $stdout . "\n" . (string) $stderr);
		return null;
	}
	if (preg_match('/^COMMERCE_SQUARE_FIXTURE_JSON=([A-Za-z0-9+\/=]+)$/m', (string) $stdout, $match) !== 1) {
		$failures[] = "Commerce fixture {$scenario} returned no payload.";
		return null;
	}
	$decoded = base64_decode($match[1], true);
	$payload = is_string($decoded) ? json_decode($decoded, true) : null;
	if (!is_array($payload)) {
		$failures[] = "Commerce fixture {$scenario} returned invalid JSON.";
		return null;
	}
	return $payload;
};

$noSquare = $runFixture('no-square');
$noSquareRepeat = $runFixture('no-square');
$square = $runFixture('square');
$noWooCommerce = $runFixture('no-woocommerce');

if (is_array($noSquare)) {
	$assert(($noSquare['activation_completed'] ?? false) === true, 'Activation without Square did not complete.');
	$assert(($noSquare['migration_marker'] ?? null) === 1, 'Activation without Square did not reach the existing migration completion marker.');
	$assert(($noSquare['square_bridge_declared'] ?? true) === false, 'The Square bridge was declared while Square was absent.');
	$assert(($noSquare['square_request_declared'] ?? true) === false, 'The Square request subclass was declared while its parent was absent.');
	$assert(($noSquare['hook_counts']['wc_payment_gateway_square_credit_card_get_order'] ?? -1) === 0, 'A Square credit-card callback was registered while Square was absent.');
	$assert(($noSquare['hook_counts']['wc_payment_gateway_square_cash_app_pay_get_order'] ?? -1) === 0, 'A Square Cash App callback was registered while Square was absent.');
	$assert(($noSquare['hook_counts']['wp_ajax_vms_discounts_search_products'] ?? 0) === 1, 'The non-Square Commerce runtime did not remain available.');
	$assert(stripos((string) ($noSquare['notice_html'] ?? ''), 'WooCommerce Square') !== false, 'The missing-Square state did not identify WooCommerce Square.');
	$assert(stripos((string) ($noSquare['notice_html'] ?? ''), 'unavailable') !== false, 'The missing-Square state did not identify the unavailable integration.');
}
$assert(is_array($noSquare) && $noSquare === $noSquareRepeat, 'Repeated missing-Square bootstraps were not deterministic.');

if (is_array($square)) {
	$assert(($square['activation_completed'] ?? false) === true, 'Activation with Square did not complete.');
	$assert(($square['square_bridge_declared'] ?? false) === true, 'The Square bridge was not declared when Square was available.');
	$assert(($square['square_request_declared'] ?? false) === true, 'The Square request subclass was not declared when its parent was available.');
	$assert(($square['hook_counts']['wc_payment_gateway_square_credit_card_get_order'] ?? 0) === 1, 'The Square credit-card callback registration changed.');
	$assert(($square['hook_counts']['wc_payment_gateway_square_cash_app_pay_get_order'] ?? 0) === 1, 'The Square Cash App callback registration changed.');
	$assert(stripos((string) ($square['notice_html'] ?? ''), 'WooCommerce Square') === false, 'A false missing-Square notice appeared with Square available.');
}

if (is_array($noWooCommerce)) {
	$assert(($noWooCommerce['activation_completed'] ?? false) === true, 'Activation without WooCommerce did not complete gracefully.');
	$assert(($noWooCommerce['square_bridge_declared'] ?? true) === false, 'The Square bridge was declared without WooCommerce.');
	$assert(stripos((string) ($noWooCommerce['notice_html'] ?? ''), 'requires WooCommerce to be active') !== false, 'The existing missing-WooCommerce notice changed.');
}

$entrySource = (string) file_get_contents($entryFile);
$loaderSource = (string) file_get_contents($loaderFile);
$bridgeSource = (string) file_get_contents($bridgeFile);
$assert(preg_match('/^Version:\s*' . preg_quote($expectedVersion, '/') . '\s*$/m', $entrySource) === 1, "Commerce header is not {$expectedVersion}.");
$assert(strpos($entrySource, "define('VMS_DISCOUNTS_VERSION', '{$expectedVersion}')") !== false, "Commerce version constant is not {$expectedVersion}.");
$assert(preg_match('/if\s*\(\s*\$this->square_bridge_parent_available\(\)\s*\)\s*\{\s*require_once\s+VMS_DISCOUNTS_PATH\s*\.\s*[\'\"]includes\/class-vms-discounts-square-bridge\.php[\'\"]/s', $loaderSource) === 1, 'The Square-only bridge file is not conditionally loaded behind the parent contract.');
$assert(substr_count($bridgeSource, 'class VMS_Discounts_') === 2, 'The Square bridge file class inventory changed unexpectedly.');
$assert(substr_count($bridgeSource, 'extends \\WooCommerce\\Square\\Gateway\\API\\Requests\\Orders') === 1, 'The expected Square request parent declaration changed.');

if ($failures !== array()) {
	fwrite(STDERR, "Commerce missing-Square regression failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Commerce missing-Square activation regression passed for {$expectedVersion}: no-Square / Square-present / no-WooCommerce / deterministic repeat.\n";
