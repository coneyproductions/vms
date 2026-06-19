# VMS 0.2.24.675 Test Plan — Ticket Ratio Rules

## A. Version markers
1. Upload and activate the build.
2. Confirm these all show `0.2.24.675`:
   - VMS plugin header
   - `VMS_VERSION`
   - `vms-build.txt`

## B. Admin configuration smoke
1. Open a ticketed Event Plan on staging.
2. In Ticketing v2, create or edit two tickets:
   - Adult / General Admission, paid, enabled, **Counts toward add-on unlock** checked.
   - Child Admission, `$0`, enabled, **Counts toward add-on unlock** unchecked.
3. On Child Admission, enable **Limit by qualifying tickets** and set **Max per qualifying ticket** to `4`.
4. Save config.
5. Reload the Event Plan and confirm the child ticket ratio setting persists.

## C. Preview / sync smoke
1. Run Preview Sync.
2. Confirm no validation error is raised solely by the new ratio fields.
3. Push/Publish ticket changes on staging.
4. Confirm adult and child ticket products remain linked to the event.

## D. Public cart behavior
Run these as a logged-out/public buyer where possible:

| Scenario | Expected result |
| --- | --- |
| 0 Adult + 1 Child | Blocked with a qualifying-ticket message |
| 1 Adult + 4 Child | Allowed |
| 1 Adult + 5 Child | Blocked with ratio-limit message |
| 2 Adult + 8 Child | Allowed |
| 2 Adult + 9 Child | Blocked with ratio-limit message |

## E. Cart update / checkout guard
1. Add a valid combination, such as 1 Adult + 4 Child.
2. In cart, try increasing Child Admission to 5.
3. Confirm cart/checkout validation blocks the order.
4. Confirm reducing back to 4 clears the blocker.

## F. Regression checks
1. Existing fire pit/table add-on qualification still works.
2. Verified ticket login/approval validation still works.
3. Ticket max-qty-per-order still works.
4. Disabled pending-sync ticket guard still blocks stale disabled tickets.
5. Normal adult-only ticket purchase still works.

## Notes
This build adds server-side enforcement and admin configuration. If public ticket UI does not pre-disable the child selector before submission, the cart/add-to-cart guard should still fail closed and show the error notice.
