# VMS Event Plan Performance Patch 1 Report

Date: 2026-06-02

Scope:
- Local only
- Event Plan `76`
- Patch 1 only: auto-title internal `wp_update_post()` no-op guard

Changed files:
- `vms/includes/core/event-plan-performance.php`
- `vms/includes/cpt/event-plans.php`

Trace artifacts:
- Raw trace: `wp-content/vms-event-plan-perf-trace.log`
- Baseline report: `vms/local-event-plan-perf-report.md`

## Guard Summary

Patch 1 adds a field-level no-op check before the Event Plan title sync calls `wp_update_post()`.

Behavior:
- Build the same post array VMS was already about to submit internally.
- Compare each proposed post field against the current raw post value.
- If no post fields would change, skip `wp_update_post()`.
- When `VMS_EP_PERF_TRACE` is enabled, log the skip with `phase=skip` and `skip_reason=no_op`.

This guard now covers:
- Main Event Plan auto-title sync path
- Empty-title autoset helper path

There was no behavior change to ticketing, TEC sync, staffing, vendor, or publish logic.

## Acceptance Result

Target plain no-change Update result was met.

Before:
- `save_post` pass count: `2`
- internal `wp_update_post()` count: `1`
- `event_plan_auto_title_sync` performed an internal post update
- staffing seed queue work was triggered on the second pass

After:
- `save_post` pass count: `1`
- internal `wp_update_post()` count: `0`
- `event_plan_auto_title_sync` logged `skip/no_op`
- Event Plan title and status stayed unchanged
- No Woo ticket/product sync observed
- No Action Scheduler jobs observed

## No-Change Update Comparison

| Metric | Before | After | Result |
| --- | ---: | ---: | --- |
| Queries | 602 | 430 | down 172 |
| Peak memory | 203 MB | 203 MB | flat |
| `save_post` passes | 2 | 1 | target met |
| Internal `wp_update_post()` | 1 | 0 | target met |
| Auto-title sync | internal update | `skip/no_op` | target met |
| Secondary-vendor rebuild | yes | yes | still runs |
| Calendar maintenance queued | yes | yes | still runs |
| Staffing seed hook | yes | yes, but skipped | improved |
| Staffing queue job/meta writes | yes | no | improved |
| Woo ticket/product sync | no | no | unchanged |
| Action Scheduler jobs | none | none | unchanged |

Key no-change trace observations after Patch 1:
- `event_plan_auto_title_sync` logged `skip_reason=no_op`
- `event_plan_secondary_vendor_rebuild` still ran and still queued calendar maintenance
- `vms_staffing_seed_template_on_save` still ran, but it exited as `skipped=1`
- No `vms_staffing_queue_seed_event_slots` run occurred on the plain no-change Update
- Staffing queue-state meta was no longer written on the plain no-change Update

## Other Scenario Comparison

| Scenario | Queries Before | Queries After | Peak MB Before | Peak MB After | Save Passes Before | Save Passes After | Internal Updates Before | Internal Updates After | Notes |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Open edit screen only | 653 | 447 | 216 | 217 | 0 | 0 | 0 | 0 | Load path not changed by Patch 1; lower query count is run-to-run noise, not attributable to this patch |
| Small content change Update | 488 | 419 | 205 | 199 | 2 | 1 | 1 | 0 | auto-title now skips; secondary-vendor and staffing hooks still run |
| Featured image change Update | 502 | 433 | 199 | 197 | 2 | 1 | 1 | 0 | auto-title now skips; featured-image sync still behaves normally |
| Vendor/staffing-related Update | 500 | 484 | 197 | 195 | 2 | 1 | 1 | 0 | actual staffing queue now schedules here instead of being pre-triggered by no-change Update |
| Publish / republish | 555 | 466 | 195 | 195 | 2 | 1 | 1 | 0 | publish side effects still queue as expected; auto-title no longer adds a second pass |

Scenario notes after Patch 1:
- All save scenarios dropped from `2` save passes to `1`.
- All save scenarios dropped from `1` internal `wp_update_post()` to `0`.
- All save scenarios logged auto-title skip/no-op instead of doing an internal post update.
- No new Woo ticket/product sync appeared.
- No Action Scheduler jobs appeared.

## Remaining Heavy Work After Patch 1

Plain no-change Update still does unnecessary work:
- `event_plan_secondary_vendor_rebuild` still runs
- calendar maintenance still queues and writes queue meta on the no-change request
- staffing save hook still executes, although it now skips before queueing

This means Patch 1 removed the confirmed recursive save churn, but the no-change Update still pays for unchanged vendor-derived work.

## Recommended Next Patch

Next patch: secondary-vendor dirty check.

Why this next:
- It is now the clearest remaining no-change offender.
- On a plain no-change Update it still rebuilds derived vendor data and still queues calendar maintenance.
- A dirty check there should remove both the rebuild cost and the redundant calendar queue/meta writes.

Recommended order after Patch 1:
1. Secondary-vendor dirty check
2. Staffing seed/maintenance dirty check
3. Calendar maintenance queue no-op guard
4. Edit-screen load reduction
