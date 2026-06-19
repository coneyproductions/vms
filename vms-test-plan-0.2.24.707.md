# VMS 0.2.24.707 Test Plan — Event Plan Featured Image Save Scope Without Trace Dependency

## Pre-checks

1. Install/activate VMS `0.2.24.707`.
2. Confirm version markers:
   - Plugin page shows `0.2.24.707`.
   - `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.707`.
   - `vms/vms-build.txt` begins with `0.2.24.707`.
3. Confirm the target Event Plan is already published and linked to a live TEC event.
4. Run the matrix twice:
   - once with `VMS_EP_PERF_TRACE` disabled or undefined
   - once with `VMS_EP_PERF_TRACE = true`

## Comparison saves

1. Run a no-op WordPress Update on the published Event Plan.
2. Confirm the runtime save scope reports:
   - `save_scope = no_op`
   - `post_field_changes = []`
   - no queue, WP-Cron, or Action Scheduler additions
3. Run a normal non-image content update on the same Event Plan.
4. Confirm the runtime save scope reports:
   - `save_scope = mixed`
   - `post_field_changes` includes `content` (or the edited post field)
   - no false `featured_image_only` classification
5. Run a featured-image-only update on the same published Event Plan.
6. Confirm the runtime save scope reports:
   - `save_scope = featured_image_only`
   - meaningful effective keys include `_thumbnail_id`
   - linked TEC featured-image sync runs from `thumbnail_meta`
   - linked TEC featured-image sync completes once only
7. Restore the original featured image and confirm the restore save also reports:
   - `save_scope = featured_image_only`
   - linked TEC featured-image sync runs once only

## Save-path verification

1. For the featured-image-only save, confirm these heavy actions are skipped:
   - `tec_status_sync`
   - `event_plan_saved_side_effects`
   - `staff_tasks_generation`
   - `staffing_rollup_dirty`
   - `staffing_seed_template`
2. Confirm image-only saves do not add:
   - `vms_event_plan_calendar_maintenance`
   - `vms_staffing_seed_event_slots_queued`
3. Confirm these counters stay at `0` for the featured-image-only save:
   - Action Scheduler additions
   - duplicate cron additions
   - ticket/product sync writes
4. If tracing is enabled, confirm saved profile output mirrors the runtime scope and sync results.
5. If tracing is disabled, confirm runtime behavior is unchanged even though no saved profile output is persisted.

## Image propagation verification

1. Change the published Event Plan featured image to a different attachment.
2. Confirm:
   - the Event Plan featured image changes
   - the linked TEC event featured image changes to match
   - the linked public TEC event page renders the new image
   - the Event Plan public URL renders the new image
   - the homepage renders the new image
   - the calendar surface renders the new image
3. Confirm ticket/event image consumers still resolve correctly:
   - Event Plan image when present
   - linked TEC image fallback when the plan image is absent
   - vendor fallback only when both plan and TEC images are absent

## Regression checks

1. Confirm the Event Plan status remains published after the featured-image-only save.
2. Confirm the linked TEC event remains linked throughout the change and restore.
3. Confirm no unrelated vendor/ticket/calendar publish workflow runs.
4. Confirm no broad Event Plan side-effect branch runs unless a real ticket-affecting change occurs.
