# VMS Test Plan — 0.2.24.564 Event Credit Foundation

## Scope

Adds optional Event Credit handling for cancelled events. This does **not** replace refunds. It gives operators a safe path to issue a tracked one-use WooCommerce coupon when a customer chooses credit instead of a refund.

## Changed Files

- `vendor-management-system.php`
- `vms-build.txt`
- `includes/core/registry/constants.php`
- `includes/core/load.php`
- `includes/core/event-credits.php`
- `includes/cpt/event-plans/partials/workflow-status.php`
- `assets/css/vms-admin.css`
- `docs/backlog.txt`
- `docs/idea_pad_context.txt`
- `vms-test-plan-0.2.24.564.md`

## What To Verify

🚨 **CODEX / LOCAL WORDPRESS TESTING REQUIRED** 🚨

This pass touches WooCommerce coupons and cancelled-event order discovery. It should be tested on a local/staging copy before production use.

### 1. Version / install sanity

1. Install or upgrade to `0.2.24.564`.
2. Confirm the Plugins screen shows version `0.2.24.564`.
3. Confirm `vms-build.txt` reads `0.2.24.564`.
4. Confirm there is no activation fatal.

### 2. Event Credits admin area

1. Go to **VMS → Event Credits**.
2. Confirm the list screen loads.
3. Confirm manual “Add New” is not the normal workflow.

Expected: Event Credits are visible/manageable, but created from cancelled Event Plans rather than freehand manual entry.

### 3. Cancelled Event Plan panel

1. Open a cancelled Event Plan that has linked TEC/Woo ticket orders.
2. In the Cancellation section, confirm the new **Customer Resolution: Event Credits** panel appears.
3. Click **Refresh Eligible Orders**.
4. Confirm eligible paid ticket/add-on orders appear.

Expected: The candidate list uses the same event-linked ticket/add-on discovery boundaries as the refund system and does not include unrelated Woo order lines.

### 4. Create credit only

1. For an eligible order, click **Create Credit Only**.
2. Confirm a success notice appears.
3. Confirm the candidate row now shows an Event Credit code.
4. Confirm a `vms_event_credit` record exists under **VMS → Event Credits**.
5. Confirm a Woo coupon exists with the same code.
6. Confirm the original Woo order has a private order note referencing the Event Credit.

Expected: Credit/coupon are created once. Re-clicking should not duplicate the credit for the same original event/order.

### 5. Issue + email

1. For another eligible order with a billing email, click **Issue + Email**.
2. Confirm the credit is created.
3. Confirm status becomes **Issued + emailed** if mail succeeds.
4. Confirm email failure creates a warning but does not delete the credit.

Expected: Credit creation is durable even if `wp_mail()` fails.

### 6. Coupon redemption guardrails

1. Add a future eligible event ticket/add-on to cart.
2. Apply the Event Credit coupon code.
3. Confirm the coupon applies.
4. Try applying the same coupon to an unrelated/non-event product.
5. Try applying it to the original cancelled event product if still reachable.

Expected: Coupon is usable on eligible future event products only, not the original cancelled event or unrelated products.

### 7. Redemption status sync

1. Complete a test order using the Event Credit coupon.
2. Move the order to a paid status if needed.
3. Re-open the Event Credit record.

Expected: Event Credit status changes to **Redeemed** and stores the redeemed order ID/date.

### 8. Void behavior

1. Open an issued Event Credit record.
2. Change status to **Voided**.
3. Save.
4. Open the linked Woo coupon.

Expected: The linked coupon is drafted/disabled.

## Regression Checks

- Existing cancellation refund discovery still works.
- Existing live refund button/action still works.
- Cancelled public event messaging still works.
- Event Plan save/update still works without clicking Event Credit buttons.
- No nested forms are introduced in the Event Plan editor.

## Known Follow-ups

- Customer self-service “refund vs Event Credit” choice link is not included yet.
- Event Credit liability/reporting dashboard is not included yet.
- Customer-facing coupon error copy may need additional polish after live Woo checkout testing.
