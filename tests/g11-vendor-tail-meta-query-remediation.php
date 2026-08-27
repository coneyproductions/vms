<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('BVMGR_CPT_VENDOR', 'vms_vendor');

function g11_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g11_same($expected, $actual, string $message): void
{
	g11_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function g11_contains(string $needle, string $haystack, string $message): void
{
	g11_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g11_ends_with(string $haystack, string $needle): bool
{
	return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
}

function g11_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
	}

	$depth = 1;
	for ($offset = $brace + 1, $length = strlen($source); $offset < $length; $offset++) {
		$depth += $source[$offset] === '{' ? 1 : 0;
		$depth -= $source[$offset] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($offset - $start) + 1);
		}
	}

	throw new RuntimeException('Unable to parse function ' . $name . '.');
}

function g11_projection(string $source, string $function_name): string
{
	return str_replace(
		g11_extract_function($source, $function_name),
		'/* owned function: ' . $function_name . ' */',
		$source
	);
}

function g11_project_g15_payables_dates(string $source): string
{
	$current_bill = "        \$ymd = gmdate('Ymd');";
	$historical_bill = "        \$ymd = date('Ymd');";
	g11_same(1, substr_count($source, $current_bill), 'Current Payables UTC bill fallback must occur exactly once.');
	$source = str_replace($current_bill, $historical_bill, $source, $bill_count);
	g11_same(1, $bill_count, 'Payables bill fallback projection must reverse exactly one statement.');

	$current_add_days = g11_extract_function($source, 'bvmgr_payables_add_days');
	g11_same(1, substr_count($current_add_days, "new DateTimeImmutable(\$ymd . ' 00:00:00', \$utc)"), 'Current Payables add-days must construct with explicit UTC.');
	g11_same(1, substr_count($current_add_days, '$date = $date->setTimezone($utc);'), 'Current Payables add-days must re-normalize embedded timezone tokens to UTC.');
	g11_same(0, preg_match_all('/(?<![A-Za-z0-9_])date\(/', $current_add_days), 'Current Payables add-days must not retain native date().');
	$historical_add_days = <<<'PHP'
function bvmgr_payables_add_days(string $ymd, int $days): string
{
    $ymd  = trim((string) $ymd);
    $days = (int) $days;

    if ($ymd === '') {
        return '';
    }

    $ts = strtotime($ymd . ' 00:00:00');
    if (!$ts) {
        return '';
    }

    if ($days !== 0) {
        $ts = strtotime(($days >= 0 ? '+' : '') . $days . ' days', $ts);
    }

    return date('Y-m-d', $ts);
}
PHP;
	$source = str_replace($current_add_days, $historical_add_days, $source, $add_count);
	g11_same(1, $add_count, 'Payables add-days projection must reverse exactly one function.');
	return $source;
}

/**
 * @param array<string,string>                                 $sources Source contents keyed by owned source name.
 * @param string[]                                             $allowed_codes Exact source codes permitted by this slice.
 * @param array<int,array{source:string,line:int,code:string}> $expected_rows Exact annotation locations.
 * @return string[]
 */
function g11_db_annotation_errors(array $sources, array $allowed_codes, array $expected_rows): array
{
	$errors = array();
	$actual_rows = array();

	foreach ($sources as $source_name => $source) {
		$lines = preg_split('/\R/', $source);
		if (!is_array($lines)) {
			$errors[] = 'Unable to split source: ' . $source_name;
			continue;
		}

		foreach ($lines as $index => $line) {
			if (
				strpos($line, 'phpcs:') === false
				|| preg_match('/(?:WordPress\.DB|PluginCheck\.(?:Security|PluginCheck)\.DirectDB)/i', $line) !== 1
			) {
				continue;
			}

			$directive = substr($line, (int) strpos($line, 'phpcs:'));
			$reason_offset = strpos($directive, ' -- ');
			if ($reason_offset !== false) {
				$directive = substr($directive, 0, $reason_offset);
			}
			$directive = trim($directive);
			if (preg_match('/^phpcs:([a-z]+)\b(?:\s+(.+))?$/i', $directive, $matches) !== 1) {
				$errors[] = sprintf('%s:%d has an unparseable DB annotation.', $source_name, $index + 1);
				continue;
			}

			$verb = strtolower($matches[1]);
			$code = isset($matches[2]) ? trim($matches[2]) : '';
			if ($verb !== 'ignore') {
				$errors[] = sprintf('%s:%d uses forbidden phpcs:%s suppression.', $source_name, $index + 1, $verb);
				continue;
			}
			if (!in_array($code, $allowed_codes, true)) {
				$errors[] = sprintf('%s:%d uses a broad, mixed, or unowned DB source code: %s', $source_name, $index + 1, $code);
				continue;
			}

			$actual_rows[] = array('source' => $source_name, 'line' => $index + 1, 'code' => $code);
		}
	}

	$signature = static function (array $row): string {
		return $row['source'] . ':' . $row['line'] . ':' . $row['code'];
	};
	$actual_signatures = array_map($signature, $actual_rows);
	$expected_signatures = array_map($signature, $expected_rows);
	sort($actual_signatures);
	sort($expected_signatures);
	if ($actual_signatures !== $expected_signatures) {
		$errors[] = 'DB annotation locations differ from the exact expected inventory.';
	}

	return $errors;
}

/**
 * @param array<int,array{code:string,reason:string}> $rows Owned annotations for one source.
 * @return array{source:string,removed:int}
 */
function g11_strip_owned_annotations(string $source, array $rows, string $label): array
{
	$removed = 0;
	foreach ($rows as $row) {
		$marker = ' // phpcs:ignore ' . $row['code'] . ' -- ' . $row['reason'];
		g11_same(1, substr_count($source, $marker), $label . ' must contain each exact owned annotation once.');
		$count = 0;
		$source = str_replace($marker, '', $source, $count);
		g11_same(1, $count, $label . ' must strip each exact owned annotation once.');
		$removed += $count;
	}

	return array('source' => $source, 'removed' => $removed);
}

