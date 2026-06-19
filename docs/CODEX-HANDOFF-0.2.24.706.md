# VMS 0.2.24.706 — Event Plan Featured Image Save Scope

## What changed

- Preserved the Event Plan `_thumbnail_id` change as a deferred effective save key so the main Event Plan save profiler can still see that a featured-image update happened even though the thumbnail meta hook fires before `save_post_vms_event_plan`.
- Reclassified Event Plan save scope using meaningful keys instead of housekeeping-only writes, and fixed the final profiler builder to honor post-field changes when separating `no_op`, `mixed`, and `featured_image_only` saves.
- Skipped broad Event Plan publish/save side effects on `featured_image_only` saves while preserving the early linked TEC featured-image sync and preventing the later `save_post` pass from syncing it a second time.
- Added `scope_effective_meta_keys` plus one-shot linked-image sync diagnostics to the stored Event Plan save profile output.

## Production interpretation notes

- The linked TEC featured image now updates on the early `_thumbnail_id` meta path and records as `source = thumbnail_meta`.
- `save_post_vms_event_plan` still runs for featured-image updates, but the heavy publish-style branches now short-circuit when `_thumbnail_id` is the only meaningful change.
- No dedicated VMS homepage-slider queue/propagation hook was found locally. The homepage/public calendar smoke showed the changed image immediately via existing front-end consumers rather than a deferred VMS job.
- Ticketing image consumers continue to resolve from the Event Plan image first, then the linked TEC image, then the vendor fallback.

## Verification summary

- Local profiler comparison after the repair:
  - no-op save: `save_scope = no_op`
  - content save: `save_scope = mixed`
  - featured-image save: `save_scope = featured_image_only`
- Featured-image-only local profile confirmed:
  - `meta_writes = 2`
  - `internal_wp_update_post_count = 0`
  - `queue_meta_writes = 0`
  - `wp_cron_scheduled_count = 0`
  - `action_scheduler_enqueue_count = 0`
  - linked TEC image sync ran once and completed once
- Local trace confirmed the featured-image-only save skipped:
  - `tec_status_sync`
  - `event_plan_saved_side_effects`
  - `staff_tasks_generation`
  - `staffing_rollup_dirty`
  - `staffing_seed_template`
- Front-end smoke after temporarily switching the Event Plan image to attachment `2093` confirmed the updated image appeared on:
  - `https://serenaderange.local/event/whitehouse-opry/`
  - `https://serenaderange.local/?vms_event_plan=whitehouse-opry`
  - `https://serenaderange.local/`

## Version markers updated

- Plugin header: `0.2.24.706`
- `VMS_VERSION`: `0.2.24.706`
- `vms-build.txt`: `0.2.24.706`
- Build notes: `BUILD-NOTES-0.2.24.706.md`
- Test plan: `vms-test-plan-0.2.24.706.md`
- Package slug: `vms-0.2.24.706.zip`
