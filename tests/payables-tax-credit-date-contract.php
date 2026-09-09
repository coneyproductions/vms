<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
$GLOBALS['g15_assertions'] = 0;
// Current runtime contract. Historical scanner provenance is a separate, hash-bound gate.
// All IDs and metadata below are synthetic in-memory doubles; no WordPress/database boot.

function g15_assert(bool $condition, string $message): void
{
	$GLOBALS['g15_assertions']++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g15_same($expected, $actual, string $message): void
{
	g15_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

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

$root = dirname(__DIR__);
$sources = array('mirror' => array());
foreach (array('includes/core/payables.php', 'includes/portal/vendor-tax-profile.php', 'includes/core/event-credits.php') as $file) {
    $source = file_get_contents($root . '/' . $file);
    g15_assert(is_string($source) && $source !== '', 'Current source must be readable: ' . $file);
    $sources['mirror'][$file] = $source;
}
$current = array('tax_received' => "update_post_meta(\$vendor_id, \$k_recv, wp_date('Y-m-d', time(), wp_timezone()));");
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

$bill_function = g15_extract_function($sources['mirror']['includes/core/payables.php'], 'bvmgr_payables_build_bill_no');
$bill_function = g15_replace_once(
	$bill_function,
	'function bvmgr_payables_build_bill_no(string $event_date, int $venue_id, int $vendor_id): string',
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

$add_function = g15_extract_function($sources['mirror']['includes/core/payables.php'], 'bvmgr_payables_add_days');
eval($add_function);

g15_same('', bvmgr_payables_add_days('', 1), 'Empty payables date must fail closed.');
g15_same('', bvmgr_payables_add_days('   ', -1), 'Trim-empty payables date must fail closed.');
g15_same('', bvmgr_payables_add_days('not-a-date', 1), 'Malformed payables date must fail closed.');
g15_same('', bvmgr_payables_add_days('@0', 1), 'Epoch timestamp syntax must fail before adding.');
g15_same('', bvmgr_payables_add_days('1970-01-01', 1), 'Epoch-zero calendar date must fail before adding.');
g15_same('1969-12-31', bvmgr_payables_add_days('1969-12-31', 0), 'Pre-epoch zero offset changed.');
g15_same('1969-12-30', bvmgr_payables_add_days('1969-12-31', -1), 'Pre-epoch negative offset changed.');
g15_same('1970-01-01', bvmgr_payables_add_days('1969-12-31', 1), 'Pre-epoch positive offset changed.');
g15_same('2024-03-01', bvmgr_payables_add_days('2024-02-30', 0), 'Lenient normalization changed.');
g15_same('2026-03-08', bvmgr_payables_add_days('2026-03-08', 0), 'Zero offset changed.');
g15_same('2026-03-09', bvmgr_payables_add_days('2026-03-08', 1), 'Positive offset changed at nominal DST start.');
g15_same('2026-10-31', bvmgr_payables_add_days('2026-11-01', -1), 'Negative offset changed at nominal DST end.');
g15_same('2026-03-07', bvmgr_payables_add_days('2026-03-08 +14:00', 0), 'Embedded positive offset must re-normalize to the UTC instant before formatting.');
g15_same('2026-03-08', bvmgr_payables_add_days('2026-03-08 +14:00', 1), 'Embedded positive offset must re-normalize to UTC before adding.');
g15_same('2026-03-08', bvmgr_payables_add_days('2026-03-08 America/Chicago', 0), 'Embedded timezone name must re-normalize to UTC before formatting.');

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
		g15_same($case[2], bvmgr_payables_add_days($case[0], $case[1]), 'Add-days changed with PHP default timezone: ' . $runtime_timezone);
	}
}

$historical_add = g15_replace_once($historical_add_days, 'function bvmgr_payables_add_days(', 'function g15_historical_payables_add_days(', 'Historical add-days rename changed.');
eval($historical_add);
date_default_timezone_set('UTC');
foreach (array(
	array('2026-03-08', 0), array('2026-03-08', 1), array('2026-11-01', -1),
	array('2024-02-30', 2), array('1969-12-31', -1), array('1970-01-01', 1),
	array('2026-03-08 +14:00', 0), array('2026-03-08 +14:00', 1),
) as $case) {
	g15_same(g15_historical_payables_add_days($case[0], $case[1]), bvmgr_payables_add_days($case[0], $case[1]), 'WordPress-UTC legacy add-days behavior changed.');
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['g15_updated_meta'][] = array($post_id, $key, $value);
	return true;
}

$tax_function = g15_extract_function($sources['mirror']['includes/portal/vendor-tax-profile.php'], 'bvmgr_vendor_portal_render_tax_profile');
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
fwrite(STDOUT, "PASS: Current payables dates, W-9 stamp, Event Credit eligibility and legacy UTC characterization; {$GLOBALS['g15_assertions']} assertions.\n");
