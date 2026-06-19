# VMS 0.2.24.673 — Async Suppression Marker Context Fix

## Purpose
Repair the remaining `0.2.24.672` diagnostic gap: scoped heavy pages were correctly flagged as async-suppressed, but the stored fingerprint payload compacted the page/slug values down to `...` in the admin viewer. This replacement build keeps the scope values readable and adds an explicit marker entry alongside the flag.

## Changes
- Preserved the `0.2.24.672` scoped heavy-page list, including the Event Command Center slug and the intentionally scoped Event Plan editor.
- Kept the `0.2.24.671` WP-CLI activation/deactivation lifecycle-hook compatibility fix for nullable `network_wide`.
- Added an explicit `action_scheduler_async_blocked` marker entry when scoped suppression is active.
- Switched stored fingerprint export for flags/markers/notes to a deeper compaction pass so page/slug/scope values survive into the saved health-screen log.

## Files changed
- `includes/runtime-guards.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.673.md`
- `vms-test-plan-0.2.24.673.md`
- `docs/CODEX-HANDOFF-0.2.24.673.md`
- `docs/05-revision-log.md`

## Release package
- Versioned zip filename: `VMS_673_async_suppression_marker_context_fix.zip`
- Canonical convenience zip: `vms.zip`

## Validation target
- ECC, DT root, DT single-event, and the intentionally scoped Event Plan editor should all log readable `action_scheduler_async_blocked` page/slug context.
- WordPress Dashboard, Plugins, WooCommerce Orders, and other unrelated pages should still avoid that marker.
- An Action Scheduler async runner request should still be able to execute outside the scoped heavy pages.
