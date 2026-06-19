# VMS Test Plan 0.2.24.665 — Social Template Default No-Effect Meta Cleanup

## A. Version / syntax sanity

1. Confirm plugin header, `VMS_VERSION`, and `vms-build.txt` show `0.2.24.665`.
2. Run PHP lint across plugin PHP files.
3. Run `node --check` across non-minified plugin JS files.
4. Confirm plugin activation/admin load has no fatal errors.

## B. Content-only Event Plan save should be Core only

Use a published ticketed Event Plan.

1. Record baseline for Ticket Integrity queue/audit, scheduled `vms_ticket_integrity_spot_scan`, ticket config hash, sync hash, and mapped product IDs.
2. Append a harmless invisible HTML comment to post content.
3. Click normal WordPress Update.
4. Confirm save succeeds.
5. Confirm profile records:
   - `save_type=core_wp_update`
   - `post_field_changes=content`
   - changed modules `core` only
   - Ticket Integrity skipped
   - Staffing Rollup Dirty skipped
   - Staffing Seed Template skipped
6. Confirm no new spot-scan, Ticket Integrity queue/audit growth, ticket hash changes, sync hash changes, or product ID changes.
7. Confirm `_vms_social_template_overrides` is not listed as an effective meta key when no social template override was changed.

## C. Social template override still works when intentionally changed

1. On the same Event Plan, intentionally change one Promotion / Social Sharing template override from Default to a specific template, or from a specific template back to Default if a safe fixture exists.
2. Save.
3. Confirm the profile records Marketing as changed.
4. Confirm `_vms_social_template_overrides` appears as an effective meta key.
5. Restore the original value and confirm cleanup save behaves as expected.

## D. Title-only update noise check

1. Change only the Event Plan title.
2. Save.
3. Confirm Ticket Integrity/staffing heavy work stay skipped.
4. Confirm no new Ticket Integrity queue/audit activity.
5. Confirm no unrelated effective Marketing write appears.
6. Restore original title.

## E. Ticketing V2 smoke

1. Make a tiny temporary Ticketing V2 config change.
2. Confirm Save Ticket Config succeeds and profiles as module meta update for Tickets & Add-ons.
3. Run Preview Sync.
4. Confirm Preview Sync does not commit and does not change sync hash/product IDs.
5. Restore the original config.

## F. Event Module Hub / vendor shortcuts

1. Confirm Event Module Hub, Last Event Plan Save, and VMS Save Profile render on a published and draft Event Plan.
2. Confirm no bogus same-to-same warning appears.
3. Confirm Add New Vendor links render for primary and secondary vendor sections and open in a new tab.

## G. Optional reduced staging smoke

Use staging when a more real-world check is useful.

1. Confirm staging serves 0.2.24.665.
2. Run one content-only save on a published ticketed Event Plan and confirm changed modules are core only.
3. Confirm no visible Ticket Integrity queue/audit growth.
4. Smoke-test one public event page, add-to-cart, cart, and checkout if practical.
5. Record timings and any unrelated warnings separately.
