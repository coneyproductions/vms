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
$shadow_root = dirname($root, 2) . '/vms';
$artifact_code = 'WordPress.DateTime.RestrictedFunctions.date_date';
$artifact_rows = array(
	'includes/core/payables.php:88:16' => array('file' => 'includes/core/payables.php', 'occurrence' => 'payables_bill'),
	'includes/core/payables.php:119:12' => array('file' => 'includes/core/payables.php', 'occurrence' => 'payables_add'),
	'includes/portal/vendor-tax-profile.php:158:116' => array('file' => 'includes/portal/vendor-tax-profile.php', 'occurrence' => 'tax_received'),
	'includes/core/event-credits.php:844:84' => array('file' => 'includes/core/event-credits.php', 'occurrence' => 'credit_today'),
);

g15_same(4, count($artifact_rows), 'The G15 P3 artifact inventory must remain exactly four rows.');
g15_same(array($artifact_code => 4), array_count_values(array_fill(0, 4, $artifact_code)), 'The artifact-derived rule split changed.');

$artifact_path = '/tmp/wporg-dbzero-g14.qulnlt/plugin-check.strict.json';
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

function g15_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to find function: ' . $name);
	}
	$depth = 1;
	$quote = '';
	$escaped = false;
	for ($offset = $brace + 1, $length = strlen($source); $offset < $length; $offset++) {
		$character = $source[$offset];
		if ($quote !== '') {
			if ($escaped) {
				$escaped = false;
				continue;
			}
			if ($character === '\\') {
				$escaped = true;
				continue;
			}
			if ($character === $quote) {
				$quote = '';
			}
			continue;
		}
		if ($character === "'" || $character === '"') {
			$quote = $character;
			continue;
		}
		$depth += $character === '{' ? 1 : 0;
		$depth -= $character === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($offset - $start) + 1);
		}
	}
	throw new RuntimeException('Unable to parse function: ' . $name);
}

function g15_replace_once(string $source, string $search, string $replacement, string $message): string
{
	$source = str_replace($search, $replacement, $source, $count);
	g15_same(1, $count, $message);
	return $source;
}

$owned_files = array(
	'includes/core/payables.php',
	'includes/portal/vendor-tax-profile.php',
	'includes/core/event-credits.php',
);
$sources = array('mirror' => array(), 'shadow' => array());
foreach ($owned_files as $file) {
	foreach (array('mirror' => $root, 'shadow' => $shadow_root) as $tree => $tree_root) {
		$source = file_get_contents($tree_root . '/' . $file);
		g15_assert(is_string($source) && $source !== '', 'Unable to read owned source: ' . $tree . '/' . $file);
		$sources[$tree][$file] = $source;
	}
}

$current = array(
	'payables_bill' => "        \$ymd = gmdate('Ymd');",
	'tax_received' => "\t\t\t\t\t\tupdate_post_meta(\$vendor_id, \$k_recv, wp_date('Y-m-d', time(), wp_timezone()));",
	'tax_received_shadow' => "\t\t\t\t\t\t\tupdate_post_meta(\$vendor_id, \$k_recv, wp_date('Y-m-d', time(), wp_timezone()));",
	'credit_today' => "\t\t\t\$today = wp_date('Y-m-d', time(), wp_timezone());",
);

$covered_rows = array();
foreach ($artifact_rows as $row_id => $row) {
	foreach (array('mirror', 'shadow') as $tree) {
		$source = $sources[$tree][$row['file']];
		if ($row['occurrence'] === 'payables_add') {
			$function = g15_extract_function($source, 'vms_payables_add_days');
			g15_same(1, substr_count($function, "new DateTimeImmutable(\$ymd . ' 00:00:00', \$utc)"), 'Add-days must construct one immutable date with the explicit UTC object: ' . $tree);
			g15_same(1, substr_count($function, '$date = $date->setTimezone($utc);'), 'Add-days must normalize embedded timezone tokens back to UTC: ' . $tree);
			g15_same(1, substr_count($function, "return \$date->format('Y-m-d');"), 'Add-days must format the UTC immutable date once: ' . $tree);
		} else {
			$key = ($tree === 'shadow' && $row['occurrence'] === 'tax_received') ? 'tax_received_shadow' : $row['occurrence'];
			g15_same(1, substr_count($source, $current[$key]), 'Current occurrence must exist once: ' . $tree . '/' . $row_id);
		}
	}
	$covered_rows[$row_id] = true;
}
g15_same(array_keys($artifact_rows), array_keys($covered_rows), 'Every artifact row must map to one remediated occurrence.');

