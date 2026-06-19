# CODEX HANDOFF — VMS 0.2.24.628

🚨 **Please run this on staging before live deployment.**

## Goal

Polish the desktop public ticket UI so native ticket `- / +` controls no longer look top-aligned, while preserving the mobile registered-guest stack and the prior add-on stepper fixes.

## What changed

- Centered native ticket `- / +` button contents in the existing V2 ticket-row control rules for both the base V2 layout and the rewrite/progressive layer.
- Centered the ticket quantity wrapper/number field in the same rule groups.
- Did **not** add a new late/tail CSS override block.
- Preserved:
  - `0.2.24.627` registered guest email input stacked above `Add Registered Guest` on mobile.
  - `0.2.24.626` add-on stepper alignment.
  - `0.2.24.625` `Add Registered Guest` wording and Log In/Register alignment.

## Files changed

- `assets/css/ticketing-front/70-ticket-ui-v2.css`
- `assets/css/ticketing-front/80-ticket-ui-rewrite.css`
- `assets/css/vms-ticketing-front.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.628.md`
- `docs/test-plan-0.2.24.628-ticket-stepper-alignment.md`
- `vms-test-plan-0.2.24.628.md`

## Version markers updated

- Plugin header version → `0.2.24.628`
- Core constant version → `0.2.24.628`
- `vms-build.txt` → `0.2.24.628`

## What Codex should verify on staging

1. Desktop public event page using the progressive/new ticket UI:
   - General Admission `- / +` symbols are centered inside their buttons.
   - Qualified ticket `- / +` symbols are centered inside their buttons.
   - Ticket quantity number is centered vertically/horizontally.
2. Desktop unified/V2 ticket UI fallback, if separately testable:
   - Same ticket stepper alignment checks pass.
3. Mobile public event page:
   - Registered guest email input remains stacked above `Add Registered Guest`.
   - Add-on steppers remain centered.
   - Sticky `Add items to cart` remains usable.
4. Functional smoke:
   - Select tickets.
   - Select/unlock an amenity/add-on.
   - Add items to cart.
