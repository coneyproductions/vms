# VMS 0.2.24.662 Test Plan — Save Profiler Active-State Repair

## Primary goal

Confirm the 0.2.24.662 repair makes Event Plan save profiles accurately show staffing work skipped during content-only/core WordPress Updates, while preserving the 0.2.24.660 Ticket Integrity lightweight-save guard and 0.2.24.661 module dirty-map behavior.

## A. Version / activation sanity

1. Install/activate the plugin locally first.
2. Confirm `vms-build.txt` reports `0.2.24.662`.
3. Confirm the plugin header reports `0.2.24.662`.
4. Confirm `VMS_VERSION` reports `0.2.24.662`.
5. Confirm no activation fatal errors.
6. Run PHP lint and JS syntax checks.

Expected: all version markers match and activation succeeds.

## B. Content-only normal WordPress Update

1. Pick a published Event Plan with ticketing data.
2. Record baseline values for:
   - `_vms_ticket_integrity_last_plan_save_queue_at`
   - scheduled `vms_ticket_integrity_spot_scan` cron hooks for that plan
   - Ticket Integrity audit/queue row count, if available
   - ticket config/stat hashes
   - ticket product IDs
3. Make a content-only edit, such as appending an invisible HTML comment.
4. Click normal WordPress **Update**.
5. Re-open the Event Plan and inspect the Event Module Hub plus **VMS Save Profile** side metabox.

Expected:

- save succeeds
- save type is `core_wp_update` or equivalent
- changed modules show **Core only**
- Ticket Integrity Plan Save is skipped for `general_editor_save`
- Staffing Rollup Dirty is skipped with a reason like `no_staffing_change`
- Staffing Seed Template is skipped with a reason like `no_relevant_change`
- no new `vms_ticket_integrity_spot_scan` cron hook is scheduled
- no Ticket Integrity audit/queue row is added
- ticket config/stat hashes do not change
- ticket product IDs do not change

## C. Real Ticketing V2 module save

1. On a local/staging-safe Event Plan, make a small Ticketing V2 config change using **Save Ticket Config**.
2. Confirm the normal ticket save response succeeds.
3. Inspect the hub/profile after the module save.
4. Run Preview Sync.
5. Restore the original ticket config after testing.

Expected:

- ticketing still saves normally
- profile records ticket-module activity, commonly `module_meta_update` with Tickets & Add-ons changed
- profile does not falsely stay on stale Core-only state after the module save
- Preview Sync works and does not commit/push unexpectedly

## D. Event Module Hub rendering

1. Open at least one published ticketed Event Plan and one draft/ready Event Plan.
2. Confirm the Event Module Hub renders.
3. Confirm the **Last Event Plan Save** area renders cleanly.
4. Confirm the side **VMS Save Profile** metabox renders cleanly.

Expected: no fatal errors, no malformed cards, and no bogus same-to-same warning such as `Secondary vendor type changed from Food Truck to Food Truck`.

## E. Meaningful core update noise check

1. Change a safe core field such as title or description on a local fixture.
2. Save via normal WordPress Update.
3. Inspect the profile.

Expected:

- Ticket Integrity remains skipped unless an actual ticket-relevant field changed.
- Staffing remains skipped unless staffing or relevant context fields changed.
- Note any noisy module classification, such as vendor/finance meta being rewritten during a title save, as follow-up profiling polish rather than a blocker unless it triggers heavy work.

## F. Reduced staging smoke after local pass

1. Confirm staging serves `0.2.24.662`.
2. Open Event Plan `2515` or another published ticketed plan.
3. Perform one content-only save.
4. Confirm the hub/profile shows Core-only or equivalent lightweight save with Ticket Integrity and staffing work skipped.
5. Confirm no new Ticket Integrity queue entry appears.
6. Smoke-test one public event page, cart, and checkout.
7. Record editor open/save timing.

Expected: staging behaves like local and public ticket/cart/checkout remains stable.
