# VMS Codex Stress Test Plan — Guest Passes + Universal Admissions

## Build Under Test

VMS 0.2.24.561 or newer.

## Critical Known Areas

- Guest Pass registration email delivery.
- Admin Resend Email action.
- Multi-person passes with individual QR codes.
- Count consistency across Event Command Center, Vendor Portal, Single Event Detail report, and Profitability report.
- Past/cancelled event handling.

## Required Result Format

For each scenario, report:

- Pass / Fail
- Steps performed
- Expected result
- Actual result
- Screenshot if failed
- URL tested
- Event Plan ID
- Batch ID
- Claim/admission ID if visible
- Error log entries

## Scenarios

### 1. Single Free Pass, One Guest

Expected:
- One claim link.
- One admission record.
- One QR code.
- Gate scan checks in successfully.
- Duplicate scan warns already checked in.

### 2. One Group Pass for Four People

Expected:
- One claim link.
- Claim form allows max 4.
- Claiming 4 creates 4 unique QR codes.
- Each QR checks in independently.
- Duplicate scan only affects the duplicated QR.

### 3. Ten Group Passes of Four

Expected:
- One batch creates 10 links.
- Each link allows up to 4 admissions.
- Total possible admissions = 40.
- Batch cap cannot be exceeded.

### 4. Partial Group Claim

Expected:
- A 4-person link claimed for 2 people creates 2 QR codes.
- Remaining unused capacity on that claimed link cannot be claimed by another person.
- Admin clearly shows claimed party size.

### 5. Same Link Claimed Twice

Expected:
- First claim succeeds.
- Second claim shows already claimed.
- No duplicate admissions are created.

### 6. Phone Limit

Expected:
- Max Claims per Phone is enforced.
- Failed claim creates no partial records.

### 7. Email Limit

Expected:
- Max Claims per Email is enforced.
- Failed claim creates no partial records.

### 8. No Email Provided

Expected:
- Claim succeeds.
- QR codes display.
- No email attempt is made.
- Resend Email is hidden or disabled.

### 9. Email Provided

Expected:
- Claim succeeds.
- Confirmation says email sent only if WordPress accepted the message.
- Email contains all QR codes and View / Print link.
- Admin Email column shows sent timestamp or not-sent state.

### 10. Resend Pass Email

Expected:
- Admin clicks Resend Email.
- Success/failure notice is visible.
- Email includes all QR codes.
- Audit log records sent or failed action.

### 11. View / Print Pass From Admin

Expected:
- View Pass opens without 404.
- Printable page includes guest name, event, date, venue, party size, QR codes, and reference.

### 12. Past Event Claim Link

Expected:
- Past events do not show in dropdown.
- Event-specific past links are blocked.
- No admission records are created.

### 13. Any Event Pass With Past + Future Events

Expected:
- Dropdown shows future published eligible events only.
- Cancelled, draft/private, and past events are hidden.

### 14. Cancelled Event

Expected:
- Cancelled event cannot be selected.
- Existing claimed passes show a warning.
- Gate scan does not admit without manager override.

### 15. Voided Pass Before Claim

Expected:
- Claim URL no longer works.
- Admin status shows voided.
- No admission records are created.

### 16. Voided Claimed Admission

Expected:
- Voiding claimed pass invalidates related QR codes or requires explicit per-person choice.
- Gate scan shows voided.
- Audit trail records user and timestamp.

### 17. One Person Arrives Before Group

Expected:
- QR 1 checks in.
- QR 2–4 remain valid.
- Gate/admin view shows 1 of 4 checked in.

### 18. Duplicate QR Screenshot Shared

Expected:
- First scan succeeds.
- Second scan says already checked in and shows prior check-in time.

### 19. Manual Name Search

Expected:
- Gate staff can search by name.
- Claimed pass appears.
- Staff can check in the correct individual admission.

### 20. Phone Search

Expected:
- Search finds matching claimed pass.
- Staff can check in one person or remaining people.

### 21. Paid TEC Ticket Regression

Expected:
- Existing TEC QR scanning still works.
- No fake Woo order is created for guest passes.
- Paid ticket counts remain paid-only.

### 22. Mixed Door Flow

Expected:
- One gate screen supports paid tickets, vendor guests, manual comps, and guest passes.
- Source labels are visible but do not require workflow switching.

### 23. Reporting Count Consistency

Compare:
- Event Command Center
- Vendor Portal bonus card
- Single Event Detail report
- Profitability report
- Guest Pass admin
- VMS admissions/gate list

Expected:
- Paid tickets count matches paid sources only.
- Comp/free count matches VMS admission headcount rules.
- Total admitted = paid + comp/free.
- Guest passes do not increase revenue.
- Guest passes do not increase vendor bonus-eligible paid count.

### 24. Vendor Bonus Count

Expected:
- Guest passes/comps do not count toward paid-ticket bonus unless explicitly configured.
- Vendor Portal separates presales, door sales, and comped/guest list.

### 25. Batch Preview Persistence

Expected:
- Preview preserves all form values.
- Commit uses exact preview payload.

### 26. Failed Preview Persistence

Expected:
- Invalid/missing data shows an error.
- Entered values remain.
- No batch is generated.

### 27. Admin CSV Export

Expected:
- Export includes batch, claim status, claimer, email, phone, event, party size, QR/admission count, and created date.
- Sensitive full tokens are not casually exposed unless intentionally included.

### 28. Mobile Claim Page

Expected:
- Fields stack correctly.
- Party-size control is usable.
- QR confirmation page has no horizontal scrolling.

### 29. Mobile Gate Scan

Expected:
- QR codes scan cleanly from another phone.
- Manual search is usable.
- Duplicate warning is obvious.

### 30. Email Failure

Expected:
- Claim succeeds.
- Confirmation does not falsely say email was sent.
- Admin shows not-sent/failure state.
- Resend can retry.
- Failure is logged.
