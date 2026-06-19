<?php

/**
 * VMS Registry Constants
 *
 * Central place for shared constants so we avoid magic strings across core/modules.
 */
 
defined('ABSPATH') || exit;
 
if (!defined('VMS_PLUGIN_SLUG')) {
	define('VMS_PLUGIN_SLUG', 'vms');
}
 
// Plugin version (SemVer). Use this for WP asset cache-busting.
if (!defined('VMS_VERSION')) {
		// v0.2.24.724: adds a Wallet Plus / TCPDF PDF-only mitigation that swaps header, hero, and reusable QR assets to local readable paths and prefers resized hero derivatives where available.
		// v0.2.24.723: reduces Ticket Integrity full-scan memory pressure by compacting cron snapshots, avoiding duplicate in-memory result arrays, clearing sold-context caches between events, and skipping cleanly when memory headroom is too low.
		// v0.2.24.722: adds an opt-in flat-file slow-request logger for scoped checkout, wallet/PDF, and async loopback diagnostics with threshold-only writes, redacted URLs, hashed IPs, and log rotation.
		// v0.2.24.721: keeps scheduled State of the Range delivery on cached ticket-integrity snapshots, marks stale-snapshot sends explicitly, skips fast when no usable snapshot exists, and leaves clearer fatal breadcrumbs.
		// v0.2.24.720: enriches TEC Event JSON-LD instead of duplicating it by default, consolidates Google schema, removes qualified/free ticket offers from public schema, fixes organizer type, and keeps compact Event Details sidebar.
		// v0.2.24.714: filters Event Plan staffing candidates by explicit role eligibility plus hard-block qualification status, keeps assigned-but-now-ineligible staff visible with warnings, and stops auto-dropping those preserved assignments on save.
		// v0.2.24.713: keeps generated Event Plan collapsible wrappers from swallowing existing lazy sections, adds explicit section boundaries around compensation/readiness, and forces lazy readiness details to start collapsed until first load while preserving .712 blank-save protections.
		// v0.2.24.712: preserves Event Plan vendor, lineup, and deferred-section state on blank/unloaded saves unless an explicit clear intent is posted, and makes lazy-section load failures visible.
		// v0.2.24.711: treats unregistered premium modules as disabled so add-ons fail closed until the VMS module registry can verify their gate state.
		// v0.2.24.710: adds vendor-application email confirmation gating, secure confirmation tokens, WP-user resolution on confirm, canonical review-ready queue gating, applicant portal states, resend throttles, and approval-time user-link enforcement without changing operator status values.
		// v0.2.24.709: redesigns the logged-out Vendor Portal entry screen, upgrades vendor application thank-you messaging, adds vendor-aware My Account guidance plus portal-origin login redirects, and expands approved application email guidance without changing approval/linking data flow.
		// v0.2.24.708: trims Event Plan admin first-paint cost by memoizing ADD badge counts, making readiness/social sections lazy on collapsed load, and booting lineup vendor selectors from selected-only rows before full option hydration.
		// v0.2.24.707: keeps Event Plan featured-image-only save classification and narrow side-effect gating active even when VMS_EP_PERF_TRACE is disabled, and avoids lineup rewrite churn when lineup inputs were not posted.
		// v0.2.24.706: classifies published Event Plan featured-image edits as image-only saves, skips unrelated publish/deferred maintenance, and records one-shot linked TEC thumbnail sync diagnostics.
		// v0.2.24.705: hardens State of the Range scheduling/diagnostics with self-healing daily cron timing, delivery-state instrumentation, dry-run/admin preview tooling, and WP-CLI status helpers.
		// v0.2.24.703: normalizes verified-ticket credential images to readable JPG proofs, adds an admin-set original upload limit, and improves PDF/HEIC upload guidance without depending on VMS Ops.
		// v0.2.24.700: repairs later Event Plan featured-image changes so linked TEC events and slider consumers pick up the new banner instead of staying on the earlier vendor fallback.
		// v0.2.24.698: adds a compact public-calendar mode with three-month weekend-focused chunks, an operator-selectable default view, and per-month weekday-column suppression for unused dates.
		// v0.2.24.696: fixes the verification upload AJAX payload build order so disabled form fields no longer strip action/nonce/program data before submission.
		// v0.2.24.695: fixes verification upload AJAX form-target shadowing, prevents image-processing WP_Error fatals, and keeps classic checkout policy acknowledgement posted through Woo AJAX checkout.
		// v0.2.24.694: makes State of the Range ticket metrics consistent across active mapped ticket rows, switches gross to completed net ticket revenue, renames availability/capacity labels for accuracy, and decodes plain-text email entities before send.
	// v0.2.24.693: hardens Ticket Integrity and State of the Range scans by narrowing target discovery, replacing full Woo order hydration with aggregate sold-quantity queries, and logging/reporting refresh failures explicitly.
	// v0.2.24.688: adds emergency passive-admin containment for heavy backfills, TEC admin async suppression scope, structured admin trace logging, and duplicate-work locks for admin-triggered maintenance.
	// v0.2.24.687: fixes Chrome mobile checkbox add-ons by giving checkbox selectors the same touch-toggle/click-dedupe hardening as ticket steppers.
	// v0.2.24.686: counts prior customer event-ticket purchases toward add-on qualification and subtracts prior add-on pool usage so addon limits stay consistent across separate orders.
	// v0.2.24.685: fixes progressive qualified-ticket row re-sync after late native qty changes and adds touch-safe qualified-ticket "more info" disclosure toggles on mobile.
	// v0.2.24.684: speeds mobile Chrome ticket steppers, syncs progressive public/mobile control sizes across both ticketing stylesheets, and broadens progressive-section touch activation to the full header surface.
	// v0.2.24.683: hardens mobile/Chrome ticket quantity and add-on interactions, adds touch fallbacks for native steppers and progressive toggles, and avoids cart-context stalls before atomic add-to-cart.
	// v0.2.24.682: adds Payment Gateway Health to Ticket Integrity / State of the Range, including Square connection checks, incident memory, admin alerts, and scheduled monitoring.
	// v0.2.24.681: adds relative ticket sale-date controls and clamps ticket sale end dates to the Event Plan/TEC event end.
	// v0.2.24.679: reinforces sale-price emphasis for hydrated public ticket rows that render sale/regular prices as sibling wrapper spans.
	// v0.2.24.678: polishes public ticket sale display, adds visible sale-end copy, and surfaces limited-ticket ratio guidance before customers over-select youth/child-style tickets.
	// v0.2.24.677: adds a vendor application Holding / Keep on File status plus operator response notes/emails so applicants can be kept warm without approval/rejection.
	// v0.2.24.676: adds shared allowance groups to ticket ratio rules so multiple youth/child ticket rows can draw from one adult/GA allowance pool.
	// v0.2.24.673: preserves the scoped async-suppression markers but keeps the page/slug context visible in stored fingerprints and adds an explicit marker entry for blocked requests.
	// v0.2.24.672: adds explicit heavy-page Action Scheduler async suppression scope markers, includes Event Command Center in the scoped page list, and keeps suppression limited to DT/ECC/Event Plan editor surfaces.
	// v0.2.24.671: completes the WP-CLI activation-hook compatibility follow-up by removing an invalid no-op cast from the nullable network-wide lifecycle handlers.
	// v0.2.24.670: hardens resource-fingerprint plugin lifecycle hooks so WP-CLI activation/deactivation can pass a null network-wide flag without fatalling on staging.
	// v0.2.24.669: adds threshold-based resource fingerprints, DT/ECC timing markers, request-level DT memoization, and Action Scheduler async suppression on heavy admin report pages.
	// v0.2.24.668: fixes Event Command Center total admitted/ticketed note math when manual comp counts exceed the reporting model total.
	// v0.2.24.665: cleans Event Plan save profiler dirty-map noise by comparing final meta values against save-start baselines, reduces no-effect lineup index churn, preserves pay-ack timestamps on unchanged saves, and adds Add New Vendor shortcuts in primary/secondary vendor sections.
	// v0.2.24.663: adds publish/status-transition save profiling, pre-update post-field change capture, and no-op meta update attempt diagnostics so publish-path load can be traced separately from ordinary Event Plan Updates.
	// v0.2.24.662: repairs the Event Plan save-profiler staffing guard so content-only editor saves correctly record staffing heavy work as skipped instead of triggered.
	// v0.2.24.661: adds module-aware Event Plan save profiles and skips unchanged staffing matrix/rollup/template work on ordinary core updates.
	// v0.2.24.660: keeps normal WordPress Event Plan updates lightweight by skipping Ticket Integrity queues on content-only/general editor saves and filters no-op review warnings from module hub/change displays.
	// v0.2.24.658: trims Ticketing V2 save-config AJAX responses to avoid huge no-op payloads, adds AJAX request-age/handler timing diagnostics, fast-flushes preview success responses, and exposes the last Event Plan save profile in wp-admin for staging diagnostics.
	// v0.2.24.656: reduces Event Plan save pressure by skipping unchanged Ticketing v2 config writes/audit snapshots, resetting stale default-template sales_end values for fresh plans, deduping integrity queue logs, and adding a lightweight save profiler.
	// v0.2.24.654: adds per-ticket early/regular price windows so one public ticket can carry advance pricing without creating separate Early/Regular ticket products.
	// v0.2.24.653: fixes legacy single-GA public visibility so a disabled Early GA row cannot hide the enabled General Admission product while preserving 0.2.24.652 Event Plan save/publish load shielding.
	// v0.2.24.649: blocks disabled-but-not-yet-pushed ticket products at add-to-cart/cart/checkout and falls back to last-pushed sync metadata so qualified/free tickets cannot silently become public while config changes are pending.
	// v0.2.24.648: hardens Ticketing v2 legacy GA product mapping so Early GA cannot inherit the existing GA product, blocks duplicate ticket rows from controlling the same Woo product, preserves plain-text comparison characters in labels, and warns about skipped numbered add-on sequences before commit.
	// v0.2.24.646: replaces UTF-8 typographic separators on the Event Feedback admin screen with ASCII-safe labels so notification-settings redirects do not show mojibake characters.
	// v0.2.24.645: removes stale GA sale-window suppression from public add-on rendering so valid mapped add-ons remain visible on active events; add-to-cart qualification/stock rules still apply.
	// v0.2.24.641: prevents public/general ticket max-qty caps from blocking normal group purchases and relaxes cart quantity stabilization so Woo controls do not feel stuck.
	// v0.2.24.640: polishes progressive ticket copy/headings, configurable add-on section labels, decoded display labels, and restored ticket help text.
	// v0.2.24.637: merges staff certification admin visibility/email sender fixes into the qualified-row more-info ticket UI baseline.
	// v0.2.24.636: removes qualified-ticket jargon/checkout wording and adds collapsed first-time verification help inside each approved/free ticket row.
	// v0.2.24.635: fixes the Staff Portal Certifications tab by adding the missing render function and preserving upload/review notifications.
	// v0.2.24.634: adds staff portal certificate uploads with pending review, admin/staff email notifications, review audit metadata, and approve/reject email flow.
	// v0.2.24.633: hardens Woo checkout recovery after native validation/Turnstile errors so Place Order re-enables when corrected.
	// v0.2.24.622: restores row-level qualified-ticket descriptions in Progressive UI while keeping deeper qualification panels collapsed until selected.
	// v0.2.24.619: changes public list-view calendar cards to a true 16:9 media frame so posters/events read larger on mobile and tablet.
	// v0.2.24.618: forces public mobile/tablet calendar to card-list view and expands the cached-month fallback.
	// v0.2.24.615: adds rollback-safe progressive public ticket UI grouping Tickets, Qualified Discounts, and Amenities.
	// v0.2.24.614: adds Event Feedback new-submission email notifications plus protected admin deletion for feedback responses.
	// v0.2.24.613: hardens Event Feedback duplicate submissions with idempotency keys, request fingerprints, recipient/email guards, and duplicate-aware summaries.
	// v0.2.24.612: cleans debug-log noise by hardening early i18n paths, cron schedule labels, and admin menu title/hidden-page registration for PHP 8.3.
	// v0.2.24.611: hardens runtime boot against missing internal files, cleans stale VMS cron events on deactivation, and keeps admin-only/reporting/email-follow-up work off normal public requests.
	// v0.2.24.610: fixes duplicate empty custom Email Follow-Up templates and adds selected-recipient/batched manual sends.
	// v0.2.24.607: adds safe customer first-name/greeting tokens for Email Follow-Ups and migrates saved templates to use the smart greeting when missing.
	// v0.2.24.606: repairs Email Follow-Ups preview event selection so recent/past Event Plans remain available for post-event feedback emails.
	// v0.2.24.605: integrates Email Follow-Ups with Event Feedback survey links, feedback invite tokens, and post-event email preview routing.
	// v0.2.24.603: fixes verified-ticket self allowance resolution so profile/default/direct-grant quantities are not capped by public ticket max-qty settings and the buyer can use their own eligible quantity without extra guest prompts.
	// v0.2.24.602: fixes qualified-ticket claim validation so one approved assignee can use multiple tickets up to their effective event allowance instead of being blocked as a duplicate email.
	// v0.2.24.600: fixes registry-driven add-on page shell defaults so plain callbacks keep VMS nav, restores Staff Tasks global nav, and adds Email Follow-Ups to Marketing & Social category links.
	// v0.2.24.596: hardens the compact VMS admin menu so secondary pages are physically removed from the WordPress left rail instead of relying on VMS-screen-only CSS.
	// v0.2.24.588: fixes Guided Tours shell callback visibility, wraps Data Tools in the VMS admin shell, forces VMS parent highlighting for VMS pages, and tightens top-nav dropdown widths.
	// v0.2.24.587: catalogs legacy/direct admin pages in the registry and centralizes the compact left-rail section specs while keeping direct callbacks intact.
	// v0.2.24.586: tightens admin menu registry pass so only durable section pages remain in the left rail while all module/add-on pages stay discoverable in All VMS Pages.
	// v0.2.24.585: adds first-pass admin menu registry, page directory, and add-on-safe discovery.
	// v0.2.24.584: adds Square Sync Protection firewall for VMS/TEC ticket, admission, and event add-on Woo products while preserving Square-owned normal catalog items.
	// v0.2.24.583: adds the first Email Follow-Ups foundation with MailPoet detection, event-aware previews, test sends, logging, and off-by-default scheduled sends.
			// v0.2.24.746: routes cancellation notifications through modern staffing slot assignments first, preserves legacy staff fallback, and records typed/skipped notification recipients for the admin job panel.
			// v0.2.24.744: restricts My Tickets notice removal to exact notice fragments so wrapped siblings cannot be removed.
			// v0.2.24.743: matches singular/plural View Ticket(s) labels when removing fully refunded TEC My Tickets notices.
			// v0.2.24.741: adds capped Early Bird ticket pricing, sale/total availability display controls, and refund-aware active ticket notices.
			// v0.2.24.739: replaces direct ADD assignment with a review/confirm flow that defaults to current vendor types, preserves grouped assignments, and centers admin buttons/badges.
			// v0.2.24.738: makes ADD Vendor Review filters update recipient eligibility live and compacts Vendor Availability date detail rows/actions.
			// v0.2.24.737: adds Market Vendor target/needed-slots controls in the compact Additional Vendors UI and preserves ADD visibility metadata through secondary-vendor update paths.
			// v0.2.24.736: keeps grouped Additional Vendors compatibility meta aligned on ordinary Event Plan saves so flat/id-index readers still see Food, Dessert, and Market assignments after unrelated module saves.
			// v0.2.24.735: adds canonical multi-type secondary vendor assignments, grouped Additional Vendor Event Plan UI, type-specific calendar/ADD slot handling, Market Vendor support, and Music Vendor/Food Vendor wording updates.
			define('VMS_VERSION', '0.2.24.746');
	// ^ bump in sync with plugin header + vms-build.txt
	// IMPORTANT: keep in sync with plugin header Version.
	// PATCH: premium modules now fail closed until they register with VMS core.
}
 
