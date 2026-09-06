# VMS Data Tools 0.5.45 — Duplicate Loader + Labor Fallback Hardening

## Purpose
Make the current Data Tools package safer to activate after a parallel/duplicate install, and make Event Profitability labor overhead smarter for hourly staff rows with partially-entered shift times.

## Changes
- Adds an early duplicate-copy guard in `vms-data-tools.php` so activating this copy while another Data Tools copy is already loaded shows an admin notice instead of immediately redeclaring constants/functions.
- Removes macOS metadata from the rebuilt zip package.
- Updates hourly labor overhead logic so a staff row with only one side of the shift time filled can still be included if VMS resolves a usable duration from event/duration fallback.
- Keeps a warning/reason on inferred labor rows so the operator knows to review the shift window.

## Files changed
- `vms-data-tools.php`
- `includes/admin/page-reporting-module.php`
- `vms-build.txt`
- `docs/BUILD-NOTES-0.5.45.md`
- `docs/TEST-PLAN-0.5.45.md`

## Important install note
If WordPress already created a parallel Data Tools install, use the Plugins screen or file manager to ensure only one Data Tools folder remains active. The canonical folder should be `vms-data-tools`.
