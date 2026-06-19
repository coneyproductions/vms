# VMS Event Plan Performance Audit

Date: 2026-06-02

Environment:
- Local only
- VMS `0.2.24.703`
- Trace flag enabled via `VMS_EP_PERF_TRACE`
- Trace log: `wp-content/vms-event-plan-perf-trace.log`

Fixture:
- Event Plan `76` (`Whitehouse Opry`)
- Local enrichment added for diagnostics:
  - 4 ticket rows
  - 2 add-on rows
  - primary vendor
  - 2 supporting acts
  - 2 secondary vendors
  - staffing assignments
  - linked TEC event
  - featured image

## Scenario Summary

| Scenario | Query max | Peak memory | Save pass max | Internal `wp_update_post()` max | Jobs queued / queue writes |
| --- | ---: | ---: | ---: | ---: | --- |
| Open edit screen only | 653 | 216 MB | 0 | 0 | none |
| No-change Update | 602 | 203 MB | 2 | 1 | queued calendar maintenance + queued staffing seed; wrote calendar/staffing queue meta |
| Small content change Update | 488 | 205 MB | 2 | 1 | no new jobs; queue helpers still ran and skipped because prior no-change request already held schedule/lock state |
| Featured image change Update | 502 | 199 MB | 2 | 1 | no new jobs; linked TEC thumbnail synced immediately, on-save sync then hit duplicate guard |
| Vendor/staffing-related Update | 500 | 197 MB | 2 | 1 | no new jobs in this sequential run; queue helpers still ran and skipped because prior no-change request already held schedule/lock state |
| Publish / republish | 555 | 195 MB | 2 | 1 | queued ticket integrity spot scan + deferred calendar publish; wrote publish queue meta |

Notes:
- No Action Scheduler enqueue/schedule entries were observed in any scenario.
- Jobs observed here were WP-Cron plus queue-state post meta plus transient locks.
- Scenarios 3-5 were run after scenario 2. That means later queue helpers often reported `already_scheduled=1` / `already_locked=1` rather than writing fresh queue state again.

## Slowest Sections

Overall slowest measured sections:
1. `event_plan_admin_screen_boot` on edit-screen open: 288 ms
2. `save_post_vms_event_plan_core` on publish/republish: 82 ms
3. `save_post_vms_event_plan_core` on no-change Update: 67 ms
4. `event_plan_details_meta_box_render` on edit-screen open: 61 ms
5. `event_plan_partial_render_time-lineup` on edit-screen open: 27 ms
6. `vms_internal_wp_update_post` on publish/republish: 23 ms
7. `vms_internal_wp_update_post` on no-change Update: 22 ms
8. `event_plan_vendor_availability_checks` on edit-screen open: 15 ms
9. `vms_staffing_seed_template_on_save` on no-change Update: 13 ms
10. `vms_staffing_queue_seed_event_slots` on no-change Update: 11 ms

Edit-screen load hotspots:
1. `event_plan_admin_screen_boot`: 288 ms
2. `event_plan_details_meta_box_render`: 61 ms
3. `event_plan_partial_render_time-lineup`: 27 ms
4. `event_plan_vendor_availability_checks`: 15 ms
5. `event_plan_partial_render_secondary-vendors`: 9 ms
6. `event_plan_staff_render_context`: 6 ms
7. `event_plan_partial_render_advanced-controls`: 4 ms

No-change Update hotspots:
1. `save_post_vms_event_plan_core`: 67 ms
2. `vms_internal_wp_update_post`: 22 ms
3. `vms_staffing_seed_template_on_save`: 13 ms
4. `vms_staffing_queue_seed_event_slots`: 11 ms
5. `event_plan_secondary_vendor_rebuild`: 9 ms

## No-Change Update Findings

Observed on a plain Update with no field edits:

- `save_post_vms_event_plan` ran twice.
- An internal `wp_update_post()` ran once with reason `event_plan_auto_title_sync`.
- The internal update added a second heavy save-hook pass even though the save-profiler showed no core post-field change.
- `event_plan_secondary_vendor_rebuild` still ran.
- Secondary-vendor rebuild still queued calendar maintenance with reason `vendor_category_sync`.
- Staffing seed save logic still ran and queued `vms_staffing_seed_event_slots_queued`.
- Staffing queue-state meta was written:
  - `_vms_staffing_seed_queue_state`
  - `_vms_staffing_seed_queued_at`
  - `_vms_staffing_seed_actor_user_id`
  - `_vms_staffing_seed_reason`
- TEC status sync still ran, but it was effectively free in this trace (`0 ms`).
- Featured-image sync still ran, but it immediately resolved as `already_synced` / duplicate-guarded and stayed cheap (`0-1 ms`).
- No Woo ticket/product sync hook was observed on plain Update.
- No Action Scheduler enqueue/schedule was observed on plain Update.

Interpretation:
- The biggest no-change offender is not one giant ticketing call. It is a combination of:
  - recursive save churn from internal title sync
  - unconditional secondary-vendor rebuild
  - unconditional staffing seed maintenance path

## Module-Specific Notes

Recursive / internal save churn:
- Every save scenario hit `internal_wp_update_post_count=1` and `save_pass_count=2`.
- The recorded reason was always `event_plan_auto_title_sync`.
- This is the cleanest confirmed source of duplicated save-hook work.

Secondary vendors:
- `event_plan_secondary_vendor_rebuild` ran on every save scenario.
- On the first no-change Update it also scheduled calendar maintenance and wrote queue meta.
- Later scenarios hit the same queue path but correctly skipped fresh writes because the job was already scheduled/locked.

Staffing:
- Staffing save hooks ran on every save scenario.
- On the first no-change Update, staffing seed scheduling was not skipped; it queued work and wrote queue-state meta.
- Later scenarios correctly skipped fresh queue writes only because the seed job was already scheduled/locked.

TEC / calendar:
- `event_plan_tec_status_sync` ran on every save scenario, but was cheap in this sample.
- The expensive calendar side effect came from secondary-vendor-driven maintenance queueing, not from the tiny status-sync call itself.

Featured image:
- Featured-image change hit linked TEC thumbnail sync immediately (`updated`), then the save-hook sync hit the request guard and became a duplicate no-op.
- Plain Update still calls the featured-image sync path, but it stayed cheap.

Ticketing / Woo:
- No direct Woo ticket/product sync was observed in these standard editor saves.
- Publish/republish did queue ticket integrity spot-scan follow-up.

## Recommendation

Safest first production-worthy patch:

1. Add a no-op guard around the internal auto-title sync so VMS does not call `wp_update_post()` when the computed auto title already matches the current post title.

Why this first:
- It removes the confirmed second `save_post` pass seen in every save scenario.
- It is a no-behavior-change guard.
- It directly improves the worst no-change Update failure mode.
- It likely reduces duplicate downstream hook churn across ticket-integrity, staffing, featured-image, and other save listeners.

Immediate follow-up patch after that:
1. Add dirty checks so unchanged secondary-vendor inputs do not rebuild derived vendor data or queue calendar maintenance.
2. Add dirty checks so unchanged staffing inputs do not enter the seed-maintenance queue path.

In short:
- Patch the recursive auto-title `wp_update_post()` first.
- Then patch unchanged secondary-vendor / staffing work on plain Update.
