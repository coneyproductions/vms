# Codex Handoff — VMS 0.2.24.586 Admin Menu Rail Tightening

## Purpose

Patch `0.2.24.585` correctly introduced the admin menu registry and All VMS Pages directory, but the WordPress left rail became too permissive: legacy/direct submenu pages remained visible and flooded the VMS sidebar.

This build tightens the behavior:

- Keep only durable VMS section landing pages visible in the WordPress left rail.
- Keep all legacy/module/add-on submenu entries registered so direct links continue to work.
- Hide non-primary entries with the existing `vms-admin-ui-menu-hidden` class.
- Keep discoverability through All VMS Pages and the VMS top navigation.
- Make registry-created pages directory/top-nav discoverable by default instead of left-menu visible by default.

## Files changed

- `includes/admin-ui/nav.php`
  - Changed compact-left-menu logic to hide every non-primary submenu entry unless explicitly allowed by filter.
  - Added `vms_admin_ui_compact_left_menu_force_visible_secondary_slugs` escape hatch for future intentional visible secondary pages.

- `includes/core/registry/admin-menu.php`
  - Changed registry default `left_menu` from `true` to `false`.
  - Explicitly kept `vms-admin-pages` as a left-menu-visible durable page.

- Version/build docs updated to `0.2.24.586`.

## Key behavior to verify

The VMS left menu should no longer show every module/add-on item. Expected visible items are the clean section entries only. Hidden items must remain available in All VMS Pages and through direct URLs.

## Test plan

See:

- `docs/test-plan-0.2.24.586-admin-menu-rail-tightening.md`
- `vms-test-plan-0.2.24.586.md`
