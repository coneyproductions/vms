# VMS Module Boundaries (Stabilization Pass 10)

## Goal
Reduce ambiguity about where new work belongs so future passes do not reintroduce loader drift.

## Canonical live entry path
1. `backstage-venue-manager.php`
2. `includes/bootstrap.php`
3. area loaders under `includes/*/load.php`
4. support loader under `includes/support/load.php`
5. module loader under `includes/modules/load.php`

## Canonical admin bootstrap
- `includes/admin/load.php`

Do not add new admin feature wiring to `admin/load.php`.
That file is a compatibility shim only.

## Canonical portal bootstrap
- `includes/portal/load.php`

Portal feature files own their shortcode registration.
The loader only includes live portal surfaces.

## Canonical support bootstrap
- `includes/support/load.php`

Support-only subsystems such as Docs and Data Tools should enter through this loader, not through one-off root includes.

## Canonical docs bootstrap
- `includes/docs/load.php`

Do not wire docs feature files directly from `includes/bootstrap.php`.
Keep docs registry/render/public wiring inside the docs loader.

## Canonical module bootstrap
- `includes/modules/load.php`

Do not treat `includes/modules/loader.php` as the active development target anymore. It is a compatibility shim only.

## Canonical public bootstrap
- `includes/public/load.php`

Public shortcodes/templates should be wired here through canonical includes.

## Compatibility shims
These files may remain present, but should not become active development targets:
- `vms.php`
- `includes/core/bootstrap.php`
- `admin/load.php`
- `admin/docs-page.php`
- `includes/portal/vendor-profile.php` (legacy shim)
- `includes/modules/loader.php`

## Staged / placeholder-backed areas
These areas may exist in the zip but are not the main target for new feature wiring until promoted:
- Dashboard phase placeholder subviews in `includes/admin/menu.php`
- Ops Console placeholder-backed sections
- Safety subsystem present in code but not in canonical bootstrap
- Express Bar extracted to standalone module

## Rule for future passes
When adding or modifying a feature:
1. wire it through the canonical area/support loader
2. keep shims delegating only
3. avoid duplicate registration paths
4. document staged/dormant status in `docs/04-module-status-audit.md` when relevant

## Docs admin page
- Canonical path: `includes/admin/docs-page.php`
- Compatibility shim: `admin/docs-page.php`
- Do not wire the docs admin page directly from `includes/bootstrap.php`. It belongs under the canonical admin loader.
