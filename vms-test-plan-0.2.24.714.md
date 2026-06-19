# VMS 0.2.24.714 Test Plan — Staff Eligibility Patch

## A. Package / version

1. Confirm the release ZIP contains one canonical top-level folder: `vms/`.
2. Confirm `vms/vendor-management-system.php` reports `Version: 0.2.24.714`.
3. Confirm `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.714`.
4. Confirm `vms/vms-build.txt` begins with `0.2.24.714`.

## B. Static validation

Run from the unpacked plugin directory:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
php -l includes/core/staffing.php
php -l includes/cpt/event-plans.php
php -l includes/cpt/event-plans/partials/staff.php
php -l tests/event-plan-staff-eligibility.php
zip -T ../vms-0.2.24.714-staff-eligibility-hotfix.zip
```

## C. WordPress-backed local regression

```bash
php -d memory_limit=512M \
  -d mysqli.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' \
  -d pdo_mysql.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' \
  tests/event-plan-staff-eligibility.php
```

Expected result: `event plan staff eligibility regression tests: PASS`

## D. Local admin smoke

Use a disposable Event Plan clone and open the Staff section.

1. Confirm the Bartender / Bar role no longer lists every staff record by default.
2. Confirm only explicitly role-matched, hard-block-qualified bar staff appear as fresh candidates.
3. Confirm Cleanup does not pull in bar-only staff unless they also match Cleanup explicitly.
4. Confirm an already-assigned but now-ineligible staff member still renders checked with a warning instead of disappearing.
5. Confirm saving the plan does not silently remove that already-assigned warning case.

## E. Staging smoke

After local validation passes and the staging candidate is installed:

1. Open an Event Plan with staffing configured.
2. Expand the Staff section and confirm the candidate list loads without PHP or JS errors.
3. Confirm the Bartender / Bar role is filtered and does not show the full employee pool.
4. Confirm lazy-load for the Staff section still works.
5. Confirm vendor, lineup, and ticketing preservation behavior from `.712` / `.713` is unchanged.

## F. Guardrails

1. Do not deploy production.
2. Do not auto-remove saved staff assignments as part of this patch.
