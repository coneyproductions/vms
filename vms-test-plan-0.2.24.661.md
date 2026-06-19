# VMS 0.2.24.661 Test Plan — Event Plan Save Profiler + Module Dirty Map

## Codex testing note

Codex may make small, directly related code repairs during testing if a minor issue is discovered. If code is changed, update the VMS version/build number consistently in the plugin header, `VMS_VERSION`, and `vms-build.txt`, and document the repair.

## Primary goal

Confirm Event Plan saves are profiled and classified correctly without changing public ticketing behavior.

## A. Version / activation sanity

1. Install/activate the plugin locally first.
2. Confirm `vms-build.txt` reports `0.2.24.661`.
3. Confirm the plugin header reports `0.2.24.661`.
4. Confirm `VMS_VERSION` reports `0.2.24.661`.
5. Confirm no activation fatal errors.
6. Run PHP lint and JS syntax checks.

Expected: all version markers match and activation succeeds.

## B. Event Module Hub / Save Profile rendering

1. Open a published ticketed Event Plan.
2. Confirm the Event Module Hub renders.
3. Confirm the hub shows a **Last Event Plan Save** panel.
4. If no profile exists yet, confirm the empty state is clean and not confusing.
5. Confirm the side metabox **VMS Save Profile** renders without fatal errors.

Expected: the hub and side profile metabox render cleanly.

## C. Content-only normal WordPress Update

1. Pick a published Event Plan with ticketing data.
2. Record baseline values for:
   - `_vms_ticket_integrity_last_plan_save_queue_at`
   - scheduled `vms_ticket_integrity_spot_scan` cron hooks for that plan
   - Ticket Integrity audit/queue row count, if available
   - ticket config/stat hashes
   - ticket product IDs
3. Make a content-only edit, such as appending an invisible HTML comment.
4. Click normal WordPress **Update**.
5. Re-open the Event Plan.
6. Inspect the Event Module Hub and the side **VMS Save Profile** metabox.

Expected:

- save succeeds
- a save profile is written even if the save is fast
- save type is similar to `core_wp_update`
- changed modules show **Core only** or equivalent core-only state
- Ticket Integrity is shown as skipped for the normal editor save
- staffing rollup/template work is skipped unless staffing/context fields actually changed
- no new `vms_ticket_integrity_spot_scan` cron hook is scheduled
- no Ticket Integrity audit/queue row is added
- ticket config/stat hashes do not change
- ticket product IDs do not change

## D. Meaningful core field update

1. Change a safe core field such as event description or title on a local fixture.
2. Save via normal WordPress Update.
3. Inspect the profile.

Expected:

- profile records a core editor save
- tickets/staffing/vendors/finance/marketing are not marked dirty unless their fields/meta actually changed
- no unintended ticket push/sync occurs

## E. Real ticket config change

1. On a staging-safe/local Event Plan, make a small Ticketing V2 config change using **Save Ticket Config**.
2. Confirm the normal ticket save response still succeeds.
3. Inspect the hub/profile after the module save.
4. Run Preview Sync.

Expected:

- ticketing still saves normally
- Preview Sync still works
- a module profile may show `module_meta_update` with Tickets & Add-ons as the changed module
- profile does not misleadingly remain on a stale Core-only save after a ticket module update
- no unintended commit/push occurs from preview

## F. Staffing save-path guard

1. Perform a content-only Event Plan update.
2. Confirm profile shows staffing rollup/template work skipped.
3. On a local fixture where staffing fields can safely change, make a real staffing change if feasible.
4. Confirm staffing dirty detection is recognized when staffing actually changes.

Expected: staffing work is skipped for non-staffing saves and recognized for real staffing changes.

## G. No-op warning regression

1. Re-open the Event Plan that previously showed a warning like:
   - `Secondary vendor type changed from Food Truck to Food Truck`
2. Confirm the no-op warning is not displayed in the Event Module Hub / Needs Review surfaces.

Expected: same-label no-op warnings stay suppressed.

## H. Reduced staging smoke after local pass

1. Confirm staging serves `0.2.24.661`.
2. Open Event Plan `2515` or another published ticketed plan.
3. Perform one content-only save.
4. Confirm the hub/profile shows a core-only save and heavy work skipped.
5. Confirm no new Ticket Integrity queue entry appears.
6. Smoke-test one public event page, cart, and checkout.
7. Record editor open/save timing.

Expected: staging behaves like local and public ticket/cart/checkout remains stable.
