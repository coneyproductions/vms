# VMS 0.2.24.683 Test Plan — Mobile Chrome Ticketing Touch/Submit Hardening

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.683`.
3. Confirm `VMS_VERSION` reports `0.2.24.683`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.683`.

Expected: all markers match `0.2.24.683`.

## B. Native Ticket Quantity on Mobile Chrome

Use a public event with live TEC ticket rows such as `Double Vision HTX`.

1. Open the event page in Chrome mobile.
2. Tap the native ticket `+` once on a row that starts at `0`.
3. Repeat on the left/right edges of the same `+` button rather than only the center.
4. Tap the native ticket `-` once after the row reaches `1`.

Expected: single taps reliably change the ticket quantity without requiring repeated tapping.

## C. Progressive Add-Ons Accordion on Mobile Chrome

Use a public event with the progressive ticket UI enabled and visible add-ons.

1. Load the event page in Chrome mobile.
2. Without adding a ticket yet, tap the add-ons accordion header once.
3. Repeat with taps near the text and near the edge of the header.
4. Collapse and re-open the section once more.

Expected: the section expands and collapses on the first tap even when add-on purchase rules still require a qualifying ticket for selection.

## D. Atomic Submit Without Cart-Context Stall

1. On a ticket-only event selection, choose one ticket and submit.
2. Confirm the button does not remain stuck on `Adding...` while waiting for `cart_context`.
3. On an event that includes add-ons, choose a qualifying ticket and one add-on.
4. Submit again.

Expected: ticket-only submits proceed directly to atomic add-to-cart, and add-on submits tolerate a slow `cart_context` response instead of hanging indefinitely.

## E. Syntax Checks

Run:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
php -l includes/integrations/ticketing-rules-v2.php
node --check assets/vms-ticketing-front.js
node --check assets/vms-ticketing-progressive-ui.js
node --check assets/vms-ticketing-front-server-controls.js
```

Expected: all commands pass.
