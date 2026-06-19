# VMS Test Plan — 0.2.24.586 Admin Menu Rail Tightening

🚨 **Codex/staging required:** This patch changes wp-admin menu behavior. Test on staging before relying on it in production.

## Version checks

- Confirm plugin header version is `0.2.24.586`.
- Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.586`.
- Confirm `vms-build.txt` contains `0.2.24.586`.

## Admin menu checks

1. Log in as an administrator.
2. Open wp-admin and inspect the VMS left menu.
3. Confirm the visible VMS submenu is limited to durable section pages:
   - Dashboard
   - Events & Schedule
   - Tickets & Admissions
   - Vendors & Staff
   - Marketing & Sales
   - Reports & Finance
   - Venue Setup
   - Tools & Integrity
   - Settings & Add-ons
4. Confirm legacy/module/add-on pages such as Meta Ads Builder, Data Tools, Referrals, Express Bar, ADD Dispatch, Staff Roles, and Square Sync Protection do not flood the left rail.
5. Open Tools & Integrity / All VMS Pages and confirm those pages are still listed and clickable from the directory.
6. Confirm direct URLs for hidden pages still resolve, for example:
   - `admin.php?page=vms-data-tools`
   - `admin.php?page=vms-square-sync-protection`
   - `admin.php?page=vms-ticket-integrity`
7. Confirm the VMS top navigation still exposes practical links inside each cluster.
8. Confirm no duplicate VMS parent menu is created.

## Add-on regression check

Create or enable a small test add-on that registers a VMS admin page directly under `vms-dashboard` or via `vms_register_admin_page()`. Confirm:

- The page does not flood the left rail by default.
- The page appears in All VMS Pages.
- The direct admin URL still renders.

## Failure protocol

If Codex makes any code changes during testing, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision log, handoff notes, this test plan or a follow-up test plan, and package filename before returning a replacement zip.
