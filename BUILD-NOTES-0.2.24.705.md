# VMS 0.2.24.705

## Scope

- Harden the VMS State of the Range daily email/report without changing the underlying Ticket Integrity scan scope or Event Plan performance paths.
- Make scheduling failures, render failures, send failures, and stale-lock interference observable from VMS itself.
- Add safe local/admin and WP-CLI diagnostics that do not send mail unless an operator explicitly chooses a test send.

## Root cause addressed

- The daily report is scheduled directly by VMS on a WP-Cron hook and sent through `wp_mail()`, not by MailPoet queue automation.
- The existing schedule code only checked whether a hook existed, so duplicate hooks, wrong recurrences, or DST/local-time drift could persist indefinitely.
- The existing report state only tracked a minimal sent/attempted snapshot, which made it hard to distinguish “never invoked,” “rendered but not sent,” and “send attempted but mail handoff failed.”
- The scan refresh path relied on a transient lock with no explicit stale-lock recovery when an expired/invalid value lingered.

## Behavior changes

- Added compact delivery-state tracking for State of the Range:
  - `last_scheduled_run_at`
  - `last_render_started_at`
  - `last_render_finished_at`
  - `last_send_attempt_at`
  - `last_successful_send_at`
  - `last_recipient`
  - `last_subject`
  - `last_mailer`
  - `last_result`
  - `last_error`
  - `next_scheduled_run_at`
- Kept legacy state keys synchronized so existing data is preserved and older installs normalize forward cleanly.
- Added daily-hook self-healing so the scan/report schedules are re-created when duplicates exist, recurrence is wrong, or the stored local wall-clock time drifts from the intended `03:17` / `06:05` site-time anchors.
- Added explicit stale scan-lock recovery when the stored lock is invalid or older than the lock TTL plus a short grace window.
- Added Ticket Integrity admin diagnostics:
  - State of the Range status panel
  - preview panel
  - preview today’s report
  - dry-run diagnostic
  - send test to admin
- Added WP-CLI helpers:
  - `wp vms state-of-range status`
  - `wp vms state-of-range render --date=YYYY-MM-DD --dry-run`
  - `wp vms state-of-range send-test --to=email@example.com`
  - `wp vms state-of-range reschedule`

## Files changed

- `includes/ticketing/ticket-integrity-daily-report.php`
- `includes/ticketing/ticket-integrity-cron.php`
- `includes/ticketing/ticket-integrity-monitor.php`
- `includes/admin/ticket-integrity-page.php`
- `includes/core/cli/state-of-range.php`
- `includes/core/load.php`
- `tests/state-of-range-upcoming-filter.php`
- `tests/state-of-range-delivery-state.php`
- `tests/ticket-integrity-scan-lock.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.705.md`
- `vms-test-plan-0.2.24.705.md`
- `docs/CODEX-HANDOFF-0.2.24.705.md`

## Local verification performed

- `php -l vms/includes/ticketing/ticket-integrity-daily-report.php`
- `php -l vms/includes/ticketing/ticket-integrity-cron.php`
- `php -l vms/includes/ticketing/ticket-integrity-monitor.php`
- `php -l vms/includes/admin/ticket-integrity-page.php`
- `php -l vms/includes/core/cli/state-of-range.php`
- `php -l vms/includes/core/load.php`
- `php -l vms/tests/state-of-range-upcoming-filter.php`
- `php -l vms/tests/state-of-range-delivery-state.php`
- `php -l vms/tests/ticket-integrity-scan-lock.php`
- `php vms/tests/state-of-range-upcoming-filter.php`
- `php vms/tests/state-of-range-delivery-state.php`
- `php vms/tests/ticket-integrity-scan-lock.php`
- `wp-local vms state-of-range status`
- `wp-local vms state-of-range render --date=2026-06-02 --dry-run`
- `wp-local vms state-of-range reschedule`
- `wp-local eval 'require_once .../ticket-integrity-page.php; ... render_daily_report_status_panel(); ...'`

## Package

- Production-bound package slug: `vms-0.2.24.705.zip`
