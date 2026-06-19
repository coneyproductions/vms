# VMS Test Plan — 0.2.24.504

## Scope
Single isolated behind-the-scenes refactor on top of 0.2.24.503.

## Verify
1. Open an Event Plan editor.
2. Confirm the overall Event Plan layout still matches 0.2.24.503.
3. Expand/collapse **Staff** and confirm the section renders normally.
4. Confirm staffing roles, assigned staff, tax badges, and threshold/headcount messaging still appear normally.
5. Save Draft and reload the same Event Plan.
6. Re-open Staff and confirm assignments/check states persist visually.
7. Check `debug.log` if enabled for any new `event-plans.php` warnings/fatals.

## Guardrails
- No CSS changes
- No layout changes
- No save-path changes
- Refactor scope limited to staff render-context preparation only
