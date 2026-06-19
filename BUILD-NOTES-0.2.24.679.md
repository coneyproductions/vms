# VMS 0.2.24.679 — Public Sale Price Emphasis Follow-up

## Purpose

This build is a narrow follow-up to `0.2.24.678` after local public-page testing showed the sale badge, strike-through regular price, sale deadline, and limited-ticket ratio guidance working, but the active sale price itself was not visually stronger on hydrated TEC ticket rows.

## Changes

- Adds a sale-active class to public ticket rows when the front-end access map reports `sale_active`.
- Annotates public ticket price containers with `vms-ticket-price-sale-active`.
- Detects both native Woo/TEC `del` / `ins` sale markup and hydrated sibling-span price markup.
- Adds `vms-ticket-regular-price` and `vms-ticket-sale-price` classes so the sale price can be styled reliably after public hydration.
- Extends `assets/css/ticketing-front/95-ticket-public-polish.css` and the compiled `assets/css/vms-ticketing-front.css` so active sale prices are larger/bolder/red while the regular price remains smaller/crossed out.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `assets/vms-ticketing-front.js`
- `assets/css/ticketing-front/95-ticket-public-polish.css`
- `assets/css/vms-ticketing-front.css`
- `BUILD-NOTES-0.2.24.679.md`
- `vms-test-plan-0.2.24.679.md`

## Validation Performed

- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `node --check assets/vms-ticketing-front.js`
- `zip -T VMS_679_ticket_sale_price_emphasis.zip`

## Notes / Caveats

- This is intentionally scoped to the one actionable `B.5` miss from the `0.2.24.678` test pass.
- The existing `0.2.24.678` ratio-limit behavior and add-on guidance logic are unchanged.
