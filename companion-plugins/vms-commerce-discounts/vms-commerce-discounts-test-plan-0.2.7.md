# VMS Commerce Discounts 0.2.7 Test Plan

## Goal

Add a simple checkout tip/gratuity prompt for Express Bar usage without disturbing existing discount rules, ticket logic, add-on qualification, or Square discount bridging.

## Admin setup smoke test

1. Install and activate `vms-commerce-discounts-0.2.7.zip`.
2. Go to **WooCommerce > VMS Discounts**.
3. Confirm the page loads without PHP fatal errors.
4. In **Tips / gratuity**:
   - Enable tips at checkout.
   - Set **Where tips appear** to **Regular product orders only**.
   - Keep label as `Tip / Gratuity`.
   - Keep presets as `1,3,5`.
   - Leave custom tip enabled.
   - Leave taxable tip disabled unless intentionally testing tax behavior.
5. Save settings and confirm values persist after reload.

## Express Bar / regular product checkout test

🚨 **Codex/live-site smoke test recommended before customer use.**

1. Add a normal WooCommerce product intended for Express Bar to the cart.
2. Visit cart or checkout.
3. Confirm the tip prompt appears.
4. Click `$1`, `$3`, or `$5`.
5. Confirm checkout totals refresh and a positive fee line named `Tip / Gratuity` appears.
6. Click **No tip** and confirm the fee line is removed.
7. Enter a custom amount, apply it, and confirm the fee line and order total update.
8. Change cart quantity and confirm the selected tip persists.

## Ticket-only regression test

1. Add only a TEC ticket product to cart.
2. Visit checkout.
3. Confirm the tip prompt does **not** appear when scope is **Regular product orders only**.
4. Confirm ticket quantity controls, subtotal math, and checkout flow are unchanged.

## Discount regression test

1. Use an existing product discount rule from version 0.2.6.
2. Add qualifying products to cart.
3. Confirm the discount still applies as before.
4. Add a tip.
5. Confirm the discount remains negative/discount-style and the tip remains positive/fee-style.
6. Confirm removing the tip does not remove the discount.

## Woo order verification

1. Place a low-value test order if safe.
2. Open the Woo order admin screen.
3. Confirm the order includes a fee line named `Tip / Gratuity`.
4. Confirm the order has `_vms_discounts_tip_amount`, `_vms_discounts_tip_label`, and `_vms_discounts_tip_scope` metadata.
5. Confirm no ticket/add-on quantity, inventory, or VMS discount metadata was created from the tip.

## Square/payment sanity check

🚨 **Square checkout should be tested before live use because this feature changes the order total through a Woo fee line.**

1. With Square enabled, place a small test/live order only if operationally safe.
2. Confirm the card charge equals the Woo order total including tip.
3. Confirm Woo records the tip as a fee line.
4. Confirm VMS/Data Tools can later treat tips separately from ticket/product revenue.

## Expected behavior

- Tips are optional.
- Tips are never discounts.
- Tips do not qualify products, tickets, or add-ons.
- Tips do not affect inventory.
- Tips persist through normal checkout refreshes.
- Tips are cleared after order thank-you flow.

## Known limitation

This first pass targets classic WooCommerce cart/checkout hook rendering. If the site uses the WooCommerce Checkout Block instead of the classic checkout template/shortcode, the server-side fee logic remains reusable but the visual tip selector may need a separate Blocks integration.
