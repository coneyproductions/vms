# VMS Data Tools 0.5.46 — Report Fingerprints + Single-Event Memoization

## Purpose
Reduce repeated work on heavy DT admin report pages and add enough timing visibility that staging can show which report phases still dominate before a deeper query rewrite.

## Changes
- Added DT report fingerprint flags/timing markers across the main report path:
  - ticket-report source lookup
  - website event map build
  - Square scoped-order fetch
  - Square event-map aggregation
  - report dataset build
  - event model build
  - single-event evidence
  - labor overhead
  - event costs
  - single-event page render
- Added request-level memoization for ticket reports, website maps, datasets, event models, single-event evidence, lifetime website truth, and labor overhead.
- Added a short event-scoped transient cache for the DT ticket-report path.
- Reused the already-built dataset website row for the default single-event lifetime case instead of rebuilding the same lifetime website truth again.
- Removed an extra early summary/cost calculation pass so single-event report summary/cost work runs once after evidence is applied.
- Preserved the canonical-folder duplicate-copy guard from `0.5.45`.

## Files changed
- `vms-data-tools.php`
- `includes/bootstrap.php`
- `includes/admin/page-revenue-intelligence.php`
- `includes/admin/page-reporting-module.php`
- `vms-build.txt`
- `docs/BUILD-NOTES-0.5.46.md`
- `docs/TEST-PLAN-0.5.46.md`

## Important pairing note
The admin fingerprint viewer, capped storage, and Action Scheduler async-runner guard live in VMS core `0.2.24.669`. Use this DT build with that paired VMS build when diagnosing staging performance.
