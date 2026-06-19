# VMS 0.2.24.701

## Scope

- Narrow no-behavior-change Event Plan save churn cleanup only.
- No changes to ticket pricing, checkout, Woo product creation/update, Preview/Commit flows, VMS Ops, checkout policies, public rendering, workflow rules, TEC publish behavior, staff task behavior, or Ticket Integrity behavior.

## Files Changed

- `includes/cpt/event-plans.php`
- `includes/core/staffing.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`

## Behavior Preserved

- Ordinary Event Plan saves still save normally.
- Deferred calendar publish still schedules and runs the same job.
- Calendar/vendor maintenance still schedules and runs the same job.
- Staffing seed still schedules and runs the same job.
- Linked TEC featured-image repair from `.700` still updates stale TEC thumbnails from the Event Plan featured image.
- No-image fallback behavior remains unchanged.

## Guards Added

- Added a request-scoped linked-TEC image-sync guard in `includes/cpt/event-plans.php` keyed by Event Plan ID, linked TEC event ID, and current Event Plan thumbnail state.
- Added a queue-meta no-op guard for deferred calendar publish in `includes/cpt/event-plans.php` so existing queued/locked state for the same plan and reason no longer rewrites `_vms_calendar_publish_*` meta.
- Added a queue-meta no-op guard for calendar/vendor maintenance in `includes/cpt/event-plans.php` so existing queued/locked state for the same plan, TEC event, and reason no longer rewrites `_vms_calendar_maintenance_*` meta.
- Added a queue-meta no-op guard for staffing seed in `includes/core/staffing.php` so existing queued/locked state for the same plan and reason no longer rewrites `_vms_staffing_seed_*` meta.

## Secondary-Vendor Dirty Check

- Skipped in `.701`.
- Reason: the current secondary-vendor save block also feeds derived index meta, qualification flags, snapshot meta, and downstream TEC/vendor maintenance. That change is better isolated as a separate Phase 2 patch.

## Local Tests Performed

- Test 1: Confirmed an ordinary Event Plan save repaired a stale linked TEC thumbnail from `2093` to the Event Plan thumbnail `2084`.
- Test 1b: Confirmed a second linked-image sync call in the same request returns `request_guard_duplicate` after the first successful sync.
- Test 2: Confirmed a no-image Event Plan save left the linked TEC fallback thumbnail unchanged at `2093`, and a fresh no-image helper call returned `plan_thumbnail_missing`.
- Test 3: Confirmed deferred calendar publish kept one scheduled event, preserved the first `_queued_at` value on a same-reason requeue, and only rewrote queue meta when the reason changed.
- Test 4: Confirmed calendar/vendor maintenance kept one scheduled event, preserved the first `_queued_at` value on a same-reason requeue, and only rewrote queue meta when the reason changed.
- Test 5: Confirmed staffing seed kept one scheduled event, preserved the first `_queued_at` value on a same-reason requeue, and only rewrote queue meta when the reason changed.
- Test 6: Confirmed an ordinary published Event Plan save completed without fatal errors, kept the linked TEC event ID stable, and preserved the effective ticket snapshot.

## Package

- Production-bound package slug: `vms-0.2.24.701.zip`
