# VMS 0.2.24.746

## Purpose
Fix cancellation notifications so assigned staff in the current staffing slot model are included, legacy staff assignments remain a fallback only, and the admin cancellation job panel shows exactly who was sent, failed, or skipped.

## Changes
- Updated `includes/core/cancellation-adapters.php` so cancellation notification recipient discovery resolves modern staff slot assignments first, checks staff email sources in this order: `_vms_linked_user_id` user email, `_vms_staff_id` reverse-linked user email, `_vms_contact_email`, `_vms_vendor_primary_email`, `_vms_vendor_email`, records `missing_email` skips, and stores typed/grouped recipient results for the job summary.
- Updated `includes/cpt/event-plans.php` so the Cancellation Job panel renders grouped vendor, secondary vendor, and staff totals plus readable sent, failed, and skipped rows with recipient type, display name, and skip/failure reason.
- Added `tests/cancellation-notification-staff-email-resolver.php`, a WordPress-backed regression harness that proves linked-user precedence, `_vms_staff_id` fallback, and `missing_email` skip reporting without sending real email.
- Bumped the plugin header version, `VMS_VERSION`, `vms-build.txt`, and revision log to `0.2.24.746`.

## Files changed
- `includes/core/cancellation-adapters.php`
- `includes/cpt/event-plans.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `tests/cancellation-notification-staff-email-resolver.php`

## Validation
- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `php -l includes/core/cancellation-adapters.php`
- `php -l includes/cpt/event-plans.php`
- `php -l tests/cancellation-notification-staff-email-resolver.php`
- Local WordPress runtime harness:
  - `php tests/cancellation-notification-staff-email-resolver.php`
  - notification step reported `staff_assignment_source = modern`
  - a staff member with no `_vms_contact_email` but a linked `_vms_linked_user_id` user email was included as a deliverable recipient
  - a conflicting `_vms_staff_id` reverse-link did not override the linked-user email
  - a second staff member with only `_vms_staff_id` reverse-link email was included as a deliverable recipient
  - one assigned staff member without any usable email was recorded as `missing_email`
  - formatted panel rows rendered as `Sent: Staff - Jane Smith <jane@example.test>` and `Skipped: Staff - John Doe - missing_email`

## Package
- `vms-0.2.24.746-cancellation-staff-notification-fix.zip`
- `vms-0.2.24.746-cancellation-staff-notification-changed-files.zip`