if (!function_exists('vms_asset_version')) {
	function vms_asset_version(): string
	{
		return defined('VMS_VERSION') ? (string) VMS_VERSION : '';
	}
}

if (!defined('VMS_TEXTDOMAIN')) { // USED IN statuses.php
	define('VMS_TEXTDOMAIN', 'vms');
}

if (!defined('VMS_REST_NAMESPACE')) {
	define('VMS_REST_NAMESPACE', 'vms/v1');
}

/**
 * Post Types
 */
if (!defined('VMS_CPT_EVENT_PLAN')) {
	define('VMS_CPT_EVENT_PLAN', 'vms_event_plan');
}
if (!defined('VMS_CPT_VENDOR')) {
	define('VMS_CPT_VENDOR', 'vms_vendor');
}
if (!defined('VMS_VENDOR_PROFILE_BASE_SLUG')) {
	define('VMS_VENDOR_PROFILE_BASE_SLUG', 'vendor');
}
if (!defined('VMS_CPT_STAFF')) {
	define('VMS_CPT_STAFF', 'vms_staff');
}

/**
 * Capabilities
 */
// if (!defined(constant_name: 'VMS_CAP_READ')) {
// 	define('VMS_CAP_READ', 'vms_read');
// }
// if (!defined('VMS_CAP_MANAGE_EVENT_PLANS')) {
// 	define('VMS_CAP_MANAGE_EVENT_PLANS', 'vms_manage_event_plans');
// }
// if (!defined('VMS_CAP_MANAGE_VENDORS')) {
// 	define('VMS_CAP_MANAGE_VENDORS', 'vms_manage_vendors');
// }

