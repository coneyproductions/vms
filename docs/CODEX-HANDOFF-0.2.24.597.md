# CODEX HANDOFF — VMS 0.2.24.597 Admin menu direct URL access repair

## Summary

This build repairs the 0.2.24.596 regression where direct secondary VMS admin URLs could show the WordPress permissions screen. The prior build compacted `submenu['vms-dashboard']` during `admin_menu`; WordPress can still consult the submenu during plugin-page capability checks, so removing secondary pages that early can make valid admin URLs look unauthorized.

## Important

🚨 Test on staging before production.

## What changed

- Removed the `admin_menu` timing hook for `vms_admin_ui_compact_left_menu()`.
- Kept compaction on `admin_head`, after WordPress has already validated access to the requested admin page but before the visible left admin menu renders.
- Preserved the compact VMS left flyout behavior from non-VMS admin screens.
- Preserved direct access for secondary pages such as Square Sync Protection, Guided Tours, Data Tools, Ticket Integrity, and Event Command Center.
- Version markers updated to `0.2.24.597`.

## URLs to test

- `wp-admin/index.php`
- `wp-admin/admin.php?page=vms-admin-pages`
- `wp-admin/admin.php?page=vms-square-sync-protection`
- `wp-admin/admin.php?page=vms-guided-tours`
- `wp-admin/admin.php?page=vms-data-tools`
- `wp-admin/admin.php?page=vms-ticket-integrity`
- `wp-admin/admin.php?page=vms-event-command-center`
- `wp-admin/edit.php?post_type=vms_event_plan`

## Expected visible VMS left menu

When hovering/opening **VMS** from any wp-admin page, the visible submenu should stay limited to:

1. Dashboard
2. Planning
3. Vendors & Staff
4. Marketing & Social
5. Venues
6. Settings
7. Tools

## Notes

No ticketing, Square product sync, checkout, Event Plan save, Express Bar, refund, or public-facing behavior was intentionally changed.
