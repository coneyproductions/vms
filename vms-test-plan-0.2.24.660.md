# VMS 0.2.24.660 Test Plan — Event Plan Lightweight Update Guard

## Codex testing note

Codex may make small, directly related code repairs during testing if a minor issue is discovered. If code is changed, update the VMS version/build number consistently in the plugin header, `VMS_VERSION`, and `vms-build.txt`, and document the repair.

## A. Version / activation sanity

1. Install/activate the plugin on staging.
2. Confirm `vms-build.txt` reports `0.2.24.660`.
3. Confirm the plugin header reports `0.2.24.660`.
4. Confirm `VMS_VERSION` reports `0.2.24.660`.
5. Confirm no activation fatal errors.

Expected: all version markers match and activation succeeds.

## B. Regression: Event Module Hub still renders

1. Open at least three Event Plans:
   - one published ticketed event
   - one draft/ready event
   - one complex event with lineup/staffing/ticketing data
2. Confirm the **Event Module Hub** metabox renders.
3. Confirm the six hub cards still appear:
   - Core Event Details
   - Tickets & Add-ons
   - Lineup & Vendors
   - Staffing
   - Compensation / Finance
   - Marketing / Promo
4. Confirm hub links still resolve and do not 404.

Expected: same 0.2.24.659 hub behavior, without fatal errors.

## C. Critical fix: content-only WP Update must not queue Ticket Integrity

1. Choose a published Event Plan with ticketing, preferably the same kind used in the failed 0.2.24.659 test.
2. Record the current values for:
   - `_vms_ticket_integrity_last_plan_save_queue_at`
   - scheduled `vms_ticket_integrity_spot_scan` events for that plan
   - Ticket Integrity log/audit row count if available
3. Make a content-only edit using the normal WordPress editor, such as appending or changing an invisible HTML comment in post content.
4. Click the normal WordPress **Update** button.
5. Re-check the same values.

Expected:

- WordPress save succeeds.
- `_vms_ticket_integrity_last_plan_save_queue_at` does **not** update because of this content-only save.
- No new `vms_ticket_integrity_spot_scan` is scheduled for that plan.
- No new Ticket Integrity queue/log row is created merely because of the content-only Event Plan update.
- Save profile/profiler notes, if visible, may show `ticket_integrity_plan_save: skipped_general_editor_save`.

## D. Ticketing changes should still queue Ticket Integrity when appropriate

1. On a staging-safe Event Plan, make a small Ticketing V2 config change.
2. Save config normally.
3. Check scheduled cron/log behavior.

Expected: real ticketing config/sync meta changes still trigger the ticketing meta watcher and may queue Ticket Integrity as before.

## E. Publish/cancel high-risk actions still queue when appropriate

Use staging-safe test records only.

1. Publish a draft/ready Event Plan using the VMS publish flow.
2. Confirm Ticket Integrity can still queue from the publish action/transition path.
3. If a safe cancellation/refund test fixture exists, verify cancellation/refund actions still queue expected integrity work.

Expected: high-risk lifecycle actions were not disabled by the general editor-save guard.

## F. No-op review warning guard

1. Re-open the Event Plan that previously showed a warning like:
   - `Secondary vendor type changed from Food Truck to Food Truck`
2. Confirm the no-op warning is not displayed in the Event Module Hub / Needs Review display.
3. Make a real secondary vendor type change on a safe staging fixture.
4. Confirm a real change still displays as expected.

Expected: same-label no-op warnings are suppressed, real changes remain visible.

## G. Public smoke test

1. Open a public upcoming event page.
2. Confirm ticket UI still loads.
3. Add a normal paid ticket to cart.
4. Confirm cart and checkout still load.

Expected: no public ticket/cart/checkout regression from this admin save-path patch.
