# Build Notes — VMS 0.2.24.651

## Summary
This patch addresses a painful Event Plan editor workflow and reduces the amount of work performed during `Publish Now`.

## Changes

### Event Plan lineup editor
- Supporting lineup `<details>` cards now remember their expanded/collapsed state per Event Plan and browser.
- The memory key uses the Event Plan ID and each lineup row's stable `row_id`.
- Long lineups with 4+ supporting entries default supporting cards collapsed on first load so the page is not a wall of expanded fields.
- **Expand All** and **Collapse All** now persist the chosen state.
- Newly added supporting rows still open immediately so operators can fill them in.

### Publish / TEC sync load hardening
- Removed the duplicate immediate post-publish `tribe_update_event()` call. The primary publish payload already includes the event-facing data.
- Added a stored TEC calendar sync signature so repeat/no-op `Publish Now` clicks can skip a heavy TEC update when nothing calendar-facing changed.
- Avoids re-setting the same TEC featured image when it is already correct.
- Defers vendor-category calendar maintenance to a small scheduled task (`vms_event_plan_calendar_maintenance`) instead of doing it inside the editor request.
- Records lightweight maintenance metadata:
  - `_vms_calendar_maintenance_queued_at`
  - `_vms_calendar_maintenance_reason`
  - `_vms_calendar_maintenance_last_run`
  - `_vms_calendar_maintenance_last_error` when applicable

## Notes
- The public TEC event still receives title, status, date/time, content, venue, ticket URL, cost, and featured image in the main publish operation.
- Vendor/category term syncing is lower priority and can safely finish shortly after publish via WP-Cron.
- This does not change ticket product creation/update behavior; the retired legacy Woo auto-publish path remains retired.

## Preserved from 0.2.24.650
- Disabled saved-config ticket rows are hidden/neutralized on public ticket UIs during pending ticket sync windows.
- Server-side disabled-ticket fail-closed guards remain in place for add-to-cart/cart/checkout.
