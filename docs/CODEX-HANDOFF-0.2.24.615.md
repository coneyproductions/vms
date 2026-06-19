# Codex Handoff — VMS 0.2.24.615

## Goal

Validate the first conservative pass of the Progressive public ticket UI.

This build is based on `0.2.24.614` and preserves the Event Feedback notification/delete work from that release.

## Changed files

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/admin/settings-page.php`
- `includes/cpt/event-plans.php`
- `includes/cpt/event-plans/partials/advanced-controls.php`
- `includes/integrations/ticketing-rules-v2.php`
- `includes/tours/class-vms-tours-service.php`
- `assets/vms-ticketing-progressive-ui.js`
- `assets/css/vms-ticketing-front.css`
- `assets/css/ticketing-front/90-ticket-progressive-ui.css`
- `docs/test-plan-0.2.24.615-progressive-ticket-ui.md`

## What changed

- Added a third global Ticket UI Layout option: **Progressive (Tickets / Qualified / Amenities)**.
- Added an Event Plan-level **Public ticket UI** override with:
  - Inherit global setting
  - Force Progressive
  - Force V2 Unified
  - Force Legacy / Safe Mode
- Extended front-end ticket UI settings so per-event overrides can control the active public render mode.
- Added a Progressive enhancement script that runs only when the active layout is Progressive.
- The script groups the existing V2 ticket UI into customer-friendly sections:
  - Tickets
  - Qualified Discounts
  - Amenities / Add-ons
- Qualified Discounts stays collapsed on initial load when possible.
- Qualified login/register/claim helper panels are hidden until the customer selects a qualified-ticket quantity.
- Add-ons stay collapsed by default unless an add-on is already selected.
- Existing TEC/Woo quantity controls, add-to-cart behavior, add-on gating, and server-side business rules remain the source of truth.
- Updated the Ticketing UI settings tour copy for Progressive rollout and rollback.

## Safety / rollback

- Global rollback: VMS Settings → Ticket UI Layout → **Safe Mode (TEC-only)**.
- Per-event rollback: Event Plan → Advanced Controls → Public ticket UI → **Force Legacy / Safe Mode**.
- This pass does not remove or rewrite TEC/Woo checkout behavior.
- This pass does not intentionally change ticket inventory, ticket creation, add-on eligibility, qualification allowance, or checkout validation logic.

## Test emphasis

Run the packaged test plan:

`docs/test-plan-0.2.24.615-progressive-ticket-ui.md`

Focus especially on:

1. Safe Mode still renders legacy behavior.
2. Progressive mode renders Tickets open by default, Qualified Discounts collapsed by default, and Amenities/Add-ons collapsed by default.
3. Qualified verification/helper UI is not visible on initial page load and appears only after selecting qualified-ticket quantity.
4. Customers are not led to believe they must buy GA before qualified admission.
5. Add-ons remain gated correctly and cart math remains correct.
6. Per-event Force Legacy / Safe Mode works even when the global setting is Progressive.
7. No nested forms or new console errors are introduced.
8. Mobile width around 390px has no horizontal overflow.

## Regression checks

- Event with GA only.
- Event with GA + qualified tickets.
- Event with GA + add-ons.
- Event with GA + qualified tickets + add-ons.
- Add-on requiring 4 qualifying tickets.
- Logged-in approved qualified buyer.
- Logged-out / unapproved qualified buyer.
- Cart/checkout reachability.
- Event Feedback admin controls from `0.2.24.614` still load.

## Repair protocol

🚨 If Codex makes any code changes during testing, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision log, this handoff or a follow-up handoff, the test plan, and package filename before returning a replacement zip.
