# VMS Commerce Discounts 0.2.10 Test Plan

## Purpose

Verify the hotfix for the Woo Cart Block display regression introduced in 0.2.9.

This build should preserve the 0.2.8 four-ticket discount calculation fix while preventing Woo Blocks from showing raw HTML such as `<span class="vms-discounts-block-price...">` in the cart.

## Install

1. Upload `vms-commerce-discounts-0.2.10.zip` on staging first.
2. Confirm the installed plugin shows version `0.2.10`.
3. Open `wp-content/plugins/vms-commerce-discounts/vms-build.txt` and confirm it reports `Version: 0.2.10`.
4. Clear page/object/cache plugin cache before testing the public cart.

## Test 1 — Four-ticket discount still qualifies

1. Empty the cart.
2. Add 4 paid General Admission tickets for a future event.
3. Open `/cart/`.
4. Confirm no raw `<span>`, `<del>`, `<ins>`, or other HTML tags appear as visible text.
5. Confirm the line item or subtotal display includes a plain discount note such as:
   - `was $80.00`
   - `saved $8.00`
   - or the public discount label.
6. Confirm the estimated total reflects the discount before tax.
7. Proceed to checkout and confirm the same discounted math still applies.

Expected example with 4 × $20 tickets and a 10% ticket discount:

- Original ticket line: `$80.00`
- Discounted ticket line: `$72.00`
- Discount amount before tax: `$8.00`

## Test 2 — Six-ticket discount math

1. Change the same cart to 6 paid General Admission tickets.
2. Confirm the cart does not show literal HTML.
3. Confirm the discount is `$12.00` before tax.
4. Confirm checkout shows/charges the discounted amount.

Expected example with 6 × $20 tickets and a 10% ticket discount:

- Original ticket line: `$120.00`
- Discounted ticket line: `$108.00`
- Discount amount before tax: `$12.00`

## Test 3 — Quantity below threshold

1. Change the paid ticket quantity to 3.
2. Confirm no discount applies.
3. Confirm no stale was/saved discount note remains.

## Test 4 — Free/qualified tickets should not unlock paid-ticket discount

1. Empty the cart.
2. Add only free/qualified tickets if available.
3. Confirm the 4-ticket paid-ticket discount does not apply.
4. Add paid tickets below the threshold plus free/qualified tickets.
5. Confirm free/qualified tickets do not incorrectly push the paid-ticket rule over the threshold.

## Test 5 — Classic cart/checkout fallback

If a classic shortcode/template cart is available on staging:

1. Add 4+ paid tickets.
2. Confirm the classic cart still shows the original price struck through and the discounted price beside it.
3. Confirm the cart and checkout math are correct.

## Pass criteria

- No raw HTML appears visibly in Cart Block or Checkout Block item rows.
- 4 paid tickets unlock the configured discount.
- 6 paid tickets produce the correct larger discount.
- Free/qualified tickets do not count as paid tickets for this rule.
- Checkout and cart totals match.

## Rollback

Rollback to `vms-commerce-discounts-0.2.8.zip` if cart/checkout math regresses. Rollback to `0.2.9` is not recommended because it can render raw HTML in the Woo Cart Block.
