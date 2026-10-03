# VMS Express Bar 0.6.12 Test Plan

🚨 Runtime checkout validation required. This pass changes Express Bar order-level tagging for classic checkout and Woo block checkout.

## Setup

- Install VMS Express Bar 0.6.12.
- Confirm plugin header, `VMSEB_VERSION`, and `vms-build.txt` all show `0.6.12`.
- Confirm WooCommerce and VMS are active.
- Use Express Bar QA event plan `1969` and current QA bar products.
- Use a published ticket product for ticket-only and mixed-order validation.

## Tests

1. **Classic checkout, Express Bar only**
   - Add Express Bar items to cart through the runtime harness or classic checkout path.
   - Create an order through Woo classic checkout.
   - Confirm order meta:
     - `_vms_express_bar_order = 1`
     - `_vms_express_bar_event_plan_ids` populated
     - `_vms_express_bar_queue_status = pending`
     - `_vmseb_consumption_summary` populated
   - Confirm order appears in the Express Bar queue.

2. **Block checkout, Express Bar only**
   - Add Express Bar items to cart.
   - Create/process an order through the Woo Store API checkout path.
   - Confirm the same order meta and queue visibility as above.

3. **Ticket-only negative case**
   - Create an order with a ticket product and no Express Bar items.
   - Confirm no Express Bar queue meta is present at the order level.
   - Confirm the order does not appear in the Express Bar queue.

4. **Mixed order**
   - Create an order containing both a ticket product and Express Bar items.
   - Confirm the order is tagged as an Express Bar order and appears in queue.
   - Confirm `_vms_express_bar_event_plan_ids` references the Express Bar event plan only.

5. **Pickup metadata**
   - Confirm pickup name remains visible in cart/checkout UI where supported.
   - Confirm the order line items retain the Express Bar pickup name.

6. **Scheduler health**
   - Capture Action Scheduler counts before testing.
   - Capture Action Scheduler counts after testing.
   - Confirm no unexpected failed/running backlog appears during checkout validation.
