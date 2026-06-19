# VMS Event Plan Performance Patch 3 Report

Date: 2026-06-02

Scope:
- Local only
- Event Plan `76`
- Patch 3 only: staffing dirty-check cleanup

Changed files:
- `vms/includes/core/staffing.php`
- `vms/includes/cpt/event-plans.php`

Trace artifacts:
- Raw trace: `wp-content/vms-event-plan-perf-trace.log`
- Patch 2 report: `vms/local-event-plan-perf-patch2-report.md`

## Guard Summary

Patch 3 adds a request-scoped staffing state assessment during the main Event Plan save and reuses it in the later staffing `save_post` hooks.

Included staffing-related fields in the dirty check:
- Staff role headcount: `vms_staff_role_headcount[role_id]`
- Assigned staff IDs: `vms_staff_assignments[role_id][]`
- Shift timing mode: `vms_staff_role_time_mode[role_id]`
- Absolute shift times: `vms_staff_role_shift_start[role_id]`, `vms_staff_role_shift_end[role_id]`
- Relative timing anchors and offsets: `vms_staff_role_start_anchor[role_id]`, `vms_staff_role_start_offset[role_id]`, `vms_staff_role_end_anchor[role_id]`, `vms_staff_role_end_offset[role_id]`
- Shift duration: `vms_staff_role_duration_minutes[role_id]`
- Activation thresholds: `vms_staff_role_activation_thresholds[role_id]`
- Template apply request: `vms_staffing_template_apply`, `vms_staffing_template_id`, `vms_staffing_template_mode`

Context keys still allowed to trigger staffing seed work:
- `_vms_event_date`
- `_vms_start_time`
- `_vms_end_time`
- `_vms_venue_id`
- `_vms_event_type`

Dirty categories emitted by the guard:
- `staff_assignment_changed`
- `staff_headcount_changed`
- `staff_times_changed`
- `staff_activation_threshold_changed`
- `staffing_template_apply_requested`

Not included because this Event Plan staffing save path does not currently persist them here:
- Separate per-staff notes/instructions inputs
- Separate staffing readiness/status fields
- Standalone availability/conflict inputs

Trace behavior added by Patch 3:
- `event_plan_staffing_save phase=skip skip_reason=no_staffing_change`
- `event_plan_staffing_availability_conflict phase=skip skip_reason=no_staffing_change`
- `event_plan_staffing_seed phase=skip skip_reason=no_staffing_change`
- `event_plan_staffing_queue_meta phase=skip skip_reason=no_staffing_change`
- `event_plan_staffing_save phase=run dirty_reason=staff_headcount_changed`
- `event_plan_staffing_seed phase=run dirty_reason=staff_headcount_changed`

## Acceptance Result

Plain no-change Update target was met.

Patch 2 baseline:
- Queries: `396`
- Peak memory: `205 MB`
- `save_post` passes: `1`
- Internal `wp_update_post()`: `0`
- Secondary-vendor rebuild: skipped
- Vendor calendar maintenance: skipped
- Staffing hook: entered, but only generic hook traces existed

Patch 3 result:
- Queries: `392`
- Peak memory: `201 MB`
- `save_post` passes: `1`
- Internal `wp_update_post()`: `0`
- Secondary-vendor rebuild: skipped
- Vendor calendar maintenance: skipped
- Staffing save path: explicit `skip/no_staffing_change`
- Staffing availability/conflict maintenance: explicit `skip/no_staffing_change`
- Staffing seed hook: explicit `skip/no_staffing_change`
- Staffing queue meta: explicit `skip/no_staffing_change`
- Woo ticket/product sync: none observed
- Action Scheduler jobs: none observed
- Title/status: unchanged

## No-Change Update Comparison

| Metric | Patch 2 | Patch 3 | Result |
| --- | ---: | ---: | --- |
| Queries | 396 | 392 | down 4 |
| Peak memory | 205 MB | 201 MB | down 4 MB |
| `save_post` passes | 1 | 1 | unchanged |
| Internal `wp_update_post()` | 0 | 0 | unchanged |
| Secondary-vendor rebuild | skipped | skipped | unchanged |
| Vendor calendar maintenance | skipped | skipped | unchanged |
| Staffing save path | generic hook skip | explicit `skip/no_staffing_change` | target met |
| Staffing availability/conflict maintenance | implicit skip | explicit `skip/no_staffing_change` | target met |
| Staffing seed queue | none | none | target met |
| Staffing queue meta writes | none | none | target met |
| Woo ticket/product sync | none | none | unchanged |
| Action Scheduler jobs | none | none | unchanged |

Key Patch 3 no-change trace lines:
- `event_plan_staffing_save phase=skip skip_reason=no_staffing_change`
- `event_plan_staffing_availability_conflict phase=skip skip_reason=no_staffing_change`
- `event_plan_staffing_seed phase=skip skip_reason=no_staffing_change`
- `event_plan_staffing_queue_meta phase=skip skip_reason=no_staffing_change`

