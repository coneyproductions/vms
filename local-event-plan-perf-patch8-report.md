# VMS Event Plan Performance Patch 8 Report

Local only. No staging or production deploy.

## Changed Files

- `vms/includes/core/event-plan-performance.php`
- `vms/includes/cpt/event-plans.php`
- `vms/includes/cpt/event-plans/partials/time-lineup.php`
- `vms/assets/js/vms-lineup-schedule-admin.js`

## Patch Summary

Patch 8 added two things:

1. Baseline admin-screen memory/query tracing for:
   - Dashboard
   - Regular post edit
   - TEC event edit
   - Event Plan list
   - Event Plan edit

2. Lighter supporting-vendor option hydration on Event Plan edit:
   - render the full supporting-vendor option list once as a shared template
   - render each supporting-vendor `<select>` as placeholder + selected vendor only
   - hydrate the full option list client-side on editor load and for new supporting rows

## Baseline Admin Screen Comparison

| Screen | Queries | Peak MB | Current MB | Files | Woo files | TEC files | VMS files |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Dashboard | 301 | 214 | 214 | 4781 | 1146 | 1584 | 232 |
| Post edit | 399 | 228 | 224 | 4965 | 1294 | 1623 | 232 |
| TEC event edit | 350 | 216 | 216 | 4875 | 1143 | 1703 | 232 |
| Event Plan list | 344 | 219 | 214 | 4767 | 1155 | 1590 | 232 |
| Event Plan 76 edit | 600 | 218 | 216 | 4780 | 1156 | 1587 | 239 |

## Memory Attribution Findings

- Event Plan edit peak memory is not meaningfully higher than baseline admin screens.
- Event Plan edit was only `+4 MB` over Dashboard (`218 MB` vs `214 MB`).
- Event Plan edit was lower than regular post edit (`218 MB` vs `228 MB`).
- Event Plan edit added only `7` extra VMS files over the baseline admin screens (`239` vs `232`).
- Event Plan edit added only `10` Woo files over Dashboard (`1156` vs `1146`).
- Event Plan edit added only `3` TEC files over Dashboard (`1587` vs `1584`).

Conclusion:

- The persistent `~216-218 MB` peak is mostly WordPress/Woo/TEC admin baseline, not Event Plan-specific memory growth.
- Patch 8 did not find a large VMS-only memory block to remove safely in this pass.

## Vendor Option Context Findings

Warm open-edit rerun on Event Plan `76`:

- `event_plan_vendor_option_context`: `9 ms`, `q+2`
- supporting vendor options available: `76`
- supporting vendor selects rendered on initial page: `11`
- shared option payload: `8824 bytes`
- time-lineup HTML output: `331782 bytes`

Patch 7 reference:

- time-lineup HTML output: `419165 bytes`

Effect:

- shared supporting-vendor options cut initial time-lineup markup by about `87383 bytes`
- supporting-vendor full option HTML now exists once instead of being repeated across each supporting row and the template row
- the editor still hydrates correctly to `77` visible `<option>` entries per supporting select in the browser (`76` vendors + placeholder)

## Open Edit Comparison

Patch 7 warmed open-edit baseline:

- `372` queries
- `216 MB` peak
- `199 ms` admin boot
- `36 ms` details render
- `4 ms` time-lineup render

Patch 8 warmed open-edit rerun:

- `597` queries
- `218 MB` peak
- `236 ms` admin boot
- `35 ms` details render
- `3 ms` time-lineup render

Interpretation:

- Patch 8 improved the Event Plan-specific vendor markup path.
- Patch 8 did **not** improve overall open-edit query count.
- The query spike is happening before the vendor option context work starts:
  - `admin_boot_start`: `190` queries
  - `details_meta_box_render start`: `530` queries
  - `event_plan_vendor_option_context start`: `538` queries
- The vendor option context itself is not the remaining bottleneck.

## Save / Update Regression Check

### No-change Update

Patch 7:

- `316` queries
- `209 MB` peak

Patch 8:

