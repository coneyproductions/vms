# VMS Data Tools 0.5.51 — Processor Cash vs Website Attribution Phase 1

## Purpose

Stop DT from presenting website-originated Woo totals and Square processor totals as additive cash channels when website orders settle through Square.

This build is presentation and labeling cleanup only. It does not change Square classification logic or the deeper profitability engine yet.

## Changes

- Made Square processor totals the only collected-cash truth in the main DT reporting surfaces.
- Recast website totals as website-originated attribution instead of a second collected-cash channel.
- Removed additive website-plus-Square collected-cash headlines from:
  - Single Event Report
  - side-by-side summary
  - Revenue Intelligence overview cards
  - Revenue Intelligence top-level callouts and comparison copy
- Replaced the old `Gross Cashflow Seen` headline concept with clearer metrics:
  - `Square total collected`
  - `Website-originated portion`
  - `Direct Square / POS portion`
  - `Current profitability basis`
- Updated Single Event supporting tables and reconciliation copy so the processor cash story and the profitability-basis story are clearly separated.
- Updated Revenue Intelligence labels, notes, composition copy, reconciliation copy, and event table headers so they no longer imply that website-originated dollars are extra processor cash.
- Updated Revenue Intelligence CSV headers to distinguish:
  - website-originated totals
  - Square processor collected totals
  - direct Square / POS portion
  - current profitability basis
- Kept legacy compatibility fields in place where useful, but normalized their semantics to Square processor truth instead of additive website-plus-Square cash.

## Explicit non-goals

- No Square source classification changes.
- No mapper behavior changes.
- No profitability-basis formula rewrite.
- No Ticket Pace or Quick Read math changes.
- No database schema or write-path changes.

## Files changed

- `vms-data-tools.php`
- `vms-build.txt`
- `includes/admin/menu.php`
- `includes/admin/page-reporting-module.php`
- `includes/admin/page-revenue-intelligence.php`
- `docs/BUILD-NOTES-0.5.51.md`
- `docs/TEST-PLAN-0.5.51.md`

## Pairing note

This build is safe as a Phase 1 visibility pass on top of the same VMS core pairing used by `0.5.50` because it does not alter data collection, classification, or database writes.
