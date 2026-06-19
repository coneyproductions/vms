# VMS 0.2.24.712 Test Plan — Event Plan Vendor Preservation Hotfix

## A. Package / version

1. Confirm the release ZIP contains one canonical top-level folder: `vms/`.
2. Confirm `vms/vendor-management-system.php` reports `Version: 0.2.24.712`.
3. Confirm `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.712`.
4. Confirm `vms/vms-build.txt` begins with `0.2.24.712`.

## B. Static validation

Run from the unpacked plugin directory:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
php -l includes/cpt/event-plans.php
php -l includes/cpt/event-plans/partials/secondary-vendors.php
php -l includes/cpt/event-plans/partials/time-lineup.php
php -l tests/event-plan-editor-vendor-preservation.php
node --check assets/js/vms-lineup-schedule-admin.js
zip -T ../vms-0.2.24.712-event-plan-vendor-preservation-hotfix.zip
```

## C. WordPress-backed preservation harness

Preferred direct PHP path for this Local site:

```bash
php -d memory_limit=512M \
  -d mysqli.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' \
  -d pdo_mysql.default_socket='/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock' \
  tests/event-plan-editor-vendor-preservation.php
```

If the local PHP/WordPress runtime already has the correct socket and memory settings, `wp eval-file wp-content/plugins/vms/tests/event-plan-editor-vendor-preservation.php` is also acceptable.

Expected coverage:

1. Existing primary vendor plus blank `vms_band_vendor_id` without clear intent preserves `_vms_band_vendor_id`.
2. Existing primary lineup row plus blank primary vendor without clear intent preserves the row `vendor_id`, `set_start`, and `set_end`.
3. Explicit primary clear intent clears both the stored primary vendor and the primary lineup row vendor.
4. Valid new primary vendor ID still updates both the stored primary vendor and the primary lineup row vendor.
5. Existing secondary vendor type and IDs plus blank secondary fields without clear intent preserve `_vms_secondary_vendor_type` and `_vms_secondary_vendor_ids`.
6. Explicit secondary clear intent clears the stored secondary type, canonical IDs, and index meta.
7. Valid new secondary vendor type and selected IDs still update correctly.
8. Deferred/unloaded save sentinels preserve existing primary vendor, secondary vendor state, staff assignments, and ticketing meta.

## D. Admin smoke

1. Reproduce a post `2526` style plan locally if available:
   Save Draft, Mark Ready, and Publish Now must not clear an existing primary vendor, food-truck type, selected food truck, or primary lineup vendor when deferred sections were not loaded.
2. Reproduce a post `2528` style intact plan locally if available:
   confirm primary vendor, supporting food truck, staff assignments, and ticketing state survive a normal save when deferred sections remain unloaded.
3. In the Event Plan editor, use the explicit `Clear primary vendor` control and confirm the primary vendor and primary lineup vendor clear only after that action.
4. In Secondary Vendors, use the explicit `Clear secondary vendors` control and confirm the saved type and IDs clear only after that action.
5. Force a lazy-section load failure in dev tools or by breaking the AJAX request and confirm the failed section auto-expands with a visible inline error message.
6. Trigger `Load Full Ticketing Editor` and confirm the page reload scrolls back to the ticketing section and focuses the first interactive control there.

## E. Regression

1. Confirm valid primary vendor reassignment still works.
2. Confirm valid secondary vendor type and vendor-list reassignment still works.
3. Confirm supporting lineup rows still add, remove, reorder, and keep derived summary updates.
4. Confirm no unrelated staffing, readiness, or ticketing data is cleared when those deferred sections are not loaded into the DOM.
5. Confirm no deployment or remote changes are performed as part of this package validation.
