=== Backstage Venue Manager ===
Contributors: coneyproductions
Tags: event management, venue management, vendor management, ticketing, woocommerce
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage venue operations, event plans, vendor records, and optional ticketing workflows from WordPress.

== Description ==

Backstage Venue Manager helps venue operators manage event plans, vendor records, and related venue workflows from WordPress.

The core plugin loads without WooCommerce, The Events Calendar, or Event Tickets. Features that depend on one of those optional integrations become available when the required plugin is installed and active. When an optional dependency is missing, Backstage Venue Manager is intended to keep loading while the dependent feature stays unavailable.

Use the Event Command Center to review event readiness, staffing commitments, ticket availability, and financial summaries. Optional integrations supply additional information when installed and configured.

== Installation ==

1. Upload the plugin folder or ZIP to your WordPress site.
2. Activate `Backstage Venue Manager`.
3. Open the `Backstage Venue Manager` admin menu and review the available modules for your site.
4. Install WooCommerce only if you need commerce, admissions, or ticketing-related workflows that depend on it.
5. Install The Events Calendar and Event Tickets only if you need event-linked ticketing workflows that depend on them.
6. Configure Cloudflare Turnstile only if you plan to use the vendor application form with Turnstile protection enabled.
7. Configure any optional external services only for the features you intend to use.

== Private Document Storage ==

W-9s, staff certificates, verification proofs, technical documents, and import files require private filesystem storage. Private uploads are disabled until your host configures it. BVM activation and features that do not use private documents remain available.

Ask your hosting administrator to create a persistent, PHP-readable/writable directory outside every website document root, alias, symlink mapping, CDN origin, and public backup/export location. Do not assume the parent of WordPress is private. The directory must be dedicated to this WordPress installation/network and must not be exposed by any web server. Back it up privately together with the database.

In wp-config.php, define BVMGR_PRIVATE_STORAGE_ROOT as that directory's canonical absolute path, and BVMGR_PRIVATE_STORAGE_WEB_ROOTS as an array of the canonical filesystem roots published by the host, including document roots and aliases. For example, with a host-verified public root /srv/example/public and a separate private directory /srv/example-documents:

`define('BVMGR_PRIVATE_STORAGE_ROOT', '/srv/example-documents');`
`define('BVMGR_PRIVATE_STORAGE_WEB_ROOTS', array('/srv/example/public'));`

These example paths are not defaults. The host must supply the complete mapping: PHP cannot enumerate arbitrary server/proxy aliases. BVM checks real paths, public-root overlap, WordPress/content/uploads locations, and consistency with the server's DOCUMENT_ROOT. Unknown configuration, symlink/junction redirects, unsafe roots, or unavailable permissions fail closed; there is no public-uploads fallback. HTTP requests must supply a server DOCUMENT_ROOT matching a declared root. CLI migrations use the same explicit host declaration. Multisite uses a separate site-ID directory and requires a network super administrator for migration; repeat per site.

After configuring storage, open Tools → BVM Private Documents. Existing uploads-based documents require an explicit administrator migration. This includes the historical BVM/VMS buckets and any short-lived 1.3 test copy under `uploads/backstage-venue-manager/private/site-N`; none is a valid final destination. Securely back up their files and database first, then run the migration batches until none remain. BVM preserves document IDs, verifies copied contents, records recovery state, and removes only the owned legacy plaintext after verification. Interrupted or failed migrations remain visible and can be retried; ordinary reads and page rendering never migrate files. Existing copies may still be publicly accessible until migration completes. Do not change the configured root without also restoring its private objects; BVM will not fetch missing documents from public uploads.

Legacy private WordPress attachment IDs remain associated with their records; migration also protects registered image derivatives. Unrelated uploads and separately managed companion files are not migrated. Authorized downloads stream through existing BVM routes; the secure filesystem location is not published as a download URL. An HTTP 403 probe or server deny file is not the confidentiality boundary and is not required: the host must make the outside directory structurally unmappable by every public server configuration.

