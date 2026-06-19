# VMS Test Plan — 0.2.24.522 Reschedule ticketing + public reschedule visuals

Test only the specific fixes in this pass.

## Test A — rescheduled draft ticketing default
1. Cancel a published Event Plan and create a linked replacement draft through the replacement-date workflow.
2. Open the new replacement draft.
3. Confirm **Tickets for this event** is set to **On**.
4. Confirm ticketing template/config rows from the source still exist if they were present on the original plan.

## Test B — cancelled public event banner copy
1. Open a cancelled public event page that has a live replacement event.
2. Confirm the banner title reads **Event Rescheduled**.
3. Confirm the banner body reads exactly: **This event has been rescheduled. Please see updated details below.**
4. Confirm the **View New Date** link still works.

## Test C — diagonal ribbon overlays
1. On that cancelled public event page, confirm the featured image uses a prominent diagonal **Rescheduled** ribbon, not the old small corner pill.
2. Visit the VMS public venue calendar month containing that event.
3. Confirm the cancelled event still appears on the calendar.
4. Confirm its image artwork also shows the diagonal **Rescheduled** ribbon.
5. Open the event popup and confirm the popup image shows the same ribbon.
6. Confirm the popup CTA says **View Details** rather than **Get Tickets** for the cancelled entry.

## Report back
Report PASS or FAIL only, with brief evidence and screenshots only if something is wrong.
