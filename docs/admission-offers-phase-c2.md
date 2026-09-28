# Admission Offers Phase C2 Woo session/cart binding

Status: paid Claim and held Reservation binding to a WooCommerce session/cart only. C2 cannot create or attach an order, apply a discount, alter a price, call a gateway, create a TEC attendee or BVM credential, or create an Allocation/Fulfillment row.

## Checkout and session authority

Provider-facing activation requires the existing Claim public ID and raw Claim access secret plus an explicit Woo product ID and quantity. The service validates the secret with the stored hash, reloads Claim/Offer/identity/eligibility/Reservation authority, locks the canonical Claim, and permits at most one `checkout_started` attempt. The Checkout stores only an HMAC proof in `checkout_ref`; its provider name carries the key version. The random binding nonce and neutral Claim/Checkout/Event Plan/product allocation pointers stay in the Woo session. Cart metadata contains only the neutral Claim public ID, Checkout public ID, Event Plan ID, and binding version.

The product must be an explicit Woo ticket product returned by the current BVM/TEC Event Plan authority and linked to the selected TEC event. No title, price, order, URL, or first-result heuristic is used. Bound quantities can remain below the Claim quantity but cannot exceed the approved allocation or Claim quantity. Unrelated tickets, concessions, add-ons, and ordinary Woo products remain unbound and unchanged.

## Cart lifecycle and recovery

Classic and Store API add, restore, whole-cart, quantity, removal, empty-cart, undo, and checkout paths revalidate the server-owned binding. Read, totals, refresh, and restoration do not renew. Initial binding, a valid eligible addition, a valid semantic quantity change, and first checkout entry use the C1 renewal policy and its 20-minute target, 45-minute hard ceiling, and Offer/Claim expiry caps.

Removing the final bound item or emptying the cart abandons the Checkout and releases the held Reservation through the C1 release service while retaining the durable Claim. Undo and explicit session-loss recovery never revive terminal rows: they atomically reacquire capacity, retain historical Checkout/Reservation rows, and create a fresh session binding. A second browser without explicit secret-backed recovery fails; concurrent browsers serialize on the Claim lock.

## C2 safety barrier

Both classic checkout validation and Store API checkout validation stop an Offer-bound cart before Woo order creation with the temporary C2 barrier. Ordinary carts are ignored. The existing nine-table schema and `1.1.0` marker remain unchanged. C2 registers no pricing, fee, coupon, order creation/attachment, payment, Square, attendee, credential, fulfillment, scheduler, Outreach, or Guest Pass mutation hook.
