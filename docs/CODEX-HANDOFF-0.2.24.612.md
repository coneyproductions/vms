# CODEX HANDOFF — VMS 0.2.24.612 — Debug Log Cleanup

## Build

- Version: `0.2.24.612`
- Package: `vms-0.2.24.612-debug-log-cleanup.zip`

## Scope

This pass targets WordPress 6.7+ early translation notices and PHP 8.3 null deprecations that were still refilling `debug.log` after the 0.2.24.611 incident hardening release.

- Adds explicit core textdomain loading on `init`.
- Replaces early registry/schedule/help defaults with runtime-safe labels so VMS no longer triggers early `vms` translation notices during bootstrap.
- Stops registering hidden VMS submenu pages with `null` parents, which was feeding null values into WordPress admin title/path handling on PHP 8.3.
- Hardens admin-page title/menu-title normalization so registry-driven pages always provide meaningful non-empty strings.
- Keeps incident-hardening behavior for migrations, cron cleanup, and public-request guards intact.

## Files touched

- `vendor-management-system.php`
- `includes/core/registry/admin-menu.php`
- `includes/core/registry/constants.php`
- `includes/core/registry/statuses.php`
- `includes/tours/tours.php`
- `includes/admin-ui/nav.php`
- `includes/social-share/queue-runner.php`
- `includes/modules/staff-tasks/notifications.php`
- `vms-build.txt`
- docs listed below

## Test plan

- `docs/test-plan-0.2.24.612-debug-log-cleanup.md`

## Verification completed locally

- PHP lint pending after all plugin version/doc updates are finalized.
- Package integrity + local cron/log checks pending after packaging.

## Remaining validation

Run the mixed public/admin debug-log soak and cron checks from the test plan before promotion.