To replace a legacy VMS installation, deactivate it in WordPress Plugins or Network Admin, then activate Backstage Venue Manager. BVM never changes another plugin's activation state and retains venue data.

== Frequently Asked Questions ==

= Does Backstage Venue Manager require WooCommerce? =

No for baseline plugin loading. Commerce, admissions, and some ticketing workflows depend on WooCommerce.

= Does Backstage Venue Manager require The Events Calendar or Event Tickets? =

No for baseline plugin loading. Event-linked ticketing workflows depend on those plugins when that feature set is in use.

= What happens if optional dependencies are missing? =

Backstage Venue Manager is intended to keep loading. Features that depend on the missing plugin should fail closed instead of being treated as globally available.

= Does Backstage Venue Manager send passive telemetry? =

No. Backstage Venue Manager does not include passive telemetry in the WordPress.org core plugin. External calls happen only when an operator enables or uses a feature that needs them.

= What happens on uninstall? =

Backstage Venue Manager retains operational data by default. The plugin ships with a safe uninstall routine that does not automatically remove stored data.

= Does Backstage Venue Manager support multisite? =

Multisite is not officially supported or verified for 1.3.0.

= How are privacy export and erasure requests handled? =

Backstage Venue Manager 1.3.0 does not add dedicated exporter or eraser automation. Operators should handle requests manually with their existing WordPress tools and site-specific operational procedures until that automation is added.

= Where do I get support? =

Use the plugin's WordPress.org support forum after the listing is approved. Product documentation and release context are also published at https://coneyproductions.booklivetalent.com/vms/ .

= Where do I report security issues privately? =

Email coneyproductionsllc@gmail.com.

== External Services ==

Backstage Venue Manager can connect to third-party services only when the corresponding feature is enabled or configured.

1. Cloudflare Turnstile
Used by: optional protection for the public Vendor Application form against automated abuse.
Activation: Backstage Venue Manager uses Turnstile only after an administrator supplies both a site key and a secret key. If either key is missing, the active Vendor Application form is unavailable and the Cloudflare client is not loaded.
Browser contact: when the fully configured active Vendor Application form is displayed, the visitor's browser loads Cloudflare's Turnstile client from challenges.cloudflare.com before the application is submitted. That contact may disclose ordinary request information such as the visitor IP address, browser or device information, request headers, page URL or referrer, and the configured public site key.
Server-side verification: Backstage Venue Manager sends the Turnstile response token and the visitor IP address to Cloudflare and authenticates the verification request with the configured secret key. The Vendor Application form contents are not sent to Cloudflare through this integration.
Service docs and privacy: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/ and https://www.cloudflare.com/turnstile-privacy-policy/

2. QRServer / goQR.me
Used by: admissions and pass-claim QR image generation, when those workflows are used.
Data sent: the QR payload encoded into the generated QR-image request URL.
Service docs and privacy: https://goqr.me/api/ and https://goqr.me/privacy-safety-security/

3. Vendor-provided ICS calendar URLs
Used by: vendor availability ICS sync, when an operator or vendor configures an ICS URL.
Data sent: Backstage Venue Manager fetches the configured ICS URL directly from the remote calendar host.
Service terms and privacy: depend on the configured calendar host.

4. Operator-configured webhook endpoints
Used by: webhook-based social sharing and publishing workflows, when configured.
Data sent: event identifiers, venue summary fields, rendered caption text, destination URL, featured image URL, queue metadata, and an HMAC signature when a signing secret is configured.
Service terms and privacy: depend on the configured webhook destination selected by the operator.

