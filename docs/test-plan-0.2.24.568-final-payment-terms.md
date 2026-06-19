# VMS 0.2.24.568 Test Plan - Final Payment Terms Foundation

## Purpose
Verify that Event Plan vendor compensation can store expected final payment timing and method, including ACH / Direct Deposit, and that agreement snapshots can consume those terms from VMS Core.

## Manual test steps

1. Install/replace VMS Core with `vms-0.2.24.568-final-payment-terms.zip`.
2. Open an Event Plan with a primary vendor and compensation terms.
3. In Compensation -> Final Payment, set Expected Final Payment to `Day of event` and Payment Method to `ACH / Direct Deposit`.
4. Save/update the Event Plan.
5. Reload the Event Plan and confirm the Final Payment fields persist.
6. Change Expected Final Payment to `N days after event`, enter `7`, save, reload, and confirm the value persists.
7. Change Expected Final Payment to `Specific date`, choose a date, save, reload, and confirm the value persists.
8. Change Payment Method to `Other`, enter a custom method, save, reload, and confirm the custom method persists.
9. Click `Lock Draft Pay for This Event` and confirm the Locked Pay summary includes expected final payment timing and payment method.
10. Change only a final payment field and save; confirm the Draft Pay differs from Locked Snapshot warning appears because the compensation hash changed.
11. Apply/re-apply a compensation package and confirm event-level deposit/final-payment terms are not wiped unless explicitly edited on the Event Plan.

## Expected result
Final payment terms are treated as Event Plan compensation truth and appear in core compensation snapshots/hash checks without changing existing ticketing, vendor portal, or refund behavior.

## Codex failure/version instructions
If a test fails, document the failure, fix the smallest durable root cause, re-run the failed test plus an adjacent regression test, and package a complete replacement zip. If Codex edits code, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision log, handoff, relevant test plan docs, and package filename in the same pass. If only docs change, do not bump the runtime version unless behavior changed.
