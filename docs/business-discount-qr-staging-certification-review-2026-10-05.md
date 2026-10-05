# Business discount QR — staging certification review receipt

Date: 2026-10-05
Branch: `feature/business-discount-qr-links-20261005`
Baseline: `f2bc88359da52ce3f0e923c1632e7a4493bd41ab`
Review status: locally ready; real Square sandbox acceptance remains required

## Outcome and architecture

The implementation remains the smallest supported integration: a reusable
signed Backstage Outreach link activates a server-owned distribution mapping,
then native Event Tickets ticket selection, WooCommerce cart/checkout, a native
50% percentage coupon, the configured payment gateway, Woo orders, and Event
Tickets fulfillment remain authoritative. Native WooCommerce `CheckoutLink`
was rejected because its installed implementation empties and repopulates the
cart, which would violate cart and attendee-data preservation. No pricing,
payment, checkout, or ticket engine was added. Admission Offers paid phases and
their checkout barrier remain untouched.

One managed coupon per business distribution is necessary when per-business
native usage limits or coupon-level attribution are required. WooCommerce does
not make one coupon's usage limit a combined limit across other business
coupons. Outreach reuses only the coupon already owned by the exact
distribution when its ownership metadata and configuration hash still match;
it never searches for, edits, or adopts an unrelated coupon.

Coupon offers can use a percent batch configured at exactly 50%, or share the
same active free capacity batch used by a separate complimentary campaign. The
coupon itself is always a native WooCommerce 50% percentage coupon. Supporting
the shared free batch is what permits paid reservations and complimentary
claims to contend truthfully for one batch cap. The complimentary path still
requires `distribution_type=complimentary` and a free batch at landing and
again under its transaction lock.

## Review findings and fixes

1. **Classic order-payment validation could throw before WooCommerce's payment
   exception boundary.** The `woocommerce_before_pay_action` hook now catches
   validation failures and adds a Woo error notice. WooCommerce therefore sees
   the notice and does not call the gateway.
2. **An attributed unpaid order needed exact financial revalidation.** Retry
   now requires exactly one coupon—the distribution-owned coupon—and verifies
   that Commerce Discounts plus the coupon equal exactly 50% of eligible gross.
   Removed/replaced coupons, altered line totals, over-50% Commerce adjustments,
   changed eligibility, pause, revoke, and expiry fail before gateway dispatch.
3. **Paid and complimentary capacity logic shared a lock but could not be
   exercised concurrently under one active batch type.** A fixed-50% paid offer
   may now use the same free capacity batch as a complimentary campaign. Both
   paths serialize on `bvm-pass-batch-{id}` and count paid/unexpired holds plus
   non-cancelled complimentary admissions.
4. **Replay and out-of-order terminal callbacks needed stronger idempotence.**
   Status reconciliation now takes the batch lock, preserves first terminal
   timestamps, refuses to resurrect a failed/cancelled/refunded unpaid order as
   paid, preserves a fully refunded terminal state, and schedules a short WP
   Cron reconciliation if the lock or ledger update is temporarily unavailable.
5. **Refund/revenue reporting was too coarse.** The additive ledger now records
   eligible gross, eligible net, eligible item refunds, full order total, full
   order refunds, and a settlement-review code. Reporting distinguishes native
   coupon uses, paid orders, discounted ticket quantity, admission revenue, net
   whole-order revenue, refunds, cancellations, and review-required settlements.
6. **Paid presentation reused complimentary language.** Paid screens and print
   output now use “Neighborhood Offer” and “Discount Voucher,” explicitly state
   “50% off admission for up to 2 people per order” for the intended
   configuration, and identify normal-price unrelated items. “Guest Pass” and
   claim wording remain confined to the free path.

## Enforcement and payment-in-flight policy

- A signed session stores only distribution ID, signed-token hash, and
  activation time. Every cart restoration reloads the distribution, business,
  membership, campaign, batch, coupon ownership, and product allowlist from the
  server. Submitted business, campaign, coupon, product, and price IDs are not
  trusted.
- A manually entered managed code is invalid without the matching signed
  session or matching attributed unpaid order. Changing business context
  replaces only the managed coupon and preserves cart contents. An invalid
  restored session removes managed coupons.
- Open carts lose the managed coupon when an offer closes. Existing unpaid
  order-payment URLs are blocked and cannot dispatch a new gateway request.
  Their bounded capacity reservation remains until failure/cancellation or the
  Woo hold-stock expiry so an already-dispatched payment cannot oversell.
