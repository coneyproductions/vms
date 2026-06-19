# VMS 0.2.24.635 — Staff Certifications Tab Hotfix

## Summary
0.2.24.634 added staff certificate upload/review notifications but the Staff Portal Certifications tab called `vms_staff_portal_render_certifications()` without shipping the renderer. This hotfix adds that missing render function and keeps the 0.2.24.634 workflow intact.

## Changed Files
- `includes/portal/staff-portal.php`
  - Adds `vms_staff_portal_render_certifications()`.
  - Renders the staff-facing upload form.
  - Renders the staff-facing list of submitted/approved/rejected/expired certifications.
  - Calls the existing upload handler and notification workflow.
- `vendor-management-system.php`
  - Bumps plugin header to `0.2.24.635`.
- `includes/core/registry/constants.php`
  - Bumps `VMS_VERSION` to `0.2.24.635`.
- `vms-build.txt`
  - Adds the 0.2.24.635 build note.
- `vms-test-plan-0.2.24.635.md` and `docs/test-plan-0.2.24.635-staff-certifications-tab-hotfix.md`
  - Adds staging regression steps.

## Notes
This is a targeted recovery patch for the front-end staff portal error shown on `/staff-portal/?tab=certifications`. No live Meta/Square/Woo actions are involved.
