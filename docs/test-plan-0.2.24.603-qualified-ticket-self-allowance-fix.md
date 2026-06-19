# VMS Test Plan — 0.2.24.603 — Qualified Ticket Self-Allowance Fix

## Purpose

Verify that an approved/verified ticket buyer can use their own eligible qualified-ticket quantity without being forced into an extra guest-email row too early.

This follow-up fixes the remaining issue from `0.2.24.602`: the duplicate-email validation was relaxed, but the front-end and server-side self-allowance calculation could still effectively behave as though the buyer only had one eligible pass when public ticket max-qty settings or an event/direct-grant quantity were involved.

## Changed Files

- `includes/integrations/ticketing-rules-v2.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- test/handoff docs

## 🚨 Required Browser/Checkout Verification

This touches qualified-ticket purchase validation. Test on staging/local before live unless this is needed as an urgent customer unblock.

### 1. Default verified allowance covers two tickets

1. Use an approved Veteran/credentialed customer account with the program default allowance set to `2`.
2. Open an event with a Veteran/qualified ticket.
3. Select quantity `2`.
4. Confirm VMS does **not** require a second guest email when the logged-in customer has two available passes.
5. Add to cart and proceed to checkout.

Expected: both selected qualified tickets can be covered by the buyer account when their remaining eligible quantity is at least `2`.

### 2. Per-user override covers higher quantity

1. Set the same approved customer's verified allowance override to `4` for the relevant program.
2. Clear the customer's current cart/session if needed.
3. Open the event as that customer.
4. Select quantity `4`.
5. Confirm VMS does **not** require guest-email rows for tickets that are covered by the customer's own allowance.
6. Try quantity `5`.

Expected: quantity `4` is allowed for the buyer account; quantity `5` requires an additional approved guest email or fails gracefully.

### 3. Event/direct grant can raise the allowance

1. If direct event grants are enabled for the ticket, create or update an active event-specific grant for the approved customer with quantity `4`.
2. Confirm the buyer can use up to the grant quantity even when the normal program default is lower.

Expected: active event/direct grant quantities can raise the buyer's effective per-event cap.

### 4. Prior consumption still reduces remaining quantity

1. Complete or simulate a previous qualified-ticket order for the same customer/event.
2. Reopen the event as the same customer.
3. Confirm the available self-covered quantity is reduced by the already-consumed order quantity.

Expected: previously used qualified tickets still count against the allowance.

### 5. Mixed checkout regression

1. Add one or more paid GA tickets.
2. Add allowed qualified tickets.
3. Add eligible add-ons if the event has them.
4. Confirm subtotal math, TEC native quantity controls, helper copy, and checkout reachability still behave normally.

Expected: no unrelated ticketing/add-on regression.

## Rollback

Rollback to `0.2.24.600` if qualified-ticket checkout breaks broadly, cart validation throws PHP fatals, or paid-ticket checkout becomes blocked.
