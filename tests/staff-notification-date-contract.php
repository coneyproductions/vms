<?php

declare(strict_types=1);

$g15_plugin_root = dirname(__DIR__);

function g15_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function g15_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$g15_relative_file = 'includes/modules/staff-tasks/notifications.php';
$g15_mirror_file = $g15_plugin_root . '/' . $g15_relative_file;
$g15_sources = array('current' => file_get_contents($g15_mirror_file));
g15_assert_true(is_string($g15_sources['current']), 'Current notification source must be readable.');

$g15_helper_block = <<<'PHP'
if (!function_exists('bvmgr_tasks_notification_format_floating_local_datetime')) {
	function bvmgr_tasks_notification_format_floating_local_datetime(string $format, string $expression): string
	{
		$utc = new DateTimeZone('UTC');
		try {
			$datetime = trim($expression) === ''
				? new DateTimeImmutable('@0')
				: new DateTimeImmutable($expression, $utc);
		} catch (Exception) {
			$datetime = new DateTimeImmutable('@0');
		}

		return $datetime->setTimezone($utc)->format($format);
	}
}
PHP;

foreach ($g15_sources as $g15_tree => $g15_source) {
    g15_assert_same(0, preg_match_all('/(?<![A-Za-z0-9_])date\s*\(/', $g15_source), $g15_tree . ' must contain zero native date() calls.');
    g15_assert_same(0, preg_match_all('/phpcs:(?:ignore|disable)[^\n]*WordPress\.DateTime/i', $g15_source), $g15_tree . ' must not suppress DateTime findings.');
    g15_assert_true(str_contains($g15_source, $g15_helper_block), $g15_tree . ' must retain the floating UTC helper contract.');
    g15_assert_same(1, substr_count($g15_source, "new DateTimeZone('UTC')"), $g15_tree . ' helper must use one explicit UTC zone.');
    g15_assert_same(2, substr_count($g15_source, "new DateTimeImmutable('@0')"), $g15_tree . ' helper must preserve blank and invalid epoch fallbacks.');
    g15_assert_same(1, substr_count($g15_source, 'new DateTimeImmutable($expression, $utc)'), $g15_tree . ' helper must parse floating expressions in explicit UTC.');
    g15_assert_same(1, substr_count($g15_source, 'return $datetime->setTimezone($utc)->format($format);'), $g15_tree . ' helper must format in explicit UTC.');
    g15_assert_same(0, substr_count($g15_source, 'wp_date('), $g15_tree . ' must not introduce a site-zone double shift.');
    g15_assert_same(0, substr_count($g15_source, 'date_default_timezone_set('), $g15_tree . ' must not mutate process timezone.');

}

defined('ABSPATH') || define('ABSPATH', $g15_plugin_root . '/');
defined('MINUTE_IN_SECONDS') || define('MINUTE_IN_SECONDS', 60);

// Loading definitions may register hooks; this isolated helper test never dispatches them.
function add_action(...$args): bool { return true; }
function add_filter(...$args): bool { return true; }
function sanitize_key(string $key): string { return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)); }

require $g15_mirror_file;

$g15_original_timezone = date_default_timezone_get();
date_default_timezone_set('UTC');

$g15_legacy_utc = static function (string $format, string $expression): string {
    $timestamp = strtotime($expression);
    return gmdate($format, $timestamp === false ? 0 : $timestamp);
};

$g15_legacy_cases = array(
    array('Y-m-d H:i:s', '2026-03-08 01:30:00 +120 minutes'),
    array('Y-m-d H:i:s', '2026-11-01 00:30:00 +120 minutes'),
    array('Y-m-d 23:59:59', '2026-06-18 09:15:00'),
    array('Y-m-d H:i:s', '2026-06-18 09:15:00 +7 days'),
    array('Y-m-d H:i:s', '2026-06-18 09:15:00 +3 days'),
    array('Y-m-d', '2026-06-18 09:15:00'),
    array('Y-m-d H:i:s', ''),
    array('Y-m-d H:i:s', 'not-a-date'),
    array('Y-m-d H:i:s', '@0'),
    array('Y-m-d H:i:s', '1969-12-31 23:59:59'),
    array('Y-m-d H:i:s', '2026-02-30 10:00:00'),
    array('Y-m-d H:i:s', '2026-01-15 10:00:00-05:00 +120 minutes'),
);
foreach ($g15_legacy_cases as [$g15_format, $g15_expression]) {
    g15_assert_same(
        $g15_legacy_utc($g15_format, $g15_expression),
        bvmgr_tasks_notification_format_floating_local_datetime($g15_format, $g15_expression),
        'Helper must preserve the supported WordPress-UTC result for ' . var_export($g15_expression, true) . '.'
    );
}

