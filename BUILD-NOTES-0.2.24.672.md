# VMS 0.2.24.672 — Scoped Async Suppression Markers

## Purpose
Finish the Action Scheduler async suppression follow-up from the staging diagnostic run by making the scoped heavy-page list explicit, adding the missing Event Command Center slug, and ensuring scoped pages always leave a usable `action_scheduler_async_blocked` fingerprint trail.

## Changes
- Added reusable heavy-page scope detection in `includes/runtime-guards.php` for:
  - `vms-event-command-center`
  - Data Tools root/reporting pages
  - DT single-event report
  - Event Plan edit screens (intentionally scoped)
- Added explicit `action_scheduler_async_blocked` fingerprint flags on scoped admin pages with the current page slug and scope reason.
- Kept the filter-based async-runner suppression itself scoped to those heavy admin surfaces; unrelated admin pages still return `true` from the suppression filter.
- Made `action_scheduler_async_blocked` a log-worthy flag so scoped pages produce a recent fingerprint entry even when they load under the normal runtime threshold.
- Preserved the `0.2.24.671` WP-CLI activation/deactivation compatibility fix for nullable `network_wide`.

## Files changed
- `includes/runtime-guards.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.672.md`
- `vms-test-plan-0.2.24.672.md`
- `docs/CODEX-HANDOFF-0.2.24.672.md`
- `docs/05-revision-log.md`

## Release package
- Versioned zip filename: `VMS_672_scoped_action_scheduler_async_suppression_markers.zip`
- Canonical convenience zip: `vms.zip`

## Validation performed in package build
- PHP syntax check passed for the changed VMS files.
- Staging validation should confirm scoped markers appear on ECC, DT root, DT single-event, and the intentionally scoped Event Plan editor, while remaining absent from normal dashboard/plugins/orders pages.
