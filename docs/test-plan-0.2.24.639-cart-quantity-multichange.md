# VMS 0.2.24.639 — Multi-Step Cart Quantity Hotfix Test Plan

🚨 **Codex/staging test required before production.** This is a customer-facing cart hotfix. If Codex makes any code changes during testing, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, test plan, and package filename before returning a replacement zip.

## Purpose

Fix the remaining cart quantity failure where changing quantity by one step works, but two quick reductions/increases can be refused or overwritten. This pass keeps the 0.2.24.638 VMS cart-context polling reduction and adds a classic Woo cart quantity stabilizer that remembers the customer’s last intended quantity, waits for the cart to stop processing, and submits/reapplies the settled quantity if a stale Woo response snaps the field back. It also fixes VMS ticket max-quantity validation so a cart exactly at the allowed limit is not treated as blocked.

## Files touched

- `assets/vms-ticketing-front.js`
- `includes/integrations/ticketing-rules-v2.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`

## Version checks

1. Confirm the plugin header reports `0.2.24.639`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.639`.
3. Confirm `vms-build.txt` begins with `0.2.24.639`.

## Primary reproduction test

1. On staging, reproduce the exact customer/operator case: add 6 tickets to the cart where the intended final quantity is 4.
2. On the cart page, click the quantity decrease control twice or manually change the input from 6 to 4.
3. Confirm the cart settles at 4 without returning to 6 or 5.
4. Confirm totals update for 4 tickets.
5. Confirm checkout is available if 4 is the allowed/valid quantity.

## Rapid quantity regression

1. Add 4 public event tickets to the cart.
2. Increase 4 → 5 → 6 quickly. Confirm the final settled quantity is 6.
3. Reduce 6 → 5 → 4 quickly. Confirm the final settled quantity is 4.
4. Repeat by typing directly into the quantity input rather than using plus/minus controls.
5. Repeat with a mixed cart containing event tickets plus an eligible VMS add-on such as a fire pit/table reservation.
6. Repeat with a regular non-ticket Woo product if available; VMS should not make normal cart changes worse.

## At-limit validation regression

1. Use a ticket with a known max quantity limit, such as 4 per customer/order.
2. Put more than the allowed quantity in the cart if possible, then reduce it to exactly the allowed quantity.
3. Confirm VMS no longer shows a blocker merely because the cart is exactly at the limit.
4. Increase above the limit again. Confirm VMS still shows the appropriate limit blocker.

## Network / race check

1. Open DevTools Network on the cart page.
2. Perform the 6 → 4 reduction.
3. Confirm Woo may perform normal cart update requests, but VMS does not fire a burst of cart-context requests during the quantity update.
4. Confirm the final Woo cart update request contains/saves the intended final quantity.

## VMS blocker regression

1. Create or reproduce an invalid VMS ticket/add-on combination, such as a reservation/add-on without enough qualifying tickets.
2. Confirm the cart/checkout still shows the VMS blocker message and prevents checkout.
3. Correct the cart quantity/add-on combination. Confirm checkout becomes available after Woo finishes updating or after a refresh.

## Checkout recovery regression

1. On checkout, trigger a normal native validation error such as missing payment/customer details or Turnstile if available.
2. Correct the field/challenge.
3. Confirm the Place Order button is not permanently disabled when VMS has no cart blockers.

## Rollback

If cart quantity behavior is worse, roll back to `0.2.24.638` or `0.2.24.637` immediately and capture: browser, cart type/classic vs block cart, Network requests around the quantity update, console errors, whether the cart contains only tickets, tickets + add-ons, or regular products, and whether the final intended quantity was changed by clicking controls or typing directly.
