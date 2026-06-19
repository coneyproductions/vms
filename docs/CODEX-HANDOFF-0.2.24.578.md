# Codex Handoff — 0.2.24.578

## Scope
Tight staffing follow-up after 0.2.24.577.

## What changed
- Event Plan staffing now stops falling back to Staff Role default headcount for omitted roles once the event already has event-level staffing data or an applied staffing template.
- **Replace staffing from template** should now leave omitted roles at **0 / not in use** instead of showing them as required because of role defaults.
- Staffing headcount context now prefers **expected attendance** from ticket sales + admissions before falling back to true headcount.
- A saved true-headcount value of `0` should no longer override sold ticket counts on the staffing screen.
- When a template is applied in **Replace staffing from template** mode, stale staffing validation notices from pre-template defaults are suppressed for that save cycle.

## Highest-risk areas to verify
1. Apply a template with **Replace staffing from template** to an event that previously had other roles and confirm omitted roles no longer show required by default.
2. Save the Event Plan again after template apply and confirm omitted roles stay at `0` unless intentionally re-added.
3. Confirm blank/new events still use Staff Role defaults before any event-level staffing data exists.
4. For an event with sold tickets and `_vms_true_headcount = 0`, confirm the staffing screen shows the sold-ticket attendance source instead of `0 (True headcount)`.
5. Confirm admissions-only events still wire attendance.
6. Confirm true-headcount fallback still works when ticket/admission data is absent.
7. Confirm Event Plan staffing UI and template apply flow still load without fatal errors.

## Known intent
This pass is behavior correction only. No new schema changes were introduced.