## Staffing-Only Change Comparison

This extra validation changed only `vms_staff_role_headcount[4]`.

| Metric | Patch 2 | Patch 3 | Result |
| --- | ---: | ---: | --- |
| Queries | 448 | 458 | up 10 |
| Peak memory | 199 MB | 217 MB | up 18 MB |
| `save_post` passes | 1 | 1 | unchanged |
| Internal `wp_update_post()` | 0 | 0 | unchanged |
| Secondary-vendor rebuild | skipped | skipped | preserved |
| Vendor calendar maintenance | skipped | skipped | preserved |
| Staffing dirty reason | n/a | `staff_headcount_changed` | confirmed |
| Staffing availability/conflict maintenance | ran | ran | preserved |
| Staffing seed queue | scheduled | scheduled | preserved |
| Staffing queue meta writes | written | written | preserved |

Key Patch 3 staffing-only trace lines:
- `event_plan_staffing_save phase=run dirty_reason=staff_headcount_changed`
- `event_plan_staffing_availability_conflict phase=run dirty_reason=staff_headcount_changed`
- `event_plan_staffing_seed phase=run dirty_reason=staff_headcount_changed`
- `event_plan_staffing_queue_meta phase=run reason=event_plan_save`

## Vendor-Only Change Comparison

This extra validation changed only the secondary-vendor assignment array.

| Metric | Patch 2 | Patch 3 | Result |
| --- | ---: | ---: | --- |
| Queries | 438 | 431 | down 7 |
| Peak memory | 197 MB | 215 MB | up 18 MB |
| `save_post` passes | 1 | 1 | unchanged |
| Internal `wp_update_post()` | 0 | 0 | unchanged |
| Secondary-vendor rebuild | ran | ran | preserved |
| Vendor calendar maintenance | queued | queued | preserved |
| Staffing save path | generic hook skip | explicit `skip/no_staffing_change` | target met |
| Staffing seed queue | none | none | preserved |
| Staffing queue meta writes | none | none | preserved |

Key Patch 3 vendor-only trace lines:
- `event_plan_secondary_vendor_rebuild ... dirty_fields=["secondary_vendor_ids"]`
- `event_plan_calendar_vendor_maintenance phase=run dirty_reason=secondary_vendor_ids`
- `event_plan_staffing_save phase=skip skip_reason=no_staffing_change`
- `event_plan_staffing_seed phase=skip skip_reason=no_staffing_change`
- `event_plan_staffing_queue_meta phase=skip skip_reason=no_staffing_change`

## Publish / Republish Confirmation

Publish/republish behavior stayed intact where it matters:
- Queries: `434`
- Peak memory: `217 MB`
- `save_post` pass count stayed `1`
- Internal `wp_update_post()` stayed `0`
- Secondary-vendor rebuild stayed `skip/no_vendor_change`
- Staffing save path stayed `skip/no_staffing_change`
- Publish-specific jobs still queued:
  - `vms_event_plan_deferred_calendar_publish`
  - `vms_ticket_integrity_spot_scan`
- Queue meta still written for publish jobs:
  - `_vms_calendar_publish_queue_state`
  - `_vms_calendar_publish_queued_at`
  - `_vms_calendar_publish_queue_reason`
  - `_vms_ticket_integrity_last_plan_save_queue_at`

## Other Scenario Results

Open edit screen only:
- Queries: `430`
- Peak memory: `216 MB`
- Slowest sections:
  - `event_plan_admin_screen_boot`: `273 ms`
  - `event_plan_details_meta_box_render`: `114 ms`
  - `event_plan_partial_render_time-lineup`: `77 ms`

Basic field-only Update:
- Queries: `390`
- Peak memory: `213 MB`
- Secondary-vendor rebuild: skipped
- Staffing save path: skipped

Featured image Update:
- Queries: `404`
- Peak memory: `215 MB`
- Secondary-vendor rebuild: skipped
- Staffing save path: skipped

Combined vendor/staffing change:
- Queries: `465`
- Peak memory: `217 MB`
- Secondary-vendor rebuild: ran
- Staffing save path: ran with `staff_headcount_changed`
- Staffing queue helper hit the existing queue/lock guard: `event_plan_staffing_queue_meta phase=skip skip_reason=already_queued_or_locked`

## Recommended Patch 4

Patch 4 should move to edit-screen open/load reduction.

Why next:
- The no-change save path now has the intended no-op guards for auto-title, secondary vendors, and staffing follow-up hooks
- The remaining no-change staffing work is the normalized state read needed to prove the skip, not downstream queue churn
- The edit screen is still expensive on every open: `430` queries, `216 MB` peak, and the top timings are still concentrated in admin boot plus meta box/time-lineup rendering

Recommended next order:
1. Edit-screen open/load reduction
2. Admin hub/lazy-loading groundwork for lineup/staffing/vendor-heavy sections
3. Calendar/status/readiness query reduction
4. Additional queue/meta no-op guards only if a later trace shows new save-time churn
