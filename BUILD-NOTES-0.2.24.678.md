# VMS 0.2.24.678 — Public Ticket Sale + Limited Ticket Guidance Polish

## Purpose

This build polishes the public Ticketing V2 / Progressive Ticket UI for event ticket mixes like youth tickets, children's tickets, qualified/free tickets, and sale-priced General Admission.

The main customer-facing goals are:

- Make limited/ratio tickets explain their requirement before the customer tries to buy too many.
- Make the public sale state more obvious.
- Show the sale end date/time so customers know sale pricing is temporary.
- Preserve the existing server-side ticket/add-on enforcement from earlier builds.

## Changes

### Public limited-ticket ratio guidance

- Exposes ticket ratio-rule metadata to the public ticket front-end for each ticket row.
- Adds an inline requirement note on ratio-limited ticket rows such as youth/child tickets.
- Counts current cart quantity and selected qualifying ticket quantity before add-to-cart.
- Clamps the visible quantity field to the currently allowed quantity when the customer over-selects.
- Disables the row plus button when the current selection is already at the allowed quantity.
- Uses the Event Plan's configured qualifying-ticket label when available, falling back to `qualifying tickets`.

Example behavior:

- With 0 qualifying tickets selected, a ratio-limited ticket row shows the requirement and blocks increasing beyond 0.
- With 4 qualifying tickets selected and a 1:1 rule, the row allows up to 4 limited tickets.
- With shared ratio groups, limited rows continue to draw from the same shared allowance pool.

### Sale display polish

- Promotes the `On Sale` badge to a larger, more visible pill.
- Adds a visible sale deadline line under/near the badge when early-sale end metadata is available.
- Makes the sale price visually stronger while keeping the original crossed-out price visible.
- Adds source CSS module `assets/css/ticketing-front/95-ticket-public-polish.css` and updates the compiled/enqueued `assets/css/vms-ticketing-front.css`.

### Public data exposure

The front-end ticket access map now includes safe display metadata needed by the public UI:

- `counts_toward_unlock`
- `ratio_rule_enabled`
- `ratio_rule_max_per_qualifying`
- `ratio_rule_qualifier_mode`
- `ratio_rule_group`
- `regular_price`
- `early_price`
- `early_price_start`
- `early_price_end`
- `sale_active`

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/integrations/ticketing-rules-v2.php`
- `assets/vms-ticketing-front.js`
- `assets/css/ticketing-front/95-ticket-public-polish.css`
- `assets/css/vms-ticketing-front.css`
- `BUILD-NOTES-0.2.24.678.md`
- `vms-test-plan-0.2.24.678.md`

## Validation Performed

- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `php -l includes/integrations/ticketing-rules-v2.php`
- `node --check assets/vms-ticketing-front.js`
- `zip -T VMS_678_ticket_public_ui_polish.zip`

## Notes / Caveats

- This package was syntax-checked and packaged here, but it was not live-browser tested against the production Taylor Swift event page from this environment.
- The customer-facing add-to-cart/cart/checkout guards remain the final source of enforcement; the new UI guidance is a friendlier front-end guard to prevent avoidable customer confusion before submission.
