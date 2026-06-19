## 0.2.24.577 Test Plan — staffing template UI parity + layout

### Goal
Verify the Staffing Template editor now matches Event Plan timing behavior and no longer uses a single unreadable horizontal row.

### Smoke
1. Activate/upload 0.2.24.577.
2. Open **VMS → Staffing Templates**.
3. Confirm the page loads without fatal errors.
4. Open an existing template and confirm existing slot values render.

### Template slot layout
1. Confirm each template slot renders as a multi-row card, not one long table row.
2. Confirm Row 1 contains Role / Staff needed / Time mode.
3. Confirm Row 2 contains timing controls.
4. Confirm Row 3 contains Pay type / Rate / Optional / Notes / Remove.
5. Confirm the card remains readable on standard desktop widths.

### Absolute / Relative parity
1. In a template slot, set **Time mode = Absolute**.
2. Confirm only **Shift start / Shift end / Duration** are visible.
3. Switch to **Relative**.
4. Confirm **Shift start / Shift end** hide.
5. Confirm **Start anchor / Start offset / End anchor / End offset / Duration** show.
6. Switch back to **Absolute** and confirm the template row updates immediately.

### Duration path
1. In **Absolute** mode, enter **Shift start** plus **Duration**.
2. Confirm **Shift end** hides once Duration is present.
3. Save template.
4. Reopen and confirm values persisted.
5. Repeat in **Relative** mode using **Start anchor / Start offset / Duration**.
6. Confirm relative end fields hide while Duration is present.
7. Save and confirm persistence.

### Add/remove row behavior
1. Add a new slot.
2. Confirm the new slot gets the same card layout and timing behavior.
3. Remove a slot when more than one exists.
4. Confirm removal works.
5. Confirm the last remaining slot cannot be removed accidentally.

### Regression checks
1. Apply a saved template on an Event Plan.
2. Confirm Event Plan timing controls still behave correctly.
3. Confirm template save/edit/apply still works after the UI refactor.
4. Confirm no PHP notices/fatal errors appear during save.
