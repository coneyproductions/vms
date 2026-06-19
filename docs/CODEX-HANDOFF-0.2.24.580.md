# CODEX HANDOFF — VMS 0.2.24.580

## Focus
Tight staffing follow-up patch for Event Plan guest-count sourcing and operator-facing wording.

## What changed
- Staffing headcount context now prefers a live ticket-sales read when ticket product links are available.
- Event Plan staffing summary and helper text now use operator-friendly wording like **Anticipated guests** and **Needed now**.
- Staffing alerts/template messaging were updated to remove internal wording such as **Current wired attendance** and **True headcount** from the Event Plan screen.

## Highest-risk areas to test
1. Event Plan staffing summary shows sold tickets instead of stale `0` when an event has live sales.
2. Events with no sales still behave sensibly and do not fatal.
3. Admissions-only events still show anticipated guests correctly.
4. Staffing helper copy / pills / warnings all reflect the new operator wording.
5. Existing staffing template alerts still trigger correctly.

## Notes
- This pass is intentionally narrow. It does not redesign staffing rules or thresholds.
- The goal is trustworthy guest-count sourcing plus cleaner operator copy on the Event Plan staffing surface.
