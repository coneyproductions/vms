# CODEX HANDOFF — VMS 0.2.24.627

🚨 **Please run this on staging before live deployment.**

## Goal

Repair the mobile progressive qualified-ticket guest-email layout after `0.2.24.626` so the registered guest email input stacks above the `Add Registered Guest` button again on phone widths, without reintroducing stepper alignment issues.

## What changed

- Repaired the mobile registered-guest claim row layout in the existing `@media (max-width: 782px)` ticket UI block.
- Increased selector specificity for the progressive claim-seat input wrapper so the mobile stack wins over the more general V2 claim-seat layout.
- Kept the `0.2.24.626` stepper alignment fix intact.
- Preserved the `Add Registered Guest` wording and the corrected Log In / Register button alignment.

## Files changed

- `assets/css/ticketing-front/80-ticket-ui-rewrite.css`
- `assets/css/vms-ticketing-front.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.627.md`
- `docs/test-plan-0.2.24.627-registered-guest-mobile-stack.md`
- `vms-test-plan-0.2.24.627.md`

## Version markers updated

- Plugin header version → `0.2.24.627`
- Core constant version → `0.2.24.627`
- `vms-build.txt` → `0.2.24.627`

## Validation already run locally

- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `php tests/check-package-integrity.php .`

## What Codex should verify on staging

1. On the public progressive ticket UI, select 1 Veteran Admission ticket while logged out.
2. Confirm the yellow Log In / Register note still renders correctly and the button labels remain centered.
3. Confirm the `Bringing a registered guest?` panel shows the registered guest email input stacked **above** the `Add Registered Guest` button on mobile widths.
4. Confirm the guest-email input/button area is not squeezed into a side-by-side row on phone widths.
5. Confirm native ticket and add-on `- / +` stepper controls remain vertically centered.
6. Confirm add-ons still gate/unlock correctly and add-to-cart still works.

