# VMS Express Bar 0.6.17 Test Plan

## Goal
Verify the dedicated `/express-bar/` page chooses the correct event context when no `event_plan_id` query parameter is present.

## Tests
1. Create or identify a non-cancelled Event Plan dated today/currently upcoming.
2. Leave its per-event Express Bar checkbox OFF.
3. Ensure another future Event Plan has Express Bar enabled and appears earlier in the Event Plan admin list.
4. Load `/express-bar/?nocache=1`.
5. Confirm the page title/subtitle uses today's/current Event Plan, not the first Express Bar-enabled future plan.
6. Enable the per-event checkbox for today's Event Plan and reload `/express-bar/?nocache=2`; confirm it still selects today's Event Plan.
7. Load `/express-bar/?event_plan_id=<known-enabled-event-id>` and confirm explicit query selection still wins.
8. Confirm event-page CTA behavior remains unchanged: it only appears for Event Plans with Express Bar enabled and auto-embed allowed.
9. Confirm cancelled/canceled Event Plans are ignored by auto-selection.
10. Confirm a closed/no active menu state appears if there are no non-past Event Plans.

## Notes
- This pass intentionally does not introduce global default ordering hours. Blank per-event open time should still show browse-only mode until ordering opens or a future default-hours feature is added.
