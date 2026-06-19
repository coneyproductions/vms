# VMS Test Plan — 0.2.24.641 Ticket Quantity / Checkout Hotfix

## Scope
This patch is targeted at day-of customer purchase friction where normal ticket quantities are not cooperating in cart/checkout.

## What changed
- Public/general ticket `max_qty_per_order` values are no longer treated as VMS customer caps by default.
- Verified/login/registered-guest tickets still use VMS allowance and assignment enforcement.
- Classic cart quantity stabilization no longer blocks Woo/theme quantity listeners; it only remembers the intended final value and reconciles stale snapbacks.

## Required staging checks

### 1. Version markers
- Confirm `vendor-management-system.php`, `includes/core/registry/constants.php`, and `vms-build.txt` all show `0.2.24.641`.

### 2. Public/GA quantity is not capped by VMS
- Open a public event with a normal paid GA ticket.
- Add 6+ GA tickets to cart.
- Confirm VMS does **not** show `Limit reached for this event` for normal/public tickets.
- Proceed to checkout and confirm the Place Order button is not blocked by a VMS ticket limit notice.

### 3. Reduce cart quantity before checkout
- Start with 6 GA tickets in the cart.
- Reduce to 4.
- Confirm the cart total and line quantity settle at 4 and do not snap back to 5 or 6.
- Refresh the cart page and confirm the line quantity remains 4.

### 4. Increase cart quantity before checkout
- Increase the same GA ticket line from 4 to 7 or more.
- Confirm the cart total and line quantity settle at the intended quantity.
- Confirm checkout remains available unless Woo/TEC inventory is actually exhausted.

### 5. Verified/free registered tickets still enforce rules
- Try adding a verified/free ticket without the required approved account/guest assignment.
- Confirm VMS still blocks the restricted ticket with the appropriate registration/guest message.
- With an approved account/guest, confirm the allowed quantity can be added up to the real allowance.

### 6. Add-ons still qualify from GA quantity
- Add the required number of GA tickets for a fire pit/table.
- Confirm the add-on can be selected.
- Reduce GA below the requirement and confirm the add-on qualification warning still appears.

### 7. Checkout validation recovery regression
- On checkout, intentionally trigger a native validation error such as missing payment details or unchecked required challenge/terms field.
- Correct the missing field.
- Confirm Place Order re-enables when no VMS cart blocker remains.

## Notes
- Woo/TEC inventory should still prevent overselling. This patch only removes VMS' accidental customer cap behavior for public tickets.
- If a future venue intentionally wants per-customer caps for public tickets, use the `vms_ticketing_v2_should_enforce_ticket_max_qty` filter.
