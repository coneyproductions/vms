=== Backstage Venue Manager ===
Contributors: coneyproductions
Tags: event management, venue management, vendor management, ticketing, woocommerce
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage venue operations, event plans, vendor records, and optional ticketing workflows from WordPress.

== Description ==

Backstage Venue Manager helps venue operators manage event plans, vendor records, and related venue workflows from WordPress.

The core plugin loads without WooCommerce, The Events Calendar, or Event Tickets. Features that depend on one of those optional integrations become available when the required plugin is installed and active. When an optional dependency is missing, Backstage Venue Manager is intended to keep loading while the dependent feature stays unavailable.

Backstage Venue Manager 1.2.0 was runtime-tested on WordPress 6.8 and 7.0. Packaging, repo-root release tests, and direct WordPress boot smoke were also revalidated under PHP 8.3 during this release-candidate pass.

== Installation ==

1. Upload the plugin folder or ZIP to your WordPress site.
2. Activate `Backstage Venue Manager`.
3. Open the `VMS` admin menu and review the available modules for your site.
4. Install WooCommerce only if you need commerce, admissions, or ticketing-related workflows that depend on it.
5. Install The Events Calendar and Event Tickets only if you need event-linked ticketing workflows that depend on them.
6. Configure Cloudflare Turnstile only if you plan to use the vendor application form with Turnstile protection enabled.
7. Configure any optional external services only for the features you intend to use.

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

Multisite is not officially supported or verified for 1.2.0.

= How are privacy export and erasure requests handled? =

Backstage Venue Manager 1.2.0 does not add dedicated exporter or eraser automation. Operators should handle requests manually with their existing WordPress tools and site-specific operational procedures until that automation is added.

= Where do I get support? =

Use the plugin's WordPress.org support forum after the listing is approved. Product documentation and release context are also published at https://coneyproductions.booklivetalent.com/vms/ .

= Where do I report security issues privately? =

Email coneyproductionsllc@gmail.com.

== Screenshots ==

1. Backstage Venue Manager dashboard and top-level navigation.
2. Event plan editing and venue operations workflow.
3. Vendor records and vendor application workflow.
4. Admissions and ticketing-related operator tools.

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

Depending on the modules in use, retained data can include settings, venue and vendor records, event-planning records, ticketing-related operational data, and related logs or status metadata. Backstage Venue Manager 1.2.0 does not add automated uninstall cleanup tooling or dedicated privacy exporter or eraser automation. Operators should review their operational data-handling process before uninstalling the plugin or responding to privacy requests.

== Optional Integrations / Dependencies ==

WooCommerce, The Events Calendar, and Event Tickets are optional integrations for 1.2.0. Backstage Venue Manager should continue loading without them, but dependent features will remain unavailable until the required plugin stack is installed.

Optional add-ons are distributed as separate plugins. The WordPress.org core plugin can detect compatible companion plugins when they are installed, but it does not install, license, or unlock them from inside the core plugin.

== Support and Security Reporting ==

Public support: use the plugin's WordPress.org support forum after approval.

Product documentation: https://coneyproductions.booklivetalent.com/vms/

Private security reports: coneyproductionsllc@gmail.com

== Changelog ==

= 1.2.0 =

* Established the Backstage Venue Manager public core line under the `backstage-venue-manager` package identity and WordPress.org slug.
* Hardened WordPress.org review-sensitive security and request boundaries while preserving established venue, vendor, Event Plan, and ticketing workflows.
* Normalized output, JSON, filesystem, upload, and download handling across public-package review surfaces.
* Externalized executable admin helpers where appropriate and aligned AJAX nonce and response-lifecycle behavior.
* Hardened public-package construction, release metadata, and exclusion handling for WordPress.org review.
* Aligned Vendor Application Turnstile configuration, client loading, and disclosure behavior for the public core package.
* Preserved compatibility-focused behavior for optional WooCommerce, The Events Calendar, and Event Tickets integrations.

= 1.0.0 =

* First public WordPress.org release for Backstage Venue Manager.
* Applied the selected public plugin name, author metadata, licensing metadata, and public-facing readme.
* Documented optional dependency boundaries, external-service disclosures, privacy notes, and uninstall data-retention behavior.

== Upgrade Notice ==

= 1.2.0 =

Backstage Venue Manager 1.2.0 establishes the public core release line. Do not activate it alongside another installed copy under a different plugin directory. Back up your files and database before replacement or migration. Some optional features may require separate extensions.

= 1.0.0 =

Initial public WordPress.org release. Review the dependency, privacy, and data-retention notes before using the plugin in production.
