# VMS 0.2.24.640 — Progressive Ticket Copy / Heading Polish Test Plan

🚨 **Codex/staging test required before production.** This is customer-facing ticket UI copy/layout work. If Codex makes any code changes during testing, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, test plan, and package filename before returning a replacement zip.

## Purpose

Polish the Progressive public ticket UI for the current customer base: simplify verified/free admission row copy, replace the old “First time? More info” trigger, restore the configured Tickets help block, make add-on section labels editable, decode escaped ticket display labels, and make Progressive section titles stand out more clearly.

## Files touched

- `assets/vms-ticketing-front.js`
- `assets/vms-ticketing-progressive-ui.js`
- `assets/css/ticketing-front/90-ticket-progressive-ui.css`
- `assets/css/vms-ticketing-front.css`
- `includes/helpers.php`
- `includes/admin/settings-page.php`
- `includes/integrations/ticketing-rules-v2.php`
- `includes/tours/class-vms-tours-service.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`

## Version checks

1. Confirm the plugin header reports `0.2.24.640`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.640`.
3. Confirm `vms-build.txt` begins with `0.2.24.640`.

## Public ticket UI checks

1. Open a public event using Progressive Ticket UI.
2. Confirm regular tickets and verified/free tickets remain grouped inside the main Tickets section.
3. Confirm verified/free ticket rows show the short default copy `Requires registration` instead of the old “Free with approved...” / “Already approved...” language.
4. Confirm the row disclosure link reads `Click here for more info.`.
5. Expand the disclosure and confirm the deeper registration/approval guidance still appears and remains collapsed by default.
6. Confirm ticket titles containing escaped characters display naturally, especially `Children's Admission (<12yo)` rather than `Children's Admission (&lt;12yo)`.
7. Confirm the main Progressive section titles are visibly larger/more prominent than row body copy on desktop and mobile.

## Ticket help copy regression

1. Go to VMS Settings → Ticketing → Ticket UI.
2. Confirm `Show ticket help above Tickets` is enabled.
3. Confirm the configured ticket help copy appears in the Progressive public Tickets section above the ticket rows.
4. Disable the ticket help setting, save, and confirm the Tickets help block disappears.
5. Re-enable it and confirm it returns with saved formatting.

## Add-on section setting checks

1. In VMS Settings → Ticketing → Ticket UI, confirm editable fields exist for:
   - Add-on section heading
   - Add-on section subtext
2. With defaults, confirm the public add-on accordion heading reads `Fire Pits & Tables`.
3. Confirm the add-on accordion subtext reads `Click here to add a fire pit or table to your order.`.
4. Change those settings to custom neutral wording, save, and confirm the public Progressive add-on section updates.
5. Clear the custom values, save, and confirm defaults return.

## Add-on behavior regression

1. Confirm add-ons remain collapsed by default when no add-on is selected.
2. Select an add-on quantity and confirm the section opens/indicates selection as before.
3. Confirm add-on qualification/blocker logic still works: add-ons requiring qualifying tickets should still block checkout until enough eligible tickets are selected.

## Cart/checkout regression from 0.2.24.639

1. Re-run the key 0.2.24.639 cart case: add 6 tickets, reduce to 4, and confirm the cart settles at 4.
2. Confirm checkout is not blocked when the cart is exactly at a valid ticket/add-on threshold.
3. Confirm invalid ticket/add-on combinations still show the VMS blocker.

## Rollback

If the public ticket UI is worse or verified ticket redemption breaks, roll back to `0.2.24.639` and capture: event URL, browser/device, Ticket UI layout setting, whether the event has verified tickets, whether add-ons are present, screenshots of the Tickets and add-on sections, and console errors.