// if (!defined('VMS_CAP_MANAGE_STAFF')) {
// 	define('VMS_CAP_MANAGE_STAFF', 'vms_manage_staff');
// }
// if (!defined('VMS_CAP_MANAGE_DATA_TOOLS')) {
// 	define('VMS_CAP_MANAGE_DATA_TOOLS', 'vms_manage_data_tools');
// }
// if (!defined(constant_name: 'VMS_CAP_VIEW_FINANCIALS')) {
// 	define('VMS_CAP_VIEW_FINANCIALS', 'vms_view_financials');
// }
// if (!defined('VMS_CAP_MANAGE_PAYOUTS')) {
// 	define('VMS_CAP_MANAGE_PAYOUTS', 'vms_manage_payouts');
// }


// Schedule user context (per-user user_meta)
if (!defined('VMS_SCH_CURRENT_VENUE_META_KEY')) {
	define('VMS_SCH_CURRENT_VENUE_META_KEY', '_vms_current_venue_id');
}
if (!defined('VMS_SCH_CURRENT_SCOPE_META_KEY')) {
	define('VMS_SCH_CURRENT_SCOPE_META_KEY', '_vms_schedule_scope');
}

/**
 * Option keys
 */
// if (!defined('VMS_OPT_SETTINGS_VERSION')) {
// 	define('VMS_OPT_SETTINGS_VERSION', 'vms_settings_version');
// }
// if (!defined('VMS_OPT_DEFAULT_TIMEZONE')) {
// 	define('VMS_OPT_DEFAULT_TIMEZONE', 'vms_default_timezone');
// }
// if (!defined('VMS_OPT_CSV_IMPORT_DEFAULTS')) {
// 	define('VMS_OPT_CSV_IMPORT_DEFAULTS', 'vms_csv_import_defaults');
// }
// if (!defined('VMS_OPT_NOTIFICATION_DEFAULTS')) {
// 	define('VMS_OPT_NOTIFICATION_DEFAULTS', 'vms_notification_defaults');
// }