5. Vendor-selected video and oEmbed providers
Used by: optional vendor profile and promotional video embeds, when a vendor or operator saves a supported external video URL.
Server-side contact: WordPress may request the selected URL and its provider endpoints to discover and render the embed.
Browser contact: when an embedded video is displayed, the visitor's browser may connect directly to the selected provider.
Data sent: the selected video URL and ordinary server request metadata during discovery; browser contact may disclose the visitor IP address, browser or device information, request headers, page URL or referrer, and provider cookies according to that provider's policies.
Service docs, terms, and privacy: supported providers are described at https://wordpress.org/documentation/article/embeds/; terms and privacy depend on the provider selected by the vendor or operator.

== Privacy / Data Retention ==

Backstage Venue Manager retains operational data by default on uninstall to reduce the risk of accidental data loss.

Depending on the modules in use, retained data can include settings, venue and vendor records, event-planning records, ticketing-related operational data, and related logs or status metadata. Backstage Venue Manager 1.3.0 does not add automated uninstall cleanup tooling or dedicated privacy exporter or eraser automation. Operators should review their operational data-handling process before uninstalling the plugin or responding to privacy requests.

== Optional Integrations / Dependencies ==

WooCommerce, The Events Calendar, and Event Tickets are optional integrations for 1.3.0. Backstage Venue Manager should continue loading without them, but dependent features will remain unavailable until the required plugin stack is installed.

Optional add-ons are distributed as separate plugins. The WordPress.org core plugin can detect compatible companion plugins when they are installed, but it does not install, license, or unlock them from inside the core plugin.

== Support and Security Reporting ==

Public support: use the plugin's WordPress.org support forum after approval.

Product documentation: https://coneyproductions.booklivetalent.com/vms/

Private security reports: coneyproductionsllc@gmail.com

== Changelog ==

= 1.3.0 =

* Consolidated the certified canonical source into one unified Backstage Venue Manager release.
* Included Core operational and Event Command Center contracts, ticket-purchase extensions, and lifecycle stability corrections.
* Added Guest List discoverability across Event Plan, Event Command Center, admissions, and event-day reporting surfaces.
* Included private-storage, local QR, portal-hook, webhook-security, activation, and path-safety protections.
* Included Event Plan rollback and resynchronization corrections plus follow-up and vendor-outcome workflows.

= 1.2.0 =

* Added the Event Command Center and staffing assignment responses with read-only staffing summaries.
* Separated transactional receipts, reported values, forecasts, and unavailable accounting totals.
* Included current staffing lifecycle tables on new installations; existing staffing data uses the guarded migration workflow.
* Established the Backstage Venue Manager public core line under the `backstage-venue-manager` package identity and WordPress.org slug.
* Hardened WordPress.org review-sensitive security and request boundaries while preserving established venue, vendor, Event Plan, and ticketing workflows.
* Normalized output, JSON, filesystem, upload, and download handling across public-package review surfaces.
* Externalized executable admin helpers where appropriate and aligned AJAX nonce and response-lifecycle behavior.
* Hardened public-package construction, release metadata, and exclusion handling for WordPress.org review.
* Aligned Vendor Application Turnstile configuration, client loading, and disclosure behavior for the public core package.
* Preserved compatibility-focused behavior for optional WooCommerce, The Events Calendar, and Event Tickets integrations.

= 1.0.0 =

* Initial prerelease candidate for Backstage Venue Manager.
* Applied the selected public plugin name, author metadata, licensing metadata, and public-facing readme.
* Documented optional dependency boundaries, external-service disclosures, privacy notes, and uninstall data-retention behavior.

== Upgrade Notice ==

= 1.3.0 =

Backstage Venue Manager 1.3.0 is the unified canonical-source release. Back up files and the database before upgrading, and do not activate it alongside another installed copy under a different plugin directory.

= 1.2.0 =

Backstage Venue Manager 1.2.0 establishes the public core release line. Do not activate it alongside another installed copy under a different plugin directory. Back up your files and database before replacement or migration. Some optional features may require separate extensions.

= 1.0.0 =

Initial public WordPress.org release. Review the dependency, privacy, and data-retention notes before using the plugin in production.