$root = dirname(__DIR__);
$shadow_root = dirname($root, 2) . '/vms';
$relative_paths = array(
	'vendors' => 'includes/cpt/vendors.php',
	'payables' => 'includes/core/payables.php',
	'onboarding' => 'includes/core/vendor-booking-onboarding.php',
	'tax_export' => 'includes/admin/vendors/tax-export-csv.php',
	'profiles' => 'includes/public/vendor-profiles.php',
	'vendor_category' => 'includes/taxonomies/vendor-category.php',
);
$function_names = array(
	'vendors' => 'bvmgr_vendor_delete_revert_event_plans',
	'payables' => 'bvmgr_payables_build_bills_for_export',
	'onboarding' => 'bvmgr_vendor_booking_onboarding_daily_runner',
	'tax_export' => 'bvmgr_vendor_tax_export_csv_adminpost',
	'profiles' => 'vms_vendor_profiles_find_next_upcoming_event',
	'vendor_category' => 'bvmgr_vendor_categories_get_related_event_plan_ids',
);
$mirror_sources = array();
$shadow_sources = array();
foreach ($relative_paths as $source_name => $relative_path) {
	$mirror_path = $root . '/' . $relative_path;
	$shadow_path = $shadow_root . '/' . $relative_path;
	g11_assert(is_file($mirror_path), 'Missing mirror source: ' . $relative_path);
	g11_assert(is_file($shadow_path), 'Missing shadow source: ' . $relative_path);
	$mirror_sources[$source_name] = (string) file_get_contents($mirror_path);
	$shadow_sources[$source_name] = (string) file_get_contents($shadow_path);
	g11_assert($mirror_sources[$source_name] !== '' && $shadow_sources[$source_name] !== '', 'Owned source must be readable: ' . $relative_path);
}
$baseline_mirror_sources = $mirror_sources;
$baseline_shadow_sources = $shadow_sources;
$baseline_mirror_sources['payables'] = g11_project_g15_payables_dates($mirror_sources['payables']);
$baseline_shadow_sources['payables'] = g11_project_g15_payables_dates($shadow_sources['payables']);

