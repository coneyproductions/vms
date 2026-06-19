# VMS 0.2.24.579 Test Plan - Qualified Ticket Claiming UX + Regression

## Goal

Verify that qualified/free ticket claiming is clearer for customers, especially when one buyer adds multiple separately approved guests in one order, without breaking normal ticketing, add-ons, subtotal math, or checkout validation.

## Build under test

- Plugin: `vms`
- Version: `0.2.24.579`
- Package: `vms-0.2.24.579-qualified-ticket-claiming-ux.zip`

## Preconditions

1. Install/replace VMS Core with `vms-0.2.24.579-qualified-ticket-claiming-ux.zip`.
2. Confirm WordPress shows VMS version `0.2.24.579`.
3. Confirm `vms/vms-build.txt` reads `0.2.24.579`.
4. Confirm `vendor-management-system.php` header and `includes/core/registry/constants.php` define version `0.2.24.579`.
5. Use an event that includes:
   - at least one normal paid GA ticket
   - at least one qualified ticket requiring approved guest-email claiming
   - any add-on that depends on ticket thresholds
   - the event policy checkbox if enabled for that event

## Tests

### 1. Normal paid GA ticket

1. Open an event with normal General Admission tickets.
2. Add 1 paid GA ticket.
3. Confirm subtotal updates correctly.
4. Proceed to checkout.
5. Confirm no qualified-ticket validation is triggered.

Expected:
- Paid ticket flow works normally.

### 2. Single approved veteran ticket

1. Use an email already approved for Veteran Admission.
2. Add/claim 1 Veteran ticket.
3. Confirm the action button does not say **Verify**.
4. Confirm the approved email is accepted.
5. Confirm the success state reads clearly as an added guest, not a new verification request.
6. Confirm subtotal remains correct.
7. Proceed to checkout.

Expected:
- Veteran ticket can be claimed normally.

### 3. Unapproved email attempts veteran ticket

1. Enter an email that exists but is not approved.
2. Attempt to add/claim the Veteran ticket.

Expected:
- The ticket claim is blocked.
- The message clearly explains that the guest must register and be approved before the ticket can be claimed.

### 4. Unknown email attempts veteran ticket

1. Enter an email that is not tied to any approved qualified guest account.
2. Attempt to add/claim the Veteran ticket.

Expected:
- The ticket claim is blocked.
- The message clearly explains that the guest must register and be approved before the ticket can be claimed.

### 5. Multiple approved qualified guests in one transaction

1. Add the appropriate number of qualified tickets for a multi-guest order.
2. Enter each approved guest email.
3. Confirm each approved guest can be added successfully.
4. Enter one of the same guest emails again on another qualified ticket row.
5. Confirm duplicate guest emails are blocked with a direct duplicate message.
6. Confirm subtotal remains correct.
7. Proceed to checkout.
8. Confirm ticket/admission records are tied to the correct guest emails or claim records.

Expected:
- Multiple qualified guests can be claimed in one order as long as each email is approved.
- Duplicate guest emails are blocked.
- No subtotal or checkout lockups occur.

### 6. Mixed order

1. Build an order with:
   - 1 paid GA ticket
   - 1 Veteran ticket
   - 1 First Responder ticket
   - an eligible add-on item
   - the event policy checkbox
2. Confirm the qualified-ticket rows still accept the correct approved guest emails.
3. Confirm the add-on still follows its threshold rules.
4. Confirm the event policy checkbox still governs checkout enablement normally.
5. Proceed to checkout.

Expected:
- Paid and qualified tickets coexist in one cart.
- Qualified-ticket logic does not block unrelated paid tickets.
- Add-ons and event policy behavior remain correct.

### 7. Mobile layout

1. Test the qualified-ticket section at mobile width.
2. Confirm the helper disclosure does not overwhelm the page.
3. Confirm **Add Qualified Guest** fits or wraps cleanly.
4. Confirm the quantity controls remain aligned and usable.

Expected:
- Mobile purchase flow remains clean and understandable.

### 8. Accessibility / basic usability

1. Confirm the expandable help control is keyboard-accessible.
2. Confirm row messages are visible as text and do not rely on color alone.
3. Confirm the email field label makes it clear that it is for an approved guest email.

Expected:
- Customers can understand the qualified-ticket flow without guessing.

## Regression checks

- TEC native quantity controls still work.
- Unified v2 ticket layout still renders.
- Ticket helper descriptions and qualified-ticket helper boxes still render.
- Add-on qualification / threshold behavior still works.
- Subtotal math still matches selected items.
- Existing approved-user lookup behavior still works.
- Existing ticket inventory behavior still works.
- Existing guest list / comp admission behavior still works.
- Existing event policy checkbox behavior still works.

## Automation note

This workspace did not expose a local ticketing browser regression harness or `package.json` build/test script for the purchase flow, so validation here should include manual event-page coverage after the syntax checks on touched PHP/JS files.

## Codex testing note

🚨 If Codex makes even a minimal code repair while testing this build, Codex must update all relevant version markers and packaging docs in the same pass before returning a replacement zip. At minimum this includes the plugin header version, `VMS_VERSION`, `vms-build.txt`, changelog/revision notes, this test plan or follow-up test notes, Codex handoff notes, and the package filename. Do not return a modified build with stale versioning/docs.
