# VMS 0.2.24.588 Test Plan — Admin Menu Shell Repair + Data Tools Shell Integration

🚨 If Codex makes any code changes during testing, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision log, handoff notes, this test plan or a follow-up test plan, and the package filename before returning a replacement zip.

## Scope

This pass repairs the 0.2.24.587 Guided Tours fatal, improves Data Tools integration into the VMS admin shell, preserves the clean nine-entry VMS left rail, and applies a small top-navigation dropdown width polish.

## Version checks

1. Confirm `vendor-management-system.php` header is `0.2.24.588`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.588`.
3. Confirm `vms-build.txt` contains `0.2.24.588`.

## Guided Tours fatal repair

1. Open `wp-admin/admin.php?page=vms-guided-tours`.
2. Confirm HTTP 200.
3. Confirm no PHP fatal related to `vms_admin_ui_render_shell()` or private/non-callable callbacks.
4. Confirm the Guided Tours page renders inside the VMS shell/top navigation.
5. Confirm the settings form and registered tours table still appear.

## Data Tools integration

1. Open `wp-admin/admin.php?page=vms-data-tools`.
2. Confirm HTTP 200.
3. Confirm the page renders with the VMS admin shell/top navigation/theme.
4. Confirm the WordPress left parent highlights VMS, not Tools.
5. Confirm the Data Tools content still appears and its internal tool cards/links remain usable.
6. Confirm direct access through All VMS Pages still opens Data Tools.
7. Confirm `Tools > VMS Data Tools` is no longer shown if that legacy submenu was present, while the direct URL still works.

## Left rail regression

1. Confirm the VMS left rail still shows exactly the durable section entries:
   - Dashboard
   - Events & Schedule
   - Tickets & Admissions
   - Vendors & Staff
   - Marketing & Sales
   - Reports & Finance
   - Venue Setup
   - Tools & Integrity
   - Settings & Add-ons
2. Confirm hidden/module/add-on pages do not flood the visible left rail.
3. Confirm All VMS Pages still renders and lists hidden/direct pages such as:
   - `vms-data-tools`
   - `vms-square-sync-protection`
   - `vms-ticket-integrity`
   - `vms-email-followups`
   - `vms-add-dispatch`
   - `vms-guided-tours`

## Direct URL smoke checks

Confirm these URLs return HTTP 200 and keep VMS as the active parent where applicable:

- `admin.php?page=vms-admin-pages`
- `admin.php?page=vms-data-tools`
- `admin.php?page=vms-guided-tours`
- `admin.php?page=vms-square-sync-protection`
- `admin.php?page=vms-ticket-integrity`
- `admin.php?page=vms-email-followups`
- `admin.php?page=vms-add-dispatch`
- `admin.php?page=vms-settings`

## Top-navigation polish

1. Hover or focus the top-nav tabs with quick menus, especially Settings and Tools.
2. Confirm dropdown panels line up with the width of the corresponding tab.
3. Confirm long item labels wrap or remain readable rather than forcing the dropdown wider than the tab.
4. Confirm keyboard focus still opens the quick menu.

## Console/log checks

1. Confirm no PHP fatals or warnings introduced by this patch.
2. Confirm no missing callback errors in All VMS Pages.
3. Confirm no console-blocking JavaScript errors on All VMS Pages, Guided Tours, or Data Tools.
