# VMS 0.2.24.637 — Staff Certification Admin Visibility + Email Sender Merge

## Purpose

This build merges the staff certification admin visibility/email sender hotfix into the uploaded `0.2.24.636` qualified-row more-info ticket UI baseline.

## Preserved from 0.2.24.636

- Public approved/free admission rows no longer show customer-facing “Qualified ticket.” wording.
- First-time approved/free admission help is collapsed inside each relevant ticket row.
- Existing qualified-ticket enforcement and registered-guest behavior remain intact.

## Added / changed in 0.2.24.637

- New VMS admin page: `VMS → Staff Certifications`.
- Pending review count badge on the VMS menu / Staff Certifications submenu.
- Admin notice when staff certification uploads are waiting for review.
- Staff list column showing pending/approved/expired/rejected certification counts.
- Staff profile Qualifications / Licenses metabox notice when uploads need review.
- Admin certification submission emails now go to:
  - site admin email, and
  - administrator user emails.
- Staff certification emails now include a site-branded `From:` header where WordPress/mail delivery honors custom headers.

## Review workflow

Staff uploads remain `Pending Review`. Approval/rejection still happens from the Staff Profile / Qualifications-Licenses metabox so VMS does not create a second competing approval mechanism.

## Files touched

- `vendor-management-system.php`
- `vms-build.txt`
- `includes/core/registry/constants.php`
- `includes/core/staffing.php`
- `includes/admin/load.php`
- `includes/admin/menu.php`
- `includes/admin/staff-certifications.php`
- `includes/admin/staff-list-columns.php`
- `includes/cpt/staff.php`
- `assets/css/vms-admin.css`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.637.md`
- `docs/test-plan-0.2.24.637-staff-certification-admin-visibility.md`
- `vms-test-plan-0.2.24.637.md`

## Notes for testing

No live Meta, Square, Woo payment, or TEC mutation should be needed for this test. Use staging and a test staff user.
