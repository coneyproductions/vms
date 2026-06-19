# Test Plan — VMS 0.2.24.630 Approved Ticket Help Card

## Scope

Verify the approved/free admission help card is ticket-specific, clear for non-technical customers, and does not regress the recent progressive ticket UI layout fixes.

## Setup

- Install/upload `VMS_Ticket_UI_630.zip` on staging.
- Clear WP Super Cache/public page cache after upload.
- Open a public event with General Admission, Veteran Admission, Police/Fire/EMT Admission, and gated Amenities.

## Checks

1. Confirm VMS reports version `0.2.24.630` in `vms-build.txt` and the VMS Settings screen.
2. Confirm the public event page runtime config is progressive:
   - `uiLayout: "progressive"`
   - `uiProgressive: "1"`
   - DOM includes `vms-ticket-ui-progressive`
3. Confirm the global `Need help choosing tickets?` helper is not shown at the top of the Tickets section.
4. While logged out, select `Veteran Admission` quantity 1.
5. Confirm the selected Veteran row shows, in order:
   - login/register note
   - `Need help ordering Veteran Admission tickets?` help card
   - `Bringing an approved guest?` panel
6. Confirm help-card copy says approvals are often completed quickly and tells the customer to come back after approval, without mentioning checkout.
7. Confirm help-card copy clearly says not to also buy General Admission for the same person.
8. Confirm the approved guest email label says `Approved guest email for ticket 1`.
9. Confirm the action button still says `Add Registered Guest`.
10. Repeat checks 4–9 for `Police, Fire Fighter, EMT Admission`.
11. On mobile widths, confirm the approved guest email input stacks above the `Add Registered Guest` button.
12. On desktop, confirm ticket and add-on steppers are visually centered.
13. Confirm Amenities is collapsed by default.
14. Add 4 General Admission tickets and confirm a Fire Table unlocks.
15. Add 4 GA + 1 Fire Table to cart and confirm the cart receives the correct items.

## Rollback

If public ticket layout regresses, roll back to `0.2.24.629` and clear page cache.
