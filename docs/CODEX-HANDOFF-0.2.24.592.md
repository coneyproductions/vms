# CODEX HANDOFF — VMS 0.2.24.592 Compact menu discovery correction

## Summary
This build corrects the 0.2.24.591 admin-menu overcorrection. The WordPress VMS left rail should remain concise and section-like. Event Command Center and Square Sync Protection should be discoverable through the VMS top navigation, All VMS Pages, and direct URLs — not as extra forced left-menu items.

## Key files
- `includes/core/registry/admin-menu.php`
- `includes/admin-ui/nav.php`
- `docs/test-plan-0.2.24.592-compact-menu-discovery-correction.md`

## Required staging checks
🚨 Confirm the following before production use:

1. The WordPress VMS left menu remains compact and does not expand back into the full legacy page list.
2. Event Command Center is available from the VMS top nav / Planning discovery path.
3. `admin.php?page=vms-event-command-center` returns HTTP 200 and renders in the VMS shell/top nav.
4. Square Sync Protection is available from the VMS top nav / Tools discovery path.
5. `admin.php?page=vms-square-sync-protection` returns HTTP 200 and renders in the VMS shell/top nav.
6. All VMS Pages lists both Event Command Center and Square Sync Protection.
7. Data Tools remains under the VMS shell/top nav and does not reappear as a legacy Tools submenu.

## Notes
This is a menu/discovery correction only. It should not alter ticketing, Square payment processing, Square catalog sync behavior, Event Plan saves, or Express Bar product logic.

