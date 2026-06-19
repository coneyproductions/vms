# VMS Test Plan — 0.2.24.524 VMS Native Photo Grid

Test only the new VMS public photo-grid shortcode.

## Goal
Verify that VMS can replace the TEC photo view on a public page with VMS-controlled event cards that honor cancelled/rescheduled overlays.

## Setup
1. Edit the target public page.
2. Replace `[tribe_events view="photo"]` with `[vms_events_photo limit="4"]`.
3. Save/update the page.

## Test
1. Open the public page.
2. Confirm the VMS photo grid renders event cards instead of a shortcode string or empty block.
3. Confirm normal upcoming events render with image, title, and date card.
4. Confirm a cancelled/rescheduled event still appears when it falls within the rendered range.
5. Confirm the cancelled/rescheduled event image shows the diagonal overlay.
6. Click the cancelled/rescheduled card and confirm it opens the expected public event page.
7. Confirm mobile width stacks the cards cleanly in a single column.

## Report
Report PASS or FAIL only, with brief evidence.
