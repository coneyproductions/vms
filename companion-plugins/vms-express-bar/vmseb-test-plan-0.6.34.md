# VMS Express Bar 0.6.34 — staging test plan

1. Complete an Express Bar checkout with a tip.
2. Confirm checkout still shows the existing tip UI and total.
3. In the created Woo order confirm **Bar Staff Tip** is a product line (not a fee line).
4. Confirm the line has `_vmseb_online_tip=yes` and `_vms_discounts_is_tip=yes`.
5. Confirm the hidden carrier product has SKU `VMS-ONLINE-TIP-CARRIER` and role `online_tip`.
6. With BVM Square Reporting provisioned, confirm the line receives class `online_tips` and its stable Square variation ID.
7. Confirm total charged exactly matches the checkout total.
