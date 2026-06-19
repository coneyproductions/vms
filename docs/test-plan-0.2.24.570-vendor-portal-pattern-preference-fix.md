# VMS 0.2.24.570 Test Plan - Vendor Portal Pattern Preference Reload Fix

🚨 **This repair follows live testing of `0.2.24.569` with VMS Agreements `0.3.10`, which exposed a vendor Availability reload regression.**

This pass does not add a new Availability mode. It repairs the existing vendor portal read path so a saved Pattern preference survives the next normal page load while preserving the dashboard add-on hook and Agreements portal integration added in `0.2.24.569`.

## Install / Version Checks

1. Install/replace VMS Core with `vms-0.2.24.570-vendor-portal-pattern-preference-fix.zip`.
2. Confirm WordPress shows VMS version `0.2.24.570`.
3. Confirm `vms/vms-build.txt` reads `0.2.24.570`.
4. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.570`.

## Syntax / Smoke Checks

1. Run PHP lint on all VMS and VMS Agreements PHP files.
2. Activate VMS Core and VMS Agreements.
3. Confirm no fatal errors on the public page containing `[vms_vendor_portal]`.
4. Log in as a linked vendor and open the Vendor Portal dashboard.

## Primary Repair Check

1. Open the Vendor Portal `Availability` tab for a linked vendor.
2. Save a weekly Pattern selection so `_vms_availability_preferred_method` is stored as `pattern`.
3. Confirm the immediate POST response still shows the Pattern section open.
4. Refresh or revisit the Availability tab with a normal GET request.
5. Confirm the Pattern section remains open after reload and the Manual section does not reopen unless explicitly selected.

## Pair Regression Checks

1. Confirm the Dashboard tab still shows the native cards plus the VMS Agreements Pending Agreements card for a vendor with pending packets.
2. Confirm the Agreements nav/tab still appears for a vendor with pending packets and does not appear for a vendor without them.
3. Confirm a vendor without pending packets who visits `tab=agreements` sees a friendly empty state instead of an error.
4. Confirm vendor portal admin preview mode still works with a valid preview nonce and vendor ID.
5. Confirm public agreement review/print behavior still works for an existing packet sample.

## Notes

- This repair only changes the accepted read-time values for `_vms_availability_preferred_method`.
- If testing reveals another behavior change, update the version/build markers, handoff, relevant test-plan docs, and package filename in the same pass before shipping a replacement zip.
