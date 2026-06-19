# VMS 0.2.24.670 — WP-CLI Activation Hook Compatibility Follow-up

## Purpose
Fix the staging-discovered activation/deactivation fatal from `0.2.24.669` so the new fingerprint lifecycle hooks remain compatible with WP-CLI on hosts that pass `null` for the `network_wide` argument.

## Changes
- Relaxed `vms_resource_fingerprint_track_plugin_activation()` and `vms_resource_fingerprint_track_plugin_deactivation()` to accept a nullable network-wide flag.
- Preserved the existing plugin lifecycle fingerprint logging behavior.
- Kept the `0.2.24.669` resource fingerprint viewer, DT/ECC timing markers, request-level memoization, and Action Scheduler async-runner suppression unchanged.

## Files changed
- `includes/runtime-guards.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.670.md`
- `vms-test-plan-0.2.24.670.md`
- `docs/CODEX-HANDOFF-0.2.24.670.md`
- `docs/05-revision-log.md`

## Validation performed in package build
- PHP syntax check passed for the changed VMS files.
- Staging reactivation via WP-CLI is the primary regression check for this follow-up.

## Notes for Codex / staging
Retest section `B` from the `0.2.24.669` staging validation plan, specifically the Data Tools deactivate/reactivate cycle. The failure condition in `0.2.24.669` was a fatal during `wp plugin activate vms-data-tools` after VMS had registered the fingerprint lifecycle callbacks.