$meta_key_code = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key';
$meta_query_code = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query';
$inventory = array(
	array('source' => 'vendors', 'file' => $relative_paths['vendors'], 'line' => 32, 'column' => 9, 'code' => $meta_query_code, 'anchor' => "'meta_query'             => array(", 'reason' => 'Vendor deletion must enumerate the complete all-status Event Plan set with this exact primary-vendor link so every broken reference is cleared and flagged.'),
	array('source' => 'vendors', 'file' => $relative_paths['vendors'], 'line' => 50, 'column' => 9, 'code' => $meta_query_code, 'anchor' => "'meta_query'             => array(", 'reason' => 'Vendor deletion must enumerate the complete all-status Event Plan set with this exact indexed secondary-vendor link so every broken reference is removed and flagged.'),
	array('source' => 'payables', 'file' => $relative_paths['payables'], 'line' => 200, 'column' => 9, 'code' => $meta_query_code, 'anchor' => "'meta_query'     => [", 'reason' => 'A payables export must include the complete Event Plan set for one requested date, finite venue list, and allowlisted workflow statuses so no payable line is omitted.'),
	array('source' => 'onboarding', 'file' => $relative_paths['onboarding'], 'line' => 906, 'column' => 13, 'code' => $meta_key_code, 'anchor' => "'meta_key' => '_vms_event_date'", 'reason' => 'The daily onboarding runner intentionally identifies canonical event-date metadata while scanning the complete one-year reminder window across eligible Event Plan statuses.'),
	array('source' => 'onboarding', 'file' => $relative_paths['onboarding'], 'line' => 907, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'The daily onboarding runner intentionally scans the complete Event Plan set inside its fixed one-year event-date window so every due headliner reminder is evaluated.'),
	array('source' => 'tax_export', 'file' => $relative_paths['tax_export'], 'line' => 43, 'column' => 9, 'code' => $meta_query_code, 'anchor' => "'meta_query'     => array(", 'reason' => 'The nonce- and capability-gated 1099 export intentionally enumerates every Vendor with completed tax-profile metadata so the CSV is complete.'),
	array('source' => 'profiles', 'file' => $relative_paths['profiles'], 'line' => 733, 'column' => 13, 'code' => $meta_key_code, 'anchor' => "'meta_key'       => \$date_key", 'reason' => 'The public Vendor profile intentionally orders its bounded 12-plan candidate query by canonical event-date metadata to select the next valid linked event.'),
	array('source' => 'profiles', 'file' => $relative_paths['profiles'], 'line' => 734, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query'     => array(", 'reason' => 'The public Vendor profile limits this vendor/date/linked-event metadata filter to 12 upcoming Event Plan candidates before validating the first public event.'),
	array('source' => 'vendor_category', 'file' => $relative_paths['vendor_category'], 'line' => 310, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query'             => [[", 'reason' => 'Vendor save propagation must enumerate the complete all-status Event Plan set with this exact primary-vendor link so every category snapshot stays current.'),
	array('source' => 'vendor_category', 'file' => $relative_paths['vendor_category'], 'line' => 326, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query'             => [[", 'reason' => 'Vendor save propagation must enumerate the complete all-status Event Plan set with this exact indexed secondary-vendor link so every category snapshot stays current.'),
);

g11_same(10, count($inventory), 'Wave 4 G11 tail ownership must remain exactly 10 rows.');
$code_counts = array_count_values(array_column($inventory, 'code'));
ksort($code_counts);
$expected_code_counts = array($meta_key_code => 2, $meta_query_code => 8);
ksort($expected_code_counts);
g11_same($expected_code_counts, $code_counts, 'Artifact-derived rule split must remain K2/Q8.');

foreach ($inventory as $row) {
	$lines = preg_split('/\R/', $baseline_mirror_sources[$row['source']]);
	g11_assert(is_array($lines) && isset($lines[$row['line'] - 1]), 'Artifact-owned line should exist: ' . $row['file'] . ':' . $row['line']);
	$line = $lines[$row['line'] - 1];
	g11_contains($row['anchor'], $line, 'Owned annotation must remain attached to its exact query anchor: ' . $row['file'] . ':' . $row['line']);
	g11_contains('phpcs:ignore ' . $row['code'] . ' -- ' . $row['reason'], $line, 'Owned row must carry its exact installed source code and rationale.');
	g11_same(1, substr_count($line, 'phpcs:ignore'), 'Owned row must contain exactly one line-local annotation.');
}

$artifact_path = '/tmp/wporg-wave4-integrated.nTzezu/plugin-check/plugin-check.strict.json';
if (is_file($artifact_path)) {
	g11_same(
		'278819f58c585c226824fd89d541fc5ab107c11897240e281683fa6abad8d179',
		hash_file('sha256', $artifact_path),
		'Authoritative Wave 4 strict JSON hash changed.'
	);
	$decoded = json_decode((string) file_get_contents($artifact_path), true);
	g11_assert(is_array($decoded), 'Authoritative Wave 4 strict JSON should decode.');
	$actual_rows = array();
	foreach ($decoded as $row) {
		if (!is_array($row) || !in_array((string) ($row['code'] ?? ''), array($meta_key_code, $meta_query_code), true)) {
			continue;
		}
		foreach ($relative_paths as $relative_path) {
			if (g11_ends_with((string) ($row['file'] ?? ''), $relative_path)) {
				$actual_rows[] = array(
					'file' => $relative_path,
					'line' => (int) ($row['line'] ?? 0),
					'column' => (int) ($row['column'] ?? 0),
					'code' => (string) ($row['code'] ?? ''),
				);
				break;
			}
		}
	}
	$signature = static function (array $row): string {
		return $row['file'] . ':' . $row['line'] . ':' . $row['column'] . ':' . $row['code'];
	};
	$expected_rows = array_map(
		static function (array $row): array {
			return array('file' => $row['file'], 'line' => $row['line'], 'column' => $row['column'], 'code' => $row['code']);
		},
		$inventory
	);
	$actual_signatures = array_map($signature, $actual_rows);
	$expected_signatures = array_map($signature, $expected_rows);
	sort($actual_signatures);
	sort($expected_signatures);
	g11_same($expected_signatures, $actual_signatures, 'Strict JSON target rows must equal the embedded 10-row inventory.');
}

$allowed_codes = array($meta_key_code, $meta_query_code);
$mirror_expected_annotations = array_map(
	static function (array $row): array {
		return array('source' => $row['source'], 'line' => $row['line'], 'code' => $row['code']);
	},
	$inventory
);
$shadow_expected_annotations = array_map(
	static function (array $row): array {
		return array(
			'source' => $row['source'],
			'line' => $row['line'] - ($row['source'] === 'vendor_category' ? 3 : 0),
			'code' => $row['code'],
		);
	},
	$inventory
);
g11_same(array(), g11_db_annotation_errors($baseline_mirror_sources, $allowed_codes, $mirror_expected_annotations), 'Mirror DB annotations must equal the exact K2/Q8 inventory after reversing G15 validation-only changes.');
g11_same(array(), g11_db_annotation_errors($baseline_shadow_sources, $allowed_codes, $shadow_expected_annotations), 'Shadow DB annotations must equal the exact K2/Q8 inventory after reversing G15 validation-only changes.');

$negative_annotations = array(
	'block disable' => '// phpcs:disable WordPress.DB',
	'block enable' => '// phpcs:enable WordPress.DB',
	'file ignore' => '// phpcs:ignoreFile WordPress.DB',
	'DB category' => '// phpcs:ignore WordPress.DB -- forbidden category suppression',
	'slow-query family' => '// phpcs:ignore WordPress.DB.SlowDBQuery -- forbidden family suppression',
	'prepared-SQL family' => '// phpcs:ignore WordPress.DB.PreparedSQL -- forbidden family suppression',
	'direct-query family' => '// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- forbidden family suppression',
	'Plugin Check direct-DB family' => '// phpcs:ignore PluginCheck.Security.DirectDB -- forbidden family suppression',
	'unowned exact code' => '// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- forbidden exact code',
	'mixed installed codes' => '// phpcs:ignore ' . $meta_key_code . ',' . $meta_query_code . ' -- forbidden mixed-list suppression',
);
foreach ($negative_annotations as $label => $annotation) {
	$mutated_sources = $baseline_mirror_sources;
	$mutated_sources['tax_export'] .= "\n" . $annotation . "\n";
	g11_assert(g11_db_annotation_errors($mutated_sources, $allowed_codes, $mirror_expected_annotations) !== array(), 'Annotation audit must reject negative control: ' . $label);
}

$annotation_specs = array_fill_keys(array_keys($relative_paths), array());
foreach ($inventory as $row) {
	$annotation_specs[$row['source']][] = array('code' => $row['code'], 'reason' => $row['reason']);
}

$whole_hashes = array(
	'mirror' => array(
		'vendors' => '5c0f82b7f2727c8362205d1d05e9b39d5aa86360e1f5b69d1edc381bfabfda0b',
		'payables' => 'a69575a326f8eca4cec46bb8943907aeb8f343e2eada49b6a3b0525d47005e6d',
		'onboarding' => 'd35a1cff22437fb05c7e5d6144485569cd5029c159b94859c2eb80d7a4e18a61',
		'tax_export' => '81c8e94a51769026434c36b5c6d8a4db8b7a5e991a5daebdaa71638ad4763014',
		'profiles' => '15637ab133d5992c0c7fa1dfe484612d2d5eaaec2d5a4db399a448012d65d865',
		'vendor_category' => '99295d39066d2bf78dea394b30e01086d52b548991d42bd553c829033868d2d9',
	),
	'shadow' => array(
		'vendors' => 'a045ca6ab93cbb5408954950d31024430caaa806492fc30ff88eae57b17f82b1',
		'payables' => 'a69575a326f8eca4cec46bb8943907aeb8f343e2eada49b6a3b0525d47005e6d',
		'onboarding' => 'd35a1cff22437fb05c7e5d6144485569cd5029c159b94859c2eb80d7a4e18a61',
		'tax_export' => '81c8e94a51769026434c36b5c6d8a4db8b7a5e991a5daebdaa71638ad4763014',
		'profiles' => '15637ab133d5992c0c7fa1dfe484612d2d5eaaec2d5a4db399a448012d65d865',
		'vendor_category' => 'ec712f10fe51b8a3e47db1cb2ff5b09ce206f75e5ee16f38b77410d3e207f338',
	),
);
$stripped_hashes = array(
	'mirror' => array(
		'vendors' => 'cabeae63fe5a19a9ed83b2c2b991f1314ed413daefe6df2138743245670a1880',
		'payables' => '01dd66a526fc74c0997d4c3add08c58953749425280e8d25ecb9dc3cd55c2e89',
		'onboarding' => '1e1bd3a7eaf18d55f7da820e6450e283726b05a7b0b38a2382c77580f8a5bf75',
		'tax_export' => '4f79ccf914fc094c503d58ac9502f1f0d09b841dfe06131153ebc2bcd58f3baf',
		'profiles' => 'ef2e2ec2d959e9fa0f0a176540eca62e292351f00adc8520e988e4c865286b65',
		'vendor_category' => '6fe21f0c3bd8cd11b168e9a0de27df79ee4d1d29a2dd54185ac171b7780d4783',
	),
	'shadow' => array(
		'vendors' => '26f979a8db05aef9aabb3f64da690be72b13255ec36c8489665c1e738a4180de',
		'payables' => '01dd66a526fc74c0997d4c3add08c58953749425280e8d25ecb9dc3cd55c2e89',
		'onboarding' => '1e1bd3a7eaf18d55f7da820e6450e283726b05a7b0b38a2382c77580f8a5bf75',
		'tax_export' => '4f79ccf914fc094c503d58ac9502f1f0d09b841dfe06131153ebc2bcd58f3baf',
		'profiles' => 'ef2e2ec2d959e9fa0f0a176540eca62e292351f00adc8520e988e4c865286b65',
		'vendor_category' => '8581f1e3ecc3f0b66cc63bb62bcc13bb23b2e33347eb0a511e96d46d9a6a4e3c',
	),
);
$projection_hashes = array(
	'mirror' => array(
		'vendors' => '63d7d1cf7e3ad505718944a03cd2591e724f4880d8d2b2ed1d815b99643937ca',
		'payables' => '3007b5a197c0e773d028f8cdca2886dd08d85ead7fa614b93706ba93e2d52855',
		'onboarding' => '37e7b07a842dd5a1485bd3710b65bd33c6e47080a3ebd78adf06052290f0773f',
		'tax_export' => '27ee3d9e3a0aa3307d24608ac7291a378b322d40d2678418746bf1e59d749f6a',
		'profiles' => '83d6937e7366ba3bcc896dc54763b4cb1441c9b6b8217376e3aaca8d99f87b15',
		'vendor_category' => '9b018a0c2dc0929d042bcd74f26de04fea12a657fbc784f2fb90cfc624c5adaa',
	),
	'shadow' => array(
		'vendors' => 'e559080128fdf6128ecd4499907be0e0a6abf6b1f6ec45338cae89b4e3bf4978',
		'payables' => '3007b5a197c0e773d028f8cdca2886dd08d85ead7fa614b93706ba93e2d52855',
		'onboarding' => '37e7b07a842dd5a1485bd3710b65bd33c6e47080a3ebd78adf06052290f0773f',
		'tax_export' => '27ee3d9e3a0aa3307d24608ac7291a378b322d40d2678418746bf1e59d749f6a',
		'profiles' => '83d6937e7366ba3bcc896dc54763b4cb1441c9b6b8217376e3aaca8d99f87b15',
		'vendor_category' => 'b9912a29e7da1593996a99542ad71b62ed74d5ad9453b81a2049ea64a9b9a490',
	),
);

$stripped_sources = array('mirror' => array(), 'shadow' => array());
$total_removed = 0;
foreach (array('mirror' => $baseline_mirror_sources, 'shadow' => $baseline_shadow_sources) as $tree => $sources) {
	foreach ($sources as $source_name => $source) {
		g11_same($whole_hashes[$tree][$source_name], hash('sha256', $source), $tree . ' whole-source hash changed: ' . $source_name);
		$stripped = g11_strip_owned_annotations($source, $annotation_specs[$source_name], $tree . ':' . $source_name);
		g11_same($stripped_hashes[$tree][$source_name], hash('sha256', $stripped['source']), $tree . ' annotation-stripped source changed: ' . $source_name);
		g11_same(
			$projection_hashes[$tree][$source_name],
			hash('sha256', g11_projection($source, $function_names[$source_name])),
			$tree . ' outside-owned-function projection changed: ' . $source_name
		);
		$stripped_sources[$tree][$source_name] = $stripped['source'];
		$total_removed += $stripped['removed'];
	}
}
g11_same(20, $total_removed, 'Mirror and shadow must each contain exactly 10 owned annotations.');

$mutated_profiles = str_replace(
	"'posts_per_page' => 12",
	"'posts_per_page' => 13",
	$stripped_sources['mirror']['profiles'],
	$mutation_count
);
g11_same(1, $mutation_count, 'Runtime mutation control must alter one exact Vendor Profile query limit.');
g11_assert(
	hash('sha256', $mutated_profiles) !== $stripped_hashes['mirror']['profiles'],
	'Annotation-stripped whole-source hash must reject a non-comment runtime mutation.'
);

foreach (array('payables', 'onboarding', 'tax_export', 'profiles') as $exact_source) {
	g11_same($mirror_sources[$exact_source], $shadow_sources[$exact_source], 'Full mirror/shadow parity changed: ' . $exact_source);
}
foreach (array('vendors', 'vendor_category') as $divergent_source) {
	g11_same(
		g11_extract_function($mirror_sources[$divergent_source], $function_names[$divergent_source]),
		g11_extract_function($shadow_sources[$divergent_source], $function_names[$divergent_source]),
		'Owned function must remain exact across divergent trees: ' . $divergent_source
	);
	g11_assert($mirror_sources[$divergent_source] !== $shadow_sources[$divergent_source], 'Intentional whole-file divergence must remain: ' . $divergent_source);
}

$vendor_lines = preg_split('/\R/', $mirror_sources['vendors']);
g11_assert(is_array($vendor_lines), 'Deferred Vendors source lines should split.');
g11_same(1, substr_count($mirror_sources['payables'], "        \$ymd = gmdate('Ymd');"), 'Remediated Payables bill fallback must remain exact.');
$payables_add_days = g11_extract_function($mirror_sources['payables'], 'bvmgr_payables_add_days');
g11_contains("new DateTimeImmutable(\$ymd . ' 00:00:00', \$utc)", $payables_add_days, 'Remediated Payables add-days constructor changed.');
g11_contains('$date = $date->setTimezone($utc);', $payables_add_days, 'Remediated Payables UTC re-normalization changed.');
g11_assert(strpos($payables_add_days, 'phpcs:') === false, 'Remediated Payables add-days must remain unsuppressed.');
g11_contains("sprintf(esc_html__('Photo %d URL'", $vendor_lines[395], 'Deferred Vendors output row 396 changed.');
g11_assert(strpos($vendor_lines[395], 'phpcs:') === false, 'Deferred Vendors output row must remain unsuppressed.');

final class WP_Post
{
	public int $ID;
	public string $post_type;
	public string $post_status;

	public function __construct(int $id, string $post_type, string $post_status = 'publish')
	{
		$this->ID = $id;
		$this->post_type = $post_type;
		$this->post_status = $post_status;
	}
}

final class WP_Query
{
	/** @var mixed */
	public $posts;

	public function __construct(array $args)
	{
		$entry = $GLOBALS['g11_wp_query_queue'] === array()
			? array('posts' => array())
			: array_shift($GLOBALS['g11_wp_query_queue']);
		$this->posts = $entry['posts'] ?? array();
		$GLOBALS['g11_wp_query_calls'][] = array('args' => $args, 'posts' => $this->posts);
	}
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$clean = preg_replace('/[^a-z0-9_\-]/', '', $value);
	return is_string($clean) ? $clean : '';
}

function __($text, $domain = ''): string
{
	unset($domain);
	return (string) $text;
}

function wp_strip_all_tags($text): string
{
	return strip_tags((string) $text);
}

function wp_date(string $format, ?int $timestamp = null): string
{
	return gmdate($format, $timestamp ?? (int) $GLOBALS['g11_now_ts']);
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('UTC');
}

function current_time(string $type, bool $gmt = false)
{
	unset($gmt);
	return $type === 'timestamp' ? (int) $GLOBALS['g11_now_ts'] : gmdate($type, (int) $GLOBALS['g11_now_ts']);
}

function bvmgr_meta_key(string $scope, string $name): string
{
	$keys = array(
		'event_plan' => array(
			'band_vendor_id' => '_vms_band_vendor_id',
			'status' => '_vms_event_plan_status',
			'secondary_vendor_ids' => '_vms_secondary_vendor_ids',
			'secondary_vendor_id' => '_vms_secondary_vendor_id',
			'integrity_issue' => '_vms_integrity_issue',
			'date' => '_vms_event_date',
			'venue_id' => '_vms_venue_id',
			'comp_structure' => '_vms_comp_structure',
			'flat_fee_amount' => '_vms_flat_fee_amount',
			'tec_event_id' => '_vms_tec_event_id',
		),
		'vendor' => array(
			'tax_profile_completed_at' => '_vms_vendor_tax_profile_completed_at',
		),
	);
	return $keys[$scope][$name] ?? ('_vms_' . $scope . '_' . $name);
}

function get_posts(array $args)
{
	$result = $GLOBALS['g11_get_posts_queue'] === array() ? array() : array_shift($GLOBALS['g11_get_posts_queue']);
	$GLOBALS['g11_get_posts_calls'][] = array('args' => $args, 'result' => $result);
	return $result;
}

function get_post(int $post_id)
{
	return $GLOBALS['g11_posts'][$post_id] ?? null;
}

function get_post_field(string $field, int $post_id, string $context = '')
{
	unset($context);
	return $field === 'post_title' ? ($GLOBALS['g11_titles'][$post_id] ?? '') : '';
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	return $GLOBALS['g11_meta'][$post_id][$key] ?? ($single ? '' : array());
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['g11_meta'][$post_id][$key] = $value;
	$GLOBALS['g11_meta_updates'][] = compact('post_id', 'key', 'value');
	return true;
}

function delete_post_meta(int $post_id, string $key): bool
{
	unset($GLOBALS['g11_meta'][$post_id][$key]);
	$GLOBALS['g11_meta_deletes'][] = compact('post_id', 'key');
	return true;
}

function add_post_meta(int $post_id, string $key, $value, bool $unique = false): bool
{
	$GLOBALS['g11_meta_adds'][] = compact('post_id', 'key', 'value', 'unique');
	return true;
}

function wp_update_post(array $post): int
{
	$GLOBALS['g11_post_updates'][] = $post;
	return (int) ($post['ID'] ?? 0);
}

function bvmgr_event_plan_flag_missing_vendor(int $plan_id, int $vendor_id, string $vendor_title): void
{
	$GLOBALS['g11_primary_flags'][] = compact('plan_id', 'vendor_id', 'vendor_title');
}

function bvmgr_event_plan_flag_missing_secondary_vendor(int $plan_id, int $vendor_id, string $vendor_title): void
{
	$GLOBALS['g11_secondary_flags'][] = compact('plan_id', 'vendor_id', 'vendor_title');
}

function bvmgr_add_admin_notice(string $message, string $type): void
{
	$GLOBALS['g11_notices'][] = compact('message', 'type');
}

function bvmgr_event_plan_allowed_statuses(string $context, array $args): array
{
	$GLOBALS['g11_allowed_status_calls'][] = compact('context', 'args');
	return $GLOBALS['g11_allowed_statuses'];
}

function bvmgr_vendor_booking_onboarding_get_settings(): array
{
	return $GLOBALS['g11_onboarding_settings'];
}

function bvmgr_event_plan_get_status(int $plan_id, string $context): string
{
	unset($context);
	return $GLOBALS['g11_plan_statuses'][$plan_id] ?? '';
}

function bvmgr_vendor_booking_onboarding_should_process_status(string $status, array $settings): bool
{
	unset($settings);
	return in_array($status, $GLOBALS['g11_process_statuses'], true);
}

function bvmgr_vendor_booking_onboarding_plan_targets(int $plan_id): array
{
	return $GLOBALS['g11_plan_targets'][$plan_id] ?? array();
}

function bvmgr_vendor_booking_onboarding_get_vendor_plan_status(int $plan_id, int $vendor_id): array
{
	return $GLOBALS['g11_vendor_plan_statuses'][$plan_id][$vendor_id] ?? array();
}

function bvmgr_vendor_booking_onboarding_send_reminder(int $plan_id, int $vendor_id): array
{
	$GLOBALS['g11_reminders'][] = compact('plan_id', 'vendor_id');
	return array('success' => true);
}

function current_user_can(string $capability): bool
{
	$GLOBALS['g11_capability_calls'][] = $capability;
	return $GLOBALS['g11_can_manage'];
}

function wp_die(string $message): void
{
	throw new RuntimeException($message);
}

function check_admin_referer(string $action): bool
{
	$GLOBALS['g11_nonce_checks'][] = $action;
	return true;
}

function nocache_headers(): void
{
	$GLOBALS['g11_nocache_calls']++;
}

function get_the_title(int $post_id): string
{
	return $GLOBALS['g11_titles'][$post_id] ?? '';
}

function get_permalink(int $post_id): string
{
	return 'https://example.test/event/' . $post_id;
}

function vms_vendor_profiles_today_ymd(): string
{
	return '2026-08-08';
}

function bvmgr_tec_is_cancelled_event(int $event_id): bool
{
	return !empty($GLOBALS['g11_cancelled'][$event_id]);
}

function bvmgr_format_local_ymd(string $ymd, string $format): string
{
	unset($format);
	return 'Formatted ' . $ymd;
}

function wp_reset_postdata(): void
{
	$GLOBALS['g11_reset_postdata_calls']++;
}

function g11_reset_runtime(): void
{
	$GLOBALS['g11_get_posts_queue'] = array();
	$GLOBALS['g11_get_posts_calls'] = array();
	$GLOBALS['g11_wp_query_queue'] = array();
	$GLOBALS['g11_wp_query_calls'] = array();
	$GLOBALS['g11_posts'] = array();
	$GLOBALS['g11_titles'] = array();
	$GLOBALS['g11_meta'] = array();
	$GLOBALS['g11_meta_updates'] = array();
	$GLOBALS['g11_meta_deletes'] = array();
	$GLOBALS['g11_meta_adds'] = array();
	$GLOBALS['g11_post_updates'] = array();
	$GLOBALS['g11_primary_flags'] = array();
	$GLOBALS['g11_secondary_flags'] = array();
	$GLOBALS['g11_notices'] = array();
	$GLOBALS['g11_allowed_status_calls'] = array();
	$GLOBALS['g11_allowed_statuses'] = array('published', 'ready');
	$GLOBALS['g11_onboarding_settings'] = array('enabled' => false, 'video_soft_requirement' => false, 'reminder_after_days' => 0, 'reminder_before_days' => 0);
	$GLOBALS['g11_plan_statuses'] = array();
	$GLOBALS['g11_process_statuses'] = array('ready', 'published');
	$GLOBALS['g11_plan_targets'] = array();
	$GLOBALS['g11_vendor_plan_statuses'] = array();
	$GLOBALS['g11_reminders'] = array();
	$GLOBALS['g11_can_manage'] = true;
	$GLOBALS['g11_capability_calls'] = array();
	$GLOBALS['g11_nonce_checks'] = array();
	$GLOBALS['g11_nocache_calls'] = 0;
	$GLOBALS['g11_cancelled'] = array();
	$GLOBALS['g11_reset_postdata_calls'] = 0;
	$GLOBALS['g11_now_ts'] = time();
}

eval(g11_extract_function($mirror_sources['vendors'], $function_names['vendors']));
eval(g11_extract_function($mirror_sources['payables'], $function_names['payables']));
eval(g11_extract_function($mirror_sources['onboarding'], $function_names['onboarding']));
$tax_export_function = g11_extract_function($mirror_sources['tax_export'], $function_names['tax_export']);
$tax_export_function = str_replace("\n\texit;\n", "\n\treturn;\n", $tax_export_function, $tax_exit_replacements);
g11_same(1, $tax_exit_replacements, 'Tax export test adapter must replace only the terminal exit.');
eval($tax_export_function);
eval(g11_extract_function($mirror_sources['profiles'], $function_names['profiles']));
eval(g11_extract_function($mirror_sources['vendor_category'], $function_names['vendor_category']));

g11_reset_runtime();
bvmgr_vendor_delete_revert_event_plans(0);
g11_same(array(), $GLOBALS['g11_get_posts_calls'], 'Invalid Vendor deletion must not query.');
$GLOBALS['g11_posts'][7] = new WP_Post(7, 'post');
bvmgr_vendor_delete_revert_event_plans(7);
g11_same(array(), $GLOBALS['g11_get_posts_calls'], 'Non-Vendor deletion must not query.');

g11_reset_runtime();
$GLOBALS['g11_posts'][7] = new WP_Post(7, 'vms_vendor');
$GLOBALS['g11_titles'][7] = '<b>Headliner</b>';
$GLOBALS['g11_get_posts_queue'] = array(false, false);
bvmgr_vendor_delete_revert_event_plans(7);
$vendor_primary_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => 'any',
	'fields' => 'ids',
	'posts_per_page' => -1,
	'no_found_rows' => true,
	'update_post_term_cache' => false,
	'update_post_meta_cache' => false,
	'meta_query' => array(array('key' => '_vms_band_vendor_id', 'value' => 7, 'compare' => '=', 'type' => 'NUMERIC')),
);
$vendor_secondary_args = $vendor_primary_args;
$vendor_secondary_args['meta_query'][0]['key'] = '_vms_secondary_vendor_id';
g11_same($vendor_primary_args, $GLOBALS['g11_get_posts_calls'][0]['args'], 'Vendor deletion primary query arguments changed.');
g11_same(false, $GLOBALS['g11_get_posts_calls'][0]['result'], 'Vendor deletion primary query failure result changed.');
g11_same($vendor_secondary_args, $GLOBALS['g11_get_posts_calls'][1]['args'], 'Vendor deletion secondary query arguments changed.');
g11_same(false, $GLOBALS['g11_get_posts_calls'][1]['result'], 'Vendor deletion secondary query failure result changed.');
g11_same(array(), $GLOBALS['g11_meta_updates'], 'Two failed Vendor deletion queries must fail closed.');

g11_reset_runtime();
$GLOBALS['g11_posts'] = array(
	7 => new WP_Post(7, 'vms_vendor'),
	101 => new WP_Post(101, 'vms_event_plan', 'publish'),
	102 => new WP_Post(102, 'vms_event_plan', 'draft'),
);
$GLOBALS['g11_titles'][7] = '<b>Headliner</b>';
$GLOBALS['g11_meta'] = array(
	101 => array('_vms_band_vendor_id' => 7),
	102 => array('_vms_band_vendor_id' => 0, '_vms_secondary_vendor_ids' => array(7, 9), '_vms_integrity_issue' => ''),
);
$GLOBALS['g11_get_posts_queue'] = array(array(101), array(102, 101, 0));
bvmgr_vendor_delete_revert_event_plans(7);
g11_same(array(101), $GLOBALS['g11_get_posts_calls'][0]['result'], 'Vendor deletion primary result capture changed.');
g11_same(array(102, 101, 0), $GLOBALS['g11_get_posts_calls'][1]['result'], 'Vendor deletion secondary result capture changed.');
g11_same(array(array('plan_id' => 101, 'vendor_id' => 7, 'vendor_title' => 'Headliner')), $GLOBALS['g11_primary_flags'], 'Primary Vendor deletion flag behavior changed.');
g11_same(array(array('plan_id' => 102, 'vendor_id' => 7, 'vendor_title' => 'Headliner')), $GLOBALS['g11_secondary_flags'], 'Secondary Vendor deletion flag behavior changed.');
g11_same(array(array('ID' => 101, 'post_status' => 'draft')), $GLOBALS['g11_post_updates'], 'Published plan downgrade behavior changed.');
g11_same(1, count($GLOBALS['g11_notices']), 'Vendor deletion should emit one summary notice.');

g11_reset_runtime();
$missing_input = bvmgr_payables_build_bills_for_export('', array());
g11_same(array('bills' => array(), 'warnings' => array('Missing event date and/or venues.')), $missing_input, 'Payables missing-input result changed.');
g11_same(array(), $GLOBALS['g11_get_posts_calls'], 'Payables missing input must not query.');

g11_reset_runtime();
$GLOBALS['g11_get_posts_queue'][] = false;
$payables_failure = bvmgr_payables_build_bills_for_export('2026-09-20', array(4, 0, 2), array('status_allow' => array('ready', 'cancelled')));
$payables_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => array('publish', 'draft', 'pending', 'private'),
	'posts_per_page' => -1,
	'fields' => 'ids',
	'orderby' => 'ID',
	'order' => 'ASC',
	'meta_query' => array(
		'relation' => 'AND',
		array('key' => '_vms_event_date', 'value' => '2026-09-20', 'compare' => '='),
		array('key' => '_vms_venue_id', 'value' => array(4, 2), 'compare' => 'IN', 'type' => 'NUMERIC'),
		array('key' => '_vms_event_plan_status', 'value' => array('ready'), 'compare' => 'IN'),
	),
);
g11_same($payables_args, $GLOBALS['g11_get_posts_calls'][0]['args'], 'Payables complete query arguments changed.');
g11_same(false, $GLOBALS['g11_get_posts_calls'][0]['result'], 'Payables query failure result changed.');
g11_same(array('bills' => array(), 'warnings' => array()), $payables_failure, 'Payables query failure must remain empty.');

g11_reset_runtime();
$GLOBALS['g11_get_posts_queue'][] = array(201);
$GLOBALS['g11_meta'][201]['_vms_venue_id'] = 0;
$payables_result = bvmgr_payables_build_bills_for_export('2026-09-20', array(4), array('status_allow' => array('published')));
g11_same(array(201), $GLOBALS['g11_get_posts_calls'][0]['result'], 'Payables non-empty query result capture changed.');
g11_same(array('Plan #201 is missing a venue link; skipped.'), $payables_result['warnings'], 'Payables missing-venue result changed.');
g11_same(array(), $payables_result['bills'], 'Payables missing-venue result must not create bills.');

g11_reset_runtime();
bvmgr_vendor_booking_onboarding_daily_runner();
g11_same(array(), $GLOBALS['g11_get_posts_calls'], 'Disabled onboarding runner must not query.');

g11_reset_runtime();
$GLOBALS['g11_onboarding_settings'] = array('enabled' => true, 'video_soft_requirement' => true, 'reminder_after_days' => 3, 'reminder_before_days' => 2);
$GLOBALS['g11_get_posts_queue'][] = false;
bvmgr_vendor_booking_onboarding_daily_runner();
$onboarding_args = $GLOBALS['g11_get_posts_calls'][0]['args'];
$onboarding_start = new DateTimeImmutable($onboarding_args['meta_query'][0]['value'][0], new DateTimeZone('UTC'));
$onboarding_end = new DateTimeImmutable($onboarding_args['meta_query'][0]['value'][1], new DateTimeZone('UTC'));
$onboarding_expected_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => array('publish', 'draft', 'private', 'pending'),
	'posts_per_page' => -1,
	'fields' => 'ids',
	'meta_key' => '_vms_event_date',
	'meta_query' => array(array(
		'key' => '_vms_event_date',
		'value' => array($onboarding_start->format('Y-m-d'), $onboarding_end->format('Y-m-d')),
		'compare' => 'BETWEEN',
		'type' => 'DATE',
	)),
	'no_found_rows' => true,
);
g11_same($onboarding_expected_args, $onboarding_args, 'Onboarding complete get_posts arguments changed.');
g11_same(365, (int) $onboarding_start->diff($onboarding_end)->format('%a'), 'Onboarding query must retain its fixed one-year window.');
g11_same(false, $GLOBALS['g11_get_posts_calls'][0]['result'], 'Onboarding query failure result changed.');
g11_same(array(), $GLOBALS['g11_reminders'], 'Failed onboarding query must not send reminders.');

