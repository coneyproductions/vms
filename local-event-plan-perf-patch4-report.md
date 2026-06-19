# VMS Event Plan Performance Patch 4 Report

Local only. No staging or production deploy.

## Scope

Patch 4 targeted Event Plan edit-screen load reduction with no save/publish behavior changes.

Implemented locally:

- Deferred the Staff editor on initial Event Plan open and rendered a summary-only shell until expanded.
- Added authenticated admin AJAX lazy loading for the Staff section.
- Batched vendor option hydration for the Time + Lineup editor so primary/supporting vendor option rows reuse one shared availability/default-fee context.
- Added finer-grained trace spans inside the Time + Lineup render path.

Changed files:

- `vms/includes/cpt/event-plans.php`
- `vms/includes/cpt/event-plans/partials/time-lineup.php`
- `vms/includes/cpt/event-plans/partials/staff.php`

Raw trace:

- `wp-content/vms-event-plan-perf-trace.log`

## What Changed On Initial Load

Summary/lazy behavior:

- `staff` now renders summary-only on first edit-screen open.
- Full Staff editor HTML is fetched only when the section is expanded.
- The initial Staff shell preserves save safety with `vms_staffing_lazy_unloaded=1`, so unopened Staff sections still skip staffing work on save.

Render-path reductions:

- Time + Lineup still renders on initial load, but vendor option hydration is now computed once and reused across the primary vendor editor and all supporting vendor cards.
- Supporting-vendor default fee lookups are reused from a prebuilt map instead of recalculated per row.

New trace coverage:

- `event_plan_time_lineup_summary_render`
- `event_plan_primary_vendor_editor_render`
- `event_plan_supporting_act_card_render`
- `event_plan_time_lineup_timeline_render`
- `event_plan_time_lineup_health_render`
- `event_plan_staff_section_render`
- `event_plan_section_lazy_load`

## Open Edit Screen

Patch 3 baseline for Event Plan `76`:

- Queries: `430`
- Peak memory: `216 MB`
- Dominant phases:
  - `event_plan_admin_screen_boot`: `273 ms`
  - `event_plan_details_meta_box_render`: `114 ms`
  - `event_plan_partial_render_time-lineup`: `77 ms`

Patch 4 result:

- Queries: `388`
- Peak memory: `216 MB`
- Dominant phases:
  - `event_plan_admin_screen_boot`: `216 ms`
  - `event_plan_details_meta_box_render`: `46 ms`
  - `event_plan_partial_render_secondary-vendors`: `11 ms`
  - `event_plan_vendor_availability_checks`: `8 ms`
  - `event_plan_partial_render_time-lineup`: `8 ms`

Delta:

- Queries: `430 -> 388` (`-42`)
- Peak memory: `216 MB -> 216 MB` (flat)
- `event_plan_admin_screen_boot`: `273 ms -> 216 ms`
- `event_plan_details_meta_box_render`: `114 ms -> 46 ms`
- `event_plan_partial_render_time-lineup`: `77 ms -> 8 ms`

Initial-load trace notes:

- `event_plan_staff_section_render phase=summary_only skip_reason=collapsed_initial_load lazy_load=1`
- Supporting act cards present on open: `10`
- Supporting act detail bodies open by default on open: `0`

## Save/Update Regression Check

### Plain no-change Update

Patch 3:

- Queries: `392`
- Peak memory: `201 MB`
- `save_post` passes: `1`
- Internal `wp_update_post()`: `0`

Patch 4:

- Queries: `375`
- Peak memory: `207 MB`
- `save_post` passes: `1`
- Internal `wp_update_post()`: `0`

Behavior checks on Patch 4:

- `event_plan_auto_title_sync`: skipped (`no_op`)
- `event_plan_secondary_vendor_rebuild`: skipped
- `event_plan_calendar_vendor_maintenance`: skipped
- `event_plan_staffing_save`: skipped
- `event_plan_staffing_seed`: skipped
- `event_plan_staffing_queue_meta`: skipped
- Woo ticket/product sync: none observed
- Action Scheduler jobs: none observed
- Title/status behavior: unchanged

### Other scenarios

| Scenario | Patch 3 | Patch 4 | Notes |
| --- | --- | --- | --- |
| Basic field update | `390 q / 213 MB` | `373 q / 215 MB` | Save path preserved; vendor/staffing skipped |
| Featured image change | `404 q / 215 MB` | `387 q / 215 MB` | Featured image sync still runs; vendor/staffing skipped |
| Vendor + staffing change | `465 q / 217 MB` | `465 q / 205 MB` | Vendor rebuild and staffing work still run |
| Publish / republish | `434 q / 217 MB` | `415 q / 207 MB` | Publish-specific jobs preserved |
| Extra staffing-only save | `458 q / 217 MB` | `452 q / 211 MB` | Staffing dirty path preserved; vendor rebuild skipped |
| Extra vendor-only save | `431 q / 215 MB` | `414 q / 213 MB` | Vendor dirty path preserved; staffing skipped |

Scenario-specific behavior checks:

- Staffing-only save still runs staffing availability/conflict maintenance and staffing seed work when staffing fields change.
- Vendor-only save still runs secondary-vendor rebuild and vendor calendar maintenance when vendor fields change.
- Publish / republish still queues publish-specific follow-up work, including deferred calendar publish and ticket integrity spot scan.

## Manual UI Test

Tested against Event Plan `76` in the real admin editor.

Verified:

- Event Plan `76` opens successfully with the Staff section initially deferred.
- Time + Lineup `Expand All` opens all `10` supporting-vendor cards.
- Time + Lineup `Collapse All` closes those detail cards again.
- Expanding Staff triggers lazy load and displays the full staffing editor with saved values.
- Editing a lazy-loaded Staff field and clicking Update succeeds.
- Refreshing the page and reopening Staff shows the saved value persisted.
- No PHP warnings/notices/fatal output appeared in page content.
- No page-level JavaScript exceptions were observed.

Observed browser issue:

- One existing admin asset 404 appeared during page load:
  - `/wp-includes/css/jquery-ui.min.css?ver=1.13.2`

This 404 was present as a browser console resource error, but it is outside the Patch 4 changes and not tied to the Event Plan lazy-load implementation.

## Remaining Dominant Phases

After Patch 4, the main remaining cost is still `event_plan_admin_screen_boot`.

Top remaining open-screen phases:

- `event_plan_admin_screen_boot`: `216 ms`
- `event_plan_details_meta_box_render`: `46 ms`
- `event_plan_partial_render_secondary-vendors`: `11 ms`
- `event_plan_vendor_availability_checks`: `8 ms`
- `event_plan_partial_render_time-lineup`: `8 ms`

The edit-screen bottleneck has shifted away from Time + Lineup rendering and toward admin boot/query volume.

## Recommendation For Patch 5

Patch 5 should target admin-screen boot query reduction.

Best next step:

- batch/cache expensive edit-screen boot lookups before the meta box render path
- focus on readiness/status checks, linked TEC lookup, ticket/add-on summary loading, and other repeated admin boot queries

Reason:

- Patch 4 materially reduced the meta-box and Time + Lineup cost.
- The largest remaining user-facing cost is now boot/query overhead, not save recursion or lineup rendering.
