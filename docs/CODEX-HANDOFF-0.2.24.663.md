# Codex Handoff — VMS 0.2.24.663

## Context

The current modularization goal is to stop the Event Plan editor from acting like one giant all-module rebuild. 0.2.24.660/0.2.24.662 fixed the ordinary content-only WordPress Update path: content-only saves now profile as Core only and skip Ticket Integrity/staffing heavy work.

A live observation after installing/testing 0.2.24.662 showed that changing an Event Plan name and publishing still pushed cPanel SPEED/CPU to 100%, with high NPROC/Entry Processes but no disk I/O pressure. That points to CPU/process churn in the publish/status-transition path, not the ordinary update path.

## What changed in 0.2.24.663

- `includes/core/event-plan-save-profiler.php`
  - Adds pre-update capture via `pre_post_update`.
  - Adds status-transition capture via `transition_post_status`.
  - Adds deferred notes/heavy-actions so hooks that fire before `save_post_vms_event_plan` can still show up in the final save profile.
  - Adds post-field labels (`title`, `content`, `excerpt`, `status`) to the saved profile.
  - Adds meta update attempt and no-op meta update attempt counters.
  - Updates Event Module Hub / VMS Save Profile output to surface the new diagnostics.

- `includes/ticketing/ticket-integrity-cron.php`
  - Adds deferred profiler notes for publish-transition Ticket Integrity spot-scan queueing.

## Primary testing question

When an Event Plan is published or transitions status, does the profile now show publish/status details clearly enough to identify what is making publish heavy?

## Things to watch

- Save type should become `publish_transition` when the Event Plan enters publish from a non-publish status.
- A normal content-only Update to an already-published plan should remain `core_wp_update` / Core only.
- Publish may still queue Ticket Integrity; that is expected for this diagnostic build if publish enters `publish` from another status.
- No-op meta update attempts should be counted but should not change save behavior.
- Ticketing V2 Save Config and Preview Sync should continue to behave like 0.2.24.662.

## Recommended next patch after evidence is collected

0.2.24.664 should reduce publish work based on the 0.2.24.663 profile evidence, especially no-op vendor/finance/meta rewrites and any heavy actions that run on title/status-only publish without a real module change.