g11_reset_runtime();
$GLOBALS['g11_onboarding_settings'] = array('enabled' => true, 'video_soft_requirement' => true, 'reminder_after_days' => 3, 'reminder_before_days' => 2);
$GLOBALS['g11_get_posts_queue'][] = array(0, 301, 302);
$GLOBALS['g11_plan_statuses'] = array(301 => 'ready', 302 => 'cancelled');
$GLOBALS['g11_meta'][301]['_vms_event_date'] = gmdate('Y-m-d', time() + (10 * DAY_IN_SECONDS));
$GLOBALS['g11_plan_targets'][301] = array(401 => array('is_headliner' => true));
$GLOBALS['g11_vendor_plan_statuses'][301][401] = array(
	'video_status' => 'needed',
	'initial_sent_at_gmt' => gmdate('Y-m-d H:i:s', $GLOBALS['g11_now_ts'] - (5 * DAY_IN_SECONDS)),
	'last_reminder_at_gmt' => '',
);
bvmgr_vendor_booking_onboarding_daily_runner();
g11_same(array(0, 301, 302), $GLOBALS['g11_get_posts_calls'][0]['result'], 'Onboarding query result capture changed.');
g11_same(array(array('plan_id' => 301, 'vendor_id' => 401)), $GLOBALS['g11_reminders'], 'Onboarding due-reminder behavior changed.');

