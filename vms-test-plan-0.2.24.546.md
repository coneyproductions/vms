# VMS Test Plan — 0.2.24.546

## Scope
CSS rewrite pass for checkbox-style reserved add-ons in the live V2 ticket flow.

## Test Areas
1. **Correct live view**
   - Open the live event ticket page that shows reserved add-ons.
   - Confirm the checkbox styling applies on the production-facing V2 add-on view.

2. **Checkbox appearance**
   - Confirm checkbox-style add-ons display as a normal, unmistakable checkbox.
   - Confirm the checkbox is not oversized like a numeric control.
   - Confirm the label reads **Reserve**.

3. **Section border cleanup**
   - Confirm there is no extra border around the full reserved add-ons block/container.
   - Confirm individual add-on cards still retain their normal card styling.

4. **Behavior**
   - Check a checkbox-style add-on and confirm it selects cleanly.
   - Uncheck it and confirm it clears cleanly.

5. **Rule copy**
   - Confirm qualifying-rule helper text stays near the control area and remains prominent.

6. **Regression**
   - Confirm normal stepper-style add-ons still look and behave normally.
