# VMS Test Plan — 0.2.24.543

## Focus
Make checkbox-mode reserved add-ons look and behave like real native checkboxes instead of faux number inputs or toggle pills.

## Test
1. Open an event with a checkbox-mode add-on and a normal stepper add-on.
2. Confirm the checkbox-mode add-on shows a native checkbox with a simple text label.
3. Check it. Confirm the row highlights and the note says selected/uncheck to remove.
4. Uncheck it. Confirm it clears immediately.
5. Confirm no plus/minus controls are visible for checkbox mode.
6. Confirm normal stepper add-ons still show minus / quantity / plus.
7. Confirm checkbox mode still respects qualifying-ticket requirements.
8. Confirm help text still appears in the correct place above tickets and add-ons.
