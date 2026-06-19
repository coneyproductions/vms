# VMS 0.2.24.707

## Scope

- Keep published Event Plan featured-image-only save classification and lightweight routing active even when `VMS_EP_PERF_TRACE` is disabled or undefined in production.
- Preserve the linked TEC featured-image sync and the public image consumers that depend on the Event Plan / linked TEC featured image.
- Eliminate false save-scope widening caused by lineup meta rewrites when no lineup inputs were posted.

## Root cause addressed

- `0.2.24.706` still allowed production behavior to drift because the main save-profile lifecycle and persisted diagnostics were too tightly coupled.
- In production, where `VMS_EP_PERF_TRACE` was not defined, the diagnostic profiler did not persist output, which made it appear that the narrow image-only path was dormant and obscured whether the runtime scope logic was still available.
- Local verification also exposed a second scope-widening issue: ordinary editor saves without posted lineup inputs could still synthesize a fresh primary lineup row ID and rewrite `_vms_lineup_entries_v1` / `_vms_lineup_primary_entry_id`, turning no-op or image-only saves into `mixed`.

## Behavior changes

- Split Event Plan save-profiler behavior into:
  - always-on runtime scope detection and save-state helpers
  - optional diagnostic profile persistence controlled by `VMS_EP_PERF_TRACE`
- Kept these behaviors always on, regardless of tracing:
  - early `_thumbnail_id` preservation from the thumbnail-meta hook
  - `featured_image_only` save classification
  - downstream save-scope exposure
  - published Event Plan heavy-branch skip logic for image-only saves
- Kept these diagnostics optional:
  - `_vms_last_save_profile`
  - `_vms_event_plan_save_profile_history`
  - saved module/meta instrumentation payloads
- Skipped lineup normalization/persistence entirely when no lineup-related request fields were posted, preventing synthetic row-ID churn on ordinary editor saves.
- Preserved one-shot linked TEC featured-image sync on the early thumbnail-meta path and confirmed the later `save_post` sync pass skips once that sync has already completed.

## Files changed

- `includes/core/event-plan-save-profiler.php`
- `includes/cpt/event-plans.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.707.md`
- `vms-test-plan-0.2.24.707.md`
- `docs/CODEX-HANDOFF-0.2.24.707.md`

## Local verification performed

- `php -l vms/includes/core/event-plan-save-profiler.php`
- `php -l vms/includes/cpt/event-plans.php`
- Existing published Event Plan comparison matrix on plan `2163` linked to TEC `2164`
- Tracing forced off before bootstrap:
  - no-op save classified `no_op`
  - content-only save classified `mixed`
  - featured-image-only save classified `featured_image_only`
  - featured-image restore save classified `featured_image_only`
  - `vms_event_plan_calendar_maintenance` queue additions: `0`
  - `vms_staffing_seed_event_slots_queued` queue additions: `0`
  - Action Scheduler additions: `0`
  - linked TEC featured-image sync ran once from `thumbnail_meta`
- Tracing enabled:
  - same runtime classifications as above
  - saved profile output recorded `save_scope` correctly
  - saved `featured_image_sync` recorded one completed thumbnail-meta sync only
- Public smoke after temporary image flip to attachment `2084` and restore to `202`:
  - Event Plan public page showed the updated image
  - linked TEC event page showed the updated image
  - homepage showed the updated image
  - calendar page showed the updated image

## Package

- Production-bound package slug: `vms-0.2.24.707.zip`
