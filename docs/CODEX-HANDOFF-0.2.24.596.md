# CODEX HANDOFF — VMS 0.2.24.596 Admin menu left-rail hardening

## Summary

This build fixes the wp-admin VMS left menu sprawl visible from non-VMS admin screens, such as the main WordPress Dashboard. The previous compact-menu pass marked secondary pages with a hidden CSS class, but the CSS lived in the VMS admin UI stylesheet, which only loads on VMS screens. That meant the left rail looked clean inside VMS pages and exploded again on normal wp-admin screens.

## Important

🚨 Test on staging before production.

## What changed

- The compact-menu pass now physically reduces `submenu['vms-dashboard']` to the durable VMS section launchers.
- The full pre-compaction submenu inventory is preserved in memory so **All VMS Pages** can still list/discover secondary pages.
- Direct URLs remain registered by WordPress; this changes menu visibility, not page access.
- A tiny global `assets/css/vms-admin-menu.css` file was added as a defensive fallback for legacy/add-on items still carrying the old hidden class.
- Version markers updated to `0.2.24.596`.

## Expected visible VMS left menu

When hovering or opening **VMS** from any wp-admin page, the visible submenu should be limited to:

1. Dashboard
2. Planning
3. Vendors & Staff
4. Marketing & Social
5. Venues
6. Settings
7. Tools

Secondary pages such as Event Plans, Vendor Availability, Season Dates, Guest Passes, Ticket Integrity, Square Sync Protection, Guided Tours, Data Tools, Meta Ads pages, and ADD Dispatch should remain reachable through the VMS top navigation, hub cards, All VMS Pages, or direct URL — but should not flood the WordPress left flyout.

## Primary URLs to test

- `wp-admin/index.php`
- `wp-admin/admin.php?page=vms-dashboard`
- `wp-admin/admin.php?page=vms-admin-pages`
- `wp-admin/admin.php?page=vms-square-sync-protection`
- `wp-admin/admin.php?page=vms-guided-tours`
- `wp-admin/admin.php?page=vms-data-tools`
- `wp-admin/edit.php?post_type=vms_event_plan`

## Notes

No ticketing, Square product sync, checkout, Event Plan save, Express Bar, or refund logic was intentionally changed.
