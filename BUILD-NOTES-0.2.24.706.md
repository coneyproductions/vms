# VMS 0.2.24.706

## Scope

- Keep published Event Plan featured-image updates on a lightweight image-sync path instead of letting them look like broader publish maintenance.
- Preserve the linked TEC event image update and the public image consumers that depend on the Event Plan / linked TEC featured image.
- Improve local save-path instrumentation so no-op, content-only, and featured-image-only saves can be compared directly.

## Root cause addressed

- The linked TEC featured-image sync already ran on `_thumbnail_id` changes before the main Event Plan `save_post_vms_event_plan` profiler began.
- Because the profiler started later, featured-image-only saves were classified from housekeeping writes like `_vms_unpublished_changes_at` and `_vms_admin_scroll_to` instead of the actual `_thumbnail_id` change.
- That misclassification let published image-only updates continue through broader Event Plan save branches even when ticket/vendor/staff/calendar state did not change.
- The final save-profile builder also failed to initialize `post_field_changes` before scope classification, which could mislabel true content edits as `no_op`.

## Behavior changes

- Added deferred/early effective-meta tracking so `_thumbnail_id` survives into the main Event Plan save-profiler state.
- Added scope-only effective-meta filtering so housekeeping keys do not widen save scope.
- Reclassified Event Plan save scope to:
  - `no_op` for housekeeping-only editor saves with no post-field changes
  - `mixed` for real content/post-field edits
  - `featured_image_only` when `_thumbnail_id` is the only meaningful change
- Skipped the following on `featured_image_only` saves:
  - TEC status sync
  - `vms_event_plan_saved` side effects
  - staff task generation
  - staffing rollup dirty queue maintenance
  - staffing seed template queue maintenance
- Kept linked TEC featured-image sync on the early thumbnail-meta path and prevented the later `save_post` sync pass from running it a second time in the same request.
- Added `scope_effective_meta_keys` to the saved Event Plan profile output so image-only classification is visible in diagnostics.

## Files changed

- `includes/core/event-plan-save-profiler.php`
- `includes/cpt/event-plans.php`
- `includes/core/staffing.php`
- `includes/modules/staff-tasks/generator.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.706.md`
- `vms-test-plan-0.2.24.706.md`
- `docs/CODEX-HANDOFF-0.2.24.706.md`

## Local verification performed

- `php -l vms/includes/core/event-plan-save-profiler.php`
- `php -l vms/includes/cpt/event-plans.php`
- `php -l vms/includes/core/staffing.php`
- `php -l vms/includes/modules/staff-tasks/generator.php`
- Local Event Plan perf runner scenarios:
  - `restore_baseline`
  - `no_change_update`
  - `basic_field_update`
  - `featured_image_change`
- Verified saved Event Plan profiles from `_vms_last_save_profile` / `_vms_event_plan_save_profile_history`:
  - no-op save classified as `no_op`
  - content save classified as `mixed`
  - featured-image save classified as `featured_image_only`
  - `internal_wp_update_post_count = 0`
  - `queue_meta_writes = 0`
  - `wp_cron_scheduled_count = 0`
  - `action_scheduler_enqueue_count = 0`
  - linked TEC image sync `ran_once = true` and `completed_once = true`
- Front-end smoke after temporary image flip to attachment `2093` and restore to `2084`:
  - linked TEC event page contained the updated image
  - Event Plan public URL contained the updated image
  - homepage / public calendar URL contained the updated image
  - linked Event Plan and TEC thumbnails restored to baseline `2084`

## Package

- Production-bound package slug: `vms-0.2.24.706.zip`
