# CODEX HANDOFF — VMS 0.2.24.639

🚨 **Staging/customer-cart test required.**

## Summary

This is the follow-up hotfix to 0.2.24.638. The first cart patch improved one-step quantity changes, but the real-world case still failed when the cart had two extra tickets and the customer/operator reduced quantity twice to reach the desired valid quantity.

## Updated diagnosis

There are two likely contributors:

1. Classic Woo/theme cart auto-updates can submit the first quantity change while the customer is still clicking. A stale first response can then re-render the cart and overwrite the second intended quantity.
2. VMS ticket max-quantity cart validation treated a cart exactly at the allowed limit as still blocked because it checked `remaining <= 0`, not just `cart_qty > allowed`. That is wrong for at-limit carts: exactly 4 of 4 should be valid; only 5+ should block.

## Changes

- Adds a classic Woo cart quantity stabilizer in `assets/vms-ticketing-front.js`.
- Remembers the customer’s latest intended quantity per cart line.
- Debounces classic cart quantity changes and clicks the Woo Update Cart button once the cart is no longer busy.
- Reapplies the intended quantity and resubmits if a stale Woo refresh snaps the input back.
- Preserves the 0.2.24.638 behavior that avoids broad VMS MutationObserver polling on cart pages.
- Fixes `vms_ticketing_v2_enforce_ticket_max_qtys_in_cart()` so exactly-at-limit carts are not blocked.

## Files changed

- `assets/vms-ticketing-front.js`
- `includes/integrations/ticketing-rules-v2.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `vms-test-plan-0.2.24.639.md`
- `docs/test-plan-0.2.24.639-cart-quantity-multichange.md`

## Required testing

Run `vms-test-plan-0.2.24.639.md`. The highest-priority test is the exact case: cart has 6 tickets, desired final quantity is 4, reduce twice or type 4 directly, and confirm the cart settles at 4 with checkout available.

Do not invoke unrelated live-changing actions during testing. If any code changes are required during testing, bump version/build notes/package filename again before returning the revised zip.
