# VMS Test Plan — 0.2.24.621 Progressive Ticket UI Mobile Flattening

## Scope

Validate that the Progressive ticket UI remains clean on desktop while mobile/tablet no longer feels boxed-in or horizontally constrained.

## Install / version checks

1. Install/activate the packaged zip on staging.
2. Confirm `vms-build.txt` reports `0.2.24.621`.
3. Confirm the WP plugin header and `VMS_VERSION` constant both report `0.2.24.621`.

## Desktop regression

1. Open an event with GA tickets, qualified tickets, and Amenities/add-ons.
2. Confirm the purchase UI still has one visible `Tickets` heading.
3. Confirm ticket rows are visible together in one Tickets section.
4. Confirm `Amenities` is collapsed by default and no longer says `/ Add-ons`.
5. Add 1 GA ticket and confirm subtotal updates.
6. Expand Amenities, add an eligible add-on only after requirements are met, and confirm subtotal/cart behavior still works.

## Mobile / tablet layout

Test at approximately 390px, 430px, iPad/tablet width, and one Android-like narrow viewport if available.

1. Confirm the ticket UI no longer appears as multiple nested boxes inside each other.
2. Confirm ticket rows use more available width and the quantity controls do not stick out of the left side of the ticket card.
3. Confirm GA price and quantity controls fit on the same control row.
4. Confirm unselected qualified ticket rows are compact and do not show the long explanatory copy.
5. Select a qualified ticket quantity and confirm the relevant qualification/help panel appears only after selection.
6. Expand Amenities and confirm add-on cards use the available width better.
7. Confirm add-on quantity/price/status text stays inside the card.
8. Confirm the sticky Add items to cart button still stays usable on mobile.

## Business-rule regression

1. Add-on qualification requirements must still block add-ons until the required ticket count is present.
2. Qualified tickets must still require the existing approved-account/claim workflow.
3. No changes should occur to Woo product IDs, TEC ticket IDs, cart line items, prices, inventory, or event/add-on eligibility rules.

## Rollback

If mobile layout regresses, disable Progressive layout in VMS ticket UI settings or roll back to `0.2.24.620`.
