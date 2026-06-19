# CODEX HANDOFF — VMS 0.2.24.640

🚨 **Staging/customer-facing ticket UI test required.**

## Summary

This patch polishes the Progressive public ticket UI copy and hierarchy based on the live event page review. It intentionally keeps the existing ticket/qualified/add-on business rules intact while changing the customer-facing default language and display details.

## Changes

- Changes verified/free admission row default copy to `Requires registration`.
- Changes the qualified/free row disclosure trigger from `First time? More info` to `Click here for more info.`.
- Decodes escaped display labels before rendering customer-facing ticket names, so labels such as `Children's Admission (<12yo)` do not display as `&lt;12yo`.
- Makes Progressive section titles larger and more prominent on desktop and mobile.
- Restores the configured Tickets help/custom-instructions block in Progressive layout.
- Renames the default add-on accordion from Amenities to `Fire Pits & Tables`.
- Adds editable global settings for the Progressive add-on section heading and subtext.
- Updates Ticketing UI guided-tour copy away from “Amenities” and toward neutral add-on wording.
- Defaults add-on subtext to `Click here to add a fire pit or table to your order.`.
- Preserves the 0.2.24.639 cart quantity multi-change hotfix.

## Files changed

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
- `vms-test-plan-0.2.24.640.md`
- `docs/test-plan-0.2.24.640-progressive-ticket-copy-heading-polish.md`

## Required testing

Run `vms-test-plan-0.2.24.640.md`. Prioritize the public event page checks first: verified ticket row copy, the more-info trigger, decoded `<12yo` ticket labels, visible ticket help copy, larger section headings, and the default/custom add-on section heading/subtext.

Do not invoke unrelated live-changing actions during testing. If any code changes are required during testing, bump version/build notes/package filename again before returning the revised zip.
