# Codex Handoff — VMS 0.2.24.741

## What changed

- Added capped Early Bird pricing to VMS Ticketing V2.
- Early Bird price state now evaluates the regular price, early price, optional start/end dates, optional cap, net sold quantity, and refund-adjusted remaining sale quantity.
- Capped Early Bird sales can expire by deadline, cap exhaustion, or whichever condition happens first.
- Cap-only Early Bird pools are supported when no hard end date is configured.
- Public sale copy can show capped sale availability separately from total ticket inventory.
- Total ticket availability now has its own global/event display controls so large remaining capacity can be hidden while capped sale urgency remains visible.
- The TEC `You have X Tickets for this Event` notice is corrected/hidden using refund-aware active ticket counts for the current logged-in customer.

## Settings added

Global VMS settings:

- `ticket_ui_availability_display`
  - `always`
  - `low`
  - `hide`
- `ticket_ui_availability_low_threshold`
- `ticket_ui_sale_availability_display`
  - `when_capped`
  - `low`
  - `hide`
- `ticket_ui_sale_availability_low_threshold`

Event Plan overrides:

- `_vms_ticket_ui_availability_display_override`
- `_vms_ticket_ui_sale_availability_display_override`

Ticket config field:

- `early_price_cap`

## Runtime notes

- Sold quantity for cap evaluation is based on Woo line items minus refund line items when the order-item tables are available.
- Product lookup totals remain as fallback only.
- Product price filters re-evaluate Early Bird cap state at runtime so stale product meta does not keep a sold-out Early Bird price active.
- Add-to-cart/cart/checkout guards block carts that exceed the remaining active Early Bird quantity while any discounted quantity still remains.
- When Early Bird quantity is fully exhausted, the ticket is allowed to continue at regular price after refresh instead of being blocked as sold out.

## Verification performed locally

- `php -l` passed for:
  - `includes/integrations/ticketing-phase-b.php`
  - `includes/integrations/ticketing-rules-v2.php`
  - `includes/admin/settings-page.php`
  - `includes/cpt/event-plans.php`
  - `includes/cpt/event-plans/partials/ticketing-v2.php`
- `node --check` passed for:
  - `assets/admin-ticketing.js`
  - `assets/vms-ticketing-front.js`

## Packaging note

- Package name: `vms-0.2.24.741-early-bird-caps-sale-availability-refund-count.zip`
