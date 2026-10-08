# Outreach 1.2.15 — Unified Contacts & Partners foundation

## Decision

Phase 1 is safe as an additive Outreach-owned identity layer. Existing Outreach Contacts, reusable businesses, campaign-recipient snapshots, BVM Sources, campaign distributions, signed links, coupons, claims, orders, contact activities, and mail audits remain operationally authoritative and are not rewritten or backfilled.

The new directory answers “who is this person or organization?” It does not answer “may this party receive or distribute this offer?” Campaign and distribution eligibility continue to come from the existing operational workflows until a separately accepted integration phase.

## Persistence design

The schema is independently versioned by `backstage_outreach_party_db_version`. All tables use the WordPress prefix and are owned by Backstage Outreach.

| Table suffix | Authority | Purpose and important constraints |
| --- | --- | --- |
| `vms_outreach_party_parties` | Authoritative canonical identity | Stable UUID plus `person` or `organization`, display and component names, status, identifying details, notes, provenance, and operator timestamps. Names and channels are editable attributes, never identity keys. |
| `vms_outreach_party_contact_methods` | Authoritative canonical contact method | Multiple email, phone, website, and social methods per Party. Normalized values are indexed matching hints but are intentionally not globally unique. A Party/method/value tuple is unique. Status and primary flags preserve changed channels without rewriting history. |
| `vms_outreach_party_affiliations` | Authoritative canonical relationship | Many persons to many organizations, with role, status, effective dates, provenance, notes, and operator timestamps. Application validation enforces person → organization direction. |
| `vms_outreach_party_sources` | Authoritative canonical directory association | Many Parties to many existing BVM Source IDs. A unique Party/Source association is idempotent. This association does not create reusable-business membership or campaign eligibility. |
| `vms_outreach_party_campaign_roles` | Authoritative canonical directory association | Many Parties to many existing campaign IDs and explicit roles such as `contacted` or `referrer`. It has no delivery, token, offer, or checkout effect in Phase 1. |
| `vms_outreach_party_legacy_links` | Authoritative current compatibility mapping | Reviewed one-to-one mapping from each supported legacy reference (`outreach_contact`, `business`, or `campaign_recipient`) to one canonical Party. One Party may own many legacy links. Snapshot hashes detect drift. |
| `vms_outreach_party_identity_audit` | Append-only audit | Records proposal review, link, unlink, correction, contact, affiliation, Source, and campaign-role decisions with before/after Party IDs, reasons, operator, request key, and timestamp. |

WordPress `dbDelta()` creates the additive tables. No foreign keys are declared because some referenced tables are BVM-owned and WordPress upgrades must tolerate independent plugin lifecycle. Services enforce referential integrity, transactions, row locking, unique keys, and immutable audit writes.

Rollback for Phase 1 is isolated: disable the directory entry point, then—only after exporting the new tables—drop these seven tables and delete `backstage_outreach_party_db_version`. Existing tables and schema markers `vms_outreach_db_version=1.1.0` and `backstage_outreach_business_db_version=1.3.0` are not rolled back or changed.

## Identity and duplicate rules

- A Party ID and UUID are stable independent of name, email, phone, Source, affiliation, or campaign.
- Email and phone normalization support search and suggestions only. Shared office email, shared phone, changed channels, and person/organization overlap are valid.
- The system never automatically merges Parties or creates a Party from a legacy record.
- Suggestions identify exact normalized channel matches and normalized name matches, explain each reason, and mark multiple plausible candidates as ambiguous.
- Operators explicitly confirm a Party/legacy association against a current legacy snapshot hash.
- Repeating the same association is idempotent. A legacy reference already linked to another Party fails closed.
- Correction explicitly reassigns the single current mapping and writes an audit record. Unlinking removes only the compatibility mapping and writes an audit record; neither action changes the legacy row.

## Legacy compatibility adapters

Supported references are:

- `outreach_contact` → existing `vms_outreach_contacts.id`
- `business` → existing `vms_outreach_businesses.id`
- `campaign_recipient` → existing pass-Outreach recipient ID

Adapters return a sanitized read-only identity snapshot and its SHA-256 digest. The mapping stores that digest as reviewed evidence. Historical rows, legacy IDs, campaign history, signed links, coupons, tokens, and delivery state are never changed by association work.

The Realtor scenario therefore maps the two explicitly verified recipient snapshots for a Realtor to one person Party while retaining both campaign histories. Brokerages are organization Parties connected through affiliations. Matching brokerage names are suggestions for organization review, never proof that two people are the same. Party-to-Source association with Source 5 does not create a reusable-business membership and cannot make Source 5 eligible for Business Review.

For Fitness, mapping an existing business to an organization Party changes only the compatibility mapping. Campaign 25 / Batch 71, 35 distributions, customer and flyer URLs, managed coupons, delivery history, limits, expiration, checkout attribution, refunds, and accounting remain untouched.

## Suppression contract

Suppression remains authoritative in the existing normalized-address suppression table. The directory derives suppression state for every active email method at read time. Creating or editing a Party does not subscribe, unsuppress, queue, or send anything. A future communication service must re-check suppression for the selected channel immediately before handoff.

## Administrator interface

The compact **Contacts & Partners** directory supports people and organizations, search across identity/channel/affiliation fields, Party type and Source filters, a missing-email filter, duplicate-review indicators, affiliations, legacy links, and basic canonical edits. The interface labels canonical values separately from immutable legacy snapshots.

Existing **Legacy Contacts / Prospects** remains available during Phase 1. It stays authoritative for existing individual-recipient workflows and CSV behavior.

## Future distribution contract

The next phase may consume typed IDs without changing Phase 1 rows:

- `contacted_party_id`: who Outreach communicates with.
- `referring_party_id`: person or organization receiving reusable referral authority.
- `redeeming_customer`: checkout or claim identity, never inferred to be the referring Party.
- `offer_definition`: none, complimentary, percentage, or fixed amount.
- `distribution_authority`: one-time personal invitation or reusable partner authority.
- `communication_activity`: channel-specific audited handoff/outcome.
- `conversion_attribution`: claim/order/refund attribution to distribution authority and referring Party.

Paid referral offers must extend the certified WooCommerce business-discount path and keep taxes, refunds, replay/concurrency, capacity, order attribution, and paid-attendance classification intact. They must not use complimentary BVM Guest Pass claims. Phase 1 neither exposes nor activates paid-individual offer controls.

## Migration strategy

There is no automatic migration or backfill. Operators may generate reviewed suggestions and confirm mappings incrementally. A later rollout may provide a bounded migration preview, but it must use the same snapshot, ambiguity, explicit-review, idempotency, correction, and audit contracts. Existing operations remain authoritative until that rollout is separately accepted.
