# VMS Test Plan — 0.2.24.545

## Scope
Checkbox-style reserved add-on UI polish for the public ticket flow.

## Test Areas
1. **Checkbox presentation**
   - Open an event with checkbox-style reserved add-ons.
   - Confirm the control looks like a normal checkbox (not a faux number input).
   - Confirm the label reads **Reserve**.

2. **Qualifying-rule helper placement**
   - On desktop, confirm unlock/helper copy appears near the add-on control area, not buried in the description block.
   - On mobile, confirm the helper copy remains readable and stays associated with the control.

3. **Qualifying-rule emphasis**
   - With too few qualifying tickets selected, confirm the message reads like: `Requires 4 qualifying tickets • You have 3.` and is visually emphasized.
   - With enough qualifying tickets selected, confirm the message reads like: `Up to 1 allowed with your current tickets.` and is visually emphasized.

4. **Checkbox behavior**
   - Check a checkbox-style add-on and confirm the card enters a selected state.
   - Uncheck it and confirm it clears cleanly.
   - Confirm the selected helper reads `Selected. Uncheck to remove.`

5. **Non-checkbox add-ons**
   - Confirm quantity stepper add-ons still work normally.
   - Confirm their helper/status lines still update correctly.

6. **Atomic add to cart**
   - Add GA tickets plus a checkbox-style reserved add-on.
   - Confirm cart redirect succeeds and the selected add-on appears in cart.
   - Repeat after unchecking/removing the add-on to confirm no stale selection is carried forward.