if (!defined('VMS_OPT_VENDOR_ONBOARDING_EMAIL_TEMPLATE')) {
	define('VMS_OPT_VENDOR_ONBOARDING_EMAIL_TEMPLATE', 'vms_vendor_onboarding_email_template');
}
if (!defined('VMS_OPT_TICKET_MUTATION_AUDIT_DB_SCHEMA_VERSION')) {
	define('VMS_OPT_TICKET_MUTATION_AUDIT_DB_SCHEMA_VERSION', 'vms_ticket_mutation_audit_db_schema_version');
}
if (!defined('VMS_OPT_TICKET_INVENTORY_AUDIT_DB_SCHEMA_VERSION')) {
	define('VMS_OPT_TICKET_INVENTORY_AUDIT_DB_SCHEMA_VERSION', 'vms_ticket_inventory_audit_db_schema_version');
}

//
// Deprecated. Only for migrations / backwards compatibility checks //
//


/**
 * Meta keys (underscore = internal/private)
 */
if (!defined('VMS_META_EVENT_PLAN_VENUE_ID_LEGACY')) {
	define('VMS_META_EVENT_PLAN_VENUE_ID_LEGACY', '_vms_event_plan_venue_id'); // deprecated; do not use
}
if (!defined('VMS_META_EVENT_PLAN_DATE')) {
	define('VMS_META_EVENT_PLAN_DATE', '_vms_event_date'); // YYYY-mm-dd
}
if (!defined('VMS_META_EVENT_PLAN_VENUE_ID')) {
	define('VMS_META_EVENT_PLAN_VENUE_ID', '_vms_venue_id');
}
if (!defined('VMS_META_EVENT_PLAN_NOTES_INTERNAL')) {
	define('VMS_META_EVENT_PLAN_NOTES_INTERNAL', '_vms_event_plan_notes_internal');
}
if (!defined('VMS_META_EVENT_PLAN_NOTES_PUBLIC')) {
	define('VMS_META_EVENT_PLAN_NOTES_PUBLIC', '_vms_event_plan_public_notes');
}

