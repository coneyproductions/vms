# CODEX HANDOFF — VMS 0.2.24.631

🚨 **Please test this on staging before live deployment.**

## Goal

Hotfix the `0.2.24.630` selected approved/free admission row collapse where the new ticket-specific help card was being inserted as a loose child in the ticket row grid and overlapping the description/login panel on desktop.

## What changed

- Moved `.vms-claim-ticket-help` into the existing `.vms-ticket-status-stack` with the login/register note and approved guest panel.
- Kept the intended order: login/register note, help card, approved guest email panel.
- Did not add another CSS tail override.
- Preserved the approved-ticket copy from `0.2.24.630`.

## Files changed

- `assets/vms-ticketing-front.js`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.631.md`
- `docs/test-plan-0.2.24.631-approved-ticket-help-layout-hotfix.md`
- `vms-test-plan-0.2.24.631.md`

## Version markers updated

- Plugin header version → `0.2.24.631`
- Core constant version → `0.2.24.631`
- `vms-build.txt` → `0.2.24.631`

## Validation already run locally

- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `node --check assets/vms-ticketing-front.js`
- `php tests/check-package-integrity.php .`
- `unzip -t VMS_Ticket_UI_631.zip`

## Staging checks

1. Open Buffet Beach public event page on desktop.
2. Select 1 Veteran Admission ticket while logged out.
3. Confirm the row no longer collapses/overlaps.
4. Confirm order is login/register note → `Need help ordering Veteran Admission tickets?` → `Bringing an approved guest?` panel.
5. Repeat for Police, Fire Fighter, EMT Admission.
6. Check mobile width: guest email input still stacks above `Add Registered Guest`.
7. Confirm ticket and add-on steppers remain centered.
8. Confirm Amenities remains collapsed by default and add-on gating still works.
