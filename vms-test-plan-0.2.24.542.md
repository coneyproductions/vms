# VMS Test Plan — 0.2.24.542

## Focus
Validate checkbox-style add-ons in the public ticket UI.

## Test cases
1. Set one reserved add-on to checkbox mode.
2. Load the event page with no add-on selected. Confirm the label reads `Reserve`.
3. Check the add-on. Confirm:
   - the checkbox shows as checked
   - the label changes to `Reserved`
   - the row gets a clearer selected state
   - the note explains it can be unchecked to remove
4. Uncheck the add-on. Confirm it returns to the unselected state.
5. With the checkbox add-on selected, reduce qualifying tickets below the requirement and confirm the UI either prompts to adjust or allows removal without trapping the selection.
6. Verify normal stepper add-ons still increment and decrement normally.
7. Add to cart and confirm the selected checkbox add-on is included exactly once.
