# VMS Data Tools 0.5.42 Test Plan — Ticket Pace Event Comparison

## Build

- Plugin: `vms-data-tools`
- Version: `0.5.42`
- Slug: `ticket-pace-event-comparison`

## Repair/versioning protocol

🚨 If Codex or any tester makes even a minimal code repair during testing, update all relevant version markers and package notes before returning the modified zip. At minimum check/update:

- `vms-data-tools.php` plugin header version
- `VMS_DT_VERSION`
- `vms-build.txt`
- this test plan or replacement test notes
- package filename

Do not return a modified build with stale versioning/docs.

## Primary checks

1. Install/replace the plugin zip.
2. Confirm WordPress shows VMS Data Tools version `0.5.42`.
3. Open **VMS → Reports → Ticket Pace**.
4. Select a current/upcoming event with ticket sales.
5. Confirm the page still shows:
   - Current paid qty
   - Current sales
   - Non-cancelled average qty/sales
   - Projected finish
   - Pace trend chart
   - Milestones table with Today/current row when today is between standard milestones
   - Daily pace table

## Direct event comparison

1. In the Ticket Pace filter card, use **Compare pace to event**.
2. Select a specific past event that has ticket sales.
3. Refresh the report.
4. Confirm the chart shows:
   - This event line
   - Historical average line
   - Selected event comparison line
   - Legend labels for each line
5. Confirm the **Event-to-event pace comparison** table appears.
6. Confirm the table compares the two events by days out, including:
   - 30 / 14 / 7 / 3 / 1 / 0 day checkpoints
   - Today/current row when today is not one of the standard checkpoints
   - this event date and selected comparison event date for each checkpoint
   - qty and sales for each side
   - difference column
7. Confirm future checkpoints for the current event still show **Not reached yet** instead of repeating today’s totals.
8. Confirm the selected past event may show completed future checkpoints because it is historical data.

## Historical average / cancelled events

1. Confirm KPI labels read **Non-cancelled avg qty** and **Non-cancelled avg sales**.
2. Confirm the comparison scope copy says the average is based on non-cancelled previous events.
3. If the site has cancelled/canceled Event Plans in the prior-event range, confirm the **Cancelled excluded** summary row appears.
4. Confirm cancelled/canceled events are not included in the average final qty/sales or milestone averages.

## Regression checks

1. Set **Compare pace to event** back to **Historical average only**.
2. Confirm the event-to-event table disappears.
3. Confirm the Ticket Pace report still works without a selected comparison event.
4. Confirm the existing Compare Events report is unchanged and still compares final/current totals as before.
5. Confirm no fatal errors in the admin screen or PHP error log.