function g15_validate_no_date_suppressions(string $source): void
{
	if (preg_match('/phpcs:(?:disable|enable|ignoreFile)[^\r\n]*(?:WordPress\.DateTime|RestrictedFunctions\.date_date)/i', $source) === 1) {
		throw new RuntimeException('Block/file DateTime suppression is forbidden.');
	}
	if (preg_match('/phpcs:ignore[^\r\n]*(?:WordPress\.DateTime|RestrictedFunctions\.date_date)/i', $source) === 1) {
		throw new RuntimeException('DateTime ignores are forbidden for remediated boundaries.');
	}
}

foreach ($sources as $tree => $tree_sources) {
	$combined = implode("\n", $tree_sources);
	g15_same(0, preg_match_all('/(?<![A-Za-z0-9_])date\(/', $combined), 'Native date() must be zero across owned P3 files: ' . $tree);
	g15_validate_no_date_suppressions($combined);
}
foreach (array(
	'// phpcs:disable WordPress.DateTime',
	'// phpcs:enable WordPress.DateTime',
	'// phpcs:ignoreFile WordPress.DateTime',
	'// phpcs:ignore WordPress.DateTime -- broad category',
	'// phpcs:ignore WordPress.DateTime.RestrictedFunctions -- family',
	'// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date,WordPress.Security.EscapeOutput.OutputNotEscaped -- mixed',
) as $negative_directive) {
	$rejected = false;
	try {
		g15_validate_no_date_suppressions($sources['mirror']['includes/core/payables.php'] . "\n" . $negative_directive);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	g15_assert($rejected, 'DateTime suppression negative control was accepted.');
}

$historical = array(
	'payables_bill' => "        \$ymd = date('Ymd');",
	'tax_received' => "\t\t\t\t\t\tupdate_post_meta(\$vendor_id, \$k_recv, function_exists('wp_date') ? wp_date('Y-m-d', time(), wp_timezone()) : date('Y-m-d'));",
	'tax_received_shadow' => "\t\t\t\t\t\t\tupdate_post_meta(\$vendor_id, \$k_recv, function_exists('wp_date') ? wp_date('Y-m-d', time(), wp_timezone()) : date('Y-m-d'));",
	'credit_today' => "\t\t\t\$today = function_exists('wp_date') ? wp_date('Y-m-d', time(), wp_timezone()) : date('Y-m-d');",
);
$historical_add_days = <<<'PHP'
function vms_payables_add_days(string $ymd, int $days): string
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
$pre_hashes = array(
	'mirror' => array(
		'includes/core/payables.php' => 'a69575a326f8eca4cec46bb8943907aeb8f343e2eada49b6a3b0525d47005e6d',
		'includes/portal/vendor-tax-profile.php' => '0c7a7eb2d3a028c0a147a87792c3e22af68de2548bfd92256169ba9df6ae6945',
		'includes/core/event-credits.php' => '7339da181909451754cf007452104cdb903a5cbaa8f978e363679873deae9093',
	),
	'shadow' => array(
		'includes/core/payables.php' => 'a69575a326f8eca4cec46bb8943907aeb8f343e2eada49b6a3b0525d47005e6d',
		'includes/portal/vendor-tax-profile.php' => '276d52b01348cdd1c04598d8585c4f31ed474996e274cb5a91d5e71c919221ee',
		'includes/core/event-credits.php' => '7339da181909451754cf007452104cdb903a5cbaa8f978e363679873deae9093',
	),
);

$project_historical = static function (string $source, string $file, string $tree = 'mirror'): string {
	global $current, $historical, $historical_add_days;
	if ($file === 'includes/core/payables.php') {
		$source = g15_replace_once($source, $current['payables_bill'], $historical['payables_bill'], 'Payables bill projection changed.');
		return g15_replace_once($source, g15_extract_function($source, 'vms_payables_add_days'), $historical_add_days, 'Payables add-days projection changed.');
	}
	if ($file === 'includes/portal/vendor-tax-profile.php') {
		$key = $tree === 'shadow' ? 'tax_received_shadow' : 'tax_received';
		return g15_replace_once($source, $current[$key], $historical[$key], 'Vendor Tax projection changed: ' . $tree);
	}
	return g15_replace_once($source, $current['credit_today'], $historical['credit_today'], 'Event Credits projection changed.');
};

foreach ($sources as $tree => $tree_sources) {
	foreach ($tree_sources as $file => $source) {
		g15_same($pre_hashes[$tree][$file], hash('sha256', $project_historical($source, $file, $tree)), 'Immutable pre-edit projection changed: ' . $tree . '/' . $file);
	}
}

$mutation_anchors = array(
	'includes/core/payables.php' => array('return (float) $raw;', 'return 999.0;'),
	'includes/portal/vendor-tax-profile.php' => array("return 'POST' === \$request_method;", 'return false; // mutation control'),
	'includes/core/event-credits.php' => array("apply_filters('vms_event_credit_code_prefix', 'EVENT-CREDIT')", "apply_filters('vms_event_credit_code_prefix', 'MUTATED')"),
);
foreach ($mutation_anchors as $file => $mutation) {
	$mutated = g15_replace_once($sources['mirror'][$file], $mutation[0], $mutation[1], 'Mutation anchor changed: ' . $file);
	g15_assert(hash('sha256', $project_historical($mutated, $file)) !== $pre_hashes['mirror'][$file], 'Immutable projection accepted runtime drift: ' . $file);
}

g15_same($sources['mirror']['includes/core/payables.php'], $sources['shadow']['includes/core/payables.php'], 'Payables must retain full mirror/shadow parity.');
g15_same($sources['mirror']['includes/core/event-credits.php'], $sources['shadow']['includes/core/event-credits.php'], 'Event Credits must retain full mirror/shadow parity.');
g15_assert($sources['mirror']['includes/portal/vendor-tax-profile.php'] !== $sources['shadow']['includes/portal/vendor-tax-profile.php'], 'Vendor Tax whole-file divergence disappeared.');
g15_same(1, substr_count($sources['mirror']['includes/portal/vendor-tax-profile.php'], $current['tax_received']), 'Mirror Vendor Tax shared statement changed.');
g15_same(1, substr_count($sources['shadow']['includes/portal/vendor-tax-profile.php'], $current['tax_received']), 'Shadow Vendor Tax shared statement changed.');

$tax_export_source = file_get_contents($root . '/includes/admin/vendors/tax-export-csv.php');
g15_assert(is_string($tax_export_source), 'Unable to read deferred tax-export source.');
g15_same('81c8e94a51769026434c36b5c6d8a4db8b7a5e991a5daebdaa71638ad4763014', hash('sha256', $tax_export_source), 'Deferred tax-export source changed.');
g15_same(3, preg_match_all('/(?<![A-Za-z0-9_])date\(/', $tax_export_source), 'The three deferred tax-export date() calls must remain untouched.');

$GLOBALS['g15_site_timezone'] = new DateTimeZone('UTC');
$GLOBALS['g15_now'] = 0;
$GLOBALS['g15_updated_meta'] = array();
$GLOBALS['g15_post_meta'] = array();
$GLOBALS['g15_trace'] = array();
$GLOBALS['g15_filter_value'] = true;

function wp_timezone(): DateTimeZone
{
	return $GLOBALS['g15_site_timezone'];
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
	$timestamp = $timestamp ?? time();
	$timezone = $timezone ?? wp_timezone();
	return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
}

function get_post_field(string $field, int $post_id): string
{
	unset($field);
	return $post_id > 0 ? 'Main Hall' : '';
}

function sanitize_title($value): string
{
	return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', (string) $value), '-'));
}

$bill_function = g15_extract_function($sources['mirror']['includes/core/payables.php'], 'vms_payables_build_bill_no');
$bill_function = g15_replace_once(
	$bill_function,
	'function vms_payables_build_bill_no(string $event_date, int $venue_id, int $vendor_id): string',
	'function g15_payables_build_bill_no(string $event_date, int $venue_id, int $vendor_id, int $timestamp): string',
	'Bill test-function rename changed.'
);
$bill_function = g15_replace_once($bill_function, "gmdate('Ymd')", "gmdate('Ymd', \$timestamp)", 'Bill fallback clock injection changed.');
eval($bill_function);

$midnight_boundary = (new DateTimeImmutable('2026-03-08 00:30:00', new DateTimeZone('UTC')))->getTimestamp();
g15_same('VMS-main-hall-20260308-44', g15_payables_build_bill_no('2026-03-08', 9, 44, $midnight_boundary), 'Bill date digits changed.');
g15_same('VMS-main-hall-20260308-44', g15_payables_build_bill_no('20x26/03/08', 9, 44, $midnight_boundary), 'Bill digit normalization changed.');
date_default_timezone_set('America/Chicago');
g15_same('20260307', date('Ymd', $midnight_boundary), 'Historical local-midnight characterization changed.');
g15_same('VMS-main-hall-20260308-44', g15_payables_build_bill_no('bad', 9, 44, $midnight_boundary), 'Bill fallback must remain UTC at local midnight.');
date_default_timezone_set('Asia/Tokyo');
g15_same('VMS-venue-20260308-0', g15_payables_build_bill_no('2026-03-08T12:34', 0, 0, $midnight_boundary), 'Bill fallback changed under a second non-UTC runtime.');

$add_function = g15_extract_function($sources['mirror']['includes/core/payables.php'], 'vms_payables_add_days');
eval($add_function);

g15_same('', vms_payables_add_days('', 1), 'Empty payables date must fail closed.');
g15_same('', vms_payables_add_days('   ', -1), 'Trim-empty payables date must fail closed.');
g15_same('', vms_payables_add_days('not-a-date', 1), 'Malformed payables date must fail closed.');
g15_same('', vms_payables_add_days('@0', 1), 'Epoch timestamp syntax must fail before adding.');
g15_same('', vms_payables_add_days('1970-01-01', 1), 'Epoch-zero calendar date must fail before adding.');
g15_same('1969-12-31', vms_payables_add_days('1969-12-31', 0), 'Pre-epoch zero offset changed.');
g15_same('1969-12-30', vms_payables_add_days('1969-12-31', -1), 'Pre-epoch negative offset changed.');
g15_same('1970-01-01', vms_payables_add_days('1969-12-31', 1), 'Pre-epoch positive offset changed.');
g15_same('2024-03-01', vms_payables_add_days('2024-02-30', 0), 'Lenient normalization changed.');
g15_same('2026-03-08', vms_payables_add_days('2026-03-08', 0), 'Zero offset changed.');
g15_same('2026-03-09', vms_payables_add_days('2026-03-08', 1), 'Positive offset changed at nominal DST start.');
g15_same('2026-10-31', vms_payables_add_days('2026-11-01', -1), 'Negative offset changed at nominal DST end.');
g15_same('2026-03-07', vms_payables_add_days('2026-03-08 +14:00', 0), 'Embedded positive offset must re-normalize to the UTC instant before formatting.');
g15_same('2026-03-08', vms_payables_add_days('2026-03-08 +14:00', 1), 'Embedded positive offset must re-normalize to UTC before adding.');
g15_same('2026-03-08', vms_payables_add_days('2026-03-08 America/Chicago', 0), 'Embedded timezone name must re-normalize to UTC before formatting.');

$timezone_cases = array(
	array('2026-03-08', 1, '2026-03-09'),
	array('2026-11-01', -1, '2026-10-31'),
	array('2024-02-30', 2, '2024-03-03'),
	array('1969-12-31', 0, '1969-12-31'),
	array('1970-01-01', 9, ''),
	array('2026-03-08 +14:00', 0, '2026-03-07'),
);
foreach (array('UTC', 'America/Chicago', 'Asia/Tokyo') as $runtime_timezone) {
	date_default_timezone_set($runtime_timezone);
	foreach ($timezone_cases as $case) {
		g15_same($case[2], vms_payables_add_days($case[0], $case[1]), 'Add-days changed with PHP default timezone: ' . $runtime_timezone);
	}
}

$historical_add = g15_replace_once($historical_add_days, 'function vms_payables_add_days(', 'function g15_historical_payables_add_days(', 'Historical add-days rename changed.');
eval($historical_add);
date_default_timezone_set('UTC');
foreach (array(
	array('2026-03-08', 0), array('2026-03-08', 1), array('2026-11-01', -1),
	array('2024-02-30', 2), array('1969-12-31', -1), array('1970-01-01', 1),
	array('2026-03-08 +14:00', 0), array('2026-03-08 +14:00', 1),
) as $case) {
	g15_same(g15_historical_payables_add_days($case[0], $case[1]), vms_payables_add_days($case[0], $case[1]), 'WordPress-UTC legacy add-days behavior changed.');
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['g15_updated_meta'][] = array($post_id, $key, $value);
	return true;
}

$tax_function = g15_extract_function($sources['mirror']['includes/portal/vendor-tax-profile.php'], 'vms_vendor_portal_render_tax_profile');
$tax_error_start = strpos($tax_function, 'if (is_wp_error($file_id))');
$tax_success_start = $tax_error_start === false ? false : strpos($tax_function, '} else {', $tax_error_start);
$tax_stamp_position = strpos($tax_function, trim($current['tax_received']));
g15_assert($tax_error_start !== false && $tax_success_start !== false && $tax_stamp_position !== false, 'Unable to locate W-9 upload success boundary.');
g15_assert($tax_error_start < $tax_success_start && $tax_success_start < $tax_stamp_position, 'W-9 received stamp must remain in the successful upload branch.');
g15_same(0, substr_count(substr($tax_function, $tax_error_start, $tax_success_start - $tax_error_start), "wp_date('Y-m-d'"), 'W-9 error branch must not persist a received date.');

$tax_stamp_callback = eval(
	'return static function (int $vendor_id, string $k_recv, int $timestamp) {'
	. str_replace('time()', '$timestamp', trim($current['tax_received']))
	. ' return $GLOBALS["g15_updated_meta"]; };'
);
g15_assert($tax_stamp_callback instanceof Closure, 'Unable to build W-9 stamp callback.');
$GLOBALS['g15_site_timezone'] = new DateTimeZone('America/Chicago');
$GLOBALS['g15_updated_meta'] = array();
g15_same(array(array(71, '_received', '2026-03-07')), $tax_stamp_callback(71, '_received', $midnight_boundary), 'Successful W-9 persistence must use the site-local date.');

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $value));
}

