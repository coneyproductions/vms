# Build Notes 1.3.8

## Release identity

- Plugin: Backstage Venue Manager
- Public slug and text domain: backstage-venue-manager
- Version and build marker: 1.3.8
- Certified base: 4877ad3bd88a18b59104b3c5b9705af28cd17029
- Release type: focused staffing-notification safety hotfix

## Release scope

This patch suppresses staffing email when the canonical Event Plan performance
end has passed or cannot be resolved safely. Historical staffing edits remain
available. The guard applies when notification work is created and immediately
before transport, including cron recovery and manual retries.

Upcoming proposals, confirmations, cancellations, schedule changes, assignment
links, historical cutover, duplicate suppression, and delivery failure handling
remain unchanged. No database migration is introduced.

## Build procedure

Build only from the clean committed release checkpoint:

    php scripts/build-public-release.php --output-dir <task-owned-output>

Build twice independently and require identical ZIP SHA-256 values and normalized
extracted manifests. Verify the exact packaged runtime against staging after
deployment.

## Staging gate

Back up the staging database and installed Backstage Venue Manager runtime before
deployment. Require staffing notifications to be disabled and keep all outbound
mail and HTTP fail-closed throughout synthetic acceptance.

## Rollback

Restore the captured pre-deployment BVM 1.3.7 runtime and database backup, then
clear relevant caches if staging acceptance fails.

## Environment boundary

This release authorizes staging certification only. It does not authorize
production deployment, real email, tagging, WordPress.org submission, or changes
to other BVM features.
