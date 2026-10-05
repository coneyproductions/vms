# Build Notes 1.3.2

## Release identity

- Plugin: `Backstage Venue Manager`
- Public slug and text domain: `backstage-venue-manager`
- Version and build marker: `1.3.2`
- Companion release: `Backstage Outreach 1.1.0`, packaged separately

## Release scope

This patch adds the BVM integration surface for reusable-business Guest Pass distribution owned by Backstage Outreach 1.1.0. BVM remains the native Source, batch, claim, admission credential, check-in, cancellation, and quick-print authority. The public BVM artifact continues to exclude the Outreach companion.

The Passes screen gains shared sanitized filters, deterministic pagination, referring-business attribution when the companion is active, and a batched all-matching CSV export. The existing native claim path gains a batch-scoped serialization contract and neutral extension context so the companion can atomically reserve capacity and attribution without changing ordinary Guest Pass behavior.

## Build procedure

Build only from the clean committed release checkpoint with:

`php scripts/build-public-release.php --output-dir <task-owned-output>`

Build twice from the same commit. Require identical ZIP SHA-256 values and normalized extracted manifests. The BVM artifact must contain one `backstage-venue-manager/` root and no `companion-plugins/backstage-outreach` path. Build Backstage Outreach 1.1.0 separately with one `backstage-outreach/` root.

## Staging gate

Back up the staging database and both installed plugin directories, block outbound mail, verify runtime drift, deploy both artifacts, run the additive companion migration, flush and verify rewrites, compare deployed runtime manifests to the artifacts, and complete bounded synthetic claim/admin acceptance before considering any production action.

## Environment boundary

This release task authorizes staging certification only. It does not authorize production deployment, creation or distribution of the real campaign, tagging, merging to the default branch, WordPress.org submission, or customer communication.
