# Build Notes 1.3.5

## Release identity

- Plugin: `Backstage Venue Manager`
- Public slug and text domain: `backstage-venue-manager`
- Version and build marker: `1.3.5`
- Release type: focused Ticketing Amenities presentation restoration

## Release scope

This patch restores the existing Event Plan override → global setting → built-in
fallback chain for the Progressive Ticketing Amenities heading and subtext. It
also restores configured add-on help text, a safely validated heading background
with automatic readable foreground contrast, and initially expanded Amenities
when eligible choices exist. A visitor's manual expand/collapse choice is retained
through DOM re-enhancement.

There is no database migration and no intentional change to ticket or rental
availability, minimums, prices, quantity calculations, cart payloads, checkout,
claims, ticket inventory, or order behavior.

## Build procedure

Build only from the clean committed release checkpoint:

`php scripts/build-public-release.php --output-dir <task-owned-output>`

Build twice independently and require identical ZIP SHA-256 values and normalized
extracted manifests. Verify the packaged runtime against the deployed staging
runtime before acceptance.

## Staging gate

Back up the staging database and installed Backstage Venue Manager runtime before
deployment. Deploy only BVM 1.3.5, keep delivery blocked, and use disposable test
data. Verify effective heading/subtext precedence, help visibility, `#dda6a6`
contrast, initial expansion, keyboard/manual collapse and reopening, re-enhancement,
no eligible Amenities, ticket/add-on quantity behavior, minimums, paid-ticket
eligibility, cart handoff, desktop and `390px` layout, overflow, and browser console.
Remove fixtures and restore any temporary settings after acceptance.

## Rollback

Restore the pre-deployment BVM runtime backup and database backup if a settings
or data rollback is required, then clear page/object caches. BVM 1.3.4 remains the
production rollback authority.

## Environment boundary

This release authorizes staging certification only. It does not authorize
production deployment, Outreach deployment or promotion, a default-branch merge,
tagging, WordPress.org submission, real purchases, or message delivery.
