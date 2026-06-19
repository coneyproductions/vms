# VMS Test Plan — 0.2.24.602 — Qualified Ticket Multi-Claim Fix

## Scope

Fixes the qualified/credential ticket validation path so an approved assignee email may be used for more than one ticket **up to that account's effective event allowance**.

This specifically targets cases like Veteran Admission where the program default allowance is commonly `2`, or where an operator manually increases a specific user's verified allowance to a higher value.

## What changed

- Removed the hard duplicate-email block from the front-end qualified-ticket assignment UI.
- Removed the server-side duplicate-email block from AJAX guest-email validation.
- Removed the server-side duplicate-email block from cart/add-to-cart/checkout assignment validation.
- Kept the true limit check intact: prior purchases + existing cart assignments + current assignment cannot exceed the assignee's effective allowance.
- Left TEC native ticket quantity controls, ticket/add-on layout, subtotal math, stock logic, and admin menu behavior unchanged.

## Why this matters

Before this patch, VMS could correctly read an approved credential and still reject the same email as `duplicate_assignee` / “This guest email has already been added” before checking that user's allowance. That made the global default allowance of `2`, and per-user overrides like `4`, appear to be ignored.

## Install / Upgrade

1. Install or upgrade to `0.2.24.602`.
2. Clear any page/object cache that might hold the old `assets/vms-ticketing-front.js` version.
3. Open a clean browser/private window for customer-flow testing.

## 🚨 Codex / staging testing required before production if possible

This touches qualified-ticket purchase validation, so please test on staging/local before installing live unless this is needed as an urgent customer fix.

## Focused regression checks

### 1. Default allowance allows two tickets

1. Use an approved Veteran/credentialed test account with no per-user override and program default allowance `2`.
2. Open an event with a verified/credential ticket requiring assignment/verification.
3. Select quantity `2`.
4. Confirm the customer can proceed without “This guest email has already been added.”
5. Continue to checkout but stop before payment unless using a test gateway.

Expected: two qualified tickets are accepted for the same approved account when the effective allowance is `2`.

### 2. Per-user override above default is honored

1. Edit a test user and set the matching verified allowance override to `4`.
2. Open the same event while logged in as that user.
3. Select quantity `4`.
4. Attempt checkout.

Expected: VMS allows up to `4` qualified tickets for that account, assuming no prior purchases/active assignments already consume the allowance.

### 3. Limit still blocks overage

1. With the same override set to `4`, select quantity `5`.
2. Try to assign/proceed.

Expected: VMS blocks the 5th unit with an allowance/limit message. It must not silently allow unlimited claims.

### 4. Additional approved guest can use multiple seats

1. Use Buyer A with a normal account.
2. Enter Guest B's approved credential email for two assignment rows when Guest B's allowance is `2` or greater.
3. Click Add Qualified Guest on both rows.

Expected: both rows validate if Guest B's allowance supports both seats. A third row should fail when the effective allowance is `2`.

### 5. Prior purchase/cart consumption still counts

1. Complete or simulate a prior paid order consuming one qualified ticket for the approved account.
2. Re-open the same event and attempt the remaining allowed quantity.

Expected: VMS subtracts already-purchased units from the account's effective allowance.

### 6. Existing ticket UI guardrails

Confirm all of the following still work:

- Native TEC quantity stepper remains usable.
- Paid GA ticket purchase still reaches checkout.
- Mixed GA + qualified tickets still calculate subtotal correctly.
- Add-on gating still works.
- Mobile ticket/add-on layout has no horizontal overflow.
- Credential Claims Activity logs success/failure entries with useful reasons.

## Rollback

Rollback to `0.2.24.600` if qualified-ticket checkout breaks broadly, cart validation throws fatal errors, or regular paid ticket purchases are blocked.

Do **not** roll back merely because an account is blocked after it genuinely reaches its effective event allowance.
