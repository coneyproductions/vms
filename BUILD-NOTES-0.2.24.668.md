# VMS 0.2.24.668 — ECC Total Admitted Note Regression Fix

## Purpose
Fix the remaining Event Command Center regression from `0.2.24.667`: the Ticket Snapshot could update `Guest list / comps` from `_vms_comp_headcount_true`, but still fail to render the total admitted/ticketed note because `total_ticket_count` stayed pinned to the reporting model's paid-only total.

## Changes
- Event Command Center now calculates `total_ticket_count` as the larger of:
  - the reporting source total, or
  - `paid tickets + comp/free count`.
- The note render condition now also checks whether `comp_count > 0`, so a valid manual true-comp count cannot be hidden just because the reporting model total has not incorporated it.
- The packaged test plan now includes the resource-spike diagnostic carry-forward instructions from the cPanel spike discussion, so Codex has the performance investigation note in the same place as the functional regression tests.

## Files changed
- `includes/admin/event-command-center.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `vms-test-plan-0.2.24.668.md`
- `BUILD-NOTES-0.2.24.668.md`
- `docs/05-revision-log.md`

## Validation performed in package build
- PHP syntax check passed for changed files.
- Full plugin PHP syntax check passed.
- JS syntax check passed for non-minified plugin JS files.
- Zip integrity check passed.

## Notes for Codex / staging
Retest section `B.5` from the 0.2.24.667 run. Expected result on the local `Whitehouse Opry` substitute: when `_vms_comp_headcount_true=1`, ECC should render a note like `Total admitted/ticketed: 9 (8 paid + 1 comp/free)` instead of hiding the note.
