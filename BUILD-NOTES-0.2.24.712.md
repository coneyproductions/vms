# VMS 0.2.24.712

## Scope

- Event Plan editor hotfix for destructive blank-save behavior around primary vendor, primary lineup vendor, and secondary vendors.
- Deferred/lazy section failure visibility improvements for Secondary Vendors, Staff, Readiness details, and the full ticketing-editor reload path.
- No deployment, production mutation, or remote changes are included.

## What changed

- Added server-side submission resolvers so missing or blank deferred-section POST keys preserve existing Event Plan vendor state unless an explicit clear token is present.
- Stopped blank `vms_band_vendor_id` submissions from clearing `_vms_band_vendor_id` or forcing the primary lineup row vendor to `0` unless explicit clear intent is posted.
- Stopped blank `vms_secondary_vendor_type` and `vms_secondary_vendor_ids` submissions from clearing stored secondary vendor state unless explicit clear intent is posted.
- Added explicit primary and secondary vendor clear controls and clear-intent hidden fields so destructive clears require operator intent instead of normal blank form values.
- Centralized Secondary Vendors lazy-section initialization so a lazily injected section still binds add/remove/clear controls after AJAX load.
- Made lazy-section load failures auto-expand with a visible inline error message, and added ticketing-section scroll/focus after `vms_ep_load_section=ticketing_v2` reloads.
- Added a local WordPress-backed preservation harness covering blank-save preservation, explicit clears, valid reassignment, and deferred/unloaded save scenarios.

## Files changed

- `includes/cpt/event-plans.php`
- `includes/cpt/event-plans/partials/secondary-vendors.php`
- `includes/cpt/event-plans/partials/time-lineup.php`
- `assets/js/vms-lineup-schedule-admin.js`
- `tests/event-plan-editor-vendor-preservation.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.712.md`
- `vms-test-plan-0.2.24.712.md`

## Local verification summary

- `php -l includes/cpt/event-plans.php`
- `php -l includes/cpt/event-plans/partials/secondary-vendors.php`
- `php -l includes/cpt/event-plans/partials/time-lineup.php`
- `php -l tests/event-plan-editor-vendor-preservation.php`
- `node --check assets/js/vms-lineup-schedule-admin.js`
- WordPress-backed harness passed locally with one-off CLI overrides for the Local socket path and memory limit:
  `php -d memory_limit=512M -d mysqli.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' -d pdo_mysql.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' tests/event-plan-editor-vendor-preservation.php`
- The `wp` wrapper on this machine still defaulted to `/tmp/mysql.sock`, so direct `wp eval-file ...` was not used for the final pass.

## Packaging note

- Package this candidate as `VMS 0.2.24.712`.
- Review before any deployment.
