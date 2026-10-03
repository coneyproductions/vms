# VMS Express Bar 0.6.16 Test Plan

## Focus
Validate the dedicated `/express-bar/` page auto-selects the correct Event Plan when no `event_plan_id` query parameter is present.

## Steps
1. Confirm at least two Express Bar-enabled Event Plans exist:
   - one not happening today
   - one happening today or currently active
2. Load `/express-bar/` with no query string.
3. Confirm the selected event title is today's/current event, not simply the first Event Plan in the admin list.
4. Disable Express Bar on today's event and reload `/express-bar/`.
5. Confirm the page selects the next upcoming Express Bar-enabled event or shows a closed state if none exists.
6. Load `/express-bar/?event_plan_id=EVENT_ID` for a known enabled Event Plan.
7. Confirm the explicit event ID still overrides automatic selection.

## Regression Checks
- Event-specific shortcode embeds still render the specified event.
- Past/cancelled events are not selected automatically.
- Direct cart build/add-to-cart flow still uses the selected Event Plan ID.
