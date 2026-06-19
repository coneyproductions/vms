# VMS 0.2.24.683 — Mobile Chrome Ticketing Touch/Submit Hardening

## Purpose

This build follows `0.2.24.682` and separates the mobile Chrome ticketing fixes from the prior Payment Gateway Health work.

The main operator issue was that some customers on Chrome mobile could not reliably increase ticket quantity from `0` with a single tap, and the progressive add-ons accordion could feel non-responsive. This build hardens those touch paths and keeps public ticket submits from hanging forever behind a slow `cart_context` prefetch.

## Changes

- Adds a touch fallback for native TEC ticket quantity `+` / `-` buttons so VMS can advance the field and emit normal `input` / `change` events if the browser touch does not become a reliable click.
- Watches native ticket quantity buttons on `pointerup` / `touchend` in addition to the existing click-driven path.
- Binds the progressive add-ons accordion toggle on `pointerup` / `touchend` with click dedupe so mobile Chrome does not depend on delayed click synthesis to open the section.
- Enlarges the progressive mobile quantity controls and applies `touch-action: manipulation` plus touch-friendly toggle/button affordances to reduce tap misses and accidental text selection.
- Skips the `cart_context` prefetch entirely for ticket-only atomic submits.
- Lets add-on submits continue after a short timeout if `cart_context` is slow or temporarily stalled under load.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `assets/vms-ticketing-front.js`
- `assets/vms-ticketing-progressive-ui.js`
- `assets/css/vms-ticketing-front.css`
- `includes/integrations/ticketing-rules-v2.php`
- `assets/vms-ticketing-front-server-controls.js`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.683.md`
- `vms-test-plan-0.2.24.683.md`

## Validation Performed

- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `php -l includes/integrations/ticketing-rules-v2.php`
- `node --check assets/vms-ticketing-front.js`
- `node --check assets/vms-ticketing-progressive-ui.js`
- `node --check assets/vms-ticketing-front-server-controls.js`

## Notes / Caveats

- The progressive add-ons section can still enforce ticket-qualification rules after it opens; this build only hardens the interaction path, not the business rules.
- The ticketing bundle build stamp remains filemtime-driven, while the release marker for this package is `0.2.24.683`.
