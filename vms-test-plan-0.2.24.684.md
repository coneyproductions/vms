# VMS 0.2.24.684 Test Plan — Mobile Progressive Ticketing Responsiveness Follow-Up

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.684`.
3. Confirm `VMS_VERSION` reports `0.2.24.684`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.684`.

Expected: all markers match `0.2.24.684`.

## B. Native Ticket Stepper Responsiveness on Mobile Chrome

Use a public progressive ticket event such as `American Petty: A Tom Petty Experience`.

1. Open the event page in Chrome mobile.
2. Tap the native ticket `+` once on a row that starts at `0`.
3. Confirm the qty changes from `0` to `1` without repeated tapping.
4. Tap `-` once and confirm the qty returns to `0`.

Expected: mobile taps feel immediate, and the stepper does not wait for a noticeably delayed synthesized click before reflecting the quantity change.

## C. Progressive Add-Ons Header Activation

1. Load the same event page in Chrome mobile.
2. Tap the `Fire Pits & Tables` header on the title line.
3. Collapse it.
4. Tap again on the descriptive subtext line instead of the title line.

Expected: the add-ons section expands/collapses from either part of the header without requiring repeated taps.

## D. Mobile Touch Target Size

1. Open the event page in mobile emulation or on a real phone.
2. Inspect the native ticket qty `+` / `-` buttons and the qty input.
3. Confirm the mobile progressive controls render at the larger touch size instead of the old `38px` layout.

Expected: native progressive ticket steppers render at `44px` control size with a matching taller qty input.

## E. Staging Page-Level Error Follow-Up

1. Open browser devtools on the staging event page.
2. Reload the page.
3. Check the console for `TICKETS_SEL is not defined`.

Expected: if that error is still present, treat it as a separate page-level custom-script defect outside the VMS bundle and correct/remove that injected script independently.

## F. Syntax Checks

Run:

```bash
node --check assets/vms-ticketing-front.js
node --check assets/vms-ticketing-progressive-ui.js
node --check assets/vms-ticketing-front-server-controls.js
```

Expected: all commands pass.
