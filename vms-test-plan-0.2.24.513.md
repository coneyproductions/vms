# VMS Test Plan — 0.2.24.513

## Scope
Eligibility verification submission email alert for new pending requests.

## Verify
1. Submit a new eligibility verification request from My Account with a valid proof file.
2. Confirm the request lands in **VMS → Eligibility Approvals** with **Pending** status.
3. Confirm the site admin email receives a new message with:
   - Program name
   - Submitted timestamp
   - Customer name
   - Customer email
   - User note when provided
   - Review queue link
   - View proof link
4. Submit another request with the note field blank and confirm the email still sends cleanly without an empty “User note” line.
5. Confirm approve / deny still works and still sends the existing customer decision email.
6. Confirm no PHP fatal/errors occur during submission when mail is unavailable; request should still save and stay pending.
