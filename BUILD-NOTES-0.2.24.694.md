# VMS 0.2.24.694 — State of the Range Metric Accuracy

## Purpose

This build fixes State of the Range content accuracy so the email uses one coherent ticket basis, includes active verified/free ticket rows, and renders clean plain text.

## Changes

- Reworked State of the Range event-row math so Sold, Paid sold, Free/qualified sold, and Gross all come from completed-order lookup data across active mapped ticket rows.
- Included active mapped verified/qualified zero-dollar ticket rows in the report metrics instead of excluding them just because they are not `public` or `login` visibility tickets.
- Changed event-row copy from `Left` to `Available inventory`, `Capacity` to `Ticket capacity`, and `Free/comp sold` to `Free/qualified sold`.
- Decoded HTML entities before the plain-text email is handed to `wp_mail()`, preventing encoded currency symbols and punctuation from leaking into the delivered message.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/ticketing/ticket-integrity-daily-report.php`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.694.md`
- `BUILD-NOTES-0.2.24.694.md`
- `vms-test-plan-0.2.24.694.md`

## Validation Performed

- `php -l includes/ticketing/ticket-integrity-daily-report.php`
- Staging event `3884` row preview after package-equivalent patch load.
- Staging plain-text email preview confirmed literal `$`, dash, and ampersand rendering instead of HTML entities.
- Production read-only source comparison traced the previous mismatch to mixed data sources plus exclusion of verified ticket rows.

## Notes / Caveats

- `Available inventory` intentionally means current ticket inventory availability, not `Ticket capacity - Sold`.
- `Ticket capacity` intentionally means the sum of active mapped ticket-row inventory totals, not venue headcount capacity.
