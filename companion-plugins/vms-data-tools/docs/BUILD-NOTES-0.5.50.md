# VMS Data Tools 0.5.50 — Single Event Reconciliation Visibility

## Purpose

Make the Single Event Report explain its current money math without changing what the report counts yet.

## Changes

- Fixed Single Event supporting links so they load deferred row detail by appending `vms_dt_detail=single_event_supporting` when needed, while preserving the destination anchor.
- Removed the duplicate DOM ids that made some Single Event support links land on the wrong node or appear to do nothing.
- Added a visible `Gross cashflow to profitability reconciliation` table to the Single Event supporting data section. The table exposes the current arithmetic from:
  - gross cashflow seen
  - less tips
  - less service charges
  - less website refunds already reflected
  - less Square overlap held / excluded
  - less Square unclassified
  - less Square ignored
  - less/add back residual other adjustments
  - equals profitability basis / `counted_total_cents`
  - less known cost total
  - equals net after known costs
- Added explicit held ticket-like Square metrics:
  - gross
  - tax
  - net
- Added a dedicated `Held ticket-like Square rows` supporting table so the held ticket-like totals are backed by visible row detail.
- Added a dedicated `Future-event Square ticket rows in this time window` supporting table so later-event ticket rows captured by the current event time window are obvious.
- Renamed the prior generic held-row table to make clear it covers held-out non-ticket Square rows.
- Updated Single Event copy so:
  - `Gross cashflow seen` links to the reconciliation section
  - `Refunds / overlap held` shows explicit held ticket-like gross/tax/net instead of the old mixed gross-minus-net note
  - `Net after known costs` refers to the profitability basis rather than the visible combined counted reference
- No Square classification logic was changed in this build.

## Files changed

- `vms-data-tools.php`
- `includes/admin/page-reporting-module.php`
- `vms-build.txt`
- `docs/BUILD-NOTES-0.5.50.md`
- `docs/TEST-PLAN-0.5.50.md`

## Pairing note

This build is visibility-only for the Single Event Report. It is safe to pair with the same VMS core version already used by `0.5.49` because it does not alter Square classification or database writes.
