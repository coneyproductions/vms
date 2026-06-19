<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 4) . '/');
}

if (!defined('HOUR_IN_SECONDS')) {
	define('HOUR_IN_SECONDS', 3600);
}

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}

$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_hooks'] = array();
$GLOBALS['vms_test_mail_should_send'] = true;
$GLOBALS['vms_test_sent_messages'] = array();
$GLOBALS['vms_test_log_events'] = array();
$GLOBALS['vms_test_scan_calls'] = 0;
$GLOBALS['vms_test_scan_result'] = null;

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		return $text;
	}
}

if (!function_exists('_n')) {
	function _n(string $single, string $plural, int $number, string $domain = ''): string
	{
		return $number === 1 ? $single : $plural;
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

if (!function_exists('sanitize_email')) {
	function sanitize_email($email): string
	{
		$email = trim((string) $email);
		return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		return trim((string) $value);
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
		unset($hook_name);
		return $value;
	}
}

if (!function_exists('add_action')) {
	function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
	{
		unset($priority, $accepted_args);
		$GLOBALS['vms_test_hooks'][$hook_name][] = $callback;
	}
}

if (!function_exists('remove_action')) {
	function remove_action(string $hook_name, callable $callback, int $priority = 10): void
	{
		unset($priority);
		$hooks = $GLOBALS['vms_test_hooks'][$hook_name] ?? array();
		foreach ($hooks as $index => $registered) {
			if ($registered === $callback) {
				unset($hooks[$index]);
			}
		}
		$GLOBALS['vms_test_hooks'][$hook_name] = array_values($hooks);
	}
}

if (!function_exists('get_option')) {
	function get_option(string $option, $default = false)
	{
		return array_key_exists($option, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$option] : $default;
	}
}

if (!function_exists('update_option')) {
	function update_option(string $option, $value, bool $autoload = false): bool
	{
		unset($autoload);
		$GLOBALS['vms_test_options'][$option] = $value;
		return true;
	}
}

if (!function_exists('wp_mail')) {
	function wp_mail(string $to, string $subject, string $message): bool
	{
		foreach ((array) ($GLOBALS['vms_test_hooks']['phpmailer_init'] ?? array()) as $callback) {
			$mailer = (object) array(
				'Mailer' => 'smtp',
				'Host' => 'smtp.example.test',
			);
			$callback($mailer);
		}

		if (empty($GLOBALS['vms_test_mail_should_send'])) {
			$error = new class()
			{
				public function get_error_message(): string
				{
					return 'simulated_mail_failure';
				}
			};
			foreach ((array) ($GLOBALS['vms_test_hooks']['wp_mail_failed'] ?? array()) as $callback) {
				$callback($error);
			}
			return false;
		}

		$GLOBALS['vms_test_sent_messages'][] = array(
			'to' => $to,
			'subject' => $subject,
			'message' => $message,
		);
		return true;
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

if (!function_exists('vms_ticket_integrity_get_settings')) {
	function vms_ticket_integrity_get_settings(): array
	{
		return array(
			'daily_report_enabled' => 1,
			'daily_report_recipient' => 'ops@example.test',
			'alert_recipient' => '',
		);
	}
}

if (!function_exists('vms_ticket_integrity_get_results_store')) {
	function vms_ticket_integrity_get_results_store(): array
	{
		return $GLOBALS['vms_test_results_store'];
	}
}

if (!function_exists('vms_ticket_integrity_scan_all')) {
	function vms_ticket_integrity_scan_all(array $args = array()): array
	{
		unset($args);
		$GLOBALS['vms_test_scan_calls']++;
		if (is_array($GLOBALS['vms_test_scan_result'] ?? null)) {
			return $GLOBALS['vms_test_scan_result'];
		}

		return array(
			'ok' => true,
			'store' => $GLOBALS['vms_test_results_store'],
		);
	}
}

if (!function_exists('vms_ticket_integrity_prepare_payment_gateway_health')) {
	function vms_ticket_integrity_prepare_payment_gateway_health(string $trigger = '', int $cache_ttl = 0): array
	{
		unset($trigger, $cache_ttl);
		return array();
	}
}

if (!function_exists('vms_ticket_integrity_log_event')) {
	function vms_ticket_integrity_log_event(string $type, string $message, array $context = array()): void
	{
		$GLOBALS['vms_test_log_events'][] = array(
			'type' => $type,
			'message' => $message,
			'context' => $context,
		);
	}
}

if (!function_exists('vms_ticket_integrity_begin_fatal_guard')) {
	function vms_ticket_integrity_begin_fatal_guard(string $type, array $context = array()): string
	{
		unset($type, $context);
		return 'guard';
	}
}

if (!function_exists('vms_ticket_integrity_end_fatal_guard')) {
	function vms_ticket_integrity_end_fatal_guard(string $guard_id): void
	{
		unset($guard_id);
	}
}

require_once dirname(__DIR__) . '/includes/ticketing/ticket-integrity-daily-report.php';

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$parseLocal = static function (string $value): int {
	$date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, wp_timezone());
	if (!$date instanceof DateTimeImmutable) {
		return 0;
	}

	return $date->getTimestamp();
};

$resetState = static function (): void {
	$GLOBALS['vms_test_sent_messages'] = array();
	$GLOBALS['vms_test_hooks'] = array();
	$GLOBALS['vms_test_mail_should_send'] = true;
	$GLOBALS['vms_test_log_events'] = array();
	$GLOBALS['vms_test_scan_calls'] = 0;
	$GLOBALS['vms_test_scan_result'] = null;
	vms_ticket_integrity_update_daily_report_state(array());
};

try {
	$generatedAt = $parseLocal('2026-06-02 09:00:00');
	$eventAt = $parseLocal('2026-06-06 20:00:00');
	$assert($generatedAt > 0, 'Failed to resolve generated-at fixture.');
	$assert($eventAt > 0, 'Failed to resolve event fixture.');

	$buildStore = static function (int $scanCompletedAt, array $overrides = array()) use ($eventAt, $generatedAt): array {
		$store = array(
			'events' => array(
				array(
					'event_title' => 'Future June Event',
					'event_timestamp' => $eventAt,
					'event_date_local' => vms_ticket_integrity_format_datetime($eventAt),
					'status' => 'green',
					'ticket_snapshots' => array(),
					'issues' => array(),
					'issue_summary' => 'No issues detected.',
				),
			),
			'summary' => array(
				'events_scanned' => 1,
				'green' => 1,
				'yellow' => 0,
				'red' => 0,
				'informational' => 0,
			),
			'last_scan' => array(
				'completed_at_gmt' => $scanCompletedAt,
			),
			'report_meta' => array(
				'generated_at_gmt' => $generatedAt,
			),
		);

		return array_replace_recursive($store, $overrides);
	};

	$GLOBALS['vms_test_results_store'] = $buildStore($generatedAt);

	$resetState();
	$staleScanAt = $generatedAt - (21 * HOUR_IN_SECONDS);
	$GLOBALS['vms_test_results_store'] = $buildStore($staleScanAt);
	$staleCron = vms_ticket_integrity_send_state_of_range_report(
		'cron',
		array(
			'mode' => 'test_cron_stale',
			'generated_at_gmt' => $generatedAt,
		)
	);
	$staleCronState = vms_ticket_integrity_get_daily_report_state();
	$assert(!empty($staleCron['ok']), 'Expected cron report to send from a stale snapshot.');
	$assert($GLOBALS['vms_test_scan_calls'] === 0, 'Cron report should not run a full inline scan.');
	$assert(($staleCronState['last_result'] ?? '') === 'send_success', 'Cron stale send should record send_success.');
	$assert(!empty($staleCronState['used_stale_snapshot']), 'Cron stale send should record used_stale_snapshot.');
	$assert(count((array) $GLOBALS['vms_test_sent_messages']) === 1, 'Cron stale send should call wp_mail once.');
	$assert(strpos((string) ($GLOBALS['vms_test_sent_messages'][0]['message'] ?? ''), 'using the last available integrity snapshot') !== false, 'Cron stale send should include a visible stale-data notice.');

	$resetState();
	$GLOBALS['vms_test_results_store'] = array(
		'events' => array(),
		'summary' => array(
			'events_scanned' => 0,
			'green' => 0,
			'yellow' => 0,
			'red' => 0,
			'informational' => 0,
		),
		'last_scan' => array(
			'completed_at_gmt' => 0,
		),
		'report_meta' => array(
			'generated_at_gmt' => $generatedAt,
		),
	);
	$noSnapshot = vms_ticket_integrity_send_state_of_range_report(
		'cron',
		array(
			'mode' => 'test_cron_no_snapshot',
			'generated_at_gmt' => $generatedAt,
		)
	);
	$noSnapshotState = vms_ticket_integrity_get_daily_report_state();
	$assert(empty($noSnapshot['ok']), 'Expected cron report to fail fast without a usable snapshot.');
	$assert($GLOBALS['vms_test_scan_calls'] === 0, 'Cron no-snapshot failure should not run an inline scan.');
	$assert(($noSnapshotState['last_result'] ?? '') === 'skipped_no_snapshot', 'Cron no-snapshot failure should record skipped_no_snapshot.');
	$assert(($noSnapshotState['last_status'] ?? '') === 'failed', 'Cron no-snapshot failure should record failed status.');
	$assert(($noSnapshotState['last_error'] ?? '') === 'no_usable_snapshot', 'Cron no-snapshot failure should record no_usable_snapshot.');
	$assert(count((array) $GLOBALS['vms_test_sent_messages']) === 0, 'Cron no-snapshot failure must not call wp_mail.');

	$resetState();
	$GLOBALS['vms_test_results_store'] = array(
		'events' => array(),
		'summary' => array(),
		'last_scan' => array(
			'completed_at_gmt' => 0,
		),
		'report_meta' => array(
			'generated_at_gmt' => $generatedAt,
		),
	);
	$GLOBALS['vms_test_scan_result'] = array(
		'ok' => false,
		'message' => 'scan_failed',
	);
	$manualScanFailure = vms_ticket_integrity_send_state_of_range_report(
		'manual',
		array(
			'mode' => 'test_manual_scan_failure',
			'generated_at_gmt' => $generatedAt,
		)
	);
	$manualScanFailureState = vms_ticket_integrity_get_daily_report_state();
	$assert(empty($manualScanFailure['ok']), 'Expected manual report to fail when the inline refresh scan fails without a usable snapshot.');
	$assert($GLOBALS['vms_test_scan_calls'] === 1, 'Manual no-snapshot failure should attempt one inline refresh scan.');
	$assert(($manualScanFailureState['last_result'] ?? '') === 'skipped_scan_failed', 'Manual scan failure should record skipped_scan_failed.');
	$assert(($manualScanFailureState['last_error'] ?? '') === 'scan_failed', 'Manual scan failure should preserve the scan error.');
	$assert(count((array) $GLOBALS['vms_test_sent_messages']) === 0, 'Manual scan failure must not call wp_mail.');
	$assert(
		count(
			array_filter(
				(array) $GLOBALS['vms_test_log_events'],
				static function (array $entry): bool {
					return ($entry['type'] ?? '') === 'daily_report_skipped_scan_failed';
				}
			)
		) === 1,
		'Manual scan failure should log daily_report_skipped_scan_failed exactly once.'
	);

	$resetState();
	$GLOBALS['vms_test_results_store'] = $buildStore($generatedAt);
	$dryRun = vms_ticket_integrity_send_state_of_range_report(
		'manual',
		array(
			'dry_run' => true,
			'mode' => 'test_dry_run',
			'generated_at_gmt' => $generatedAt,
		)
	);
	$dryRunState = vms_ticket_integrity_get_daily_report_state();
	$assert(!empty($dryRun['ok']), 'Expected dry-run render to succeed.');
	$assert(($dryRunState['last_result'] ?? '') === 'dry_run_rendered', 'Dry-run should record dry_run_rendered result.');
	$assert(absint($dryRunState['last_successful_send_at'] ?? 0) === 0, 'Dry-run should not mark a successful send timestamp.');
	$assert(absint($dryRunState['last_sent_gmt'] ?? 0) === 0, 'Dry-run should not set the legacy sent timestamp.');
	$assert(count((array) $GLOBALS['vms_test_sent_messages']) === 0, 'Dry-run should not call wp_mail.');

	$resetState();
	$GLOBALS['vms_test_mail_should_send'] = false;
	$failure = vms_ticket_integrity_send_state_of_range_report(
		'manual',
		array(
			'mode' => 'test_failure',
			'generated_at_gmt' => $generatedAt,
		)
	);
	$failureState = vms_ticket_integrity_get_daily_report_state();
	$assert(empty($failure['ok']), 'Expected simulated mail failure to return not-ok.');
	$assert(($failureState['last_result'] ?? '') === 'send_failed', 'Failed send should record send_failed result.');
	$assert(($failureState['last_status'] ?? '') === 'failed', 'Failed send should record failed status.');
	$assert(absint($failureState['last_successful_send_at'] ?? 0) === 0, 'Failed send must not mark a successful send timestamp.');
	$assert(($failureState['last_error'] ?? '') === 'simulated_mail_failure', 'Failed send should capture the mailer error.');

	$resetState();
	$success = vms_ticket_integrity_send_state_of_range_report(
		'manual',
		array(
			'mode' => 'test_success',
			'generated_at_gmt' => $generatedAt,
		)
	);
	$successState = vms_ticket_integrity_get_daily_report_state();
	$assert(!empty($success['ok']), 'Expected simulated mail send to succeed.');
	$assert(($successState['last_result'] ?? '') === 'send_success', 'Successful send should record send_success result.');
	$assert(($successState['last_status'] ?? '') === 'sent', 'Successful send should record sent status.');
	$assert(absint($successState['last_successful_send_at'] ?? 0) > 0, 'Successful send should record a successful-send timestamp.');
	$assert(absint($successState['last_sent_gmt'] ?? 0) === absint($successState['last_successful_send_at'] ?? 0), 'Legacy sent timestamp should stay aligned with successful send.');
	$assert(($successState['last_mailer'] ?? '') === 'smtp:smtp.example.test', 'Successful send should capture the mailer details.');
	$assert(count((array) $GLOBALS['vms_test_sent_messages']) === 1, 'Successful send should call wp_mail exactly once.');

	fwrite(STDOUT, "State of the Range delivery-state test passed.\n");
	exit(0);
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . "\n");
	exit(1);
}
