# VMS 0.2.24.702

## Scope

- Fix State of the Range upcoming-event filtering so past events are excluded as of the report date in the WordPress/site timezone.
- Keep the stored snapshot unchanged and apply the cutoff only during email/report rendering and tracked-upcoming aggregation.
- Preserve same-day inclusion, plain-text formatting, and any separately intentional historical reporting behavior.

## Files Changed

- `includes/ticketing/ticket-integrity-daily-report.php`
- `tests/state-of-range-upcoming-filter.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`

## Root Cause

- The monitor snapshot path already discovers upcoming targets using local site dates and parsed event timestamps.
- The State of the Range email renderer was iterating the stored `events` snapshot without a report-date cutoff, so stale past events could still appear and contribute to tracked-upcoming totals if they remained in the snapshot.

## Behavior Change

- The email renderer now derives a local report-day boundary from the report generation timestamp in `wp_timezone()`.
- Events whose local event day is before that local report day are excluded from the Upcoming events block.
- The same filtered event set now drives `Tickets sold (tracked upcoming events)`, `Gross sales (tracked upcoming events)`, `Events needing attention`, `Events scanned`, and the red/yellow/green rollup.
- Same-day events on the report date remain included.
- Snapshot generation and persistence remain unchanged; the stored snapshot is filtered only at render time.

## Local Tests Performed

- Standalone harness: `php vms/tests/state-of-range-upcoming-filter.php`
- Standalone render inspection: `php vms/tests/state-of-range-upcoming-filter.php --print-body`
- Syntax: `php -l vms/includes/ticketing/ticket-integrity-daily-report.php`
- Syntax: `php -l vms/tests/state-of-range-upcoming-filter.php`
- WordPress-backed render: verified a controlled 2026-06-01 report under WP-CLI using the Local MySQL socket override and `--skip-plugins=event-tickets --skip-themes`, with no real email sent.
- WordPress-backed result: confirmed a 2026-05-30 event was absent from the Upcoming events block and from tracked-upcoming totals, while same-day 2026-06-01 and future June events still appeared.
- Plain-text output: confirmed literal dollar signs, ampersands, and dashes still render without HTML entities.

## Package

- Production-bound package slug: `vms-0.2.24.702.zip`
