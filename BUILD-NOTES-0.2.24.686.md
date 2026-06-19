# VMS 0.2.24.686 — Prior Event Orders Count Toward Add-On Eligibility

## Purpose

This build updates reserved add-on qualification so prior orders for the same event count toward the customer’s add-on allowance instead of only counting tickets currently selected on the page or already in the cart.

## Changes

- Added shared customer purchase-history helpers that look up prior Woo orders for the current shopper by logged-in account and saved billing email.
- Count prior qualifying ticket purchases toward reserved add-on eligibility for the same event plan.
- Count prior purchased add-ons in each entitlement pool toward the same pool limit so repeat orders cannot exceed the event’s configured ticket-to-add-on math.
- Added prior-qualification and prior-pool fields to the reserved add-on render block, inline server-controls state, and `vms_ticketing_v2_cart_context` AJAX response.
- Updated front-end add-on limit math to include prior qualifying purchases and prior pool usage alongside current cart/page selection.
- Updated Woo add-to-cart validation and cart/checkout validation so server enforcement matches the UI.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/integrations/ticketing-rules-v2.php`
- `assets/vms-ticketing-front.js`
- `assets/vms-ticketing-front-fallback.js`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.686.md`
- `vms-test-plan-0.2.24.686.md`

## Validation Performed

- `php -l includes/integrations/ticketing-rules-v2.php`
- `node --check assets/vms-ticketing-front.js`
- `node --check assets/vms-ticketing-front-fallback.js`

## Notes / Caveats

- Prior-order qualification can only be matched when the shopper can be identified from the current session, currently via logged-in user and/or saved Woo billing email context.
- I did not run a full browser repro with seeded returning-customer orders in this local environment, so the manual test plan below should be run on a shopper/account that already has qualifying event tickets.
