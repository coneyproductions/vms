# VMS 0.2.24.671 — Activation Hook Compatibility Parse Fix

## Purpose
Complete the staging activation-hook compatibility fix from `0.2.24.670` by removing the invalid no-op cast that caused a parse error after the nullable callback signature was introduced.

## Changes
- Removed the invalid `(void) $network_wide;` lines from the nullable plugin lifecycle fingerprint handlers.
- Preserved the nullable `network_wide` callback signatures so WP-CLI activation/deactivation remains compatible on staging.
- Kept the `0.2.24.669` fingerprint viewer, DT/ECC timing markers, request-level memoization, and Action Scheduler async-runner suppression unchanged.

## Files changed
- `includes/runtime-guards.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.671.md`
- `vms-test-plan-0.2.24.671.md`
- `docs/CODEX-HANDOFF-0.2.24.671.md`
- `docs/05-revision-log.md`

## Validation performed in package build
- PHP syntax check passed for the changed VMS files.
- Staging DT activation/reactivation is the primary regression check for this follow-up.