- If the gateway request was already dispatched before pause/revoke/expiry,
  a later successful capture is honored rather than automatically voided or
  refunded. The order and ticket remain valid and the ledger is marked
  `settled_after_offer_closed`, `late_settlement_after_reservation_expiry`, or
  `late_settlement_after_offer_closed` for operator review.
- Pausing or revoking never alters a paid order, attendee, admission QR, or
  check-in state.

## Limits and accounting definitions

| Measure | Enforcement/reporting |
|---|---|
| Coupon uses | Native WooCommerce coupon lifecycle. This can differ transiently from settled paid orders. |
| Paid orders | Distinct ledger orders in paid state; opening a link and abandoning checkout do not count. |
| Discounted ticket quantity | Eligible ticket quantity on paid ledger rows. Partial refunds do not silently reclassify attendance. |
| Eligible items per order | Native `limit_usage_to_x_items` plus server validation; intended configuration is 2. |
| Per-business order limit | Native usage limit on that business's managed coupon. |
| Per-business ticket limit | Paid/unexpired held quantity plus prior complimentary quantity for the distribution. |
| Campaign ticket limit | Paid/unexpired held quantity plus non-cancelled complimentary admissions for the campaign. |
| Batch ticket limit | Combined quantity across all campaigns/businesses sharing the batch, under one named lock. |
| Admission revenue | Eligible ticket net less item-specific eligible refunds, for rows still classified paid. |
| Net order revenue | Whole Woo order total less refunds, for rows still classified paid. |

Native per-coupon limits do not enforce the campaign-wide or batch-wide total
across businesses; the Outreach ledger and shared lock provide those limits.

## Additive migration

The Outreach business schema target advances from `1.2.0` to `1.2.1`.
`dbDelta()` adds, without dropping or renaming data:

- `eligible_ticket_gross_total DECIMAL(18,6)`
- `eligible_ticket_net_total DECIMAL(18,6)`
- `eligible_ticket_refunded_total DECIMAL(18,6)`
- `order_total DECIMAL(18,6)`
- `settlement_review_code VARCHAR(80)`

Existing 1.2.0 distribution and paid-ledger columns remain. Existing rows
receive database defaults; historical financial fields are not fabricated.

## Dependency boundary

Production dependency versions supplied for staging certification are:

- WooCommerce 11.1.2
- WooCommerce Square 5.5.1, active
- Commerce Discounts 0.2.14-rc1
- Square Reporting 0.1.3
- Event Tickets 5.30.0.1 / Plus 6.9.3

The Local runtime is older/different: WooCommerce 11.0.1, WooCommerce Square
5.2.0 inactive, Commerce Discounts 0.2.13, Event Tickets 5.29.2.1, Event Tickets
Plus 6.9.3, and no Square Reporting plugin. Local logic tests therefore do not
certify gateway acceptance or production-version compatibility. No synthetic
`payment_complete()` result is represented as a Square acceptance test.

## Focused local verification

- Source contract: 26 assertions passed.
- Disposable Woo/Event Tickets runtime: passed. Two customers used the same
  business link for separate $90 orders ($100 eligible ticket at 50% plus one
  unrelated $40 product); a second business retained independent attribution.
- Session/manual-code tests: valid restoration, managed-coupon reapplication,
  business replacement, forged restoration removal, direct-entry rejection,
  and non-stacking passed without removing cart items.
- Retry/state tests: failed retry, expired hold release, cancellation, coupon
  removal, extra-coupon rejection, exact-price tamper rejection, pause, expiry,
  revoke, terminal callback replay, and already-dispatched settlement review
  passed.
- Accounting: three final paid business-B orders / three discounted tickets,
  $140 eligible admission revenue, $260 net whole-order revenue, $100 total
  refunds, one full-refund order, four cancelled reservations, and one
  review-required in-flight settlement reconciled.
- Concurrent one-slot race: clean independent WordPress processes competed with
  a paid reservation and complimentary claim against one shared batch slot.
  Exactly one won; the other received the combined batch-cap error; total
  quantity stayed 1; replay produced at most one paid ledger row and one free
  mapping. The clean-process race passed six runs.
- Native fulfillment: two attendees generated, one checked in, purchased
  tickets survived offer closure, and the existing Woo/Event Tickets item name
  was unchanged.
- Synthetic fixtures and screenshot records were removed after execution.

Runtime screenshots:

- `artifacts/business-discount-qr/operator-distribution.png`
  (`d12da24b35a300f361cbb367c5213cabbf996510c474db17b430221ce3f3096d`)
- `artifacts/business-discount-qr/customer-offer.png`
  (`cc2bbc482e9b5b72c9129340b99de13c834a934e807bd9aac7e87f29889e29ea`)