function sanitize_text_field($value): string
{
	return trim(strip_tags((string) $value));
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	$GLOBALS['g15_trace'][] = array('meta', $post_id, $key);
	return $GLOBALS['g15_post_meta'][$post_id][$key] ?? '';
}

function apply_filters(string $hook, $value, ...$args)
{
	$GLOBALS['g15_trace'][] = array('filter', $hook, $value, $args);
	return $GLOBALS['g15_filter_value'];
}

function bvmgr_event_credit_meta_keys(): array
{
	return array('original_event_plan_id' => '_credit_original_plan');
}

function bvmgr_meta_key(string $object, string $field): string
{
	unset($object, $field);
	return '_event_status';
}

function bvmgr_cancellation_refund_product_role(int $product_id): string
{
	unset($product_id);
	return '';
}

$credit_function = g15_extract_function($sources['mirror']['includes/core/event-credits.php'], 'bvmgr_event_credit_product_is_eligible');
$credit_function = g15_replace_once($credit_function, 'function bvmgr_event_credit_product_is_eligible(', 'function g15_event_credit_product_is_eligible(', 'Event Credit test-function rename changed.');
$credit_function = g15_replace_once($credit_function, 'time()', '$GLOBALS[\'g15_now\']', 'Event Credit clock injection changed.');
eval($credit_function);

