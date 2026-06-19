# VMS Event Plan Performance Patch 10

Local-only ticket module-hub reduction pass for Event Plan `76`.

## Changed files

- `vms/includes/admin/event-command-center.php`

## What Patch 10 changed

### Ticket module hub tracing

- added summary-path trace events for:
  - `command_center_module_hub_ticket`
  - `command_center_ticket_summary`
  - `command_center_ticket_meta`
  - `command_center_ticket_tec_lookup`
  - `command_center_ticket_woo_lookup`
  - `command_center_ticket_integrity`
  - `command_center_ticket_full_details`
- added command-center ticket query checkpoints for:
  - `command_center_ticket_start`
  - `command_center_ticket_summary`
- kept the existing module-hub checkpoint:
  - `command_center_module_hub_ticket`

### Summary-first / deferred behavior

- removed the edit-screen module-hub ticket card’s dependency on `vms_event_command_center_get_ticket_reporting_truth()`
- kept the full Command Center page on the original source-of-truth path:
  - `vms_event_command_center_get_ticket_snapshot()`
  - `vms_event_command_center_get_ticket_reporting_truth()`
- built a cheap edit-screen summary from:
  - one ticket-related Event Plan meta bundle
  - linked TEC ID/status only
  - saved ticket config / sync counts
  - cached ticket stats meta
  - stored ticket-integrity result entry, when present
- changed the edit-screen card to show:
  - configured tickets
  - configured add-ons
  - linked calendar status
  - cached sales snapshot
  - cached snapshot age
- moved full ticket detail access behind:
  - `Open Full Ticket Report` -> full Event Command Center page

## Ticket module query fingerprints

### Patch 9 baseline inside `command_center_module_hub_ticket`

| Rank | Source | Count | Pattern family |
| --- | --- | ---: | --- |
| 1 | `vms` | 40 | repeated taxonomy scans |
| 2 | `vms` | 24 | repeated post hydration |
| 3 | `vms` | 23 | repeated postmeta bundle loads |
| 4 | `vms` | 20 | repeated taxonomy + termmeta reads |
| 5 | `vms` | 16 | repeated TEC ticket-product lookup |
| 6 | `vms` | 16 | repeated Woo order-item meta reads |
| 7 | `vms` | 16 | repeated Woo order-item meta batch reads |
| 8 | `vms` | 12 | repeated Woo order-item reads |

### Patch 10 result inside `command_center_module_hub_ticket`

- initial edit-screen open emitted no meaningful query fingerprints for the ticket summary scope
- `command_center_module_hub_ticket` delta dropped to `0 queries / 0 ms` on the warmed suite run
- the card now reads cached/meta-backed summary data already available in request scope instead of running the heavy ticket truth/report path

## Open-edit comparison

Patch 9 baseline:

- queries: `600`
- peak memory: `218 MB`
- admin boot: `260 ms`
- details render: `38 ms`
- time-lineup render: `3 ms`
- `command_center_module_hub_ticket`: `253 queries / 19.856 ms`

Patch 10 full-suite open-edit (`request_id=63220a29f92a`):

- queries: `365`
- peak memory: `218 MB`
- admin boot: `235 ms`
- details render: `38 ms`
- time-lineup render: `3 ms`
- `command_center_module_hub_ticket`: `0 queries / 0 ms`

Net effect:

- major drop in the ticket module-hub cost on initial open
- measurable total open-edit query reduction
- no memory win expected here; peak remained baseline-heavy at `218 MB`

## Module-hub ticket before / after

| Metric | Patch 9 | Patch 10 |
| --- | ---: | ---: |
| `command_center_module_hub_ticket` queries | 253 | 0 |
| `command_center_module_hub_ticket` elapsed | 19.856 ms | 0 ms |
| ticket card mode | heavy truth/report path | summary-only |
| full detail access | paid on initial edit open | deferred to full Command Center |

Patch 10 ticket summary trace on initial edit open:

- `command_center_ticket_summary phase=run cache=miss`
- `command_center_ticket_meta phase=run cache=miss`
- `command_center_ticket_tec_lookup phase=summary_only`
- `command_center_ticket_woo_lookup phase=summary_only`
- `command_center_ticket_integrity phase=summary_only`
- `command_center_ticket_full_details phase=lazy_available`
- `command_center_module_hub_ticket phase=full_detail_deferred reason=initial_edit_screen`
- `command_center_module_hub_ticket phase=summary_only`

## No-change Update comparison

Patch 9 baseline:

- queries: `316`
- `save_post`: `1`
- internal `wp_update_post`: `0`
- auto-title: no-op
- secondary-vendor rebuild: skipped
- vendor calendar maintenance: skipped
- staffing: skipped
- no Woo sync
- no Action Scheduler jobs

