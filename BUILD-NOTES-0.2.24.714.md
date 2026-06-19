# VMS 0.2.24.714

## Scope

- Event Plan staffing candidate eligibility hotfix for the Staff section only.
- No vendor, lineup, ticketing, or collapsible-section preservation logic changes.

## What changed

- Staff assignment candidate lists now require an explicit role match before a staff member appears for that role.
- Roles with hard-block qualification rules now exclude unqualified staff from the candidate list instead of only rendering disabled checkboxes.
- Staff already assigned to a role remain visible when they are no longer eligible, with warning copy, so existing plans do not silently lose saved assignments.
- Save-time staffing validation now blocks newly posted ineligible assignments while preserving previously saved assigned exceptions until an operator removes them manually.
- Added a WordPress-backed regression harness for staffing eligibility and save behavior.

## Files changed

- `includes/core/staffing.php`
- `includes/cpt/event-plans.php`
- `includes/cpt/event-plans/partials/staff.php`
- `tests/event-plan-staff-eligibility.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.714.md`
- `vms-test-plan-0.2.24.714.md`

## Local verification summary

- `php -l includes/core/staffing.php`
- `php -l includes/cpt/event-plans.php`
- `php -l includes/cpt/event-plans/partials/staff.php`
- `php -l tests/event-plan-staff-eligibility.php`
- `php -d memory_limit=512M -d mysqli.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' -d pdo_mysql.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' tests/event-plan-staff-eligibility.php`

## Packaging note

- Package this candidate as `VMS 0.2.24.714` for staging review only.
- No production deployment is included in this build note.
