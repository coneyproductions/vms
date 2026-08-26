<?php
defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

if (!class_exists('BVMGR_CLI_State_Of_Range_Command')) {
	class BVMGR_CLI_State_Of_Range_Command
	{
		/**
		 * Show State of the Range scheduling and delivery status.
		 *
		 * ## OPTIONS
		 *
		 * [--format=<format>]
		 * : Output format. Options: summary, json. Default: summary.
		 *
		 * ## EXAMPLES
		 *
		 *     wp vms state-of-range status
		 *     wp vms state-of-range status --format=json
		 *
		 * @subcommand status
		 * @when after_wp_load
		 *
		 * @param array<int,string> $args
		 * @param array<string,string> $assoc_args
		 */
		public function status(array $args, array $assoc_args): void
		{
			unset($args);
			$status = $this->collect_status();
			$format = sanitize_key((string) ($assoc_args['format'] ?? 'summary'));

			if ($format === 'json') {
				WP_CLI::line(wp_json_encode($status, JSON_PRETTY_PRINT));
				return;
			}

			foreach ($status as $label => $value) {
				if (is_array($value)) {
					$value = implode(', ', array_map('strval', $value));
				}
				WP_CLI::log($label . ': ' . (string) $value);
			}
		}

		/**
		 * Render the report without sending email.
		 *
		 * ## OPTIONS
		 *
		 * [--date=<YYYY-MM-DD>]
		 * : Local site date to render against. Defaults to today.
		 *
		 * [--dry-run]
		 * : Accepted for compatibility; this command always performs a dry run and never sends mail.
		 *
		 * [--print-body]
		 * : Print the rendered plain-text body.
		 *
		 * [--to=<email>]
		 * : Optional recipient override for preview metadata only.
		 *
		 * ## EXAMPLES
		 *
		 *     wp vms state-of-range render --dry-run
		 *     wp vms state-of-range render --date=2026-06-02 --print-body
		 *
		 * @subcommand render
		 * @when after_wp_load
		 *
		 * @param array<int,string> $args
		 * @param array<string,string> $assoc_args
		 */
		public function render(array $args, array $assoc_args): void
		{
			unset($args);
			$this->assert_helpers_available();

			$generated_at = $this->resolve_generated_at($assoc_args);
			$recipient = sanitize_email((string) ($assoc_args['to'] ?? ''));
			$result = vms_ticket_integrity_send_state_of_range_report(
				'cli',
				array(
					'dry_run' => true,
					'mode' => 'cli_render',
					'generated_at_gmt' => $generated_at,
					'recipient' => $recipient,
				)
			);
			if (empty($result['ok'])) {
				WP_CLI::error((string) ($result['message'] ?? 'State of the Range dry run failed.'));
			}

			$email = is_array($result['email'] ?? null) ? $result['email'] : array();
			WP_CLI::success('State of the Range dry run rendered successfully.');
			WP_CLI::log('Generated: ' . $this->format_timestamp($generated_at));
			WP_CLI::log('Subject: ' . (string) ($email['subject'] ?? ''));
			if (!empty($assoc_args['print-body'])) {
				WP_CLI::line('');
				WP_CLI::line((string) ($email['body'] ?? ''));
			}
		}

		/**
		 * Send a test report to an explicit recipient.
		 *
		 * ## OPTIONS
		 *
		 * --to=<email>
		 * : Recipient for the test send.
		 *
		 * [--date=<YYYY-MM-DD>]
		 * : Local site date to render against. Defaults to today.
		 *
		 * ## EXAMPLES
		 *
		 *     wp vms state-of-range send-test --to=admin@example.com
		 *
		 * @subcommand send-test
		 * @when after_wp_load
		 *
		 * @param array<int,string> $args
		 * @param array<string,string> $assoc_args
		 */
		public function send_test(array $args, array $assoc_args): void
		{
			unset($args);
			$this->assert_helpers_available();

			$recipient = sanitize_email((string) ($assoc_args['to'] ?? ''));
			if ($recipient === '') {
				WP_CLI::error('A valid --to email address is required.');
			}

			$generated_at = $this->resolve_generated_at($assoc_args);
			$result = vms_ticket_integrity_send_state_of_range_report(
				'cli',
				array(
					'mode' => 'cli_test',
					'recipient' => $recipient,
					'generated_at_gmt' => $generated_at,
				)
			);
			if (empty($result['ok'])) {
				WP_CLI::error((string) ($result['message'] ?? 'State of the Range test send failed.'));
			}

			WP_CLI::success('State of the Range test email sent to ' . $recipient . '.');
		}

		/**
		 * Repair and refresh the scheduled daily hooks.
		 *
		 * ## EXAMPLES
		 *
		 *     wp vms state-of-range reschedule
		 *
		 * @subcommand reschedule
		 * @when after_wp_load
		 *
		 * @param array<int,string> $args
		 * @param array<string,string> $assoc_args
		 */
		public function reschedule(array $args, array $assoc_args): void
		{
			unset($args, $assoc_args);
			if (!function_exists('vms_ticket_integrity_maybe_schedule_cron')) {
				WP_CLI::error('Cron scheduling helper is unavailable.');
			}

			vms_ticket_integrity_maybe_schedule_cron();
			$status = $this->collect_status();
			WP_CLI::success('State of the Range schedules refreshed.');
			WP_CLI::log('Next scheduled run: ' . (string) ($status['next_scheduled_run'] ?? 'Never'));
			WP_CLI::log('Scheduled hook count: ' . (string) ($status['scheduled_hook_count'] ?? '0'));
		}

		/**
		 * @return array<string,mixed>
		 */
		private function collect_status(): array
		{
			$this->assert_helpers_available();

			$snapshot = vms_ticket_integrity_daily_report_status_snapshot();
			$state = is_array($snapshot['state'] ?? null) ? $snapshot['state'] : array();

			return array(
				'hook' => (string) ($snapshot['hook'] ?? 'vms_ticket_integrity_daily_report'),
				'expected_local_time' => (string) ($snapshot['expected_local_time'] ?? '06:05'),
				'next_scheduled_run' => (string) ($snapshot['next_scheduled_run_local'] ?? 'Never'),
				'scheduled_hook_count' => absint($snapshot['scheduled_hook_count'] ?? 0),
				'scheduled_timestamps' => array_map(array($this, 'format_timestamp_value'), (array) ($snapshot['scheduled_timestamps'] ?? array())),
				'last_scheduled_run' => $this->format_timestamp(absint($state['last_scheduled_run_at'] ?? 0)),
				'last_render_finished' => $this->format_timestamp(absint($state['last_render_finished_at'] ?? 0)),
				'last_send_attempt' => $this->format_timestamp(absint($state['last_send_attempt_at'] ?? 0)),
				'last_successful_send' => $this->format_timestamp(absint($state['last_successful_send_at'] ?? 0)),
				'last_result' => (string) ($state['last_result'] ?? ''),
				'last_error' => (string) ($state['last_error'] ?? ''),
				'last_recipient' => (string) ($state['last_recipient'] ?? ''),
				'last_subject' => (string) ($state['last_subject'] ?? ''),
				'last_mailer' => (string) ($state['last_mailer'] ?? ''),
				'last_trigger' => (string) ($state['last_trigger'] ?? ''),
				'last_mode' => (string) ($state['last_mode'] ?? ''),
				'used_stale_snapshot' => !empty($state['used_stale_snapshot']) ? 'yes' : 'no',
			);
		}

		/**
		 * @param array<string,string> $assoc_args
		 */
		private function resolve_generated_at(array $assoc_args): int
		{
			$date = trim((string) ($assoc_args['date'] ?? ''));
			if ($date === '') {
				return time();
			}

			$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
			$generated = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 09:00:00', $tz);
			if (!$generated instanceof DateTimeImmutable) {
				WP_CLI::error('Invalid --date value. Expected YYYY-MM-DD.');
			}

			return $generated->getTimestamp();
		}

		private function format_timestamp(int $timestamp): string
		{
			if (function_exists('vms_ticket_integrity_format_datetime')) {
				return vms_ticket_integrity_format_datetime($timestamp);
			}

			if ($timestamp <= 0) {
				return 'Never';
			}

			return wp_date('Y-m-d H:i:s', $timestamp, wp_timezone());
		}

		/**
		 * @param mixed $timestamp
		 */
		private function format_timestamp_value($timestamp): string
		{
			return $this->format_timestamp(absint($timestamp));
		}

		private function assert_helpers_available(): void
		{
			if (!function_exists('vms_ticket_integrity_daily_report_status_snapshot') || !function_exists('vms_ticket_integrity_send_state_of_range_report')) {
				WP_CLI::error('State of the Range helpers are unavailable.');
			}
		}
	}

	WP_CLI::add_command('vms state-of-range', 'BVMGR_CLI_State_Of_Range_Command');
}
