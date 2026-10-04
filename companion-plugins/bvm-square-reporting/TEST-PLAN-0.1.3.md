# BVM Square Reporting 0.1.3 — staging test plan

1. Confirm BVM Square Reporting 0.1.3 is active and WooCommerce Square is in SANDBOX mode.
2. Confirm the existing reporting objects and carrier SKUs are unchanged.
3. Place controlled orders covering:
   - a paid GA ticket;
   - a complimentary ticket;
   - multiple GA tickets;
   - a ticket plus event add-on;
   - normal Express Bar merchandise plus a ticket;
   - a future-event ticket;
   - a Cash App Pay ticket order;
   - a discounted Express Bar plus ticket order that uses the native Square discount bridge.
4. For every ticket/add-on line, confirm Square shows the exact Woo/BVM order-item name and never leaves `ONLINE TICKET` or `ONLINE ADDON` when the canonical Woo item exists.
5. Confirm reporting catalog variation IDs, SKUs, categories, quantities, prices, taxes, discounts, tenders, totals, and normal Express Bar lines are unchanged.
6. Retry one controlled payment/order flow and confirm no duplicate Square order or second name update is created.
7. Confirm no new `bvm-square-reporting` errors appear in WooCommerce logs.
