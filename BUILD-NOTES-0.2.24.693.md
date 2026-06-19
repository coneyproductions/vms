# VMS 0.2.24.693 — Ticket Integrity Memory Hardening

## Purpose

This build fixes the Ticket Integrity / State of the Range scheduled scan path that was exhausting PHP memory before the daily report could reach mail handoff.

## Changes

- Replaced the per-product sold-quantity scan path with aggregate WooCommerce SQL lookups so Ticket Integrity no longer hydrates large sets of full order objects just to total sold quantities.
- Tightened Ticket Integrity target discovery to upcoming linked Event Plans and replaced the heavy "does this plan use ticketing?" snapshot path with lightweight raw meta/config/sync checks.
- Added shutdown fatal guards and explicit failure logging for scan and daily-report operations, including a separate `scan_failed_memory` event when a memory exhaustion fatal is detectable.
- Added report-refresh handling that logs `daily_report_started`, `daily_report_failed`, and `daily_report_skipped_scan_failed`, and can reuse the last good snapshot with a visible warning when a fresh refresh fails.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/integrations/ticketing-phase-b.php`
- `includes/ticketing/ticket-integrity-monitor.php`
- `includes/ticketing/ticket-integrity-daily-report.php`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.693.md`
- `BUILD-NOTES-0.2.24.693.md`
- `vms-test-plan-0.2.24.693.md`

## Validation Performed

- `php -l includes/integrations/ticketing-phase-b.php`
- `php -l includes/ticketing/ticket-integrity-monitor.php`
- `php -l includes/ticketing/ticket-integrity-daily-report.php`
- Local WP-CLI scan verification via `vms_ticket_integrity_scan_all()` completed successfully without fataling and stayed within a bounded memory profile.
- Local WP-CLI daily-report verification via `vms_ticket_integrity_send_state_of_range_report()` reached mail handoff successfully.
- Local stale-snapshot and no-snapshot failure paths were exercised to verify `used_stale_snapshot`, `daily_report_skipped_scan_failed`, and `daily_report_failed` behavior.

## Notes / Caveats

- This build does not change the cron schedule itself; it fixes the data-loading path inside the existing scheduled scan/report flow.
- When a refresh fails but a prior snapshot exists, the report can still send with a warning banner. If no usable snapshot exists, the report now fails loudly in the Ticket Integrity log/state instead of dying silently.
