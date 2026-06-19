# VMS Test Plan — 0.2.24.525 True Live Cancellation Auto-Refunds

Test only the cancellation auto-refund changes.

## Goal
Verify that cancelling an Event Plan with an auto-refund policy now attempts live WooCommerce gateway refunds instead of always queueing everything for manual review.

## Setup
1. Use an event with at least one paid WooCommerce/TEC ticket order.
2. Confirm the order was paid through a gateway that supports WooCommerce refunds.
3. Optional: keep one mixed/unsupported/manual-refund order available so you can confirm the queue-for-review fallback still works.

## Test
1. Open the Event Plan edit screen.
2. In Cancellation policy, choose **Stop sales + auto refund**.
3. Click **Mark Cancelled**.
4. Confirm the warning dialog now explicitly says VMS will attempt **LIVE** refunds and only refund matching event ticket lines on mixed orders.
5. Let the cancellation save finish.
6. In the Cancellation Job panel, confirm **Refund discovery** shows candidate counts plus auto-eligible/manual-review counts.
7. Confirm **Refund execution** shows at least one **Refunds sent** entry instead of queueing every order by default.
8. Open one refunded Woo order and confirm a real WooCommerce refund record exists with the expected amount and gateway/order notes.
9. If you included an unsupported/manual-refund order, confirm it was queued for manual review with a readable reason instead of being falsely marked refunded.
10. Repeat with **Stop sales + queue refunds** and confirm that policy still queues orders without attempting live refunds.

## Report
Report PASS or FAIL only, with brief evidence and the order numbers tested.
