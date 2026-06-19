# VMS Test Plan — 0.2.24.628 Ticket Stepper Alignment

🚨 **Codex/staging verification recommended before live deployment.**

## Version checks

1. Upload/install `VMS_Ticket_UI_628.zip` on staging.
2. Confirm `vms-build.txt` reports `0.2.24.628`.
3. Confirm the WP plugin header and `VMS_VERSION` constant both report `0.2.24.628`.

## Desktop ticket stepper visual regression

Test on the Buffet Beach event or another event with GA, qualified tickets, and add-ons.

Widths:
- Desktop/full browser width.
- Tablet-ish width if practical.

Expected:
- General Admission `-` and `+` symbols are vertically centered inside their buttons.
- Qualified ticket `-` and `+` symbols are vertically centered inside their buttons.
- Ticket quantity number field is centered vertically/horizontally.
- Add-on / amenity steppers remain centered.
- No horizontal overflow or obvious row layout drift.

## Mobile regression

Widths:
- 390px
- 412px
- 430px

Expected:
- Registered guest email input remains stacked above the `Add Registered Guest` button.
- `Log In` and `Register` button text remains centered.
- Ticket and add-on steppers remain usable and centered.
- Sticky `Add items to cart` CTA remains usable.

## Add-on gating regression

1. Add 4 General Admission tickets.
2. Confirm a Fire Table/add-on becomes eligible.
3. Add 1 Fire Table/add-on.
4. Add items to cart.

Expected:
- Cart receives the expected 4 GA tickets + selected add-on.
- Existing add-on gating and subtotal behavior are unchanged.
