# VMS 0.2.24.591 — Registry-driven left-menu visibility test plan

## Goal
Confirm compact VMS menu visibility is controlled by the admin menu registry, not one-off page exceptions.

## Checks
1. Confirm the plugin header, `VMS_VERSION`, and `vms-build.txt` all report `0.2.24.591`.
2. Confirm **VMS > Event Command Center** is visible in the left VMS menu.
3. Confirm `admin.php?page=vms-event-command-center` loads HTTP 200 and renders inside the shared VMS shell/top nav.
4. Confirm **VMS > Square Sync Protection** is visible in the left VMS menu.
5. Confirm `admin.php?page=vms-square-sync-protection` loads HTTP 200 and renders inside the shared VMS shell/top nav.
6. Confirm **VMS > All VMS Pages** still lists hidden/direct pages, including Data Tools, Ticket Integrity, Email Follow-Ups, ADD Dispatch, and Guided Tours.
7. Confirm the left VMS menu remains compact and does not regress into the full legacy submenu list.

🚨 Codex/staging required: verify the left-menu items visually after install because this is an admin-menu rendering fix.
