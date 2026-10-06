# Build Notes 1.3.4

## Release identity

- Plugin: `Backstage Venue Manager`
- Public slug and text domain: `backstage-venue-manager`
- Version and build marker: `1.3.4`
- Paired companion release: `Backstage Outreach 1.2.5`, packaged and deployed separately

## Release scope

This patch adds definition-only Guest Pass batch validation for reusable-business
offers and internal transactional tokens for complimentary business claims. Zero
quantity remains invalid for ordinary individual-link batches. Internal tokens are
created only inside the locked claim transaction, immediately claimed or rolled
back, never exposed as operator-facing links, and do not increment
`generated_count`. No schema target changes.

Backstage Outreach 1.2.5 removes the irrelevant individual-link quantity from the
reusable-business form, consolidates its empty state, conditionally presents event
scope fields, and carries plain-language admission limits through review and QR
setup.

## Build procedure

Build BVM only from the clean committed release checkpoint with:

`php scripts/build-public-release.php --output-dir <task-owned-output>`

Build Outreach from the same commit as a standalone archive rooted at
`backstage-outreach/`. Build each artifact twice independently and require
identical ZIP SHA-256 values and normalized extracted manifests.

## Staging gate

Back up the staging database and both installed plugin runtimes, block delivery,
verify runtime drift, deploy the paired artifacts, and verify package/runtime tree
parity. Exercise zero-token complimentary claims, rollback/replay/concurrency,
the 50% offer route, all scopes, 35-business review, UTF-8, focus, and desktop and
390px layouts. Remove every fixture and guard and reconcile monitored data to the
captured baseline.

## Environment boundary

This release authorizes staging certification only. It does not authorize
production deployment, a default-branch merge, real data changes, purchases,
message delivery, tagging, or WordPress.org submission.
