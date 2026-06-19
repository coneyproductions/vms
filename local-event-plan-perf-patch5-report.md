# VMS Event Plan Performance Patch 5 Report

Local only. No staging or production deploy.

Test target: Event Plan `76`

## Changed files

- `vms/includes/core/event-plan-performance.php`
- `vms/includes/cpt/event-plans.php`
- `vms/includes/cpt/event-plans/partials/advanced-controls.php`
- `vms/includes/cpt/event-plans/partials/ticketing-v2.php`

## Patch summary

Patch 5 reduced admin boot load by changing the Ticketing metabox to summary-first rendering on initial open and by adding request-local admin-boot memoization/tracing.

Initial Event Plan open now:

- renders a lightweight Ticketing summary on first load
- skips the full managed ticketing editor until explicitly requested
- keeps Advanced Controls focused on calendar/link troubleshooting
- records boot-phase query deltas, memory deltas, and cache hits
- caches request-local admin boot summaries for:
  - Event Plan meta bundle
  - linked TEC summary
  - ticket summary
  - add-on summary

The full ticketing editor still renders on explicit request via the local edit screen and preserves the existing Ticketing Save → Preview → Commit flow.

## Admin boot phases traced

Added or expanded trace coverage for:

- `event_plan_admin_boot_meta_bundle`
- `event_plan_admin_boot_linked_tec`
- `event_plan_admin_boot_ticket_summary`
- `event_plan_admin_boot_add_on_summary`
- `event_plan_admin_boot_vendor_summary`
- `event_plan_admin_boot_secondary_vendor_state`
- `event_plan_admin_boot_staffing_summary`
- `event_plan_admin_boot_readiness`
- `event_plan_admin_boot_integrity_flags`
- `event_plan_admin_boot_availability_conflict`
- `event_plan_admin_boot_woo_lookup`

Initial open now shows:

- Ticketing summary path: `event_plan_ticketing_v2_lookup phase=summary_only skip_reason=deferred_initial_load`
- Woo-heavy summary lookup deferred: `event_plan_admin_boot_woo_lookup phase=deferred reason=summary_only_initial_load`
- Request-local cache hits on repeated meta/linked TEC/ticket summary calls

## Memoized, batched, and deferred lookups

Memoized / batched:

- batched Event Plan meta preload through one request-local meta bundle
- request-local linked TEC summary reuse
- request-local ticket summary reuse
- request-local add-on summary reuse
- request-local trace ticket snapshot caching, to keep trace overhead down

Deferred:

- full managed Ticketing editor render
- managed ticket config/template/admin-editor hydration
- managed ticket cache reconcile done only when the full Ticketing editor is explicitly loaded

## Open edit screen comparison

Patch 4 baseline:

- `388` queries
- `216 MB` peak
- `216 ms` admin boot
- `46 ms` details meta box render
- `8 ms` time-lineup partial

Patch 5:

- `374` queries
- `216 MB` peak
- `173 ms` admin boot
- `25 ms` details meta box render
- `4 ms` time-lineup partial

Delta:

- queries: `388 -> 374`
- admin boot: `216 ms -> 173 ms`
- details meta box: `46 ms -> 25 ms`
- time-lineup partial: `8 ms -> 4 ms`

Slowest remaining open-edit phases:

1. `event_plan_admin_screen_boot` `173 ms`, `q+183`
2. `event_plan_details_meta_box_render` `25 ms`, `q+16`
3. `event_plan_vendor_availability_checks` `8 ms`, `q+2`
4. `event_plan_admin_boot_availability_conflict` `8 ms`, `q+2`
5. `event_plan_partial_render_secondary-vendors` `6 ms`, `q+6`

## Save and publish comparisons

| Scenario | Patch 4 queries | Patch 5 queries | Patch 4 peak | Patch 5 peak | Notes |
| --- | ---: | ---: | ---: | ---: | --- |
| No-change Update | 375 | 322 | 207 MB | 207 MB | save pass `1`, internal `wp_update_post` `0`, auto-title no-op, vendor/staffing skips preserved |
| Basic field Update | 373 | 320 | 215 MB | 215 MB | vendor/staffing skips preserved |
| Featured image change | 387 | 332 | 215 MB | 217 MB | featured image sync still runs, vendor/staffing skips preserved |
| Vendor + staffing change | 465 | 447 | 205 MB | 213 MB | vendor rebuild and staffing work still run |
| Publish / republish | 415 | 348 | 207 MB | 213 MB | publish-specific jobs preserved |
| Staffing-only change | 452 | 423 | 211 MB | 201 MB | staffing work preserved, vendor rebuild still skips |
| Vendor-only change | 414 | 352 | 213 MB | 211 MB | vendor rebuild still runs, staffing still skips |

## No-change Update confirmation

Patch 5 preserved all Patch 1-4 save protections:

- `save_post` pass count: `1`
- internal `wp_update_post()`: `0`
- `event_plan_auto_title_sync`: `phase=skip`, `skip_reason=no_op`
- `event_plan_secondary_vendor_rebuild`: `phase=skip`, `skip_reason=no_vendor_change`
- `event_plan_calendar_vendor_maintenance`: `phase=skip`, `skip_reason=no_vendor_change`
- `event_plan_staffing_save`: `phase=skip`, `skip_reason=no_staffing_change`
- staffing seed still skips
- no Woo ticket/product sync observed
- no Action Scheduler jobs observed
- no title/status change

## Manual UI test results

Browser test target: Event Plan `76` in wp-admin

Confirmed:

- Ticketing metabox opens in summary-first mode on initial load
- Ticketing summary shows:
  - `Configured tickets`
  - `Configured add-ons`
  - `Linked TEC status`
- Advanced Controls shows the new note pointing operators to the Ticketing metabox for the full editor
- Workflow/status section still renders
- linked TEC status still renders inside Advanced Controls
- Secondary Vendors section still renders
- supporting-vendor `Expand All` / `Collapse All` still work
  - open details count reached `12`
  - collapse left `1` detail open
- Staff section still lazy-loads
- lazy Staff save persisted after Update
- refreshed lazy Staff content still returned the saved value
- no PHP warnings/notices were rendered

Headless note:

- the initial Staff lazy-load was exercised through the real browser page
- after save/refresh, persisted Staff values were verified through the same authenticated admin AJAX endpoint the UI uses for lazy loading
- baseline was restored after the test; `vms_staff_role_headcount[4]` is back to `1`

Browser errors:

- no new page-level JS or PHP failures were introduced
- the pre-existing unrelated `404` remains:
  - `/wp-includes/css/jquery-ui.min.css?ver=1.13.2`

## Remaining dominant phases

Patch 5 removed the full managed ticketing editor from the initial boot path, so the biggest remaining initial-open costs are now:

- `event_plan_admin_screen_boot`
- `event_plan_details_meta_box_render`
- vendor availability/conflict checks
- secondary-vendor section render

The Ticketing summary path is now effectively free on initial open:

- `event_plan_partial_render_ticketing-v2` `0 ms`, `q+0`
- `event_plan_ticketing_v2_lookup phase=summary_only`

## Recommendation for Patch 6

Patch 6 should target readiness/status and vendor-summary boot query reduction.

Reason:

- Ticketing is no longer a dominant initial-open offender
- Staff is already deferred
- remaining boot cost is concentrated in admin boot orchestration, vendor availability/conflict checks, and secondary-vendor/admin summary work

Safest next direction:

1. batch or cache readiness/status warning inputs inside admin boot
2. reduce repeated vendor availability/conflict summary work
3. if needed after that, move Secondary Vendors toward summary-first rendering on initial open