if (!defined('VMS_META_VENDOR_DISPLAY_NAME')) {
	define('VMS_META_VENDOR_DISPLAY_NAME', '_vms_vendor_display_name');
}
if (!defined('VMS_META_VENDOR_CONTACT_NAME')) {
	define('VMS_META_VENDOR_CONTACT_NAME', '_vms_vendor_contact_name');
}
if (!defined('VMS_META_VENDOR_EMAIL')) {
	define('VMS_META_VENDOR_EMAIL', '_vms_vendor_email');
}
if (!defined('VMS_META_VENDOR_PHONE')) {
	define('VMS_META_VENDOR_PHONE', '_vms_vendor_phone');
}
if (!defined('VMS_META_VENDOR_STATUS')) {
	define('VMS_META_VENDOR_STATUS', '_vms_vendor_status');
}
if (!defined('VMS_META_VENDOR_AVAIL_DEFAULT')) {
	define('VMS_META_VENDOR_AVAIL_DEFAULT', '_vms_vendor_availability_default');
}

if (!defined('VMS_META_PAY_AMOUNT')) {
	define('VMS_META_PAY_AMOUNT', '_vms_pay_amount');
}
if (!defined('VMS_META_PAY_METHOD')) {
	define('VMS_META_PAY_METHOD', '_vms_pay_method');
}
if (!defined('VMS_META_PAY_STATUS')) {
	define('VMS_META_PAY_STATUS', '_vms_pay_status');
}
if (!defined('VMS_META_PAY_DATE')) {
	define('VMS_META_PAY_DATE', '_vms_pay_date');
}
if (!defined('VMS_META_PAY_NOTES')) {
	define('VMS_META_PAY_NOTES', '_vms_pay_notes');
}



