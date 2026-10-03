# VMS Express Bar 0.6.6 Release Notes

## Focus

Dedicated Express Bar page handoff and event-page CTA conversion.

## Changes

- Bumped plugin version/build marker to `0.6.6`.
- Added dedicated Express Bar page detection:
  - saved page ID via `vmseb_public_page_id`
  - automatic lookup by `/express-bar/` slug
  - admin-side shortcode scan fallback
- Added one-click public page creation from **VMS → Bar Menu** when no page is detected.
- Added public page selector in **VMS → Bar Menu** so operators can reassign the Express Bar page without code.
- Changed event-page auto display from full menu embed to a compact CTA/link.
  - Event pages now point customers to the dedicated Express Bar page.
  - The full menu remains rendered by `[vms_express_bar_menu]` on the dedicated page.
- Added support for event-specific Express Bar links using `?event_plan_id=123`.
- Preserved browse-only behavior on the dedicated Express Bar page when ordering is closed.
- Added CTA/admin status styling.

## Notes

This pass intentionally leaves the full shortcode available for manual placement, but the preferred customer path is now the dedicated Express Bar page. Event pages should act as a pointer, not the primary menu surface.
