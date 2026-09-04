# Backstage Outreach

Backstage Outreach is the recovered Guest Pass Outreach workflow for Backstage
Venue Manager 1.2.0 and newer. It is intentionally maintained as a companion
plugin so the public Backstage Venue Manager package does not absorb the
historical outreach and direct-email feature set.

## Runtime requirements

- Backstage Venue Manager 1.2.0 or newer must be active.
- The legacy VMS plugin must remain inactive.
- Activate this plugin only after taking a database backup and confirming the
  historical `vms_*` Outreach tables belong to the target site.

The plugin preserves the historical table and record identifiers. Its schema
upgrade is additive and idempotent: it creates missing Outreach tables, adds
missing claim-attribution columns/indexes, and backfills only missing normalized
status values. It does not drop, rename, truncate, or reset Outreach data.

## Delivery safety

Outreach contains the recovered explicit-send workflow. Merely activating the
plugin does not queue or send invitations, and there is no scheduled sender.
Sending remains an authenticated, nonce-protected admin action limited to valid
Email recipients. Local or staging acceptance should use non-customer addresses
and must not mark real recipients sent.

## Development checks

From the Backstage Venue Manager repository root:

```sh
php tests/backstage-outreach-recovery.php
php tests/backstage-outreach-bvm-integration.php
php tests/backstage-outreach-bootstrap.php
php tests/backstage-outreach-legacy-guard.php
find companion-plugins/backstage-outreach -name '*.php' -print0 | xargs -0 -n1 php -l
```

The companion directory is listed in `release-public-excludes.txt`; it is not
part of the public Backstage Venue Manager release artifact.
