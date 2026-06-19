# CODEX HANDOFF — VMS 0.2.24.591 Registry-driven left-menu visibility

## Summary
This build replaces the one-off visible-page exception behavior from the prior menu patch with registry-driven visibility. Pages that should appear in the WordPress VMS left menu now opt in through admin-menu registry metadata using `left_menu => true`.

## Key files
- `includes/core/registry/admin-menu.php`
- `includes/admin-ui/nav.php`
- `docs/test-plan-0.2.24.591-registry-left-menu-visibility.md`

## Required staging checks
🚨 Confirm the following before production use:

1. VMS left menu shows **Event Command Center**.
2. VMS left menu shows **Square Sync Protection**.
3. Both direct URLs load HTTP 200 and render in the VMS shell/top nav.
4. The compact menu remains compact; hidden/direct pages are still discoverable through **All VMS Pages**.
5. Data Tools remains under the VMS shell/top nav and does not reappear as a legacy Tools submenu.

## Notes
This is not intended to make every detected page visible in the left menu. The durable rule is: primary section pages and registry entries with `left_menu => true` are visible; everything else remains discoverable through top nav, All VMS Pages, and direct URL.
