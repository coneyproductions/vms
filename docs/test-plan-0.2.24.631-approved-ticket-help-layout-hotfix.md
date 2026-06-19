# Test Plan — VMS 0.2.24.631 Approved Ticket Help Layout Hotfix

## Scope

Verify the approved/free admission help card added in `0.2.24.630` no longer breaks the desktop ticket row layout and still behaves correctly on mobile.

## Setup

- Upload/install `VMS_Ticket_UI_631.zip` on staging.
- Clear server/page cache after install.
- Use the Buffet Beach public event page or another event with Veteran and Police/Fire/EMT Admission tickets.

## Checks

1. Confirm VMS reports version `0.2.24.631` in `vms-build.txt` and the VMS Settings screen.
2. Desktop, logged out: select 1 Veteran Admission ticket.
   - The ticket row should not collapse into a narrow left column.
   - The short row description should remain readable.
   - Login/Register card should appear first.
   - Ticket-specific help card should appear next.
   - Approved guest email card should appear after the help card.
3. Confirm help card copy uses the ticket name and avoids `Qualified Admission`.
4. Repeat with Police, Fire Fighter, EMT Admission.
5. Mobile widths 390/412/430:
   - Progressive layout renders.
   - Approved guest email input stacks above `Add Registered Guest`.
   - Sticky `Add items to cart` remains usable.
6. Confirm ticket and add-on `- / +` steppers remain centered.
7. Confirm Amenities remains collapsed by default.
8. Confirm add-on gating still unlocks after 4 qualifying tickets.
9. Confirm 4 GA + 1 Fire Table can be added to cart.

## Fail conditions

- Help card overlaps the ticket description, login/register note, or guest email panel.
- Help card appears as a stray grid child beside the description/control area.
- Mobile guest email field returns to side-by-side with the button.
- Steppers drift top-aligned again.
