# Test Plan — VMS 0.2.24.632 Approved Ticket Help Collapse

## Scope

Verify the ticket-specific approved/free ticket help card is collapsible, collapsed by default, compact when expanded, and does not regress the recent ticket UI fixes.

## Setup

- Install `VMS_Ticket_UI_632.zip` on staging.
- Use an event with General Admission, Veteran Admission, Police/Fire/EMT Admission, and gated Amenities/add-ons.
- Clear public page cache after install if WP Super Cache serves stale markup.

## Checks

1. Confirm VMS reports version `0.2.24.632` in `vms-build.txt` and the VMS Settings screen.
2. Load the public event page on desktop and mobile.
3. Select 1 Veteran Admission ticket while logged out.
4. Confirm order is:
   - Log In/Register card
   - collapsed `Need help ordering Veteran Admission tickets?` help panel
   - `Bringing an approved guest?` panel
5. Expand the help panel and confirm:
   - copy is not numbered/indented
   - copy does not include the removed General Admission warning line
   - copy mentions approvals are often completed quickly
6. Repeat for Police, Fire Fighter, EMT Admission.
7. Confirm mobile approved guest input remains stacked above `Add Registered Guest`.
8. Confirm Log In/Register button text remains centered.
9. Confirm native ticket and add-on steppers remain visually centered.
10. Confirm Amenities is collapsed by default in progressive mode.
11. Confirm add-on gating still unlocks after 4 qualifying tickets and adding 4 GA + 1 Fire Table succeeds.
