# VMS 0.2.24.713

## Scope

- Event Plan editor hotfix for collapsible wrapper boundaries around Primary Vendor Compensation and the deferred Secondary Vendors, Staff, and Readiness sections.
- Readiness Details boot-state fix so unloaded lazy details start collapsed and the first click loads content instead of collapsing the shell.
- Preserves the `.712` vendor/state blank-save protections without reopening save-path behavior.

## What changed

- Updated the shared Event Plan collapsible wrapper logic to stop before:
  - `h4`
  - `[data-vms-collapsible-break]`
  - `.vms-collapsible-section[data-section-key]`
  - `.vms-ep-card--readiness-summary`
- Added an explicit server-rendered break immediately after the Primary Vendor Compensation partial.
- Marked the Readiness Summary card as an explicit collapsible boundary.
- Forced lazy/unloaded sections to initialize collapsed, including `readiness_details`, before any saved local toggle state is applied.
- Kept the `.712` preservation logic intact for primary vendor, primary lineup vendor, secondary vendors, staff, and deferred/unloaded saves.

## Files changed

- `includes/cpt/event-plans.php`
- `includes/cpt/event-plans/partials/compensation.php`
- `includes/cpt/event-plans/partials/editor-scripts.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.713.md`
- `vms-test-plan-0.2.24.713.md`

## Local verification summary

- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `php -l includes/cpt/event-plans.php`
- `php -l includes/cpt/event-plans/partials/secondary-vendors.php`
- `php -l includes/cpt/event-plans/partials/time-lineup.php`
- `php -l includes/cpt/event-plans/partials/compensation.php`
- `php -l includes/cpt/event-plans/partials/editor-scripts.php`
- `php -l tests/event-plan-editor-vendor-preservation.php`
- `node --check assets/js/vms-lineup-schedule-admin.js`
- WordPress-backed preservation harness passed locally with Local socket overrides:
  `php -d memory_limit=512M -d mysqli.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' -d pdo_mysql.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' tests/event-plan-editor-vendor-preservation.php`
- Headless local admin probe on disposable Event Plan `2309` confirmed section order:
  `compensation -> secondary_vendors -> staff -> readiness_summary -> readiness_details -> cancellation`
- The same probe confirmed:
  - Secondary Vendors is not inside Compensation and lazy-loads the editable selector rows on first click.
  - Staff is not inside Compensation and lazy-loads the staffing editor on first click.
  - Readiness Summary and Readiness Details are not inside Compensation.
  - Readiness Details starts collapsed with `data-vms-lazy-loaded="0"` and loads/opens on the first click.

## Packaging note

- Package this candidate as `VMS 0.2.24.713` for staging review first.
- No staging or production deployment is included in this package step.