/**
 * Vendor ↔ User linking (many-to-many)
 *
 * Back-compat pointers:
 * - User meta:  _vms_vendor_id (primary/default vendor for portal convenience)
 * - Vendor meta: _vms_vendor_user_id (primary contact user for vendor)
 *
 * Authoritative mapping storage:
 * - DB table suffix: vms_vendor_user_links (full table name uses $wpdb->prefix)
 */
if (!defined('VMS_USER_PRIMARY_VENDOR_META_KEY')) {
	define('VMS_USER_PRIMARY_VENDOR_META_KEY', '_vms_vendor_id');
}
if (!defined('VMS_VENDOR_PRIMARY_USER_META_KEY')) {
	define('VMS_VENDOR_PRIMARY_USER_META_KEY', '_vms_vendor_user_id');
}
if (!defined('VMS_DB_TABLE_VENDOR_USER_LINKS_SUFFIX')) {
	define('VMS_DB_TABLE_VENDOR_USER_LINKS_SUFFIX', 'vms_vendor_user_links');
}
if (!defined('VMS_DB_TABLE_VENDOR_APP_CONFIRM_TOKENS_SUFFIX')) {
	define('VMS_DB_TABLE_VENDOR_APP_CONFIRM_TOKENS_SUFFIX', 'vms_vendor_app_confirm_tokens');
}

/**
 * Staffing tables (Phase A structured role scheduling)
 */
if (!defined('VMS_DB_TABLE_STAFFING_TEMPLATES_SUFFIX')) {
	define('VMS_DB_TABLE_STAFFING_TEMPLATES_SUFFIX', 'vms_staffing_templates');
}
if (!defined('VMS_DB_TABLE_STAFFING_TEMPLATE_SLOTS_SUFFIX')) {
	define('VMS_DB_TABLE_STAFFING_TEMPLATE_SLOTS_SUFFIX', 'vms_staffing_template_slots');
}
if (!defined('VMS_DB_TABLE_EVENT_ROLE_SLOTS_SUFFIX')) {
	define('VMS_DB_TABLE_EVENT_ROLE_SLOTS_SUFFIX', 'vms_event_role_slots');
}
if (!defined('VMS_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX')) {
	define('VMS_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX', 'vms_event_role_assignments');
}
if (!defined('VMS_DB_TABLE_STAFFING_EVENT_ROLLUPS_SUFFIX')) {
	define('VMS_DB_TABLE_STAFFING_EVENT_ROLLUPS_SUFFIX', 'vms_staffing_event_rollups');
}
if (!defined('VMS_DB_TABLE_STAFFING_AUDIT_LOG_SUFFIX')) {
	define('VMS_DB_TABLE_STAFFING_AUDIT_LOG_SUFFIX', 'vms_staffing_audit_log');
}
if (!defined('VMS_DB_TABLE_TICKET_MUTATION_AUDIT_SUFFIX')) {
	define('VMS_DB_TABLE_TICKET_MUTATION_AUDIT_SUFFIX', 'vms_ticket_mutation_audit');
}
if (!defined('VMS_DB_TABLE_TICKET_INVENTORY_AUDIT_SUFFIX')) {
	define('VMS_DB_TABLE_TICKET_INVENTORY_AUDIT_SUFFIX', 'vms_ticket_inventory_audit');
}

