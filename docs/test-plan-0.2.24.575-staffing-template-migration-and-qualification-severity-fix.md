# VMS 0.2.24.575 Test Plan - Staffing Template Migration Repair and Qualification Severity Fix

## Goal

Verify the follow-up staffing fix that:
- repairs the staffing-template attendance-band schema upgrade path on installs that never ran the v5 migration
- restores usable staffing template save/edit/apply behavior
- fixes qualification enforcement so mixed rule severities are evaluated per missing requirement instead of collapsing to the role's highest possible severity

## Build under test

- Plugin: `vms`
- Version: `0.2.24.575`
- Package: `vms-0.2.24.575-staffing-template-migration-and-qualification-severity-fix.zip`

## Preconditions

1. Install/replace VMS Core with `vms-0.2.24.575-staffing-template-migration-and-qualification-severity-fix.zip`.
2. Confirm WordPress shows VMS version `0.2.24.575`.
3. Confirm `vms/vms-build.txt` reads `0.2.24.575`.
4. Confirm `vendor-management-system.php` header and `includes/core/registry/constants.php` define version `0.2.24.575`.
5. Prefer testing on a site that previously showed the `wp_vms_staffing_templates` save failure so the migration repair path is exercised for real.

## 1) Migration / schema repair

1. Activate or reload the plugin on a site that previously lacked `min_headcount` and `max_headcount`.
2. Confirm `wp_vms_staffing_templates` now contains both columns:
   - `min_headcount`
   - `max_headcount`
3. Confirm `vms_db_schema_version` is at `vendor_core_v5`.
4. Open **VMS → Staffing Templates** and save a template that includes an attendance band.
5. Confirm the save succeeds with no `Template save failed` error.
6. Reopen the template and confirm the attendance band values persist.

## 2) Template apply / recommendation path

1. Create or edit at least two templates with different attendance bands, for example:
   - Small Show: `0–74`
   - Medium Show: `75–149`
2. On an Event Plan, confirm the staffing template selector loads templates normally.
3. Use **Apply Staffing Template** in **Merge missing roles only** mode and confirm the event receives the expected staffing rows.
4. Use **Apply Staffing Template** in **Replace staffing from template** mode and confirm the event is rebuilt from the selected template.
5. Confirm the Event Plan shows sensible **Applied** vs **Recommended now** template context.
6. Confirm near-threshold / outgrown warnings now execute on saved templates instead of being blocked by schema failure.
7. Confirm an empty Event Plan can still auto-seed from a matching active template when appropriate.

## 3) Qualification severity behavior

Create one Staff Role with mixed rules:
- `TABC Certified` → `Hard block`
- `Internal bar training` → `Warn only`

Create or use staff records with these states:
- Staff A: has active `TABC Certified`, missing `Internal bar training`
- Staff B: missing `TABC Certified`
- Staff C: has everything required

Then verify:

1. **Staff A** remains assignable.
2. **Staff A** shows a warning state (`Q⚠`), not a disabled hard block.
3. Saving the Event Plan keeps **Staff A** assigned and reports only a warning.
4. **Staff B** is disabled or stripped on forced save because the missing requirement is the actual `Hard block` rule.
5. **Staff C** shows `Q✓` and saves normally.

## 4) Soft-block regression check

1. Use a role with a single `Soft block` requirement.
2. Assign a staff member missing that qualification.
3. Confirm the picker stays usable, the UI shows warning/bypass style behavior, and the assignment is not removed on save.

## 5) General staffing regression check

1. Reconfirm the Event Plan staffing labels still read **Staff needed** and **Activate at attendance**.
2. Reconfirm absolute mode with **Shift start + Duration** still saves with blank **Shift end**.
3. Reconfirm relative timing still persists correctly.
4. Reconfirm the Staff profile qualification card layout still renders correctly.
5. Reconfirm no fatal errors appear on staffing screens.

## Pass criteria

- The staffing template table is upgraded to include attendance-band columns on the tested install.
- Staffing templates save/edit/apply successfully.
- Attendance-band recommendation and alert logic can be exercised normally.
- Qualification enforcement only hard-blocks assignments when an actually missing requirement is marked `Hard block`.
- Previously passing staffing UX behavior remains intact.
