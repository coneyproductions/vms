# VMS Event Plan Performance Patch 9

Local-only diagnostic + batching pass for Event Plan admin boot on Event Plan `76`.

## Changed files

- `vms/includes/core/event-plan-performance.php`
- `vms/includes/admin/event-command-center.php`
- local `wp-config.php`

## What Patch 9 changed

### Query audit / tracing

- added local-only query fingerprint capture behind `VMS_EP_PERF_TRACE`
- enabled `SAVEQUERIES` for traced local requests and taught the trace reader to honor either `SAVEQUERIES` or `$wpdb->save_queries`
- added admin-boot hook checkpoints for:
  - `current_screen`
  - `load_post`
  - `metabox_registration`
  - `admin_enqueue`
  - `admin_head`
  - `before_details_render`
- added command-center module-hub subphase checkpoints for:
  - `command_center_module_hub_header`
  - `command_center_module_hub_ticket`
  - `command_center_module_hub_financial`
  - `command_center_module_hub_lineup`
  - `command_center_module_hub_staffing`
  - `command_center_module_hub_marketing`
  - `command_center_module_hub_weather`
  - `command_center_module_hub_notes`
  - `command_center_module_hub_alerts`
  - `command_center_module_hub_health`

### Safe batching / memoization

- added request-local cache helper for Event Command Center read-only payloads
- cached:
  - `ticket_reporting_truth`
  - `lineup_snapshot`
  - `module_hub_payload`
- rewrote command-center lineup snapshot loading to:
  - read Event Plan meta once
  - normalize + enrich lineup once
  - reuse one enriched result for entries, summary, and warnings
  - prime vendor post/meta caches before enrichment
  - prime vendor term caches before enrichment
  - batch secondary-vendor title lookup instead of per-vendor `get_the_title()`

## Admin boot hook / subphase breakdown

Patch 9 open-edit request id: `122412f7ea0a`

### Pre-details hook checkpoints

| Phase | Query count | Delta | Elapsed |
| --- | ---: | ---: | ---: |
| `current_screen` | 189 | 189 | 10.46 ms |
| `load_post` | 192 | 192 | 10.684 ms |
| `metabox_registration` | 202 | 10 | 1.189 ms |
| `admin_enqueue` | 231 | 29 | 1.233 ms |
| `admin_head` | 241 | 10 | 0.668 ms |
| `before_details_render` | 546 | 305 | 24.346 ms |

### Dominant metabox path before Event Plan Details

The trace shows the big jump before `event_plan_details_meta_box_render` is the Event Command Center module-hub metabox, not the Event Plan details box itself.

| Module hub phase | Query count | Delta | Elapsed |
| --- | ---: | ---: | ---: |
| `command_center_module_hub_header` | 285 | 2 | 0.065 ms |
| `command_center_module_hub_ticket` | 538 | 253 | 19.856 ms |
| `command_center_module_hub_financial` | 540 | 2 | 0.555 ms |
| `command_center_module_hub_lineup` | 545 | 5 | 0.868 ms |
| `command_center_module_hub_staffing` | 546 | 1 | 0.065 ms |
| `command_center_module_hub_marketing` | 546 | 0 | 0 ms |
| `command_center_module_hub_weather` | 546 | 0 | 0 ms |
| `command_center_module_hub_notes` | 546 | 0 | 0 ms |
| `command_center_module_hub_alerts` | 546 | 0 | 0 ms |
| `command_center_module_hub_health` | 546 | 0 | 0 ms |

## Top query fingerprints

### Pre-details baseline / plugin boot

| Phase | Source | Count | Normalized pattern | Assessment |
| --- | --- | ---: | --- | --- |
| `current_screen` | `core` | 67 | `SELECT option_value FROM wp_options WHERE option_name = '?' LIMIT ?` | baseline/core admin + plugin option boot |
| `current_screen` | `vms` | 23 | `SELECT option_value FROM wp_options WHERE option_name = '?' LIMIT ?` | not Event Plan details; sample caller was `vms-meta-ads` plugin boot |
| `current_screen` | `tec` | 11 | `SELECT option_value FROM wp_options WHERE option_name = '?' LIMIT ?` | TEC baseline boot |
| `current_screen` | `woo` | 10 | `SELECT option_value FROM wp_options WHERE option_name = '?' LIMIT ?` | Woo baseline boot |

### Dominant Event Plan-specific family after batching

These fingerprints now sit almost entirely inside `command_center_module_hub_ticket`.

| Rank | Source | Count | Normalized pattern | Assessment |
| --- | --- | ---: | --- | --- |
| 1 | `vms` | 40 | `SELECT DISTINCT ... FROM wp_terms ... wp_term_relationships ...` | VMS-triggered taxonomy scans inside command-center ticket reporting path |
| 2 | `vms` | 24 | `SELECT * FROM wp_posts WHERE ID = ? LIMIT ?` | repeated VMS object hydration inside the same ticket path |
| 3 | `vms` | 23 | `SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE post_id IN (?) ORDER BY meta_id ASC` | repeated VMS meta bundle loads inside the same ticket path |
| 4 | `vms` | 20 | `SELECT DISTINCT ... LEFT JOIN wp_termmeta ...` | repeated VMS taxonomy + termmeta qualification / mapping reads |
| 5 | `vms` | 16 | `SELECT wp_posts.ID FROM wp_posts INNER JOIN wp_postmeta ... _tribe_wooticket_for_event ...` | VMS-triggered ticket lookup query |
| 6 | `vms` | 16 | `SELECT ... FROM wp_woocommerce_order_itemmeta WHERE order_item_id = ?` | VMS-triggered Woo order-item hydration |
| 7 | `vms` | 16 | `SELECT ... FROM wp_woocommerce_order_itemmeta WHERE order_item_id IN (?)` | VMS-triggered Woo order-item meta batch reads |
| 8 | `vms` | 12 | `SELECT ... FROM wp_woocommerce_order_items WHERE order_id = ?` | VMS-triggered Woo order-item reads |

