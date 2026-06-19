# VMS Loader Map

This file documents the intended live bootstrap path for the current stabilization line.

## Canonical live entry

1. `vendor-management-system.php`
2. `includes/bootstrap.php`
3. area loaders:
   - `includes/core/load.php`
   - `includes/integrations/load.php`
   - `includes/rest/load.php`
   - `includes/public/load.php`
   - `includes/portal/load.php`
   - `includes/social-share/load.php`
4. admin-only:
   - `includes/admin/load.php`
5. support loader:
   - `includes/support/load.php`
6. module loader:
   - `includes/modules/load.php`

## Compatibility shims

These files still exist so older references do not fatal, but they should not be treated as primary load paths:

- `vms.php`
- `includes/core/bootstrap.php`
- `admin/load.php`
- `admin/docs-page.php`
- `includes/modules/loader.php`

Both bootstrap shims now delegate directly to the canonical bootstrap chain.

## Intentionally not in canonical live core bootstrap

These subsystems are present in the codebase but are not part of the canonical live loader in this stabilization line:

- `includes/safety/*`
- Express Bar admin/public remnants in core

## Staged admin placeholders still visible in navigation

These admin surfaces currently act as staged/fallback pages rather than full live modules:

- Teams
- Alert Presets
- dashboard subviews for Operations / Finance / Onboarding & Health

## Loader rule

When adding or moving code:

- feature files should not include other feature files
- area/support loaders own their local feature includes
- compatibility shims should delegate to canonical loaders, not rebuild their own include stacks

## Portal loader note

Portal shortcodes are now registered by their feature files:

- `includes/portal/vendor-portal.php`
- `includes/portal/staff-portal.php`

The portal loader should stay as a pure include file so shortcode ownership remains obvious.

## Docs loader note

Docs system bootstrapping now lives behind:

- `includes/docs/load.php`

`includes/bootstrap.php` should not directly include docs feature files anymore.

## Admin docs page boundary
- Canonical owner: `includes/admin/docs-page.php`
- Legacy shim: `admin/docs-page.php`
- The docs admin page should be loaded from `includes/admin/load.php`, not directly from top-level bootstrap.

## Support loader note

Support-only subsystems now enter through:

- `includes/support/load.php`

That keeps `includes/bootstrap.php` from accumulating one-off support includes over time.

## Module loader note

Canonical module bootstrap now lives in:

- `includes/modules/load.php`

`includes/modules/loader.php` remains only as a compatibility delegate.
