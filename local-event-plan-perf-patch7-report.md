# VMS Event Plan Performance Patch 7 Report

Local only. No staging or production deploy.

Test target: Event Plan `76`

Raw trace:

- `wp-content/vms-event-plan-perf-trace.log`

Local-only test helper note:

- the local runner at `/tmp/vms_ep_perf_runner.php` was updated so vendor-mutating scenarios explicitly load the lazy Secondary Vendors section before posting
- this was needed after Patch 7 made Secondary Vendors lazy on initial open

## Changed files

- `vms/includes/core/event-plan-performance.php`
- `vms/includes/cpt/event-plans.php`
- `vms/includes/cpt/event-plans/partials/readiness-details.php`

## Patch summary

Patch 7 did two things:

1. moved vendor/readiness detail views further toward summary-first boot
2. added memory attribution checkpoints so the remaining `216 MB` peak can be tied to concrete phases

Initial-open behavior changes:

- Secondary Vendors now renders as a summary shell on first open and lazy-loads the full vendor availability / qualification editor when expanded
- Readiness now renders a lightweight `Readiness Summary` card on first open and lazy-loads the detailed warning panel when expanded
- the Secondary Vendors lazy shell now uses the same `lazy_unloaded` sentinel pattern as Staff so a collapsed untouched section does not trigger save-time vendor rebuilds

## Vendor / readiness details made summary-first or lazy-loaded

Summary-first on initial open:

- `Readiness Summary`
  - publish-blocking warning count
  - vendor warning count
  - linked TEC summary status
  - configured ticket count
- Secondary Vendors shell
  - selected type summary
  - selected vendor count
  - warning count

Lazy-loaded on demand:

- full secondary-vendor editor
- secondary-vendor availability / qualification detail list
- readiness warning detail panel
- linked TEC / ticket / add-on detail context shown inside readiness details

Trace markers added for that behavior:

- `event_plan_vendor_conflict_details phase=lazy_available`
- `event_plan_readiness_details phase=lazy_available`
- `event_plan_secondary_vendor_render phase=summary_only skip_reason=collapsed_initial_load`

## Memory checkpoints added

Patch 7 adds `event_plan_memory_checkpoint` entries around:

- admin boot start / end
- details meta box render before / after
- vendor summary boot
- vendor availability boot
- vendor conflict / option-context boot
- secondary-vendor summary boot
- secondary-vendor availability boot
- readiness boot
- readiness summary render
- time-lineup render
- ticketing summary render
- advanced controls render
- Secondary Vendors shell render
- Staff shell render

Each checkpoint records:

- elapsed context via surrounding span
- query count delta from previous checkpoint
- current memory usage
- peak memory usage
- memory delta from previous checkpoint
- dependency/include snapshot where requested

## Open edit screen comparison

Patch 6 warmed baseline:

- `372` queries
- `216 MB` peak
- `185 ms` admin boot
- `34 ms` details meta box render
- `4 ms` time-lineup partial

Patch 7 warmed open-edit result:

- `372` queries
- `216 MB` peak
- `199 ms` admin boot
- `36 ms` details meta box render
- `4 ms` time-lineup partial

Delta:

- queries: flat
- peak memory: flat
- admin boot: no meaningful improvement in this local warmed run
- details render: slightly slower locally
- time-lineup: flat

Patch 7-specific open-edit observations:

- `event_plan_vendor_availability_boot`: `8 ms`, `q+2`, memory flat at `198 MB`
- `event_plan_admin_boot_availability_conflict`: `9 ms`, `q+2`, memory flat at `198 MB`
- `event_plan_secondary_vendor_summary_boot`: `1 ms`, `q+1`, `detail_mode=summary_only`
- `event_plan_readiness_boot`: `1 ms`, `q+0`, `detail_mode=summary_only`
- full Secondary Vendors detail render is no longer on the initial path; the trace now reports `event_plan_vendor_conflict_details phase=lazy_available`
- readiness detail content is no longer on the initial path; the trace now reports `event_plan_readiness_details phase=lazy_available`

Result:

- Patch 7 safely moved vendor/readiness detail content off the initial request
- it did not materially lower the warmed open-edit headline metrics on this local dataset

## No-change Update comparison

Patch 6 baseline:

- `322` queries
- `207 MB` peak
- `save_post` passes: `1`
- internal `wp_update_post()`: `0`

Patch 7:

- `316` queries
- `209 MB` peak
- `save_post` passes: `1`
- internal `wp_update_post()`: `0`

Confirmed preserved behavior:

- `event_plan_auto_title_sync`: `phase=skip`, `skip_reason=no_op`
- `event_plan_secondary_vendor_rebuild`: `phase=skip`, `skip_reason=no_vendor_change`, `lazy_unloaded=1`
- `event_plan_calendar_vendor_maintenance`: `phase=skip`, `skip_reason=no_vendor_change`, `lazy_unloaded=1`
- `event_plan_staffing_save`: `phase=skip`, `skip_reason=no_staffing_change`, `lazy_unloaded=1`
- `event_plan_staffing_seed`: `phase=skip`, `skip_reason=no_relevant_change`
- no Woo ticket/product sync observed
- no Action Scheduler jobs observed
- no title/status change observed

Important regression fix:

