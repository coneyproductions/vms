# CODEX Handoff — VMS 0.2.24.590 Menu Pinned Pages

## Purpose
Fix compact VMS admin menu behavior that left high-use direct pages registered but visually hidden in the WordPress left menu.

## Changes
- Pinned `vms-event-command-center` so **VMS > Event Command Center** remains visible after compact left-rail refactors.
- Pinned `vms-square-sync-protection` so **VMS > Square Sync Protection** remains visible after compact left-rail refactors.
- Added both slugs to the VMS parent/submenu highlighting map.
- Updated version markers to `0.2.24.590`.

## Files changed
- `includes/admin-ui/nav.php`
- `includes/admin/menu.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`

## Test focus
🚨 Browser/admin menu verification is required.

1. Install on staging.
2. Confirm **VMS > Event Command Center** appears in the left WordPress VMS menu.
3. Confirm `/wp-admin/admin.php?page=vms-event-command-center` returns HTTP 200 and renders the command center picker/page.
4. Confirm **VMS > Square Sync Protection** appears in the left WordPress VMS menu.
5. Confirm `/wp-admin/admin.php?page=vms-square-sync-protection` returns HTTP 200 and shows scan/repair controls.
6. Confirm compact menu layout still keeps the main VMS section links tidy and does not reintroduce a long unorganized submenu.
7. Confirm **All VMS Pages** still lists hidden/direct pages.

## Notes
This pass does not change ticketing, event plan save behavior, Square payment processing, WooCommerce product sync settings, or the Square Sync Protection scan/repair logic.
