# VMS 0.2.24.674 Test Plan

🚨 **Codex repair/versioning protocol:** If Codex makes even a small directly-related code repair while testing/troubleshooting this build, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision/build notes, this test plan or a follow-up test plan, and the package filename before returning a replacement zip.

## A. Version markers
1. Install/update the canonical `vms` plugin folder from `VMS_674_event_plan_lightweight_save_and_tec_author_fix.zip` or the equivalent canonical package contents.
2. Confirm `wp-content/plugins/vms/vms-build.txt` shows `0.2.24.674`.
3. Confirm the plugin header and `VMS_VERSION` report `0.2.24.674`.
4. Confirm DT remains `0.5.46` unless a DT code change is intentionally part of the patch.

## B. Empty Event Plan create/save path
1. Clear VMS trace logs / PHP error log if you want a clean pass.
2. In wp-admin with WordPress + WooCommerce + TEC/Event Tickets + VMS active, create a brand-new Event Plan with no ticket config and save it as draft.
3. Confirm the save finishes quickly and does not spike shared-host CPU/process counts the way the broken `0.2.24.662` build did.
4. Confirm the trace log shows the Event Plan save hooks, but `ticket_integrity_spot_scan`, `staff_tasks_generation`, and `staffing_seed_template` report `skip_reason=no_effective_tickets`.
5. Confirm no duplicate per-event jobs are scheduled for:
   - `vms_ticket_integrity_spot_scan`
   - `vms_tasks_generate_for_event_queued`
   - `vms_staffing_seed_event_slots_queued`
   - `vms_event_plan_calendar_maintenance`

## C. Empty Event Plan publish path
1. Publish the same no-ticket Event Plan.
2. Confirm the linked TEC event is still created/published as expected.
3. Confirm the publish path does not enqueue Ticket Integrity, staff-task generation, or extra maintenance jobs for that empty event.
4. Confirm there is at most one deferred maintenance job scheduled for that Event Plan.
5. Save/update the same empty Event Plan again and confirm the path stays lightweight.

## D. TEC author verification
1. Open the WordPress Events list (`post_type=tribe_events`) after creating the linked TEC event.
2. Confirm the Author column shows a real user such as `admin`, not `—`.
3. Confirm the linked `tribe_events` post does not have `post_author=0`.
4. Confirm repeated Event Plan saves/resyncs do not reset the TEC event author to `0`.
5. Confirm the trace log records `event_plan_id`, `linked_tec_event_id`, author before/after, captured actor, current user, and cron/AJAX/REST context.

## E. Public calendar sanity
1. Open the linked public calendar event from wp-admin.
2. Confirm the public event URL resolves and remains clickable.
3. If you intentionally backfill a linked TEC event with `post_author=0` in staging, run a VMS sync/save path and confirm author is repaired without creating a duplicate TEC event.

## F. Ticketed regression sanity
1. Create or open an Event Plan that does have effective tickets.
2. Confirm deferred calendar publish/maintenance, Ticket Integrity, and staffing background paths can still queue once per event when needed.
3. Confirm repeated saves while a job is pending do not schedule duplicates.
