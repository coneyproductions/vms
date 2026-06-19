# VMS Test Plan — 0.2.24.625 Qualified Guest Button Polish

🚨 **Codex/staging verification recommended before live deployment.**

## Scope

Validate the selected qualified-ticket logged-out flow after final vocabulary/alignment polish.

## Version checks

1. Upload/install `VMS_Ticket_UI_625.zip` on staging.
2. Confirm `vms-build.txt` reports `0.2.24.625`.
3. Confirm the WP plugin header and `VMS_VERSION` constant both report `0.2.24.625`.

## Public ticket UI regression

1. Open a public event with General Admission, qualified tickets, and Amenities/add-ons.
2. Confirm there is one visible `Tickets` heading and no duplicate `Admission` heading.
3. Confirm qualified rows still show their short row descriptions at quantity `0`.
4. Confirm Amenities is collapsed by default, titled `Amenities`, and still shows its helper subtext.

## Selected qualified-ticket check

At desktop and phone widths, select one Veteran/qualified ticket while logged out.

Expected:

- The login/register note says: `Please log in to redeem your [ticket name]. New here? Register first.` or equivalent.
- The Log In and Register button text is vertically centered.
- The guest panel is titled `Bringing a registered guest?`.
- The guest-email field sits above the action button on phone widths.
- The action button says `Add Registered Guest`.
- `Add Qualified Guest` is no longer shown in the selected qualified-ticket customer flow.
- No horizontal overflow at 390, 412, 430, 768, or 1024 widths.

## Functional regression

1. Add 4 General Admission tickets.
2. Confirm a Fire Table/add-on becomes eligible.
3. Add 1 Fire Table/add-on.
4. Click Add items to cart.

Expected:

- Cart receives the expected 4 GA tickets + selected add-on.
- Existing add-on gating and subtotal behavior are unchanged.

## Notes

If cart-level Woo discounts apply after checkout/cart load, do not treat a cart total difference from the event-page subtotal as a regression unless this build changed discount behavior.
