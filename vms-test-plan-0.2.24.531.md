# VMS Test Plan — 0.2.24.531

## Goal
Confirm **Run Live Refunds Now** on an already-cancelled Event Plan sends a real standalone request with a valid Event Plan ID instead of `0`, on desktop and Safari/iPhone.

## Setup
1. Install `vms-0.2.24.531-live-refund-request-id-fix.zip`.
2. Use an already-cancelled Event Plan that shows **Cancellation Job** and **Run Live Refunds Now**.
3. Make sure at least one cancelled-plan ticket order still exists for refund testing.

## Tests
1. Open the cancelled Event Plan on desktop.
2. Hover or copy the **Run Live Refunds Now** link if convenient and confirm the URL includes `action=vms_run_live_refunds_now` plus a non-zero request ID (`event_plan_id`, `source_post_id`, or `post_id`).
3. Click **Run Live Refunds Now** and choose **Cancel** in the confirmation. Confirm nothing runs.
4. Click **Run Live Refunds Now** again and choose **OK**. Confirm:
   - no normal post save occurs
   - no staffing/editor validation notices appear
   - no “Invalid Event Plan for live refund action. Received ID 0 (unknown type).” error appears
5. Repeat the same click flow on iPhone/Safari.
6. After a successful run, inspect the **Cancellation Job** panel and confirm refund-only result messaging appears.
7. Open one affected Woo order and confirm a real refund note/status exists when the gateway accepted the refund.
8. Confirm already-refunded lines were not refunded twice and manual-review orders remain queued instead of being blindly refunded.

## Expected result
The standalone cancelled-plan refund action resolves the correct Event Plan ID every time and runs without falling back to ID `0`.
