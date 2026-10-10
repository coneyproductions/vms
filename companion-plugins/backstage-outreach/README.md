# Backstage Outreach

Current release: **1.2.22**. Business campaign setup now presents existing and new offer batches as equal, explicit paths while preserving reviewed selections and new-batch drafts. Promotional flyers can inherit a dedicated venue flyer logo or use a campaign-specific override without changing WordPress or BVM branding; the existing site logo remains the fallback. Existing Contacts, recipients, distributions, signed links, coupons, claims, suppression, delivery history, and MailPoet subscriber state remain authoritative and unchanged.

Backstage Outreach is the recovered Guest Pass Outreach workflow for Backstage
Venue Manager 1.2.0 and newer. It is intentionally maintained as a companion
plugin so the public Backstage Venue Manager package does not absorb the
historical outreach and direct-email feature set.

## Runtime requirements

- Backstage Venue Manager 1.2.0 or newer must be active.
- The legacy VMS plugin must remain inactive.
- Activate this plugin only after taking a database backup and confirming the
  historical `vms_*` Outreach tables belong to the target site.
- Promotional handoff requires a complete WooCommerce venue postal address,
  opaque-token storage/signing, and the registered HTTPS confirmation endpoint.
- RFC 8058 advertisement is optional unless the site explicitly requires it.
  PHPMailer DKIM is detected automatically. An externally signed transport must
  explicitly report both `ready` and `signs_rfc8058_headers` through the
  `backstage_outreach_mail_transport_readiness` filter. Without that evidence,
  Outreach omits both one-click headers while retaining the body link.

The plugin preserves the historical table and record identifiers. Its schema
upgrade is additive and idempotent: it creates missing Outreach tables, adds
missing claim-attribution columns/indexes, and backfills only missing normalized
status values. Version 1.2.19 adds an opaque unsubscribe-token table. It
does not drop, rename, truncate, or reset Outreach data.

## Mandatory promotional unsubscribe

Outreach appends the venue postal identity and a personalized HTTPS unsubscribe
URL to every promotional message at the final handoff boundary; operators do
not need to add a merge tag. A normal GET renders a responsive confirmation
page and never changes suppression state, protecting against link scanners and
prefetchers. Browser confirmation or, when advertised, a valid RFC 8058
one-click POST creates or reuses a global suppression record. Repeated requests
report that the address was already unsubscribed, and a prior stronger
suppression reason is preserved.

Tokens are random, stored only as hashes, and authenticated with a durable
site-specific signing key. The public URL contains neither an email address nor
a database identifier. Suppression is checked again under the same recipient
lock immediately before every `wp_mail()` handoff, including queued execution
and explicit resend paths. Failure to create or validate the token, acquire the
lock, load suppression infrastructure, resolve postal identity, or confirm the
registered HTTPS endpoint prevents the handoff. Unverified transport signing
suppresses only the optional RFC 8058 headers. Sites whose applicable policy
requires one-click may make transport verification mandatory with the
`backstage_outreach_require_rfc8058` filter.

## Transport verification

Do not infer RFC 8058 compliance from MailPoet or from downstream delivery
alone. The smallest conclusive check for a downstream signer is one separately
authorized message to a controlled mailbox: inspect the raw received message
for an aligned `Authentication-Results: dkim=pass` and a `DKIM-Signature` whose
`h=` list includes both `list-unsubscribe` and `list-unsubscribe-post`. Until
that evidence exists, leave transport readiness unverified and Outreach will
omit both headers.

## Canonical Party partner workflow

Version 1.2.18.1 retains the operator-reviewed workflow for adopting historical
campaign recipients into canonical Person Parties. Only a shared historical
Contact ID groups snapshots authoritatively. When Contact IDs are absent, an
exact normalized email, person name, and organization across different
campaigns may form a compatibility proposal, with its evidence displayed for
explicit operator confirmation. No single shared name, email, phone, or
organization is sufficient. Commit retries reuse reviewed legacy links and
provenance, while the original recipient snapshots remain unchanged.

Reusable Partner Admission Offers use a new paid definition-only batch and a
new linked campaign. They do not convert existing complimentary campaigns or
batches, generate Guest Pass claim tokens, or require reusable-business
memberships. Bulk generation delegates to the typed Party referral engine and
verifies each signed link and managed coupon independently, so failed rows can
be retried without duplicating successful rows.

