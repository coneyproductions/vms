# VMS Express Bar 0.6.15 Test Plan

## Focus
Verify the dedicated `/express-bar/` page selects the correct Event Plan when no `event_plan_id` query parameter is present.

## Setup
Create or confirm these Event Plans:
1. A past Express Bar-enabled event.
2. A future Express Bar-enabled event that appears first in the Event Plan admin list.
3. A today/current Express Bar-enabled event.
4. A non-Express-Bar event.
5. Optional: a cancelled/canceled Express Bar-enabled event.

## Tests
1. Load `/express-bar/` with no query parameter.
   - Expected: today/current Express Bar-enabled event is selected.
2. Disable Express Bar for today's event and reload `/express-bar/`.
   - Expected: next upcoming Express Bar-enabled event is selected by event date, not WP post order.
3. Re-enable today's event and load `/express-bar/?event_plan_id=<future_event_id>`.
   - Expected: explicit query parameter is honored.
4. Confirm non-Express-Bar events are ignored.
5. Confirm cancelled/canceled Event Plans are ignored.
6. Confirm past events are ignored.
7. Confirm shortcode `[vms_express_bar_menu]` on the dedicated Express Bar page still works without an embedded ID.

## Regression Checks
- Express Bar menu renders normally once the event is selected.
- Browse-only/open/closed messaging still follows the selected event's ordering window.
- Existing event-specific links from Event Plans still load the correct event with `?event_plan_id=`.
- PHP syntax checks pass for changed files.
