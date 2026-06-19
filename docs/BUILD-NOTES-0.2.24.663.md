# VMS 0.2.24.663 — Publish Path Profiler

## Purpose

This build is a diagnostic architecture patch for the next Event Plan performance target: publishing/status transitions.

VMS 0.2.24.660/0.2.24.662 verified that ordinary content-only WordPress Updates can stay lightweight. However, a simple title change followed by publishing still produced high CPU/process load on the live host. 0.2.24.663 does not tear apart the publish pipeline yet; it adds the profiler visibility needed to identify exactly what publish is doing before we remove or defer heavy work.

## Changes

- Extends `includes/core/event-plan-save-profiler.php` with pre-update and status-transition diagnostics.
- Captures previous status before WordPress updates the Event Plan, so the profile can distinguish:
  - `core_wp_update`
  - `publish_transition`
  - `unpublish_transition`
  - other `status_transition` saves
- Records safe post-field change labels such as `title`, `content`, `excerpt`, and `status` without storing the old/new content values.
- Defers transition-time notes into the later save profile because WordPress fires `transition_post_status` before `save_post_vms_event_plan`.
- Adds no-op meta update attempt counting, including a top-key list, so Codex can identify modules attempting to rewrite unchanged data during publish/save flows.
- Updates the Event Module Hub / VMS Save Profile UI to show:
  - status start/end
  - post fields changed
  - meta update attempts
  - no-op meta update attempts
  - publish/status queue notes
- Updates Ticket Integrity spot-scan queue instrumentation so publish-transition spot-scan scheduling is visible in the Event Plan save profile.
- Bumps official version markers to `0.2.24.663`.

## What this intentionally does not change

- Does not remove publish validation.
- Does not suppress publish-time Ticket Integrity by itself.
- Does not change public ticket/cart/checkout behavior.
- Does not change Ticketing V2 Save Config / Preview / Push behavior.
- Does not yet move modules out of the Event Plan editor.
- Does not yet clean up every noisy meta rewrite; it makes those rewrites visible for the next reduction patch.

## Expected value

After a publish/status transition, the profile should answer:

- Did the save classify as `publish_transition`?
- What post fields actually changed?
- Which modules were marked dirty?
- Which heavy actions were skipped, scheduled, or triggered?
- Did Ticket Integrity queue a spot scan because of publish?
- Which meta keys were actually written?
- Which meta keys were attempted as no-op rewrites?

This should give us the evidence needed for a safer 0.2.24.664 publish-work reduction pass.