The Party contact dashboard records exact invitation snapshots and manual
email, phone, text, social, or note activity. First-time handoff is protected
against duplicates; resend is a separate explicit action. Global Outreach
suppression and missing-email checks fail closed, and a successful `wp_mail()`
return is recorded only as an accepted handoff—not as delivery. Immediately
before every handoff, the server revalidates the active Party, Source
association, campaign, paid batch, distribution, expiry, signed referral,
managed coupon ownership, offer configuration, suppression, and send mode.

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
automatically applies a managed native percentage or fixed-product coupon after an eligible
Event Tickets product enters the existing cart. It does not empty the cart,
replace checkout/payment, or create a ticket outside Event Tickets fulfillment.

New coupon-backed distributions use an active Percentage Off or Fixed Amount Off
batch and derive the exact managed coupon value from that reviewed batch. Legacy
paid 50% distributions may continue to share an active Free capacity batch; they
remain explicitly treated as 50% compatibility distributions rather than having
their stored data rewritten. Eligible event plans and ticket product
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

## Business CSV review

Version 1.2.2 expands Import Businesses preview into a complete, non-mutating
review of every parsed CSV record. The review shows the sanitized values that
will be stored, physical CSV row numbers, full addresses, validation results,
missing-email delivery limitations, and expandable Notes. Unsupported headers,
duplicate canonical/alias mappings, invalid records, and empty valid sets block
commit in both the interface and the server handler. `address` is supported as
an explicit alias for `address_line`.

A replacement upload invalidates the prior preview before validation begins,
so a failed replacement cannot leave an older file available to commit. The
10-minute preview expiry, capability and nonce checks, atomic transaction,
provenance keys, and one-record-per-business identity model remain unchanged.

## Reusable business campaign setup

Version 1.2.3 adds an explicit Reusable Business Links / QRs campaign route.
An operator selects an existing business Source and compatible active batch,
then reviews every current active membership before creating the campaign.
Businesses without email remain link-eligible; email values are reference data
only and do not prepare delivery. The review keeps business QR count separate
from individual claim-link quantity and the batch shared-admission cap.

Campaign creation revalidates the Source, batch, active memberships, and the
reviewed digest before saving. It creates no historical recipient/contact rows,
preserves independent business identity, carries the Source and batch directly
into business QR setup, and defaults all reviewed active businesses for the
next review step. Complimentary, Percentage Off, and Fixed Amount Off batches
retain their distinct distribution behavior. Message previews are labeled
as sample data, and display-time punctuation repair leaves historical stored
records unchanged.

Version 1.2.4 guides the operator through the reusable-business prerequisites
inside Outreach. The batch picker exposes only active batches owned by the
selected Source whose offer is complimentary, percentage off, or fixed amount off. A Source with
no eligible batch has an explicit reviewed batch-definition flow that preserves
the campaign draft and creates no individual claim links. Preview and campaign
creation retain server-side Source, batch, membership, and review drift checks
when browser validation is absent or bypassed.

The setup screen presents Source, offer batch, business review, campaign
creation, and QR generation in order. Definition-only business batches store a
true zero individual-link quantity; operators choose admissions per customer
and the total admissions available across all businesses. Scope-specific fields
appear only for the selected event scope, while optional offer expiry remains
separate. The reviewed Source/batch carries directly into QR setup, and saved
link results render as labeled cards on narrow screens. Complimentary business
claims create an internal claim token only inside the claim transaction, so the
zero-token definition remains usable without generating operator-facing links.

Version 1.2.5 simplifies that definition form, presents one accessible missing-
batch action, and shows only the scope fields that affect the reviewed offer.
It labels per-customer and across-business admission limits in plain language.
Together with Backstage Venue Manager 1.3.4, complimentary reusable-business
claims create and immediately claim an internal transactional token without
exposing or counting it as an individual claim link.

Version 1.2.6 gives reusable-business paid offers neutral “Admission Offer” wording. Percentage
offers accept a reviewed value greater than 0 and up to 100; fixed offers deduct
the reviewed dollar amount from each eligible admission within the customer
limit and never reduce a ticket below zero. The managed coupon, cart and checkout
validation, public offer page, printable QR, and export all report the same value.

Version 1.2.7 places the delivery route before route-specific campaign fields and
preserves compatible draft values when an operator switches into reusable-business
setup. Business review now reports its own prerequisite state and carries separate
per-customer, per-business, and shared overall admission limits into QR setup.
Each saved business distribution also provides a signed, public, read-only
reception-desk flyer with accurate offer terms, scope, expiry, scarcity wording,
the existing venue logo, and a QR for that business's customer offer link.

Version 1.2.8 keeps the business selection, review, saved-link, and sharing work
inside Step 5. Pending selections, per-business limits, paid-order caps, and
site-timezone expiry survive review and validation, while an exact review token
and Source/batch/membership/configuration digests prevent stale saves. Admission
limits are grouped with explicit shared-capacity guidance.

