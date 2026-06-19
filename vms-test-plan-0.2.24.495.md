# VMS Test Plan — 0.2.24.495

## Focus
Restore Event Plan editor visual parity to the known-good `0.2.24.479` layout shell while preserving later non-Event-Plan fixes already present in `0.2.24.494`.

## Install / Upgrade
1. Install or upgrade to `0.2.24.495`.
2. Open an existing Event Plan that previously showed the layout drift.
3. Hard refresh the browser if needed.

## Core UI checks
1. Confirm the Event Plan editor no longer shows large blank gaps or wrapped/bleeding sections.
2. Confirm the section order matches the expected legacy shell:
   - Event Details
   - Time Lineup & Schedule
   - Title
   - Primary Vendor Compensation
   - Secondary Vendors
   - Staff
   - Event Plan Status & Workflow
   - Ticketing
3. Confirm Primary Vendor Compensation no longer appears to disrupt the sections below it.
4. Confirm Secondary Vendors renders in its normal card shell.
5. Confirm Staff renders in its normal card shell.

## Functional checks
1. Expand/collapse Secondary Vendors.
2. Expand/collapse Staff.
3. Change a harmless field in Compensation, save Draft, and reload.
4. Change a harmless field in Staff, save Draft, and reload.
5. Confirm Ticketing still appears in its usual host area.
6. Confirm Advanced Controls still appear in their usual host area.

## Regression checks
1. Open Vendor Portal and Staff Portal to make sure unrelated recent changes still load.
2. Confirm the public calendar current-month past-events hotfix from `0.2.24.492` is still present.
3. Review `debug.log` for any new PHP warnings/fatals involving:
   - `event-plans.php`
   - `partials/staff.php`
   - Event Plan editor rendering in general

## Expected result
The Event Plan editor should visually match the last known-good `0.2.24.479` shell, without discarding unrelated later fixes.
