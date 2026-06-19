# VMS 0.2.24.674 — Event Plan Empty-Save Load Shed and TEC Author Fix

## Purpose
Repair the production regression observed on `0.2.24.662` where creating or publishing a brand-new Event Plan with no tickets still queued heavy VMS work, prevent public calendar dead cards during deferred TEC sync, and harden linked TEC event creation so `tribe_events` posts never land with `post_author=0`.

## Changes
- Fixed the no-ticket Event Plan publish/save performance regression.
- Added guards to skip heavy ticketing, staffing, and Ticket Integrity work for empty Event Plans.
- Prevented public calendar dead cards while the linked TEC event is still syncing by hiding public-feed entries until the TEC event is published and has a real permalink.
- Explicitly set and backfilled TEC event authors during VMS sync.
- Added shared Event Plan performance helpers for:
  - request/job tracing
  - effective-ticket detection based on saved ticket config/sync state
  - captured actor-user persistence
  - per-event transient job locks
  - TEC post-author resolution and backfill
- Guarded the empty/no-ticket path so Event Plan create/save/publish no longer queues:
  - Ticket Integrity spot scans
  - staff-task generation
  - staffing template seeding
  - TEC vendor/category maintenance
- Deferred heavy maintenance out of inline `save_post_vms_event_plan` work:
  - vendor/category TEC maintenance now reuses the deferred calendar-maintenance job
  - staffing template seeding now runs in a delayed per-event cron job
- Dedupe-hardened per-event background jobs with `wp_next_scheduled()` and transient single-flight locks for:
  - deferred calendar publish
  - calendar maintenance
  - Ticket Integrity spot scans
  - queued staff-task generation
  - queued staffing template seeding
- Added temporary tracing around the relevant Event Plan save/publish hooks and deferred jobs, including request ID, PID, cron/AJAX/REST context, status transition, ticket count, job name, and elapsed ms.
- Hardened TEC author handling across VMS sync paths:
  - explicit `post_author` on create/update
  - background-safe author precedence using Event Plan author, captured actor, current user, then first administrator
  - backfill for already-linked TEC events with invalid author IDs
  - permalink-presence logging alongside author tracing

## Files changed
- `includes/core/event-plan-performance.php`
- `includes/core/load.php`
- `includes/cpt/event-plans.php`
- `includes/ticketing/ticket-integrity-cron.php`
- `includes/ticketing/ticket-integrity-monitor.php`
- `includes/modules/staff-tasks/generator.php`
- `includes/core/staffing.php`
- `includes/integrations/ticketing-phase-b.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.674.md`
- `vms-test-plan-0.2.24.674.md`
- `docs/CODEX-HANDOFF-0.2.24.674.md`
- `docs/05-revision-log.md`
- `tests/check-package-integrity.php`

## Release package
- Versioned zip filename: `VMS_674_event_plan_lightweight_save_and_tec_author_fix.zip`
- Canonical convenience zip: `vms.zip`

## Validation target
- A brand-new Event Plan with no tickets should remain lightweight during create/save/publish.
- That path must not enqueue Ticket Integrity, staff-task generation, or duplicate per-event maintenance jobs.
- The linked TEC event should always resolve to a real author and remain clickable from wp-admin / public calendar views.
