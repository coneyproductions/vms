# VMS Test Plan — 0.2.24.590 Menu Pinned Pages

🚨 Codex/staging browser check required.

## Version checks
- Plugin header shows `0.2.24.590`.
- `VMS_VERSION` shows `0.2.24.590`.
- `vms-build.txt` shows `0.2.24.590`.

## Admin menu checks
- Log in as an administrator.
- Open WordPress admin.
- Confirm **VMS > Event Command Center** is visible.
- Confirm **VMS > Square Sync Protection** is visible.
- Confirm the VMS left menu is still compact/tidy.

## Direct URL checks
- `/wp-admin/admin.php?page=vms-event-command-center` returns HTTP 200.
- `/wp-admin/admin.php?page=vms-square-sync-protection` returns HTTP 200.
- Both pages keep VMS as the active parent menu.

## Regression checks
- **VMS > Schedule** still opens.
- **VMS > All VMS Pages** still opens and lists direct/hidden pages.
- **VMS > Data Tools** still opens inside the VMS shell when available.
- No ticket purchase UI changes are expected.