- `316` queries
- `207 MB` peak
- `save_post`: `1`
- internal `wp_update_post`: `0`
- auto-title: `skip / no_op`
- secondary-vendor rebuild: `skip / no_vendor_change`
- vendor calendar maintenance: `skip / no_vendor_change`
- staffing save: `skip / no_staffing_change`
- Woo ticket/product sync: none observed
- Action Scheduler jobs: none observed
- title/status behavior: unchanged

### Other Save / Publish Scenarios

| Scenario | Patch 7 queries | Patch 8 queries | Patch 7 peak MB | Patch 8 peak MB | Notes |
| --- | ---: | ---: | ---: | ---: | --- |
| Basic field update | 316 | 316 | 213 | 211 | still no-op for vendor/staffing |
| Featured image change | 328 | 328 | 217 | 217 | still no-op for vendor/staffing |
| Vendor-only change | 352 | 361 | 213 | 211 | secondary-vendor rebuild still runs; calendar maintenance still queues |
| Staffing-only change | 427 | 427 | 213 | 211 | staffing still runs with dirty reason; vendor rebuild still skips |
| Vendor + staffing change | 447 | 447 | 205 | 205 | both dirty paths still run |
| Publish / republish | 356 | 356 | 213 | 211 | publish-specific jobs preserved |

### Publish / Republish Preservation

Confirmed preserved on `publish_republish`:

- cron queued: `vms_event_plan_deferred_calendar_publish`
- cron queued: `vms_ticket_integrity_spot_scan`
- queue meta writes still occur for publish-specific flows
- no duplicate save pass regression

## Manual UI Checks

Browser validation on Event Plan `76`:

- Ticketing summary-first view still visible
- Readiness Summary still visible
- Primary vendor selector still visible
- Supporting Vendor `Expand All` still opens supporting cards
- Supporting Vendor `Collapse All` still closes supporting cards
- supporting vendor selects hydrate to `77` options in-browser
- Secondary Vendors lazy section still loads
- Secondary Vendor legend still appears after load
- Readiness details lazy section still loads
- Staff lazy section still loads
- no visible PHP warnings/notices
- no page JS exceptions
- known unrelated browser error still present:
  - `404 /wp-includes/css/jquery-ui.min.css?ver=1.13.2`

Persistence checks:

- Real save/update persistence was revalidated through the existing authenticated admin-form scenario suite.
- A headless Chrome save+refresh loop hit a WordPress redirect/navigation race (`net::ERR_ABORTED`) after successful save triggers, so browser-save assertions were not used as the source of truth for scenario metrics.

## Assessment

### Is Woo/TEC memory load baseline or VMS-triggered?

Mostly baseline.

Evidence:

- Dashboard already loads `1146` Woo files and `1584` TEC files.
- Event Plan edit only raises that to `1156` Woo and `1587` TEC.
- Peak memory on Event Plan edit is close to Dashboard and below regular post edit.

### Is vendor option hydration a meaningful contributor?

Only as HTML payload, not as the main boot/query bottleneck.

Evidence:

- `event_plan_vendor_option_context` is now only `9 ms` and `q+2` on the warmed open-edit rerun.
- Supporting-vendor markup shrank by about `87 KB`.
- Total open-edit query count still remained dominated by the pre-details admin boot tail.

## Remaining Dominant Phases

- `event_plan_admin_screen_boot`
- pre-details admin boot work between `admin_boot_start` and `details_meta_box_render start`
- remaining Woo/TEC-heavy admin tail after the Event Plan details metabox completes

## Recommendation for Patch 9

Patch 9 should target the broader admin boot query tail, not deeper vendor selector work.

Recommended next patch:

- **Woo/TEC admin boot guard / deeper admin boot query batching**

Why:

- Patch 8 shows the vendor option context is already cheap enough.
- Memory is mostly baseline.
- The remaining open-edit regression is query-heavy and happens before the Event Plan-specific vendor UI work starts.
- Deeper vendor AJAX search is possible later, but it is no longer the highest-signal next move.
