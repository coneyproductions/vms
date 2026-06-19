# Codex Handoff — VMS 0.2.24.616

## Goal

Validate the quick correction pass for the Progressive public ticket UI.

This build is based on `0.2.24.615` and preserves the Progressive feature flag / rollback structure from that release.

## Changed files

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/helpers.php`
- `includes/admin/settings-page.php`
- `includes/tours/class-vms-tours-service.php`
- `assets/vms-ticketing-front.js`
- `assets/vms-ticketing-progressive-ui.js`
- `assets/css/vms-ticketing-front.css`
- `assets/css/ticketing-front/90-ticket-progressive-ui.css`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.616.md`
- `docs/test-plan-0.2.24.616-progressive-ticket-ui-correction.md`

## What changed

- Progressive mode now keeps **all admission/ticket rows in one Admission card**.
- Qualified ticket rows are no longer moved into a separate Qualified Discounts section.
- Qualified claim/login/status helper panels remain hidden until that qualified row has a selected quantity.
- The old split-section behavior is cleaned up if a prior render already created `.vms-ticket-ui-qualified`.
- The V2 add-on mount now targets the progressive content wrapper when present, and the progressive section wrapper also re-pulls loose child nodes into the collapsible content area on every run. This repairs the case where Amenities/Add-ons content can be remounted outside the open/closed panel.
- Default ticket help copy now says to choose the correct ticket type for each guest and not add GA for a guest using a free/qualified ticket.
- Legacy exact-match help copy is treated as stale so the old “Step 1: Select General Admission...” default does not continue showing after this update.
- Settings and guided-tour copy now describe the Progressive flow as Admission + Amenities rather than Tickets / Qualified / Amenities.

## Safety / rollback

- Global rollback: VMS Settings → Ticket UI Layout → **Safe Mode (TEC-only)**.
- Per-event rollback: Event Plan → Advanced Controls → Public ticket UI → **Force Legacy / Safe Mode**.
- This pass does not intentionally change TEC/Woo ticket creation, inventory, add-to-cart validation, add-on eligibility, qualified-ticket allowances, or checkout validation.

## Test emphasis

Run the packaged test plan:

`docs/test-plan-0.2.24.616-progressive-ticket-ui-correction.md`

Focus especially on:

1. Progressive mode shows one Admission card containing GA and qualified ticket rows together.
2. There is no separate Qualified Discounts accordion/card.
3. Qualified helper/login/claim panels stay hidden until the matching qualified quantity is selected.
4. The copy does not imply the customer should add GA first for someone using a free/qualified ticket.
5. Amenities/Add-ons content appears inside the Amenities accordion when expanded.
6. Add-on gating and cart math remain unchanged.
7. Safe Mode and per-event rollback still work.
8. No nested forms or new console errors are introduced.

## Repair protocol

🚨 If Codex makes any code changes during testing, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision log, this handoff or a follow-up handoff, the test plan, and package filename before returning a replacement zip.