$reset_credit = static function (string $status, string $event_date, bool $filter_value = true): void {
	$GLOBALS['g15_post_meta'] = array(
		1 => array('_credit_original_plan' => 99),
		10 => array('_vms_event_plan_id' => 100, '_tribe_wooticket_for_event' => 200),
		100 => array('_event_status' => $status, '_vms_event_date' => $event_date),
	);
	$GLOBALS['g15_trace'] = array();
	$GLOBALS['g15_filter_value'] = $filter_value;
};
$filter_count = static function (): int {
	return count(array_filter($GLOBALS['g15_trace'], static fn(array $call): bool => $call[0] === 'filter'));
};
$GLOBALS['g15_site_timezone'] = new DateTimeZone('America/Chicago');
$GLOBALS['g15_now'] = $midnight_boundary;

$reset_credit('cancelled', '2026-03-08');
g15_same(false, g15_event_credit_product_is_eligible(1, 10), 'Cancelled Event Plan must remain ineligible.');
g15_same(0, $filter_count(), 'Cancelled eligibility must stop before filters.');

$reset_credit('published', '2026-03-06');
g15_same(false, g15_event_credit_product_is_eligible(1, 10), 'Past Event Plan must remain ineligible.');
g15_same(0, $filter_count(), 'Past-date eligibility must stop before filters.');

