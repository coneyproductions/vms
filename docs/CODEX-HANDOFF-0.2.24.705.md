# VMS 0.2.24.705 — State of the Range Scheduling and Diagnostics

## What changed

- Hardened State of the Range delivery-state tracking so VMS records when the report was scheduled, rendered, send-attempted, successfully sent, and which recipient/mailer/error were involved.
- Added self-healing for the daily Ticket Integrity scan/report WP-Cron hooks so duplicate hooks, wrong recurrences, and local-time drift are repaired automatically.
- Added explicit stale scan-lock recovery so an invalid or over-age lock does not keep blocking refreshes indefinitely.
- Added Ticket Integrity admin diagnostics plus safe preview/dry-run controls and a constrained admin test-send path.
- Added `wp vms state-of-range` helpers for status, dry-run render, send-test, and reschedule.

## Production interpretation notes

- State of the Range is still scheduled by VMS on `vms_ticket_integrity_daily_report`.
- State of the Range is still sent through `wp_mail()`.
- MailPoet cron hardening can still matter as the downstream mail transport, but MailPoet is not the direct scheduler/queue owner for this report path.
- The intended local run times remain:
  - scan: `03:17`
  - report: `06:05`

## Verification summary

- Standalone tests now cover:
  - same-day inclusion
  - dry-run not marking sent
  - failed send not marking successful send
  - successful send recording successful send state
  - stale scan-lock recovery
- Local WordPress-backed smoke checks verified:
  - `wp vms state-of-range status`
  - `wp vms state-of-range render --date=2026-06-02 --dry-run`
  - `wp vms state-of-range reschedule`
  - render-only admin diagnostics panel output without fatal errors

## Version markers updated

- Plugin header: `0.2.24.705`
- `VMS_VERSION`: `0.2.24.705`
- `vms-build.txt`: `0.2.24.705`
- Build notes: `BUILD-NOTES-0.2.24.705.md`
- Test plan: `vms-test-plan-0.2.24.705.md`
