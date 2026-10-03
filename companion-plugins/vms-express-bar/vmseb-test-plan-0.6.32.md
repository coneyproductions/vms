# VMS Express Bar 0.6.32 Test Plan

## Preflight
1. Confirm staging is running VMS Express Bar 0.6.32.
2. Confirm temporary WPCode snippets #5981 and #5982 remain disabled.
3. Purge staging cache.

## A. Review action placement
1. Open Express Bar on desktop with an eligible event.
2. Confirm the sticky top summary shows only selected-item count and subtotal; no Review order button is present.
3. Scroll to the end of the menu and confirm **Review Order** and **Checkout** are present together.
4. Select an item and confirm the sticky summary count/subtotal update normally.
5. Tap **Review Order** at the bottom and confirm routing to cart still works.
6. Tap **Checkout** at the bottom and confirm direct checkout routing still works.

## B. Mobile
1. Confirm the sticky summary is materially shorter without the top CTA.
2. Confirm menu rows and quantity steppers remain usable while the summary is sticky.
3. Confirm both bottom actions remain visible and usable at the end of the menu.

## C. Regression
1. Event selector remains bounded/scrollable with multiple events.
2. Admission states still work.
3. Exactly one plugin-native tip prompt appears in Express Bar checkout.
4. Tip collapse/change behavior remains correct.
5. Age gate, ordering windows, resolver, and metadata remain unchanged.
