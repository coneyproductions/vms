# VMS Test Plan — 0.2.24.563 Cancellation Refund Add-ons

🚨 CODEX TESTING REQUIRED before relying on this for a production cancellation with live customer refunds.

## Scope
This pass expands cancellation refund discovery/execution so VMS refunds event-linked add-ons/entitlements along with TEC ticket lines, while still excluding unrelated Woo products on the same order.

## What changed
- Cancellation product discovery now includes TEC tickets plus VMS products linked by Event Plan ID, TEC event ID, or ticketing sync-map rows.
- Refund discovery now matches order lines by vetted event product IDs and by stored VMS order-item event snapshots.
- Refund execution rechecks live refunded quantity per line item before creating the refund, so retry runs do not double-refund already-refunded ticket/add-on lines.
- Cancellation sales-stop now closes VMS event add-on products as well as ticket products.

## Primary local test
1. Create or use a test Event Plan with:
   - one paid TEC ticket product,
   - one paid VMS add-on/entitlement product,
   - one unrelated Woo product that is not linked to the Event Plan.
2. Place a test order containing all three products.
3. Cancel the Event Plan using an auto-refund policy with the normal live-refund confirmation flow.
4. Confirm the refund candidate includes:
   - the TEC ticket line,
   - the VMS add-on/entitlement line.
5. Confirm the refund candidate does not include the unrelated Woo product line.
6. Execute the refund in a test/sandbox-safe environment.
7. Confirm Woo creates one refund containing only the ticket and add-on line items.
8. Confirm `restock_items` remains false.
9. Confirm the order still shows the unrelated product as not refunded.

## Retry safety test
1. Re-run the manual live refund request on the same cancelled Event Plan.
2. Confirm already-refunded ticket/add-on quantities are skipped and no duplicate refund is created.
3. Confirm the cancellation job summary reports either no refundable amount or no new refund lines.

## Legacy/snapshot safety test
1. Place a test order for a VMS add-on, then temporarily alter or remove the product-level event linkage in local testing only.
2. Confirm discovery can still match the add-on line if the order item contains `_vms_event_plan_id` or `_vms_tec_event_post_id` snapshot meta.
3. Restore the product metadata after the test.

## Regression checks
- Cancelled RSVP tickets still draft/disable as before.
- Auto-refund guard behavior still blocks production live refunds unless confirmation/capability requirements are met.
- Unsupported payment gateways still queue orders for manual review.
- Orders with only unrelated Woo products are not refunded.
- Orders where the ticket was already refunded but the event add-on remains refundable can still refund the remaining add-on line in a fresh refund run.
