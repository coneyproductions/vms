# VMS Express Bar 0.6.39 test plan

1. Confirm the plugin header, `VMSEB_VERSION`, and `vms-build.txt` report `0.6.39`.
2. Run PHP syntax checks for every PHP file, JavaScript syntax checks for every JavaScript file, and every Express Bar regression test.
3. Desktop and mobile: verify the selected-item summary scrolls normally and the bottom **Review Order** bar stays visible while the Express Bar shell is in view.
4. Open **Events & Tickets** while Express Bar is scrolled; confirm the dropdown/drawer remains above and clickable, with no overlap from the selected-item summary or purchase bar.
5. Verify the purchase bar is not clipped by the WordPress admin bar or mobile safe area, does not cause horizontal overflow, and leaves enough space beneath the menu controls.
6. Verify cart-flow cases: empty cart/no selections; ticket cart/no selections; ticket cart/Express Bar selections; Express Bar-only cart; and removal of all Express Bar selections while a ticket remains.
7. Confirm the persistent count and total reflect the current WooCommerce cart while category counters and the in-flow subtotal continue to reflect staged Express Bar selections.
8. Confirm pickup name, age gate, quantity steppers, availability, event association, ordering windows, metadata, pricing, tax, checkout, and admissions remain unchanged.
9. Confirm the production primary desktop and mobile menus contain the actual WooCommerce Cart page without changing the issue #3 Events & Tickets dropdown.
10. Check browser console, PHP error log, page response, caches, and the active production event before sign-off.
