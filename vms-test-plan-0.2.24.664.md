# VMS 0.2.24.664 Test Plan — No-Effect Meta Dirty-Map Cleanup + Vendor Shortcuts

## Primary goal

Confirm 0.2.24.664 keeps the 0.2.24.662/0.2.24.663 safety behavior while cleaning the noisy module dirty-map classification and reducing known no-effect meta churn.

## Testing environment note

Test locally first whenever possible. The user generally installs each candidate version on staging as a fallback/real-world option, so staging may be used for behaviors that depend on real hosting, WP-Cron, cache, Woo/TEC checkout, public pages, or online-only conditions.

## A. Version / syntax / activation sanity

1. Install/activate the plugin locally first.
2. Confirm `vms-build.txt` reports `0.2.24.664`.
3. Confirm the plugin header reports `0.2.24.664`.
4. Confirm `VMS_VERSION` reports `0.2.24.664`.
5. Confirm no activation fatal errors.
6. Run PHP lint across plugin PHP files.
7. Run `node --check` across non-minified plugin JS files.

Expected: all version markers match and syntax/activation checks pass.

## B. Regression: content-only normal WordPress Update stays lightweight and clean

1. Pick a published Event Plan with ticketing data.
2. Record baseline values for:
   - `_vms_ticket_integrity_last_plan_save_queue_at`
   - scheduled `vms_ticket_integrity_spot_scan` cron hooks for that plan
   - Ticket Integrity audit/queue row count, if available
   - ticket config/stat hashes
   - ticket product IDs
3. Make a content-only edit, such as appending/removing an invisible HTML comment.
4. Click normal WordPress **Update**.
5. Re-open the Event Plan and inspect the Event Module Hub plus **VMS Save Profile** side metabox.

Expected:

- save succeeds
- save type remains `core_wp_update` or equivalent
- changed modules show **Core only** unless a real module value changed
- post fields changed should show `content` if content was changed
- Ticket Integrity Plan Save is skipped for `general_editor_save`
- Staffing Rollup Dirty is skipped with `no_staffing_change`
- Staffing Seed Template is skipped with `no_relevant_change`
- no new `vms_ticket_integrity_spot_scan` cron hook is scheduled
- no Ticket Integrity audit/queue row is added
- ticket config/stat hashes do not change
- ticket product IDs do not change
- any no-effect module writes are listed under `no_effect_meta_write_keys` and do not make modules appear dirty

## C. Already-published title update check

1. On an already-published Event Plan, change only the title.
2. Save via normal WordPress Update.
3. Inspect the profile.

Expected:

- save type should remain `core_wp_update`, not `publish_transition`
- post fields changed should include `title`
- Ticket Integrity and staffing heavy work should remain skipped unless a true relevant module field changed
- changed modules should be Core only or cleaner than 0.2.24.663
- `_vms_lineup_entry_vendor_id` should not be deleted/re-added when the vendor index set is unchanged
- `_vms_pay_override_ack_ts` / low-guarantee ack timestamps should not update solely because a title changed when the acknowledgement snapshots are unchanged

## D. Publish/status-transition diagnostic check

Use a local or staging-safe Event Plan that can be published without affecting real customers.

1. Start with a draft/ready/non-published Event Plan.
2. Record baseline values for Ticket Integrity queue/audit counts and scheduled spot-scan hooks.
3. Change only the Event Plan title, or make another safe core-only edit.
4. Publish the Event Plan.
5. Re-open the Event Plan and inspect the Event Module Hub plus **VMS Save Profile**.

Expected:

- save succeeds
- save type is `publish_transition` when status enters `publish` from a non-publish status
- status line shows the old status → `publish`
- post fields changed includes `status`, and includes `title` if title changed
- publish/status diagnostics from 0.2.24.663 still appear
- if Ticket Integrity queues a publish spot scan, it is shown as scheduled or skipped/already scheduled with reason `event_plan_publish`
- this build is still allowed to schedule a true publish-transition spot scan; that is not automatically a failure

## E. Ticketing V2 regression check

1. On a safe Event Plan, make a small Ticketing V2 config change using **Save Ticket Config**.
2. Confirm the normal ticket save response succeeds.
3. Inspect the hub/profile after the module save.
4. Run Preview Sync.
5. Restore the original ticket config after testing.

Expected:

- ticketing still saves normally
- profile records ticket-module activity, commonly `module_meta_update` with Tickets & Add-ons changed
- profile does not falsely stay on stale Core-only state after the module save
- Preview Sync works and does not commit/push unexpectedly

## F. Vendor shortcut UI check

1. Open a new or existing Event Plan editor.
2. In the Primary Vendor section, confirm an `Add new vendor` button/link appears near the selector.
3. Click/open it.
4. Confirm it opens the WordPress Add New Vendor screen in a new tab/window and does not navigate away from the unsaved Event Plan.
5. In the Secondary Vendors section, confirm an `Add new vendor` button/link appears near the secondary vendor row controls.
6. If a secondary vendor type is selected, confirm the link includes context for that type in the URL.

Expected: shortcuts are visible, safe, and do not break the existing vendor selectors.

## G. Event Module Hub / VMS Save Profile rendering

1. Open at least one published ticketed Event Plan and one draft/ready Event Plan.
2. Confirm the Event Module Hub renders.
3. Confirm the **Last Event Plan Save** area renders cleanly.
4. Confirm the side **VMS Save Profile** metabox renders cleanly.
5. Confirm no bogus same-to-same warning such as `Secondary vendor type changed from Food Truck to Food Truck`.

Expected: no fatal errors, no malformed cards, and the new/effective meta diagnostic fields do not break display.

## H. Reduced staging smoke after local pass

1. Confirm staging serves `0.2.24.664`.
2. Open one published ticketed Event Plan and confirm the hub/profile renders.
3. Perform one content-only normal Update and confirm it stays lightweight and classifies cleanly.
4. If needed, use a staging-safe draft/ready Event Plan for one publish/status-transition test.
5. Smoke-test one public event page, cart, and checkout.
6. Record editor open/save/publish timing and any cPanel/Query Monitor observations.

Expected: staging behaves like local, public ticket/cart/checkout remains stable, and publish diagnostics remain available for the later publish-work reduction patch.
