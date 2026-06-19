# VMS 0.2.24.664 Build Notes — No-Effect Meta Dirty-Map Cleanup + Vendor Shortcuts

## Summary

0.2.24.664 is a conservative follow-up to the 0.2.24.663 publish-path profiler. It does not remove major publish behavior yet. It tightens the profiler so ordinary Event Plan saves are classified by final effective data changes instead of noisy internal meta churn, and it reduces two known no-effect write sources.

## Changes

### Event Plan save profiler

- Captures save-start meta value hashes for touched Event Plan meta keys.
- Compares final meta values at profiler finish.
- Keeps module dirty-map reasons only when a touched meta key actually changed by the end of the save, or when the module was explicitly marked dirty by a non-meta action.
- Adds diagnostic profile fields:
  - `effective_meta_keys`
  - `no_effect_meta_write_keys`
- Keeps no-op meta update attempt counting from 0.2.24.663.

### Lineup/vendor index churn

- `vms_rebuild_event_plan_lineup_indexes()` now compares the existing `_vms_lineup_entry_vendor_id` index set to the next set before deleting/re-adding meta rows.
- If the indexed vendor IDs are already correct, the rebuild returns without delete/add churn.

### Pay acknowledgement timestamp churn

- Draft Pay override acknowledgement timestamps are preserved when the acknowledgement state and snapshots are unchanged.
- Low-guarantee acknowledgement timestamps are preserved when the acknowledgement state and snapshot are unchanged.
- Missing timestamps/user IDs are still filled when needed.

### Event Plan vendor shortcuts

- Adds a safe `Add new vendor` button near the Primary Vendor selector.
- Adds a safe `Add new vendor` button near the Secondary Vendors controls.
- Both links open the WordPress Add New Vendor screen in a new tab so an unsaved Event Plan is not abandoned.
- Context query args are included for future vendor-prefill/return flow work.

## Important scope notes

- This version does not yet remove the publish-triggered Ticket Integrity spot scan. It should only make the profiler cleaner and reduce known no-effect churn.
- A true publish/status transition may still schedule Ticket Integrity with reason `event_plan_publish`; that remains expected until a later publish-work reduction pass.
- The Add New Vendor action is currently a safe link/shortcut, not an inline AJAX quick-add modal.

## Validation performed during packaging

- PHP lint passed across plugin PHP files.
- JS syntax checks passed across non-minified plugin JS files.
- Zip integrity passed.
