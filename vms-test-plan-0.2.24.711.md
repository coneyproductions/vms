# VMS 0.2.24.711 Test Plan — Premium Module Gate Fail-Closed

## A. Package / version

1. Confirm the release ZIP contains one canonical top-level folder: `vms/`.
2. Confirm `vms/vendor-management-system.php` reports `Version: 0.2.24.711`.
3. Confirm `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.711`.
4. Confirm `vms/vms-build.txt` begins with `0.2.24.711`.

## B. Static validation

Run from the unpacked plugin directory:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
php -l includes/modules/load.php
zip -T ../vms-0.2.24.711-premium-module-gate-fail-closed.zip
```

## C. Module registry behavior

Use the companion `vms-meta-ads` package for validation:

1. With an empty `vms_premium_modules_enabled` list, confirm `meta_ads_builder` stays locked.
2. With `meta_ads_builder` explicitly disabled, confirm it stays locked.
3. With `meta_ads_builder` explicitly enabled, confirm it loads.
4. Confirm an unregistered module slug returns disabled/fail-closed.
5. Confirm a registered module still flows through the existing licensing filters.
6. Confirm direct admin URLs for registered companion pages still work when the module is enabled.

## D. Regression

1. Confirm no PHP fatals occur during normal VMS admin boot.
2. Confirm no VMS admin-menu behavior changed beyond the expected existing compact-left-rail design.
3. Confirm no deployment or remote changes are performed as part of this package validation.
