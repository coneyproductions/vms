# VMS 0.2.24.662 — Save Profiler Active-State Repair

## Purpose

This build repairs a local Codex finding from 0.2.24.661. The new Event Plan save profiler and module dirty map correctly prevented Ticket Integrity work during a content-only WordPress Update, but the staffing save guards could not detect that the profiler was active because the helper `vms_event_plan_save_profiler_active()` was missing.

That caused the visible save profile to incorrectly report staffing heavy work as triggered on a pure core/content save, even though Ticket Integrity and ticket data remained untouched.

## Changes

- Adds `vms_event_plan_save_profiler_active()` in `includes/core/event-plan-save-profiler.php`.
- Allows staffing guards to correctly skip and report:
  - `staffing_rollup_dirty` as skipped for non-staffing saves.
  - `staffing_seed_template` as skipped when no staffing/date/time/context keys changed.
- Keeps the 0.2.24.661 Event Module Hub save-profile visibility and module dirty map intact.
- Bumps official version markers to `0.2.24.662`.

## What this does not change

- Does not change public ticket pricing.
- Does not change public ticket/cart/checkout behavior.
- Does not change Ticketing V2 save/preview/push logic.
- Does not broaden Ticket Integrity suppression beyond the lightweight/core-save guard already introduced in 0.2.24.660.
- Does not yet clean up noisy title-save classification where unrelated vendor/finance meta can be rewritten during normal editor saves. That remains a follow-up profiling-polish item.

## Codex local result carried into this build

Codex patched and reran the local test as 0.2.24.662. The content-only WordPress Update on published ticketed Event Plan `76` succeeded and recorded:

- `save_type=core_wp_update`
- `Changed: Core only`
- `Ticket Integrity Plan Save (general_editor_save)` skipped
- `Staffing Rollup Dirty (no_staffing_change)` skipped
- `Staffing Seed Template (no_relevant_change)` skipped

Ticketing V2 still worked locally. A real Save Ticket Config change recorded a `module_meta_update` profile with `Tickets & Add-ons` as the changed module, and Preview Sync succeeded.

## Test focus

Run the included `vms-test-plan-0.2.24.662.md`. Prioritize confirming that content-only core saves show staffing work skipped, while real Ticketing V2 changes still appear as ticket-module work.
