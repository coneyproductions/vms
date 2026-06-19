# Build Notes — VMS 0.2.24.652

## Summary
This patch goes deeper than 0.2.24.651 after staging still showed the shared-host CPU/Speed limit pegging during Event Plan publish.

The key change is that **Publish Now no longer runs the expensive TEC calendar create/update inside the Event Plan editor save request**. The Event Plan is marked Published immediately, but the TEC sync is queued for a short delayed background run so the admin editor does not have to sit on the heaviest work.

## Changes

### Event Plan publish load shield
- `Publish Now` now validates the Event Plan, marks it Published, and queues `vms_event_plan_deferred_calendar_publish` instead of calling `tribe_create_event()` / `tribe_update_event()` during the editor request.
- The queued sync stores lightweight status meta:
  - `_vms_calendar_publish_queue_state`
  - `_vms_calendar_publish_queued_at`
  - `_vms_calendar_publish_started_at`
  - `_vms_calendar_publish_completed_at`
  - `_vms_calendar_publish_last_error`
  - `_vms_calendar_publish_queue_reason`
- If a site needs the old immediate behavior, developers can disable deferral with the `vms_event_plan_defer_calendar_publish` filter or the `VMS_EVENT_PLAN_DEFER_CALENDAR_PUBLISH` constant.
- Existing `Re-sync to Calendar` remains the immediate manual sync path.

### Version-bump cleanup no longer runs inside editor requests
- The legacy ticket-meta cleanup that runs after VMS version changes no longer executes directly on `admin_init` while saving/editing Event Plans.
- Admin requests now only schedule cleanup for later.
- Cleanup runs in small cron chunks instead of spending up to several seconds of admin-page CPU after a plugin update.

### Staff task generation deferred
- Event Plan save no longer generates/reconciles staff tasks synchronously.
- Staff task generation is queued through `vms_tasks_generate_for_event_queued` and processed shortly after save.
- Queue state is stored on the plan with `_vms_tasks_generation_*` meta.

## Preserved from 0.2.24.651
- Supporting lineup cards remember expanded/collapsed state per Event Plan/editor browser.
- Long supporting-act lineups default collapsed on first load.
- No-op calendar sync signatures still prevent repeat unchanged TEC writes when the queued job runs.
- Vendor/category calendar maintenance remains deferred.

## Preserved from 0.2.24.650
- Disabled saved-config ticket rows are hidden/neutralized on public ticket UIs during pending ticket sync windows.
- Server-side disabled-ticket fail-closed guards remain in place for add-to-cart/cart/checkout.

## Important operational note
The expensive TEC sync work still has to happen. This patch moves that work out of the editor request so publishing/editing does not tie up the admin page. If the server still pegs briefly a few minutes later when WP-Cron runs the queued job, that confirms the remaining bottleneck is the TEC write itself and the next step should be a lower-level TEC writer or server-side profiling of TEC hooks.
