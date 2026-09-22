# BVM Square Reporting 0.1.2 — staging test plan

1. Confirm BVM Square Reporting 0.1.2 is active on staging and WPCode snippet 6001 is inactive.
2. Confirm the existing `online_ticket` reporting object still has the staging variation and `Online Ticket` category configured before checkout.
3. Place a controlled staging order containing at least one BVM ticket and, if available, one normal Square catalog concession item.
4. Record the Woo order ID, exact ticket line name, quantities, prices, taxes, discounts, and order total.
5. Inspect the actual Square order created by WooCommerce Square and confirm:
   - the ticket line name exactly matches the Woo/BVM order-item name;
   - the ticket retains the stable `online_ticket` catalog variation and `Online Ticket` reporting category;
   - ticket quantity, price, tax, discount, and totals match the Woo order;
   - the normal concession control line is unchanged.
6. Confirm no new `bvm-square-reporting` errors appear in WooCommerce logs.
7. Do not deploy to production until this actual-Square-order gate passes and a separate production go-live is explicitly authorized.
