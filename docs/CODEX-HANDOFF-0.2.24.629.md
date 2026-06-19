# CODEX HANDOFF — VMS 0.2.24.629

🚨 **Please run this on staging before live deployment.**

## Goal

Improve public progressive ticketing clarity for qualified/free tickets so customers understand they should choose **one admission path per person**: General Admission, their own qualified ticket, or a registered guest email — not both.

## What changed

- Clarified the registered-guest helper copy shown after selecting a qualified ticket.
- Added a collapsible `Need help choosing tickets?` explainer inside the progressive Tickets section.
- Kept the helper collapsed by default so the main ticket form stays clean.
- Preserved the 0.2.24.628 stepper-centering CSS and earlier registered-guest mobile layout fixes.

## Files changed

- `assets/vms-ticketing-front.js`
- `assets/css/ticketing-front/80-ticket-ui-rewrite.css`
- `assets/css/vms-ticketing-front.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.629.md`
- `docs/test-plan-0.2.24.629-qualified-ticket-clarity.md`
- `vms-test-plan-0.2.24.629.md`

## Version markers updated

- Plugin header version → `0.2.24.629`
- Core constant version → `0.2.24.629`
- `vms-build.txt` → `0.2.24.629`

## Validation already run locally

- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `node --check assets/vms-ticketing-front.js`
- `php tests/check-package-integrity.php .`

## What Codex should verify

1. Public Buffet Beach event page still renders progressive ticketing when the setting is progressive.
2. The Tickets section shows a collapsed `Need help choosing tickets?` explainer.
3. Opening the explainer clearly states one admission option per person and explains General Admission, qualified tickets, and registered guest email use.
4. Selecting a qualified ticket while logged out shows the updated registered-guest copy explaining that the guest email path is an alternative to General Admission for the same person.
5. Mobile registered-guest email input remains stacked above `Add Registered Guest`.
6. Ticket and add-on `- / +` steppers remain centered.
7. Amenities remains collapsed by default in progressive mode.
8. Add-on gating and add-to-cart still work for 4 GA + 1 Fire Table.
