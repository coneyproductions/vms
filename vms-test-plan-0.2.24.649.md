# VMS 0.2.24.649 Test Plan — Disabled Qualified Ticket Safety Guard

## Goal

Verify that saving a ticket config with a qualified/free ticket disabled cannot temporarily make that ticket publicly claimable before the operator runs Preview → Commit / Push Ticket Changes.

## Regression Setup

Use a staging Event Plan with:

- Ticketing v2 enabled.
- At least one paid/public ticket.
- At least one free or qualified ticket with `visibility_mode = verified`, an approved-program rule, and a mapped Woo/TEC product already visible from a previous ticket push.
- A logged-out browser/session available for public testing.
- A logged-in non-approved customer account available for public testing.
- Optional: an approved customer account to confirm normal behavior before disabling.

## Test Cases

### 1. Disabled qualified ticket fails closed before ticket push

1. Open the Event Plan in wp-admin.
2. In Ticketing v2 config, disable the qualified/free ticket row.
3. Click **Save Config** only.
4. Do **not** run Preview → Commit / Push Ticket Changes.
5. In a logged-out browser, open the public event page.
6. If the old qualified ticket product is still visible, try to add it to cart.

Expected:

- The ticket must not add to cart.
- The customer should see an unavailable-ticket error similar to: `"Ticket Name" is no longer available for this event.`
- No free/qualified ticket can be loaded into cart without approval.

### 2. Disabled qualified ticket blocks checkout if already in cart

1. Before disabling, add the qualified/free ticket to a test cart if possible.
2. Disable the config row and click **Save Config** only.
3. Visit cart and checkout.

Expected:

- Cart/checkout shows the unavailable-ticket blocker.
- Checkout cannot proceed until the disabled ticket is removed.

### 3. Atomic/progressive ticket UI path blocks the disabled ticket

1. With the same disabled-but-not-pushed state, use the progressive ticket UI if it still exposes the old product in any way.
2. Attempt to add the disabled qualified/free ticket through the VMS atomic add endpoint.

Expected:

- The atomic add request returns an error with code `ticket_disabled_pending_sync`.
- The ticket does not enter the cart.

### 4. Legacy/adopted product metadata fallback preserves verification

1. Use a mapped legacy/adopted qualified ticket product where VMS product markers are incomplete or suspected stale.
2. Confirm the last pushed sync map still marks the row as `verified`.
3. Attempt to add the ticket while logged out or logged in as a non-approved customer.

Expected:

- The ticket still requires login/verification based on the last pushed sync map.
- Missing product meta must not downgrade it to public/free admission.

### 5. Push/Commit still retires disabled ticket normally

1. Run Preview → Commit / Push Ticket Changes after disabling the qualified ticket.
2. Refresh the public event page.

Expected:

- The disabled ticket product is retired/drafted/hidden according to existing commit behavior.
- The disabled-ticket blocker should no longer be the only protection; the product should be gone or unavailable publicly.

### 6. Public/general admission remains unaffected

1. Add normal paid/public GA tickets to cart.
2. Increase/reduce quantities.
3. Proceed to checkout validation.

Expected:

- GA ticket quantities remain governed by Woo/TEC inventory, not a new VMS per-customer cap.
- The 0.2.24.641 group-purchase behavior remains intact.

## Codex Notes

🚨 This patch needs a staging test specifically around the dangerous Save Config → public page → add free ticket window. Do not only test after running Commit, because Commit already retires disabled products.

