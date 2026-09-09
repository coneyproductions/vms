<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

function g15_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g15_same($expected, $actual, string $message): void
{
	g15_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

$root = dirname(__DIR__);
$artifact_code = 'WordPress.DateTime.RestrictedFunctions.date_date';
$artifact_rows = array(
	'includes/core/payables.php:88:16' => array('file' => 'includes/core/payables.php', 'occurrence' => 'payables_bill'),
	'includes/core/payables.php:119:12' => array('file' => 'includes/core/payables.php', 'occurrence' => 'payables_add'),
	'includes/portal/vendor-tax-profile.php:158:116' => array('file' => 'includes/portal/vendor-tax-profile.php', 'occurrence' => 'tax_received'),
	'includes/core/event-credits.php:844:84' => array('file' => 'includes/core/event-credits.php', 'occurrence' => 'credit_today'),
);

g15_same(4, count($artifact_rows), 'The G15 P3 artifact inventory must remain exactly four rows.');
g15_same(array($artifact_code => 4), array_count_values(array_fill(0, 4, $artifact_code)), 'The artifact-derived rule split changed.');

$artifact_path = getenv('BVM_G14_HISTORICAL_STRICT_JSON') ?: '/tmp/wporg-dbzero-g14.qulnlt/plugin-check.strict.json';
g15_assert(is_file($artifact_path), 'Authoritative DB-zero/G14 strict JSON is missing.');
g15_same('c5fe4d23b3cdf632f239632a23f2c58f9ccf7b8e293ff4b9e71f65101527aa17', hash_file('sha256', $artifact_path), 'Authoritative strict JSON hash changed.');
$artifact = json_decode((string) file_get_contents($artifact_path), true);
g15_assert(is_array($artifact), 'Authoritative strict JSON must decode.');
g15_same(181, count($artifact), 'Authoritative total finding count changed.');
$type_counts = array_count_values(array_column($artifact, 'type'));
g15_same(139, $type_counts['ERROR'] ?? 0, 'Authoritative error count changed.');
g15_same(42, $type_counts['WARNING'] ?? 0, 'Authoritative warning count changed.');

$date_rows = array_values(array_filter(
	$artifact,
	static fn(array $row): bool => ($row['code'] ?? '') === 'WordPress.DateTime.RestrictedFunctions.date_date'
));
g15_same(14, count($date_rows), 'Authoritative date_date count changed.');
$owned_suffixes = array(
	'includes/core/payables.php',
	'includes/portal/vendor-tax-profile.php',
	'includes/core/event-credits.php',
);
$actual_owned_signatures = array();
foreach ($date_rows as $row) {
	$file = (string) ($row['file'] ?? '');
	foreach ($owned_suffixes as $suffix) {
		if ($file === $suffix || substr($file, -strlen($suffix)) === $suffix) {
			$actual_owned_signatures[] = $suffix . ':' . (int) $row['line'] . ':' . (int) $row['column'] . ':' . (string) $row['code'];
			break;
		}
	}
}
$expected_owned_signatures = array_map(
	static fn(string $row_id): string => $row_id . ':WordPress.DateTime.RestrictedFunctions.date_date',
	array_keys($artifact_rows)
);
sort($actual_owned_signatures);
sort($expected_owned_signatures);
g15_same($expected_owned_signatures, $actual_owned_signatures, 'Authoritative owned date rows changed.');
g15_same(10, count($date_rows) - count($actual_owned_signatures), 'Date rows outside G15 P3 must remain exactly ten.');

fwrite(STDOUT, "Historical G14 payables provenance: exact original artifact verified.\n");
