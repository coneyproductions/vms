# CODEX HANDOFF — VMS 0.2.24.593 Menu heading alignment

## Summary

This pass corrects the admin menu information architecture after the compact-menu discovery work. The left WordPress VMS submenu remains concise, but its visible headings now match the primary VMS top-nav headings instead of using a separate set of section names.

## Changed files

- `includes/core/registry/admin-menu.php`
- `includes/admin-ui/nav.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/06-test-plan.md`
- `docs/test-plan-0.2.24.593-menu-heading-alignment.md`

## Test plan

Run `docs/test-plan-0.2.24.593-menu-heading-alignment.md`.

## Notes

- This should not add individual pages back to the left rail.
- The left rail now groups detailed registry sections into the same primary headings as the top nav.
- `events_schedule` and `tickets_admissions` pages highlight Planning.
- `reports_finance` and `tools_integrity` pages highlight Tools.
- `marketing_sales` pages highlight Marketing & Social.
- `venue_setup` pages highlight Venues.
- `settings_addons` pages highlight Settings.
