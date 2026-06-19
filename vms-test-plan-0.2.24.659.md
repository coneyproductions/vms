# VMS 0.2.24.659 Test Plan — Event Plan Module Hub

## Codex testing note

Codex may make small, directly related code repairs during testing if a minor issue is discovered. If code is changed, update the VMS version/build number consistently in the plugin header, `VMS_VERSION`, and `vms-build.txt`, and document the repair.

## A. Version / package sanity

1. Install/activate the plugin on staging.
2. Confirm `vms-build.txt` reports `0.2.24.659`.
3. Confirm the plugin header reports `0.2.24.659`.
4. Confirm `VMS_VERSION` reports `0.2.24.659`.
5. Confirm no activation fatal errors.

Expected: all version markers match and activation succeeds.

## B. Event Plan editor visibility

1. Open an existing Event Plan with ticketing, lineup, and staffing data.
2. Confirm a new metabox titled **Event Module Hub** appears near the top of the editor.
3. Confirm the hub displays cards for:
   - Core Event Details
   - Tickets & Add-ons
   - Lineup & Vendors
   - Staffing
   - Compensation / Finance
   - Marketing / Promo

Expected: cards render without PHP warnings/notices and without breaking the existing Event Plan editor sections.

## C. Summary accuracy smoke test

On the same Event Plan, compare the hub summary against known existing data:

1. Core card shows date/time, venue, status, and last updated value.
2. Ticket card shows sold count, gross sales, and remaining inventory when known.
3. Lineup card shows primary vendor, supporting count, and secondary vendor count.
4. Staffing card shows filled/needed coverage and open roles.
5. Compensation card shows vendor pay/labor/projected margin.
6. Marketing card shows event page/social/promo video status.

Expected: values are reasonable. The Event Plan metabox may show lightweight/cached snapshots, while the full Event Command Center remains the deeper diagnostic view.

## D. Link behavior

Click each available hub action link:

1. **Edit Core Details** should remain in the Event Plan editor and jump to the Event Plan Details metabox.
2. **Manage Tickets** should remain in the Event Plan editor and jump to the Ticketing metabox.
3. **Open Integrity** should open the Ticket Integrity page.
4. **Edit Lineup** should jump to the lineup section.
5. **Manage Staffing** should jump to the staffing section when present.
6. **Edit Compensation** should jump to the compensation section.
7. **Open Ads Workspace** should open the marketing/Meta Ads Builder destination available in that install.
8. **Open Full Command Center** should open the existing Event Command Center for the same Event Plan.

Expected: links do not 404, do not submit the Event Plan form, and do not trigger save/publish actions.

## E. Save-path safety

1. Open an Event Plan.
2. Do not change any fields.
3. Click the normal WordPress **Update** button.
4. Confirm the hub still appears after redirect.
5. Confirm no extra Ticket Integrity queue row is created merely by displaying the hub.
6. Confirm opening the Event Plan editor does not trigger the full Ticket Integrity scan just to render the hub.
7. Confirm the latest Event Plan save profile does not show a new expensive operation caused by the hub itself.

Expected: the hub is read-only and does not add new save-side behavior.

## F. Regression checks

1. Ticketing V2 Save config still works.
2. Ticketing V2 Preview sync still works.
3. Existing Event Plan Details save still works.
4. Existing Command Center page still loads.
5. Existing Event Plan submitbox **Open Command Center** link still appears.

Expected: no regression in 0.2.24.658 behavior.

