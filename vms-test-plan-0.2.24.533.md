# VMS Test Plan — 0.2.24.533

Test only the VMS native photo-grid polish on the latest baseline.

## Preconditions
1. Install `vms-0.2.24.533-photo-grid-polish-reapply.zip`.
2. A public page already uses `[vms_events_photo limit="4"]` or `[vms_events_photo_grid limit="4"]`.
3. At least one upcoming event and one rescheduled event are available to render in the grid.

## Test
1. Open the public page with the VMS photo grid.
2. Confirm the cards render normally with no PHP warnings or broken layout.
3. Confirm cards no longer have excessive empty white space beneath short titles.
4. Confirm card heights feel tighter and more compact than the older tall white-box layout.
5. Confirm the date badge looks styled/polished, not like a plain block.
6. Confirm the rescheduled event still shows its diagonal ribbon.
7. Confirm the rescheduled status note renders as a pill-style line under the date/title area.
8. On desktop, hover a card and confirm there is a subtle lift/hover polish.
9. On mobile or narrow width, confirm the cards stack cleanly and remain compact.

## Report back
Report PASS or FAIL only, with brief evidence.
