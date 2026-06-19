# VMS Event Plan Performance Patch 2 Report

Date: 2026-06-02

Scope:
- Local only
- Event Plan `76`
- Patch 2 only: secondary-vendor dirty check

Changed files:
- `vms/includes/cpt/event-plans.php`

Trace artifacts:
- Raw trace: `wp-content/vms-event-plan-perf-trace.log`
- Patch 1 report: `vms/local-event-plan-perf-patch1-report.md`

## Guard Summary

Patch 2 adds a vendor-state dirty check before the `event_plan_secondary_vendor_rebuild` block.

Included vendor-related fields:
- Primary vendor assignment: `_vms_band_vendor_id` / `vms_band_vendor_id`
- Secondary vendor type: `_vms_secondary_vendor_type` / `vms_secondary_vendor_type`
- Secondary vendor assignments: `_vms_secondary_vendor_ids` / `vms_secondary_vendor_ids[]`
- Linked TEC event: `_vms_tec_event_id` only as the calendar-maintenance queue target

Not included because this rebuild path does not use them:
- Vendor schedule/time fields
- Vendor visibility/status flags
- Staffing fields
- Ticketing fields
- Supporting-act / lineup rows beyond the primary vendor ID

Repair triggers that still force a rebuild even without vendor input changes:
- Canonical secondary vendor meta mismatch
- Secondary vendor index meta mismatch
- Missing vendor category snapshot meta
- `missing_secondary_vendor` integrity issue still present

Trace behavior:
- Skip branch logs `event_plan_secondary_vendor_rebuild phase=skip skip_reason=no_vendor_change`
- Calendar skip branch logs `event_plan_calendar_vendor_maintenance phase=skip skip_reason=no_vendor_change`
- Run branch logs `dirty_branch=run` with dirty fields, and maintenance logs `phase=run` with the dirty reason

## Acceptance Result

Plain no-change Update target was met.

Patch 1 baseline:
- Queries: `430`
- Peak memory: `203 MB`
- `save_post` passes: `1`
- Internal `wp_update_post()`: `0`
- Secondary-vendor rebuild: ran
- Calendar maintenance: queued

Patch 2 result:
- Queries: `396`
- Peak memory: `205 MB`
- `save_post` passes: `1`
- Internal `wp_update_post()`: `0`
- Secondary-vendor rebuild: skipped
- Calendar maintenance: skipped
- Staffing seed hook: still runs, still skips
- Woo ticket/product sync: none observed
- Action Scheduler jobs: none observed
- Title/status: unchanged

## No-Change Update Comparison

| Metric | Patch 1 | Patch 2 | Result |
| --- | ---: | ---: | --- |
| Queries | 430 | 396 | down 34 |
| Peak memory | 203 MB | 205 MB | flat/noise |
| `save_post` passes | 1 | 1 | unchanged |
| Internal `wp_update_post()` | 0 | 0 | unchanged |
| Auto-title sync | `skip/no_op` | `skip/no_op` | unchanged |
| Secondary-vendor rebuild | ran | skipped | target met |
| Vendor calendar maintenance | queued | skipped | target met |
| Calendar queue meta writes | `_vms_calendar_maintenance_*` written | none | target met |
| Staffing seed hook | skipped | skipped | unchanged |
| Woo ticket/product sync | none | none | unchanged |
| Action Scheduler jobs | none | none | unchanged |

Key Patch 2 no-change trace lines:
- `event_plan_secondary_vendor_rebuild phase=skip skip_reason=no_vendor_change`
- `event_plan_calendar_vendor_maintenance phase=skip skip_reason=no_vendor_change`
- `event_plan_auto_title_sync phase=skip skip_reason=no_op`
- `vms_staffing_seed_template_on_save ... skipped=1`

## Vendor/Staffing Change Comparison

This scenario changed both a secondary vendor row and a staffing field.

| Metric | Patch 1 | Patch 2 | Result |
| --- | ---: | ---: | --- |
| Queries | 484 | 489 | flat/noise |
| Peak memory | 195 MB | 197 MB | flat/noise |
| `save_post` passes | 1 | 1 | unchanged |
| Internal `wp_update_post()` | 0 | 0 | unchanged |
| Secondary-vendor rebuild | ran | ran | preserved |
| Vendor dirty reason | n/a | `secondary_vendor_ids` | confirmed |
| Vendor calendar maintenance | queued | queued | preserved |
| Staffing queue | scheduled | scheduled | preserved |

Key Patch 2 vendor/staffing trace lines:
- `event_plan_secondary_vendor_rebuild ... dirty_branch=run dirty_fields=["secondary_vendor_ids"]`
- `event_plan_calendar_vendor_maintenance phase=run dirty_reason=secondary_vendor_ids`
- `vms_event_plan_schedule_calendar_maintenance ... reason=vendor_category_sync`

## Publish/Republish Confirmation

Publish/republish behavior stayed intact where it matters:
- `save_post` pass count stayed `1`
- Internal `wp_update_post()` stayed `0`
- Auto-title stayed `skip/no_op`
- Publish-specific follow-up still queued:
  - `vms_event_plan_deferred_calendar_publish`
  - `vms_ticket_integrity_spot_scan`
- Post title remained `QA Band Vendor 1775435985240`
- Post status remained `publish`

The only removed work on publish/republish was the unchanged vendor rebuild branch:
- `event_plan_secondary_vendor_rebuild phase=skip skip_reason=no_vendor_change`
- `event_plan_calendar_vendor_maintenance phase=skip skip_reason=no_vendor_change`

## Other Scenario Notes

Patch 2 also skipped secondary-vendor rebuild on:
- Basic field-only Update
- Featured image Update

Extra validation beyond the requested six scenarios:
- A staffing-only save was run locally
- Result: secondary-vendor rebuild skipped, vendor calendar maintenance skipped, staffing queue behavior remained separate

## Recommended Patch 3

Patch 3 should be staffing dirty-check cleanup.

Why next:
- After Patch 2, the no-change Update still enters the staffing save hook even though it skips quickly
- A staffing-only save still reaches the staffing queue helper path, even if it no-ops because a queue/lock already exists
- Edit-screen load is still the heaviest request, but the next safest no-behavior-change win is to tighten staffing dirty detection before shifting to UI/load work

Recommended order after Patch 2:
1. Staffing dirty-check cleanup
2. Calendar/vendor maintenance queue no-op guard
3. Edit-screen open/load reduction
