# Build Notes 1.3.6

## Release identity

- Plugin: `Backstage Venue Manager`
- Public slug and text domain: `backstage-venue-manager`
- Version and build marker: `1.3.6`
- Release type: focused Event Feedback Guest Pass integration

## Release scope

This patch adds eligible Backstage Outreach Guest Pass claimants to the existing
post-event feedback recipient resolver. It uses the claimant identity and exact
Event Plan recorded by the completed claim, requires a claimed token and at least
one uncanceled admission, and deduplicates normalized email addresses across
ticket, Guest Pass, and other complimentary sources.

Other follow-up templates, paid-ticket eligibility, feedback links, recipient
selection, duplicate-send protection, and manual/automatic sending controls are
unchanged. Guest-only feedback recipients are not enrolled in MailPoet as a side
effect. There is no database migration.

## Build procedure

Build only from the clean committed release checkpoint:

`php scripts/build-public-release.php --output-dir <task-owned-output>`

Build twice independently and require identical ZIP SHA-256 values and normalized
extracted manifests. Verify the exact packaged runtime against staging after
deployment.

## Staging gate

Back up the staging database and installed Backstage Venue Manager runtime before
deployment. Keep outbound mail blocked. Verify Guest Pass-only, paid-only, mixed,
normalized duplicate, multi-admission, missing-email, canceled/invalid, wrong-event,
and zero-value complimentary cases without sending a survey or changing MailPoet.

## Rollback

Restore the pre-deployment BVM 1.3.5 runtime backup and database backup, then clear
page/object caches if staging acceptance fails.

## Environment boundary

This release authorizes staging certification only. It does not authorize production
deployment, an Outreach update, tagging, WordPress.org submission, or message delivery.