/**
 * Staff Tasks module (V1 foundation)
 */
if (!defined('VMS_CAP_TASKS_MANAGE_TEMPLATES')) {
	define('VMS_CAP_TASKS_MANAGE_TEMPLATES', 'vms_manage_task_templates');
}
if (!defined('VMS_CAP_TASKS_MANAGE_CHECKLISTS')) {
	define('VMS_CAP_TASKS_MANAGE_CHECKLISTS', 'vms_manage_checklist_templates');
}
if (!defined('VMS_CAP_TASKS_MANAGE_ALL')) {
	define('VMS_CAP_TASKS_MANAGE_ALL', 'vms_manage_tasks_all');
}
if (!defined('VMS_CAP_TASKS_COMPLETE_SELF')) {
	define('VMS_CAP_TASKS_COMPLETE_SELF', 'vms_complete_tasks_self');
}
if (!defined('VMS_CAP_TASKS_VIEW_SELF')) {
	define('VMS_CAP_TASKS_VIEW_SELF', 'vms_view_tasks_self');
}
if (!defined('VMS_DB_TABLE_TASK_TEMPLATES_SUFFIX')) {
	define('VMS_DB_TABLE_TASK_TEMPLATES_SUFFIX', 'vms_task_templates');
}
if (!defined('VMS_DB_TABLE_CHECKLIST_TEMPLATES_SUFFIX')) {
	define('VMS_DB_TABLE_CHECKLIST_TEMPLATES_SUFFIX', 'vms_checklist_templates');
}
if (!defined('VMS_DB_TABLE_CHECKLIST_ITEMS_SUFFIX')) {
	define('VMS_DB_TABLE_CHECKLIST_ITEMS_SUFFIX', 'vms_checklist_items');
}
if (!defined('VMS_DB_TABLE_TASK_INSTANCES_SUFFIX')) {
	define('VMS_DB_TABLE_TASK_INSTANCES_SUFFIX', 'vms_task_instances');
}
if (!defined('VMS_DB_TABLE_TASK_LOGS_SUFFIX')) {
	define('VMS_DB_TABLE_TASK_LOGS_SUFFIX', 'vms_task_logs');
}
if (!defined('VMS_OPT_TASKS_DB_SCHEMA_VERSION')) {
	define('VMS_OPT_TASKS_DB_SCHEMA_VERSION', 'vms_tasks_db_schema_version');
}
if (!defined('VMS_OPT_TASKS_SETTINGS')) {
	define('VMS_OPT_TASKS_SETTINGS', 'vms_tasks_settings_v1');
}
if (!defined('VMS_CRON_TASKS_NIGHTLY')) {
	define('VMS_CRON_TASKS_NIGHTLY', 'vms_tasks_nightly_generator');
}

/**
 * Continuity Binder option key (wp_options).
 */
if (!defined('VMS_CONTINUITY_BINDER_OPTION')) {
    define('VMS_CONTINUITY_BINDER_OPTION', 'vms_continuity_binder_v1');
}

if (!defined('VMS_OPT_REWRITE_FLUSH_VENDOR_PROFILES_V1')) {
    define('VMS_OPT_REWRITE_FLUSH_VENDOR_PROFILES_V1', 'vms_rewrite_flushed_vendor_profiles_v1');
}

/**
 * Due Dates / Compliance Obligations (wp_options)
 */
if (!defined('VMS_OPT_DUE_PAYEES')) {
  define('VMS_OPT_DUE_PAYEES', 'vms_due_payees_v1');
}
if (!defined('VMS_OPT_DUE_OBLIGATIONS')) {
  define('VMS_OPT_DUE_OBLIGATIONS', 'vms_due_obligations_v1');
}
if (!defined('VMS_OPT_DUE_LOG')) {
  define('VMS_OPT_DUE_LOG', 'vms_due_log_v1');
}

/**
 * Ticketing templates (wp_options)
 */
