# VMS Event Plan Performance Patch 6 Report

Local only. No staging or production deploy.

Test target: Event Plan `76`

## Changed files

- `vms/includes/cpt/event-plans.php`
- `vms/includes/cpt/event-plans/partials/secondary-vendors.php`
- `vms/includes/cpt/event-plans/partials/advanced-controls.php`

## Patch summary

Patch 6 focused on admin boot query churn around readiness and vendor summaries.

It adds request-local summary builders and cache helpers for:

- integrity / missing-vendor warning state
- primary vendor summary state
- secondary-vendor summary state
- readiness / publish-warning state
- per-vendor availability state
- per-vendor default comp summary
- per-vendor tax summary
- vendor type slug maps and secondary-vendor qualification checks

It also moves the Secondary Vendors partial onto that precomputed summary so the edit screen no longer rebuilds the same vendor/admin state inside the partial render.

## Readiness / status phases traced

Patch 6 added or refined trace coverage for:

- `event_plan_readiness_boot`
- `event_plan_missing_vendor_integrity_boot`
- `event_plan_admin_boot_readiness`
- `event_plan_admin_boot_integrity_flags`
- `event_plan_admin_boot_linked_tec`
- `event_plan_admin_boot_ticket_summary`
- `event_plan_admin_boot_add_on_summary`

These spans now record elapsed time, query delta, peak memory checkpoint, and cache hit/miss state when `VMS_EP_PERF_TRACE` is enabled.

## Vendor / admin summary phases traced

Patch 6 added or refined trace coverage for:

- `event_plan_vendor_summary_boot`
- `event_plan_vendor_availability_boot`
- `event_plan_vendor_conflict_boot`
- `event_plan_secondary_vendor_summary_boot`
- `event_plan_admin_boot_vendor_summary`
- `event_plan_admin_boot_secondary_vendor_state`
- `event_plan_admin_boot_availability_conflict`

The boot path now distinguishes between:

- `phase=run`
- `phase=summary_only`
- cache `hit` / `miss`
- deferred conflict detail on initial boot

## Memoized, batched, and deferred lookups

Memoized / batched:

- request-local Event Plan meta bundle reuse
- request-local linked TEC summary reuse
- request-local ticket/add-on summary reuse
- request-local integrity summary reuse
- request-local readiness summary reuse
- request-local primary vendor summary reuse
- request-local secondary-vendor summary reuse
- request-local vendor availability / comp default / tax summary reuse
- request-local vendor type slug map reuse
- request-local secondary-vendor missing-item checks

Deferred / summary-first:

- vendor conflict detail is now booted as summary-only
- readiness boot reuses summary inputs instead of re-fetching linked TEC / ticket / add-on / integrity data
- Secondary Vendors partial consumes prebuilt pool rows instead of re-running per-row taxonomy and qualification work

## Open edit screen comparison

Patch 5 baseline:

- `374` queries
- `216 MB` peak
- `173 ms` admin boot
- `25 ms` details meta box render
- `4 ms` time-lineup partial

Patch 6 warmed open-edit result:

- `372` queries
- `216 MB` peak
- `185 ms` admin boot
- `34 ms` details meta box render
- `4 ms` time-lineup partial

Delta:

- queries: `374 -> 372`
- peak memory: unchanged at `216 MB`
- admin boot time: not materially improved in this local run
- time-lineup remained flat

Important local variance note:

- the first cold open request after trace reset spiked much higher before settling
- the representative number above is the warmed `open_edit_screen` request from the clean scenario suite

Patch 6-specific open-edit observations:

- `event_plan_vendor_summary_boot`: `8 ms`, `q+2`
- `event_plan_vendor_availability_boot`: `8 ms`, `q+2`
- `event_plan_secondary_vendor_summary_boot`: `3 ms`, `q+1`
- `event_plan_readiness_boot`: `1 ms`, `q+0`
- `event_plan_partial_render_secondary-vendors`: `1 ms`, `q+1`

The main measurable win from Patch 6 was reducing repeated vendor/admin summary work and trimming open-edit query count slightly. It did not deliver a meaningful admin-boot time drop on this local dataset.

