# VMS 0.2.24.650 — Disabled Ticket Public UI Guard

## Problem

VMS 0.2.24.649 correctly blocked disabled-but-not-yet-pushed ticket products at add-to-cart/cart/checkout, but the public event page could still show stale TEC/Woo quantity controls after an operator disabled a qualified/free ticket and clicked **Save Config** without pushing ticket changes.

That left a confusing and dangerous public UI state: the disabled qualified/free ticket could still appear selectable until the public ticket sync retired it.

## Fix

This build extends the 0.2.24.649 fail-closed protection into the public ticket UI:

- Builds a public runtime map of saved-config-disabled ticket rows and their last-pushed Woo/TEC product IDs.
- Localizes that map to the public ticket scripts as `disabledTicketProductIds` and `disabledTicketMap`.
- Strips matching disabled TEC ticket rows from the server-rendered public event HTML where possible before the VMS add-on mount runs.
- Updates the Progressive/VMS frontend controller to zero, disable, hide, and ignore disabled pending-sync ticket rows before building ticket state.
- Updates server-controls and fallback frontend controllers with the same hidden/disabled pending-sync ticket guard.
- Keeps the 0.2.24.649 server-side add-to-cart/cart/checkout disabled-ticket blockers in place.

## Expected behavior

If an operator disables a qualified/free ticket and clicks **Save Config**, but does not push ticket changes yet:

- The stale public ticket row should not remain visibly selectable.
- If a stale row briefly exists in the DOM, its quantity is reset to `0`, controls are disabled, and the row is hidden.
- Disabled rows no longer contribute to ticket totals, add-on eligibility, or atomic add payloads.
- Any direct add-to-cart attempt still fails closed server-side.

## Files changed

- `includes/integrations/ticketing-rules-v2.php`
- `assets/vms-ticketing-front.js`
- `assets/vms-ticketing-front-server-controls.js`
- `assets/vms-ticketing-front-fallback.js`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`

## Validation performed

- `php -l includes/integrations/ticketing-rules-v2.php`
- Full plugin PHP lint pass
- `node --check assets/vms-ticketing-front.js`
- `node --check assets/vms-ticketing-front-server-controls.js`
- `node --check assets/vms-ticketing-front-fallback.js`

## Not yet performed

No live staging browser test was performed in this build environment.
