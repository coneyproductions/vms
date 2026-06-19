# CODEX HANDOFF - VMS Core 0.2.24.575 Staffing Template Migration Repair and Qualification Severity Fix

## Build

- Plugin: `vms`
- Version: `0.2.24.575`
- Package target: `vms-0.2.24.575-staffing-template-migration-and-qualification-severity-fix.zip`
- Baseline: `0.2.24.574-staffing-ux-refinement-duration-qualification-rules`

## Why this follow-up exists

Codex found two real defects in `0.2.24.574`:

1. **High** - staffing-template attendance-band columns never became usable on the tested install because the schema upgrade path stopped at vendor core v4 while the needed columns were added in v5.
2. **High** - mixed qualification rules were escalating warn-only requirements into hard blocks whenever the same role also had any hard-block rule.

This pass fixes those two defects without trying to redesign the staffing feature again.

## What changed

1. **Schema / migration repair**
   - Re-enabled the canonical activation hooks in `vendor-management-system.php`.
   - Explicitly loads `includes/activation.php` from the plugin root before registering activation/deactivation hooks.
   - Updated the runtime migration path in `includes/core/plugin.php` so normal plugin loads advance through **vendor core v5**, not just v4.
   - Added a staffing-template save preflight that re-checks the template table schema and runs the v5 migration before writing attendance-band fields.

2. **Qualification severity fix**
   - Updated staffing qualification checks so the returned severity is derived from the **missing requirements for that staff member**, not from the role's maximum possible severity.
   - Warn-only missing requirements now stay warnings even when the role also contains a separate hard-block rule.
   - Hard blocks still disable/remove assignments only when the actually missing requirement is marked `Hard block`.
   - Normalized role save behavior so the legacy fallback severity is no longer re-derived by collapsing all rule severities upward during save.

## Files touched

- `vendor-management-system.php`
- `includes/core/plugin.php`
- `includes/core/staffing.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/06-test-plan.md`
- `docs/01-project-handoff.md`
- `docs/CODEX-HANDOFF-0.2.24.575.md`
- `docs/test-plan-0.2.24.575-staffing-template-migration-and-qualification-severity-fix.md`
- `vms-test-plan-0.2.24.575.md`

## What should be retested

Run the packaged test plan focused on the previously blocked areas plus the qualification regression:

- template migration / attendance-band column repair
- template save/edit persistence
- manual template apply
- attendance-band recommendation / threshold warnings
- auto-seed from matching templates
- mixed qualification severities where warn-only and hard-block rules coexist on the same role
- soft-block regression behavior
- quick sanity check that the recent staffing UX polish still behaves normally

## Packaged docs to use

- `docs/test-plan-0.2.24.575-staffing-template-migration-and-qualification-severity-fix.md`
- `docs/CODEX-HANDOFF-0.2.24.575.md`
- `vms-test-plan-0.2.24.575.md`

## Reminder

This is still a targeted staffing follow-up. It does **not** add new forecasting logic, master qualification registries, or broader staffing redesign work. The goal is to make the existing staffing template and qualification system behave correctly on real installs.
