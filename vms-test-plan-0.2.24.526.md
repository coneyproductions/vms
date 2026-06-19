# VMS Test Plan — 0.2.24.526 Cancelled-Plan Live Refund Rerun Button

Test only the already-cancelled live-refund rerun pass.

## Goal
Verify that a previously cancelled Event Plan can submit a real batch refund run after the fact, without uncancelling the plan.

## Setup
1. Use an Event Plan already in **Cancelled** status.
2. The cancellation policy on that plan should be one of these:
   - **Stop sales + queue refunds**
   - **Stop sales + auto refund**
   - **Stop sales + auto refund + attendee cleanup**
3. The plan should have at least one ticket order tied to it that is still refundable.
4. Optional: include one mixed order and/or one order that should remain manual-review-only so you can confirm safe fallback behavior.

## Test A — button visibility
1. Open the already-cancelled Event Plan edit screen.
2. In the **Cancellation Job** panel, confirm a button labeled **Run Live Refunds Now** appears.
3. Confirm the helper text explains that VMS will re-scan remaining eligible ticket orders, attempt live WooCommerce refunds, and skip unsafe orders into manual review.

## Test B — confirmation behavior
1. Click **Run Live Refunds Now**.
2. Confirm the browser dialog clearly warns that VMS will attempt LIVE refunds now for the already-cancelled event.
3. Cancel the dialog once and confirm nothing runs.
4. Click again and accept the dialog.

## Test C — job execution
1. After confirming, allow the save/reload to complete.
2. In the **Cancellation Job** panel, confirm **Refund discovery** and **Refund execution** have rerun.
3. Confirm the job log now reflects a manual live-refund request / rerun rather than only the original cancellation pass.
4. Confirm at least one eligible Woo order now has a real WooCommerce refund record and gateway/order notes.
5. Confirm any mixed/unsupported/unsafe order remains queued for manual review rather than being blindly refunded.
6. Confirm previously refunded lines are not refunded a second time.

## Test D — queue-only upgrade path
1. Repeat with a cancelled plan whose stored cancellation policy is **Stop sales + queue refunds**.
2. Click **Run Live Refunds Now**.
3. Confirm VMS upgrades that already-cancelled plan to a live auto-refund policy for this rerun and reports that in the admin notices / job history.
4. Confirm eligible refunds are attempted live after the rerun.

## Expected result
A cancelled event can submit a live batch refund attempt after the fact, safely and audibly, without changing the event back out of Cancelled status.
