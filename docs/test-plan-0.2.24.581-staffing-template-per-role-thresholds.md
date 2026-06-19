# Test Plan — 0.2.24.581 Staffing template per-role thresholds

## Core checks
- Create a staffing template with mixed role triggers, for example:
  - Manager → Staff needed 1, Activate at attendance 1
  - Sound Tech → Staff needed 1, Activate at attendance 1
  - Bartender → Staff needed 1, Activate at attendance 1
  - Bartender 2 → Staff needed 1, Activate at attendance 75
- Save and reopen the template. Confirm each slot keeps its own trigger.

## Apply checks
- Apply the template to an Event Plan in **Merge missing roles only** mode.
- Confirm each affected role on the Event Plan inherits the matching **Activate at attendance** value.
- Apply the same template in **Replace staffing from template** mode to an Event Plan that already has other roles/thresholds.
- Confirm omitted roles/thresholds are cleared.

## Auto-seed checks
- Create a new eligible Event Plan that should auto-seed from the template.
- Confirm seeded Event Plan roles carry the template’s per-role trigger values.

## Regression checks
- Existing templates created before this patch still load and save without fatal errors.
- Template-wide attendance bands still save and continue to affect template recommendation / eligibility.
- Event Plan timing / duration behavior remains unchanged.
- Staffing qualification behavior remains unchanged.
