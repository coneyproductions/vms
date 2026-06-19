# Codex Handoff — 0.2.24.577

## Scope
Follow-up staffing template editor polish after 0.2.24.576/575 validation.

## What changed
- Staffing Template slot editor now mirrors Event Plan timing behavior.
- Template slot timing fields now switch based on **Time mode**:
  - **Absolute** shows Shift start / Shift end / Duration
  - **Relative** shows Start anchor / Start offset / End anchor / End offset / Duration
- When **Duration** is entered, the current-mode end fields are hidden so the UI reads as an alternative path instead of “everything is required.”
- Template slot editor was rebuilt from one long horizontal row into a clearer multi-row responsive card layout.
- Template slot label **Headcount** was renamed to **Staff needed** for consistency with the Event Plan UI.

## Highest-risk areas to verify
1. Existing staffing templates still load and save.
2. Newly added template slots save with:
   - absolute timing
   - relative timing
   - duration-only end path
3. Switching time mode back and forth updates the visible fields immediately.
4. Add Slot / Remove behaves correctly and does not corrupt slot indexes.
5. Notes / pay type / rate / optional continue to persist.
6. Event Plan staffing UI still behaves exactly as before.

## Known intent
This pass is UI parity/polish only for the **template editor**. No schema changes were introduced.
