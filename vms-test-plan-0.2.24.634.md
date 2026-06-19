# Test Plan — VMS 0.2.24.634 Staff Certification Upload + Notifications

## Pre-checks

1. Install/activate VMS 0.2.24.634 on staging.
2. Confirm version markers:
   - Plugin page shows `0.2.24.634`.
   - `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.634`.
   - `vms/vms-build.txt` begins with `0.2.24.634`.
3. Confirm at least one WordPress user is linked to a `vms_staff` profile through `_vms_staff_id`.
4. Confirm staging can send or log emails.

## Staff portal upload

1. Log in as a linked staff user.
2. Open the Staff Portal.
3. Confirm navigation includes **Certifications**.
4. Open **Certifications**.
5. Upload a test certificate with:
   - Certification type: `TABC`
   - Issuing organization: `Test Authority`
   - Certificate/license number: `TEST-123`
   - Expiration date in the future
   - PDF or image proof file
6. Expected:
   - Success message says the certificate is pending review.
   - The uploaded item appears under **Your Certificates** with **Pending Review**.
   - Staff user receives a confirmation email.
   - Admin recipient receives a pending-review email with a staff profile review link.

## Admin review — approval

1. Open the linked Staff profile in admin.
2. Confirm the uploaded qualification appears in **Qualifications / Licenses**.
3. Confirm proof URL/link is present.
4. Change status from **Pending Review** to **Approved**.
5. Save/update the Staff profile.
6. Expected:
   - Staff user receives approval email.
   - Admin recipient receives approval status email.
   - Staff profile stores review metadata.
   - Staff portal now shows the certification as **Approved**.
   - Scheduling qualification checks treat the qualification as valid.

## Admin review — rejection

1. Upload a second test certificate from the staff portal.
2. Open the Staff profile in admin.
3. Change the second upload status from **Pending Review** to **Rejected**.
4. Enter a review note/rejection reason, such as `Test rejection reason`.
5. Save/update the Staff profile.
6. Expected:
   - Staff user receives needs-attention/rejection email.
   - Email includes the review note/rejection reason.
   - Admin recipient receives rejection status email.
   - Staff portal shows the item as **Rejected** and displays the note.
   - Scheduling qualification checks do not treat the rejected qualification as valid.

## Regression checks

1. Existing admin-entered Approved/active qualifications still appear and remain scheduling-valid.
2. Existing Pending/Expired/Inactive qualifications still do not satisfy required role qualification checks.
3. Staff portal Tax Profile, Employee Packet, Dashboard, and Availability tabs still load.
4. Upload form rejects empty certification type.
5. Upload form rejects missing file.
6. Accepted file types: PDF, JPG, PNG, WEBP.
7. No duplicate WordPress admin menu entries or duplicate VMS shell/nav appear due to this pass.

## Rollback

Rollback to the previous known-good VMS zip if staff portal or admin staff-profile editing causes a fatal error. This patch stores new qualification metadata in the existing `_vms_staff_qualifications` post meta array, so rollback should leave unknown metadata inert.
