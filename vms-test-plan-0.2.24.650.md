# VMS 0.2.24.650 Test Plan — Disabled Ticket Public UI Guard

## Goal

Confirm that disabling a qualified/free ticket and saving config does not leave the old public Woo/TEC ticket visibly selectable while ticket changes are pending push/commit.

## Primary regression test

1. Open an Event Plan with at least one qualified/free ticket that currently exists publicly.
2. Disable the qualified/free ticket row, for example `Nurse Appreciation Week`.
3. Click **Save Config** only.
4. Do **not** push/commit ticket changes.
5. Open the public event page in a logged-out/private browser window.
6. Hard refresh / clear page cache if needed.

### Expected result

- The disabled ticket row is not visibly selectable.
- No `+` control remains active for that disabled ticket.
- The disabled ticket quantity cannot remain at a stale nonzero value.
- The sticky footer total does not count the disabled ticket.
- Add-ons such as Fire Pits/Tables do not become eligible based only on the disabled ticket quantity.

## Direct add-to-cart safety test

1. Capture the disabled ticket product ID from the existing public product/sync map if available.
2. Attempt a direct add-to-cart URL or atomic add request for that product.

### Expected result

- The add fails with the disabled-ticket unavailable notice.
- The product is not added to the cart.

## Cart/checkout safety test

1. Before installing this build, or using another session, get the soon-to-be-disabled ticket into a cart if possible.
2. Install this build.
3. Disable the ticket in config and Save Config only.
4. Visit cart and checkout.

### Expected result

- Cart/checkout validation blocks the disabled ticket.
- Customer is told the ticket is no longer available and must remove it/refresh.

## Positive control

1. Re-enable the ticket.
2. Save Config.
3. Push/commit ticket changes normally.
4. Confirm public behavior matches the enabled ticket rules.

### Expected result

- Enabled qualified tickets still show their registration/approval copy.
- Eligible approved customers can still claim tickets according to their allowance.
- Public paid GA tickets remain purchasable normally.

## Notes

This build is intended to protect the pending-sync window. It does not replace the normal ticket push/commit workflow; it makes that workflow fail closed while public Woo/TEC products are temporarily stale.
