# VMS 0.2.24.745

## Purpose
Hotfix the public sale ticket card regression where the Early Bird/sale detail line renders above the ticket title.

## Changes
- Updated `assets/vms-ticketing-front.js` so active sale rows always place the public ticket sale badge before the title and the sale detail/meta line after the title.
- Kept the existing sale detail content, price display behavior, quantity controls, and qualified/free ticket rendering unchanged.

## Files changed
- `assets/vms-ticketing-front.js`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`

## Validation
- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `php -l includes/integrations/ticketing-rules-v2.php`
- `node --check assets/vms-ticketing-front.js`
- Production Taylor page DOM smoke using Playwright against `https://serenaderange.com/event/reputation-a-tribute-to-taylor/`

## Package
- `vms-0.2.24.745-ticket-sale-title-order-hotfix.zip`
