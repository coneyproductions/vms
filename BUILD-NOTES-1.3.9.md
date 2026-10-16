# Build Notes 1.3.9

## Release identity

- Plugin: Backstage Venue Manager
- Public slug and text domain: backstage-venue-manager
- Version and build marker: 1.3.9
- Certified base: 3d67ceec5ae62822387b7beb58e9460eeee7d3a1
- Release type: Wave 2A Guest Pass workflow cleanup

## Release scope

This release adds exact-snapshot preview and guarded commit behavior to Guest
Pass batch creation; conditional validity/value fields; safe name and internal
notes editing; duplication into a paused, uncommitted form draft; conservative
retirement for batches with no issued or linked rights; and factual batch
activity counts. It also aligns the public optional-marketing checkbox and
removes redundant copy.

Issued Guest Pass rights, Outreach-managed reusable links, claims, admissions,
check-ins, campaign relationships, economics, and history are not rewritten.
There is no database schema migration. Broader retirement semantics and the
full reporting/CSV redesign remain deferred to Wave 2B.

## Build procedure

Build only from the clean committed release checkpoint:

    php scripts/build-public-release.php --output-dir <task-owned-output>

Build twice independently and require identical ZIP SHA-256 values and
normalized extracted manifests. Verify the exact packaged runtime against
staging after deployment.

## Staging gate

Back up the staging database and installed Backstage Venue Manager runtime
before deployment. Fingerprint protected campaign, batch, signed-link, claim,
coupon, order, and handoff state before and after. Keep all outbound mail and
HTTP fail-closed during synthetic acceptance.

## Rollback

Restore the captured pre-deployment BVM 1.3.8 runtime and database backup if any
identity, health, data-integrity, or acceptance check fails.

## Environment boundary

This release authorizes staging certification only. It does not authorize
production deployment, real email, tagging, WordPress.org submission, or
changes to Backstage Outreach 1.2.35.
