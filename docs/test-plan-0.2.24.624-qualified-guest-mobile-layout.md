# VMS Test Plan — 0.2.24.624 Qualified Guest Mobile Layout Cleanup

🚨 **Codex/staging verification recommended before live deployment.** This pass changes selected qualified-ticket customer-facing copy and mobile layout for the claim/registered-email area.

## Scope

Validate that the Progressive public ticket UI remains grouped and mobile-safe while the selected qualified-ticket instructions become clearer for logged-out customers.

## Version checks

1. Upload/install `VMS_Ticket_UI_624.zip` on staging.
2. Confirm `vms-build.txt` reports `0.2.24.624`.
3. Confirm the WP plugin header and `VMS_VERSION` constant both report `0.2.24.624`.

## Desktop regression

1. Open a public event with General Admission, qualified tickets, and Amenities/add-ons.
2. Confirm there is one visible `Tickets` heading and no duplicate `Admission` heading.
3. Confirm qualified rows still show their short row descriptions at quantity `0`.
4. Confirm Amenities is collapsed by default, titled `Amenities`, and still shows its helper subtext.
5. Select a qualified ticket quantity of `1` while logged out.

Expected:
- The login/register note uses concise wording similar to `Please log in to redeem your [ticket name]. New here? Register first.`
- The note shows Log In and Register actions.
- The guest-email section is separate and titled `Bringing a registered guest?`.
- The guest-email helper mentions entering the registered email for guests already approved for that specific ticket.
- The old combined wording about “bringing additional approved guests” is gone.
- The redundant `Need more than one qualified ticket?` disclosure is not shown while the email panel is already visible.

## Mobile/tablet layout

Test at 390, 412, 430, 768, and 1024 widths.

1. Select one Veteran/qualified ticket while logged out.
2. Inspect the login/register note and guest-email panel.
3. Confirm the registered-email input and `Add Qualified Guest` button are usable.

Expected:
- No horizontal overflow.
- No field/button squeeze where the input collapses to a tiny visible area.
- The extra outline around each registered-guest email row is removed or visually neutralized so the area feels less boxed-in.
- On phone widths, the registered-email input sits above the `Add Qualified Guest` button and both controls span the available width.
- The sticky Add items to cart CTA remains usable.

## Functional regression

1. Add 4 General Admission tickets.
2. Confirm a Fire Table/add-on becomes eligible.
3. Add 1 Fire Table/add-on.
4. Click Add items to cart.

Expected:
- Cart receives the expected 4 GA tickets + selected add-on.
- Existing add-on gating and subtotal behavior are unchanged.

## Notes

If cart-level Woo discounts apply after checkout/cart load, do not treat a cart total difference from the event-page subtotal as a regression unless this build changed the discount behavior.