g15_assert_same('1970-01-01 00:00:00', bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', ''), 'Blank direct input must preserve the epoch fallback.');
g15_assert_same('1970-01-01 00:00:00', bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', 'not-a-date'), 'Invalid direct input must preserve the epoch fallback.');
g15_assert_same('1970-01-01 00:00:00', bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '@0'), 'Explicit epoch input must remain valid.');
g15_assert_same('1969-12-31 23:59:59', bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '1969-12-31 23:59:59'), 'Pre-epoch input must remain representable.');
g15_assert_same('2026-03-02 10:00:00', bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '2026-02-30 10:00:00'), 'Lenient calendar normalization must remain supported.');
g15_assert_same('2026-01-15 17:00:00', bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '2026-01-15 10:00:00-05:00 +120 minutes'), 'Explicit-offset input must normalize to UTC.');

foreach (array('UTC', 'America/Chicago', 'Asia/Tokyo') as $g15_runtime_timezone) {
    date_default_timezone_set($g15_runtime_timezone);
    g15_assert_same('2026-03-08 03:30:00', bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '2026-03-08 01:30:00 +120 minutes'), $g15_runtime_timezone . ' must preserve nominal spring-forward wall arithmetic.');
    g15_assert_same('2026-11-01 02:30:00', bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '2026-11-01 00:30:00 +120 minutes'), $g15_runtime_timezone . ' must preserve nominal fall-back wall arithmetic.');
    g15_assert_same('1970-01-01 00:00:00', bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', 'invalid'), $g15_runtime_timezone . ' must preserve invalid-input epoch behavior.');
}

g15_assert_same('2026-06-18 23:59:59', bvmgr_tasks_notification_digest_window_end('today', '2026-06-18 09:15:00'), 'Today digest must end at local-wall end of day.');
g15_assert_same('2026-06-25 09:15:00', bvmgr_tasks_notification_digest_window_end('next7', '2026-06-18 09:15:00'), 'Next-seven digest must retain nominal wall time.');
g15_assert_same('2026-06-21 09:15:00', bvmgr_tasks_notification_digest_window_end('next3', '2026-06-18 09:15:00'), 'Next-three digest must retain nominal wall time.');
g15_assert_same('2026-06-21 09:15:00', bvmgr_tasks_notification_digest_window_end('unknown', '2026-06-18 09:15:00'), 'Unknown digest windows must retain the next-three default.');
g15_assert_same('1970-01-01 23:59:59', bvmgr_tasks_notification_digest_window_end('today', ''), 'Blank today input must preserve epoch end-of-day behavior.');
g15_assert_same('1970-01-01 23:59:59', bvmgr_tasks_notification_digest_window_end('today', 'invalid'), 'Invalid today input must preserve epoch end-of-day behavior.');
g15_assert_same('1970-01-08 00:00:00', bvmgr_tasks_notification_digest_window_end('next7', '@0'), 'Next-seven must preserve explicit epoch-relative behavior.');

$g15_blank_window_started = time() + (7 * 86400) - 2;
$g15_blank_window_result = strtotime(bvmgr_tasks_notification_digest_window_end('next7', '') . ' UTC');
$g15_blank_window_finished = time() + (7 * 86400) + 2;
g15_assert_true(is_int($g15_blank_window_result), 'Blank next-seven output must remain parseable.');
g15_assert_true($g15_blank_window_result >= $g15_blank_window_started && $g15_blank_window_result <= $g15_blank_window_finished, 'Blank next-seven must preserve current-time-relative behavior.');

date_default_timezone_set($g15_original_timezone);

fwrite(STDOUT, "PASS: Canonical floating notification dates and digest-window helpers; current delivery is covered by staff-tasks/authority.php and concurrency.php.\n");
