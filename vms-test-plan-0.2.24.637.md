# Test Plan — VMS 0.2.24.637 Staff Certification Admin Visibility + Email Sender Merge

## Setup

- Install VMS `0.2.24.637` on staging over the uploaded `0.2.24.636` baseline.
- Confirm `vms-build.txt`, plugin header, and `VMS_VERSION` report `0.2.24.637`.
- Use a test staff profile linked to a WordPress user with an accessible email inbox.

## Tests

### 1. Staff portal upload still works

1. Open the staff portal as the linked staff user.
2. Go to the Certifications tab.
3. Upload a test certificate, such as TABC, with an expiration date.

Expected:

- Upload succeeds.
- Staff sees the certificate listed as Pending Review.
- Staff receives a receipt email.
- Email sender should show the site name where the mail client honors the custom From header.

### 2. Admin receives submission notification

Expected:

- Site admin email and administrator user emails receive the pending review notification.
- Email includes staff name, certification name, expiration date, and staff profile review link.

### 3. Admin visibility appears

Expected:

- VMS admin notice appears saying staff certifications need review.
- VMS menu or Staff Certifications submenu shows a pending count badge.
- `VMS → Staff Certifications` page exists and lists the pending upload.
- Staff list has a Certifications column with a Pending badge for the staff member.
- Staff profile Qualifications / Licenses metabox shows a pending-review notice.

### 4. Approval email flow

1. Open the staff profile.
2. Change the pending certificate status to Approved.
3. Save/update the staff profile.

Expected:

- Staff receives approval email.
- Admin receives approval/audit email.
- Pending count disappears from the review queue/menu/notice.
- Staff list column changes from Pending to Approved.

### 5. Rejection email flow

1. Upload another test certificate.
2. Open the staff profile.
3. Add a review note/rejection reason.
4. Change status to Rejected.
5. Save/update the staff profile.

Expected:

- Staff receives rejection email including the reason.
- Admin receives rejection/audit email.
- Pending count disappears.
- Rejected count appears in Staff list metadata.

### 6. Ticket UI regression check

Open an event using the progressive public ticket UI.

Expected:

- Approved/free admission rows still use the `0.2.24.636` customer-facing copy.
- The “First time? More info” disclosure is collapsed by default inside each approved/free admission row.
- Existing registered-guest/approval enforcement still works.

## Rollback

Rollback to the previous working `0.2.24.636` zip if staging shows a fatal error or if staff portal uploads/admin pages are blocked.
