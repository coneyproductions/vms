# CODEX HANDOFF — VMS 0.2.24.611 — Incident Hardening

## Build

- Version: `0.2.24.611`
- Package: `vms-0.2.24.611-incident-hardening.zip`

## Scope

This pass hardens VMS boot and cleanup paths around the SR.com incident:

- Guards the optional/internal migrations include so a missing `includes/db/migrations.php` no longer hard-fatals public traffic.
- Queues one-time admin diagnostics instead of exposing public fatals when required internal files are missing.
- Unschedules stale `vms_*` cron hooks on deactivation, including safe cleanup of stale single events.
- Ensures recurring VMS jobs are only scheduled from admin/cron/CLI-safe contexts after custom schedules exist.
- Keeps admin-only modules, heavy reporting, tours, and most Email Follow-Ups/Data Tools boot work off normal public requests.

## Files touched

- `vendor-management-system.php`
- `includes/runtime-guards.php`
- `includes/activation.php`
- `includes/core/plugin.php`
- `includes/core/registry/constants.php`
- `includes/cpt/event-plans.php`
- `includes/tours/class-vms-tours-service.php`
- `includes/social-share/load.php`
- `includes/social-share/queue-runner.php`
- `includes/modules/staff-tasks/staff-tasks.php`
- `includes/modules/staff-tasks/notifications.php`
- `includes/modules/staff-tasks/generator.php`
- `includes/modules/email-followups/email-followups.php`
- `includes/modules/email-followups/scheduler.php`
- `includes/core/calendar-ticket-counts.php`
- `includes/core/notifications.php`
- `includes/core/vendor-booking-onboarding.php`
- `includes/integrations/ticketing-phase-b.php`
- `includes/integrations/ticketing-verifications.php`
- `includes/ticketing/ticket-integrity-cron.php`
- `tests/check-package-integrity.php`
- `vms-build.txt`
- docs listed below

## Test plan

- `docs/test-plan-0.2.24.611-incident-hardening.md`
- `docs/test-plan-incident-hardening.md`

## Verification completed locally

- `php -l` passed on all touched PHP files.
- `php tests/check-package-integrity.php vms` returned `Package integrity OK.`

## Remaining validation

Run the staging smoke/performance/debug-log checks from the test plan before packaging or promoting this build.
