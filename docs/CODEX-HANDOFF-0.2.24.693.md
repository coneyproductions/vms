# Codex Handoff — VMS 0.2.24.693

## Focus

Ship a safe replacement for the production-failing Ticket Integrity / State of the Range scan path so the scheduled report completes without relying on a higher PHP memory limit.

## High-priority assertions

1. Ticket Integrity must not scan every published Event Plan by default; target discovery should stay bounded to relevant upcoming linked plans.
2. Sold-quantity reconciliation must not hydrate large Woo order object graphs when aggregate SQL can provide the same totals.
3. Scheduled scan/report failures must leave a visible audit trail through `scan_failed`, `scan_failed_memory`, `daily_report_failed`, or `daily_report_skipped_scan_failed` instead of disappearing after a shutdown fatal.
4. If a fresh refresh fails but a previous snapshot exists, the State of the Range report may send from that snapshot only when the warning is explicit in both state/log data and the email body.
5. The existing cron hooks must remain scheduled as `vms_ticket_integrity_daily_scan` and `vms_ticket_integrity_daily_report`.

## Known scope

No VMS Data Tools code changed in this build. The fix stays inside VMS Ticket Integrity scan/report internals and does not alter cron ownership, plugin activation state, or production mail routing.

## Release package

- Versioned zip filename: `vms-0.2.24.693.zip`
- Canonical plugin folder inside the zip: `vms/`
