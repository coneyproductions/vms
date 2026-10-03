# VMS Express Bar 0.6.13 Test Plan

## Goal

Validate automatic event selection on the dedicated public Express Bar page.

## Setup

Create or identify these VMS Event Plans:

1. A past event with Express Bar enabled.
2. A same-day/current event with Express Bar enabled.
3. A future event with Express Bar enabled.
4. A closer/future event with Express Bar disabled.

## Tests

### 1. Same-day/current default

- Visit `/express-bar/` with no `event_plan_id`.
- Confirm the same-day/current Express Bar-enabled event is selected.

### 2. Future fallback

- Disable Express Bar on the same-day/current event or move it to the past.
- Visit `/express-bar/` with no `event_plan_id`.
- Confirm the next upcoming Express Bar-enabled event is selected.

### 3. Past events excluded

- Confirm past Express Bar events are not selected automatically.

### 4. Disabled events excluded

- Confirm future events without Express Bar enabled are ignored.

### 5. Explicit event override

- Visit `/express-bar/?event_plan_id={VALID_ENABLED_EVENT_ID}`.
- Confirm the explicit event is shown even if another event would be auto-selected.

### 6. No eligible event closed state

- Disable Express Bar on all eligible events or remove their future dates.
- Visit `/express-bar/`.
- Confirm a clean Express Bar Closed / no active menu state appears.

## Regression Checks

- Public event-page CTA links still include the event-specific `event_plan_id`.
- Express Bar add-to-cart still respects the selected event ID.
- Browse-only behavior remains unchanged when ordering opens is blank.
- Direct event-specific shortcode `[vms_express_bar_menu event_plan_id="123"]` still works.

## Expected Result

The dedicated Express Bar page should no longer appear to choose a random event. It should pick today/current first, then nearest upcoming enabled event, and otherwise show closed.
