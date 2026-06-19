# VMS Test Plan — 0.2.24.565 Event Credit Coupon Guardrails

## Scope

This pass reconciles the Codex-tested Event Credit coupon guardrail repair into a packaged build. The feature remains the same as `0.2.24.564`, but the package now includes the live-tested fix that prevents Event Credit fixed-cart coupons from being accepted on unrelated carts/products.

## Changed Files

- `vendor-management-system.php`
- `vms-build.txt`
- `includes/core/registry/constants.php`
- `includes/core/event-credits.php`
- `docs/backlog.txt`
- `docs/05-revision-log.md`
- `vms-test-plan-0.2.24.565.md`

## Codex Repair Instructions

Codex should first run the test plan exactly as written.

If a test fails:

1. Identify the failing behavior.
2. Make the smallest safe code change needed to fix that failure.
3. Do not redesign unrelated workflows.
4. Do not touch unrelated ticketing templates, guest lists, checkout UI, or refund logic unless the failed Event Credit test proves they are directly involved.
5. Re-run the failed test and related regression checks.
6. Update all required version/build markers and build notes when code changes are made:
   - plugin header `Version`
   - `VMS_VERSION`
   - `vms-build.txt`
   - revision/build notes
   - test plan notes when applicable
7. Report what failed, what files changed, why the change was necessary, and what was retested.

## What To Verify

🚨 **CODEX / LOCAL WORDPRESS TESTING REQUIRED** 🚨

This build touches WooCommerce coupon validation. Test on local/staging before production use.

### 1. Version / install sanity

1. Install or upgrade to `0.2.24.565`.
2. Confirm the Plugins screen shows version `0.2.24.565`.
3. Confirm `vms-build.txt` reads `0.2.24.565`.
4. Confirm **VMS → Settings** or any visible VMS version surface reports `0.2.24.565` if available.
5. Confirm there is no activation fatal.

### 2. Event Credit admin sanity

1. Go to **VMS → Event Credits**.
2. Confirm the list screen loads.
3. Confirm Event Credits remain operator-managed records, not a normal freehand public workflow.

### 3. Cancelled Event Plan candidate scan

1. Open a cancelled Event Plan with paid ticket/add-on orders.
2. Confirm the **Customer Resolution: Event Credits** panel appears.
3. Click **Refresh Eligible Orders**.
4. Confirm eligible paid event-linked ticket/add-on orders appear.
5. Confirm unrelated Woo order lines are not included in the Event Credit amount.

### 4. Coupon guardrail regression

Using a real Event Credit coupon code from a cancelled Event Plan:

1. Add an eligible future event product to cart.
2. Apply the Event Credit coupon.
3. Confirm the coupon applies.
4. Empty the cart.
5. Add an unrelated/non-event product to cart.
6. Apply the same Event Credit coupon.
7. Confirm Woo rejects it.
8. Empty the cart.
9. Add the original cancelled-event product to cart if it is still reachable.
10. Apply the same Event Credit coupon.
11. Confirm Woo rejects it or does not discount it.

Expected: Event Credit coupons are only usable on eligible future event products. They must not behave like generic fixed-cart coupons.

### 5. Redemption sync

1. Complete a test order using the Event Credit coupon on an eligible future event product.
2. Move the order to a paid status if needed.
3. Re-open the Event Credit record.

Expected: Event Credit status changes to **Redeemed** and stores the redeemed order ID/date.

### 6. Void behavior

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
- No unrelated ticketing templates, guest lists, checkout UI, or refund logic are changed.

## Known Follow-ups

- Customer self-service “refund vs Event Credit” choice link is not included yet.
- Event Credit liability/reporting dashboard is not included yet.
- Customer-facing invalid-coupon copy may need additional polish after more live Woo checkout testing.
