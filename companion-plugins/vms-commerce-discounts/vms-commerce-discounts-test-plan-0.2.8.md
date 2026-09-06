# VMS Commerce Discounts 0.2.8 Test Plan

## Goal

Verify that paid ticket-count discounts apply reliably when WooCommerce cart line subtotals have not yet been populated, while free/comped ticket lines do not qualify for or absorb the paid-ticket discount.

## Install / version check

1. Upload and activate `vms-commerce-discounts-0.2.8.zip` on staging first.
2. Confirm the plugin shows version `0.2.8`.
3. Open `wp-content/plugins/vms-commerce-discounts/vms-build.txt` and confirm it reports `Version: 0.2.8`.

## Four-ticket discount smoke test

1. Go to **WooCommerce > VMS Discounts**.
2. Confirm the four-ticket rule is active:
   - Unlock type: `Ticket count`
   - Required quantity: `4`
   - Discount type: `Percent off`
   - Amount: `10`
   - Applies to: `Tickets only`
   - Max applications per order: `1`
3. As a logged-out or incognito shopper, add 4 paid General Admission tickets to the cart for a future event.
4. Confirm the ticket subtotal is discounted by 10%. Example: 4 × $20 = $80 before discount, expected discount = $8.
5. Increase the same ticket line to 6. Confirm 6 × $20 = $120 before discount, expected discount = $12.
6. Refresh the cart and confirm the discount remains applied.
7. Proceed to checkout and confirm the discounted total carries forward.

## Cart Blocks / modern cart check

1. Repeat the four-ticket test on the live cart page layout used by the site.
2. Confirm the **estimated total** reflects the discount.
3. If the discount does not display as its own visible line in the Cart Block, verify the arithmetic still reflects the reduced price. This patch targets discount application; block-specific display rows can be handled separately if needed.

## Free / qualified ticket guardrail

1. Add a free or qualified ticket line that should be $0.00.
2. Confirm that free line does not count toward the 4 paid-ticket threshold.
3. Confirm a discount is not allocated onto the free line.
4. Add 4 paid GA tickets alongside the free line and confirm only the paid ticket subtotal receives the discount.

## Mixed cart regression

1. Add 4+ paid event tickets plus a regular WooCommerce product such as eggs or a shirt.
2. Confirm the four-ticket rule discounts tickets only.
3. Confirm regular products remain full price unless a separate product rule applies.

## Tips regression

1. If checkout tips are enabled, confirm the tip prompt still behaves as it did in 0.2.7.
2. Confirm tips remain a positive fee line and do not affect ticket counts or discount qualification.

## Expected result

- 4+ paid tickets trigger the configured ticket-count discount.
- Free/comped lines do not qualify for or absorb the discount.
- The cart total and checkout total remain consistent after refresh, quantity changes, and checkout navigation.
