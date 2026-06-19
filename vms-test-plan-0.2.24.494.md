# VMS Test Plan — 0.2.24.494

## Scope
Rollback-only safety patch for the Event Plan refactor helper extraction regression introduced in 0.2.24.493.

This build keeps the 0.2.24.492 public calendar current-month past-events hotfix intact and restores the pre-0.2.24.493 Event Plan editor rendering path.

## Focused checks

1. Open an existing Event Plan in wp-admin.
2. Confirm these sections render with normal full-width layout and expected order:
   - Event Plan Details
   - Title
   - Primary Vendor Compensation
   - Secondary Vendors
   - Staff
   - Event Plan Status & Workflow
   - Ticketing
3. Confirm no odd half-width / nested-column carryover appears after the compensation section.
4. Expand and collapse Secondary Vendors and Staff.
5. Save Draft and reload the same Event Plan.
6. Confirm compensation controls still render normally:
   - option tiles
   - draft pay fields
   - lock snapshot / acknowledgment boxes
7. Confirm staff assignments, role cards, and tax badges still render.
8. Review debug.log for any new PHP warnings/fatals tied to `event-plans.php` or Event Plan partial rendering.

## Expected result
The Event Plan editor should look like the stable pre-0.2.24.493 layout again, with no refactor-induced rendering regression.
