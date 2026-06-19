# VMS 0.2.24.593 — Menu heading alignment test plan

🚨 Codex/staging verification recommended before production.

## Purpose

Restore the intended admin information architecture after the compact-menu correction: the WordPress left VMS menu should stay concise and should use the same primary headings as the VMS top navigation instead of a separate naming scheme.

## Version checks

1. Confirm the plugin header, `VMS_VERSION`, and `vms-build.txt` all report `0.2.24.593`.

## Browser checks

1. Visit `wp-admin/admin.php?page=vms-schedule`.
2. Confirm the left VMS submenu is concise and shows the same primary headings as the top VMS nav:
   - Dashboard
   - Planning
   - Vendors & Staff
   - Marketing & Social
   - Venues
   - Settings
   - Tools
3. Confirm `Planning` is the highlighted left item while viewing Schedule.
4. Confirm the top VMS nav still shows the Planning cluster and Schedule subnavigation.
5. Confirm `admin.php?page=vms-event-command-center` returns HTTP 200, shows the VMS shell/top nav, and keeps Planning active.
6. Confirm `admin.php?page=vms-square-sync-protection` returns HTTP 200, shows the VMS shell/top nav, and keeps Tools active.
7. Confirm `admin.php?page=vms-admin-pages` returns HTTP 200 and still lists hidden/direct pages.
8. Confirm the left VMS menu did not expand back into a long list of individual pages.

## Regression checks

1. Data Tools direct URL still works if the companion plugin is active.
2. Guided Tours direct URL still works and remains discoverable through Settings/top nav or All VMS Pages.
3. Ticket Integrity direct URL still works and remains discoverable through top nav or All VMS Pages.
4. Existing page callbacks should not show “callback missing” for Event Command Center or Square Sync Protection.
