# VMS 0.2.24.592 — Compact menu discovery correction test plan

## Goal
Confirm feature-page discovery works without turning the WordPress VMS left rail back into a full page list.

## Checks
1. Confirm the plugin header, `VMS_VERSION`, and `vms-build.txt` all report `0.2.24.592`.
2. Confirm the WordPress left **VMS** menu remains compact and section-like; it should not show every VMS feature page as its own left-menu item.
3. Confirm **Event Command Center** is discoverable from the VMS top navigation under the Planning/Events discovery path.
4. Confirm `admin.php?page=vms-event-command-center` returns HTTP 200 and renders inside the shared VMS shell/top nav.
5. Confirm **Square Sync Protection** is discoverable from the VMS top navigation under the Tools discovery path.
6. Confirm `admin.php?page=vms-square-sync-protection` returns HTTP 200 and renders inside the shared VMS shell/top nav.
7. Confirm **All VMS Pages** lists both `vms-event-command-center` and `vms-square-sync-protection`.
8. Confirm **Data Tools** still renders in the VMS shell/top nav and does not reappear as a legacy Tools submenu.
9. Confirm no ticketing/Express Bar purchase flow behavior changed from this menu-only correction.

🚨 Codex/staging required: visually verify the left-menu compactness and the top-nav/dropdown discovery paths after install.

