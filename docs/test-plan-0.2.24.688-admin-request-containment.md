# VMS 0.2.24.688 Test Plan

## Scope

Emergency containment for passive admin requests, especially TEC event-list browsing and broad `admin_init` maintenance work that should no longer run on ordinary admin page loads.

## Build markers

1. Confirm `vms/vendor-management-system.php` reports `Version: 0.2.24.688`.
2. Confirm `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.688`.
3. Confirm `vms/vms-build.txt` reads `0.2.24.688`.

## Passive admin containment

1. Open `/wp-admin/edit.php?post_type=tribe_events`.
2. Refresh it 2-3 times, including one fast repeat click or separate tab load.
3. Expected:
   - The page loads instead of hanging behind runaway PHP workers.
   - No automatic VMS backfill, sync, cleanup scheduler, or firewall migration starts just because the TEC list loaded.
   - VMS resource fingerprints or PHP error log traces show `heavy_admin_guard` / `[VMS TRACE]` entries with `decision=skipped` and a reason such as `passive_tec_admin` or `passive_admin_list`.
   - Action Scheduler async-runner suppression is logged for the TEC admin request.

4. Open `/wp-admin/edit.php?post_type=vms_event_plan`.
5. Expected:
   - The Event Plans list loads normally.
   - The same heavy admin backfills remain skipped on this passive list load.

6. Open one Event Plan editor at `/wp-admin/post.php?post=<event_plan_id>&action=edit`.
7. Expected:
   - The editor loads normally.
   - Passive page bootstrap does not start the guarded background-style backfills.
   - Existing intentional editor actions still work.

## Emergency switch

1. In a staging/local config, define `VMS_DISABLE_HEAVY_ADMIN_HOOKS` as `true`.
2. Reload the TEC list and the Event Plan list/editor.
3. Expected:
   - Guard traces show `reason=constant_disabled`.
   - No guarded admin-side heavy work runs.
   - Public ticketing remains unaffected.

## Public regression

1. Open one public event page.
2. Add tickets to cart.
3. Load cart and checkout.
4. Complete a normal purchase flow if staging allows it.
5. Expected:
   - Public event rendering, cart, and checkout behave the same as before.
   - No admin-only containment guard blocks public ticket checkout.

## Trace review

1. Open the VMS resource fingerprint screen after the tests.
2. Confirm entries capture:
   - request URI
   - screen/admin page context
   - guarded hook/action names
   - skip/allow decisions
   - elapsed time and memory
3. If server-side logs are available, confirm matching `[VMS TRACE]` JSON lines exist for the guarded blocks.
