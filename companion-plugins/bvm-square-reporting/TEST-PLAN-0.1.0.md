# BVM Square Reporting 0.1.0 — staging test plan

1. Confirm WooCommerce Square is in SANDBOX mode.
2. Open WooCommerce → BVM Square Reporting and provision the four reporting objects.
3. Confirm all four rows show Ready.
4. Create a mixed order containing a paid online ticket, an event add-on, a BVM rental, a normal Express Bar catalog product, and an Express Bar tip.
5. In Woo order items confirm:
   - ticket → `_bvm_square_reporting_class=online_ticket`
   - event add-on → `online_addon`
   - rental → `rental`
   - tip carrier → `online_tips`
   - normal Express Bar product is not stamped by this add-on
6. Confirm each classified line has `_square_item_variation_id` matching its stable reporting variation.
7. In Square sandbox confirm revenue lands in ONLINE TICKET / ONLINE ADDON / RENTAL / ONLINE TIPS instead of Uncategorized.
8. Confirm normal Square-synced bar products retain their existing Square categories.
