# VMS Test Plan — 0.2.24.498

## Focus
Apply a text-only cleanup to the Event Plan title auto-sync wording on top of the stable `0.2.24.495` baseline. No CSS, layout, or section placement changes are included in this pass.

## Install / Upgrade
1. Install or upgrade to `0.2.24.498`.
2. Open an existing Event Plan.
3. Hard refresh the browser if needed.

## Core checks
1. Confirm the Event Plan layout matches the stable `0.2.24.495` baseline.
2. Confirm the title checkbox now reads `Auto-update title to Primary Vendor`.
3. Confirm the helper note now references `Primary Vendor` instead of `Band`.
4. Confirm the empty preview text now reads `(select Primary Vendor to preview)`.
5. Change the Primary Vendor and confirm the browser prompt now references `Primary Vendor`.

## Functional checks
1. Toggle the auto-title checkbox on and off.
2. Save Draft and reload.
3. Publish/update and reload.
4. Confirm no title-sync behavior changed other than wording.

## Regression checks
1. Confirm there are no layout shifts in the Title area or lower Event Plan sections.
2. Review `debug.log` for any new warnings or fatals involving:
   - `event-plans.php`
   - `partials/title.php`
   - `partials/editor-scripts.php`

## Expected result
The Event Plan remains visually identical to `0.2.24.495`, with title auto-sync wording updated from `Band` to `Primary Vendor` anywhere the operator sees that flow.
