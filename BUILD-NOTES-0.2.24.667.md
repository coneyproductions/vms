# VMS 0.2.24.667 — Event Command Center Ticket Snapshot Repair

## Purpose
Repair the Event Command Center ticket snapshot so it no longer depends only on the stale `_vms_ticket_stats_v1` cache.

## Changes
- Event Command Center now prefers the Data Tools reporting model when available, because that model can combine website ticket revenue with counted door/Square ticket rows.
- If Data Tools is unavailable, Event Command Center falls back to the core VMS ticket revenue report rows instead of the stale cached goal/ticket-stats payload.
- The Ticket Snapshot card now labels the primary count as **Paid tickets** and shows a note with total admitted/ticketed count when comp/free tickets are present.
- Ticket Integrity low-inventory signals now ignore free/comp ticket rows by default unless a future ticket-level policy explicitly opts them in.

## Expected effect for The Tuxedo Cats
- ECC should no longer show `0` paid tickets and `$0.00` gross sales when ticket revenue rows exist.
- If Data Tools is active and healthy, ECC should align with the reporting/profitability source of truth.
- Children's/free admission rows should stop creating misleading low-inventory alerts by default.

## Files changed
- `includes/admin/event-command-center.php`
- `includes/ticketing/ticket-integrity-daily-report.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`

## Validation performed in package build
- PHP syntax check passed for changed files.
- Full plugin PHP syntax check passed.
- JS syntax check passed for non-minified plugin JS files.
- Zip integrity check passed.

## Notes for Codex / staging
This patch should be tested with Data Tools active and inactive. With Data Tools inactive, ECC should still get online Woo/VMS ticket revenue from core ticket revenue rows, but it will not include DT door/Square ticket totals.
