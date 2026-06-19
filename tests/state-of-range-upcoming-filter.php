<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 4) . '/');
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		return $text;
	}
}

if (!function_exists('absint')) {
	function absint($maybeint): int
	{
		return abs((int) $maybeint);
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($key): string
	{
		$key = strtolower((string) $key);
		$key = preg_replace('/[^a-z0-9_\-]/', '', $key);
		return is_string($key) ? $key : '';
	}
}

if (!function_exists('get_bloginfo')) {
	function get_bloginfo(string $show = ''): string
	{
		if ($show === 'charset') {
			return 'UTF-8';
		}

		if ($show === 'name') {
			return 'Serenade Range';
		}

		return '';
	}
}

if (!function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags(string $text): string
	{
		return strip_tags($text);
	}
}

if (!function_exists('wp_specialchars_decode')) {
	function wp_specialchars_decode(string $text, int $quote_style = ENT_QUOTES): string
	{
		return htmlspecialchars_decode($text, $quote_style);
	}
}

if (!function_exists('wp_timezone')) {
	function wp_timezone(): DateTimeZone
	{
		return new DateTimeZone('America/Chicago');
	}
}

if (!function_exists('wp_date')) {
	function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
	{
		$timestamp = $timestamp ?? time();
		$date = new DateTimeImmutable('@' . $timestamp);
		if ($timezone instanceof DateTimeZone) {
			$date = $date->setTimezone($timezone);
		}

		return $date->format($format);
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('apply_filters')) {
	function apply_filters(string $hook_name, $value)
	{
		return $value;
	}
}

if (!function_exists('vms_ticket_integrity_sort_events')) {
	function vms_ticket_integrity_sort_events(array $events): array
	{
		usort(
			$events,
			static function (array $a, array $b): int {
				return absint($a['event_timestamp'] ?? 0) <=> absint($b['event_timestamp'] ?? 0);
			}
		);

		return $events;
	}
}

if (!function_exists('vms_ticket_integrity_open_issues')) {
	function vms_ticket_integrity_open_issues(array $issues): array
	{
		return $issues;
	}
}

if (!function_exists('vms_ticket_integrity_format_datetime')) {
	function vms_ticket_integrity_format_datetime(int $timestamp): string
	{
		if ($timestamp <= 0) {
			return 'Never';
		}

		return wp_date('Y-m-d g:i a', $timestamp, wp_timezone());
	}
}

require_once dirname(__DIR__) . '/includes/ticketing/ticket-integrity-daily-report.php';

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

try {
	$parseLocal = static function (string $value): int {
		$date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, wp_timezone());
		if (!$date instanceof DateTimeImmutable) {
			return 0;
		}

		return $date->getTimestamp();
	};

	$reportGeneratedAt = $parseLocal('2026-06-01 09:00:00');
	$pastEventAt = $parseLocal('2026-05-30 20:00:00');
	$sameDayEventAt = $parseLocal('2026-06-01 19:30:00');
	$futureEventAt = $parseLocal('2026-06-06 20:00:00');

	$assert($reportGeneratedAt > 0, 'Failed to resolve the fixture report timestamp.');
	$assert($pastEventAt > 0, 'Failed to resolve the past-event fixture timestamp.');
	$assert($sameDayEventAt > 0, 'Failed to resolve the same-day fixture timestamp.');
	$assert($futureEventAt > 0, 'Failed to resolve the future-event fixture timestamp.');

	$store = array(
		'events' => array(
			array(
				'event_title' => 'Past May Event',
				'event_timestamp' => $pastEventAt,
				'event_date_local' => vms_ticket_integrity_format_datetime($pastEventAt),
				'status' => 'green',
				'ticket_snapshots' => array(),
				'issues' => array(),
				'issue_summary' => 'No issues detected.',
			),
			array(
				'event_title' => 'Same Day June Event',
				'event_timestamp' => $sameDayEventAt,
				'event_date_local' => vms_ticket_integrity_format_datetime($sameDayEventAt),
				'status' => 'green',
				'ticket_snapshots' => array(),
				'issues' => array(),
				'issue_summary' => 'No issues detected.',
			),
			array(
				'event_title' => 'Future June Event & Friends',
				'event_timestamp' => $futureEventAt,
				'event_date_local' => vms_ticket_integrity_format_datetime($futureEventAt),
				'status' => 'green',
				'ticket_snapshots' => array(),
				'issues' => array(),
				'issue_summary' => 'No issues detected.',
			),
		),
		'summary' => array(
			'events_scanned' => 2,
			'green' => 2,
			'yellow' => 0,
			'red' => 0,
			'informational' => 0,
		),
		'last_scan' => array(
			'completed_at_gmt' => $reportGeneratedAt,
		),
		'report_meta' => array(
			'generated_at_gmt' => $reportGeneratedAt,
		),
	);

	$filteredEvents = vms_ticket_integrity_filter_state_of_range_events((array) $store['events'], $reportGeneratedAt);
	$assert(count($filteredEvents) === 2, 'Expected exactly two same-day/future events after filtering.');
	$filteredTitles = array_map(static function (array $event): string {
		return (string) ($event['event_title'] ?? '');
	}, $filteredEvents);
	$assert(in_array('Same Day June Event', $filteredTitles, true), 'Expected the same-day event to survive filtering.');
	$assert(in_array('Future June Event & Friends', $filteredTitles, true), 'Expected the future June event to survive filtering.');

	$email = vms_ticket_integrity_build_state_of_range_email($store);
	$body = (string) ($email['body'] ?? '');

	$assert($body !== '', 'Expected a rendered email body.');
	$assert(strpos($body, 'Past May Event') === false, 'Past event still appeared in the upcoming list.');
	$assert(strpos($body, 'Same Day June Event') !== false, 'Same-day event did not appear in the upcoming list.');
	$assert(strpos($body, 'Future June Event & Friends') !== false, 'Future event did not appear in the upcoming list.');
	$assert(strpos($body, '&amp;') === false, 'HTML entity leakage detected in plain-text output.');
	$assert(strpos($body, '&#36;') === false, 'Dollar sign entity leakage detected in plain-text output.');
	$assert(strpos($body, '$0.00') !== false, 'Expected plain-text money formatting to remain intact.');

	if (in_array('--print-body', $argv, true)) {
		fwrite(STDOUT, $body . "\n");
	}

	fwrite(STDOUT, "State of the Range upcoming-event filter test passed.\n");
	exit(0);
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . "\n");
	exit(1);
}