g11_reset_runtime();
$GLOBALS['g11_can_manage'] = false;
$denied = false;
try {
	bvmgr_vendor_tax_export_csv_adminpost();
} catch (RuntimeException $exception) {
	$denied = $exception->getMessage() === 'Permission denied.';
}
g11_assert($denied, 'Tax export must retain its capability failure.');
g11_same(array(), $GLOBALS['g11_get_posts_calls'], 'Denied tax export must not query.');

g11_reset_runtime();
$GLOBALS['g11_get_posts_queue'][] = false;
ob_start();
bvmgr_vendor_tax_export_csv_adminpost();
$tax_failure_output = (string) ob_get_clean();
$tax_args = array(
	'post_type' => 'vms_vendor',
	'post_status' => 'any',
	'posts_per_page' => -1,
	'orderby' => 'title',
	'order' => 'ASC',
	'meta_query' => array(array('key' => '_vms_vendor_tax_profile_completed_at', 'compare' => 'EXISTS')),
	'fields' => 'ids',
);
g11_same($tax_args, $GLOBALS['g11_get_posts_calls'][0]['args'], 'Tax export complete query arguments changed.');
g11_same(false, $GLOBALS['g11_get_posts_calls'][0]['result'], 'Tax export failure result changed.');
g11_same(array('vms_vendor_tax_export_csv'), $GLOBALS['g11_nonce_checks'], 'Tax export nonce check changed.');
g11_same(1, $GLOBALS['g11_nocache_calls'], 'Tax export no-cache behavior changed.');
g11_contains('vendor_id', $tax_failure_output, 'Tax export failure must still produce the CSV header.');

