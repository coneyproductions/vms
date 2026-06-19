# VMS 0.2.24.669 — Resource Fingerprints + Report Load Guardrails

## Purpose
Add low-overhead request/task fingerprinting for slow or heavy VMS/DT work, expose recent diagnostics in wp-admin, and reduce repeated DT/ECC admin report cost on the shared staging/production PHP worker pool.

## Changes
- Added threshold-based VMS Resource Fingerprint logging in `includes/runtime-guards.php` for:
  - requests over `3.0s`
  - requests over `128 MB` peak memory
  - plugin activation/deactivation/update hooks
  - WP-Cron and Action Scheduler runs
  - ECC calculations
  - DT report calculations
  - VMS queue scheduling/runs
- Logged request URI, admin page, current user ID, admin/AJAX/REST/cron/WP-CLI context, runtime, peak memory, due WP-Cron counts, Action Scheduler pending/running counts, and calculation flags/markers.
- Replaced the placeholder VMS health page with a recent fingerprint viewer and capped log retention/clear controls.
- Added timing markers around ECC ticket-reporting truth, Ticket Integrity scan, and payload build phases.
- Suppressed Action Scheduler async request-runner dispatch on heavy DT/ECC admin pages so those screens are less likely to dogpile extra PHP workers.

## Files changed
- `includes/runtime-guards.php`
- `includes/activation.php`
- `includes/admin/menu.php`
- `includes/admin/event-command-center.php`
- `includes/ticketing/ticket-integrity-cron.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.669.md`
- `vms-test-plan-0.2.24.669.md`
- `docs/CODEX-HANDOFF-0.2.24.669.md`
- `docs/05-revision-log.md`

## Validation performed in package build
- PHP syntax check passed for changed VMS files.
- PHP syntax check passed for changed Data Tools files paired with this build.
- Version markers were bumped in sync with the code changes.

## Notes for Codex / staging
Use this build to measure DT root, DT single-event, and ECC load behavior on staging. The first goal is visibility: confirm the new fingerprint entries capture the slow page with useful markers and queue counts before making deeper report-query changes.
