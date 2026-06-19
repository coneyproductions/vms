# VMS Test Plan — 0.2.24.499

## Focus
Apply a wording-only cleanup to remaining Event Plan operator-facing "Band" text on top of the stable `0.2.24.498` baseline. No CSS, layout, or placement changes are included in this pass.

## Install / Upgrade
1. Install or upgrade to `0.2.24.499`.
2. Open an existing Event Plan.
3. Hard refresh the browser if needed.

## Core checks
1. Confirm the Event Plan layout still matches the stable `0.2.24.498` baseline.
2. Confirm any remaining Event Plan notices and warnings now say `Primary Vendor` instead of `Band` where this is operator-facing text.
3. Confirm the empty tax helper now says `Select a Primary Vendor to see tax requirements.`
4. Confirm the lineup helper now says `vendor availability hints` instead of `per-band availability hints`.

## Functional checks
1. Open an Event Plan with no Primary Vendor selected and confirm the tax helper wording looks correct.
2. Trigger any available Ready-state validation that references the Primary Vendor and confirm the wording is updated.
3. Save Draft and reload.
4. Publish/update and reload.

## Regression checks
1. Confirm there are no layout shifts anywhere in the Event Plan editor.
2. Confirm Secondary Vendors, Staff, and Workflow sections still behave exactly as before.
3. If logging is enabled, review `debug.log` for any new warnings or fatals involving:
   - `event-plans.php`
   - `partials/basic-details.php`
   - `partials/time-lineup.php`
   - `partials/editor-scripts.php`

## Expected result
The Event Plan remains visually identical to `0.2.24.498`, with more consistent operator-facing `Primary Vendor` wording across notices, validation copy, and helper text.
