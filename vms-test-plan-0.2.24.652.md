# VMS 0.2.24.652 Test Plan — Event Plan Publish Load Shield

## Build
- Plugin version: `0.2.24.652`
- Source baseline: `0.2.24.651`
- Primary focus: keep Event Plan publish/edit requests from maxing shared-host CPU by deferring heavy post-save work.

## Smoke checks
1. Install/activate the zip in the canonical `vms/` plugin folder.
2. Confirm **VMS** shows version `0.2.24.652` in WordPress plugins.
3. Confirm `/wp-content/plugins/vms/vms-build.txt` begins with `0.2.24.652`.
4. Open an Event Plan editor page and confirm the page loads normally.

## Publish Now load shield
1. Open a Ready Event Plan on staging.
2. Watch cPanel resource usage or another server monitor.
3. Click **Publish Now**.
4. Confirm the editor request returns without the long blocking publish behavior.
5. Confirm the Event Plan status is Published.
6. Confirm an admin notice says the calendar sync was queued.
7. Check post meta or cron list for `vms_event_plan_deferred_calendar_publish` scheduled for that Event Plan.
8. Wait for WP-Cron to run, or manually trigger cron.
9. Confirm the linked TEC event is created/updated.
10. Confirm `_vms_calendar_publish_queue_state` becomes `complete` and `_vms_calendar_publish_completed_at` is populated.

## Failure/visibility check
1. Temporarily make an Event Plan invalid on staging, such as removing the event date.
2. Click **Publish Now**.
3. Confirm validation blocks publish and no queued calendar sync is created.
4. Restore valid values.

## Staff task generation deferral
1. Save/update an Event Plan that has staff/task settings.
2. Confirm the editor save returns normally.
3. Confirm `vms_tasks_generate_for_event_queued` is scheduled.
4. After cron runs, confirm `_vms_tasks_generation_queue_state` becomes `complete`.

## Version-bump cleanup guard
1. After activating this build, open an Event Plan editor page.
2. Confirm legacy ticket cleanup does not run inside that editor request.
3. Confirm `vms_event_plan_legacy_ticket_cleanup` is scheduled from a non-editor admin request.
4. Let cron run and confirm cleanup progresses in small chunks without blocking Event Plan editing.

## Regression checks from 0.2.24.651
1. Open an Event Plan with 4+ supporting acts.
2. Confirm supporting act cards default collapsed on first load.
3. Expand/collapse selected cards, refresh, and confirm the state is remembered.
4. Confirm **Expand All** / **Collapse All** still persist after refresh.

## Regression checks from 0.2.24.650
1. Disable a saved-config ticket row without pushing ticket changes.
2. Confirm the stale public ticket control is hidden/neutralized.
3. Confirm add-to-cart/cart/checkout still fail closed for disabled ticket products.

## Local validation performed for package
- Full PHP lint across all plugin PHP files.
- JavaScript syntax check across non-vendor JS assets.
