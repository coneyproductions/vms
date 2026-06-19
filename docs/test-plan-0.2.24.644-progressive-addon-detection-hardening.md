# VMS 0.2.24.644 Test Plan — Progressive Add-on Detection Hardening

## Goal
Confirm Progressive ticket UI shows the Fire Pits & Tables/add-on section for logged-out users on events whose add-on markup differs from the standard `#vms-reserved-addons.vms-entitlements-block` shape.

## Version marker
1. Deploy the zip.
2. Confirm `/wp-content/plugins/vms/vms-build.txt` reports `0.2.24.644`.
3. Confirm public assets load with `?ver=0.2.24.644`.

## Logged-out visibility
1. Open an incognito/private browser.
2. Visit the event that previously hid amenities/add-ons.
3. Confirm the Progressive UI shows the Tickets section and the Fire Pits & Tables/add-on section.
4. Confirm the add-on section is collapsed by default unless an add-on is already selected.
5. Expand the add-on section and confirm products/images/controls appear.

## Compare known-good event
1. Open the event where add-ons were already visible.
2. Confirm add-ons still appear and no duplicate add-on blocks are rendered.

## Qualification regression
1. Add fewer qualifying GA tickets than required for one add-on.
2. Confirm the add-on still shows the existing qualification warning.
3. Add the required GA quantity and confirm the add-on can still be added normally.

## Ticket quantity regression
1. Add 6 GA tickets.
2. Reduce to 4 in cart and refresh.
3. Increase to 7 and refresh.
4. Confirm no VMS ticket max blocker appears.

## Pass criteria
- Add-ons are visible to logged-out users on both the previously-broken event and the already-working event.
- No duplicate add-on section appears.
- Existing add-on qualification rules remain intact.
- Public ticket quantities remain uncapped by VMS.
