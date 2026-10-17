# Build Notes 1.3.13

## Scope

- Constrains the canonical Per Source Guest Pass row set to sources that own at least one batch matching the active Batch, status, value, availability, or search scope.
- Keeps Per Source UI and CSV on the same server-derived query and canonical batch aggregation.
- Preserves unfiltered source reporting, ordering, paging, bounded exports, Per Batch calculations, and Outreach 1.2.36 compatibility.

## Safety

- Reporting remains read-only and does not create or modify tokens, claims, admissions, invitations, campaigns, distributions, orders, coupons, or customer records.
- Source scope uses typed normalized filters and a correlated existence check; derived availability is evaluated through bounded canonical batch pages.
- Existing capability, nonce, CSV formula-neutralization, bearer-secret, and recipient-PII protections remain unchanged.

## Rollback

Restore the previous plugin files. This release has no schema change or required database rollback.
