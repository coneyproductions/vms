# VMS 0.2.24.651 Test Plan — Lineup Card Memory + Publish Load Hardening

## Build
- Plugin version: `0.2.24.651`
- Source baseline: `0.2.24.650`
- Primary focus:
  1. Event Plan supporting lineup cards remember expand/collapse state.
  2. `Publish Now` avoids duplicate TEC writes and defers lower-priority calendar maintenance.

## Smoke checks
1. Install/activate the zip normally in the canonical `vms/` plugin folder.
2. Confirm **VMS** shows version `0.2.24.651` in WordPress plugins.
3. Confirm `/wp-content/plugins/vms/vms-build.txt` begins with `0.2.24.651`.
4. Open an existing Event Plan that has 4+ supporting lineup entries.

## Lineup expand/collapse memory
1. Open an Event Plan with multiple supporting acts.
2. Confirm supporting cards are collapsed by default on first load when there are 4 or more supporting entries.
3. Expand two supporting cards and leave the rest collapsed.
4. Refresh the editor page.
5. Confirm the same two cards are expanded and the others remain collapsed.
6. Click **Expand All**, refresh, and confirm all lineup cards remain expanded.
7. Click **Collapse All**, refresh, and confirm all lineup cards remain collapsed.
8. Add a new supporting vendor row.
9. Confirm the new row opens immediately for entry.
10. Save/update the Event Plan and refresh again.
11. Confirm saved rows keep the last chosen expand/collapse state.

## Publish Now load-hardening regression
1. On staging, open an Event Plan that is already linked to a TEC event.
2. Click **Publish Now** with no event-facing changes.
3. Confirm the Event Plan remains Published and no duplicate TEC event is created.
4. Confirm the request completes without the long server spike previously seen.
5. Edit a visible event-facing field such as title, date/time, content, ticket URL/cost source, venue, or featured image.
6. Click **Publish Now** again.
7. Confirm the linked TEC event updates correctly.
8. Confirm the plan stores/updates `_vms_tec_last_sync_signature` and `_vms_tec_last_sync_at` meta.
9. Confirm a single `vms_event_plan_calendar_maintenance` cron event is scheduled for the plan/TEC event pair.
10. Run WP-Cron or wait for cron to run, then confirm `_vms_calendar_maintenance_last_run` is populated and vendor/category terms still sync as expected.

## Re-sync to Calendar regression
1. Use the existing **Re-sync to Calendar** control on a linked Event Plan.
2. Confirm the linked TEC event updates without creating a duplicate event.
3. Confirm vendor category sync is scheduled and completes via the calendar maintenance task.

## Safety checks
- Confirm public event pages still show the correct title, date/time, image, venue, ticket URL/cost, and content immediately after publish.
- Confirm previously patched disabled-ticket public UI protection from 0.2.24.650 still blocks/hides disabled ticket rows during pending ticket sync windows.
- Confirm browser console has no JavaScript errors on Event Plan editor load.

## Local validation performed for package
- Full PHP lint across all plugin PHP files.
- JavaScript syntax check across admin/public JS assets.