g11_reset_runtime();
$GLOBALS['g11_get_posts_queue'][] = array(0, 501);
$GLOBALS['g11_titles'][501] = 'Vendor Five';
$GLOBALS['g11_meta'][501]['_vms_vendor_tax_profile_completed_at'] = 1723075200;
ob_start();
bvmgr_vendor_tax_export_csv_adminpost();
$tax_output = (string) ob_get_clean();
g11_same(array(0, 501), $GLOBALS['g11_get_posts_calls'][0]['result'], 'Tax export query result capture changed.');
g11_contains('Vendor Five', $tax_output, 'Tax export should retain valid Vendor rows.');

g11_reset_runtime();
g11_same(array(), vms_vendor_profiles_find_next_upcoming_event(0), 'Invalid Vendor profile lookup must remain empty.');
g11_same(array(), $GLOBALS['g11_wp_query_calls'], 'Invalid Vendor profile lookup must not query.');

g11_reset_runtime();
$GLOBALS['g11_wp_query_queue'][] = array('posts' => false);
$empty_profile = vms_vendor_profiles_find_next_upcoming_event(7);
$profile_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => array('publish', 'draft', 'pending', 'private'),
	'posts_per_page' => 12,
	'orderby' => 'meta_value',
	'order' => 'ASC',
	'meta_key' => '_vms_event_date',
	'meta_query' => array(
		'relation' => 'AND',
		array('key' => '_vms_band_vendor_id', 'value' => '7'),
		array('key' => '_vms_event_date', 'value' => '2026-08-08', 'compare' => '>=', 'type' => 'DATE'),
		array('key' => '_vms_tec_event_id', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC'),
	),
	'fields' => 'ids',
	'no_found_rows' => true,
);
g11_same($profile_args, $GLOBALS['g11_wp_query_calls'][0]['args'], 'Vendor Profile complete WP_Query arguments changed.');
g11_same(false, $GLOBALS['g11_wp_query_calls'][0]['posts'], 'Vendor Profile query failure result changed.');
g11_same(array(), $empty_profile, 'Vendor Profile query failure must remain empty.');
g11_same(1, $GLOBALS['g11_reset_postdata_calls'], 'Vendor Profile failure must still reset postdata.');

