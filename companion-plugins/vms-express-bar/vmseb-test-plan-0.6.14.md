# VMS Express Bar 0.6.14 Test Plan

## Focus

Validate that `/express-bar/` no longer stays pinned to a stale shortcode event ID when the page is used as the dedicated public Express Bar endpoint.

## Tests

1. Edit the Express Bar page content to contain `[vms_express_bar_menu event_plan_id="OLD_EVENT_ID"]`.
2. Ensure a different Express Bar-enabled event is active today, or is the next upcoming eligible event.
3. Load `/express-bar/` with no query string.
4. Confirm the page auto-selects the active/today/next event, not `OLD_EVENT_ID`.
5. Load `/express-bar/?event_plan_id=OLD_EVENT_ID`.
6. Confirm the explicit query-string event ID is honored.
7. Place `[vms_express_bar_menu event_plan_id="OLD_EVENT_ID"]` on a normal non-dedicated test page.
8. Confirm the explicit shortcode still renders `OLD_EVENT_ID` on that non-dedicated page.
9. Confirm event page CTAs and existing direct event links still work.
10. Run `php -l` on edited PHP files.

## Expected

- Dedicated page without query auto-selects the correct event.
- Dedicated page with `?event_plan_id=` still honors the explicit event.
- Non-dedicated explicit shortcodes still work.
- No PHP syntax errors.
