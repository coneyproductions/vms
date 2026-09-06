# VMS Data Tools 0.5.40 Test Plan — Ticket Pace Checkpoint Date Fix

## Purpose

Verify that Ticket Pace milestone rows no longer copy the current total into checkpoint rows that have not actually happened yet, and no longer backfill early checkpoints with final/current totals when no sales existed by that checkpoint.

## Files changed

- `includes/admin/page-reporting-module.php`
- `includes/admin/vms-dt-admin.css`
- `vms-data-tools.php`
- `vms-build.txt`
- `docs/TEST-PLAN-0.5.40.md`

## Smoke checks

1. Install `vms-data-tools-0.5.40-ticket-pace-checkpoint-date-fix.zip` by uploading/replacing the existing VMS Data Tools plugin.
2. Confirm WordPress shows VMS Data Tools version `0.5.40`.
3. Open `VMS > Reporting > Ticket Pace`.
4. Select the event that previously showed current totals in the 30 / 3 / 1 / 0 day milestone rows.

## Expected behavior

### Current/future event, currently about 5 days out

- `30 days out` uses the actual 30-day checkpoint date.
- If there were no paid tickets by the 30-day checkpoint, Current qty shows `0` and Current sales shows `$0.00`.
- `14 days out` and `7 days out` show reached checkpoint totals.
- `3 days out`, `1 day out`, and `0 days out` show `Not reached yet` until those dates arrive.
- The highlighted row should be the closest reached checkpoint, not a future checkpoint.
- The solid chart line should only include reached checkpoints.

### Historical averages

- Historical averages should still display for all milestone rows.
- A previous event with no sales by an early checkpoint should contribute `0` for that checkpoint instead of its final/current total.

### Projection

- The projected finish card should use today’s days-out checkpoint when enough historical data exists.
- If historical data is insufficient, the page should show the existing “need more historical checkpoint data” note.

## Regression checks

1. Open the Daily pace table and confirm daily rows still show sold date, days out, online qty, door qty, day qty, day sales, cumulative qty, and cumulative sales.
2. Confirm free/comp website ticket quantities remain excluded from main Ticket Pace math.
3. Confirm Season / Year, Event Profitability, Performer Payouts, and Revenue Intelligence pages still load.

## Rollback

Rollback to `vms-data-tools-0.5.39-mobile-profit-card-compact.zip` if the Ticket Pace page fails to render or if cumulative daily totals differ from the prior build.
