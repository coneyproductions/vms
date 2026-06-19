# VMS 0.2.24.663 Test Plan — Publish Path Profiler

## Primary goal

Confirm 0.2.24.663 adds useful publish/status-transition diagnostics without regressing the 0.2.24.662 lightweight ordinary Update behavior or public ticketing behavior.

## A. Version / syntax / activation sanity

1. Install/activate the plugin locally first.
2. Confirm `vms-build.txt` reports `0.2.24.663`.
3. Confirm the plugin header reports `0.2.24.663`.
4. Confirm `VMS_VERSION` reports `0.2.24.663`.
5. Confirm no activation fatal errors.
6. Run PHP lint across plugin PHP files.
7. Run `node --check` across non-minified plugin JS files.

Expected: all version markers match and syntax/activation checks pass.

## B. Regression: content-only normal WordPress Update stays lightweight

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
- changed modules show **Core only**
- post fields changed should show `content` if content was changed
- Ticket Integrity Plan Save is skipped for `general_editor_save`
- Staffing Rollup Dirty is skipped with `no_staffing_change`
- Staffing Seed Template is skipped with `no_relevant_change`
- no new `vms_ticket_integrity_spot_scan` cron hook is scheduled
- no Ticket Integrity audit/queue row is added
- ticket config/stat hashes do not change
- ticket product IDs do not change

## C. Publish/status-transition diagnostic check

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
- Queue / hook notes include status/publish transition details
- if Ticket Integrity queued a publish spot scan, the Heavy Work summary shows `Ticket Integrity Spot Scan` as scheduled or skipped/already scheduled with reason `event_plan_publish`
- profile shows meta update attempts and no-op meta update attempts
- top meta keys / no-op meta keys give enough evidence to identify noisy vendor/finance/ticket/staffing rewrites
- no fatal errors or malformed profile UI

Note: This build is diagnostic. A publish-triggered Ticket Integrity queue is not automatically a failure unless it duplicates endlessly or runs on an already-published normal Update.

## D. Already-published title update check

1. On an already-published Event Plan, change only the title.
2. Save via normal WordPress Update.
3. Inspect the profile.

Expected:

- save type should remain `core_wp_update`, not `publish_transition`
- post fields changed should include `title`
- Ticket Integrity and staffing heavy work should remain skipped unless a true relevant module field changed
- any vendor/finance/ticket meta rewrite noise should be documented from `top_meta_keys` / no-op meta attempts, not treated as a blocker unless it triggers heavy work

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

## F. Event Module Hub / VMS Save Profile rendering

1. Open at least one published ticketed Event Plan and one draft/ready Event Plan.
2. Confirm the Event Module Hub renders.
3. Confirm the **Last Event Plan Save** area renders cleanly.
4. Confirm the side **VMS Save Profile** metabox renders cleanly.
5. Confirm no bogus same-to-same warning such as `Secondary vendor type changed from Food Truck to Food Truck`.

Expected: no fatal errors, no malformed cards, and the new publish/status fields display cleanly when available.

## G. Reduced staging smoke after local pass

1. Confirm staging serves `0.2.24.663`.
2. Open one published ticketed Event Plan and confirm the hub/profile renders.
3. Perform one content-only normal Update and confirm it stays lightweight.
4. Use a staging-safe draft/ready Event Plan for one publish/status-transition test.
5. Confirm the publish profile shows status transition, post-field changes, and publish/Ticket Integrity queue notes.
6. Smoke-test one public event page, cart, and checkout.
7. Record editor open/save/publish timing and any cPanel/Query Monitor observations.

Expected: staging behaves like local, public ticket/cart/checkout remains stable, and publish now produces enough diagnostic detail to guide the next reduction patch.
