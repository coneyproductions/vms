# CODEX HANDOFF — VMS 0.2.24.630

🚨 **Please run this on staging before live deployment.**

## Goal

Verify the approved/free admission help copy now lives inside each selected ticket type instead of as a global Tickets help card.

## What changed

- Removed the global `Need help choosing tickets?` helper from the top of the Tickets section.
- Added a ticket-specific help card inside selected approved/free ticket rows, between the Log In/Register note and the approved guest email panel.
- Updated the registration wording to avoid implying a customer can register during checkout or convert a General Admission ticket later.
- Added softer reassurance that approvals are often completed quickly.
- Updated guest-copy language from “registered guest” in the section title to “approved guest,” while keeping the action button as `Add Registered Guest`.

## Files changed

- `assets/vms-ticketing-front.js`
- `assets/css/ticketing-front/80-ticket-ui-rewrite.css`
- `assets/css/vms-ticketing-front.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.630.md`
- `docs/test-plan-0.2.24.630-approved-ticket-help-card.md`
- `vms-test-plan-0.2.24.630.md`

## Version markers updated

- Plugin header version → `0.2.24.630`
- Core constant version → `0.2.24.630`
- `vms-build.txt` → `0.2.24.630`

## Validation already run locally

- `node -c assets/vms-ticketing-front.js`
- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `php tests/check-package-integrity.php .`
- `unzip -t VMS_Ticket_UI_630.zip`

## Staging focus

- Confirm the public ticket form still renders progressive mode.
- Select Veteran Admission while logged out and confirm the ticket-specific help card appears between the login/register note and approved guest email panel.
- Confirm there is no global `Need help choosing tickets?` helper at the top of the Tickets section.
- Repeat the same help-card check for Police, Fire Fighter, EMT Admission.
- Confirm mobile still stacks the approved guest email input above the `Add Registered Guest` button.
- Confirm ticket/add-on steppers remain centered.
- Confirm Amenities remains collapsed by default and add-on gating still unlocks correctly.
