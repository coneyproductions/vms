# CODEX HANDOFF — VMS 0.2.24.632

🚨 **Please test this on staging before live deployment.**

## Goal

Polish the selected approved/free ticket help card so it does not add visual weight by default and does not waste mobile width when expanded.

## What changed

- The ticket-specific `Need help ordering [Ticket Name] tickets?` help card is now a collapsible `<details>` panel.
- The help panel is collapsed by default whenever the ticket help first appears.
- Expanded help copy renders as compact, non-indented paragraphs instead of an indented ordered list.
- Removed the final General Admission warning line from the help card copy.
- Preserved the 0.2.24.631 help-card placement/layout fix.

## Files changed

- `assets/vms-ticketing-front.js`
- `assets/css/ticketing-front/80-ticket-ui-rewrite.css`
- `assets/css/vms-ticketing-front.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.632.md`
- `docs/test-plan-0.2.24.632-approved-ticket-help-collapse.md`
- `vms-test-plan-0.2.24.632.md`

## Version markers updated

- Plugin header version → `0.2.24.632`
- Core constant version → `0.2.24.632`
- `vms-build.txt` → `0.2.24.632`

## Validation already run locally

- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `node --check assets/vms-ticketing-front.js`
- `php tests/check-package-integrity.php .`
- `unzip -t VMS_Ticket_UI_632.zip`

## What Codex should verify on staging

1. Select 1 Veteran Admission ticket while logged out.
2. Confirm the ticket-specific help card appears between Log In/Register and the approved guest panel.
3. Confirm the help card is collapsed by default.
4. Expand the help card and confirm the help text is not indented/numbered and does not include the General Admission warning line.
5. Repeat for Police, Fire Fighter, EMT Admission.
6. Confirm mobile registered guest input still stacks above `Add Registered Guest`.
7. Confirm ticket/add-on steppers remain centered.
8. Confirm Amenities remains collapsed by default and add-on gating still unlocks correctly.
