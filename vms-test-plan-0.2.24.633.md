# VMS 0.2.24.633 Test Plan — Checkout Button Recovery After Turnstile/Woo Validation

## Purpose
Verify that a failed Woo checkout validation, including Cloudflare Turnstile not being checked before submit, does not leave the Place Order button permanently disabled after the customer corrects the issue.

## What changed
- VMS cart/checkout blocking now relies on VMS server-side cart blockers only.
- Native Woo/Turnstile notices may still display, but VMS no longer treats those notices as persistent checkout blockers.
- Checkout recovery listeners run after checkout field changes, Turnstile interaction, Woo `updated_checkout`, and Woo `checkout_error` events.
- If VMS server blockers are clear, the Place Order button is re-enabled after the corrected field/challenge state.

## Required tests

### 1. Cloudflare/Turnstile missed checkbox recovery
1. Add an event ticket to cart.
2. Go to checkout.
3. Leave Cloudflare/Turnstile unchecked.
4. Click Place Order.
5. Confirm the expected validation warning appears.
6. Check/complete Cloudflare/Turnstile.
7. Confirm Place Order re-enables without refreshing the page.
8. Submit again and confirm checkout proceeds to the normal next payment/order behavior.

### 2. Normal Woo required-field recovery
1. On checkout, clear a required billing field.
2. Click Place Order.
3. Confirm Woo validation warning appears.
4. Fill the missing field.
5. Confirm Place Order re-enables without refresh.

### 3. VMS blocker still blocks
1. Create a cart state that has a real VMS ticket/add-on blocker, such as an add-on without the required qualifying ticket count.
2. Go to checkout.
3. Confirm VMS still blocks checkout and shows the VMS blocker message.
4. Fix the cart issue from cart/event page.
5. Confirm checkout becomes available after the VMS blocker clears.

### 4. Regression smoke
1. Confirm ordinary checkout without validation errors still works.
2. Confirm cart checkout button still blocks when VMS server blockers exist.
3. Confirm no duplicate VMS blocker notices appear.

## Notes for Codex
🚨 Pay special attention to the exact failure the operator reported: submit checkout before checking Cloudflare, then check Cloudflare afterward. The button must recover without refresh.
