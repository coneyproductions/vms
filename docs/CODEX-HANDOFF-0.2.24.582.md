# CODEX HANDOFF — VMS 0.2.24.582

## Focus
Merge the GA ticket product image repair onto the newer 0.2.24.581 staffing-template per-role threshold build.

## What changed
- Preserved 0.2.24.581 staffing template per-role attendance thresholds.
- Brought forward the ticket image repair from the separate 0.2.24.580 image branch.
- GA ticket image resolution now falls back from:
  1. Event Plan featured image
  2. Linked TEC event featured image
  3. Primary vendor featured image
- **Ticketing Image Tools** now syncs GA ticket products in addition to entitlement / qualified ticket / add-on products.
- Checkout now self-heals GA ticket product images when GA order lines are created.
- Older VMS-linked TEC ticket products can be identified as GA tickets even when missing the newer `product_role` marker.

## Highest-risk areas to test
1. The settings image sync updates existing GA ticket Woo product images without damaging qualified ticket/add-on images.
2. A GA checkout/order line self-heals the product image before customer-facing order/email thumbnail output.
3. Custom ticket images and image mode `none` still behave correctly.
4. 0.2.24.581 staffing template per-role threshold save/apply/replace behavior still works.
5. Legacy VMS-linked TEC ticket products without `_vms_product_role` are handled as GA tickets only when they also have valid Event Plan + TEC ticket markers.

## Notes
- This build exists because the image patch and staffing-template patch happened on separate zips.
- Do not downgrade to 0.2.24.580 after this if the staffing template threshold work is needed.
