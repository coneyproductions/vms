# Test Plan — VMS 0.2.24.629 Qualified-Ticket Clarity

## Scope

Verify the public progressive ticketing clarity pass for qualified/free tickets and registered guest emails.

## Setup

- Install `VMS_Ticket_UI_629.zip` on staging.
- Confirm VMS reports version `0.2.24.629`.
- Confirm global Ticket UI layout is `progressive` or the tested event resolves to progressive.
- Clear public page cache before testing.

## Tests

### 1. Progressive mode still renders

1. Open the Buffet Beach public event page.
2. Inspect the ticketing root.

Expected:
- `vms-ticket-ui-v2` is present.
- `vms-ticket-ui-progressive` is present.
- Runtime config has `uiProgressive: "1"`.

### 2. Ticket-choice help appears collapsed

1. Look near the Tickets heading.
2. Find `Need help choosing tickets?`.
3. Confirm it is collapsed by default.
4. Open it.

Expected:
- Copy explains one admission option per person.
- Copy explains General Admission, qualified tickets, and registered guest email use.
- The help does not take over the screen or add excessive clutter.

### 3. Registered guest copy is clearer

1. While logged out, select 1 Veteran Admission.
2. Read the `Bringing a registered guest?` panel.

Expected:
- Copy says the guest email is for a guest who already has an approved account.
- Copy makes clear the guest email path is used instead of General Admission for that same person, not in addition to it.

### 4. Mobile layout remains correct

Test at 390, 412, and 430 px widths.

Expected:
- Registered guest email input stacks above `Add Registered Guest`.
- Sticky `Add items to cart` remains usable.
- No horizontal overflow.

### 5. Steppers remain centered

1. Check ticket rows on desktop and mobile.
2. Check add-on rows on desktop and mobile.

Expected:
- `- / +` button contents are centered and not top-aligned.

### 6. Add-on gating still works

1. Leave GA at 0 and confirm Fire Table remains disabled.
2. Increase GA to 4.
3. Add 1 Fire Table.
4. Submit.

Expected:
- Fire Table unlocks at 4 qualifying tickets.
- Cart receives 4 GA + 1 Fire Table.
