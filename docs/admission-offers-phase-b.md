# Admission Offers Phase B complimentary core

Status: internal complimentary vertical slice only. No public Offer route, campaign UI, admin Offer editor, paid checkout, Shared Audience, or Outreach integration is registered.

## Native admission authority

The existing admissions REST callbacks remain the operator/scanner transport boundary, not a reusable service API. Phase B reuses the native admissions table, `bvmgr_admission_ensure_entry_token()`, pass/scan URL and local QR helpers, `bvmgr_admission_event_plan_context()`, scanner validation/check-in callbacks, and `bvmgr_admission_audit_log()`. The only shared-admissions change is a post-success audit action used to project native state into linked Offer fulfillments. Admissions without `source=admission_offer` are ignored.

The native provider creates one `party_size=1` admission per expected fulfillment unit. Existing neutral `claim_reference` and `claim_meta` columns hold the deterministic unit reference and BVM-owned Offer/Claim/Fulfillment attribution. Phase B does not use legacy Pass Claim ownership columns and does not alter the admissions schema. Token issuance, QR rendering, pass URLs, optional existing admission email behavior, scanner lookup, and check-in stay owned by the native admissions module; Phase B does not automatically send email.

## Claim transaction and recovery

The provider-facing `BVMGR_Admission_Offer_Claim_Service` accepts scalar IDs, normalized claimant fields, an opaque distribution context, a high-entropy access secret, and an idempotency key. It registers no route. The transaction locks the Offer first, resolves replay by the hashed idempotency key, rejects non-active/new non-complimentary claims, locks eligibility rows, revalidates a published and claimable Event Plan, creates the Claim, checks all retained identity-key versions, writes enforcement identities, checks capacity, moves one reservation from `held` to `consumed`, creates deterministic pending fulfillment units, appends the Claim event, and commits.

Validation and database failures before that commit roll back Claim, identity, capacity, fulfillment, and event rows together. After the durable pending boundary, provider interruption does not silently release capacity: replay locks each deterministic fulfillment unit, reconciles or creates exactly one native admission and token, links its credential, and marks the Claim fulfilled only after every expected unit is complete. This separates an invalid failed claim from a recoverable fulfillment attempt.

## Eligibility and identity

Eligibility supports exact Event Plan, venue, date window, explicit season key, and any-event rules. At least one inclusion must match, and any matching exclusion wins. WordPress post status must be `publish`; BVM workflow status must be `ready`, `published`, or `confirmed`; venue and event date must be valid. Core does not infer a marketing season from titles or dates. Exact season keys come from `bvmgr_admission_offer_event_season_keys`; an empty default fails closed.

Identity policy accepts explicit identity types and defaults to Offer-wide scope. A policy may permit a normalized opaque distribution scope. Raw claimant contact fields exist only on the approved Claim record and native admission; enforcement rows retain only versioned HMAC hashes. Access secrets and idempotency keys are never stored raw. Domain events redact identity, credential, token, and secret-shaped payload fields. A provider-neutral rate-limit filter is available without exposing raw identity.

## Check-in and revocation

Successful native admission audit writes emit a neutral observation action. Linked fulfillment projections derive `fulfilled`, `partially_used`, `used`, or `revoked`; aggregate Claims derive `fulfilled`, `partially_used`, `used`, or `revoked`. Native uncheck-in can therefore project back from used state without changing the scanner architecture. Ordinary Guest List, Guest Pass, vendor, Woo, and TEC admissions have no Offer linkage and remain unchanged.

Claim and individual-unit revocation are operator-capability guarded, cancel the native credential so the existing scanner returns its normal voided response, preserve checked-in fields and all audit/event history, and retain identity enforcement. Capacity is never released automatically. An explicit claim-level pre-check-in revocation may release the consumed reservation and records that decision; any checked-in history permanently prevents release.

## Release boundary

The certified Phase A schema remains at `1.1.0` with the same nine tables. No legacy table, WooCommerce record, TEC attendee, Commerce Discounts object, Outreach source, public route, shortcode, menu, package, deployment, or version marker is changed by Phase B. A future release containing these runtime changes still requires a separately authorized version and packaging phase.
