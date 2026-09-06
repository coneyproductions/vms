# VMS Commerce Discounts 0.2.9 Test Plan

## Purpose

Verify that the 4-ticket discount still calculates correctly and that cart-line discount presentation is visible again before checkout.

## Setup

- Install `vms-commerce-discounts-0.2.9.zip` on staging.
- Confirm WooCommerce > VMS Discounts has the four-ticket rule active:
  - Unlock after 4 tickets
  - 10% off tickets
  - Maximum applications per order: 1
  - Customer-facing label: Group Discount

## Tests

### 1. Cart Block display

1. Open a public event that has a paid General Admission ticket.
2. Add 4 GA tickets to cart.
3. Open `/cart/`.
4. Confirm the cart total reflects the discount.
5. Confirm the ticket row visibly shows the original price struck through and the discounted price/discount label.
6. Increase the quantity to 6.
7. Confirm the discount remains 10% of the ticket line, not a second stacked discount, when the rule is capped at one application.

Expected for 6 × $20 GA tickets:

- Original pre-tax ticket line: $120.00
- Discount: $12.00
- Discounted pre-tax ticket line: $108.00

### 2. Checkout display

1. Proceed to checkout from the same cart.
2. Confirm the discount still applies.
3. Confirm checkout item/order summary display does not remove or double-apply the discount.

### 3. Free/qualified ticket protection

1. Add a free/qualified ticket line that is actually priced at $0.00 in the cart.
2. Confirm it does not count toward the paid-ticket rule.
3. Confirm it does not receive part of the 10% paid-ticket discount.

### 4. Non-block fallback

If a classic Woo cart template is available, repeat the cart test there and confirm the classic item price/subtotal filters show the same struck-through original price and adjusted price.

## Rollback

Rollback to 0.2.8 if cart/checkout display causes a frontend rendering issue. The calculation fix from 0.2.8 should remain the minimum safe baseline.
