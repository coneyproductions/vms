# VMS 0.2.24.684 — Mobile Progressive Ticketing Responsiveness Follow-Up

## Purpose

This build follows `0.2.24.683` and tightens the remaining mobile Chrome responsiveness issues observed on the staging event page for `American Petty: A Tom Petty Experience`.

The main issues were:

- native ticket `+` / `-` controls still felt slow on mobile because the fallback waited too long before helping
- the progressive add-ons accordion could miss a mobile tap when the header-level touch completed but the inner button node did not receive the finishing event cleanly
- `vms-entitlements-public.css` was still resetting the progressive control-size variables back to `38px`, which erased the larger touch-target work from the main ticketing stylesheet on public event pages that load both stylesheets

## Changes

- Reduced the native mobile ticket touch-fallback delay so VMS can step the quantity sooner when Chrome delays synthesized click completion.
- Suppresses the later synthetic click only when the touch fallback already applied the quantity change, avoiding double increments while keeping truly fast native clicks untouched.
- Broadens progressive section touch handling to the full header surface with shared dedupe across header/button listeners.
- Syncs mobile progressive control sizing in both `vms-ticketing-front.css` and `vms-entitlements-public.css`.
- Enlarges native mobile public ticket steppers to `44px`, aligns the qty input height to `44px`, and adds touch-action/tap-highlight hardening to the progressive header and native ticket controls.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `assets/vms-ticketing-front.js`
- `assets/vms-ticketing-progressive-ui.js`
- `assets/css/vms-ticketing-front.css`
- `assets/css/vms-entitlements-public.css`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.684.md`
- `vms-test-plan-0.2.24.684.md`

## Validation Performed

- `node --check assets/vms-ticketing-front.js`
- `node --check assets/vms-ticketing-progressive-ui.js`
- `node --check assets/vms-ticketing-front-server-controls.js`
- Mobile Playwright probe against the real staging event page with local VMS JS/CSS hot-swapped into the live DOM:
  - native ticket `+` rendered at `44x44`
  - qty changed from `0` to `1` within 250ms of tap
  - add-ons accordion expanded on tap

## Notes / Caveats

- The staging page still contains a separate inline script that throws `Uncaught ReferenceError: TICKETS_SEL is not defined` from the event-page HTML itself. That script is not part of the VMS bundle and still needs to be corrected or removed at its source.
