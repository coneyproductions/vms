# VMS 0.2.24.634 — Staff Certification Upload + Email Notification Review

## Scope

This pass adds staff-side certificate uploads and notification handling to the existing staff qualifications/licensing system.

## Changed areas

- `includes/portal/staff-portal.php`
  - Adds a new **Certifications** staff portal tab.
  - Allows linked staff users to upload certificate/license proof files.
  - Captures certification type, issuing organization, certificate/license number, issue date, expiration date, and uploaded proof file.
  - New staff uploads are saved as `pending_verification` / **Pending Review**.
  - Sends submission confirmation to the staff user and a pending-review email to admin recipients.

- `includes/core/staffing.php`
  - Expands staff qualification normalization metadata.
  - Adds stable qualification row IDs.
  - Adds `rejected` status while preserving `active` as the scheduling-valid approved status.
  - Adds submission/review email helpers.
  - Adds lightweight qualification audit metadata on submit/approve/reject.
  - Preserves role scheduling checks: only `active` / approved qualifications satisfy requirements.

- `includes/cpt/staff.php`
  - Updates the Staff admin Qualifications / Licenses metabox.
  - Shows statuses as Approved, Pending Review, Rejected, Expired, Inactive.
  - Preserves proof URL / attachment metadata and submit/review metadata.
  - Sends staff/admin review emails when a pending certification is changed to Approved or Rejected.

- `assets/css/vms-portal.css`
  - Adds responsive layout support for the staff certification portal list/upload form.

- `assets/css/vms-admin.css`
  - Adds small admin layout polish for qualification review metadata/actions.

## Notification behavior

### On staff upload

- Admin recipient(s) receive a pending-review email with staff name, certification type, expiration date, and staff profile review link.
- Staff member receives confirmation that the certificate was received and is pending review.

### On admin approval

- Staff member receives approval email.
- Admin recipient(s) receive a review-status email for audit visibility.

### On admin rejection

- Staff member receives rejection/needs-attention email.
- Admin recipient(s) receive a review-status email for audit visibility.
- The admin note / rejection reason is included when present.

## Recipient customization

Admin recipients default to the WordPress admin email. Developers may filter recipients with:

```php
add_filter('vms_staff_qualification_admin_notification_recipients', function ($emails, $staff_id, $row, $event) {
    $emails[] = 'staff-manager@example.com';
    return $emails;
}, 10, 4);
```

`$event` values include `submitted`, `approved`, and `rejected`.

## Version markers updated

- Plugin header: `0.2.24.634`
- `VMS_VERSION`: `0.2.24.634`
- `vms-build.txt`: `0.2.24.634`
- Test plan: `docs/test-plan-0.2.24.634-staff-certification-notifications.md`
