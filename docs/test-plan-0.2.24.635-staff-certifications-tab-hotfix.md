# Test Plan — VMS 0.2.24.635 Staff Certifications Tab Hotfix

## Purpose
Verify the Staff Portal Certifications tab no longer triggers the missing-function error introduced in 0.2.24.634 and that the staff upload/review notification workflow still functions.

## Preflight
1. Install/activate VMS 0.2.24.635 on staging.
2. Confirm version markers:
   - Plugin header shows `0.2.24.635`.
   - `VMS_VERSION` is `0.2.24.635`.
   - `vms-build.txt` begins with `0.2.24.635`.
3. Clear caches if needed.

## Staff Portal Smoke Test
1. Log in as a website account linked to a `vms_staff` profile.
2. Open `/staff-portal/?tab=certifications`.
3. Expected:
   - No `Call to undefined function vms_staff_portal_render_certifications()` error.
   - Page shows an Upload a Certification card.
   - Page shows a My Certifications card.

## Upload Flow
1. Upload a small PDF/image proof file with a certification name such as `TABC`.
2. Expected:
   - Success notice appears.
   - Staff qualification is stored as Pending Review.
   - Staff receives upload confirmation email.
   - Admin/admin notification recipient receives pending-review email.

## Admin Review Flow
1. Open the linked staff profile in wp-admin.
2. Find the uploaded qualification in the Qualifications / Licenses metabox.
3. Change status to Approved and update the staff profile.
4. Expected:
   - Staff receives approval email.
   - Admin audit/status email is sent.
   - Qualification now counts as valid for staffing checks.
5. Repeat with a separate upload and set status to Rejected with a review note.
6. Expected:
   - Staff receives rejection/needs-attention email with the note.
   - Rejected qualification does not satisfy required-role qualification checks.

## Regression Checks
1. Dashboard, Tax Profile/Employee Packet, and Availability tabs still load.
2. Existing approved qualifications still display in the Certifications tab.
3. Expired qualifications still display as Expired and do not satisfy active qualification checks.