Patch 10:

- queries: `316`
- peak memory: `207 MB`
- `save_post`: `1`
- internal `wp_update_post`: `0`
- auto-title: `skip / no_op`
- secondary-vendor rebuild: `skip / no_vendor_change`
- vendor calendar maintenance: `skip / no_vendor_change`
- staffing save: `skip / no_staffing_change`
- staffing seed: `skip / no_relevant_change`
- staffing queue meta: `skip / no_relevant_change`
- no Woo sync
- no Action Scheduler jobs

Result:

- no regression in the clean no-change Update path

## Other save / publish scenarios

Patch 9 -> Patch 10:

| Scenario | Patch 9 queries | Patch 10 queries | Notes |
| --- | ---: | ---: | --- |
| `basic_field_update` | 316 | 316 | unchanged |
| `featured_image_change` | 328 | 328 | unchanged |
| `staffing_only_change` | 427 | 427 | unchanged; staffing dirty path preserved |
| `vendor_only_change` | 357 | 360 | small +3 drift; vendor rebuild still runs as expected |
| `vendor_staffing_change` | 446 | 446 | unchanged |
| `publish_republish` | 356 | 356 | unchanged; publish jobs preserved |

Publish / republish remained intact:

- `vms_event_plan_schedule_deferred_calendar_publish`
- `vms_ticket_integrity_queue_spot_scan`

## Manual UI results

Browser smoke on Event Plan `76` plus direct authenticated readiness-detail load:

- Command Center ticket card still appears
- ticket card status remained visible and warning-safe:
  - status chip: `Red`
  - warning text: `Woo inventory disagrees with the intended sellability state`
- ticket card summary showed essential summary data:
  - `Configured tickets: 4`
  - `Configured add-ons: 2`
  - `Linked calendar status: Publish`
  - `Cached sales: 0 paid / $0.00`
  - `Refreshed 1 month ago`
- `Open Full Ticket Report` linked to:
  - `/wp-admin/admin.php?page=vms-event-command-center&plan_id=76`
- full Command Center still loaded and rendered:
  - `Event Command Center`
  - `Ticket Snapshot`
- Ticketing summary-first behavior still worked:
  - `Load full ticketing editor` visible
- current primary vendor still displayed correctly:
  - select value: `83`
  - summary label: `6 Miles to Mixon`
- supporting vendor controls still worked:
  - `Collapse All` -> `0` supporting cards open
  - `Expand All` -> `10` supporting cards open
  - first supporting select -> `77` options
- Secondary Vendors lazy section still worked:
  - initial `data-vms-lazy-loaded="0"`
  - after expand `data-vms-lazy-loaded="1"`
  - `3` rows rendered
- Staff lazy section still worked:
  - initial `data-vms-lazy-loaded="0"`
  - after expand `data-vms-lazy-loaded="1"`
- Readiness details lazy payload still loaded through the authenticated admin endpoint:
  - `success=true`
  - HTML length `1305`
  - contained `Configured tickets`
- no PHP warnings/notices
- no new browser-console regressions
- the existing unrelated 404 is still present:
  - `/wp-includes/css/jquery-ui.min.css?ver=1.13.2`

## Ticket source-of-truth confirmation

Patch 10 did not change the full ticket truth path.

- `vms_event_command_center_get_ticket_reporting_truth()` is still intact
- `vms_event_command_center_get_ticket_snapshot()` is still the full Command Center page path
- only the edit-screen module-hub light snapshot changed
- the full detail action now explicitly routes admins to the full Command Center for the prior reporting/source-of-truth behavior

## Remaining dominant query fingerprints after Patch 10

The dominant Event Plan-specific pre-details ticket spike is gone.

The remaining open-edit fingerprints are now smaller and mostly outside the ticket module-hub card:

1. baseline/core/Woo option lookups during `current_screen`
2. smaller pre-details admin/header churn before Event Plan details render
3. non-ticket VMS admin queries such as:
   - staffing rollup reads
   - approval/dispatch notice counts
   - vendor-link lookups

Open-edit `before_details_render` dropped to `52` queries total, with the top fingerprints now headed by small Woo/core option reads rather than heavy ticket-report helpers.

## Recommendation for Patch 11

Patch 11 should not spend more time on the command-center ticket card.

Best next target:

1. production package prep if Patches 1 through 10 are stable enough and the remaining load is mostly baseline/admin noise
2. otherwise, target the next dominant VMS-triggered pre-details family:
   - staffing rollup / admin notice counts
   - smaller admin boot batching
   - command-center financial snapshot if it becomes the next meaningful hub cost