g11_reset_runtime();
$GLOBALS['g11_wp_query_queue'][] = array('posts' => array(0, 601, 602, 603, 604));
$GLOBALS['g11_meta'] = array(
	601 => array('_vms_tec_event_id' => 0),
	602 => array('_vms_tec_event_id' => 702),
	603 => array('_vms_tec_event_id' => 703),
	604 => array('_vms_tec_event_id' => 704, '_vms_event_date' => '2026-09-15'),
);
$GLOBALS['g11_posts'] = array(
	702 => new WP_Post(702, 'tribe_events', 'draft'),
	703 => new WP_Post(703, 'tribe_events', 'publish'),
	704 => new WP_Post(704, 'tribe_events', 'publish'),
);
$GLOBALS['g11_cancelled'][703] = true;
$GLOBALS['g11_titles'][704] = 'Valid Public Event';
$profile_result = vms_vendor_profiles_find_next_upcoming_event(7);
g11_same(array(0, 601, 602, 603, 604), $GLOBALS['g11_wp_query_calls'][0]['posts'], 'Vendor Profile query result capture changed.');
g11_same(
	array(
		'plan_id' => 604,
		'tec_event_id' => 704,
		'url' => 'https://example.test/event/704',
		'title' => 'Valid Public Event',
		'date' => '2026-09-15',
		'date_label' => 'Formatted 2026-09-15',
	),
	$profile_result,
	'Vendor Profile valid result changed.'
);

