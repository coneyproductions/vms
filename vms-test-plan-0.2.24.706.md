# VMS 0.2.24.706 Test Plan — Event Plan Featured Image Save Scope

## Pre-checks

1. Install/activate VMS `0.2.24.706`.
2. Confirm version markers:
   - Plugin page shows `0.2.24.706`.
   - `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.706`.
   - `vms/vms-build.txt` begins with `0.2.24.706`.
3. Confirm the target Event Plan is already published and linked to a live TEC event.
4. Enable local/staging save tracing if deeper diagnostics are needed:
   - `VMS_EP_PERF_TRACE = true`

## Comparison saves

1. Run a no-op WordPress Update on the published Event Plan.
2. Confirm the saved Event Plan profile reports:
   - `save_scope = no_op`
   - `post_field_changes = []`
   - no queue, WP-Cron, or Action Scheduler additions
3. Run a normal non-image content update on the same Event Plan.
4. Confirm the saved Event Plan profile reports:
   - `save_scope = mixed`
   - `post_field_changes` includes `content` (or the edited post field)
   - no false `featured_image_only` classification
5. Run a featured-image-only update on the same published Event Plan.
6. Confirm the saved Event Plan profile reports:
   - `save_scope = featured_image_only`
   - `scope_effective_meta_keys = ['_thumbnail_id']`
   - `featured_image_sync.sources = ['thumbnail_meta']`
   - `featured_image_sync.updated_count = 1`
   - `featured_image_sync.completed_once = true`
   - `featured_image_sync.ran_once = true`

## Save-path verification

1. For the featured-image-only save, confirm these heavy actions are skipped:
   - `tec_status_sync`
   - `event_plan_saved_side_effects`
   - `staff_tasks_generation`
   - `staffing_rollup_dirty`
   - `staffing_seed_template`
2. Confirm these counters stay at `0` for the featured-image-only save:
   - `internal_wp_update_post_count`
   - `queue_meta_writes`
   - `wp_cron_scheduled_count`
   - `action_scheduler_enqueue_count`
3. Confirm no duplicate queue/cron/action rows are created by the save.

## Image propagation verification

1. Change the published Event Plan featured image to a different attachment.
2. Confirm:
   - the Event Plan featured image changes
   - the linked TEC event featured image changes to match
   - the linked public TEC event page renders the new image
   - the Event Plan public URL renders the new image
   - the homepage/public calendar surface renders the new image
3. Confirm ticket/event image consumers still resolve correctly:
   - Event Plan image when present
   - linked TEC image fallback when the plan image is absent
   - vendor fallback only when both plan and TEC images are absent

## Regression checks

1. Confirm the Event Plan status remains published after the featured-image-only save.
2. Confirm no unrelated vendor/ticket/calendar publish workflow runs.
3. Confirm no ticket/product image sync or broad publish workflow is triggered unless a real ticket-affecting change occurs.
4. Confirm the linked TEC image sync still runs on publish/resync paths when a TEC event is first created or explicitly rebuilt.

