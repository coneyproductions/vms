# VMS Express Bar 0.6.9 Test Plan

🚨 Codex / browser testing recommended because this pass changes front-end focus/hover behavior.

## 1. Browse-only tooltip

1. Configure an Express Bar-enabled event where ordering is not open.
2. Visit the dedicated `/express-bar/` page.
3. Hover over a disabled quantity stepper.
4. Confirm the tooltip appears with the same ordering-window message shown on the page.
5. Move the cursor away.
6. Confirm the tooltip disappears and does not remain stuck.

## 2. Mouse click behavior

1. Click a disabled stepper.
2. Move the cursor away from the stepper.
3. Confirm the tooltip disappears instead of persisting due to focus.

## 3. Keyboard focus behavior

1. Tab to a disabled stepper wrapper.
2. Confirm the tooltip appears on visible keyboard focus.
3. Tab away.
4. Confirm the tooltip disappears.

## 4. Exact opening-time copy

1. Add a specific Express Bar opening time to the Event Plan.
2. Visit `/express-bar/` before that time.
3. Confirm item-row helper text and tooltip use the configured opening time.

## 5. Open ordering regression

1. Set the ordering window to currently open.
2. Confirm steppers work normally.
3. Confirm no disabled-stepper tooltip appears.
4. Add items and proceed to cart/checkout as expected.
