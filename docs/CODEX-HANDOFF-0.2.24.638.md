# CODEX HANDOFF — VMS 0.2.24.638

🚨 **Staging/customer-cart test required.**

## Summary

This hotfix targets the live SR.com cart quantity delay/snapback report. Customers changing ticket quantities saw the new value flash, revert to the previous value, and only settle several seconds later.

## Expected root cause

VMS's cart/checkout blocker bundle was running the checkout/cart guard path on cart pages with a broad `MutationObserver` and immediate `vms_ticketing_v2_cart_context` AJAX fetch. Woo cart quantity updates also mutate/re-render the cart. The VMS guard could therefore add extra admin-ajax requests and checkout-blocker scans while Woo was trying to process the cart update, making the cart feel broken.

## Changes

- Cart pages no longer start the broad checkout MutationObserver.
- Cart pages no longer call the VMS cart-context endpoint immediately on boot.
- Cart pages refresh VMS blocker state only after explicit cart quantity/remove/update actions and known Woo completion events.
- Cart refresh waits if Woo appears busy/loading.
- The cart-context endpoint now computes checkout blocker messages once instead of twice.
- The endpoint skips heavier blocker scans when the cart contains no VMS ticket/add-on lines.

## Files changed

- `assets/vms-ticketing-front.js`
- `includes/integrations/ticketing-rules-v2.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `vms-test-plan-0.2.24.638.md`
- `docs/test-plan-0.2.24.638-cart-quantity-responsiveness.md`

## Required testing

Run `vms-test-plan-0.2.24.638.md`. Pay special attention to the cart quantity UI and Network tab. This patch should reduce or remove VMS-side request racing; if Woo still snaps back for several seconds with no VMS cart-context burst, capture the Woo/Store API request timing and cart block/classic details for the next pass.
