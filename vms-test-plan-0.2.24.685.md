# VMS 0.2.24.685 Test Plan — Qualified Ticket Progressive Mobile Follow-Up

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.685`.
3. Confirm `VMS_VERSION` reports `0.2.24.685`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.685`.

Expected: all markers match `0.2.24.685`.

## B. Qualified Ticket Row Reveal on Mobile Chrome

Use a public progressive ticket event with qualified rows, such as `American Petty: A Tom Petty Experience`.

1. Open the event page in Chrome mobile.
2. Tap the `+` button on `Veteran Admission` once.
3. Wait briefly for the native qty to settle if needed.
4. Confirm the row now shows the login/register/help stack instead of keeping it hidden.

Expected: when the qualified row quantity becomes `1`, the row gains its selected state and the helper block becomes visible.

## C. Qualified Ticket More-Info Disclosure

1. On the same qualified ticket row, tap `Click here for more info.`.
2. Confirm the disclosure opens.
3. Tap it again.

Expected: the disclosure toggles open/closed on mobile taps and keeps `aria-expanded` in sync with the open state.

## D. Native Qty Retry Timing

1. Tap a native qualified-ticket `+` button once on mobile.
2. Confirm the progressive qualified-row state updates even if the native qty change lands slightly after the first click/tap event.

Expected: the progressive watcher follow-up retries keep the qualified-row state in sync with late native qty updates.

## E. Page-Level Staging Error Follow-Up

1. Reload the staging event page with browser devtools open.
2. Check the console for `TICKETS_SEL is not defined`.

Expected: if the error is still present, treat it as a separate page-level custom-script defect outside the VMS bundle.

## F. Syntax Checks

Run:

```bash
node --check assets/vms-ticketing-front.js
node --check assets/vms-ticketing-progressive-ui.js
```

Expected: all commands pass.
