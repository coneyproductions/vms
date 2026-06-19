# VMS Test Plan — 0.2.24.534

Test only the VMS native photo-grid CTA button pass.

## Goal
Verify that the empty space beside the date badge now contains a clear CTA button without breaking the grid layout.

## Steps
1. Install `vms-0.2.24.534-photo-grid-date-cta.zip`.
2. Open a page using `[vms_events_photo limit="4"]`.
3. Confirm each active event card shows a **Tickets** button beside the date badge.
4. Confirm the button opens the event listing normally.
5. If a rescheduled event appears in the grid, confirm its button reads **View New Date** and goes to the replacement listing.
6. Confirm the cards still look balanced on desktop and do not overflow awkwardly on mobile.

## Report back
Report PASS or FAIL only, with brief evidence.
