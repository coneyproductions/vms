# Business discount QR links — local implementation record

Date: 2026-10-05
Branch: `feature/business-discount-qr-links-20261005`
Baseline: `f2bc88359da52ce3f0e923c1632e7a4493bd41ab`

## Decision

The smallest supported integration is a signed Backstage Outreach landing link
plus a managed native WooCommerce percentage coupon. The link stores only a
server-validated distribution identifier and signed-token hash in the
WooCommerce session. Customers select tickets on the existing Event Tickets
event page and proceed through the existing WooCommerce block cart, checkout,
gateway, order, attendee, email, and ticket lifecycle.

WooCommerce 11.0.1 includes a `CheckoutLink` service, but that service empties
the cart and directly adds products. It was rejected because this feature must
preserve existing cart contents and the Event Tickets selection/attendee-data
path. No alternative pricing, payment, ticket, or checkout engine was added.
The unfinished Admission Offers paid phases remain unmerged and their checkout
barrier is unchanged.

Implementation is confined to Backstage Outreach 1.2.0. The installed BVM
1.3.2 runtime contains certified production improvements absent from the full
mirror artifact, so no BVM runtime file was edited. The mirror companion and
installed `backstage-outreach` copy are synchronized.

## Coupon ownership and attribution

Each coupon-backed business distribution owns one native `percent` coupon set
to exactly 50%. Separate coupons are necessary because WooCommerce usage limits
are coupon-specific and the coupon/order pair provides unambiguous business
attribution. A single coupon shared by all businesses could not safely enforce
per-business order limits or distinguish native coupon use by business.

Outreach reuses only the coupon ID already linked to the exact distribution,
and only when managed ownership metadata and a configuration hash still match.
It refuses an ownership mismatch, code collision, or out-of-band configuration
change. It does not search for, repurpose, or silently edit unrelated coupons.

Durable attribution is written to the order, eligible order items, and the
`vms_outreach_paid_redemptions` ledger:

- Source, campaign, business, distribution, coupon, order, and eligible ticket
  product IDs;
- discounted ticket quantity, eligible gross/net/item refunds, coupon discount,
  whole-order total/refunds, currency, lifecycle status, settlement-review code,
  and timestamps;
- a unique order mapping, while the signed URL maps to the distribution only on
  the server. Submitted campaign, business, coupon, and product identifiers are
  not trusted.

## Eligibility and pricing

Coupon setup requires either the linked active free capacity batch, permitting
paid and complimentary campaigns to share a true batch cap, or a linked active
batch with `value_type=percent` and `value_amount=50`. The native coupon remains
fixed at exactly 50% in either case. The campaign/batch event rules are resolved
through the existing BVM eligibility helpers. Only published, positive-price
Woo ticket products linked to those TEC events are stored in the distribution
snapshot and native coupon product allowlist. Other tickets, merchandise,
rentals, food, and add-ons retain their established prices.

The managed coupon is individual-use. Applying it removes another Woo coupon,
and WooCommerce refuses a later coupon that would combine with it. If Commerce
Discounts already adjusted an eligible ticket by less than 50%, the coupon is
reduced to the remaining amount needed for exactly 50% total discount. An
existing Commerce Discount above 50% blocks checkout rather than silently
stacking or changing the advertised result.

## Limits and lifecycle

| Measure | Owner/enforcement | Reporting meaning |
|---|---|---|
| Coupon uses | WooCommerce `usage_limit` on the business coupon | Native coupon usage lifecycle; may differ transiently from paid orders |
| Eligible items per order | WooCommerce `limit_usage_to_x_items`, plus pre-order validation | Maximum discounted ticket quantity in one order |
| Paid orders | Outreach paid-redemption ledger on payment/processing/completed | Completed paid redemptions only; landing views and abandoned carts do not count |
| Per-business ticket quantity | Outreach named-lock reservation/ledger | Paid plus unexpired pending quantities and any prior complimentary claims for that distribution |
| Campaign-wide ticket quantity | Outreach named-lock reservation/ledger | Paid plus unexpired pending coupon tickets and non-cancelled complimentary admissions for the campaign |
| Batch-wide ticket quantity | Outreach named-lock reservation/ledger | Same combined accounting across campaigns sharing the linked batch |

Native coupon limits do not provide a combined cap across different business
coupons. The companion ledger therefore serializes reservation under a
batch lock shared with complimentary claims and separately checks campaign and
batch totals. Pending checkout capacity expires using the WooCommerce
hold-stock interval (minimum ten minutes). Failed and cancelled orders release
it immediately. A full refund is reported as refunded and releases quantity; a
partial refund keeps the paid ticket quantity/attendance classification while
recording item-specific and whole-order refund totals.

Pause, revoke, distribution expiry, campaign expiry, and batch expiry are
checked on landing, coupon validation, cart calculation, order creation, retry,
and immediately before payment. Managed coupons cannot be used by typing the
code without the signed session or matching attributed unpaid order. Pausing or
revoking drafts the coupon and removes it from open carts. An already-created
unpaid order is blocked before payment; removed/additional coupons and changed
50% pricing are also blocked. A charge already dispatched before closure is
honored and flagged for settlement review rather than automatically
voided/refunded. Paid orders and native Event Tickets attendees are never changed by offer status.
Revocation remains permanent for the existing signed link.

## Local runtime and verification boundary

Inspected locally: WooCommerce 11.0.1, Event Tickets 5.29.2.1, Event Tickets
Plus 6.9.3, VMS Commerce Discounts 0.2.13, BVM 1.3.2, the Woo block checkout,
and WooCommerce Square 5.2.0. Square is installed but inactive and no authorized
sandbox connection is available, so no live Square sandbox charge was sent.
Disposable native Woo order payment completion verified local lifecycle logic,
but it is not gateway acceptance. An actual Square sandbox charge and refund
through the production dependency versions remain mandatory staging gates.

The disposable runtime test creates then removes synthetic events, tickets,
products, Source/batch/campaign/business/distribution/coupons/orders/refunds and
attendees. It verifies two independent customers using the same business offer,
cross-business attribution, signed-session restore/replacement, failed-payment
retry, cancellation, expired reservation release, item-specific refund, coupon
removal/addition, exact retry-price validation, exactly 50% eligible-ticket
discount, unrelated pricing, non-stacking, native paid-order usage limit,
separate accounting, pause/expiry/revoke, in-flight settlement review, callback
replay, and native Event Tickets fulfillment/check-in. A clean-process
paid-versus-complimentary last-slot race proves the shared batch lock/cap.
Screenshot fixtures are likewise removed;
only PNG evidence remains in `artifacts/business-discount-qr/`.

The staging certification review, production dependency matrix, additive 1.2.1
migration, rollback plan, and concrete Square sandbox acceptance procedure are
in `docs/business-discount-qr-staging-certification-review-2026-10-05.md`.

No real campaign, customer message, package, commit, push, merge, tag,
deployment, WordPress.org action, or poster was created.
