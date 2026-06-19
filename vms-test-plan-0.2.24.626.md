# VMS Test Plan — 0.2.24.626 Stepper Alignment Cleanup

🚨 **Codex/staging verification recommended before live deployment.**

## Version checks

1. Upload/install `VMS_Ticket_UI_626.zip` on staging.
2. Confirm `vms-build.txt` reports `0.2.24.626`.
3. Confirm the WP plugin header and `VMS_VERSION` constant both report `0.2.24.626`.

## Mobile visual regression

Test on the Buffet Beach event or another event with GA, qualified tickets, and add-ons.

Widths:
- 390px
- 412px
- 430px
- 768px
- 1024px

Expected:
- The `-` and `+` symbols in General Admission are vertically centered in their buttons.
- The `-` and `+` symbols in qualified-ticket rows are vertically centered in their buttons.
- The `-` and `+` symbols in add-on / amenity steppers are vertically centered in their buttons.
- The sticky `Add items to cart` CTA remains usable.
- No horizontal overflow.

## Qualified ticket regression

1. While logged out, increase Veteran Admission to `1`.
2. Confirm the concise login/register note still appears.
3. Confirm Log In and Register button text is centered.
4. Confirm the registered-guest action says `Add Registered Guest`.

Expected:
- Qualified helper panels still appear only after selecting the qualified ticket.
- The registered guest field remains stacked above the button on mobile.

## Add-on gating regression

1. Add 4 General Admission tickets.
2. Confirm a Fire Table/add-on becomes eligible.
3. Add 1 Fire Table/add-on.
4. Add items to cart.

Expected:
- Cart receives the expected 4 GA tickets + selected add-on.
- Existing add-on gating and subtotal behavior are unchanged.
