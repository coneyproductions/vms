# VMS 0.2.24.711

## Scope

- Companion core patch for MAB `0.1.90`.
- Premium module enablement now fails closed when a module has not registered with the VMS module registry yet.
- No unrelated core behavior is intentionally changed in this package.

## What changed

- Added `vms_module_is_registered()` as an explicit registry probe for add-ons that need to defer privileged boot until the VMS module system is available.
- Changed `vms_module_is_enabled()` so an unregistered module now returns `false` instead of falling through as effectively enabled.
- Preserved the existing premium licensing filter flow for registered modules:
  - `vms_premium_modules_enabled`
  - `vms_premium_module_licensed`
  - `vms_module_enabled`
- Kept module loading behavior unchanged for registered non-premium and registered premium modules beyond the fail-closed registration check.

## Files changed

- `includes/modules/load.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.711.md`
- `vms-test-plan-0.2.24.711.md`
- `docs/CODEX-HANDOFF-0.2.24.711.md`

## Local verification summary

- `php -l includes/modules/load.php`
- Companion MAB `0.1.90` matrix confirmed locally:
  - empty premium list stays locked
  - explicitly disabled stays locked
  - explicitly enabled loads
  - VMS core missing keeps MAB locked
  - load-order changes still allow MAB to defer and load safely when enabled

## Packaging note

- This package is intended to ship together with `vms-meta-ads-0.1.90-phase-1b-safety-hardening.zip`.
- No deployment was performed in this step.