After links are saved, every linked business receives a personalized, copyable
business-contact message with its own customer-offer and flyer URLs. Reviewed
email handoff is limited to selected, valid, unsuppressed addresses and records
audited mail-system acceptance (not confirmed delivery) so accepted handoffs
cannot be repeated accidentally. Businesses
without email remain fully supported through copyable messages. Reusable-business
campaign management no longer presents individual-recipient imports as required,
and its dedicated business-sharing template does not overwrite historical
individual-recipient templates.

Version 1.2.9 adds a configurable public flyer presentation with venue defaults,
campaign heading/subheading and artwork overrides, and Media Library preview,
replacement, removal, and fallback controls. The venue logo, exact reviewed offer,
business identity, scope, expiry, limits, and signed customer QR remain separate,
readable content in responsive and one-page Letter output.

Paid customer pages now lead with the venue and exact benefit, present the referring
business as secondary context, sort the existing eligible event set chronologically,
and use event imagery or a clean venue fallback. Important expiry and availability
stay visible, secondary terms are expandable, unavailable links retain branded
404/410 states, and existing eligibility, checkout, attribution, and capacity
enforcement remain unchanged.

Version 1.2.11 preserves multiline plain-text business-contact introductions from
the invitation editor through saved templates, personalized previews, clipboard
copying, and UTF-8 email handoff. Subjects remain single-line, and the editor
clarifies that Markdown markers are sent literally rather than rendered.

Version 1.2.12 adds a compact, campaign-specific Business Contacts & Activity
dashboard for reusable-business campaigns. Historical mail-system handoffs remain
a read-only projection of BVM admission audit records, while manual outreach is
stored in an additive Outreach-owned activity table. First-time email selection,
explicit resend review, campaign progress, and Results navigation are now
business-route aware; no individual invitation links are created by this workflow.

Version 1.2.13 keeps first-time business-email eligibility and selection directly
available in that dashboard without requiring a template save or a surviving review
transient. Visible-only bulk selection, exact recipient/content review snapshots,
an independent Needs First Email filter, structured deliberate resends, and local
handoff results make recipient intent explicit while preserving audit and replay
protections. Established business-link settings and invitation-template editing are
secondary disclosures; they remain independent from email recipient selection.

Version 1.2.14 separates an existing reusable-business offer batch from a new-batch
draft. Opening the new setup clears only the browser selection, restores editable
draft limits, preserves failed-review values, and leaves the existing batch
untouched until a new definition is explicitly confirmed. Active membership counts
also explain when a Source has no reusable businesses to review. Individual-recipient
campaigns remain complimentary Guest Pass invitations; paid recipient checkout is
not exposed without a distinct supported redemption and attribution architecture.

Version 1.2.14.1 requires at least one currently active business membership before
Business Review can be opened or a reusable-business campaign can be created. The
gate is enforced in the initial page, live browser state, server preview, and final
creation checks. A zero-member Source may still review and explicitly create a batch
definition without generating links, recipients, or a campaign.

Version 1.2.10 gives public business flyers fixed US Letter compositions that stay
consistent between preview, print, and direct PDF download. Portrait full flyers
stack proportional artwork above the offer. Landscape full flyers use a compact,
opaque offer band with the venue logo, readable offer details, and customer QR in
separate horizontal regions. Landscape Letter also provides a transient ink-saving
offer-only output; portrait offer-only output is not part of this release.

Automatic artwork now resolves a campaign override first, then the linked One Event
artwork, then the venue default, and finally a branded no-artwork fallback. Operators
can explicitly choose no artwork, and saved legacy layout combinations normalize to
compatible Letter output without changing campaign terms, business links, or signed
customer URLs.

## Development checks

From the Backstage Venue Manager repository root:

```sh
php tests/backstage-outreach-current-bvm-integration.php
php tests/business-partner-distribution.php
php tests/business-discount-qr-links.php
php tests/business-import-preview.php
php tests/business-source-campaign.php
BVM_BUSINESS_IMPORT_HTML=/tmp/business-import-review.html php tests/business-import-preview.php
BVM_BUSINESS_IMPORT_HTML=/tmp/business-import-review.html BVM_BUSINESS_IMPORT_SCREENSHOTS=/tmp/business-import-review-shots node tests/business-import-preview-screenshots.js
wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-source-campaign-runtime.php
wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-discount-qr-runtime.php
wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-discount-qr-concurrency.php
find companion-plugins/backstage-outreach -name '*.php' -print0 | xargs -0 -n1 php -l
```

The companion directory is listed in `release-public-excludes.txt`; it is not
part of the public Backstage Venue Manager release artifact.
