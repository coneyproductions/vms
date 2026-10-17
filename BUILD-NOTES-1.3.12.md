# Build Notes 1.3.12

## Scope

- Adds canonical read-only Guest Pass aggregation shared by Per Source / Per Batch UI and CSV.
- Keeps direct issued-link claim rate separate from Outreach reusable-link allocation utilization.
- Counts native admission rows for actual reserved and checked-in headcount, excluding canceled rows.
- Adds invitation attempt, unique-link, handoff, failure, and indeterminate metrics without treating handoff as inbox delivery.
- Reads optional Outreach 1.2.36 distribution and paid-redemption tables through a narrow capability-checked adapter.

## Safety

- Reporting never creates or modifies tokens, claims, admissions, invitations, campaigns, distributions, orders, coupons, or customer records.
- CSV requires the existing capability and nonce, streams bounded pages, neutralizes spreadsheet formulas, and contains no signed URLs, token hashes, unsubscribe values, or recipient PII.
- Missing Outreach functions or tables produce an explicit unavailable state while BVM-owned metrics remain usable.

## Rollback

Restore the previous plugin files. This release has no schema change or required database rollback.
