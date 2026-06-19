# Codex Handoff — VMS 0.2.24.699

## Build under test

- Plugin header: `0.2.24.699`
- `VMS_VERSION`: `0.2.24.699`
- `vms-build.txt`: `0.2.24.699`

## Change summary

- Added canonical Event Plan check-in-close persistence through `vms/includes/helpers/checkin-close.php`.
- Normal Event Plan save now stores explicit `_checkin_close_at`.
- Calendar publish and re-sync now copy `_checkin_close_at` to linked TEC events.

## Files changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `includes/core/registry/meta-keys.php`
- `includes/core/load.php`
- `includes/helpers/checkin-close.php`
- `includes/cpt/event-plans.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.699.md`

## Verification

- `php vms-ops-console-premium/tests/php/test-checkin-close-persistence.php`
- Local production-like publish flow verified explicit `_checkin_close_at` on both `vms_event_plan` and linked `tribe_events`.
