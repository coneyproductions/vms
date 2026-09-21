# Build Notes 1.3.0

## Release identity

- Plugin: `Backstage Venue Manager`
- Public slug and text domain: `backstage-venue-manager`
- Version and build marker: `1.3.0`
- Certified source baseline: `0ca4eb0e505f26e16348b20cbfd54243c241ad80`
- Certified source tree: `0794d4caf45ff523289138fcb22140ecb4941c27`

## Release scope

This release consolidates the certified reconstruction into the canonical source line. It carries forward the accepted Core operational and Event Command Center contracts; ticket-purchase extensions and lifecycle stability work; Guest List discovery and event-day actions; private-storage, local-QR, portal-hook, webhook, activation, and path-safety protections; Event Plan rollback and resynchronization corrections; and the follow-up and vendor-outcome workflow.

No new runtime feature or changed runtime authority is introduced by release preparation. Runtime edits in this checkpoint are limited to the four synchronized public release markers.

## Build procedure

Use `php scripts/build-public-release.php` from a clean checkout of the final checkpoint. The builder must complete its release tests, source and staged-package validation, credential scan, PHP lint, JavaScript syntax checks, exclusion checks, and deterministic ZIP construction. Build twice from the same commit and compare both archive SHA-256 and normalized extracted manifests.

The public artifact must have one `backstage-venue-manager/` root. Repository-only documentation, tests, scripts, reconciliation evidence, internal instructions, nested archives, symlinks, and developer-specific paths are excluded by `release-public-excludes.txt` and builder validation.

## Environment boundary

Release preparation and packaging are local-only. This note does not authorize a push, installed-local synchronization, deployment, staging or production access, WordPress.org submission, or reviewer reply.
