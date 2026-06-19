# CODEX HANDOFF — VMS 0.2.24.620

## Purpose

Polish the Progressive public ticket UI after staging review so the purchase surface is less wordy and less visually repetitive.

## Changed files

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/helpers.php`
- `includes/admin/settings-page.php`
- `includes/tours/class-vms-tours-service.php`
- `assets/vms-ticketing-front.js`
- `assets/vms-ticketing-progressive-ui.js`
- `assets/css/ticketing-front/90-ticket-progressive-ui.css`
- `assets/css/vms-ticketing-front.css`
- `docs/05-revision-log.md`
- `docs/test-plan-0.2.24.620-progressive-ticket-ui-polish.md`
- `vms-test-plan-0.2.24.620.md`

## Behavior changes

- Progressive mode now presents one **Tickets** header instead of a top **Admission** header plus an inner **Tickets** title.
- Tickets remain exposed/open by default and the ticket accordion header is effectively non-collapsible.
- Progressive ticket help text is suppressed so the long explanatory paragraph no longer renders below the ticket rows.
- Stored legacy/default ticket help copy matching the prior verbose warning is treated as stale and replaced by the new blank default.
- **Amenities / Add-ons** is renamed to **Amenities**.
- The Amenities subtext remains: “Want to add a fire pit, table, or other extras?”
- Collapsed Amenities no longer inherits the large sticky-button padding that created a blank white void.

## Regression focus

1. Open a public event using Ticket UI Layout = Progressive.
2. Confirm there is only one customer-facing ticket section title: **Tickets**.
3. Confirm the old **Admission** header does not appear in the ticket purchase UI.
4. Confirm the inner/native **Tickets** title is hidden so the word is not duplicated.
5. Confirm the long help paragraph beginning “Choose the ticket type for each guest...” does not appear.
6. Confirm all standard and qualified ticket rows stay together inside the Tickets area.
7. Confirm qualified-ticket login/claim/helper UI stays hidden until a qualified-ticket quantity is selected.
8. Confirm the Amenities section is collapsed on first load, reads **Amenities**, keeps its short subtext, and has no large blank spacer beneath it.
9. Expand Amenities and confirm actual add-on rows still render and can be selected.
10. Confirm add-to-cart/subtotal behavior is unchanged for tickets only, add-ons only where allowed, and mixed ticket + add-on selections.

## Notes

No database migration is required. This is a front-end copy/layout polish pass plus version/doc updates.
