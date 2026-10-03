# VMS Express Bar 0.6.9 — disabled-stepper tooltip cleanup

This pass cleans up the browse-only disabled quantity tooltip after staging feedback.

## Changes

- Disabled item-row helper text now uses the active ordering-window message when an exact opening time is configured.
- Disabled stepper tooltip no longer sticks after a mouse click and cursor move-away.
- Keyboard users still get the tooltip on visible focus.
- Preserved dedicated Express Bar page behavior, browse-only menu rendering, category header styling, and disabled ordering controls.

## Notes

This is intentionally a small cosmetic/accessibility pass before Square catalog population and live menu-product setup.
