# Admission Offers Phase A foundation

Status: dormant foundation only. No Phase B behavior is present.

## Runtime boundary

Phase A loads provider-neutral domain types, additive schema readiness, repositories, an append-only event ledger, and the transaction-safe Offer capacity service. It registers one `plugins_loaded` schema-readiness callback. It does not register a public route, admin menu, shortcode, cart or checkout callback, discount rule, payment observer, ticket/attendee callback, QR credential, scanner behavior, or fulfillment provider.

Legacy Guest Pass batches, pass tokens, pass claims, and their existing public routes remain a separate compatibility subsystem. The new foundation never writes legacy pass tables and does not convert historical records.

## Table boundaries

The approved nine-table design is retained because each table owns a distinct lifecycle or consistency boundary:

1. `vms_admission_offers` owns value, policy, hard global capacity, and Offer state.
2. `vms_admission_offer_eligibility` owns include/exclude eligibility rules independently of claim state.
3. `vms_admission_offer_claims` owns a claimant's right to use an Offer. A discounted Claim is not a credential.
4. `vms_admission_offer_claim_identities` owns hashed scoped-identity enforcement without placing multi-identity uniqueness on the Claim row.
5. `vms_admission_offer_reservations` owns temporary and committed capacity states plus idempotent acquisition.
6. `vms_admission_offer_checkouts` owns provider-neutral paid-checkout state and integer monetary snapshots without asserting payment or fulfillment.
7. `vms_admission_offer_order_allocations` owns the many-item allocation boundary between a Claim and future external order items.
8. `vms_admission_offer_fulfillments` owns future credential-provider state separately from Claims and Checkouts.
9. `vms_admission_offer_events` owns the append-only, retry-idempotent, redacted domain audit stream.

Combining Claims with Reservations would conflate durable entitlement with expiring capacity. Combining Checkouts, Allocations, or Fulfillments would conflate external payment state, line allocation, and actual credential issuance. Combining identities with Claims would prevent multiple identity types and provider-defined scope boundaries. No table was removed merely to reduce count.

All nine tables are additive InnoDB tables. They have no destructive foreign-key cascades, do not rename or modify legacy tables, and use a separate `vms_admission_offers_db_version` option. A rollback to the previous runtime ignores the option and unused tables, which remain harmless and retained.

## Identity scope

The enforcement key is unique on:

`offer_id + identity_scope_key + identity_type + hash_key_version + identity_hash`

The core default scope is `offer`. A distribution provider may supply another normalized opaque namespace such as `provider:campaign:opaque-id`; BVM stores and compares the namespace but does not interpret it or query provider tables. Normalized identity values are HMAC-hashed before insertion using a cryptographically generated, per-installation BVM keyring stored in the `vms_admission_offers_identity_keyring` option. Each enforcement row records its key version. Rotation appends and activates a new key while retaining prior keys; checks compute candidates for all retained versions while holding the Offer lock, so rotation cannot make a historical identity eligible again. Missing, corrupt, or incomplete key material fails closed. Database backups naturally carry the keyring, while independently installed environments receive independent keys. Claim access secrets and reservation idempotency keys use stable domain-separated SHA-256 hashes because they are high-entropy opaque values and must not depend on rotatable WordPress salts. Claim contact fields remain separate for the explicitly required claimant record. `household_key` is accepted only as an explicit future identity value and is never derived from surname, postal address, or other claimant data.

## Capacity transaction

Every acquisition starts an InnoDB transaction and follows the canonical order `Offer -> Claim -> relevant Reservation/Identity rows -> state/event write`. It locks the Offer row with `SELECT ... FOR UPDATE`, locks and validates the Claim, lazily expires stale `held` reservations, resolves a hashed idempotency key, counts current holds plus `order_attached`/`consumed` commitments, and inserts or renews atomically. The Offer-row lock serializes all contenders for the same global capacity. Failures roll back, and rollback failure is surfaced. MySQL deadlock (`1213`) and lock-wait timeout (`1205`) errors receive at most three transaction attempts with a bounded delay; other failures are never retried. The stable idempotency hash makes an ambiguous retry safe. Transients and an unlocked aggregate followed by a later insert are not used.

Associative policy/config data is recursively key-sorted before JSON hashing or persistence; lists retain order and scalar types remain distinct. The generic Phase A state writer rejects provider-authority states such as `paid`, `fulfilled`, `used`, and `refunded`. Future provider-specific code must establish that authority rather than inventing it through the dormant foundation.

Distribution-specific sub-caps are not implemented. A future provider may enforce an opaque narrower scope before calling the core service, but cannot override the authoritative Offer capacity.

## Monetary and future integration boundary

Percent values are integer basis points from 1 through 9999. Fixed values are positive integer minor units and must match the caller-supplied WooCommerce store currency. Paid validation rejects a result below one minor unit; a fully free right is `offer_type=complimentary`. No floating-point monetary authority or multi-currency behavior exists.

The distribution, pricing, and fulfillment interfaces ship without implementations. Public BVM does not require Backstage Outreach or Commerce Discounts. Before any later phase adds WooCommerce, Event Tickets, Event Tickets Plus, or Square runtime hooks, payment truth and attendee-generation behavior must be re-audited against the actual then-current certified production versions. The older versions observed during architecture reconnaissance are not runtime authority for a future phase.

## Release boundary

Phase A is a runtime change and therefore requires a future public release version increment before packaging or WordPress.org distribution. This implementation intentionally does not change the current `1.3.1` markers, build artifacts, tags, or deployment state.
