# VMS 0.2.24.615 Test Plan — Progressive Ticket UI

🚨 **Codex/staging test required before production.** This pass changes the public ticket purchase surface. Keep the existing TEC/Woo checkout path as the rollback authority.

## 1. Version / package sanity

1. Install/activate VMS `0.2.24.615` on staging.
2. Confirm version markers:
   - plugin header: `0.2.24.615`
   - `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.615`
   - `vms-build.txt`: `0.2.24.615`
3. Confirm the package contains:
   - `assets/vms-ticketing-progressive-ui.js`
   - `assets/css/ticketing-front/90-ticket-progressive-ui.css`
   - this test plan

## 2. Safe Mode regression

1. Go to VMS Settings → Ticketing / Ticket UI.
2. Set Ticket UI Layout to **Safe Mode (TEC-only)**.
3. Disable admin preview if needed to see the true legacy/public path.
4. Visit a public event page with tickets.
5. Confirm:
   - Legacy ticket UI renders as before.
   - No Progressive section headers appear.
   - GA ticket quantity selection still reaches cart/checkout.
   - Existing qualified-ticket behavior is not worse than the previous build.
   - Existing add-on behavior is not worse than the previous build.

## 3. Progressive global setting

1. Set Ticket UI Layout to **Progressive (Tickets / Qualified / Amenities)**.
2. Visit an event with GA + qualified tickets + add-ons.
3. Confirm:
   - Tickets section is visible and open by default.
   - Qualified Discounts section is visible but collapsed by default.
   - Amenities / Add-ons section is visible but collapsed by default.
   - Existing TEC/Woo quantity controls are still used.
   - No nested forms are created.
   - Browser console has no new errors from `vms-ticketing-progressive-ui.js`.

## 4. Qualified-ticket visibility

Use an event with at least one qualified-ticket type.

1. Load the event page fresh while logged out or as an unapproved customer.
2. Confirm the login/register/verification/claim helper UI is not visible before choosing a qualified quantity.
3. Expand Qualified Discounts.
4. Select quantity `1` for a qualified ticket.
5. Confirm the relevant helper/claim/verification UI appears only for that selected qualified row.
6. Return the qualified quantity to `0`.
7. Confirm the helper UI hides again.
8. Confirm the copy does not imply the customer must buy GA first.

## 5. Add-on gating and math

Use an event with an add-on requiring a qualifying ticket count, such as a fire pit/table requiring 4 tickets.

1. Load the event page in Progressive mode.
2. Confirm Amenities / Add-ons is collapsed by default.
3. Expand Amenities / Add-ons.
4. Try the add-on without enough qualifying tickets.
5. Confirm existing gating still blocks or explains the requirement.
6. Select enough qualifying tickets.
7. Confirm the add-on can be selected according to the existing rules.
8. Confirm subtotal/cart math remains correct.
9. Confirm checkout is reachable.

## 6. Per-event override

1. With the global setting still Progressive, open an Event Plan in admin.
2. In Advanced Controls, set **Public ticket UI** to **Force Legacy / Safe Mode**.
3. Save/update the Event Plan.
4. Visit the public event page.
5. Confirm the event uses the legacy/safe public layout even though the global setting is Progressive.
6. Return the override to **Inherit** and save.
7. Confirm the event returns to the global Progressive layout.
8. Repeat with **Force V2 Unified** if a staging event supports that path.

## 7. Event shape matrix

Test these event shapes in Progressive mode:

- GA only.
- GA + qualified tickets.
- GA + add-ons.
- GA + qualified tickets + add-ons.
- Event with no add-ons.
- Event with no standard/GA ticket but qualified tickets available.

Expected:

- Empty sections are hidden when appropriate.
- The purchase path remains usable.
- No horizontal overflow at approximately 390px mobile width.
- Keyboard interaction works for all section toggles.
- `aria-expanded` changes when each section is opened/closed.

## 8. Regression checks from previous build

1. Event Feedback admin results page still loads.
2. Event Feedback notification settings still save.
3. Event Feedback response delete action still appears for admins.
4. No duplicate VMS nav stack appears on pages touched by the previous release.

## 9. Failure handling

If a test fails:

1. Capture the event URL, Event Plan ID, browser width, login state, and exact setting/override state.
2. Document whether the failure occurs only in Progressive mode or also in Safe Mode.
3. Prefer rollback by setting the event override to **Force Legacy / Safe Mode** before deeper repair.
4. If code is changed, bump version markers and package docs again before returning a replacement zip.
