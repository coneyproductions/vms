# VMS Test Plan — 0.2.24.527 Standalone Cancelled-Plan Live Refund Action

## Goal
Confirm that **Run Live Refunds Now** on an already-cancelled Event Plan runs as its own refund action and does **not** trigger a normal Event Plan save.

## Install / version check
1. Install `vms-0.2.24.527-standalone-live-refund-action.zip`.
2. Confirm the plugin reports **0.2.24.527** in the Plugins screen, VMS admin footer/build stamp, and `vms-build.txt`.

## Primary regression check
1. Open an already-cancelled Event Plan that shows a **Cancellation Job** panel and a **Run Live Refunds Now** button.
2. Make a harmless unsaved editor change somewhere on the page, such as adding a character to the post body. Do **not** click Update.
3. Click **Run Live Refunds Now**.
4. Accept the confirmation prompt.
5. Confirm the page returns to the same Event Plan edit screen.
6. Confirm you do **not** see generic editor-save notices such as:
   - `Post updated.`
   - unrelated staffing/time validation errors
   - `Live refund run was not confirmed. No refunds were attempted.`
7. Confirm the unsaved editor change did **not** get saved by the refund action.

## Refund action result check
1. After the standalone refund action runs, review the top notices.
2. Confirm you see refund-specific outcome messaging only, such as:
   - refunded count
   - queued/manual-review count
   - failed count
3. Confirm the page lands back near the **Cancellation Job** panel.
4. Confirm the **Cancellation Job** step details reflect the rerun attempt.

## Safety / cancel-path regression
1. On a different Event Plan that is not yet cancelled, use **Mark Cancelled** with an auto-refund policy.
2. Confirm the existing cancellation flow still asks for confirmation and still creates/runs the cancellation job as before.
3. Confirm this pass did not remove or break the original cancellation path.

## Money-flow verification
🚨 For at least one known safe order, confirm in WooCommerce that a successful live refund creates a real refund record/order note and does not create a duplicate refund when rerun.
