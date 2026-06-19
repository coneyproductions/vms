# CODEX HANDOFF - VMS Core 0.2.24.573 Staffing Template Apply, Thresholds, and Qualifications

## Build

- Plugin: `vms`
- Version: `0.2.24.573`
- Package target: `vms-0.2.24.573-staffing-template-apply-thresholds-qualifications.zip`
- Baseline: `0.2.24.572-public-calendar-destination-setting`

## What Changed

This pass makes staffing templates usable from the normal Event Plan workflow, adds attendance-band recommendation logic, exposes relative shift timing properly, and introduces qualification checks for staffing assignments.

1. **Event Plan Staffing Template Controls**
   - Added a staffing template card directly inside **Event Plan → Staff**.
   - Shows **Applied** vs **Recommended now** template context.
   - Added safe apply modes for **Merge missing roles only** and **Replace staffing from template**.

2. **Broader Auto-Seed**
   - Added a generic save-path auto-seed hook so empty Event Plans can pick up a matching staffing template outside the narrow Schedule-only create path.
   - Existing events with staffing rows should not be silently wiped by the new hook.

3. **Attendance Bands + Review Alerts**
   - Added `min_headcount` / `max_headcount` fields to staffing templates plus schema migration support.
   - Added top-level Event Plan staffing alerts when the applied template is outgrown, another template is recommended, or the next threshold is close.
   - Template matching now considers venue/day/type plus optional attendance band.

4. **Relative Shift Timing**
   - Expanded staffing template rows and Event Plan role rows to support relative timing with start/end anchors, offsets, and duration.
   - Existing absolute timing remains supported.

5. **Staff Qualifications / Licenses**
   - Added a Staff profile meta box for qualifications/licenses with status, authority, issue/expiration dates, proof URL, and notes.
   - Added Staff Role required qualifications plus warn / soft-block / hard-block behavior.
   - Event Plan staffing assignment save now warns or blocks invalid assignments based on the role setting.

## Files Changed

- `vendor-management-system.php`
- `vms-build.txt`
- `includes/core/registry/constants.php`
- `includes/core/staffing.php`
- `includes/admin/staffing.php`
- `includes/cpt/event-plans.php`
- `includes/cpt/event-plans/partials/staff.php`
- `includes/cpt/staff.php`
- `includes/db/migrations.php`
- `includes/activation.php`
- `includes/core/plugin.php`
- `docs/01-project-handoff.md`
- `docs/05-revision-log.md`
- `docs/06-test-plan.md`
- `docs/test-plan-0.2.24.573-staffing-template-apply-thresholds-qualifications.md`
- `docs/CODEX-HANDOFF-0.2.24.573.md`
- `vms-test-plan-0.2.24.573.md`

## Guardrails Preserved

- Existing absolute shift start/end behavior remains supported.
- Existing staffing rollups and audit refresh still run after staffing changes/template apply.
- Template apply does not silently replace staffing unless the operator explicitly chooses **Replace staffing from template**.
- Qualification enforcement is role-driven and supports warn-only behavior instead of forcing hard blocks for every venue.

## Known Scope Boundary

- This build adds **current-headcount review alerts** and template recommendations, but it does **not** yet implement true pace-based predictive staffing forecasts.

## Testing Focus

Run `docs/test-plan-0.2.24.573-staffing-template-apply-thresholds-qualifications.md`.

Pay special attention to:

- normal Event Plan template apply/reapply behavior
- attendance-band template recommendation changes
- relative timing persistence on both templates and events
- qualification warnings vs hard-block behavior
- generic auto-seed on non-Schedule-created Event Plans

## Repair / Versioning Protocol

🚨 If Codex makes even a minimal code repair during testing, update all relevant version markers and packaging docs before returning a replacement zip. At minimum:

- plugin header version if present
- `VMS_VERSION`
- `vms-build.txt`
- revision/changelog/build notes
- test plan or follow-up test notes
- Codex handoff notes
- package filename

Do not return a modified build with stale versioning/docs.
