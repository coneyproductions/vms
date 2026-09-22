BVM Square Reporting
Version 0.1.2

Purpose
-------
Prevents BVM online-only commerce revenue from collapsing into Square's Uncategorized bucket while keeping event-specific ticket/add-on/rental products out of normal Square catalog sync.

Reporting classes
-----------------
- ONLINE TICKET
- ONLINE ADDON
- RENTAL
- ONLINE TIPS

How it works
------------
The add-on provisions one stable variable-price Square catalog variation per reporting class. During Woo checkout it stamps the appropriate reporting variation ID onto eligible BVM order items only. Woo/BVM remain the source of event detail and price.

After WooCommerce Square creates the Square order, the add-on restores each eligible line's exact Woo/BVM order-item name with a narrow uid/name update. The stable catalog reporting object, category, quantity, price, tax, discounts, refunds, and unrelated Square lines remain unchanged.

Setup
-----
1. Install and activate on staging.
2. Open WooCommerce > BVM Square Reporting.
3. Confirm SANDBOX is shown.
4. Click "Ensure / Repair Square Reporting Objects".
5. Run a mixed checkout and inspect the Woo order item metadata / Square sandbox order.

0.1.1
-----
- Makes the final checkout stamping pass idempotent for product lines added late in checkout (including the Express Bar tip carrier).
- Prevents duplicate Square reporting meta rows while preserving the same reporting behavior.

0.1.2
-----
- Restores exact Woo/BVM order-item names on classified Square order lines after order creation.
- Keeps the stable Square reporting variation/category attached and leaves totals and unrelated lines untouched.
- Fails closed on missing or mismatched catalog IDs and logs Square update failures without interrupting checkout.