$reset_credit('published', '2026-03-07');
g15_same(true, g15_event_credit_product_is_eligible(1, 10), 'Equal site-local date must remain eligible.');
$filter_call = end($GLOBALS['g15_trace']);
g15_same(array('filter', 'vms_event_credit_product_is_eligible', true, array(1, 10, 100, 200)), $filter_call, 'Eligibility filter ordering or arguments changed.');

$reset_credit('published', '2026-03-08');
g15_same(true, g15_event_credit_product_is_eligible(1, 10), 'Future Event Plan must remain eligible.');

$reset_credit('published', '2026-3-6');
g15_same(true, g15_event_credit_product_is_eligible(1, 10), 'Invalid-regex date must bypass the lexical past-date gate.');
g15_same('filter', (string) (end($GLOBALS['g15_trace'])[0] ?? ''), 'Invalid-regex eligibility must reach the final filter.');

$reset_credit('published', '2026-03-08', false);
g15_same(false, g15_event_credit_product_is_eligible(1, 10), 'Final eligibility filter must retain veto authority.');
g15_same('filter', (string) (end($GLOBALS['g15_trace'])[0] ?? ''), 'Filter must remain the last successful-path operation.');

$reset_credit('published', '2026-03-08');
$GLOBALS['g15_post_meta'][10]['_vms_event_plan_id'] = 99;
g15_same(false, g15_event_credit_product_is_eligible(1, 10), 'Original Event Plan product must remain ineligible.');
g15_same(0, $filter_count(), 'Original-plan rejection must stop before filters.');

date_default_timezone_set('UTC');
fwrite(STDOUT, "PASS: G15 P3 exact four-row remediation, immutable projections, parity, payables dates, W-9 stamp, and Event Credit eligibility are covered.\n");
