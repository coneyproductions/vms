# Codex Handoff — VMS 0.2.24.673

## Focus

Ship a replacement for `0.2.24.672` that keeps the scoped async-suppression diagnostics readable in the saved fingerprint log. The scoped pages were correct in `0.2.24.672`, but the stored `action_scheduler_async_blocked` values were over-compacted to `...` in the health viewer.

## High-priority assertions

1. The `0.2.24.671` WP-CLI activation/deactivation compatibility fix must remain intact.
2. ECC, DT root, DT single-event, and the intentionally scoped Event Plan editor should still log `action_scheduler_async_blocked`.
3. The stored flag/marker payload for that suppression should now preserve readable `page`, `page_slug`, `scope_reason`, and `mode` values.
4. Normal WordPress Dashboard, Plugins, WooCommerce Orders, and unrelated admin pages must not show that suppression flag.
5. Action Scheduler async runners should still be able to execute on non-scoped pages when pending work exists.

## Known scope

No DT code changed in this follow-up. DT remains `0.5.46`. Event Plan edit stays intentionally scoped because the editor still loads the Module Hub / report snapshot path and is part of the heavy-surface overlap problem this diagnostic is targeting.

## Release package

- Versioned zip filename: `VMS_673_async_suppression_marker_context_fix.zip`
- Canonical convenience zip: `vms.zip`
