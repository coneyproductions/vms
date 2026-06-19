# VMS 0.2.24.687 — Chrome Mobile Checkbox Add-On Fix

## Purpose

This build fixes reserved add-ons configured with the checkbox selector mode on Chrome mobile, where a tap could visually toggle the checkbox and then immediately revert it.

## Changes

- Added explicit touch-toggle handling for checkbox-mode add-ons in the main public ticketing front-end bundle.
- Added click dedupe after touch handling so Chrome mobile cannot toggle the same checkbox twice from one tap.
- Mirrored the same touch-toggle protection in the inline server-controls controller.
- Added mobile checkbox wrapper/input touch hardening in the public ticketing stylesheet.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `assets/vms-ticketing-front.js`
- `assets/css/vms-ticketing-front.css`
- `includes/integrations/ticketing-rules-v2.php`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.687.md`
- `vms-test-plan-0.2.24.687.md`

## Validation Performed

- `node --check assets/vms-ticketing-front.js`
- `php -l includes/integrations/ticketing-rules-v2.php`

## Notes / Caveats

- I did not run a live mobile Chrome browser repro against a concrete event URL in this environment, so the packaged test plan below should be run on an event using the checkbox selector mode.