## No-change Update comparison

Patch 5 baseline:

- `322` queries
- `207 MB` peak
- `save_post` passes: `1`
- internal `wp_update_post()`: `0`

Patch 6:

- `322` queries
- `207 MB` peak
- `save_post` passes: `1`
- internal `wp_update_post()`: `0`

Confirmed preserved behavior:

- `event_plan_auto_title_sync`: `phase=skip`, `skip_reason=no_op`
- `event_plan_secondary_vendor_rebuild`: `phase=skip`, `skip_reason=no_vendor_change`
- `event_plan_calendar_vendor_maintenance`: `phase=skip`, `skip_reason=no_vendor_change`
- `event_plan_staffing_save`: `phase=skip`, `skip_reason=no_staffing_change`
- no Woo ticket/product sync observed
- no Action Scheduler jobs observed
- no title/status change observed

## Other save / publish comparisons

| Scenario | Patch 5 queries | Patch 6 queries | Patch 5 peak | Patch 6 peak | Notes |
| --- | ---: | ---: | ---: | ---: | --- |
| Basic field Update | 320 | 320 | 215 MB | 215 MB | unchanged |
| Featured image change | 332 | 332 | 217 MB | 215 MB | unchanged behavior |
| Vendor + staffing change | 447 | 447 | 213 MB | 213 MB | vendor + staffing work preserved |
| Staffing-only change | 423 | 423 | 201 MB | 197 MB | staffing work preserved |
| Vendor-only change | 352 | 358 | 211 MB | 211 MB | slight local query variance; vendor rebuild still runs, staffing still skips |
| Publish / republish | 348 | 358 | 213 MB | 213 MB | publish-specific jobs preserved; slight local query variance |

Publish / republish still preserved:

- deferred calendar publish behavior
- ticket integrity spot scan behavior
- expected publish-specific jobs in trace

## Manual UI test results

Browser target: Event Plan `76` in wp-admin using the real admin edit form.

Confirmed in the browser:

- Ticketing still opens summary-first on initial load
- Ticketing summary shows `Configured tickets`, `Configured add-ons`, `Linked TEC status`, and `Load full ticketing editor`
- Workflow and Advanced Controls still render
- lineup warning summary still renders: `3 lineup warnings need review.`
- conflict summary still renders: `Conflicts: 0`
- primary vendor summary still renders; selected option remained `QA Band Vendor 1775435985240 [?] [T⚠]`
- Secondary Vendors section still opens and shows type legend plus vendor rows
- supporting lineup `Expand All` / `Collapse All` still work
- `Expand All` reached `12` open detail blocks
- `Collapse All` returned to `1` remaining open block, matching current local behavior

Staff lazy-load verification:

- the Staff section still lazy-loads in the admin flow
- after opening the nested vendor/staff area, the Staff editor loaded and exposed `vms_staff_role_headcount[4]`
- a real admin save changed that value from `1 -> 2`
- after refresh, the authenticated lazy Staff endpoint returned the persisted value `2`
- baseline was then restored locally and verified back at `1`

No new browser/runtime regressions observed:

- no page errors
- no PHP warning/notice text rendered
- console/network failures remained limited to the pre-existing unrelated asset error

Existing unrelated issue still present:

- `404 /wp-includes/css/jquery-ui.min.css?ver=1.13.2`

## Remaining dominant phases after Patch 6

Warmed open-edit trace still concentrates most cost in:

1. `event_plan_admin_screen_boot`
2. `event_plan_details_meta_box_render`
3. `event_plan_vendor_availability_boot`
4. `event_plan_vendor_summary_boot`
5. `event_plan_admin_boot_availability_conflict`

## Recommendation for Patch 7

Patch 7 should move to deeper admin-boot batching or lazy detail panels for readiness/vendor warnings.

Best next options:

1. lazy-load readiness / integrity detail panels while keeping warning badges visible
2. reduce the initial primary-vendor option hydration work further
3. continue moving vendor/staffing detail sections toward summary-first boot
4. investigate the remaining open-edit memory plateau separately from query work
