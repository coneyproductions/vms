# VMS Express Bar 0.6.8 Test Plan

🚨 Codex / browser testing recommended because this changes customer-facing hover/focus behavior and public menu CSS.

## 1. Version checks

- Confirm plugin header shows `0.6.8`.
- Confirm `VMSEB_VERSION` is `0.6.8`.
- Confirm `vms-build.txt` contains `0.6.8`.

## 2. Dedicated Express Bar page

- Open `/express-bar/` while the associated event has an Express Bar menu but ordering is not open.
- Confirm the menu still renders in browse-only mode.
- Confirm quantity controls remain disabled and cannot add items.

## 3. Disabled stepper tooltip

- Hover over the disabled `+` / quantity stepper area.
- Confirm a tooltip appears with the same ordering-window message, such as `Express Bar ordering opens at ...`.
- Keyboard-tab/focus the disabled stepper wrapper and confirm the message is also accessible on focus.
- Confirm the tooltip does not permanently remain after moving the mouse away or blurring focus.

## 4. Category header styling

- Confirm category headers are readable before hover.
- Hover category headers and confirm the text remains readable.
- Open/close category sections and confirm the active/open section remains readable.
- Test on desktop and mobile widths.

## 5. Ordering-open regression

- Set an event ordering window that is currently open.
- Confirm quantity steppers work normally.
- Confirm no disabled-stepper tooltip appears while ordering is open.
- Add an item, review order, and verify the cart/checkout path still works.
