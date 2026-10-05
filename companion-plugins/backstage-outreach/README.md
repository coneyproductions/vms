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

## Reusable business distribution

Version 1.2 keeps the Version 1.1 complimentary Guest Pass flow intact and adds
an explicit coupon-backed distribution type. Each business still receives one
reviewed, reusable signed referral link. A coupon-backed link establishes a
server-validated WooCommerce session, presents eligible events and terms, and
automatically applies a managed native 50% percentage coupon after an eligible
Event Tickets product enters the existing cart. It does not empty the cart,
replace checkout/payment, or create a ticket outside Event Tickets fulfillment.

Coupon-backed distribution requires either the shared active Free Guest Pass
capacity batch or an active Percent Off batch set to exactly 50%. The discount
is always the managed native 50% coupon; eligible event plans and ticket product
IDs are snapshotted from the reviewed server-side campaign/batch scope. One managed coupon per business
is intentional: WooCommerce can then enforce per-business order use and retain
an unambiguous coupon/order attribution. Outreach reuses only the coupon already
owned by that exact distribution and refuses to overwrite a changed or colliding
coupon. Arbitrary existing coupons are never repurposed.

WooCommerce enforces the coupon's product allowlist, individual-use rule,
per-business order-use cap, and eligible-items-per-order cap. Outreach adds the
combined campaign/batch discounted-ticket quantity limit across business
coupons, counts paid orders separately from complimentary claims, and reserves
capacity under the shared batch lock only for a bounded pending checkout.
Failed, cancelled, and fully refunded orders release that reservation. Pausing,
revoking, or expiring an
offer removes it from carts and blocks unpaid retries; already-paid orders and
their tickets remain untouched.

## Development checks

From the Backstage Venue Manager repository root:

```sh
php tests/backstage-outreach-current-bvm-integration.php
php tests/business-partner-distribution.php
php tests/business-discount-qr-links.php
wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-discount-qr-runtime.php
wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-discount-qr-concurrency.php
find companion-plugins/backstage-outreach -name '*.php' -print0 | xargs -0 -n1 php -l
```

The companion directory is listed in `release-public-excludes.txt`; it is not
part of the public Backstage Venue Manager release artifact.
