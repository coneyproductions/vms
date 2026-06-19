# VMS 0.2.24.638 — Cart Quantity Responsiveness Hotfix Test Plan

🚨 **Codex/staging test required before production.** This is a customer-facing cart hotfix. If Codex makes any code changes during testing, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, test plan, and package filename before returning a replacement zip.

## Purpose

Fix the WooCommerce cart quantity experience where changing ticket quantity briefly flashes the requested value, snaps back to the previous value, and only settles several seconds later. The patch removes VMS's broad cart-page MutationObserver/polling from the Woo cart update path and delays VMS cart-blocker refreshes until explicit cart actions / Woo completion events.

## Files touched

- `assets/vms-ticketing-front.js`
- `includes/integrations/ticketing-rules-v2.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`

## Version checks

1. Confirm the plugin header reports `0.2.24.638`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.638`.
3. Confirm `vms-build.txt` begins with `0.2.24.638`.

## Cart quantity regression

1. On staging, add 4 public event tickets to the cart.
2. On the cart page, increase quantity from 4 to 5.
3. Confirm the control does not visibly snap back to 4 and sit there for several seconds. A normal Woo updating/loading state is acceptable; a confusing stale quantity rollback is not.
4. Reduce quantity back from 5 to 4, then to 3. Confirm the same behavior.
5. Repeat with a mixed cart containing event tickets plus an eligible VMS add-on such as a fire pit/table reservation.
6. Repeat with a regular non-ticket Woo product if available; VMS should not add noticeable delay.

## Network / race check

1. Open DevTools Network on the cart page.
2. Reload the cart. Confirm `admin-ajax.php?action=vms_ticketing_v2_cart_context` is not fired immediately by VMS on page load solely because the cart rendered.
3. Change cart quantity once. Confirm VMS does not fire a burst of cart-context requests during Woo's own update. At most one delayed VMS cart-context request after the cart action is acceptable.
4. Confirm Woo's own cart update request still completes and totals update correctly.

## VMS blocker regression

1. Create or reproduce an invalid VMS ticket/add-on combination, such as a reservation/add-on without enough qualifying tickets.
2. Confirm the cart/checkout still shows the VMS blocker message and prevents checkout.
3. Correct the cart quantity/add-on combination. Confirm checkout becomes available after Woo finishes updating or after a refresh.

## Checkout recovery regression

1. On checkout, trigger a normal native validation error such as missing payment/customer details or Turnstile if available.
2. Correct the field/challenge.
3. Confirm the Place Order button is not permanently disabled when VMS has no cart blockers.

## Rollback

If cart quantity behavior is worse, roll back to `0.2.24.637` immediately and capture: browser, cart type/classic vs block cart, Network requests around the quantity update, console errors, and whether the cart contains only tickets, tickets + add-ons, or regular products.
