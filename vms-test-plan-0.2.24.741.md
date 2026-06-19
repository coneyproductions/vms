# VMS 0.2.24.741 Test Plan — Early Bird Caps, Sale Availability, Refund-Aware Ticket Notice

## Pre-checks

1. Activate VMS `0.2.24.741` on staging.
2. Confirm version markers:
   - plugin header shows `0.2.24.741`
   - `VMS_VERSION` is `0.2.24.741`
   - `vms/vms-build.txt` begins with `0.2.24.741`
3. Clear page/object cache before public event checks.

## Admin settings checks

1. Open VMS settings for the public Ticket UI.
2. Confirm these global settings are present:
   - Display total ticket availability
   - Low threshold
   - Display sale quantity remaining
   - Sale low threshold
3. Confirm recommended defaults resolve as:
   - Total availability: only show when low
   - Low threshold: `25`
   - Sale quantity: show when capped sale is active
   - Sale low threshold: `10`
4. Save settings and confirm the values persist after reload.

## Event Plan override checks

1. Open an Event Plan with V2/progressive tickets.
2. In Public ticket UI overrides, confirm these event-level override selects are present:
   - Total availability
   - Sale availability
3. Set each override to inherit, save, and confirm the event still follows global settings.
4. Set total availability to hide and sale availability to show capped sale quantity, save, and confirm the event-level values persist.

## Early Bird cap setup

1. On a paid public ticket, configure:
   - Regular price: `$20`
   - Early price: `$15`
   - Early end date in the future
   - Early cap: `50`
2. Save the Event Plan and trigger ticket/product sync if needed.
3. Open the public event page as a customer.
4. Confirm the ticket row shows Early Bird sale UI with capped sale quantity, for example `Early Bird: 50 available at $15 • Ends Aug 19`.
5. Confirm regular total inventory does not show when the configured total availability mode is hidden or low-only above threshold.

## Cap-only Early Bird behavior

1. Configure a paid public ticket with:
   - Regular price greater than Early price
   - Early cap greater than zero
   - No Early end date
2. Save/sync and open the public event page.
3. Confirm Early Bird pricing is active while the cap has remaining quantity.
4. Confirm the sale message shows capped availability without an end-date suffix.

## Sale low-threshold display

1. Set Sale availability to `Only when sale quantity is low`.
2. Use a capped sale with remaining quantity above the Sale low threshold.
3. Confirm the sale deadline can still show, but the exact remaining sale quantity is hidden.
4. Reduce remaining sale quantity to at or below the threshold.
5. Confirm the message switches to `Only X Early Bird tickets left`.

## Early Bird checkout enforcement

1. With a capped Early Bird sale active and remaining quantity greater than zero, add a quantity at or below the remaining sale quantity.
2. Confirm add-to-cart succeeds and cart pricing uses Early Bird price.
3. Try to add more than the remaining active Early Bird quantity.
4. Confirm add-to-cart/cart/checkout shows a clear error asking the customer to reduce quantity or refresh.
5. Exhaust the Early Bird cap.
6. Confirm the same ticket can still be purchased at regular price after refresh, rather than being blocked entirely.

## Refunded ticket notice check

1. As a logged-in test customer, purchase tickets for a staging event.
2. Confirm the event page shows the native `You have X Tickets for this Event. View Tickets` notice.
3. Refund the test tickets fully in WooCommerce.
4. Return to the public event page as the same logged-in user.
5. Confirm the notice is hidden when active net ticket quantity is zero.
6. For a partial refund, confirm the notice count changes to the remaining active quantity.

## Regression checks

1. Confirm child/youth ratio limits still require qualifying paid tickets before selection.
2. Confirm verified/free tickets still require the expected registration/verification state.
3. Confirm ticket max quantity per order still blocks over-limit quantities.
4. Confirm cancelled-event cart blocking still fires before checkout.
5. Confirm add-ons and progressive ticket UI still add items to cart normally.
6. Confirm events with no Early Bird cap behave the same as before except for the new total availability display setting.
