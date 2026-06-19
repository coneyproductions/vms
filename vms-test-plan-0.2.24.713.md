# VMS 0.2.24.713 Test Plan — Event Plan Collapsible Structure Patch

## A. Package / version

1. Confirm the release ZIP contains one canonical top-level folder: `vms/`.
2. Confirm `vms/vendor-management-system.php` reports `Version: 0.2.24.713`.
3. Confirm `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.713`.
4. Confirm `vms/vms-build.txt` begins with `0.2.24.713`.

## B. Static validation

Run from the unpacked plugin directory:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
php -l includes/cpt/event-plans.php
php -l includes/cpt/event-plans/partials/secondary-vendors.php
php -l includes/cpt/event-plans/partials/time-lineup.php
php -l includes/cpt/event-plans/partials/compensation.php
php -l includes/cpt/event-plans/partials/editor-scripts.php
php -l tests/event-plan-editor-vendor-preservation.php
node --check assets/js/vms-lineup-schedule-admin.js
zip -T ../vms-0.2.24.713-collapsible-section-structure-hotfix.zip
```

## C. WordPress-backed preservation harness

Run the existing `.712` preservation harness unchanged:

```bash
php -d memory_limit=512M \
  -d mysqli.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' \
  -d pdo_mysql.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' \
  tests/event-plan-editor-vendor-preservation.php
```

Expected result: `Event Plan editor vendor preservation test passed.`

## D. Local admin DOM / lazy-load smoke

Use a disposable local Event Plan clone with:

- existing primary vendor
- existing primary lineup row
- 19:00-21:00 times
- secondary vendor type `food_truck`
- one selected food truck

Expected after page settle:

1. Primary Vendor Compensation closes before Secondary Vendors begins.
2. Secondary Vendors is not inside Compensation.
3. Staff is not inside Compensation.
4. Readiness Summary is not inside Compensation.
5. Readiness Details is not inside Compensation.
6. Readiness Details starts collapsed while `data-vms-lazy-loaded="0"`.

Expected on interaction:

1. Clicking Secondary Vendors loads the editor rows and reinitializes editable controls.
2. Clicking Staff loads the staffing editor and its interactive controls.
3. Clicking Readiness Details loads details on the first click and leaves the section expanded.

## E. Regression

1. Reconfirm blank/missing primary vendor fields do not clear saved primary vendor or primary lineup vendor without explicit clear intent.
2. Reconfirm blank/missing secondary vendor fields do not clear saved secondary vendor type or IDs without explicit clear intent.
3. Reconfirm deferred/unloaded staff and ticketing sections preserve existing saved data on normal saves.
4. Reconfirm explicit clear controls still work only when the clear-intent token/control is posted.
5. Confirm no staging, production, SSH, or remote changes are performed as part of this package validation.
