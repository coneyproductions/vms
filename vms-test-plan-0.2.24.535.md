# VMS Test Plan — 0.2.24.535

Test only the VMS native photo-grid symmetry cleanup.

## Goal
Verify that the photo-grid cards now use a centered vertical stack and calmer CTA behavior.

## Steps
1. Install `vms-0.2.24.535-photo-grid-centered-cta.zip`.
2. Open a page using `[vms_events_photo limit="4"]`.
3. Confirm each active event card is laid out as: title, centered date badge, then centered **Get Tickets** button.
4. Confirm the button is slightly larger than before and does not change font color on hover.
5. Confirm any rescheduled card still shows **View New Date** and routes correctly.
6. Confirm the grid still looks balanced on desktop and mobile.

## Report back
Report PASS or FAIL only, with brief evidence.
