[NON-SHIPPING DRAFT FOR OPERATOR REVIEW ONLY]
[DO NOT COPY THIS TO ROOT readme.txt UNTIL OPERATOR DECISIONS ARE RESOLVED]

=== [OPERATOR DECISION REQUIRED: Final Public Plugin Name] ===
Contributors: [OPERATOR DECISION REQUIRED: WordPress.org usernames]
Tags: [OPERATOR DECISION REQUIRED: choose up to 5 accurate tags]
Requires at least: [OPERATOR DECISION REQUIRED: minimum WordPress version]
Tested up to: [OPERATOR DECISION REQUIRED: tested WordPress version]
Requires PHP: [OPERATOR DECISION REQUIRED: minimum PHP version]
Stable tag: [OPERATOR DECISION REQUIRED: first public version]
License: [OPERATOR DECISION REQUIRED: GPL license wording]
License URI: [OPERATOR DECISION REQUIRED: GPL license URL]
Requires Plugins: [OPERATOR DECISION REQUIRED: leave blank if dependencies remain optional]

Coordinate event plans, vendor records, and venue operations from WordPress. Ticketing and some workflows depend on optional plugins or external services.

== Description ==

VMS is a venue and event-operations plugin for WordPress. Current source shows operator-facing workflows for event plans, vendor records, vendor-facing flows, admissions-related utilities, and other venue-management tools inside a single plugin.

Some features are self-contained. Others depend on optional plugin stacks such as WooCommerce, The Events Calendar, or Event Tickets. The first public release should describe those feature boundaries explicitly and should not imply that every workflow is available on every install.

Current source-backed areas include:

* Event-plan and venue-management workflows inside wp-admin.
* Vendor record management plus vendor-facing portal/application surfaces.
* Optional ticketing, commerce, admissions, and refund-adjacent workflows when the required supporting plugins are present.
* Optional add-on management and companion-plugin licensing surfaces that require separate operator review and policy copy before public release.

Limitations for the first public release should be stated plainly:

* Some advanced workflows depend on optional third-party plugins.
* Some optional workflows connect to third-party services.
* Multisite support is currently [OPERATOR DECISION REQUIRED: supported / unsupported / verification required].

== Installation ==

1. Upload the plugin folder or ZIP to your WordPress site and activate the plugin.
2. Open the `VMS` admin menu and review the available settings and modules.
3. If you plan to use ticketing or commerce-linked workflows, install the optional dependency stack described below:
   [OPERATOR DECISION REQUIRED: confirm whether WooCommerce, The Events Calendar, Event Tickets, and Event Tickets Plus remain optional or any are treated as required.]
4. If you plan to use the vendor application form, configure Cloudflare Turnstile keys before relying on that workflow. Current source fails closed when that anti-spam service is enabled but not configured.
5. If you plan to use optional integrations such as vendor ICS sync, webhook delivery, add-on licensing, or other external-service-backed features, configure those services before relying on them in production.
6. Review the final privacy, uninstall, support, and dependency notes before opening the plugin to operators.

== Frequently Asked Questions ==

= Does VMS require WooCommerce? =

No for baseline plugin boot. Current source shows that some ticketing, admissions, refund-adjacent, and commerce-linked workflows require WooCommerce.

= Does VMS require The Events Calendar or Event Tickets? =

No for baseline plugin boot. Current source shows that event-linked ticket creation/sync and some event/ticket workflows depend on those plugins.

= What happens if optional dependencies are missing? =

Current source includes multiple guarded code paths and operator-facing notices for missing dependencies. Features that depend on those plugins should become unavailable instead of being advertised as universally available.

= Does VMS send data to third-party services? =

Yes, but only for features that use them or when an operator configures them. See the `External Services` section below.

= Does uninstall remove data? =

Current source does not automatically delete plugin data on uninstall.

[OPERATOR DECISION REQUIRED: replace this with the final public uninstall/data-retention policy.]

= Does VMS support multisite? =

[OPERATOR DECISION REQUIRED: final public support position.]

= Where do I get support? =

[OPERATOR DECISION REQUIRED: public support channel.]

= Where do I report security issues privately? =

[OPERATOR DECISION REQUIRED: dedicated private security-reporting channel.]

== Screenshots ==

1. [OPERATOR DECISION REQUIRED: screenshot description]
2. [OPERATOR DECISION REQUIRED: screenshot description]
3. [OPERATOR DECISION REQUIRED: screenshot description]
4. [OPERATOR DECISION REQUIRED: screenshot description]

== External Services ==

This plugin can connect to third-party services when the corresponding feature is enabled or configured.

1. Cloudflare Turnstile
   Used by: vendor application anti-spam verification.
   Data sent: the submitted Turnstile token and the visitor IP address during form verification.
   Service terms/privacy: [OPERATOR DECISION REQUIRED: insert current official links.]

2. QR image generation service (`api.qrserver.com`)
   Used by: admissions/pass-claims QR image generation paths.
   Data sent: the QR payload encoded into the generated QR-image request URL.
   Service terms/privacy: [OPERATOR DECISION REQUIRED: insert current official links.]

3. Freemius
   Used by: optional add-on licensing activation, validation, deactivation, and add-on health checks.
   Data sent: site URL, site title, plugin/add-on version, a derived installation UID, license key data, and related license-operation payloads when those actions are used.
   Service terms/privacy: [OPERATOR DECISION REQUIRED: insert current official links.]

4. Vendor-provided ICS calendar URLs
   Used by: vendor availability ICS sync when an operator or vendor has configured a calendar URL.
   Data sent: the plugin fetches the configured ICS URL directly from the host that serves that calendar.
   Service terms/privacy: [OPERATOR DECISION REQUIRED: document the policy as “depends on the configured calendar host” or provide approved copy.]

5. Operator-configured webhook endpoints
   Used by: webhook-based social sharing/publishing workflows.
   Data sent: event identifiers, venue summary fields, rendered caption text, destination URL, featured image URL, and queue metadata needed for the configured webhook flow.
   Service terms/privacy: because the endpoint is operator-configured, the applicable terms and privacy policy depend on the service chosen by the operator.

[OPERATOR DECISION REQUIRED: confirm whether any additional core-plugin services must be disclosed for the first public release.]

== Data Retention and Uninstall ==

Current source ships with an `uninstall.php` file that exits safely but does not remove stored plugin data automatically.

[OPERATOR DECISION REQUIRED: replace this section with the final public policy for uninstall, retained data, optional cleanup behavior, and any personal-data handling notes.]

== Support and Limitations ==

Support:

* [OPERATOR DECISION REQUIRED: public support channel]
* [OPERATOR DECISION REQUIRED: public documentation URL, if any]
* [OPERATOR DECISION REQUIRED: private security-reporting channel]

Known limitations and boundaries for the first public release:

* Optional ticketing and commerce workflows depend on additional plugins.
* Optional third-party services require separate configuration and disclosure.
* Add-on installation/licensing behavior must stay aligned with the final approved WordPress.org policy copy.
* Multisite support is [OPERATOR DECISION REQUIRED: supported / unsupported / verification required].

== Changelog ==

= [OPERATOR DECISION REQUIRED: first public version] =

* [OPERATOR DECISION REQUIRED: write the concise public-facing changelog summary for the chosen release candidate.]
