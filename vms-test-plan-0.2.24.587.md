# VMS 0.2.24.587 Admin Menu Registry Metadata Test Plan

🚨 **Codex/staging smoke test required before production reliance.** This pass changes the admin-menu metadata layer and left-rail source of truth. It should not change business workflows, but the admin menu is foundational.

## Build/version checks

1. Confirm `vendor-management-system.php` header version is `0.2.24.587`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.587`.
3. Confirm `vms-build.txt` contains `0.2.24.587`.

## Left rail checks

1. Open wp-admin as an administrator.
2. Confirm the visible VMS left rail still shows the durable entries only:
   - Dashboard
   - Events & Schedule
   - Tickets & Admissions
   - Vendors & Staff
   - Marketing & Sales
   - Reports & Finance
   - Venue Setup
   - Tools & Integrity
   - Settings & Add-ons
3. Confirm legacy/module pages do not flood the left rail.
4. Confirm only one top-level VMS parent exists.

## All VMS Pages directory checks

Open `wp-admin/admin.php?page=vms-admin-pages`.

Confirm:

1. The page renders without fatal errors.
2. The health cards render.
3. The new search field appears above the table.
4. Searching by page name works, for example:
   - `square`
   - `data`
   - `ticket`
   - `follow`
   - `dispatch`
5. Search can be cleared and all rows return.
6. Previously hidden pages are still listed, including:
   - `vms-data-tools`
   - `vms-square-sync-protection`
   - `vms-ticket-integrity`
   - `vms-email-followups`
   - `vms-add-dispatch`
   - `vms-guided-tours`

## Direct URL checks

Confirm these direct admin URLs still resolve/render and are not broken by registry metadata:

- `admin.php?page=vms-data-tools`
- `admin.php?page=vms-square-sync-protection`
- `admin.php?page=vms-ticket-integrity`
- `admin.php?page=vms-admin-pages`
- `admin.php?page=vms-email-followups`
- `admin.php?page=vms-add-dispatch`
- `admin.php?page=vms-guided-tours`
- `admin.php?page=vms-settings`
- `edit.php?post_type=vms_event_plan`
- `edit.php?post_type=vms_vendor`
- `edit.php?post_type=vms_staff`
- `edit.php?post_type=vms_venue`

## Registry/add-on regression

1. Register a temporary runtime test page using direct `add_submenu_page('vms-dashboard', ...)`.
2. Confirm it remains hidden from the visible left rail but appears in All VMS Pages and opens by direct URL.
3. Register a temporary runtime test page using `vms_register_admin_page()` on `vms_admin_register_pages`.
4. Confirm it remains hidden from the visible left rail by default, appears in All VMS Pages, and opens by direct URL.
5. Confirm setting `left_menu => true` on the registry test page does not unexpectedly duplicate or displace the durable VMS section entries.

## Log/console checks

Confirm no new:

- PHP fatal errors
- missing callback errors
- white screens
- console-blocking JavaScript errors on VMS admin pages
- duplicate VMS top-level menus

## Expected result

PASS if the left rail remains clean, All VMS Pages is more useful/searchable, direct admin pages still work, and temporary add-on pages are discoverable without flooding the left menu.
