# Guest Pass business distribution decision — 2026-10-04

## Authority and integration choice

The active local runtime reports Backstage Venue Manager 1.3.1 from `wp-content/plugins/backstage-venue-manager` and Backstage Outreach 1.0.0 from `wp-content/plugins/backstage-outreach`. The 14 Outreach release files at `origin/release/backstage-outreach-1.0.0` commit `27ee1bbac2ebc1a6122a886dd171605130393657` were byte-identical to the active local Outreach tree before this work. Version labels alone were not treated as byte authority, and staging/production byte parity was not claimed.

The bounded complimentary distributor uses BVM's current Guest Pass claim service and native admission credential authority. That path already owns batch/event eligibility, identity limits, capacity, independent admission entries, credential QR rendering, check-in, email delivery, and token void/restore behavior. Outreach owns reusable business identity, Source membership, campaign/business distribution links, and durable referral attribution.

The preserved Admission Offers C1/C2 branches remain separate, unmerged work. C2 intentionally stops paid offer checkout before order creation, so it is not a safe authority for this complimentary release and its payment barrier must not be bypassed. Paid Neighborhood Offers or Discount Vouchers remain future work.

## Runtime contract

- A partner QR is a signed, high-entropy, reusable marketing link. It is never an admission credential and GET requests allocate nothing.
- Each successful POST atomically consumes one ordinary BVM batch token and creates one native admission credential per requested admission unit.
- One named batch lock serializes campaign capacity and normalized phone/email limits across every partner link for that batch. A database transaction covers submission reservation, BVM claim creation, and referral attribution; email is deferred until after commit.
- A hashed submission key makes retries return the original claim. Outreach stores the resolved campaign, Source, distribution, stable business, BVM token, BVM claim, first admission entry, and party size without changing the historical `outreach_recipient_id` meaning.
- Pausing or revoking a distribution blocks future customer claims. Existing customer credentials and check-in history are retained. Ordinary BVM admission administration remains the customer cancellation/revocation authority and is audited separately. Under the existing BVM policy, canceled admission entries no longer consume the batch-wide active-admission cap; the fulfilled Outreach attribution remains historical and its optional per-business issued-admission cap is not replenished. This prevents a partner from repeatedly canceling and reissuing against a business limit while preserving the original issuance and check-in record.

## Schema and migration

Backstage Outreach 1.1.0 adds four companion-owned tables with `dbDelta`: businesses, Source/business memberships, campaign/business distributions, and distribution claims. The migration is additive and versioned by `backstage_outreach_business_db_version`. It also requests one rewrite flush so `/guest-pass/partner/{token}` becomes available after upgrade.

Historical-recipient and CSV conversion both require preview before commit. Provenance keys make repeated imports idempotent. Original recipient rows, delivery state, claims, and stored snapshots are not rewritten. Distinct historical rows are not silently merged by name or email.

Rollback is source-first: restore the prior BVM shared file and Outreach 1.0.0 files, then deactivate the new companion code if required. The additive tables and option can remain inert for evidence-preserving rollback; deleting those tables is a separate destructive database action and is not part of this task.

## Release boundary

The focused 2026-10-04 release instruction authorizes review fixes, a feature-branch commit and normal push, separate BVM 1.3.2 and Backstage Outreach 1.1.0 artifacts, staging backup/deployment, the additive companion migration, and bounded synthetic staging acceptance with outbound mail blocked. It does not authorize a merge to the default branch, a tag, production deployment, WordPress.org submission, creation or distribution of the real campaign, or customer communication. Synthetic acceptance records and temporary accounts must be removed without deleting historical records.