g11_reset_runtime();
g11_same(array(), bvmgr_vendor_categories_get_related_event_plan_ids(0), 'Invalid Vendor category lookup must remain empty.');
g11_same(array(), $GLOBALS['g11_get_posts_calls'], 'Invalid Vendor category lookup must not query.');

g11_reset_runtime();
$GLOBALS['g11_get_posts_queue'] = array(false, false);
$category_failure = bvmgr_vendor_categories_get_related_event_plan_ids(7);
$category_primary_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => 'any',
	'fields' => 'ids',
	'posts_per_page' => -1,
	'no_found_rows' => true,
	'update_post_term_cache' => false,
	'update_post_meta_cache' => false,
	'meta_query' => array(array('key' => '_vms_band_vendor_id', 'value' => 7, 'compare' => '=', 'type' => 'NUMERIC')),
);
$category_secondary_args = $category_primary_args;
$category_secondary_args['meta_query'][0]['key'] = '_vms_secondary_vendor_id';
g11_same($category_primary_args, $GLOBALS['g11_get_posts_calls'][0]['args'], 'Vendor category primary query arguments changed.');
g11_same(false, $GLOBALS['g11_get_posts_calls'][0]['result'], 'Vendor category primary failure result changed.');
g11_same($category_secondary_args, $GLOBALS['g11_get_posts_calls'][1]['args'], 'Vendor category secondary query arguments changed.');
g11_same(false, $GLOBALS['g11_get_posts_calls'][1]['result'], 'Vendor category secondary failure result changed.');
g11_same(array(), $category_failure, 'Vendor category query failures must fail closed.');

g11_reset_runtime();
$GLOBALS['g11_get_posts_queue'] = array(array(801, 802), array(802, 0, 803));
$category_result = bvmgr_vendor_categories_get_related_event_plan_ids(7);
g11_same(array(801, 802), $GLOBALS['g11_get_posts_calls'][0]['result'], 'Vendor category primary result capture changed.');
g11_same(array(802, 0, 803), $GLOBALS['g11_get_posts_calls'][1]['result'], 'Vendor category secondary result capture changed.');
g11_same(array(801, 802, 803), $category_result, 'Vendor category merged result changed.');

fwrite(STDOUT, "G11 vendor-tail meta-query remediation: PASS (Wave 4 rows 10 -> projected 0; K -2, Q -8)\n");
