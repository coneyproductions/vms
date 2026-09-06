# VMS Data Tools 0.5.52 — Revenue Intelligence Hotfix

## Purpose

Fix the Revenue Intelligence fatal introduced in `0.5.51` by restoring the missing standard-bucket helper layer used by the Revenue Intelligence bucket-label and variation-override UI.

This is a hotfix only. It does not change accounting logic, Square classification, processor-cash labeling, or the deeper profitability engine.

## Root cause

`0.5.51` called `vms_dt_rr_standard_bucket_labels()` from the Revenue Intelligence admin page but the helper definition was not present in the shipped `page-revenue-intelligence.php` file. The same missing helper block also left these related helper calls undefined:

- `vms_dt_rr_get_variation_bucket_overrides()`
- `vms_dt_rr_sanitize_bucket_key()`
- `vms_dt_rr_update_variation_bucket_overrides()`

## Changes

- Restored `vms_dt_rr_standard_bucket_labels()` in the live Revenue Intelligence codepath.
- Restored the related variation-override helper cluster so Revenue Intelligence does not depend on backup files:
  - `vms_dt_rr_variation_override_option_name()`
  - `vms_dt_rr_allowed_bucket_keys()`
  - `vms_dt_rr_sanitize_bucket_key()`
  - `vms_dt_rr_get_variation_bucket_overrides()`
  - `vms_dt_rr_update_variation_bucket_overrides()`
- Kept the definitions wrapped in `function_exists()` guards for backward compatibility and safe re-entry.
- Bumped plugin/build version markers to `0.5.52`.

## Explicit non-goals

- No accounting or profitability math changes.
- No Square classification changes.
- No label/copy rewrite beyond what already shipped in `0.5.51`.
- No database schema, migration, or write-path changes.
- No staging or production hand edits.

## Files changed

- `vms-data-tools.php`
- `vms-build.txt`
- `includes/admin/page-revenue-intelligence.php`
- `docs/BUILD-NOTES-0.5.52.md`
- `docs/TEST-PLAN-0.5.52.md`

## Pairing note

This hotfix should be treated as `0.5.51` plus the missing Revenue Intelligence helper definitions. It is intended to be deployed as a direct replacement for the broken `0.5.51` package.
