# Admission Offers Phase C1 paid Claim core

Status: internal paid Claim and temporary Reservation lifecycle only. No WooCommerce session/cart/order/payment/discount integration, checkout or allocation rows, credential fulfillment, public route, admin UI, scheduler, or Outreach integration is registered.

## Claim and capacity boundary

`BVMGR_Admission_Offer_Paid_Claim_Service` accepts only validated percent and fixed Offers. Its transaction serializes on the Offer row, reuses the Phase B Event Plan eligibility resolver, verifies replay against the stored Claim plus versioned identity rows, enforces scoped identity, reconciles stale held Reservations, and atomically creates one durable `claimed` Claim and one temporary `held` Reservation. Complimentary Offers remain exclusively owned by the Phase B service.

The initial soft expiration is acquisition time plus 20 minutes. A semantic renewal targets activity time plus 20 minutes, but every result is capped by immutable `Reservation.created_at + 45 minutes`, Offer expiry, and Claim expiry. Page refresh, session restoration, totals recalculation, background reads, invalid changes, and replay are no-ops. Expired Reservations remain historical, never revive, and release capacity. Explicit release is authority-checked, idempotent, and leaves the Claim durable for a later recovery flow.

The existing nine-table schema remains sufficient. C1 creates no Checkout, Allocation, or Fulfillment records and does not create native admissions, tokens, QR credentials, WooCommerce records, or Event Tickets records.

## Future product allocation rule

When an Event Plan has multiple eligible Woo ticket products, BVM must never auto-select a product by cheapest price, title, display order, or first match. A future distribution/claim allocation must explicitly provide an authorized, server-validated product allocation. C1 does not implement product allocation.
