# Test Plan — 0.2.24.580 Staffing anticipated guests + copy cleanup

## Primary checks
- Open an Event Plan with known ticket sales and confirm the staffing summary shows those sold tickets as **Anticipated guests**.
- Confirm a stale or zero true-headcount field no longer overrides real ticket sales on the staffing screen.
- Confirm admissions/guest-entry counts still add into the anticipated guest total when present.
- Confirm staffing pills / helper text / warnings use operator wording (for example **Guests pending**, **Needed now**, **Needed at X+ guests**).
- Confirm staffing template alerts still render and use guest-count language.

## Regression checks
- Staffing screen loads with no fatal errors.
- Existing staffing template apply behavior remains intact.
- Role timing controls still save correctly.
- Qualification warnings/blocks still render correctly.
