# Codex Handoff — VMS 0.2.24.587 Admin Menu Registry Metadata

## Summary

This patch builds on the passing `0.2.24.586` admin-menu rail tightening pass.

It does **not** attempt a risky full rewrite of every direct `add_submenu_page()` call. Instead, it safely moves VMS closer to the future registry architecture by cataloging existing direct/legacy admin pages in the central registry while leaving their current callbacks and direct URLs intact.

## Main changes

- Added central left-rail section specs to `includes/core/registry/admin-menu.php`:
  - `vms_admin_menu_default_left_rail_specs()`
  - `vms_admin_menu_left_rail_specs()`
  - filter: `vms_admin_menu_left_rail_specs`
- Updated `includes/admin-ui/nav.php` so compact left-rail selection reads from the registry layer instead of owning its own hardcoded section spec array.
- Added registry metadata for many existing core/direct admin pages with `register => false` so discovery/section/source metadata lives in the registry without changing page callbacks yet.
- Added a client-side search field to **All VMS Pages** so hidden/module/add-on pages are easier to find.
- Updated version/build markers and docs.

## Important behavior expectations

- The visible VMS left rail should remain the same clean 9 durable entries verified in `0.2.24.586`.
- Direct URLs should keep working.
- Existing direct `add_submenu_page()` calls still own their page callbacks.
- Registry metadata should improve All VMS Pages classification/source labels without causing duplicate pages or left-rail sprawl.
- Add-ons should still register through `vms_admin_register_pages` / `vms_register_admin_page()`.

## Test plan

Run:

`docs/test-plan-0.2.24.587-admin-menu-registry-metadata.md`

🚨 This is an admin-menu smoke test, not a full VMS regression pass.

## Files touched

- `includes/core/registry/admin-menu.php`
- `includes/admin-ui/nav.php`
- `assets/js/vms-admin-ui.js`
- `assets/css/vms-admin-ui.css`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/vms_add-on_convention.md`
- `docs/test-plan-0.2.24.587-admin-menu-registry-metadata.md`
- `docs/CODEX-HANDOFF-0.2.24.587.md`