if (!defined('VMS_OPT_TICKETING_TEMPLATES_V1')) {
  define('VMS_OPT_TICKETING_TEMPLATES_V1', 'vms_ticketing_templates_v1');
}

/**
 * Ticketing default template (wp_options)
 */
if (!defined('VMS_OPT_TICKETING_DEFAULT_TEMPLATE_V1')) {
  define('VMS_OPT_TICKETING_DEFAULT_TEMPLATE_V1', 'vms_ticketing_default_template_v1');
}

/**
 * Social sharing module (Phase 0/1 foundation)
 */
if (!defined('VMS_CAP_SOCIAL_MANAGE')) {
	define('VMS_CAP_SOCIAL_MANAGE', 'vms_social_manage');
}
if (!defined('VMS_OPT_SOCIAL_SETTINGS_V1')) {
	define('VMS_OPT_SOCIAL_SETTINGS_V1', 'vms_social_settings_v1');
}
if (!defined('VMS_OPT_SOCIAL_DB_SCHEMA_VERSION')) {
	define('VMS_OPT_SOCIAL_DB_SCHEMA_VERSION', 'vms_social_db_schema_version');
}
if (!defined('VMS_SOCIAL_DB_SCHEMA_VERSION')) {
	define('VMS_SOCIAL_DB_SCHEMA_VERSION', 'social_v1');
}
if (!defined('VMS_SOCIAL_CRON_HOOK')) {
	define('VMS_SOCIAL_CRON_HOOK', 'vms_social_process_queue');
}
if (!defined('VMS_SOCIAL_LOCK_TRANSIENT')) {
	define('VMS_SOCIAL_LOCK_TRANSIENT', 'vms_social_runner_lock');
}
if (!defined('VMS_SOCIAL_MAX_ATTEMPTS_DEFAULT')) {
	define('VMS_SOCIAL_MAX_ATTEMPTS_DEFAULT', 5);
}
if (!defined('VMS_DB_TABLE_SOCIAL_ACCOUNTS_SUFFIX')) {
	define('VMS_DB_TABLE_SOCIAL_ACCOUNTS_SUFFIX', 'vms_social_accounts');
}
if (!defined('VMS_DB_TABLE_SOCIAL_VENUE_MAP_SUFFIX')) {
	define('VMS_DB_TABLE_SOCIAL_VENUE_MAP_SUFFIX', 'vms_social_venue_map');
}
if (!defined('VMS_DB_TABLE_SOCIAL_TEMPLATES_SUFFIX')) {
	define('VMS_DB_TABLE_SOCIAL_TEMPLATES_SUFFIX', 'vms_social_templates');
}
if (!defined('VMS_DB_TABLE_SOCIAL_QUEUE_SUFFIX')) {
	define('VMS_DB_TABLE_SOCIAL_QUEUE_SUFFIX', 'vms_social_queue');
}
if (!defined('VMS_DB_TABLE_SOCIAL_AUDIT_SUFFIX')) {
	define('VMS_DB_TABLE_SOCIAL_AUDIT_SUFFIX', 'vms_social_audit');
}

/**
 * Ticketing claims + direct grants (Phase 1 foundation)
 */
if (!defined('VMS_OPT_TICKETING_CLAIMS_DB_SCHEMA_VERSION')) {
	define('VMS_OPT_TICKETING_CLAIMS_DB_SCHEMA_VERSION', 'vms_ticketing_claims_db_schema_version');
}
if (!defined('VMS_TICKETING_CLAIMS_DB_SCHEMA_VERSION')) {
	define('VMS_TICKETING_CLAIMS_DB_SCHEMA_VERSION', 'ticketing_claims_v1');
}
if (!defined('VMS_DB_TABLE_TICKETING_DIRECT_GRANTS_SUFFIX')) {
	define('VMS_DB_TABLE_TICKETING_DIRECT_GRANTS_SUFFIX', 'vms_ticketing_direct_grants');
}
if (!defined('VMS_DB_TABLE_TICKETING_CLAIM_RESERVATIONS_SUFFIX')) {
	define('VMS_DB_TABLE_TICKETING_CLAIM_RESERVATIONS_SUFFIX', 'vms_ticketing_claim_reservations');
}
if (!defined('VMS_DB_TABLE_TICKETING_CLAIM_LOG_SUFFIX')) {
	define('VMS_DB_TABLE_TICKETING_CLAIM_LOG_SUFFIX', 'vms_ticketing_claim_log');
}
