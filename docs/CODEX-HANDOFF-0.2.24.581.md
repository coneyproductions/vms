# CODEX HANDOFF — VMS 0.2.24.581

## Focus
Staffing template refinement so templates can carry **per-role attendance triggers** instead of forcing operators to duplicate entire templates whenever only one role changes.

## What changed
- Added template-slot **Activate at attendance** inputs in the Staffing Template editor.
- Added DB migration / runtime repair for a new `activation_threshold` column on `vms_staffing_template_slots`.
- Template save now persists role-by-role attendance triggers.
- Template apply / auto-seed now copy those triggers into the Event Plan role activation thresholds.
- Kept template-wide attendance bands in place as optional outer scope.
- `replace_all` template apply now fully clears old event staffing/thresholds before reseeding, even if the chosen template is empty.

## Highest-risk areas to test
1. Existing installs upgrade to schema **vendor_core_v6** and gain the template-slot `activation_threshold` column.
2. Creating and editing a staffing template saves the per-role **Activate at attendance** value for each slot.
3. Applying a template to an Event Plan copies each role’s trigger to the Event Plan UI.
4. Auto-seeded templates on newly created events also carry the correct per-role triggers.
5. `Replace staffing from template` clears old role thresholds for roles omitted from the replacement template.
6. Existing templates created before this patch load without fatals and default missing slot thresholds to `1`.

## Notes
- This pass does **not** add the separate **Duplicate Template** action yet.
- Template-wide guest bands still exist and remain useful as outer eligibility scope.
