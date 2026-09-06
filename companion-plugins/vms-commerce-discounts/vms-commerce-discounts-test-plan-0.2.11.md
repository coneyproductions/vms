# VMS Commerce Discounts 0.2.11 Test Plan

## Goal

Confirm the four-ticket discount calculation still works, while the cart display no longer prints raw HTML or wordy "was/saved/group discount" text in Woo Cart Blocks.

## Install

1. Upload `vms-commerce-discounts-0.2.11.zip` on staging first.
2. Confirm the plugin reports version `0.2.11`.
3. Open `wp-content/plugins/vms-commerce-discounts/vms-build.txt` and confirm `Version: 0.2.11`.
4. Clear page/object/cache layers that can affect the cart page.

## Primary ticket discount test

1. Empty the cart.
2. Add 3 paid General Admission tickets for one event.
3. Confirm no four-ticket discount applies.
4. Change quantity to 4.
5. Confirm checkout shows the discounted ticket subtotal. For four $20 tickets with 10% off, the ticket subtotal should be $72 before tax.
6. Change quantity to 6.
7. Confirm checkout shows a $12 discount on the ticket line, or a $108 ticket subtotal before tax.

## Cart page display check

1. Open the Woo cart page after the same 4-ticket and 6-ticket tests.
2. Confirm there is no raw HTML printed, such as `<span>`, `<del>`, or `<ins>`.
3. Confirm there is no wordy block-cart text such as `was`, `saved`, or `Group Discount` injected by the VMS block filter.
4. If the cart total updates to the discounted amount, confirm the estimated total matches checkout.
5. If the cart total still shows the undiscounted amount but checkout is correct, do not use cart-page express payment buttons for discounted ticket orders until a deeper Store API totals patch is built and tested.

## Classic template display check, if available

1. Test a classic shortcode cart/checkout page if one exists.
2. Confirm discounted items show only the original price struck through and the discounted price next to it.
3. Confirm there is no extra `Saved...` or discount label story text on the item row.

## Free/qualified ticket safety

1. Add a free/qualified ticket line that should not count toward paid-ticket discounts.
2. Confirm the free/qualified line does not unlock or receive the paid four-ticket discount by itself.
3. Mix free/qualified and paid tickets.
4. Confirm only paid ticket quantities/subtotals drive the paid-ticket discount.

## Regression checks

1. Confirm unrelated products such as eggs or shirts do not unlock the ticket-count rule unless a separate rule intentionally targets them.
2. Confirm existing active product discount rules still apply to their selected products.
3. Proceed through checkout far enough to verify the final order total reflects the discount before payment.
