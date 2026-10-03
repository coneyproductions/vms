# VMS Express Bar 0.6.5 Test Plan

🚨 Codex / browser testing recommended because this pass changes public render logic, shortcode fallback behavior, and menu item hydration.

## Smoke tests

1. Confirm plugin header, `VMSEB_VERSION`, and `vms-build.txt` all show `0.6.5`.
2. Activate with VMS and WooCommerce active; verify no fatal errors on public pages, event pages, cart, checkout, and wp-admin.
3. Visit a normal non-event page that does not contain the Express Bar shortcode and confirm the Express Bar does not auto-append.
4. Visit an Event Plan / TEC event with Express Bar enabled and auto-embed enabled; confirm the menu still appears.
5. Visit an Event Plan / TEC event with Express Bar disabled; confirm the menu does not appear.

## Dedicated Express Bar page tests

1. Create or edit a normal WordPress page using only `[vms_express_bar_menu]`.
2. With no published future-dated Express Bar-enabled event, confirm it shows “Express Bar Closed.”
3. With a published future-dated Express Bar-enabled event, confirm the page resolves that event automatically and displays the event name/menu.
4. Before the ordering window opens, confirm all menu categories/items render in browse-only mode and quantity/order controls are disabled.
5. During the ordering window, confirm quantity steppers enable and Review Order / Checkout work.
6. After the ordering window closes, confirm the menu remains browsable and controls are disabled.

## Cart/order regression tests

1. Add one non-age-gated item to cart and verify Express Bar event/pickup metadata appears in cart/order details.
2. Add one age-gated item and verify the birthday gate still appears before checkout/cart handoff.
3. Try submitting with no selected items and confirm the no-items validation still works.
4. Try submitting without pickup name and confirm validation still works.
5. Confirm bucket-eligible markers and subtotal summary still update correctly.

## Performance sanity checks

1. Compare Query Monitor on the dedicated Express Bar page before/after the patch using the same enabled menu items.
2. Confirm public menu rendering no longer hydrates every Woo product when only a handful of Express Bar items are enabled.
3. Confirm the Bar Menu admin registry still lists the full Woo catalog for configuration.
