# Outreach 1.2.16 Party paid-referral design

## Boundary

Canonical Person and Organization Parties may own reusable paid Admission Offer links without creating or borrowing business identities. Existing business distribution IDs, signatures (`/guest-pass/business/...`), `sr-biz50-` coupons, order metadata, and ledgers keep their prior meaning.

## Typed adapter

- Party links use `/admission-offer/partner/...`, a `party-referral|1|` signature domain, and `sr-party-` managed coupons.
- Coupon, session, order, and eligible line-item metadata carry an explicit `party` owner type plus a Party distribution ID. Absence of that marker continues to mean the legacy business owner type.
- The established eligibility, WooCommerce coupon, cart, checkout, tax, order-status, refund, pricing-integrity, and batch-lock paths are shared. They select the appropriate typed distribution and redemption table rather than duplicating the payment engine.
- Campaign and batch admission limits sum active holds/paid admissions across both business and Party ledgers under the existing batch lock. Party and optional paid-order limits are checked in the same lock.
- BVM paid-attendance classification continues to derive from the purchased ticket product. Referring Party attribution is separate from purchaser identity.

## Additive persistence

Party schema `1.1.0` adds:

- `vms_outreach_party_referral_distributions`, keyed independently by campaign and canonical Party, with signed-link state, reviewed configuration hash, ticket scope, limits, coupon ownership, and expiry.
- `vms_outreach_party_referral_redemptions`, keyed by WooCommerce order, with Party attribution, admission quantity, financial/refund snapshots, reservation state, and settlement timestamps.

The established Outreach schemas remain `1.1.0` and `1.3.0`; BVM schema and runtime do not change. No Party data is backfilled and no business row is reinterpreted.

## Fail-closed compatibility

Only active Person/Organization Parties with active membership in the campaign Source can be reviewed. New Party links require active reviewed Percentage Off or Fixed Amount Off batches; legacy Free-capacity 50% business compatibility is not extended to new Party links. Changed campaign/batch terms, source drift, inactive ownership, expired/paused/revoked links, invalid signatures, coupon ownership drift, and pricing drift fail closed. Replayed valid links remain reusable and do not consume capacity until an eligible checkout reservation is created.