## VMS-triggered vs baseline assessment

- The early `current_screen` / `load_post` option churn is mostly baseline WordPress + Woo + TEC + another VMS plugin (`vms-meta-ads`), not the Event Plan editor itself.
- The Event Plan-specific spike before details render is VMS-triggered.
- After Patch 9 batching, that Event Plan-specific spike is no longer lineup/vendor hydration.
- It is now concentrated in the Event Command Center module-hub ticket summary path, specifically `vms_event_command_center_get_ticket_snapshot_light()` -> `vms_event_command_center_get_ticket_reporting_truth()`.

## Open-edit comparison

Patch 8 baseline from the prior local report:

- queries: `600`
- peak memory: `218 MB`
- admin boot: `236 ms`
- details render: `35 ms`
- time-lineup render: `3 ms`

Patch 9 warmed open-edit:

- queries: `600`
- peak memory: `218 MB`
- admin boot: `260 ms`
- details render: `38 ms`
- time-lineup render: `3 ms`

Result:

- no meaningful top-line open-edit win
- however, the trace now shows exactly where the pre-details load comes from
- lineup/vendor repeated work inside the command-center hub dropped to a small subphase:
  - `command_center_module_hub_lineup` now cost only `5 queries / 0.868 ms`
- the remaining dominant family is the ticket/reporting truth path inside the same metabox:
  - `command_center_module_hub_ticket` cost `253 queries / 19.856 ms`

## No-change Update comparison

Patch 8 baseline:

- queries: `316`
- `save_post`: `1`
- internal `wp_update_post`: `0`
- auto-title: no-op
- secondary-vendor rebuild: skipped
- vendor calendar maintenance: skipped
- staffing: skipped
- no Woo sync
- no Action Scheduler jobs

Patch 9:

- queries: `316`
- peak memory: `205 MB`
- `save_post`: `1`
- internal `wp_update_post`: `0`
- auto-title: `skip / no_op`
- secondary-vendor rebuild: `skip / no_vendor_change`
- vendor calendar maintenance: `skip / no_vendor_change`
- staffing: `skip / no_staffing_change`
- no Woo sync
- no Action Scheduler jobs

Result:

- Patch 9 did not regress the clean no-change Update path

## Other save / publish scenarios

Patch 8 -> Patch 9:

| Scenario | Patch 8 queries | Patch 9 queries | Notes |
| --- | ---: | ---: | --- |
| `basic_field_update` | 316 | 316 | unchanged |
| `featured_image_change` | 328 | 328 | unchanged |
| `staffing_only_change` | 427 | 427 | unchanged; staffing still runs only when dirty |
| `vendor_only_change` | 361 | 357 | slight drop; vendor rebuild still runs as expected |
| `vendor_staffing_change` | 447 | 446 | essentially flat |
| `publish_republish` | 356 | 356 | unchanged; publish jobs preserved |

## Manual UI / smoke results

Browser smoke plus real admin-form scenario suite:

- Ticketing summary-first still renders:
  - `Configured tickets`
  - `Load full ticketing editor`
- current primary vendor still renders in the primary vendor selector:
  - current value observed: `83`
- supporting vendor controls still work:
  - `Collapse All` left `0` supporting cards open
  - `Expand All` reopened `10` supporting cards
  - first supporting vendor select hydrated to `77` options
- Secondary Vendors still lazy-load:
  - initial `data-vms-lazy-loaded="0"`
  - after expand: `data-vms-lazy-loaded="1"`
  - legend visible
  - `3` secondary vendor rows rendered
- Staff still lazy-loads:
  - initial `data-vms-lazy-loaded="0"`
  - after expand: `data-vms-lazy-loaded="1"`
- Readiness details lazy payload still works through the authenticated admin AJAX endpoint:
  - HTTP `200`
  - `success=true`
  - returned HTML length `1305`
  - returned HTML still contains `Configured tickets`
- real save/persistence validation remained on the existing local runner rather than the browser loop, because the headless browser save/reload flow is still noisy on WordPress redirects
- no PHP warning/notice text on the page
- no new page JS exceptions
- one browser console resource error is still present, matching the previously known unrelated `404` issue

## Remaining dominant fingerprints after Patch 9

1. `command_center_module_hub_ticket`
   - the clear dominant VMS-specific family now
   - responsible for roughly `253` queries inside the module-hub metabox before Event Plan Details render begins
2. baseline option-value lookups during `current_screen`
   - mostly core / Woo / TEC / `vms-meta-ads`
   - not the main Event Plan-specific optimization target for the next patch

## Recommendation for Patch 10

Patch 10 should target the command-center module-hub ticket summary path.

Safest likely directions, in order:

1. Keep the module-hub ticket card summary-first on Event Plan edit and stop running full `ticket_reporting_truth` on initial editor open when cached stats + integrity summary are enough.
2. If full truth is still required somewhere, defer it to:
   - the full Command Center page
   - the Ticketing editor
   - or an explicit lazy detail load
3. If behavior must stay identical, batch / cache the underlying ticket-reporting helpers so one Event Plan edit request does not repeat the Woo / TEC / taxonomy scans for the same plan.

Based on Patch 9, the next patch should not spend more time on lineup/vendor edit-screen work. That family is no longer the bottleneck.