- the first Patch 7 draft caused no-change Update to re-enter secondary-vendor save work because the collapsed lazy section did not submit vendor fields
- this was fixed by adding `vms_secondary_vendors_lazy_unloaded` and skipping vendor rebuild / calendar maintenance when the section stayed untouched

## Other save / publish comparisons

| Scenario | Patch 6 queries | Patch 7 queries | Patch 6 peak | Patch 7 peak | Notes |
| --- | ---: | ---: | ---: | ---: | --- |
| Basic field Update | 320 | 316 | 215 MB | 213 MB | no vendor/staffing regression |
| Featured image change | 332 | 328 | 215 MB | 217 MB | featured image path preserved |
| Vendor + staffing change | 447 | 447 | 213 MB | 205 MB | vendor rebuild and staffing work preserved |
| Staffing-only change | 423 | 427 | 197 MB | 213 MB | staffing work preserved; local variance higher |
| Vendor-only change | 358 | 352 | 211 MB | 213 MB | secondary-vendor rebuild still runs |
| Publish / republish | 358 | 356 | 213 MB | 213 MB | publish-specific jobs preserved |

Publish / republish preserved:

- deferred calendar publish scheduling
- ticket integrity spot scan scheduling
- no accidental vendor/staffing churn on unchanged collapsed sections

## Manual UI test results

Browser target: Event Plan `76` in wp-admin using the real admin edit form.

Confirmed in the browser:

- `Readiness Summary` card renders on initial open
- publish-blocking and vendor-warning summary rows still show on initial load
- `Readiness details` lazy-loads successfully when expanded
- current readiness state was clean in this local baseline, so the details panel showed no warning list
- Ticketing still opens summary-first on initial load
- linked TEC status still appears
- configured ticket count still appears
- primary vendor summary still shows the selected vendor: `QA Band Vendor 1775435985240 [?] [T⚠]`
- Secondary Vendors lazy-loads successfully when expanded
- current baseline secondary-vendor type loaded as `food_truck`
- the full Secondary Vendors editor showed `3` vendor rows and the type legend
- Staff still lazy-loads successfully
- a real admin save changed `vms_staff_role_headcount[4]` from `1 -> 2`
- refresh preserved the saved value through the lazy Staff section
- baseline was restored locally back to `1`

No new runtime regressions observed:

- no PHP warning/notice text rendered
- no new persistent browser console errors

Existing unrelated issue still present:

- `404 /wp-includes/css/jquery-ui.min.css?ver=1.13.2`

Headless browser note:

- one transient `admin-ajax.php` aborted request appeared only during page navigation after save
- that did not block lazy section behavior and did not present as a page error

## Memory attribution findings

Patch 7’s strongest result was attribution:

- the request was already at `190 MB` current / `195 MB` peak at `admin_boot_start`
- that starting footprint already included:
  - `903` Woo files
  - `1511` TEC/Event Tickets files
  - `232` VMS files
- by `details_meta_box_before`, memory had already reached `196 MB`
- by `details_meta_box_after`, memory was `200 MB`
- the vendor/readiness summary spans themselves were effectively memory-flat:
  - vendor summary stayed at `198 MB`
  - vendor availability stayed at `198 MB`
  - secondary-vendor summary stayed at `198 MB`
  - readiness summary stayed at `200 MB`
- `admin_boot_end` finally reached `216 MB`
- by that point the include graph had grown to:
  - `1148` Woo files
  - `1586` TEC/Event Tickets files
  - `239` VMS files

Most likely memory source:

- the persistent high-memory floor is not coming primarily from the new readiness or secondary-vendor detail builders
- it is coming from broader wp-admin request assembly plus the already-loaded Woo / TEC class and include graph that exists before the Event Plan-specific detail sections finish

Useful payload-size clues:

- vendor summary cached payload: about `92 KB`
- secondary-vendor summary cached payload in summary-only mode: about `601 bytes`
- readiness detail summary payload: about `422 bytes`
- time-lineup rendered HTML: about `419 KB`

Interpretation:

- Patch 7 successfully made readiness and secondary-vendor detail payloads small
- the remaining `216 MB` peak is more about cumulative loaded code / broader admin-page construction than about those detail payloads alone

## Remaining dominant phases after Patch 7

Warmed open-edit trace still concentrates most cost in:

1. `event_plan_admin_screen_boot`
2. `event_plan_details_meta_box_render`
3. `event_plan_vendor_summary_boot`
4. `event_plan_vendor_availability_boot`
5. `event_plan_admin_boot_availability_conflict`

The remaining unexplained memory rise happens after the captured vendor/readiness checkpoints and before `admin_boot_end`, which points away from the new lazy detail panels and toward broader admin request assembly.

## Recommendation for Patch 8

Patch 8 should be a targeted memory-reduction / vendor-option-hydration pass.

Best next target:

1. reduce or defer full vendor option-context hydration during initial open
   - `build_event_plan_vendor_option_context()` is still the clearest always-on vendor boot cost
2. add one more layer of boot-tail attribution around the final admin-page assembly if needed
3. investigate whether some current initial vendor option labels/default-fee/tax metadata can be reduced or lazily completed without breaking editor interactions

Reason:

- Patch 7 already moved readiness and secondary-vendor detail panels off the initial path
- those sections were not the primary memory source
- the next likely win is the always-on vendor option build plus the broader Woo / TEC admin include footprint
