# CODEX HANDOFF - VMS Core 0.2.24.571 Cancellation Notification Polish

## Build

- Plugin: `vms`
- Version: `0.2.24.571`
- Package target: `vms-0.2.24.571-cancellation-notification-polish.zip`
- Baseline: `0.2.24.570-vendor-portal-pattern-preference-fix`

## What Changed

This pass updates cancellation UX in two places:

1. **Public calendar presentation**
   - Removed the cancelled/rescheduled image ribbon from the VMS public venue calendar view.
   - Kept cancelled/rescheduled state classes and safe cancelled-event behavior intact so entries can still be styled/handled without making the whole month look like the venue is closed.
   - Did not intentionally remove single-event cancelled/rescheduled banners or other non-calendar public cancellation indicators.

2. **Cancellation notifications**
   - Added an Event Plan Cancellation field for a **Primary vendor email message**.
   - Stored the new event-level message as `_vms_cancel_vendor_message` through the meta-key registry.
   - Included the custom message in the cancellation job summary when a plan is marked cancelled.
   - Updated cancellation notification emails so the primary vendor receives the custom message while staff/secondary/supporting recipients receive a standard cancellation notice.
   - Stopped exposing internal cancellation policy/reason/note text in outgoing vendor/staff cancellation emails.
   - Changed `status_only` cancellation jobs so provider/refund steps are skipped by policy but notifications can still run.

## Files Changed

- `vendor-management-system.php`
- `vms-build.txt`
- `includes/core/registry/constants.php`
- `includes/core/registry/meta-keys.php`
- `includes/core/cancellation.php`
- `includes/core/cancellation-adapters.php`
- `includes/cpt/event-plans.php`
- `includes/cpt/event-plans/partials/workflow-status.php`
- `includes/public/venue-calendar-shortcode.php`
- `docs/05-revision-log.md`
- `docs/test-plan-0.2.24.571-cancellation-notification-polish.md`
- `docs/CODEX-HANDOFF-0.2.24.571.md`
- `vms-test-plan-0.2.24.571.md`

## Guardrails Preserved

- Refund execution logic was not rewritten.
- Live refund safeguards and retry idempotency rules remain in place.
- Existing cancellation job envelope/audit structure remains the orchestration source of truth.
- Replacement/rescheduled drafts clear cancellation metadata, including the new primary vendor message.
- Calendar entries still know when they are cancelled/rescheduled; only the public image ribbon was removed from the venue calendar surface.

## Required Test Plan

Run `docs/test-plan-0.2.24.571-cancellation-notification-polish.md` before considering this build validated on another environment.

## Repair / Versioning Protocol

🚨 If Codex makes even a minimal code repair while testing this build, Codex must update all relevant version markers and packaging docs in the same pass before returning a replacement zip. At minimum this includes the plugin header version, `VMS_VERSION`, `vms-build.txt`, changelog/revision notes, test plan or handoff notes, and package filename. Do not return a modified build with stale versioning/docs.
