# VMS Test Plan — 0.2.24.500

## Focus
Apply a wording-only cleanup to the primary vendor selector label and placeholder on top of the stable `0.2.24.499` baseline. No CSS, layout, placement, or behavior changes are included in this pass.

## Install / Upgrade
1. Install or upgrade to `0.2.24.500`.
2. Open an existing Event Plan.
3. Hard refresh the browser if needed.

## Core checks
1. Confirm the Event Plan layout still matches the stable `0.2.24.499` baseline.
2. Confirm the primary selector label now says `Primary Vendor`.
3. Confirm the primary selector placeholder now says `-- Select Primary Vendor --`.

## Functional checks
1. Open an Event Plan with and without a selected Primary Vendor.
2. Confirm the selected vendor still loads normally.
3. Save Draft or Update and reload.

## Regression checks
1. Confirm there are no layout shifts anywhere in the Event Plan editor.
2. Confirm Secondary Vendors, Staff, and Workflow sections still behave exactly as before.
3. If logging is enabled, review `debug.log` for any new warnings or fatals involving:
   - `partials/time-lineup.php`

## Expected result
The Event Plan remains visually identical to `0.2.24.499`, with the primary selector copy now using `Primary Vendor` consistently.
