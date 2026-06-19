# VMS 0.2.24.661 — Event Plan Save Profiler + Module Dirty Map

## Purpose

This build continues the Event Plan command-center/modularization direction by making Event Plan saves more transparent before deeper save-path teardown.

The goal is to answer this question directly in wp-admin:

> When I clicked normal WordPress Update or a module save changed Event Plan data, what did VMS think changed, what heavy work was skipped or triggered, and how long did it take?

## What changed

### Save profiler now records every Event Plan save by default

The existing `_vms_last_save_profile` profiler was previously focused on slow saves. In this build it records every Event Plan save by default so testers can validate lightweight saves even when they are fast.

Sites can still raise the threshold with the existing `vms_event_plan_save_profiler_threshold_seconds` filter or `VMS_EVENT_PLAN_SAVE_PROFILER_THRESHOLD` constant.

### Module dirty map

Each profile now includes module-level classification:

- Core Event Details
- Tickets & Add-ons
- Lineup & Vendors
- Staffing
- Compensation / Finance
- Marketing / Promo
- Agreements
- Ops / Guest List

The profile stores:

- `save_type`
- `changed_modules`
- `module_dirty_map`
- `dirty_reasons`
- `module_meta_writes`
- existing meta-write/timing fields

This gives us a bridge from the old giant Event Plan form toward module-aware saves.

### Heavy-work skipped/triggered visibility

The profiler now exposes a structured `heavy_actions` map. Ticket Integrity and staffing save hooks can record whether work was skipped or triggered, with a short reason.

Current tracked examples:

- `ticket_integrity_plan_save` skipped for normal editor saves
- `ticket_integrity_spot_scan` scheduled/skipped when a spot scan is queued or already scheduled
- `staffing_rollup_dirty` skipped/triggered based on staffing dirty detection
- `staffing_seed_template` skipped/triggered based on staffing/context dirty detection

### Event Module Hub summary

The Event Plan Module Hub now includes a **Last Event Plan Save** diagnostic panel above the module cards.

It shows:

- save type
- changed modules
- duration
- recorded GMT timestamp
- heavy work triggered
- heavy work skipped

The existing side metabox **VMS Save Profile** was also updated to show the expanded module-aware data.

### Module meta update profile

Some module actions, especially AJAX/module saves, update Event Plan meta without passing through `save_post_vms_event_plan`. This build records a tiny `module_meta_update` profile when module-owned meta changes outside a normal Event Plan save, so the hub does not keep showing a stale “Core only” save after a ticket/staffing/vendor module save.

## Files changed

- `includes/core/event-plan-save-profiler.php`
- `includes/ticketing/ticket-integrity-cron.php`
- `includes/admin/event-command-center.php`
- `includes/core/registry/constants.php`
- `assets/css/vms-event-command-center.css`
- `vendor-management-system.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.661.md`
- `vms-test-plan-0.2.24.661.md`

## Build discipline

Version bumped consistently to `0.2.24.661` in:

- plugin header
- `VMS_VERSION`
- `vms-build.txt`

## Risk level

Medium-low.

This build is mostly diagnostic and does not intentionally change public ticket/cart/checkout behavior. It does, however, touch Event Plan save profiling and admin rendering, so it should receive local-first Codex testing plus reduced staging smoke before production.