## Concrete staging acceptance procedure

1. Take a staging database/file backup and record current plugin hashes. Confirm
   the five production dependency versions above, active statuses, HTTPS, cron,
   and no real campaign/customer data will be used.
2. Confirm WooCommerce Square is connected to an **already authorized Square
   sandbox seller/location**, Square Credit Card is enabled, and Commerce
   Discounts is in its native Square bridge mode. If no authorized sandbox is
   present, stop: in WooCommerce > Settings > Square select Sandbox, enter the
   sandbox Application ID and Access Token for the authorized Square developer
   app, save, select its sandbox business location, and enable the Square card
   gateway. Do not copy production credentials. Woo's official sandbox setup is
   documented at <https://woocommerce.com/document/woocommerce-square/testing-the-woocommerce-square-extension-in-sandbox-mode/>.
3. Apply only the Outreach companion candidate and run the additive 1.2.1
   migration. Verify all five new columns, existing distribution/claim/ledger
   row counts, and Outreach 1.1.0 complimentary links before creating a
   synthetic staging-only offer.
4. Create one disposable future Event Tickets event with a $100 eligible ticket
   and one unrelated $40 product. Create a staging-only Source, shared free
   capacity batch, paid campaign, complimentary campaign, and two test
   businesses. Configure the paid offer to 50%, 2 people per order, with an
   explicit expiry and small campaign/batch cap. Block all customer mail or use
   only controlled test inboxes.
5. In two clean customer sessions, open the same business QR, retain unrelated
   cart content, select attendee-required tickets, and complete two separate
   checkouts through **Square sandbox** using Square's current official sandbox
   card data. Verify the Woo order and Square sandbox dashboard both contain the
   transaction, the Square amount equals the Woo amount, line names match the
   original ticket names, the discount references reconcile, and Square
   Reporting records the order. Woo's official procedure requires confirming
   the transaction in both systems.
6. Exercise refresh, back navigation, checkout retry, manual managed-code entry,
   another ordinary coupon, business A/B context replacement, and an unrelated
   product. Verify exact 50%, maximum two eligible tickets, no stacking, stable
   attribution, preserved attendee fields, and unchanged unrelated prices.
7. Run simultaneous last-slot paid and complimentary requests. Verify exactly
   one result, then expire/cancel the pending side and retry. Repeat payment,
   status, refund, and webhook delivery; verify no duplicate ledger/mapping or
   double release.
8. Before gateway submission, pause, expire, and revoke separate offers and
   confirm open carts and order-payment URLs cannot charge. In a controlled
   request already dispatched to Square, close the offer before callback and
   confirm the captured order is retained and marked for settlement review.
9. From WooCommerce, issue an eligible-line partial refund and then a separate
   full refund through Square. Verify the refund IDs/status in Woo and Square,
   Square Reporting totals, coupon usage, paid-order count, ticket quantity,
   admission revenue, whole-order revenue, and preserved attendance
   classification. Woo documents that Square refunds initiated in Woo are sent
   to Square and reflected in its dashboard:
   <https://woocommerce.com/document/woocommerce-square/administrator-experience-with-the-square-woocommerce-extension/>.
10. Fulfill and check in one discounted ticket; confirm paid ticket and Guest
    Pass printing/check-in remain valid after pause/revoke. Rerun the existing
    complimentary claim/replay/print checks. Remove all staging synthetic data
    and record final row counts, screenshots, Square IDs, refund IDs, logs, and
    plugin hashes.

Passing requires an actual Square sandbox charge and gateway refund through the
5.5.1/0.2.14-rc1/0.1.3 path. Simulated payment completion is not a substitute.

## Preservation and rollback

Compared with deployed Outreach 1.1.0, runtime change is additive and confined
to Backstage Outreach: version/bootstrap load, the new discount-offer include,
and additive business-distribution schema/UI/reporting. The complimentary
router, free-batch checks, native claim service, fulfillment, printing, and
existing records remain. The certified BVM runtime has no changed path.

Rollback before staging data exists: restore the backed-up Outreach 1.1.0 files
and leave the additive columns/table in place; they are inert and should not be
dropped. Rollback after synthetic paid tests: first pause/revoke all synthetic
paid distributions and allow or resolve in-flight gateway requests, export the
ledger/Square identifiers, restore Outreach 1.1.0 files, and retain the additive
data for audit. Never delete paid Woo orders, refunds, attendees, or Square
transactions as rollback. Reconnect/restore the prior sandbox configuration
only if staging setup changed it.

No commit, push, package, deployment, real campaign, customer message, poster,
or BVM change is part of this review.
