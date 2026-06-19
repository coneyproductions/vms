# Codex Handoff — VMS 0.2.24.672

## Focus

Finish the scoped Action Scheduler async suppression follow-up from the staging diagnostic run. The key gaps were that Event Command Center was missing from the scoped page list and the `action_scheduler_async_blocked` fingerprint flag did not reliably appear on scoped pages unless the Action Scheduler filter path itself happened to execute.

## High-priority assertions

1. The `0.2.24.671` WP-CLI activation/deactivation compatibility fix must remain intact.
2. ECC, DT root, DT single-event, and the intentionally scoped Event Plan editor should leave `action_scheduler_async_blocked` fingerprint entries with the page slug and scope reason.
3. Normal WordPress Dashboard, Plugins, WooCommerce Orders, and unrelated admin pages must not show that suppression flag.
4. Action Scheduler async runners should still be able to execute on non-scoped pages when pending work exists.

## Known scope

No DT code changed in this follow-up. DT remains `0.5.46`. Event Plan edit stays intentionally scoped because the editor still loads the Module Hub / report snapshot path and is part of the heavy-surface overlap problem this diagnostic is targeting.

## Release package

- Versioned zip filename: `VMS_672_scoped_action_scheduler_async_suppression_markers.zip`
- Canonical convenience zip: `vms.zip`
