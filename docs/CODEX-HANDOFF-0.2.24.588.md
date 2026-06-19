# Codex Handoff — VMS 0.2.24.588 Admin Menu Shell Repair + Data Tools Integration

## Baseline

Built from `vms-0.2.24.587-admin-menu-registry-metadata.zip`.

## Why this pass exists

The 0.2.24.587 test failed only on `admin.php?page=vms-guided-tours` because the page passed a private instance method to `vms_admin_ui_render_shell()`, whose second argument requires a callable. The user also flagged that Data Tools still feels separated from VMS and that top-nav dropdown widths do not line up crisply with their tabs.

## Code changes

- `includes/tours/class-vms-tours-admin.php`
  - Changed `render_page_content()` from private to public so it is a valid callable for the shared shell.

- `includes/admin-ui/nav.php`
  - Wrapped `vms_dt_render_tools_home()` inside `vms_admin_ui_render_shell()` when the Data Tools companion renderer exists.
  - Added helpers to map the current VMS page back to registry section metadata and the correct durable left-rail slug.
  - Added `parent_file` / `submenu_file` filters so VMS pages keep the VMS parent active.
  - Removes legacy `Tools > VMS Data Tools` submenu entry when present while preserving direct access.

- `includes/core/registry/admin-menu.php`
  - Added `section` metadata to durable left-rail specs.

- `assets/css/vms-admin-ui.css`
  - Changed quick-menu dropdown width from fixed/min-width behavior to match the owning tab width.

## Must-test items

Run:

`vms-test-plan-0.2.24.588.md`

Priority checks:

1. `admin.php?page=vms-guided-tours` no longer fatals.
2. `admin.php?page=vms-data-tools` renders inside the VMS shell/top navigation.
3. The WordPress left parent highlights VMS on Data Tools, not Tools.
4. Clean nine-entry VMS left rail remains unchanged.
5. All VMS Pages still lists hidden/direct pages.
6. Top-nav dropdowns match the width of their tabs.
