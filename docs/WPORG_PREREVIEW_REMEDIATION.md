# WordPress.org Prereview Remediation Inventory

Date: 2026-07-09

## Executive Summary

- Scope: docs-only audit of the current public-release mirror at `packages/vms-github-reconcile`, cross-checked against the live local plugin source at `vms/`.
- Mirror repo HEAD at audit start: `2ca1843b2449f2444e0fadd574c23be955df282f`.
- Mirror repo HEAD at audit end: `2ca1843b2449f2444e0fadd574c23be955df282f`.
- Mirror worktree started clean. `stash@{0}` existed before the audit and was not touched.
- Runtime PHP, JavaScript, CSS, templates, tests, versions, release packaging, deployment, tagging, pushing, and submission were intentionally not changed in this task.

Current inventory totals from this pass:

- Confirmed actionable findings: `15`
- Likely findings that need deeper review before editing: `6`
- Acceptable / false-positive / compatibility-sensitive findings called out explicitly: `10`
- Product-owner or release-manager decisions required before packaging: `3`
- Later result sections now record completed working-tree remediations for `WPORG-17B` and `WPORG-18A`/`WPORG-18B`/`WPORG-18D`; the opening totals above remain the initial prereview audit snapshot and are not auto-reduced retroactively.

Highest-priority issues from a WordPress.org rejection-risk perspective:

The initial top-five snapshot below predates the later `WPORG-17B` and `WPORG-18A`/`WPORG-18B`/`WPORG-18D` working-tree remediations.

1. The public plugin currently ships a `Premium Add-ons` installer/licensing surface and Freemius activation/validation/deactivation calls.
2. The current i18n wrapper pattern uses non-literal gettext inputs and a dynamic text-domain wrapper that is not translation-parser compatible.
3. The mirror and live plugin metadata are out of sync (`1.0.0` in the mirror vs `1.1.0` in `vms/`).
4. Upload validation is inconsistent across tax-profile, import, and private-file flows.
5. Global admin notices still render outside VMS-specific screens.

## Current Commit and Audit Scope

- Mirror repository: `packages/vms-github-reconcile`
- Live local plugin source: `vms/`
- Public plugin name: `Backstage Venue Manager`
- Requested slug and text domain: `backstage-venue-manager`
- Canonical public plugin entry point: `vendor-management-system.php`
- Compatibility shim still present: `vms.php:1-12`
- Public ZIP exclusions already remove repo-only material such as `docs/`, `tests/`, and `scripts/` from the release package: `release-public-excludes.txt:14-18`

Audit method in this pass:

- Source inspection with targeted `rg`, `sed`, and `nl -ba` searches across the mirror repo.
- Cross-checks against the live `vms/` metadata files only.
- Local toolchain discovery without installing or updating tooling.
- Historical Plugin Check artifacts used as corroborating evidence only where the current tree could not be safely or usefully re-scanned in this pass.

## Master Remediation Table

Ordered by combined security risk, WordPress.org rejection likelihood, and change risk.

| ID | Category | Severity | Confidence | Primary reference | Summary | Change risk | Batch |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `A1` | A, L | High | Confirmed | `includes/admin/addons/class-vms-admin-addons.php:15-45`; `includes/admin/addons/class-vms-addons-licensing.php:61-124`; `assets/admin/addons/manifest-addons.json:21-36` | The historical premium add-ons and Freemius licensing surface has been removed from the current public-core package; fresh package evidence shows no bundled add-ons installer, license-key UI, or Freemius traffic in the verified public boundary. | Medium | `WPORG-17B`, `WPORG-28Q` |
| `J1` | J | High | Confirmed | `includes/core/registry/admin-menu.php:19-30`; `includes/core/registry/statuses.php:13-21` | Dynamic gettext wrapper and non-literal domain usage were remediated in the working tree; final `WPORG-18` verification now shows zero parser violations and zero actionable i18n Plugin Check findings. | Medium | `WPORG-18A`, `WPORG-18B` |
| `J2` | J | High | Confirmed | `includes/admin/continuity-binder.php:266`; `includes/core/event-credits.php:380`; `includes/core/event-plan-save-profiler.php:1476`; `includes/modules/email-followups/admin-ui.php:604`; `vms/includes/modules/admissions/outreach-recipients.php:1861` | Translator-comment, placeholder-order, and final semantic comment-audit findings were remediated in the working tree; `WPORG-18D` corrected the remaining misleading heuristic comments and the final verification suite stayed clean. | Low to Medium | `WPORG-18B`, `WPORG-18D` |
| `M1` | M | High | Confirmed | `vendor-management-system.php:3-13`; `readme.txt:4-9`; `vms-build.txt:1`; `vms/vendor-management-system.php:3-13`; `vms/readme.txt:4-9`; `vms/vms-build.txt:1` | The public-core release boundary is now explicitly defined and verified: mirror metadata and the fresh disposable package align on `1.2.0`, while the live local `vms` tree intentionally remains `1.1.0` pending separate production-convergence work. | Low | `WPORG-28Q` |
| `H1` | D, H | High | Confirmed | `includes/admin/tax-profile-admin-metabox.php:102-118`; `includes/portal/vendor-tax-profile.php:121-137`; `includes/core/private-files.php:541-714` | `WPORG-20B` now routes admin and portal W-9 uploads through validated private-file helpers and brokered downloads; the historical `WPORG-21` H1 scope is fully completed by that committed work. | Medium | `WPORG-20B`, `WPORG-21` |
| `K1` | K | High | Confirmed | `includes/admin-ui/context.php`; `includes/admin/admin-notices.php` | First-run notice now uses the shared VMS admin-notice screen predicate and no longer renders across unrelated WordPress admin screens. | Low | `WPORG-23` |
| `K2` | K | High | Confirmed | `includes/admin-ui/context.php`; `includes/runtime-guards.php`; `includes/ticketing/ticket-integrity-payment-gateway-health.php` | Runtime diagnostics and payment-gateway health notices now use the same shared predicate and remain limited to VMS-owned screens, including the exact Ticket Integrity screen. | Low to Medium | `WPORG-23` |
| `C1` | C | Medium | Confirmed | `includes/cpt/venues.php:266-269`; `includes/cpt/ratings.php:177-180`; `includes/admin/staff-worker-type.php:75-76`; `includes/admin/venue-context.php:169-170`; `includes/vendor-applications.php:1728-1729`; `includes/portal/vendor-tax-profile.php:92-93`; `includes/admin/tax-profile-admin-metabox.php:35-38` | `WPORG-19A` working-tree remediation now normalizes and sanitizes nonce verification inputs across the direct request and wrapper/REST paths. The later complete `WPORG-19B` runtime inventory did not uncover any additional missing-nonce defects. | Low to Medium | `WPORG-19A`, `WPORG-19B` |
| `C2` | C | Medium | Confirmed | `includes/vendor-applications.php:1420`; `includes/vendor-applications.php:1616`; `includes/vendor-applications.php:1736`; `includes/vendor-applications.php:1843`; `includes/vendor-applications.php:1895`; `includes/vendor-applications.php:1924`; `includes/vendor-applications.php:1965`; `includes/helpers.php:3773`; `includes/admin/venue-duplicate-templates.php:372`; `includes/admin/season-dates.php:199-200`; `includes/cpt/event-plans.php:13658` | The complete `WPORG-19B` runtime inventory closed the remaining section C authorization follow-up by replacing broad or missing object-aware gates across vendor-application, vendor-review, venue-template, season-dates, and event-plan edit-screen mutation boundaries, plus aligned vendor-application admin UI gates. | Low | `WPORG-19B` |
| `D1` | D | Medium | Confirmed | `includes/portal/staff-portal.php:1755-1758`; `includes/runtime-guards.php`; `includes/vendor-applications.php`; `includes/portal/vendor-portal.php`; `includes/integrations/ticketing-verifications.php`; `includes/core/vendor-application-confirmation.php` | `WPORG-20A` working-tree remediation now normalizes ordinary request-global, redirect-derived, and server-derived inputs across the shared mirror/live runtime boundaries; the original `FILTER_UNSAFE_RAW` staff-portal path is removed and the reviewed redirect/server examples now flow through shared helper validation. | Low to Medium | `WPORG-20A` |
| `H2` | D, H | Medium | Confirmed | `includes/admin/data-tools/actions-event-plan-import.php:13-54`; `includes/services/event-plan-import/event-plan-import-engine.php`; `tests/upload-validation-guards.php:132-231` | `WPORG-20B` now validates CSV upload structure and MIME before persistence, stores importer artifacts via safe private storage keys, and fully covers the historical `WPORG-21` H2 scope. | Medium | `WPORG-20B`, `WPORG-21` |
| `H3` | D, H | Medium | Confirmed | `includes/safety/private-files.php:177-221`; `includes/core/private-files.php:355-714`; `includes/core/staffing.php:620-690` | `WPORG-20B` now validates private operational uploads before persistence, brokers authenticated downloads, and avoids new raw absolute-path persistence for these flows; the historical `WPORG-21` H3 scope is fully completed by that committed work. | Medium | `WPORG-20B`, `WPORG-21` |
| `B1` | B | Medium | Confirmed | `assets/js/vms-event-plan-shell.js`; `assets/js/vms-event-plan-staff.js`; `assets/js/vms-event-plan-title.js`; `assets/js/vms-event-plan-compensation.js`; `assets/js/vms-event-plan-secondary-vendors.js`; `assets/js/vms-event-plan-primary-vendor.js`; `assets/js/vms-event-plan-workflow.js`; `assets/admin-ticketing.js` | Event Plan executable inline runtime is fully remediated: dense PHP-emitted controllers moved into enqueued external assets, the obsolete editor-scripts partial is gone, and only inert scoped Secondary Vendors JSON plus minimal scoped ticketing configuration remain. | Low to Medium | `WPORG-22` |
| `B2` | B | Medium | Confirmed | `assets/js/vms-vendor-portal.js`; `assets/js/vms-public-calendar.js`; `includes/portal/vendor-portal.php` | Vendor Portal inline JS findings are fully remediated: obsolete modal runtime was removed, passive shell/form-submit and availability controllers moved into external assets, and only scoped availability JSON configuration remains. | Medium | `WPORG-22` |
| `B3` | B | Medium | Confirmed | `includes/vendor-applications.php:803,1247,1606`; `assets/js/vms-vendor-apply.js`; `assets/css/vms-admin.css:5767-5814` | Vendor Applications inline asset findings are fully remediated: the public form JS is externalized, the public CSS was already asset-backed, and the remaining admin-only status-pill CSS now lives in `vms-admin.css`. | Low to Medium | `WPORG-22` |
| `B4` | B | Medium | Confirmed | `includes/integrations/ticketing-rules-v2.php:8080-8198`; `assets/vms-ticketing-front.js:4871-5666` | Ticketing Rules V2 no longer emits executable inline JS for the server-controls add-on flow; the existing `vms-ticketing-front` bundle already owns that behavior and continues to read the same localized config plus server-rendered `data-*` payloads. | Medium | `WPORG-22` |
| `B5` | B | Low | Confirmed | `assets/css/admin-ticket-integrity.css`; `includes/admin/ticket-integrity-page.php` | Ticket Integrity inline CSS is fully remediated: the menu-badge rules now live in the enqueued `vms-admin-ticket-integrity` stylesheet, with the existing badge/no-badge PHP logic retained. | Low | `WPORG-22` |
| `D2` | D | Medium | Confirmed | `includes/vendor-applications.php:2142-2192`; `includes/runtime-guards.php` | `WPORG-20C` now validates the decoded Turnstile response shape before trusting `success`, keeping the earlier `WPORG-20A` request-fingerprint normalization intact. | Low to Medium | `WPORG-20A`, `WPORG-20C` |
| `D3` | D | Medium | Confirmed | `includes/integrations/ticketing-phase-b.php:1919-2051`; `includes/integrations/ticketing-phase-b.php:9596-9729` | `WPORG-20C` now bounds, decodes, and shape-validates the Phase B tier, commit-item, and config/template JSON payloads before the existing per-field normalizers run. | Medium | `WPORG-20C` |
| `D4` | D | Medium | Confirmed | `includes/integrations/ticketing-rules-v2.php:3082-3190`; `includes/integrations/ticketing-rules-v2.php:9089-9519` | `WPORG-20C` now bounds raw JSON-body reads and validates Ticketing Rules V2 atomic-add and silent-add payload shapes before any cart mutation or claim-assignment normalization occurs. | Medium | `WPORG-20C` |
| `E1` | E | Medium | Likely | `docs/plugin-check-1.0.0-raw.txt:475`; `docs/plugin-check-1.0.0-raw.txt:733`; `docs/plugin-check-1.0.0-raw.txt:1770`; `docs/plugin-check-1.0.0-raw.txt:2115`; `docs/plugin-check-1.0.0-raw.txt:2438`; `docs/plugin-check-1.0.0-raw.txt:2906` | Historical packaged Plugin Check still points to large output-escaping hotspots that were not re-audited deeply in this pass. | Medium | `WPORG-24` |
| `I1` | I | Medium | Confirmed | `includes/integrations/load.php`; `includes/integrations/ticketing.php`; `includes/integrations/ticketing-phase-b.php`; `includes/integrations/ticketing-rules-v2.php`; `includes/integrations/ticketing-claims-customer.php` | `WPORG-25` now verifies the request-global AJAX opener is explicitly owned by legacy diagnostic cleanup wrappers, V2 cleanup-only wrappers, and the approved fast helper across every audited callback family; no direct-send responder remains beneath the opener. | Medium | `WPORG-25` |
| `I2` | I | Medium | Confirmed | `includes/integrations/ticketing-rules-v2.php`; `tests/ticketing-my-tickets-notice-source-filter-remediation.php`; `tests/ticketing-server-mount-native-footer-remediation.php` | `WPORG-25` removed the My Tickets and server-mount full-page callback buffers. My Tickets now uses `tec_tickets_my_tickets_link_ticket_count_by_type`, reserved add-ons now use `tribe_template_before_include_html:tickets/v2/tickets/footer`, and disabled ticket suppression now uses native `tribe_tickets_get_tickets_query_args`. | Medium | `WPORG-25` |

## Findings Already Resolved, Acceptable, or Compatibility-Sensitive

- Core bundled modules are not currently marked as premium-gated. Representative refs: `includes/modules/admissions/admissions.php:19-26`, `includes/modules/status-notices/status-notices.php:14-21`, `includes/modules/staff-tasks/staff-tasks.php:20-27`, `includes/modules/email-followups/email-followups.php:5-10`, `includes/modules/availability-date-dispatch/availability-date-dispatch.php:17-24`.
- The package-owned fallback structured-data sink in `includes/public/event-details.php:780-824` now uses explicit script-safe JSON encoding inside an inert `application/ld+json` script; it remains structured data, not executable application logic, and must stay distinct from TEC-owned final emitters.
- JSON state blobs in `includes/admin/addons/views/page-addons.php:53`, `includes/admin/vendor-command-center.php:1498`, `includes/admin/vendor-command-center.php:1556`, and `includes/cpt/event-plans/partials/secondary-vendors.php:465` are acceptable `application/json` payloads, not inline script execution.
- Runtime AJAX URLs are generally generated correctly with `admin_url('admin-ajax.php')`; the only hard-coded `/wp-admin/admin-ajax.php` strings found in runtime PHP are log-normalization keys in `includes/core/slow-request-logger.php:292-306`.
- The internal `vms` prefix surface is broad and established across CPTs, taxonomies, REST namespaces, shortcodes, AJAX actions, and options. This is compatibility-sensitive and should not be blindly renamed to match the new display name or slug.
- `includes/modules/staff-tasks/admin-ui.php:704`, `includes/modules/staff-tasks/admin-ui.php:778`, and `includes/modules/staff-tasks/admin-ui.php:843` already unslash `_wpnonce` before `wp_verify_nonce()`.
- `includes/integrations/ticketing-verifications.php:2130-2178` is a comparatively robust upload pattern and should be treated as the preferred model for future upload fixes.
- `includes/modules/status-notices/front.php:26-29` and `includes/modules/status-notices/front.php:84` use request URI values for runtime context, not direct output or redirects.
- `includes/admin-ui/nav.php:547` uses `all_admin_notices`, but the known top-nav system is already VMS-screen oriented and is not the same issue as the global notices tracked in `K1` and `K2`.
- Driver.js is bundled with a license file and appears GPL-compatible via MIT licensing: `assets/vendor/driverjs/LICENSE.txt:1-13`.

## A. GPL Compatibility, Trialware, and Locked Features

Status:

- No current public-package blocker remains in the verified public-core artifact.
- One important "already resolved / not a blocker by itself" clarification.

### `A1` Public premium add-ons installer and Freemius licensing surface

- Severity: High
- Confidence: Confirmed
- References: `includes/admin/addons/class-vms-admin-addons.php:15-45`; `includes/admin/addons/class-vms-admin-addons.php:219-273`; `includes/admin/addons/class-vms-addons-licensing.php:61-124`; `includes/admin/addons/views/page-addons.php:15`; `assets/admin/addons/manifest-addons.json:21-36`
- Current status: this package-scope concern is resolved in the current public-core package. Fresh repository and packaged audits now show no bundled `Premium Add-ons` admin area, ZIP installer/update actions, license-key storage UI, Freemius activate/validate/deactivate operations, or Freemius disclosure in the public readme, while optional companion-plugin detection remains passive only.
- Recommended remediation: already applied by `WPORG-17B`; the current package should continue shipping without the removed add-ons installer/licensing surface.
- Compatibility or regression risk: Medium. The lowest-risk path is packaging-scope removal or conditional omission of the premium-management UI, not broad internal refactoring.
- Suggested remediation batch ID: `WPORG-17B`

Acceptable / already resolved notes:

- The inspected core module registrations are all marked `'premium' => false`: `includes/modules/admissions/admissions.php:19-26`, `includes/modules/status-notices/status-notices.php:14-21`, `includes/modules/staff-tasks/staff-tasks.php:20-27`, `includes/modules/email-followups/email-followups.php:5-10`, `includes/modules/availability-date-dispatch/availability-date-dispatch.php:17-24`.
- The current readme discloses Cloudflare Turnstile, QRServer / goQR.me, vendor-provided ICS URLs, and operator-configured webhook endpoints only: `readme.txt:80-100`.

## B. Inline JavaScript and CSS

Status:

- The original scanner-style hit table below is retained as historical prereview evidence.
- Historical `WPORG-22` B1 through B5 remain completed in the current mirror history.
- The final prereview also identified a bounded residual inline-asset inventory tracked separately under `WPORG-22R`; `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-F`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, `WPORG-22R-L`, and `WPORG-22R-M` below close the Reference Keys Map, Holidays, Event Plan Import, Staff Tasks module-admin, Vendor Compensation metabox helpers, Tax Bypass, ADD request-builder, ADD menu-badge helpers, the standalone ADD public-shell CSS residual, the Staff CPT qualifications helper, the Staffing admin helpers, and the Staff Portal runtime helpers, and the formal documentation-only closeout below now marks the parent `verified` after stale-test reconciliation at `db9f19d7c14bb36c06f6467af04d5ac62af62566`, 17 maintained tests, 22 support/sentinel tests, and a fresh scoped scan confirmed that no executable inline JS/CSS remains inside the tracked `WPORG-22R` runtime family while unrelated executable inline assets remain elsewhere in the repository outside this parent.
- Remaining script-like Event Plan payloads reviewed for B1 are limited to inert scoped JSON and minimal scoped ticketing configuration, not executable controller code.
- Acceptable structured-data/state-blob patterns should not be "fixed" as if they were executable inline JS.

Historical inline-hit counts from the initial pass:

| File | Count |
| --- | ---: |
| `includes/cpt/event-plans.php` | `12` |
| `includes/cpt/event-plans/partials/editor-scripts.php` | `12` |
| `includes/portal/vendor-portal.php` | `10` |
| `includes/vendor-applications.php` | `2` |
| `includes/integrations/ticketing-rules-v2.php` | `1` |
| `includes/admin/ticket-integrity-page.php` | `1` |

### `B1` Event Plan inline runtime is now externalized

- Severity: Medium
- Confidence: Confirmed complete
- Historical finding: Event Plans and its editor partials contained dense inline executable JavaScript in `includes/cpt/event-plans.php` and the now-deleted `includes/cpt/event-plans/partials/editor-scripts.php`.
- Current references: `assets/js/vms-event-plan-shell.js`; `assets/js/vms-event-plan-staff.js`; `assets/js/vms-event-plan-title.js`; `assets/js/vms-event-plan-compensation.js`; `assets/js/vms-event-plan-secondary-vendors.js`; `assets/js/vms-event-plan-primary-vendor.js`; `assets/js/vms-event-plan-workflow.js`; `assets/admin-ticketing.js`.
- Remediation outcome: executable Event Plan controller behavior now lives in enqueued external assets, active Event Plan PHP/partials no longer emit executable inline `<script>` controllers, and the obsolete `editor-scripts.php` partial is removed.
- Allowed configuration retained: `includes/cpt/event-plans/partials/secondary-vendors.php` keeps an inert `application/json` payload for Secondary Vendors, and `includes/integrations/ticketing.php` keeps a narrowly scoped `window.VMS_TICKETING` configuration block before `vms-admin-ticketing`; neither contains static controller behavior.
- Focused coverage: `tests/event-plan-generic-scroll-inline-js-remediation.php`, `tests/event-plan-ticketing-focus-inline-js-remediation.php`, `tests/event-plan-shell-controller-inline-js-remediation.php`, `tests/event-plan-staff-inline-js-remediation.php`, `tests/event-plan-title-sync-inline-js-remediation.php`, `tests/event-plan-compensation-refresh-inline-js-remediation.php`, `tests/event-plan-compensation-shell-inline-js-remediation.php`, `tests/event-plan-secondary-vendor-inline-js-remediation.php`, `tests/event-plan-primary-vendor-tax-inline-js-remediation.php`, `tests/event-plan-workflow-confirmations-inline-js-remediation.php`, and `tests/event-plan-dead-editor-scripts-partial-removal.php`.
- Closure audit result: `B1 COMPLETE`.
- Suggested remediation batch ID: `WPORG-22`

### `B2` Vendor Portal inline scripts and event handlers are now externalized

- Severity: Medium
- Confidence: Confirmed complete
- Historical finding: Vendor Portal contained executable inline JS plus inline form-submit attributes such as `onchange="this.form.submit()"`.
- Current references: `assets/js/vms-vendor-portal.js`; `assets/js/vms-public-calendar.js`; `includes/portal/vendor-portal.php`.
- Remediation outcome: obsolete modal runtime was removed, passive portal shell listeners and form-submit handlers moved into delegated external-asset listeners, and availability open-state plus autosave controller behavior now lives in `assets/js/vms-vendor-portal.js`.
- Allowed configuration retained: `includes/portal/vendor-portal.php` keeps a scoped availability `application/json` payload consumed by the external Vendor Portal asset; the executable `window.VMS_AV` bootstrap is gone.
- Focused coverage: `tests/vendor-portal-modal-inline-js-remediation.php`, `tests/vendor-portal-shell-inline-js-remediation.php`, `tests/vendor-portal-availability-open-state-remediation.php`, `tests/vendor-portal-availability-autosave-remediation.php`, and `tests/vendor-portal-availability-autosave-ajax.php`.
- Closure result: `B2 COMPLETE`.
- Suggested remediation batch ID: `WPORG-22`

### `B3` Vendor Applications inline CSS and inline JS are now externalized

- Severity: Medium
- Confidence: Confirmed
- References: `includes/vendor-applications.php:803,1247,1606`; `assets/js/vms-vendor-apply.js`; `assets/css/vms-admin.css:5767-5814`
- Why WordPress.org may object: this was previously a scanner-visible inline JS/CSS hit in a public-facing submission flow.
- Recommended remediation: keep the public form behavior in `assets/js/vms-vendor-apply.js`, keep the existing public CSS assets, and keep the remaining admin-only status-pill presentation in `assets/css/vms-admin.css`.
- Compatibility or regression risk: Low to Medium.
- Suggested remediation batch ID: `WPORG-22`

### `B4` Ticketing Rules V2 server-controls JS is now externalized

- Severity: Medium
- Confidence: Confirmed
- References: `includes/integrations/ticketing-rules-v2.php:8080-8198`; `assets/vms-ticketing-front.js:4871-5666`
- Why WordPress.org may object: this was previously a large executable inline controller embedded directly in the public Ticketing Rules V2 renderer.
- Recommended remediation: keep the existing `vms-ticketing-front` bundle as the only runtime owner for the server-controls flow and preserve the PHP-to-JS handoff through the existing localized config plus `data-*` attributes on `#vms-reserved-addons`.
- Compatibility or regression risk: Medium because this file also drives cart and claims flows.
- Suggested remediation batch ID: `WPORG-22`

### `B5` Ticket Integrity inline CSS is now externalized

- Severity: Low
- Confidence: Confirmed complete
- Historical finding: Ticket Integrity printed a small admin-only inline CSS block directly from PHP.
- Current references: `assets/css/admin-ticket-integrity.css`; `includes/admin/ticket-integrity-page.php`.
- Remediation outcome: the static menu-badge rules now live in the enqueued `vms-admin-ticket-integrity` stylesheet while the existing badge/no-badge PHP decision logic remains intact.
- Focused coverage: `tests/ticket-integrity-inline-css-remediation.php` and `tests/ticket-integrity-scan-lock.php`.
- Closure result: `B5 COMPLETE`.
- Suggested remediation batch ID: `WPORG-22`

Acceptable / false-positive notes:

- The package-owned fallback JSON-LD sink in `includes/public/event-details.php:780-824` now hardens output with explicit script-safe JSON flags. The separate TEC-owned final JSON-LD path is still inert structured data, not executable runtime JS, and should not be removed blindly.
- `includes/admin/addons/views/page-addons.php:53`, `includes/admin/vendor-command-center.php:1498`, `includes/admin/vendor-command-center.php:1556`, and `includes/cpt/event-plans/partials/secondary-vendors.php:465` emit `application/json` state payloads, which are materially different from executable `<script>` blocks.

## C. Nonces and Permissions

Status:

- `WPORG-19A` nonce verification input normalization is now applied in the current mirror/live working tree for the shared runtime paths, plus the live-only local outreach module.
- `WPORG-19B` follow-up is now applied in the current mirror/live working tree after a complete runtime handler inventory across the mirror and live request-processing surfaces.
- No additional missing-nonce additions, REST permission-callback changes, or public token/link trust-model changes were required in the completed runtime inventory.
- Several handlers already had the right capability/nonce shape and only required normalization cleanup, not new permission design.

### `C1` Multiple state-changing handlers verify raw submitted nonces without `wp_unslash()`

- Severity: Medium
- Confidence: Confirmed
- References: `includes/cpt/venues.php:266-269`; `includes/cpt/venues.php:382`; `includes/cpt/venues.php:536`; `includes/cpt/ratings.php:177-180`; `includes/cpt/ratings.php:404`; `includes/admin/staff-worker-type.php:75-76`; `includes/admin/venue-context.php:169-170`; `includes/admin/venue-context.php:224`; `includes/admin/venue-context.php:278`; `includes/admin/tax-profile-admin-metabox.php:35-38`; `includes/portal/vendor-tax-profile.php:92-93`; `includes/vendor-applications.php:1728-1729`; `includes/vendor-applications.php:2581-2582`
- Why WordPress.org may object: WordPress coding standards expect submitted nonce values to be unslashed before verification, and these paths mutate state.
- Recommended remediation: normalize each submitted nonce once with `wp_unslash()` plus string casting before `wp_verify_nonce()`, while keeping the existing capability and early-return behavior intact. That normalization pass is now applied in the current `WPORG-19A` working tree. The later `WPORG-19B` follow-up then closed the remaining object-level authorization review for the complete runtime handler inventory.
- Compatibility or regression risk: Low to Medium if limited to nonce normalization only.
- Suggested remediation batch ID: `WPORG-19A`, `WPORG-19B`

### `C2` Specific-object mutation and direct-dispatch handlers lacked object-aware authorization

- Severity: Medium
- Confidence: Confirmed
- References: `includes/vendor-applications.php:1420`; `includes/vendor-applications.php:1616`; `includes/vendor-applications.php:1736`; `includes/vendor-applications.php:1843`; `includes/vendor-applications.php:1895`; `includes/vendor-applications.php:1924`; `includes/vendor-applications.php:1965`; `includes/helpers.php:3773`; `includes/admin/venue-duplicate-templates.php:372`; `includes/admin/season-dates.php:199-200`; `includes/cpt/event-plans.php:13658`
- Why WordPress.org may object: several admin-post, save-post, and direct request dispatchers mutated specific posts or settings while relying on broad `edit_posts` access or on no handler-local authorization check, which can over-authorize operators who may edit some posts but not the targeted object or who should not reach an admin settings mutation path at all.
- Recommended remediation: preserve existing nonce actions, request keys, redirects, and operator flows while requiring `current_user_can('edit_post', $object_id)` or the existing filtered admin capability on the targeted object or page before any mutation. That targeted follow-up is now applied in the current `WPORG-19B` working tree, along with matching live-tree updates, a complete runtime inventory, and focused behavioral regression coverage.
- Compatibility or regression risk: Low because the remediation narrows authorization only at the specific-object boundary and does not alter the surrounding workflow.
- Suggested remediation batch ID: `WPORG-19B`

Acceptable notes:

- Some request reads are read-only and do not need nonce checks merely to silence scanners.
- `includes/modules/staff-tasks/admin-ui.php:704`, `includes/modules/staff-tasks/admin-ui.php:778`, and `includes/modules/staff-tasks/admin-ui.php:843` already follow the expected unslash pattern and can be used as a local reference implementation.

## D. Input Sanitization and Validation

Status:

- `WPORG-20A` ordinary request-global sanitization and redirect/server normalization is now applied in the current mirror/live working tree.
- The original `FILTER_UNSAFE_RAW` example and the reviewed raw redirect/server-value examples are remediated.
- The upload transport and MIME trust follow-up originally deferred from `WPORG-20A` is now completed in committed `WPORG-20B`.
- The audited `WPORG-20C` decoded JSON / structured-body validation follow-up is now applied across the first-party request, importer cache, and remote-response paths reviewed in this batch.

### `D1` Ordinary request-global sanitization and `FILTER_UNSAFE_RAW` remediation are now applied in the working tree

- Severity: Medium
- Confidence: Confirmed
- References: `includes/portal/staff-portal.php:1755-1758`; `includes/runtime-guards.php`; `includes/helpers.php`; `includes/admin/venue-context.php`; `includes/cpt/ratings.php`; `includes/vendor-applications.php`; `includes/portal/vendor-portal.php`; `includes/integrations/ticketing-verifications.php`; `includes/core/vendor-application-confirmation.php`
- Why WordPress.org may object: ordinary request/global values were previously mixed with redirect fields, server diagnostics, form repopulation, and request wrappers in a way that relied on raw access, late escaping, or ad hoc normalization.
- Recommended remediation: complete the request-global audit, add shared scalar/server/redirect helpers, replace `FILTER_UNSAFE_RAW`, apply shape guards before scalar operations, and keep escaping late at output. That work is now applied in the current `WPORG-20A` working tree for the ordinary request/global surfaces reviewed in this batch.
- Compatibility or regression risk: Low to Medium because the remediation preserves keys, redirects, hook names, and business logic while tightening only the input boundary.
- Suggested remediation batch ID: `WPORG-20A`

### `D2` Turnstile verification and fingerprint storage validation is now applied

- Severity: Medium
- Confidence: Confirmed
- References: `includes/vendor-applications.php:2142-2192`; `includes/runtime-guards.php`
- Why WordPress.org may object: the flow previously trusted a minimally validated `json_decode()` result and recorded raw IP / user-agent values after trimming only.
- Recommended remediation: validate the Turnstile response as a bounded JSON object with the expected boolean `success` field before treating the verification as authoritative, while keeping the earlier request-fingerprint normalization from `WPORG-20A`. That work is now applied in the current `WPORG-20C` working tree.
- Compatibility or regression risk: Low to Medium.
- Suggested remediation batch ID: `WPORG-20A`, `WPORG-20C`

### `D3` Ticketing Phase B JSON payload validation is now applied

- Severity: Medium
- Confidence: Confirmed
- References: `includes/integrations/ticketing-phase-b.php:1919-2051`; `includes/integrations/ticketing-phase-b.php:9596-9729`
- Why WordPress.org may object: JSON-decoded arrays were accepted after basic type checks, but there was limited per-key validation before later logic consumed them.
- Recommended remediation: bound the raw JSON strings, require list-vs-object shape explicitly, and reject malformed tier, commit-item, and config/template payloads before the established Phase B normalizers and sync logic execute. That work is now applied in the current `WPORG-20C` working tree.
- Compatibility or regression risk: Medium.
- Suggested remediation batch ID: `WPORG-20C`

### `D4` Ticketing Rules V2 JSON-body validation is now applied

- Severity: Medium
- Confidence: Confirmed
- References: `includes/integrations/ticketing-rules-v2.php:3082-3190`; `includes/integrations/ticketing-rules-v2.php:9089-9519`
- Why WordPress.org may object: raw request bodies were JSON-decoded and then normalized, but the payload contract was not fully explicit from the initial guard layer.
- Recommended remediation: read bounded request bodies, reject malformed JSON objects up front, and validate ticket-line, add-on-line, variation, and claim-assignment shapes before any cart mutation or downstream normalization occurs. That work is now applied in the current `WPORG-20C` working tree.
- Compatibility or regression risk: Medium.
- Suggested remediation batch ID: `WPORG-20C`

Compatibility-sensitive / explain-only notes:

- `includes/runtime-guards.php:357-360` and `includes/core/event-plan-performance.php:129-135` use request URI values in diagnostic and fingerprinting code. These are not automatically safe, but they are not equivalent to direct unescaped output or redirect sinks.
- `includes/modules/status-notices/front.php:26-29` and `includes/modules/status-notices/front.php:84` use request URI values for internal runtime context, not public rendering.

## E. Output Escaping

Status:

- No new high-confidence blocker was proven by direct code inspection in this pass beyond the structured-data exception already reviewed.
- Historical packaged Plugin Check artifacts still show dense escaping hotspots that deserve a dedicated follow-up batch.

### `E1` Historical packaged Plugin Check still points to large output-safety hotspots

- Severity: Medium
- Confidence: Likely
- References: `docs/plugin-check-1.0.0-raw.txt:475`; `docs/plugin-check-1.0.0-raw.txt:733`; `docs/plugin-check-1.0.0-raw.txt:1770`; `docs/plugin-check-1.0.0-raw.txt:2115`; `docs/plugin-check-1.0.0-raw.txt:2438`; `docs/plugin-check-1.0.0-raw.txt:2906`
- Why WordPress.org may object: historical packaged scans still cluster `OutputNotEscaped` and related findings in Event Plans, Vendor Applications, Availability Dispatch admin UI, Staff Portal, admin shell UI, and Vendor Portal.
- Recommended remediation: run a dedicated escape-contract pass that distinguishes safe HTML wrappers from raw output defects and does not conflate JSON-LD or pre-sanitized HTML with normal attribute/text contexts.
- Compatibility or regression risk: Medium because several remaining sites appear to depend on helper-generated HTML.
- Suggested remediation batch ID: `WPORG-24`

Acceptable note:

- The package-owned fallback JSON-LD sink in `includes/public/event-details.php:780-824` is now hardened at the output boundary. Remaining JSON-LD review should stay explanation-only for the separate TEC-owned final emitter path unless its schema payload itself is incorrect.

### `WPORG-24 E1` first portal-notice sink normalization result

- Result: the first narrow E1 slice normalized the remaining direct `vms_portal_notice()` sinks outside the main Vendor Portal in `includes/portal/vendor-tax-profile.php` and `includes/modules/admissions/vendor-guest-portal.php`.
- Contract: `vms_portal_notice()` still returns the same escaped `<div class="vms-notice vms-notice-{type}">...</div>` fragment; rendered classes, notice copy, and business logic are unchanged.
- Sink pattern: those two files now use the established `wp_kses_post(vms_portal_notice(...))` pattern already used by `includes/portal/vendor-portal.php`.
- Historical scope note: at the time of this slice, `WPORG-24` remained open because ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output still awaited later work. `WPORG-24R` later reconciles those residuals and closes the parent without reopening production code.
- Focused coverage: `tests/portal-notice-sink-remediation.php`.

### `WPORG-24 E1` public documentation Markdown output-contract result

- Result: the public documentation Markdown sink now explicitly enforces the renderer's narrow rendered-document HTML contract before echoing the rendered output.
- Production files inspected: `includes/docs-render.php`, `includes/docs-public.php`, and `includes/admin/docs-page.php`.
- Production files changed: `includes/docs-render.php` and `includes/docs-public.php`.
- Contract: `vms_docs_rendered_allowed_html()` permits only `h1`-`h6`, `p`, `ul`, `li`, `pre`, `code`, `a` with `href`, `target`, and `rel`, `strong`, and `em`; `vms_docs_inline_allowed_html()` remains limited to `a` with `href`, `target`, and `rel`, `strong`, `em`, and `code`.
- Formatting preserved: headings, paragraphs, unordered lists and list items, links with their existing safe attributes, bold text, italic text, inline code, and fenced code blocks still render as HTML.
- Boundary behavior: unsupported raw elements, event-handler attributes, unsafe link protocols, and HTML-like code text do not survive as executable or allowed markup.
- Administrator caller: `includes/admin/docs-page.php` was inspected and intentionally left unchanged because it already applies an explicit final `wp_kses()` contract and this slice targets the public sink.
- Focused coverage and validation: `tests/docs-public-markdown-output-remediation.php`; `php -l includes/docs-public.php`; `php -l includes/docs-render.php`; `php -l tests/docs-public-markdown-output-remediation.php`; `php tests/docs-public-markdown-output-remediation.php`; `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open because ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output had not yet been completed. `WPORG-24R` later reconciles those residuals and closes the parent without reopening production work.

### `WPORG-24 E1` ADD dispatch pill output-contract result

- Result: ADD administrator status/source pill fragments now use a dedicated narrow final-output contract at every production sink.
- Production helper file inspected and changed: `includes/modules/availability-date-dispatch/admin-ui.php`.
- Contract: `vms_add_dispatch_pill_allowed_html()` permits only `span` with the `class` attribute.
- Helper behavior preserved: `vms_add_dispatch_status_pill()` and `vms_add_dispatch_source_pill()` still normalize status/source values with `sanitize_key()`, preserve the existing status/source label maps, preserve the neutral/success/warning/danger/source CSS classes, escape classes with `esc_attr()`, and escape labels with `esc_html()`.
- Complete production caller inventory normalized: status-pill sinks at `admin-ui.php:350`, `:366`, `:466`, `:525`, `:557`, `:724`, `:882`, and `:956`; source-pill sinks at `admin-ui.php:365`, `:523`, `:556`, `:831`, and `:955`.
- Boundary behavior: each sink applies `wp_kses(vms_add_dispatch_status_pill(...), vms_add_dispatch_pill_allowed_html())` or `wp_kses(vms_add_dispatch_source_pill(...), vms_add_dispatch_pill_allowed_html())`; no larger ADD table, row, card, form, or page HTML is filtered through the pill allowlist.
- Focused coverage and validation: `php -l includes/modules/availability-date-dispatch/admin-ui.php`, `php -l tests/add-dispatch-pill-output-remediation.php`, `php tests/add-dispatch-pill-output-remediation.php`, `php tests/event-plan-secondary-vendor-capacity-and-add.php`, and `git diff --check` passed. `php tests/add-dispatch-open-vendor-needs.php` still has the known unrelated `WPORG-27` missing-primary ADD visibility failure, and `php tests/add-dispatch-assignment-review.php` remains blocked in this local harness because `wp-load.php` could not be located.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open because ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output had not yet been completed. `WPORG-24R` later reconciles those residuals and closes the parent without reopening production work.

### `WPORG-24 E1` Staff Portal safe-HTML output-contract result

- Result: Staff Portal helper-generated safe HTML fragments now use an explicit named final-output contract at every production sink.
- Production files inspected: `includes/portal/staff-portal.php` and `includes/portal/vendor-portal.php` for the `vms_portal_notice()` fragment consumed by the Staff Portal notice wrapper.
- Production file changed: `includes/portal/staff-portal.php`.
- Contract: `vms_staff_portal_safe_html_allowed_html()` preserves the existing Staff Portal safe-HTML contract: `a[class,href,loading,rel,target]`, `div[class,tabindex]`, `img[alt,class,loading,src]`, `p[class]`, and `span[aria-hidden,class]`.
- HTML-bearing origins and trust level: the safe fragments are fixed plugin-generated portal notices, tax/certification badges, and assignment-calendar card fragments. Notice text is escaped by `vms_portal_notice()` or the Staff Portal fallback; badge labels/classes are escaped or sanitized before composition; assignment titles, icons, venue names, dates, times, excerpts, and shift metadata are escaped as text; assignment URLs and images are passed through `esc_url()` before the fragment is composed.
- Relevant helper/value inventory: `vms_staff_portal_badge_html()` creates tax status badge spans; `vms_staff_portal_safe_html()` remains the defense-in-depth helper and now uses `vms_staff_portal_safe_html_allowed_html()`; `vms_staff_portal_notice_html()` prepares portal notice fragments; `vms_staff_portal_certification_status_badge()` creates certification badge spans; `$assignment_markup` builds the Staff Portal availability assignment fragment from already escaped text and URL parts.
- Complete production final-output inventory normalized: `vms_staff_portal_safe_html()` sinks at `staff-portal.php:270`, `:333`, `:1895`, `:2149`, and `:2779`; `vms_staff_portal_notice_html()` sinks at `staff-portal.php:1963`, `:1976`, `:2038`, `:2074`, `:2108`, `:2323`, `:2337`, `:2346`, `:2351`, `:2353`, `:2367`, `:2370`, `:2381`, `:2417`, `:2430`, `:2451`, and `:2576`.
- Boundary behavior: each final sink applies `wp_kses(vms_staff_portal_safe_html(...), vms_staff_portal_safe_html_allowed_html())` or `wp_kses(vms_staff_portal_notice_html(...), vms_staff_portal_safe_html_allowed_html())`; no full Staff Portal page, card, form, calendar, table, buffered shell, or navigation structure is filtered through the fragment allowlist.
- Defense in depth preserved: `vms_staff_portal_safe_html()` still sanitizes fragments with `wp_kses()`, final sinks apply an explicit second `wp_kses()` contract, text nodes continue using `esc_html()` / `esc_html__()`, attributes continue using `esc_attr()`, URLs continue using `esc_url()` or `esc_url_raw()` as already applicable, and request/nonce handling was not changed.
- Focused coverage and validation: `php -l includes/portal/staff-portal.php`, `php -l tests/staff-portal-safe-html-output-remediation.php`, `php tests/staff-portal-safe-html-output-remediation.php`, `php tests/vendor-portal-availability-autosave-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check` passed.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open because ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output had not yet been completed. `WPORG-24R` later reconciles those residuals and closes the parent without reopening production work.

### `WPORG-24 E1` Vendor application confirmation output-contract result

- Result: Vendor application confirmation, duplicate-status, resend-form, and small Vendor Portal applicant fragments now use an explicit named final-output contract before browser output.
- Historical finding inspected: the packaged Plugin Check artifact for `includes/core/vendor-application-confirmation.php` included `OutputNotEscaped` hits for `vms_vendor_app_render_resend_confirmation_form()`, `get_language_attributes()`, and the confirmation shell `$content` output.
- Production files inspected: `includes/core/load.php`, `includes/core/vendor-application-confirmation.php`, `includes/vendor-applications.php`, `includes/portal/vendor-portal.php`, and `docs/plugin-check-1.0.0-raw.txt`.
- Production file changed: `includes/core/vendor-application-confirmation.php`.
- Output types covered: the public token confirmation endpoint shell content emitted from `template_redirect`, shortcode-returned `[vms_vendor_apply]` confirmation/status fragments, and the small Vendor Portal applicant panel fragment; AJAX, JSON, email, page shells, and unrelated form shells were not changed.
- Contract: `vms_vendor_app_confirmation_allowed_html()` permits only `a[class,href]`, `button[class,type]`, `div[class]`, `form[action,class,method]`, `h2`, `input[id,name,type,value]`, `li`, `ol[class]`, `p[class]`, `section[class]`, `span[class]`, and `strong`.
- HTML-bearing origins and trust level: the fragments are fixed plugin-generated/translated confirmation copy, local application/portal/reset/admin-post URLs, public lookup keys, request-derived notice flags normalized by existing helpers, WordPress nonce fields, and escaped status labels; no admin-authored HTML, vendor-submitted HTML, JSON response, or HTML email body is admitted into this contract.
- Relevant helper/value inventory: `vms_vendor_app_render_resend_confirmation_form()` owns the resend form fragment; `vms_vendor_apply_render_confirmation_pending_screen()` owns pending/resent/expired confirmation cards; `vms_vendor_apply_render_existing_status_screen()` owns duplicate pending/holding/approved cards; `vms_vendor_app_render_portal_applicant_panel()` owns the Vendor Portal applicant panel; `$content` in `vms_vendor_app_render_confirmation_shell()` is the final public endpoint shell body.
- Complete production caller/sink inventory normalized: confirmation endpoint shell calls in `includes/core/vendor-application-confirmation.php`; shortcode status returns in `includes/vendor-applications.php`; and the applicant panel sink in `includes/portal/vendor-portal.php`.
- Boundary behavior: form and fragment buffers return `wp_kses((string) ob_get_clean(), vms_vendor_app_confirmation_allowed_html())`; embedded resend-form output is wrapped with the same contract; shell `$content` is echoed through `wp_kses($content, vms_vendor_app_confirmation_allowed_html())`; `language_attributes()` now owns the `<html>` attributes instead of concatenating `get_language_attributes()`.
- Defense in depth preserved: existing contextual escaping remains in place for text, attributes, URLs, status labels, nonce fields, document title, and charset; no full theme shell, Vendor Portal shell, shortcode page shell, admin shell, or email body is broadly filtered through the confirmation fragment allowlist.
- Focused coverage and validation: `php -l includes/core/vendor-application-confirmation.php`, `php -l tests/vendor-application-confirmation-output-remediation.php`, `php tests/vendor-application-confirmation-output-remediation.php`, `php tests/vendor-apply-inline-js-remediation.php`, `php tests/vendor-apply-admin-css-remediation.php`, `php tests/vendor-portal-shell-inline-js-remediation.php`, and `php tests/vendor-portal-modal-inline-js-remediation.php` passed.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open because ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output had not yet been completed. `WPORG-24R` later reconciles those residuals and closes the parent without reopening production work.

### `WPORG-24 E1` ADD public shell output-contract result

- Result: the standalone ADD public response shell now applies an explicit named final-output contract only to its inner response fragment before browser output.
- Historical finding inspected: the packaged Plugin Check artifact for `includes/modules/availability-date-dispatch/public.php` included an `OutputNotEscaped` hit for the final shell `$content_html` output at `docs/plugin-check-1.0.0-raw.txt:1767`.
- Production files inspected: `includes/modules/availability-date-dispatch/availability-date-dispatch.php`, `includes/modules/availability-date-dispatch/helpers.php`, `includes/modules/availability-date-dispatch/public.php`, `includes/modules/availability-date-dispatch/email.php`, `docs/plugin-check-1.0.0-raw.txt`, and the directly relevant ADD tests.
- Production file changed: `includes/modules/availability-date-dispatch/public.php`.
- Output types covered: the standalone public availability-response shell emitted from `template_redirect` for missing/closed/expired/requested/recorded response screens; AJAX, JSON, email, admin UI, Event Plans partials, Vendor Portal fragments, and broader theme/page shells were not changed.
- Contract: `vms_add_dispatch_public_response_allowed_html()` permits only `a[class,href]`, `br`, `div[class]`, `h1`, `p[class]`, and `strong`.
- HTML-bearing origins and trust level: the fragment is built in `vms_add_dispatch_render_public_response()` from fixed plugin-generated wrappers, translated copy, escaped event title/date/venue text, escaped request messages rendered through `nl2br(esc_html(...))`, and escaped response URLs from `vms_add_dispatch_build_response_url()`; no administrator-authored HTML, vendor-submitted HTML, AJAX payload, or email body is admitted into this contract.
- Relevant helper/value inventory: `vms_add_dispatch_render_public_response()` builds the response fragment in `$html`; `vms_add_dispatch_public_response_allowed_html()` owns the narrow contract; `$content_html` in `vms_add_dispatch_render_public_shell()` is the final browser sink; `vms_add_dispatch_template_router()` is the only production entry point that reaches that sink.
- Complete production caller/sink inventory normalized: `vms_add_dispatch_render_public_shell()` is called only from `vms_add_dispatch_render_public_response()` for the request-not-found, request-unavailable, request-closed, link-expired, response-recorded, already-recorded, and active-choice screens; there are no shortcode, AJAX, email, or admin callers for this shell in the current production tree.
- Boundary behavior: `vms_add_dispatch_render_public_shell()` now echoes `wp_kses($content_html, vms_add_dispatch_public_response_allowed_html())`; the outer standalone document shell (`<!doctype html>`, `<head>`, inline CSS, wrapper `<div>` structure, and `<body>`) remains direct plugin-generated output and is not passed through the fragment allowlist, so this slice does not filter an entire WordPress page or unrelated shell.
- Focused coverage and validation: `php -l includes/modules/availability-date-dispatch/public.php`, `php -l tests/add-dispatch-public-shell-output-remediation.php`, `php tests/add-dispatch-public-shell-output-remediation.php`, `php tests/event-plan-secondary-vendor-capacity-and-add.php`, `php tests/add-dispatch-pill-output-remediation.php`, and `git diff --check` passed. `php tests/add-dispatch-open-vendor-needs.php` still has the known unrelated `WPORG-27` missing-primary ADD visibility failure, and `php tests/add-dispatch-assignment-review.php` remains blocked in this local harness because `wp-load.php` could not be located.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open because Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output had not yet been completed. `WPORG-24R` later reconciles those residuals and closes the parent without reopening production work.

### `WPORG-24 E1` Administrator shell explicit status-notice output-contract result

- Result: the Status Notices module fragment buffered into Administrator shell `$explicit_notices_html` now uses a dedicated final-output contract at the explicit notice sink in `vms_admin_ui_render_shell()`.
- Prior gate-failure context retained: the complete Administrator shell remains unsuitable for one shared contract because `$actions_html`, `$captured_notices_html`, and `$content_html` still aggregate materially different admin fragments, including help-tour controls, extracted callback notices, Settings API/editor output, inline scripts, and application/json script blocks. This sub-slice leaves those values untouched.
- Historical finding inspected: the packaged Plugin Check artifact for `includes/admin-ui/shell.php` reported `OutputNotEscaped` hits for `$actions_html`, `$explicit_notices_html`, `$captured_notices_html`, and `$content_html` at `docs/plugin-check-1.0.0-raw.txt:2906-2909`.
- Production files inspected: `includes/admin-ui/shell.php` and `includes/modules/status-notices/admin-ui.php`.
- Production file changed: `includes/admin-ui/shell.php`.
- Complete callback inventory: only `vms_status_notice_render_list_screen()` and `vms_status_notice_render_edit_screen()` supply `notices_callback`, and both pass the same fragment owner `vms_status_notice_notice_bar()`.
- Fragment origin and trust level: `vms_status_notice_notice_bar()` emits one fixed plugin-generated success notice fragment, `<div class="notice notice-success is-dismissible"><p>…</p></div>`. Dynamic text enters only through the local status/result mapping and the `bulk_count` integer path; the callback sanitizes the status key with `sanitize_key()`, bounds the count with `absint()`, uses fixed translated strings, and escapes the final message with `esc_html()`. The callback accepts no arguments, echoes output only, its return value is ignored by the shell buffer, and no hooks or filters inject additional markup into this explicit fragment.
- Shell buffering and transformations: `vms_admin_ui_render_shell()` reads `notices_callback` from the args array, buffers it with `ob_start()`, `call_user_func($notices_callback)`, and `ob_get_clean()`, then passes the result through the existing `vms_admin_ui_prepare_notice_markup()` class-normalization step before the final sink.
- Contract: `vms_admin_ui_explicit_notice_allowed_html()` permits only `div[class]` and `p`.
- Boundary behavior: the final explicit notice sink now echoes `wp_kses($explicit_notices_html, vms_admin_ui_explicit_notice_allowed_html())` inside the existing `.vms-admin-shell__notices` wrapper. `$actions_html`, `$captured_notices_html`, and `$content_html` remain direct existing sinks, and no complete admin shell, page callback, or broader notice container is filtered through this contract.
- Defense in depth preserved: `vms_status_notice_notice_bar()` remains unchanged and keeps its existing `esc_html()` protection plus literal translated strings. No Status Notices wording, classes, screen routing, callback execution, form behavior, or shell layout changed.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php`; `php -l includes/admin-ui/shell.php`; `php -l tests/administrator-explicit-notice-output-remediation.php`; `php tests/administrator-explicit-notice-output-remediation.php`; `php tests/admin-notice-scope-remediation.php`; `php tests/portal-notice-sink-remediation.php`; `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Remaining Administrator shell work was then limited to header action fragments, captured notice fragments, and full page content, each requiring their own narrower inventories rather than a shell-wide allowlist. Broader remaining `WPORG-24` boundaries still included Event Plans partial/AJAX HTML and Pass-claims output until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Administrator shell header-action output-contract result

- Result: the Administrator shell `$actions_html` fragment now uses a dedicated final-output contract at the header-action sink in `vms_admin_ui_render_shell()`.
- Historical finding inspected: the packaged Plugin Check artifact for `includes/admin-ui/shell.php` reported `OutputNotEscaped` hits for `$actions_html`, `$explicit_notices_html`, `$captured_notices_html`, and `$content_html` at `docs/plugin-check-1.0.0-raw.txt:2438-2443`.
- Production files inspected for the caller inventory: `includes/admin-ui/shell.php`, `includes/bootstrap.php`, `includes/tours/tours.php`, `includes/core/tours/class-vms-tours.php`, `includes/admin/schedule.php`, `includes/admin/integrity-calendar-reconcile.php`, `includes/admin/integrity-venue-reconcile.php`, `includes/modules/status-notices/admin-ui.php`, `includes/admin/event-command-center.php`, `includes/modules/availability-date-dispatch/admin-ui.php`, `includes/admin/vendor-command-center.php`, `includes/admin/vendor-availability.php`, `includes/admin/ticket-integrity-page.php`, and `includes/safety/admin.php`.
- Production file changed: `includes/admin-ui/shell.php`.
- Complete current caller inventory for `'actions_html' => ...`: `includes/admin/schedule.php`; `includes/admin/integrity-calendar-reconcile.php`; `includes/admin/integrity-venue-reconcile.php`; `includes/modules/status-notices/admin-ui.php`; `includes/admin/event-command-center.php`; `includes/modules/availability-date-dispatch/admin-ui.php`; `includes/admin/vendor-command-center.php`; `includes/admin/vendor-availability.php`; `includes/admin/ticket-integrity-page.php`; `includes/safety/admin.php`.
- Exact action families discovered:
  - Empty/no-action value: the shell default and the Event Command Center / Safety Toolkit paths when their conditional help or plan links are absent.
  - Plain anchor actions: one or two concatenated `<a>` elements with `class` and `href`, emitted by Schedule, both Integrity pages, Status Notices, and Event Command Center.
  - Guided-tour button actions: one `<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="..." data-vms-tour="...">...</button>` fragment emitted directly or via `vms_render_help_button()` for Add Dispatch, Vendor Command Center, Vendor Availability, Ticket Integrity, and Safety Toolkit.
  - Guided-tour wrappers: fixed plugin `<div>` wrappers with `class`, and for Ticket Integrity also `data-vms-tour`, around the guided-tour button fragment.
- Origin and trust level: every discovered shell action fragment is fixed plugin markup assembled in code. Dynamic values entering the sink are limited to admin URLs, `get_edit_post_link()` results, translated labels, and fixed tour IDs / anchors / classes; existing fragment builders retain `esc_url()`, `esc_html__()`, `sanitize_text_field()`, `sanitize_html_class()`, `sanitize_key()`, and anchor-token sanitization. No editor HTML, Settings API HTML, callback output, hook output, or third-party markup enters `$actions_html`.
- Help-button implementations inspected:
  - Canonical bootstrap implementation: `includes/tours/tours.php` defines `vms_render_help_button()` behind `if (!function_exists(...))` and returns only the single guided-tour `<button>` shape used by the shell action callers.
  - Legacy shipped implementation: `includes/core/tours/class-vms-tours.php` also defines `vms_render_help_button()` behind the same guard but returns a materially different `details` / `summary` / panel markup family with inline `style` and `data-vms-help-action`.
  - Bootstrap resolution: `includes/bootstrap.php` requires `includes/tours/tours.php` and does not require `includes/core/tours/class-vms-tours.php`, so both definitions cannot coexist in the same canonical request and the shared tours implementation wins for current Administrator shell callers. The legacy `details` / `summary` help-menu markup remains shipped code, but it is not part of the current bootstrapped shell action family and is therefore excluded from this contract.
- Contract: `vms_admin_ui_header_actions_allowed_html()` in `includes/admin-ui/shell.php`.
- Exact allowed elements and attributes:
  - `a[class|href]`
  - `button[class|type|data-vms-tour|data-vms-tour-start]`
  - `div[class|data-vms-tour]`
- Boundary behavior: the final header-action sink now echoes `wp_kses($actions_html, vms_admin_ui_header_actions_allowed_html())` inside the existing `.vms-admin-shell__actions` wrapper. Only `$actions_html` is newly filtered; `$explicit_notices_html` remains contracted exactly as before, and `$captured_notices_html` plus `$content_html` remain untouched existing sinks.
- Scope confirmation: no complete WordPress admin page, no shell wrapper, and no caller-specific page body is filtered through this contract. No Event Plans partial/AJAX output or Pass-claims output was touched.
- Focused coverage: `tests/administrator-explicit-notice-output-remediation.php` now also proves the header-action allowlist, canonical tours bootstrap assumption, current `actions_html` caller inventory, allowed anchor/button/wrapper fragments, rejection of `style`, unsafe URLs, unapproved `data-*`, event handlers, and the unloaded legacy `details` / `summary` help-menu markup.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Remaining Administrator shell work still included captured notice fragments, which required a page-family inventory, and full page content, which required per-screen remediation. Broader remaining `WPORG-24` boundaries still included Event Plans partial/AJAX HTML and Pass-claims output until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Continuity Binder captured-notice reduction result

- Result: the Continuity Binder screen now routes its simple update-success notice through the existing Administrator shell explicit-notice path instead of leaving that one fragment to be extracted into shared `$captured_notices_html`.
- Prior gate-failure context retained: unknown and extensible captured sources still prevent one shared shell-wide contract for `$captured_notices_html`, including richer notice families, extra-attribute families, Data Tools companion output, Safety tab action output, Settings API callbacks, and full `$content_html`. This sub-slice only reduces one package-owned simple family at the source.
- Production files inspected: `includes/admin/continuity-binder.php`, `includes/admin-ui/shell.php`, `tests/administrator-explicit-notice-output-remediation.php`, and the surrounding `WPORG-24` tracker notes.
- Production file changed: `includes/admin/continuity-binder.php`.
- Original captured-notice path: `vms_render_continuity_binder_page_content()` emitted two top-level notice containers before the rest of the page body. The first was the simple success notice `<div class="notice notice-success is-dismissible"><p>Binder updated.</p></div>` gated by exact request state `isset($_GET['updated']) && $_GET['updated'] === '1'`; the second remains the richer security warning notice `<div class="notice notice-warning"><p><strong>Security note:</strong> …</p></div>`. Both were previously eligible for shell extraction, but only the success notice matches the existing explicit contract.
- New explicit-notice path: `vms_render_continuity_binder_page()` now supplies `'notices_callback' => 'vms_continuity_binder_render_updated_notice'`, and the new helper `vms_continuity_binder_render_updated_notice()` echoes only the unchanged success fragment when the exact `updated=1` flag is present. The helper accepts no arguments, adds no hooks or indirection, and reuses the shell’s existing `wp_kses($explicit_notices_html, vms_admin_ui_explicit_notice_allowed_html())` boundary.
- Dynamic values and trust level: the routed notice has no user-controlled body content. Its display condition depends only on the exact fixed query flag `updated=1`, and its text remains a fixed translated string escaped with `esc_html__()`. The remaining warning notice also remains package-owned and escaped, but because it contains `<strong>` it stays outside the explicit `div[class]` + `p` contract and remains part of unresolved captured-notice work.
- Contract and scope confirmation: `vms_admin_ui_explicit_notice_allowed_html()` remains exactly `div[class]` and `p`; `$actions_html` remains under its existing header-actions contract; the shared raw `$captured_notices_html` sink remains unresolved and untouched; `$content_html` remains unresolved and untouched; no shell extraction/preparation helper changed; no unrelated screen, Event Plans partial/AJAX file, or Pass-claims file was touched.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Continuity Binder `notices_callback` wiring, the dedicated helper owner, removal of the original success notice from the content callback, the absence of duplication, preservation of the fixed success fragment and exact display condition, and the continued presence of the richer warning notice outside the explicit contract. Validation ran with `php -l includes/admin/continuity-binder.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Remaining Administrator shell captured-notice work still included other package-owned simple families, rich linked and emphasis notice families, extra-attribute notice families, unknown/extensible captured sources, and full `$content_html`, which still required per-screen remediation. Broader remaining `WPORG-24` boundaries still included Event Plans partial/AJAX HTML and Pass-claims output until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Due Dates captured-notice reduction result

- Result: the Due Dates screen now routes its simple shell message notice family through the existing Administrator shell explicit-notice path instead of leaving those fragments to be extracted into shared `$captured_notices_html`.
- Prior gate-failure context retained: the Administrator shell still cannot use one shared captured-notice contract because richer notice families, extra-attribute families, unknown/extensible captured sources, and full `$content_html` remain unresolved. This slice only reduces the package-owned simple Due Dates family at the source.
- Production files inspected: `includes/admin/due-dates.php`, `includes/admin-ui/shell.php`, `tests/administrator-explicit-notice-output-remediation.php`, and the surrounding `WPORG-24` tracker notes.
- Production file changed: `includes/admin/due-dates.php`.
- Exact Due Dates notice inventory:
  - Eligible simple family: one top-level `<div class="notice notice-success|notice-error is-dismissible"><p>…</p></div>` fragment driven by non-empty `vms_due_msg`. Severity remains `notice-error` when the sanitized slug contains `error`; otherwise it remains `notice-success`. The body remains normalized slug text via underscore-to-space replacement.
  - Remaining captured notices: none on this screen. No richer `div.notice` fragments, links, emphasis tags, buttons, IDs, `data-*`, Settings API notices, or third-party notice callbacks were found in `includes/admin/due-dates.php`.
- Original captured-notice path: `vms_render_due_dates_admin_page_content()` read `vms_due_msg` through `vms_due_admin_query_arg()`, sanitized it with `sanitize_key()`, mapped severity from `strpos($msg, 'error') !== false`, and echoed the completed dismissible notice before the description paragraph. Because it was a top-level `div.notice`, the shell extracted it into `$captured_notices_html`.
- New explicit-notice path: `vms_render_due_dates_admin_page()` now supplies `'notices_callback' => 'vms_due_render_admin_notices'`, and the new helper `vms_due_render_admin_notices()` echoes the unchanged notice family through the shell’s existing `wp_kses($explicit_notices_html, vms_admin_ui_explicit_notice_allowed_html())` boundary. The content callback no longer emits the migrated notice blocks.
- Conditions and dynamic-value origins: the notice is controlled entirely by the `vms_due_msg` query parameter. All current producers route through `vms_due_admin_redirect()`, which already normalizes explicit message slugs with `sanitize_key()`. The explicit notice helper reuses `vms_due_admin_query_arg('vms_due_msg')`, sanitizes again with `sanitize_key()`, and escapes the final normalized text with `esc_html()`. No user-controlled HTML is emitted.
- Contract and scope confirmation: `vms_admin_ui_explicit_notice_allowed_html()` remains exactly `div[class]` and `p`; `$actions_html` remains under its existing header-actions contract; the shared raw `$captured_notices_html` sink remains unresolved and untouched; `$content_html` remains unresolved and untouched; no shell extraction/preparation helper changed; Continuity Binder and Status Notices routing remain intact; no unrelated screen, Event Plans partial/AJAX file, or Pass-claims file was touched.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Due Dates `notices_callback` wiring, the dedicated helper owner, the updated production callback inventory, removal of the original simple notices from the content callback, preservation of the success/error class mapping and normalized message text, continued shell invariants for Status Notices and Continuity Binder, and the absence of markup beyond `div[class]` and `p`. Validation ran with `php -l includes/admin/due-dates.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Remaining captured-notice work still included other package-owned simple notices, rich linked and emphasis notices, extra-attribute notices, and unknown/extensible sources. Remaining Administrator shell work still included source-by-source captured-notice reduction and per-screen remediation of full `$content_html`. Broader remaining `WPORG-24` boundaries still included Event Plans partial/AJAX HTML and Pass-claims output until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Square Sync Protection captured-notice reduction result

- Result: the Square Sync Protection screen now routes its simple scan/repair completion notice family through the existing Administrator shell explicit-notice path instead of leaving those fragments to be extracted into shared `$captured_notices_html`.
- Candidate inspection summary: both `includes/admin/square-sync-protection.php` and `includes/admin/staff-certifications.php` were inspected before editing. Square Sync Protection was selected because it had the smallest proven page-local boundary: one file owns the page registration, redirect helper, query-arg notice source, shell render call, and the exact two-branch simple notice family. Staff Certifications was deferred because the same file also owns a separate richer `admin_notices` warning family with `<strong>` and an `<a>` link, while the page-local empty-state notice is tied to a second data fetch / empty-state branch rather than a fixed redirect slug.
- Production files inspected: `includes/admin/square-sync-protection.php`, `includes/admin/staff-certifications.php`, `includes/admin/menu.php`, `includes/admin-ui/shell.php`, `tests/administrator-explicit-notice-output-remediation.php`, and the surrounding `WPORG-24` tracker notes.
- Production file changed: `includes/admin/square-sync-protection.php`.
- Exact Square Sync Protection notice inventory:
  - Eligible simple family: two top-level fragments, `<div class="notice notice-info"><p>Square Sync Protection scan complete.</p></div>` and `<div class="notice notice-success"><p>Square Sync Protection repair complete.</p></div>`, chosen solely from the sanitized `vms_square_notice` query slug values `scan_done` and `repair_done`.
  - Remaining captured notices on the selected page: none. No links, spans, emphasis tags, buttons, lists, IDs, `data-*`, inline styles, or third-party notice callbacks were found in that notice family.
  - Unselected candidate disposition: Staff Certifications remained unmigrated in that pass. Its page-local empty-state `<div class="notice notice-success inline"><p>…</p></div>` still existed in the content callback, and its richer off-page `admin_notices` warning family remained a separate later boundary. Later dedicated `WPORG-24K` work closes that global pending-review warning without changing shell contracts.
- Original captured-notice path: `vms_render_square_sync_protection_page_content()` read `$_GET['vms_square_notice']`, sanitized it with `sanitize_key()`, and echoed the scan/repair completion fragment before the card content. Because each fragment was a top-level `div.notice`, the shell extracted it into `$captured_notices_html`.
- New explicit-notice path: `vms_render_square_sync_protection_page()` now supplies `'notices_callback' => 'vms_square_sync_protection_render_admin_notice'`, and the new helper `vms_square_sync_protection_render_admin_notice()` echoes the unchanged scan/repair notice family through the shell’s existing `wp_kses($explicit_notices_html, vms_admin_ui_explicit_notice_allowed_html())` boundary. The content callback no longer emits those notice blocks.
- Conditions and dynamic-value origins: the notice is controlled entirely by the page-local `vms_square_notice` query parameter. Current producers are the same page-local admin-post handlers, which still redirect through `vms_square_sync_protection_redirect()` and normalize the outgoing slug with `sanitize_key()`. The explicit notice helper preserves that source by sanitizing the inbound query arg again and escaping the fixed message text with `esc_html__()`.
- Contract and scope confirmation: `vms_admin_ui_explicit_notice_allowed_html()` remains exactly `div[class]` and `p`; no shell allowlist or raw sink was broadened; shared `$captured_notices_html` remains raw and unresolved; shared `$content_html` remains raw and unresolved; Staff Certifications remained deferred in that slice and later returned as dedicated `WPORG-24K` work; Pass Claims remains a separate `WPORG-24` boundary; no Event Plans partial/AJAX file was touched.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Square Sync Protection `notices_callback` wiring, the dedicated helper owner, removal of the original simple notices from the content callback, preservation of the exact info/success fragments, continued callback inventory limits, and the then-explicit deferral of Staff Certifications that later closed under `WPORG-24K`. Validation ran with `php -l includes/admin/square-sync-protection.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Staff Certifications empty-state notice reduction result

- Result: the Staff Certifications page-local empty-state notice now routes through the existing Administrator shell explicit-notice path instead of being extracted into shared `$captured_notices_html`.
- Target boundary: only the page-local empty-state fragment in `includes/admin/staff-certifications.php` moved in this slice. The separate richer linked `admin_notices` warning family in the same file remained untouched in that pass and later closed under `WPORG-24K`.
- Production files inspected: `includes/admin/staff-certifications.php`, `includes/admin/menu.php`, `includes/admin-ui/shell.php`, `tests/administrator-explicit-notice-output-remediation.php`, and the surrounding `WPORG-24` tracker notes.
- Production file changed: `includes/admin/staff-certifications.php`.
- Exact Staff Certifications empty-state inventory:
  - Eligible simple family: `<div class="notice notice-success inline"><p>No staff certifications are waiting for review.</p></div>` when the resolved pending-review dataset is empty.
  - Separate rich family in that slice: the file still hooked `admin_notices` and emitted `<div class="notice notice-warning is-dismissible vms-staff-certifications-admin-notice"><p><strong>…</strong> <a …>…</a></p></div>` outside the page screen. That linked/emphasis family stayed outside the explicit contract and was not moved until later dedicated `WPORG-24K` work.
- Shared dataset mechanism: `vms_render_staff_certifications_admin_page()` now resolves the pending-review dataset exactly once through `vms_staff_certifications_get_pending_review_items()`, then passes the same resolved `$pending` array into both a page-local `notices_callback` closure and the content-rendering closure. No global state, mutable static state, transients, options, or query changes were added.
- Original captured-notice path: `vms_render_staff_certifications_admin_page_content()` previously loaded `vms_staffing_get_staff_qualification_review_items('pending_verification')`, rendered the summary card, then echoed the inline success notice and returned when `empty($pending)` was true. Because the notice was a top-level `div.notice`, the shell extracted it into `$captured_notices_html`.
- New explicit-notice path: the same page renderer now supplies a page-local notice closure through `notices_callback`, which delegates to `vms_staff_certifications_render_empty_state_notice($pending)`. The content renderer receives the same resolved dataset and no longer emits the empty-state notice.
- Conditions and escaping preserved: the notice still depends only on `empty($pending)`, still uses `esc_html__('No staff certifications are waiting for review.', 'backstage-venue-manager')`, still keeps `notice-success inline`, still remains non-dismissible, and still emits only `div[class] > p`.
- Contract and scope confirmation: no explicit-notice contract expansion occurred; shared `$captured_notices_html` remains raw and unresolved; shared `$content_html` remains raw and unresolved; the richer linked `admin_notices` warning family remains untouched; Pass Claims remains a separate `WPORG-24` boundary.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Staff Certifications shell wiring, exact inline notice fragment, single-query page render behavior for empty and nonempty datasets, removal of the original content-path emission, unchanged nonempty content rendering, the then-unchanged rich warning family source later closed by `WPORG-24K`, unchanged explicit-notice contract, and unchanged raw shell sinks. Validation ran with `php -l includes/admin/staff-certifications.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Social Sharing redirect notice reduction result

- Result: the Social Sharing redirect notice family now routes through the existing Administrator shell explicit-notice path instead of being extracted into shared `$captured_notices_html`.
- Constrained discovery scope: this pass statically searched only `vms_admin_ui_render_shell()` Administrator pages for top-level notice fragments and found plausible notice-bearing shell files in `includes/safety/admin.php`, `includes/admin/event-command-center.php`, `includes/admin-ui/nav.php`, `includes/admin/integrity-venue-reconcile.php`, `includes/admin/due-dates.php`, `includes/admin/settings-page.php`, `includes/admin/integrity-calendar-reconcile.php`, `includes/tours/class-vms-tours-admin.php`, `includes/admin/staff-certifications.php`, `includes/social-share/admin.php`, `includes/admin/continuity-binder.php`, `includes/admin/data-tools/page-event-plan-import.php`, `includes/admin/event-feedback.php`, `includes/admin/ticket-integrity-page.php`, `includes/admin/square-sync-protection.php`, `includes/admin/schedule.php`, `includes/admin/vendor-availability.php`, `includes/modules/status-notices/admin-ui.php`, `includes/modules/email-followups/admin-ui.php`, and `includes/modules/admissions/pass-claims.php`.
- Immediate exclusions retained: already migrated screens were not reconsidered; `includes/admin/integrity-venue-reconcile.php` was excluded without deeper inspection because its captured notices include `<strong>` markup inside the notice paragraph; `includes/tours/class-vms-tours-admin.php` was excluded without deeper inspection because its notice outer `div` adds `data-vms-tour`, which is outside the current explicit-notice contract; Pass Claims remained out of scope.
- Inspected candidate files: `includes/admin/data-tools/page-event-plan-import.php`, `includes/modules/email-followups/admin-ui.php`, and `includes/social-share/admin.php`.
- Production file changed: `includes/social-share/admin.php`.
- Exact Social Sharing notice inventory:
  - Eligible simple family: `vms_social_render_notices()` reads the page-local `vms_social_notice` and `vms_social_notice_type` query args, sanitizes them with `sanitize_text_field()` and `sanitize_key()`, constrains severity to `error`, `warning`, `success`, or `info`, then emits exactly `<div class="notice notice-... is-dismissible"><p>...</p></div>`.
  - Original captured-notice path: `vms_social_render_admin_page_content()` previously called `vms_social_render_notices()` ahead of the tab navigation and panel content, so the top-level notice fragment was captured into the shell’s raw `$captured_notices_html`.
  - New explicit-notice path: `vms_social_render_admin_page()` now passes `'notices_callback' => 'vms_social_render_notices'` into `vms_admin_ui_render_shell()`, and the no-shell fallback still renders the notice immediately after the page `<h1>` and before ordinary page content.
- Candidate selection rationale: Social Sharing was the smallest fully proven boundary among the three inspected candidates because one package-owned file contains the menu registration, redirect helper, query-arg notice source, shell render call, no-shell fallback, and the complete eligible notice family without shared-data plumbing, extra attributes, or rich child markup.
- Deferred inspected candidates:
  - `includes/admin/data-tools/page-event-plan-import.php` stayed unmigrated because the file mixes a redirect-populated notice payload from `vms_event_plan_import_pop_notice()` with a second inline rows-payload error family, and the main notice state is owned jointly with `includes/admin/data-tools/actions-event-plan-import.php` rather than by one isolated page-local helper.
  - `includes/modules/email-followups/admin-ui.php` stayed unmigrated because the file owns both a redirect-driven notice helper and a second inline preview warning family (`No Event Plans found for preview/testing.`), so it was not the smallest clean page-local family after Social Sharing qualified.
- Contract and scope confirmation: `vms_admin_ui_explicit_notice_allowed_html()` remains exactly `div[class]` and `p`; no explicit-notice or shell-wide captured-notice allowlist was broadened; shared raw `$captured_notices_html` remains unresolved and untouched; shared raw `$content_html` remains unresolved and untouched; Event Plan Import and Email Follow-Ups remain unmigrated; Pass Claims remains a separate `WPORG-24` boundary.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Social Sharing shell wiring, the dedicated explicit notice helper, sanitized request input handling, severity fallback to `success` for unknown types, removal of the original content-path emission, continued no-shell ordering, unchanged explicit-notice contract, and the explicit non-migration of Event Plan Import and Email Follow-Ups. Validation ran with `php -l includes/social-share/admin.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, the deferred Event Plan Import and Email Follow-Ups families, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Email Follow-Ups primary notice reduction result

- Result: the primary Email Follow-Ups redirect-notice helper now routes through the existing Administrator shell explicit-notice path instead of being emitted from the page render closure and extracted into shared `$captured_notices_html`.
- Constrained production inspection scope: this pass inspected only `includes/modules/email-followups/admin-ui.php` for the Email Follow-Ups Administrator screen and did not inspect or migrate any fallback candidate.
- Production file changed: `includes/modules/email-followups/admin-ui.php`.
- Exact submenu and render boundary:
  - The file registers `add_submenu_page('vms-dashboard', __('Email Follow-Ups', 'backstage-venue-manager'), __('Email Follow-Ups', 'backstage-venue-manager'), 'manage_options', vms_email_followups_admin_slug(), 'vms_email_followups_render_admin_page')`.
  - `vms_email_followups_render_admin_page()` resolves the current tab once, builds a page-local `$render` closure for the tab navigation and content, and now passes `vms_email_followups_render_notices` through the shell `notices_callback`.
- Exact primary notice family:
  - `vms_email_followups_render_notices()` reads `$_GET['vms_efu_notice']` through `sanitize_text_field(wp_unslash((string) $_GET['vms_efu_notice']))`.
  - It reads `$_GET['vms_efu_notice_type']` through `sanitize_key((string) $_GET['vms_efu_notice_type'])`.
  - Allowed severities remain exactly `success`, `error`, `warning`, and `info`; unknown values still fall back to `success`.
  - Output remains exactly `<div class="notice notice-... is-dismissible"><p>...</p></div>` with `esc_attr($type)` on the class fragment and `esc_html($notice)` for the text.
- Message and condition sources:
  - The helper emits nothing when the sanitized `vms_efu_notice` value is empty.
  - Non-empty notice text continues to come from page-local redirect callers in the same file, including fixed messages such as `Email follow-up settings saved.`, `Manual send was not confirmed, so no recipient emails were sent.`, `The saved send batch expired or no longer matches this event/template. No emails were sent.`, `No recipients were selected, so no emails were sent.`, `Email follow-up logs cleared.`, plus existing dynamic result messages already produced by the page-local send/preview flows.
- Ordering preserved:
  - The shell path now supplies `'notices_callback' => 'vms_email_followups_render_notices'`.
  - The original in-closure call was removed so the primary helper executes once.
  - The no-shell fallback still renders the page `<h1>`, then `vms_email_followups_render_notices()`, then the existing `$render()` closure, preserving notice-before-content ordering.
- Later follow-up boundary note: this slice intentionally left the preview empty-state warning separate. That boundary was addressed in the subsequent Email Follow-Ups preview-warning slice below.
- Contract and scope confirmation: the explicit-notice contract remains exactly `div[class]` and `p`; no allowlist was broadened; shared raw `$captured_notices_html` remains unresolved and untouched; shared raw `$content_html` remains unresolved and untouched; the preview warning remains separate; Pass Claims remains a separate `WPORG-24` boundary.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Email Follow-Ups shell wiring, the dedicated helper source, every preserved severity branch plus unknown-type fallback, sanitized request handling, single-render ordering for the shell path, preserved no-shell source ordering, unchanged provider call counts for the overview render, unchanged preview-warning source and markup, unchanged explicit-notice contract, and unchanged raw shell sinks. Validation ran with `php -l includes/modules/email-followups/admin-ui.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, any discovered Email Follow-Ups-specific test inventory search, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, the separate Email Follow-Ups preview warning, Event Plan Import, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Email Follow-Ups preview warning reduction result

- Result: the Email Follow-Ups preview empty-state warning now routes through the existing Administrator shell explicit-notice path instead of being source-rendered in preview content and then hoisted by shared captured-notice extraction.
- Constrained production inspection scope: this pass inspected only `includes/modules/email-followups/admin-ui.php` for the already-identified preview warning boundary and did not inspect or migrate any fallback candidate.
- Production file changed: `includes/modules/email-followups/admin-ui.php`.
- Exact preview boundary:
  - The active tab still resolves through `vms_email_followups_current_tab()` with accepted values `overview`, `templates`, `preview`, and `logs`, defaulting to `overview`.
  - The page renderer now resolves preview state only when `$tab === 'preview'`, then shares that same state with both the explicit notice path and the preview content path.
  - The preview empty-state condition remains exactly `event_plan_id <= 0`.
  - The warning fragment remains exactly `<div class="notice notice-warning inline"><p>No Event Plans found for preview/testing.</p></div>`.
- Shared preview-state resolution:
  - A page-local preview-state resolver now sanitizes `$_GET['event_plan_id']` with `absint()`, resolves preview event choices once through `vms_email_followups_event_choices(120, $selected_event_plan_id)` or the existing fallback helper, derives the default selected plan from that same choice list, sanitizes `$_GET['email_key']` with `sanitize_key()`, and validates it against the existing template definitions before both the notice path and the preview content use it.
  - No globals, mutable statics, transients, options, or persistent request caches were introduced for this sharing.
- Ordering preserved:
  - The page now uses one page-specific notice composer that first calls the already accepted `vms_email_followups_render_notices()` helper and then emits the preview warning only when the preview tab is active and the shared preview state is empty.
  - The shell path therefore preserves the effective final order as primary redirect notices first, preview warning second, then tabs and ordinary content.
  - The no-shell fallback still renders the page heading, then the same composed notice output, then tabs/content.
- Original content-path warning removed:
  - `vms_email_followups_render_preview_tab()` no longer echoes the preview warning itself.
  - The preview tab still renders the same filter form and then returns early for the empty state after the form markup, while the explicit notice path now owns the warning fragment so it is no longer captured from raw content.
- Lazy behavior and provider counts:
  - Preview-only resolution remains limited to the preview tab.
  - The shared preview-state resolver avoids duplicate choice and template-definition work by feeding the same resolved event choices, selected plan, and template definitions into both the notice path and the preview content path.
  - Non-preview tabs do not invoke preview-only event-choice or template-definition providers through this change.
- Contract and scope confirmation: no explicit-notice contract expansion occurred; the explicit-notice sink remains limited to `div[class]` and `p`; shared raw `$captured_notices_html` remains unresolved and untouched; shared raw `$content_html` remains unresolved and untouched; the already migrated primary redirect notices remain behaviorally unchanged; Pass Claims remains a separate `WPORG-24` boundary.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves preview-only visibility, exact warning markup, redirect-notice-before-preview-warning composition, explicit-notice-before-tabs ordering, no-shell source ordering, exact provider-call counts for empty and nonempty preview renders, non-preview laziness, preserved malformed/unknown selection fallback behavior, unchanged nonempty preview rendering, unchanged explicit-notice contract, and unchanged raw shell sinks. Validation ran with `php -l includes/modules/email-followups/admin-ui.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, the Email Follow-Ups-specific test inventory search, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, Event Plan Import, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Event Plan Import primary notice reduction result

- Result: the Event Plan Import Administrator screen now routes its primary popped/flash notice family through the existing Administrator shell explicit-notice path instead of emitting it inside page content and relying on shared captured-notice extraction.
- Constrained production inspection scope: this pass inspected only `includes/admin/data-tools/page-event-plan-import.php`, `includes/admin/data-tools/actions-event-plan-import.php`, and the exact notice-storage helper definitions in `includes/services/event-plan-import/event-plan-import-engine.php` for the Event Plan Import Administrator screen. No fallback screen was inspected or migrated.
- Production files changed:
  - `includes/admin/data-tools/page-event-plan-import.php`
- Exact page registration and shell boundary:
  - The screen still registers `add_submenu_page(null, __('Import Event Plans (CSV)', 'backstage-venue-manager'), __('Import Event Plans (CSV)', 'backstage-venue-manager'), 'manage_options', 'vms-import-event-plans', 'vms_event_plan_import_render_admin_page')`, so it remains a hidden submenu page under a `null` parent slug with `manage_options` capability and `vms_event_plan_import_render_admin_page()` as the renderer.
  - The shell path still calls `vms_admin_ui_render_shell()` with the same title `Import Event Plans (CSV)` and subtitle `Preview then commit VMS-only Event Plan updates.`. This pass adds only one page-local `notices_callback` that renders the already-popped primary notice value.
- Primary notice storage and destructive-pop lifecycle:
  - `vms_event_plan_import_set_notice(string $type, string $message)` still writes one transient keyed by `vms_event_plan_import_notice_transient_key((int) get_current_user_id())`, which resolves to `vms_epcsv_notice_{user_id}`. The store is per-user and uses the existing 10-minute transient lifetime.
  - Before storage, the type still passes through `sanitize_key()` and the message still passes through `sanitize_text_field()`, so the stored payload contract remains text-only `array('type' => ..., 'message' => ...)`.
  - `vms_event_plan_import_pop_notice(): array` still performs a destructive pop by reading that transient with `get_transient($key)`, deleting it with `delete_transient($key)`, and returning the array payload or `array()` when the store is empty or malformed.
  - This pass preserves that destructive behavior but moves the pop to one page-local render variable that is shared safely between the explicit notice path and the no-shell fallback path without a second pop.
- Primary notice-producing action paths remain unchanged:
  - Preview action writes errors for invalid upload, validation failure, generated-path failure, storage failure, and preview-build/provider failure, plus one translated success summary `Preview ready. Create: %1$d, Update: %2$d, Skip: %3$d, Errors: %4$d.`.
  - Commit action writes errors for missing preview token, expired/missing preview, empty selected-row submission, and commit/provider failure, plus one translated success summary `Import committed. Create: %1$d, Update: %2$d, Skip: %3$d, Errors: %4$d.` with the existing optional appended summary fragments for template-not-applied and selected-row counts.
  - Revert-last action writes one provider error branch and one translated success summary `Revert complete. Restored: %1$d, Failed: %2$d.`.
  - Provider and exception-style messages still flow only through existing `$validated->get_error_message()`, `$preview->get_error_message()`, and `$result->get_error_message()` calls before `sanitize_text_field()` stores them as plain text.
- Primary notice output contract preserved:
  - Severity mapping remains exactly `error|critical => notice notice-error`, `warning => notice notice-warning`, `info => notice notice-info`, and unknown/other values => `notice notice-success`.
  - Output remains exactly `<div class="... inline"><p>TEXT ONLY</p></div>` with `esc_attr(vms_event_plan_import_notice_class($type))` on the class string and `esc_html($message)` for the text.
  - The notice remains inline and non-dismissible; no HTML, links, emphasis, spans, lists, buttons, or extensible markup were introduced.
- Ordering preserved:
  - The page now pops the notice once after the capability gate succeeds, creates one page-local explicit notice callback from that resolved value, and passes it into the shell path.
  - Final shell order remains explicit primary notice first, then the introductory paragraph and ordinary content, with any separate content-local rows-payload error remaining in its existing nested Preview Results position.
  - The no-shell fallback now retains the historical ordering of page heading, introductory paragraph, primary notice, and then the remaining import form and content.
- Separate inline rows-payload error remains unchanged and unresolved:
  - The content callback still emits the separate rows-payload error only when `vms_event_plan_import_read_rows_json(...)` returns `WP_Error`, using the exact fragment `<div class="notice notice-error inline"><p>...</p></div>` with `esc_html($rows_payload->get_error_message())`.
  - That error still depends on content-local preview state and rows-payload validation, remains outside the primary popped-notice helper, and remains nested inside the Preview Results content section rather than being routed through `notices_callback`.
- Contract and scope confirmation: the explicit-notice contract remains exactly `div[class]` and `p`; no shell-wide captured-notice allowlist was added; shared raw `$captured_notices_html` remains unresolved and untouched; shared raw `$content_html` remains unresolved and untouched; Pass Claims remains a separate `WPORG-24` boundary.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Event Plan Import shell wiring, dedicated explicit notice renderer, destructive transient pop behavior, type/message sanitization, severity mapping including unknown-type fallback, capability-gate protection against premature notice consumption, single-pop shell rendering, preserved no-shell source ordering, unchanged action-handler notice-writing paths, and unchanged separate rows-payload error behavior. Validation ran with `php -l includes/admin/data-tools/page-event-plan-import.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, the discovered Event Plan Import-specific test inventory, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, the separate Event Plan Import rows-payload error boundary, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Event Feedback redirect notice reduction result

- Result: the Event Feedback Administrator screen now routes its fixed redirect notice family through the existing Administrator shell explicit-notice path instead of emitting those fragments inside page content and relying on shared captured-notice extraction.
- Constrained candidate inspection scope for this pass: only `includes/admin/event-feedback.php`, `includes/admin/ticket-integrity-page.php`, and `includes/admin/vendor-availability.php` were fully inspected as the remaining plausible Administrator captured-notice candidates.
- Candidate outcome summary:
  - Event Feedback qualified as the smallest fully proven boundary because its page-local helper `vms_feedback_admin_render_notices()` owns only four fixed redirect outcomes, each already shaped exactly as `div[class] > p` with fixed translated text, unchanged `sanitize_key()` normalization for delete status, no request-derived detail text, no provider calls, and no destructive state.
  - Ticket Integrity was deferred because `vms_ticket_integrity_render_notice_from_query()` owns a much larger top-level family with many branches, mixed redirect payload sources, and request-derived `detail` / `recipient` text appended to several messages, so it was not the smallest or safest remaining boundary.
  - Vendor Availability was deferred because its only simple notice is the list-view empty state `<div class="notice notice-info inline"><p>...</p></div>` nested inside `vms_render_vendor_availability_list_view()`, which the shell does not hoist from nested content and which therefore remains a content-local boundary rather than a qualifying page-level family.
- Selected family details:
  - Page registration and renderer remain unchanged: Event Feedback still registers through `vms_register_admin_page(...)` with slug `vms-event-feedback`, capability `manage_options`, and renderer `vms_render_event_feedback_admin_page()`.
  - The selected family is the existing fixed redirect-status helper `vms_feedback_admin_render_notices()`, which still emits only these four unchanged fragments: saved settings success, delete success, missing response error, and delete failure error.
  - The helper remains page-local and nonextensible, reads only the existing redirect query flags, preserves the historical `!empty($_GET['vms_feedback_settings_saved'])` presence check, preserves `sanitize_key((string) $_GET['vms_feedback_deleted'])`, and continues to use the same translation, escaping, severity, classes, dismissibility, and visibility.
- Ordering and untouched neighboring boundaries:
  - The shell path now passes `'notices_callback' => 'vms_feedback_admin_render_notices'` into `vms_admin_ui_render_shell()`, so the four selected fragments render through the explicit sink before ordinary page content exactly once.
  - The no-shell fallback preserves the historical ordering of page heading, redirect notice family, and then ordinary page content by calling `vms_feedback_admin_render_notices()` immediately after the `<h1>` and before `vms_feedback_admin_render_content()`.
  - The separate Event Feedback missing-plan notice (`That Event Plan could not be found.`) remains in the ordinary content callback and was not migrated with the redirect family.
  - Ticket Integrity and Vendor Availability production files remained unchanged.
  - Event Plan Import's separate rows-payload error remains a distinct content-local boundary and was not altered in this pass.
- Contract and scope confirmation: the explicit-notice contract remains exactly `div[class]` and `p`; no shell-wide allowlist was broadened; shared raw `$captured_notices_html` remains unresolved and untouched; shared raw `$content_html` remains unresolved and untouched; richer, nested, hooked, or content-local notices remain separate; Pass Claims remains a separate `WPORG-24` boundary.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Event Feedback shell wiring, callback inventory update, removed content-path emission, preserved shell and no-shell ordering, every fixed notice branch, preserved exact classes and dismissibility, unchanged escaping and malformed-value behavior, unchanged neighboring Event Feedback content notice, unchanged explicit-notice contract, and unchanged raw shell sinks. Validation ran with `php -l includes/admin/event-feedback.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, the discovered Event Feedback-specific test inventory search, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, deferred Ticket Integrity and Vendor Availability content families, the separate Event Feedback missing-plan notice, the separate Event Plan Import rows-payload error boundary, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Ticket Integrity query-notice reduction result

- Result: the Ticket Integrity Administrator screen now routes its top-level query-driven notice family through the existing Administrator shell explicit-notice path instead of emitting that helper inside page content and relying on shared captured-notice extraction.
- Constrained candidate inspection scope for this pass: only the page-local query-driven family rendered by `vms_ticket_integrity_render_notice_from_query()` in `includes/admin/ticket-integrity-page.php` was inspected for migration, along with the exact writer inventory that populates `tim_notice`, `detail`, `recipient`, `red`, and `yellow`.
- Prior gate context and qualification outcome:
  - The earlier Event Feedback pass deferred Ticket Integrity because this helper is materially broader than the four fixed Event Feedback redirect notices: it owns nineteen recognized statuses, several detail-appending branches, one recipient-appending branch, and count-bearing scan output.
  - This pass completed the narrower proof that still qualifies the whole top-level family: every branch remains page-local and package-owned, emits only `div.notice > p` text, preserves existing `sanitize_key()` / `sanitize_text_field()` / `sanitize_email()` / `absint()` normalization, and does not perform hooks, Settings API notice dispatch, provider reads, or storage mutation inside the helper itself.
  - Writer/render vocabulary was fully matched before editing. `vms_ticket_integrity_handle_manual_scan()`, `vms_ticket_integrity_handle_event_scan()`, `vms_ticket_integrity_handle_save_settings()`, the daily report send/preview/dry-run/test handlers, `vms_ticket_integrity_handle_rebuild()`, `vms_ticket_integrity_handle_duplicate_cleanup()`, and `vms_ticket_integrity_handle_export_report()` collectively produce the same nineteen-status family that the helper renders, with no discovered mismatch.
- Selected family details:
  - The shell path now passes `'notices_callback' => 'vms_ticket_integrity_render_notice_from_query'` into `vms_admin_ui_render_shell()`.
  - The content closure no longer calls `vms_ticket_integrity_render_notice_from_query()` directly, so the selected family now renders through the explicit sink before ordinary page content exactly once.
  - The helper itself was otherwise left intact: exact classes, translated message copy, selector/detail/recipient normalization, count normalization, and the plain `echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';` markup all remain unchanged.
- Ordering and untouched neighboring boundaries:
  - Shell ordering remains behaviorally unchanged for the selected family because the helper previously rendered at the top of the content closure and was extracted into captured notices; it now renders through the explicit notice sink before the same page content, so operators still see the notice before the run panel.
  - The no-shell fallback remains intentionally unchanged, including its historical heading-only output path that does not render either the content closure or the Ticket Integrity helper.
  - Vendor Availability remained unchanged because its simple notice is the nested list-view empty state `<div class="notice notice-info inline"><p>...</p></div>` inside `vms_render_vendor_availability_list_view()`, which stays content-local and is not part of this top-level Ticket Integrity family.
  - Event Plan Import remained unchanged in this pass; its separate rows-payload error boundary is still content-local and outside this query-notice family.
- Contract and scope confirmation: `vms_admin_ui_explicit_notice_allowed_html()` remains exactly `div[class]` and `p`; no shell-wide allowlist was broadened; shared raw `$captured_notices_html` remains unresolved and untouched; shared raw `$content_html` remains unresolved and untouched; Pass Claims remains a separate `WPORG-24` boundary.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Ticket Integrity shell wiring, callback inventory update, removed content-path emission, preserved heading-only fallback, complete query-argument and status vocabularies, exact writer/render vocabulary parity, every success/info/warning/error helper branch including malformed-selector normalization and detail/recipient sanitization, unchanged Vendor Availability content-local notice, unchanged explicit-notice contract, unchanged raw shell sinks, and preserved shell ordering ahead of `Run Ticket Integrity Check Now` and `Monitor Settings`. Validation ran with `php -l includes/admin/ticket-integrity-page.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/ticket-integrity-inline-css-remediation.php`, `php tests/ticket-integrity-scan-lock.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, the separate Event Feedback missing-plan notice, Vendor Availability's content-local empty-state notice, the separate Event Plan Import rows-payload error boundary, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Settings fixed redirect notice reduction result

- Result: the Settings Administrator screen now routes its fixed `default_venue_set` redirect notice through the existing Administrator shell explicit-notice path instead of emitting that one fragment inside page content and relying on shared captured-notice extraction.
- Constrained candidate inspection scope for this pass: only `includes/admin/settings-page.php`, `includes/tours/class-vms-tours-admin.php`, and `includes/admin/schedule.php` were fully inspected as the remaining plausible Administrator notice candidates under the simple explicit-notice contract.
- Candidate outcome summary:
  - Settings qualified because the selected family is the smallest fully proven page-local boundary: a single fixed translated redirect-status branch keyed by the exact raw query comparison `isset($_GET['vms_notice']) && (string) $_GET['vms_notice'] === 'default_venue_set'`, with exact `div.notice > p` text-only markup, no provider or storage reads in the helper itself, no hooks, no Settings API notice ownership, and preserved silent behavior for absent, empty, unknown, or malformed non-matching values.
  - Guided Tours was deferred in that slice because its reset-success notice remained nested inside the `.vms-tours-admin-page` content wrapper and also carried an extra `data-vms-tour` attribute on the outer `div`, so it did not fit the simple explicit-notice contract or the direct captured-notice boundary requirement. A later dedicated `WPORG-24J` page-local pass closes that separate boundary without changing shell contracts.
  - Schedule was deferred because all of its notice-like output remains nested inside the `.vms-admin-schedule-content` wrapper, and the page also coexists with richer action-required / unpublished-venue notice families containing emphasis, spans, buttons, and multi-paragraph output. The simple empty-state warnings remained content-local and were not migrated in that slice; a later dedicated `WPORG-24N` pass closes that smaller warning family without altering the richer unpublished-venue branches.
- Selected family details:
  - Page registration remains unchanged through the `admin_menu` anonymous closure in `includes/admin/menu.php`, with parent slug `vms-dashboard`, capability `manage_options`, page slug `vms-settings`, and renderer `vms_render_settings_page()`.
  - The selected family is only the fixed success notice `Default venue updated.` from the direct `vms_handle_set_default_venue()` redirect path, which continues to redirect with `page=vms-settings&vms_notice=default_venue_set`.
  - The new page-local helper `vms_render_settings_page_notices()` preserves the exact raw redirect-status comparison, exact translated text, exact escaping, exact `notice notice-success` class, non-dismissible behavior, and silent fallback for every non-matching value.
- Ordering and untouched neighboring boundaries:
  - The shell path now passes `'notices_callback' => 'vms_render_settings_page_notices'` into `vms_admin_ui_render_shell()`, so the selected fixed redirect notice renders through the explicit sink before ordinary Settings page content exactly once.
  - Shell ordering remains behaviorally unchanged because that success notice was already the first top-level notice emitted by `vms_render_settings_page_content()`, ahead of the plugin version text and the rest of the page.
  - The no-shell fallback preserves the historical ordering of heading, fixed redirect notice, then ordinary page content by calling `vms_render_settings_page_notices()` immediately after the `<h1>` and before `vms_render_settings_page_content()`.
  - Subsequent `WPORG-24` slices later closed the Ticketing stock preview / commit family, the integrity-scan result family, and the nested default-venue alert family. Remaining richer Settings boundaries still include the richer entitlement image sync completion + error details output, Settings API/editor output, and other content-local notice families.
  - Guided Tours remained unchanged in that slice as a nested tour-specific content boundary; a later dedicated `WPORG-24J` pass closes that separate reset-notice family without changing shell behavior. Schedule remained unchanged in that slice as a nested content boundary with richer coexistence; later dedicated `WPORG-24M` and `WPORG-24N` passes close the repeated invalid-bounds family and the smaller scope warning family, leaving only the richer unpublished-venue family open.
  - Venue and Calendar Reconciliation remain future rich-notice candidates; this pass did not revisit or alter them.
- Contract and scope confirmation: the simple explicit-notice contract remains exactly `div[class]` and `p`; no contract expansion occurred; no shell-wide captured-notice allowlist was introduced; shared raw `$captured_notices_html` remains unresolved and untouched; shared raw `$content_html` remains unresolved and untouched; Settings API notice ownership was not replaced or duplicated; Pass Claims remains a separate `WPORG-24` boundary.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Settings shell wiring, callback inventory update, removed content-path emission, preserved heading-before-notice-before-content fallback ordering, exact fixed notice fragment, silent behavior for absent/empty/unknown/malformed non-matching values, unchanged richer Settings content notices, the then-unchanged Guided Tours nested reset notice later closed by `WPORG-24J`, unchanged Schedule nested notice families, unchanged simple explicit-notice contract, and unchanged raw shell sinks. Validation ran with `php -l includes/admin/settings-page.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, the discovered Settings-screen test inventory search, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, the deferred Schedule nested notice families, Venue and Calendar Reconciliation as future rich-notice candidates, Vendor Availability's content-local empty-state notice, the separate Event Plan Import rows-payload error boundary, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Venue Reconciliation rich explicit-notice result

- Result: Venue Reconciliation is now the first narrowly contracted rich Administrator notice family. Its page-level `<strong>` notice branches no longer flow through shared captured-notice extraction; they render through a separate rich explicit notice sink in the Administrator shell.
- Candidate boundary update: the previously identified simple page-level `div[class] > p` captured-notice candidates now appear exhausted. Venue Reconciliation was the next narrow page-local family because every branch stays within `div[class] > p > strong + text`, with no links, spans, buttons, lists, attributes beyond `class`, or externally supplied HTML.
- Registration and ownership remained unchanged: the page still registers through the `admin_menu` anonymous closure in `includes/admin/menu.php` with parent slug `vms-dashboard`, capability `manage_options`, page slug `vms-integrity-venue-links`, and renderer `vms_render_integrity_venue_reconcile_page()`. The notice family remains package-owned, page-local, and nonextensible.
- Rich contract and shell behavior:
  - `vms_admin_ui_explicit_notice_allowed_html()` remains exactly `div[class]` and `p`.
  - The shell now also supports a separate `rich_notices_callback` path with its own dedicated allowlist limited to `div[class]`, `p`, and `strong`.
  - The rich output is buffered separately from simple explicit notices, raw `$captured_notices_html`, raw `$content_html`, and `$actions_html`.
  - Shared raw `$captured_notices_html` remains unresolved and untouched.
  - Shared raw `$content_html` remains unresolved and untouched.
  - No raw shell sink was allowlisted, and no generic arbitrary-rich-HTML sink was introduced.
- Venue family details:
  - The dedicated callback `vms_render_integrity_venue_reconcile_notice()` preserves the exact recognized `vms_msg` vocabulary: `confirm_required`, `nothing_selected`, and `done`.
  - `vms_msg` still normalizes through `sanitize_key()`. `vms_changed` still normalizes through integer casting, stays server-calculated, and remains escaped safely by rendering only the resulting integer.
  - Exact markup, wording, severity, `<strong>` placement, inline status, and nondismissible behavior remain unchanged for every branch.
  - The original content-path notice emission was removed from `vms_render_integrity_venue_reconcile_page_content()`, which now renders only the intro and ordinary page body.
  - Shell ordering remains behaviorally unchanged because the notice still appears before ordinary page content in the notice region. The historical no-shell fallback ordering also remains unchanged by rendering heading, intro, notice, then the remaining page body.
- Comparison boundary retained: Calendar Reconciliation remains deferred and unchanged as a separate future rich-notice family. This pass did not alter `includes/admin/integrity-calendar-reconcile.php`.
- Historical scope confirmation: Pass Claims remained a separate `WPORG-24` boundary at the time of this slice. `WPORG-24R` later closes the parent after the Pass Claims and other accepted child boundaries were fully reconciled.

### `WPORG-24 E1` Calendar Reconciliation rich explicit-notice result

- Result: Calendar Reconciliation now reuses the existing narrow Administrator-shell rich notice path instead of emitting its page-level `<strong>` notice family inside content and relying on shared captured-notice extraction.
- Family inventory and contract confirmation:
  - The complete rendered `vms_msg` vocabulary remains exactly `confirm_required`, `nothing_selected`, and `done`.
  - The package-owned writer family remains the single `admin_post_vms_integrity_calendar_links_action` handler, which writes only those same statuses.
  - `vms_msg` still normalizes through `sanitize_key()`.
  - `vms_changed` still normalizes through integer casting, remains server-calculated from the handler’s `$changed` counter, and stays escaped safely by rendering only the resulting integer.
  - Every notice branch remains within the existing rich contract: `div[class]`, `p`, attribute-free `strong`, and text nodes only.
- Rich callback reuse:
  - The page now exposes `vms_render_integrity_calendar_reconcile_notice()` and routes it through the existing `rich_notices_callback` shell argument.
  - The shared rich allowlist remains unchanged at exactly `div[class]`, `p`, and `strong`.
  - The simple explicit-notice allowlist remains unchanged at exactly `div[class]` and `p`.
  - No new shell callback, allowlist, or generic arbitrary-rich-HTML sink was introduced.
- Ordering and content decomposition:
  - The original content-path notice emission was removed from `vms_render_integrity_calendar_reconcile_page_content()`.
  - As with Venue Reconciliation, the Calendar page now uses the smallest page-local split needed to preserve historical no-shell ordering: intro text first, then the rich notice, then the remaining ordinary content.
  - Effective shell ordering remains unchanged because the rich notice still renders before ordinary page content in the shell notice region.
  - Historical no-shell ordering remains unchanged because the fallback path still renders heading, intro, notice, then the remaining page sections.
- Unchanged boundaries:
  - Venue Reconciliation remains behaviorally unchanged and continues to use the same existing rich callback path.
  - No raw sink allowlisting occurred.
  - Shared raw `$captured_notices_html` remains unresolved and untouched.
  - Shared raw `$content_html` remains unresolved and untouched.
  - `$actions_html` remains under its existing separate contract and was not modified.
- Pass Claims remains a separate `WPORG-24` boundary.
- Historical scope confirmation: `WPORG-24 E1` remained open at the time of this slice and was not marked complete by this reduction alone. `WPORG-24R` later closes the parent without revising the historical behavior of this slice.

### `WPORG-24 E1` Settings ticketing stock notice reduction result

- Result: the Settings page's complete Ticketing stock preview / commit notice family now routes through the existing simple Administrator-shell explicit notice sink, while the no-shell fallback keeps the notice at its historical mid-page seam immediately before `Ticketing inventory tools`.
- Rendering path and selected sink:
  - Page registration remains unchanged through the `admin_menu` anonymous closure in `includes/admin/menu.php`, with parent slug `vms-dashboard`, capability `manage_options`, page slug `vms-settings`, and renderer `vms_render_settings_page()`.
  - The shell path now passes `'notices_callback' => 'vms_render_settings_page_notice_bar'` into `vms_admin_ui_render_shell()`. That composed callback keeps the existing fixed `default_venue_set` notice first and then renders the Ticketing stock family through the same simple explicit sink.
  - The no-shell fallback still renders the heading and fixed default-venue notice first, then buffers `vms_render_settings_page_content(true)` and replaces the dedicated `<!-- vms-settings-ticketing-stock-notice -->` placeholder with the resolved Ticketing stock notice markup so the stock notice remains after the Ticketing settings controls and before the inventory-tools section exactly as before.
- Complete family inventory and state lifecycle:
  - Preview writer: `vms_handle_ticketing_stock_preview()` still requires `manage_options`, verifies the `vms_ticketing_stock_preview` nonce, runs `vms_ticketing_stock_reconcile_scan(false)`, stores the report in the per-user transient key returned by `vms_ticketing_stock_preview_transient_key(get_current_user_id())`, and redirects with `page=vms-settings&vms_ticketing_stock_preview_done=1`.
  - Commit writers: `vms_handle_ticketing_stock_commit()` and the back-compat `vms_handle_reconcile_ticketing_stock()` still require `manage_options`, verify their existing nonces, run `vms_ticketing_stock_reconcile_scan(true)`, store the commit report in the global transient `vms_ticketing_stock_reconcile_last`, and redirect with `page=vms-settings&vms_ticketing_stock_commit_done=1`. The primary commit path still deletes the current user's preview transient after storing the commit report.
  - Clear path: `vms_handle_ticketing_stock_clear_preview()` still deletes the per-user preview transient and redirects back to `page=vms-settings` without any notice flag. `vms_handle_ticketing_stock_csv()` remained unchanged and still reads the existing preview / commit stores directly for CSV output only.
  - Read path: `vms_get_settings_page_ticketing_stock_notice_state()` now resolves and caches the existing page render state once per request. It always reads the per-user preview transient because the page body still needs that report for buttons and preview details, and it reads `vms_ticketing_stock_reconcile_last` only when `isset($_GET['vms_ticketing_stock_commit_done'])` is true. Reads remain non-destructive and emit no writes or deletes.
  - Writer / renderer vocabulary remains exact and unchanged: the renderer recognizes only the presence of `vms_ticketing_stock_preview_done` and `vms_ticketing_stock_commit_done`, preview uses the per-user preview report, commit uses the global commit report, unknown query flags stay silent, missing / expired / malformed transient payloads stay silent, and both flags together still render preview before commit.
- Contract confirmation:
  - The whole family qualifies for the existing simple explicit-notice contract only: every branch remains `div[class] > p` text, with classes `notice notice-info` for preview and `notice notice-success` for commit.
  - Exact message templates remain unchanged and un-translated: `Ticketing stock preview ready: checked=%d would_update=%d skipped=%d errors=%d` and `Ticketing stock reconcile complete: checked=%d updated=%d skipped=%d errors=%d`.
  - Dynamic values remain integer-cast before rendering; no branch introduces `<strong>`, links, spans, buttons, lists, line breaks, IDs, `data-*`, Settings API notices, provider HTML, or raw request HTML.
- Ordering and unchanged boundaries:
  - Effective shell ordering remains unchanged relative to `default_venue_set`: default-venue notice first, then the Ticketing stock notice family, then ordinary Settings content.
  - Historical no-shell ordering also remains unchanged: heading, default-venue notice, ordinary Settings / Ticketing controls, Ticketing stock notice family, `Ticketing inventory tools`, then the remainder of the page.
- Subsequent `WPORG-24` slices later closed the nested default-venue alert family and the integrity-scan result family. At the time of this slice, the separate entitlement image-sync completion + error details family, Settings API/editor output, and other content-local Settings families still sat outside this slice; `WPORG-24R` later records the entitlement image-sync family as accepted finite output rather than active implementation work.
  - Guided Tours, Schedule, Venue Reconciliation, Calendar Reconciliation, and Pass Claims remained separate boundaries in this slice.
- Contract and sink confirmation: `vms_admin_ui_explicit_notice_allowed_html()` remains exactly `div[class]` and `p`; `vms_admin_ui_rich_explicit_notice_allowed_html()` remains exactly `div[class]`, `p`, and attribute-free `strong`; no shell allowlist was broadened; raw `$captured_notices_html`, raw `$content_html`, and `$actions_html` remain untouched and unresolved.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the composed Settings shell callback, unchanged default-venue fragment, exact preview / commit fragments, writer / renderer vocabulary parity, missing / expired / malformed / unknown behavior, integer-normalized HTML-like input, preview-before-commit ordering, preserved relative order with `default_venue_set`, removed content-path emission, preserved fallback placeholder seam, unchanged allowlists, and unchanged raw shell sinks. Validation ran with `php -l includes/admin/settings-page.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Historical scope confirmation: `WPORG-24 E1` remained open at the time of this slice and was not marked complete by this reduction alone. `WPORG-24R` later closes the parent without revising the historical behavior of this slice.

### `WPORG-24 E1` Pass Claims Administrator notice reduction result

- Result: Pass Claims was inspected as a separate `WPORG-24` boundary, and the Guest Passes Administrator notice family now routes through the existing simple Administrator-shell explicit notice sink instead of being emitted from the page content closure.
- Rendering path and accepted boundary:
  - The affected Administrator renderer remains `vms_pass_claims_render_admin_page()` for page slug `vms-passes`, titled `Guest Passes`.
  - The shell call now supplies `'notices_callback' => 'vms_pass_claims_render_admin_notices'` to `vms_admin_ui_render_shell()`.
  - The original content-path call removed from the `$content` closure was `vms_pass_claims_render_admin_notices();`.
  - The no-shell fallback ordering remains heading first, then `vms_pass_claims_render_admin_notices()`, then the tab/content renderer.
- State lifecycle and contract confirmation:
  - `vms_pass_claims_render_admin_notices()` still reads only the sanitized `$_GET['result']` selector plus the per-user stored message returned by `vms_pass_claims_pop_user_message()`.
  - The stored-message pop remains destructive and once-only: `vms_pass_claims_pop_user_message()` still does `get_transient($key); delete_transient($key);` and returns the popped payload for the current user exactly once per render.
  - The migrated markup remains entirely inside the simple explicit contract: fixed `source_saved`, `batch_preview`, `batch_generated`, `token_voided`, and `token_restored` branches all render `div[class] > p`, and the stored message branch remains `div[class] > p` with `esc_html()`-escaped text only.
  - HTML-like stored notice text therefore remains escaped and inert; the Administrator simple and rich notice allowlists were not broadened.
- Unchanged and deferred boundaries:
  - The public Pass Claims shell `vms_pass_claims_render_public_shell()` still echoes raw `$content_html`, so that standalone browser output remains a separate open boundary.
  - Public claim forms, success / error cards, other Pass Claims output families, and Pass Claims redirect / export surfaces were not migrated in this slice.
  - Raw `$captured_notices_html` and raw `$content_html` in the shared Administrator shell remain unresolved and unchanged.
- The separate Settings entitlement image-sync structured-result family stayed outside this Pass Claims slice at the time; `WPORG-24R` later reconciles it as accepted finite output rather than a remaining implementation boundary.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. This accepted Pass Claims Administrator slice did not complete the overall `WPORG-24` batch by itself, and `WPORG-24R` later reconciles the broader residual tracker and closes the parent.

### `WPORG-24 E1` Pass Claims public status sink reduction result

- Result: Pass Claims public output was re-inventoried as its own `WPORG-24` boundary, and the smallest independently complete family proved to be the early-return public status screens inside `vms_pass_claims_render_public_claim()`. Those branches now route through a local status-screen renderer / fragment contract before reaching the shared public shell.
- Public entry-point and shell map:
  - Public route registration remains `add_action('init', 'vms_pass_claims_register_rewrite', 30)` plus `add_rewrite_tag('%vms_pass_claim_token%', '([^&]+)')` and `add_rewrite_rule('^pass/claim/([^/]+)/?$', 'index.php?vms_pass_claim_token=$matches[1]', 'top')`.
  - Browser entry remains `add_action('template_redirect', 'vms_pass_claims_template_router', 0)`, which still returns in `wp-admin`, resolves the claim token from query-var / query-string / request-URI fallbacks, and then calls `vms_pass_claims_render_public_claim($token)`.
  - Shared public shell remains `vms_pass_claims_render_public_shell(string $headline, string $content_html): void`, still emitting the document-title filter, optional asset enqueues, `<main id="primary" class="site-main vms-pass-public-page" role="main">`, `<div class="vms-pass-wrap"><div class="vms-pass-card">`, the raw `echo $content_html;` sink, the closing wrappers, and the footer / fallback document close.
- Family inventory and qualification decision:
  - Selected family: the fixed early-return public status screens for invalid token, missing batch, inactive / void pass, expired pass, rate-limit wait, and the empty-eligible-events notice variants.
  - Exact selected markup contract across every branch is now only `<h1>...</h1><p class="vms-pass-error">...</p>`.
  - Unselected public families remained separate because they require broader markup and state: the already-claimed card (`$claimed_html` with `<strong>` metadata), the success confirmation card (`$html` with divs, images, spans, links, buttons, and QR output), and the interactive claim form (`$html` with form fields, nonce field, `data-*`, ARIA attributes, buttons, and validation/state reuse).
- Authentication, state, provenance, and contract:
  - Public status rendering remains unauthenticated and nonce-free by design; ownership stays token-based through `vms_pass_claims_find_token_by_raw()`, batch resolution stays in `vms_pass_claims_get_batch_by_id()`, rate limiting stays in `vms_pass_claims_rate_limit_hit()`, and empty-event state stays in `vms_pass_claims_eligible_events_for_batch()` plus `vms_pass_claims_empty_events_notice()`.
  - The selected family adds one local contract only: `vms_pass_claims_public_status_allowed_html()` now allows exactly `h1` and `p[class]`; `vms_pass_claims_public_status_fragment()` builds the status fragment with `esc_html()` for both title and message and applies `wp_kses()` once at that local boundary; `vms_pass_claims_render_public_status_screen()` passes the contracted fragment to the unchanged shared shell.
  - Empty-events dynamic title / message text still comes only from `vms_pass_claims_empty_events_notice()` and now remains inert text even if HTML-like provider content is returned. No links, buttons, forms, IDs, inline styles, scripts, JSON, images, or extra attributes were added.
  - Query / provider / storage behavior remains unchanged for the selected family: invalid-token still stops after token lookup, missing-batch still stops after batch lookup, unavailable / expired still stop before rate limiting and eligibility checks, rate-limited still stops after the rate-limit check, and empty-events still stops after the eligibility and empty-notice reads. No new mutations were introduced.
- Unchanged and deferred boundaries:
  - The shared public shell still contains the raw `echo $content_html;` sink for unselected Pass Claims families.
  - The already-claimed, success, and interactive claim-form public families remain on their original shell handoffs and were not contracted in this slice.
  - The accepted Guest Passes Administrator notice family remains unchanged.
  - The shared Administrator shell, `vms_admin_ui_explicit_notice_allowed_html()`, `vms_admin_ui_rich_explicit_notice_allowed_html()`, raw `$captured_notices_html`, and raw `$content_html` remain unchanged and unresolved here.
- The separate Settings entitlement image-sync structured-result family stayed outside this Pass Claims slice at the time; `WPORG-24R` later reconciles it as accepted finite output rather than a remaining implementation boundary.
- Focused coverage and validation: new coverage lives in `tests/pass-claims-public-status-output-remediation.php`, which proves the exact route registration, token resolution fallbacks, selected-family branch counts, preserved markup / ordering, exact `h1` + `p[class]` allowlist, escaped HTML-like provider text, unchanged public-shell raw sink for unselected families, unchanged Administrator shell raw sinks, and unchanged Pass Claims family boundaries. Validation ran with `php -l includes/modules/admissions/pass-claims.php`, `php -l tests/pass-claims-public-status-output-remediation.php`, `php tests/pass-claims-public-status-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. This accepted Pass Claims public-status slice did not complete the overall `WPORG-24` batch by itself, and `WPORG-24R` later reconciles the broader residual tracker and closes the parent.

### `WPORG-24 E1` Pass Claims already-claimed card sink reduction result

- Result: Pass Claims public output was re-inventoried again at the next unresolved boundary, and the already-claimed public card proved to be independently complete. That family now routes through a dedicated local claimed-card renderer instead of the inline `$claimed_html` handoff.
- Entry path, condition, and shell relationship:
  - Public route and token entry remain unchanged: `vms_pass_claims_register_rewrite()` still owns the `/pass/claim/{token}` route, `vms_pass_claims_template_router()` still returns in `wp-admin`, and `vms_pass_claims_get_request_token()` still resolves the token from query-var, query-string, or request-URI fallbacks before calling `vms_pass_claims_render_public_claim($token)`.
  - The already-claimed card remains selected only by `if ($token_status === 'claimed')` inside `vms_pass_claims_render_public_claim()`.
  - The branch still runs only after successful token lookup, successful batch lookup, active/not-void availability checks, and optional expiry checks. It still runs before rate limiting and before eligible-event lookup.
  - Shared public shell behavior remains unchanged: `vms_pass_claims_render_public_shell(string $headline, string $content_html): void` still owns the wrappers and the raw `echo $content_html;` sink, but the already-claimed family now reaches it only through the dedicated claimed-card renderer.
- Data provenance and exact claimed-card contract:
  - Authentication and nonce behavior remain unchanged: the route is still public, token-based, and nonce-free, and ownership remains the possession of a valid signed pass token.
  - Request input remains only the pass token; the card itself renders no POST input, links, buttons, forms, or follow-up actions.
  - Dynamic card data remains limited to one optional value: `reservation_entry_id` from the stored token row returned by `vms_pass_claims_find_token_by_raw()`. No claimant name, email, batch label, claimed timestamp, event details, URLs, or provider HTML are rendered in this family.
  - Existing write-time behavior remains unchanged: `vms_pass_claims_create_claim()` still derives `$entry_id` from the first created admissions entry ID and stores it back into both the claim row and token row with `%d` formatting. Render-time behavior now casts that stored value to `int`, so malformed HTML-like stored content cannot become markup.
  - Exact markup contract is the complete family: `<h1>Already Claimed</h1>`, `<p class="vms-pass-note">This pass has already been claimed.</p>`, and an optional `<p class="vms-pass-meta"><strong>Reference:</strong> GL-{entry_id}</p>` only when `reservation_entry_id > 0`.
  - No `id`, `data-*`, ARIA, links, buttons, images, lists, tables, forms, hidden fields, nonce fields, line breaks, JSON, inline styles, or scripts are part of the family.
- Local renderer and unchanged behavior:
  - The family now uses `vms_pass_claims_public_claimed_card_html(int $entry_id): string` plus `vms_pass_claims_render_public_claimed_card(int $entry_id): void`.
  - The helper keeps the markup local to the family and relies on direct escaping only: fixed translated text still uses `esc_html__()`, the optional reference label still uses `esc_html__()`, and the rendered `GL-{entry_id}` value still uses `esc_html()` on the integer-derived string. No `wp_kses()` allowlist was added because every dynamic value is fully controlled and escapable at construction.
  - Query / provider / storage behavior remains unchanged for this family: claimed-token renders still perform one token lookup and one batch lookup, then stop before rate limiting and eligibility reads. The local claimed-card renderer itself performs no additional reads or mutations.
  - Rendering still occurs exactly once for the selected family, and the success confirmation, interactive claim form, accepted public status family, Guest Passes Administrator notices, shared Administrator shell contracts, and shared public shell implementation all remain unchanged.
- Focused coverage and validation: new coverage lives in `tests/pass-claims-public-claimed-card-output-remediation.php`, while `tests/pass-claims-public-status-output-remediation.php` was updated to remove the old assumption that the already-claimed family still used the raw `$claimed_html` handoff. The claimed-card test proves the exact route registration, token resolution fallbacks, both claimed-card branches, exact markup / ordering / `<strong>` placement, absence of unsupported elements and attributes, inert malformed stored `reservation_entry_id` content, zero added provider reads inside the local renderer, exact pre-rate-limit branch stop counts, unchanged public-shell raw sink for unselected families, and unchanged Administrator shell raw sinks. Validation ran with `php -l includes/modules/admissions/pass-claims.php`, `php -l tests/pass-claims-public-status-output-remediation.php`, `php -l tests/pass-claims-public-claimed-card-output-remediation.php`, `php tests/pass-claims-public-status-output-remediation.php`, `php tests/pass-claims-public-claimed-card-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. The success confirmation and interactive claim-form families remained separate unresolved Pass Claims public boundaries until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Pass Claims success confirmation sink reduction result

- Result: Pass Claims public output was re-inventoried again at the next unresolved boundary, and the complete successful-claim confirmation family proved independently complete. That family now routes through a dedicated local success-confirmation renderer instead of building the success `$html` inline and handing it directly to the shared public shell.
- Successful-claim entry path and lifecycle:
  - Public route and shell entry remain unchanged: `vms_pass_claims_register_rewrite()` still owns `/pass/claim/{token}`, `vms_pass_claims_template_router()` still returns in `wp-admin`, and `vms_pass_claims_get_request_token()` still resolves the token from query-var, query-string, or request-URI fallbacks before calling `vms_pass_claims_render_public_claim($token)`.
  - A successful claim still occurs only on `POST` when `vms_pass_claim_submit` is present, the unslashed `_vms_pass_claim_nonce` verifies against `vms_pass_claim_submit`, the token/batch/availability/expiry gates have already passed, the token is not already claimed, the request is not rate-limited, an eligible event was found, and `vms_pass_claims_create_claim()` returns a non-`WP_Error` array result.
  - Success still renders in the same request, not after a redirect. The success state is passed directly from the `vms_pass_claims_create_claim()` return array into the local renderer and is not re-read from storage before display.
  - Claim mutation, token updates, admission-entry creation, audit logging, and optional pass-email delivery all remain inside `vms_pass_claims_create_claim()` and were not changed in this slice.
- Result provenance and exact family boundary:
  - Rendered dynamic values come from a finite result contract plus one request-derived value:
    - `event_title`, `event_date`, `venue_name`, `reference`, `scan_url`, `admission_token`, `admission_tokens`, `party_size`, `email_sent`, and `email_result` come from the success result returned by `vms_pass_claims_create_claim()`.
    - The optional email-warning branch still depends on the sanitized submitted email string from `$posted['email']`, but only to decide whether to show the fallback warning when `email_sent` is false.
  - The result array itself still comes from existing mutation flow: `reference` remains `GL-{entry_id}`, `scan_url` still comes from `vms_admission_scan_url()` when an admission token exists, `admission_tokens` still comes from the created admission-entry tokens, `event_title` / `event_date` / `venue_name` still come from the selected eligible event, and `email_result` still comes from `vms_admission_email_pass_result()` when an email was submitted.
  - No arbitrary HTML, hooks, filters, templates, or external callback markup are part of the family. Stored or provider text may still be HTML-like, but it now remains inert because the renderer escapes it as text.
- Exact local success contract:
  - The full family now lives in `vms_pass_claims_public_success_confirmation_html(array $success, string $posted_email): string` plus `vms_pass_claims_render_public_success_confirmation(array $success, string $posted_email): void`.
  - The contract includes the complete success surface and all current optional branches:
    - confirmation heading and success summary;
    - ticket wrapper;
    - single-pass QR image or multi-pass QR grid;
    - event, date, venue, admissions, and reference metadata;
    - optional `View / Print Passes` action button;
    - fixed gate/help hint;
    - optional emailed-copy note or optional email-failure warning.
  - Elements and attributes remain finite and exact: `h1`, `h2`, `div[class]`, `p[class]`, `strong`, `img[class|src|alt]`, `span`, and `a[class|href|target|rel]`. No forms, inputs, buttons, `data-*`, ARIA, inline styles, scripts, JSON, IDs, lists, or tables are part of the family.
  - No family-specific `wp_kses()` allowlist was necessary. The renderer keeps every element hard-coded locally and uses direct escaping only: text with `esc_html()`, alt text with `esc_attr__()`, and URLs with `esc_url()`.
- Unchanged behavior and boundaries:
  - Query / provider / storage behavior remains unchanged for successful submissions: the same pre-success token, batch, and eligible-event reads still occur; rate limiting still only runs when an IP is present; the same single claim-mutation call still produces the result; and the renderer itself adds no new database or storage reads.
  - QR behavior remains unchanged: QR image URLs still come from `vms_pass_claims_qr_image_url('vms-admission:' . $token)`, single-pass view links still prefer `vms_admission_public_pass_url($admission_token, true)` when available and otherwise fall back to the existing scan URL, and multi-pass grids still skip empty token rows while preserving the existing pass-number labels derived from the original total.
  - The accepted public status family and accepted already-claimed family remain unchanged. The interactive claim form remains a separate unresolved boundary and still owns the raw form-family handoff. Guest Passes Administrator behavior, the shared public shell, the shared Administrator shell, and both Administrator notice allowlists remain unchanged.
- Focused coverage and validation: new coverage lives in `tests/pass-claims-public-success-output-remediation.php`, while the accepted status and already-claimed tests were updated only to remove now-stale assumptions that the success family still used the raw shell handoff. The success test proves the exact route registration and token fallbacks, direct helper output for single-pass, multi-pass, and minimal branches, exact markup / ordering / classes / `<strong>` placement, inert HTML-like event/reference/provider text, escaped dynamic URLs and attributes, preserved QR output, omitted optional branches when source data is absent, invalid-nonce and failed-claim form-path exclusion, exact POST success entry path, preserved nonce and sanitized mutation inputs, unchanged operation counts, unchanged shared shell sinks, and unchanged accepted public / Administrator families. Validation ran with `php -l includes/modules/admissions/pass-claims.php`, `php -l tests/pass-claims-public-status-output-remediation.php`, `php -l tests/pass-claims-public-claimed-card-output-remediation.php`, `php -l tests/pass-claims-public-success-output-remediation.php`, `php tests/pass-claims-public-success-output-remediation.php`, `php tests/pass-claims-public-status-output-remediation.php`, `php tests/pass-claims-public-claimed-card-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. The interactive claim-form family was then the remaining unresolved Pass Claims public-output boundary until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Pass Claims public form sink reduction result

- Result: the interactive public claim form was re-inventoried as the last unresolved Pass Claims public-output family, and it proved independently complete. That family now routes through `vms_pass_claims_public_form_html(array $batch, array $eligible_events, array $posted, string $error, int $max_party_size): string` plus `vms_pass_claims_render_public_form(...)` instead of building `$html` inline and handing it directly to the shared public shell.
- Selected family and exact contract:
  - Scope includes the initial GET form, invalid-nonce redisplay, party-size validation redisplay, invalid-event redisplay, and claim-failure redisplay. Invalid-token, missing-batch, inactive / expired / rate-limited, empty-eligible-events, already-claimed, and successful-claim branches remain on their previously accepted local renderers and were not reworked here.
  - The local form contract preserves the exact existing selectors and attributes relied on by the public assets: `.vms-pass-grid`, `.vms-pass-span-2`, `.vms-pass-actions`, `.vms-pass-checkbox`, `.vms-pass-field-help`, `.vms-pass-number-control`, `.vms-pass-number-control__button`, `[data-vms-pass-party-decrease]`, `[data-vms-pass-party-increase]`, and `[data-vms-pass-party-size]`.
  - Elements remain finite and hard-coded locally: `h1`, `p[class]`, `strong`, `form[method]`, `div[class]`, `label[class]`, `input[type|name|value|required|min|max|step|inputmode|data-vms-pass-party-size]`, `select[name|required]`, `option[value|selected]`, `button[type|class|name|value|data-vms-pass-party-decrease|data-vms-pass-party-increase|aria-label]`, and `span[class]`. No links, images, inline styles, scripts, JSON, IDs, or template callbacks are part of the family.
  - No family-specific `wp_kses()` allowlist was needed. The renderer keeps the full structure local and uses direct escaping only, while preserving the existing `wp_nonce_field('vms_pass_claim_submit', '_vms_pass_claim_nonce', true, false)` call exactly.
- Behavior and boundary confirmation:
  - Request flow and mutation behavior remain unchanged: token lookup, batch lookup, availability / expiry / already-claimed / rate-limit gates, eligible-event lookup, posted-field sanitization, selected-event resolution, and the single `vms_pass_claims_create_claim()` attempt still happen in the same order and with the same counts.
  - Form redisplay behavior remains unchanged: invalid nonce does not repopulate submitted values, invalid event preserves sanitized submitted fields without forcing a selected eligible event, party-size validation clamps the displayed numeric value to the computed maximum, and claim-failure messages remain inert text.
  - After this slice, every current direct caller of `vms_pass_claims_render_public_shell()` now supplies locally controlled markup through one of four dedicated renderers: public status, already-claimed card, success confirmation, or interactive form. The shared shell implementation is unchanged, but no remaining known Pass Claims public caller hands mixed inline `$html` into it.
- Focused coverage and validation: new coverage lives in `tests/pass-claims-public-form-output-remediation.php`; the accepted status / already-claimed / success tests were updated only to remove stale assumptions that the form still used the old raw handoff. The new test proves exact helper-source boundaries, absence of local broad allowlists, preserved nonce helper usage, preserved JS/CSS selector contract, exact GET and redisplay markup for the full interactive family, sanitized mutation inputs on claim failure, exclusion of the already accepted non-form families, and unchanged shared-shell behavior. Validation ran with `php -l includes/modules/admissions/pass-claims.php`, `php -l tests/pass-claims-public-status-output-remediation.php`, `php -l tests/pass-claims-public-claimed-card-output-remediation.php`, `php -l tests/pass-claims-public-success-output-remediation.php`, `php -l tests/pass-claims-public-form-output-remediation.php`, `php tests/pass-claims-public-form-output-remediation.php`, `php tests/pass-claims-public-status-output-remediation.php`, `php tests/pass-claims-public-claimed-card-output-remediation.php`, `php tests/pass-claims-public-success-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: Pass Claims public output was fully inventoried for this `WPORG-24 E1` slice, but broader non-Pass-Claims boundaries still remained, including shared Administrator-shell captured / content HTML and the separate Event Plans partial / AJAX output work. `WPORG-24R` later reconciles those residuals and closes the parent.

### `WPORG-24 E1` Pass Claims public shell raw-sink replacement result

- Result: the shared Pass Claims public shell was re-inventoried after the four accepted public families were in place, and the caller graph proved fully package-owned and complete. `vms_pass_claims_render_public_shell()` no longer accepts a raw `$content_html` string and no longer contains `echo $content_html;`; it now accepts a package-owned renderer callback and invokes it exactly once at the same content insertion point inside the unchanged shell wrappers.
- Caller inventory and gate confirmation:
  - Direct production callers were fully enumerated and are now limited to four package-owned wrappers in `includes/modules/admissions/pass-claims.php`: `vms_pass_claims_render_public_status_screen(...)`, `vms_pass_claims_render_public_claimed_card(...)`, `vms_pass_claims_render_public_success_confirmation(...)`, and `vms_pass_claims_render_public_form(...)`.
  - No additional production caller, legacy branch, hook, filter, shortcode, template handoff, or documented package API route to the shell was found in the repository inventory. The only non-production references were remediation-test stubs and assertions updated for the new shell contract.
  - Each direct caller now passes a local static closure that echoes only its already accepted family output. No broad public union allowlist, no `wp_kses_post()`, no compatibility string path, and no request- or storage-sourced callable was introduced.
- Shell behavior preserved:
  - Signature changed only at the content handoff boundary, from raw HTML string input to a callable renderer contract. The shell still owns the same document-title filter registration, public CSS/JS enqueues, `get_header()` / fallback document opener behavior, `<main id="primary" class="site-main vms-pass-public-page" role="main">`, nested `<div class="vms-pass-wrap"><div class="vms-pass-card">`, closing wrappers, `get_footer()` / fallback document close behavior, and explicit termination.
  - The renderer callback is invoked directly, once, and only at the former raw sink position. No output buffering or broad re-sanitization was added to the shared shell.
  - Family markup, family ordering, route behavior, token handling, nonce behavior, validation, rate limiting, mutation flow, email behavior, and observed provider / query / storage / mutation counts remained unchanged across the accepted status, already-claimed, success, and interactive-form families.
- Adjacent boundaries unchanged:
  - Guest Passes Administrator notice behavior remained unchanged.
  - Administrator shell contracts remained unchanged, including both notice allowlists and the raw Administrator `echo $captured_notices_html;` and `echo $content_html;` sinks, which remain unresolved outside this Pass Claims boundary.
- The separate Settings entitlement image-sync structured-result family stayed outside this Pass Claims slice at the time and was not reconsidered here; `WPORG-24R` later reconciles it as accepted finite output rather than a remaining implementation boundary.
- Focused coverage and validation: new coverage lives in `tests/pass-claims-public-shell-output-remediation.php`; the accepted status / already-claimed / success / form tests were updated only for the shell signature and raw-sink removal while preserving their family-specific assertions. The shell test proves the exact callable signature, the removed raw sink, exact four-family caller inventory, the package-owned closure handoff at every production call site, exact main/card wrapper ordering, exact callback insertion position, single callback invocation, unchanged title-filter registration, unchanged asset enqueues, unchanged header/footer execution, unchanged explicit termination in source, unchanged Administrator shell raw sinks, and absence of buffering or a shared allowlist. Validation ran with `php -l includes/modules/admissions/pass-claims.php`, `php -l tests/pass-claims-public-shell-output-remediation.php`, `php -l tests/pass-claims-public-status-output-remediation.php`, `php -l tests/pass-claims-public-claimed-card-output-remediation.php`, `php -l tests/pass-claims-public-success-output-remediation.php`, `php -l tests/pass-claims-public-form-output-remediation.php`, `php tests/pass-claims-public-shell-output-remediation.php`, `php tests/pass-claims-public-status-output-remediation.php`, `php tests/pass-claims-public-claimed-card-output-remediation.php`, `php tests/pass-claims-public-success-output-remediation.php`, `php tests/pass-claims-public-form-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: the Pass Claims public-shell caller-reduction boundary was complete for `WPORG-24 E1`, but the parent tracker still carried broader non-Pass-Claims residuals, including the shared Administrator-shell captured/content HTML boundaries, the separate Settings entitlement image-sync structured-result follow-up, and separate Event Plans partial / AJAX output boundaries. `WPORG-24R` later reconciles those residuals and closes `WPORG-24`.

### `WPORG-24 E1` Event Feedback missing-plan notice reduction result

- Result: the remaining Event Feedback Administrator missing-plan notice family now routes through the existing `vms_feedback_admin_render_notices(...)` simple explicit-notice callback instead of being emitted from ordinary page content and hoisted into the shared raw `$captured_notices_html` sink.
- Selected family and request/state provenance:
  - The complete family is the single fixed branch reached when `isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0` resolves to a positive selected Event Plan ID and `vms_feedback_get_event_context($selected_event_plan_id)` returns an empty array.
  - Absent selections and malformed selections that sanitize to `0` remain on the existing intro-card path and are not part of the migrated family.
  - No dynamic detail text, links, emphasis, buttons, or extra attributes are included. The fragment remains exactly `<div class="notice notice-error"><p>That Event Plan could not be found.</p></div>` with `esc_html__()` and the `backstage-venue-manager` text domain.
- Routing and preserved ownership:
  - Event Feedback still registers through `vms_register_admin_page(...)` with slug `vms-event-feedback`, capability `manage_options`, and renderer `vms_render_event_feedback_admin_page()`.
  - The shell path still passes `'notices_callback' => 'vms_feedback_admin_render_notices'` into `vms_admin_ui_render_shell()`. No second page-level callback, no shell option change, and no allowlist expansion were introduced.
  - A local page-state resolver now caches the selected Event Plan ID plus the existing `vms_feedback_get_event_context(...)` lookup so the content path and notice callback share one read and preserve the previous provider-call count.
- Ordering and fallback preservation:
  - Shell ordering is preserved as existing explicit Event Feedback redirect notices, then the missing-plan notice, then the remaining selector/content output. The missing-plan notice still appears before ordinary Event Feedback content and still renders exactly once.
  - The dedicated no-shell fallback now preserves the historical order of page heading, existing redirect notices, selector, then missing-plan notice by deferring only the missing-plan fragment until after the selector while keeping the redirect notices ahead of ordinary content.
  - The branch-specific early return remains effective: once the missing-plan family is selected, no intro card, response summaries, sidebar metrics, or response table content renders alongside it.
- Adjacent boundaries unchanged:
  - The existing redirect notice inventory in `vms_feedback_admin_render_notices(...)` remains unchanged: saved settings success, delete success, missing response error, and delete failure error still use the same request flags, order, classes, dismissibility, translations, and escaping.
  - The simple explicit allowlist remains exactly `div[class]` and `p`; the rich allowlist remains exactly `div[class]`, `p`, and attribute-free `strong`.
  - Shared raw `$captured_notices_html` and shared raw `$content_html` in the Administrator shell remain unresolved outside this reduced Event Feedback slice.
- At the time of this slice, the tracker still carried the separate Settings entitlement image-sync structured-result family and the separate Event Plans partial / AJAX HTML boundaries. `WPORG-24R` later reconciles the entitlement image-sync family as accepted finite output, removes the stale broader Event Plan HTML residual, and closes the parent.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the new Event Feedback page-state resolver, unchanged callback inventory, migrated missing-plan fragment, removed content-path emission, preserved shell registration, preserved shell ordering, preserved no-shell ordering, preserved selector-before-missing-notice fallback behavior, preserved exact fragment HTML/classes/dismissibility/escaping, unchanged request normalization, unchanged context-read count, unchanged intro-card separation, unchanged allowlists, and unchanged shared raw shell sinks. Validation ran with `php -l includes/admin/event-feedback.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. This accepted Event Feedback slice reduced one package-owned shared-shell captured-notice family, while the tracker still carried the shared raw Administrator-shell `$captured_notices_html` / `$content_html` boundaries, the separate Settings entitlement image-sync structured-result follow-up, and separate Event Plans partial / AJAX output boundaries. `WPORG-24R` later reconciles those residuals and closes the parent.

### `WPORG-24 E1` Event Plan Import rows-payload error reduction result

- Result: the Event Plan Import Preview Results rows-payload error family now renders through a dedicated local code-owned renderer instead of interpolating arbitrary `WP_Error` message text directly into the nested Preview Results markup.
- Selected family and producer inventory:
  - The complete content-local family remains the rows decoder contract in `vms_event_plan_import_read_rows_json(...)`, which still returns only package-owned `WP_Error` codes `rows_json_missing`, `rows_json_unsafe`, `rows_json_too_large`, `rows_json_empty`, and `rows_json_invalid`.
  - Messages remain unchanged and translated through `__('...', 'backstage-venue-manager')`: `Preview rows cache is missing. Please run Preview again.`, `Preview rows cache path is invalid.`, `Preview rows cache is too large to validate safely.`, `Preview rows cache is empty.`, and `Preview rows cache is not valid JSON.`.
  - Request method, capability, nonce handling, preview-token query handling, storage lookup, JSON decoding, payload validation, and commit-path behavior remain unchanged. The decoder still runs exactly once during Preview Results rendering and exactly once when the commit path revalidates the cached rows payload.
- Local render contract and preserved placement:
  - `includes/admin/data-tools/page-event-plan-import.php` now owns `vms_event_plan_import_rows_payload_error_messages()` plus `vms_event_plan_import_render_rows_payload_error(string $error_code)`.
  - The local renderer accepts only the package-owned error code, maps it to the fixed family message, and emits the unchanged fragment `<div class="notice notice-error inline"><p>...</p></div>` with direct `esc_html()` on the mapped text.
  - The rows-payload error remains nested inside the Preview Results card, after the Preview Results heading, source-file line, and summary cards, and before the commit form, hidden preview token field, preview table, commit controls, and later page sections. It still renders alongside the empty preview table / commit UI rather than suppressing later Preview Results output.
  - The existing page-level Event Plan Import notice callback `vms_event_plan_import_render_notice(...)` remains unchanged. The rows-payload family still does not enter `notices_callback`, still does not receive shell notice normalization, and still does not alter the accepted no-shell fallback order.
- Adjacent boundaries unchanged:
  - `includes/admin-ui/shell.php`, the simple explicit-notice allowlist, the rich explicit-notice allowlist, raw `$captured_notices_html`, raw `$content_html`, and `$actions_html` were not changed.
  - Event Plan Import redirect/pop notices, CSV parsing, row matching, import execution, commit mutations, revert behavior, Event Feedback, Pass Claims, and the broader Event Plans partial / AJAX boundaries were not reconsidered in this slice.
- Focused coverage and validation: new coverage lives in `tests/event-plan-import-rows-payload-output-remediation.php`, and `tests/administrator-explicit-notice-output-remediation.php` now also proves the new local rows-payload renderer source and the code-owned message mapping while preserving the accepted shell/notices behavior. The focused rows test covers exact registration, the five-code producer vocabulary, missing/unsafe/oversized/empty/invalid/wrong-schema rows-cache branches, valid payload rendering, exact inline classes, exact nested Preview Results placement, inert source-name and row-message text, preserved commit/table visibility, shell ordering, no-shell ordering, and the continued separation from the page-level explicit-notice callback. Validation ran with `php -l includes/admin/data-tools/page-event-plan-import.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php -l tests/event-plan-import-rows-payload-output-remediation.php`, `php tests/event-plan-import-rows-payload-output-remediation.php`, `php tests/decoded-json-validation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. This accepted Event Plan Import slice closed the separate rows-payload Preview Results boundary, while the tracker still carried the shared raw Administrator-shell `$captured_notices_html` / `$content_html` boundaries, the separate Settings entitlement image-sync structured-result follow-up, and broader Event Plans partial / AJAX output boundaries. `WPORG-24R` later reconciles those residuals and closes the parent.

### `WPORG-24H` Settings integrity-scan result output remediation result

- Result: the Settings page's integrity-scan completion result family now renders through a dedicated page-local context builder / renderer wrapper instead of emitting a top-level `div.notice` that the shared Administrator shell hoisted into raw `$captured_notices_html`.
- Selected family and preserved lifecycle:
  - `vms_handle_integrity_scan()` remains registered on `admin_post_vms_integrity_scan` with the same `manage_options` gate, `vms_integrity_scan` nonce, raw `mode` / `limit` normalization, helper dispatch, transient key `vms_integrity_scan_last`, and redirect to `page=vms-settings&vms_scan_done=1`.
  - The selected reader path is now `vms_get_settings_page_integrity_scan_result_context()` plus `vms_build_settings_page_integrity_scan_result_context(...)`, which read the stored result only when `isset($_GET['vms_scan_done'])` is true, normalize only the existing `all` / `vendors` / `venues` / `events` result shapes, clamp the stored limit to the historical `500..5000` range, and reduce renderer input to finite integers, labels, timestamps, and fixed admin URLs.
  - `vms_render_settings_page_content()` now calls `vms_render_settings_page_integrity_scan_result(vms_get_settings_page_integrity_scan_result_context());` immediately before the `Data Integrity` heading. The new output is wrapped in `<div class="vms-settings-integrity-scan-result">...</div>`, so the shared shell no longer captures it as a top-level notice fragment while the page-local rich links remain intact.
- Contract and unchanged boundaries:
  - No shell contract changed: `vms_admin_ui_explicit_notice_allowed_html()` remains exactly `div[class]` and `p`; `vms_admin_ui_rich_explicit_notice_allowed_html()` remains exactly `div[class]`, `p`, and attribute-free `strong`; raw `$captured_notices_html`, raw `$content_html`, and `$actions_html` remain untouched.
  - No request, nonce, capability, scan helper, transient lifetime, default-venue notice bar, ticketing-stock notice bar, entitlement image-sync summary/detail family, Guided Tours, Schedule, Staff Certifications, Vendor Availability, Social Sharing, Event Feedback, Event Plan Import, Venue Reconciliation, Calendar Reconciliation, or Pass Claims behavior changed in this slice.
- Focused coverage and validation: new coverage lives in `tests/settings-integrity-scan-output-remediation.php`, which proves unchanged handler registration / nonce / dispatch / transient lifecycle, normalized composite and single-mode context building, exact finite composite and single-result markup, retained page-local ordering before `Data Integrity`, removal from shell captured-notice extraction, preserved entitlement image-sync capture behavior, preserved default-venue explicit notice output, unchanged shell allowlists, and absence of provider or scan reads in the renderer. Validation ran with `php -l includes/admin/settings-page.php`, `php -l tests/settings-integrity-scan-output-remediation.php`, `php tests/settings-integrity-scan-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / direct-renderer based and did not copy the shared runtime file into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open because the tracker still carried the separate entitlement image-sync structured-result family, the shared Administrator-shell raw `$captured_notices_html` / `$content_html` boundaries, and broader Event Plans partial / AJAX output boundaries. `WPORG-24R` later reclassifies entitlement image-sync as accepted finite output, keeps the raw shell sinks as intentional architecture outside the parent, removes the stale broader Event Plan HTML residual, and closes `WPORG-24`.

### `WPORG-24I` Settings default-venue alert output remediation result

- Result: the page-local nested default-venue alert family inside `vms_field_default_venue()` now renders through `vms_build_settings_default_venue_alert_context(...)` and `vms_render_settings_default_venue_alert(...)` instead of interleaving raw state reads and alert markup directly inside the Settings field body.
- Selected family and preserved lifecycle:
  - Settings registration remains unchanged: `register_setting('vms_settings_group', 'vms_settings', array(...))` still owns the array option, `add_settings_field('vms_default_venue_id', __('Default Venue', ...), 'vms_field_default_venue', 'vms-settings', 'vms_settings_venues')` still registers the same field callback, and `vms_sanitize_settings()` still normalizes `default_venue_id` with `absint(...)`.
  - The field control remains unchanged: `vms_field_default_venue()` still reads `get_option('vms_settings', array())`, resolves `$saved` from `default_venue_id`, fetches candidate venues with the same title-sorted `get_posts(...)` query over `publish`, `draft`, `private`, `pending`, and `future`, renders the same `<select name="vms_settings[default_venue_id]" class="vms-minw-320">`, and keeps the same description paragraph immediately before the selected alert family.
  - The selected alert builder now performs the state resolution before rendering: it preserves the existing single-unpublished-only-venue error branch, the selected-unpublished warning branch, the unset-or-invalid warning branch, and the hidden no-alert branch; it preserves the same edit-link fallback, the same single-published-venue `Fix now` URL built with `wp_nonce_url(admin_url('admin-post.php?action=vms_set_default_venue&venue_id=...'), 'vms_set_default_venue_...')`, and the same action labels and button classes.
  - The action lifecycle remains unchanged: `vms_handle_set_default_venue()` still requires `manage_options`, normalizes `venue_id` with `absint(wp_unslash(...))`, verifies the nonce action `vms_set_default_venue_{$venue_id}`, rejects unpublished venues, stores `(int) $venue_id` into `vms_settings['default_venue_id']`, and redirects to `page=vms-settings&vms_notice=default_venue_set`.
- Finite context and finite markup contract:
  - The normalized internal state vocabulary is finite: `hidden`, `single_unpublished`, `selected_unpublished`, and `unset`.
  - The renderer receives only normalized package-owned values: `show`, `state`, `notice_class`, `status`, and two finite action contexts with `visible`, `class`, `label`, `href`, `target`, and `rel`.
  - Final markup remains page-local and finite only: outer `div.notice.vms-settings-default-venue-alert`, one first `p` with fixed explanatory copy and optional nested `strong` around the status text location, then zero to two sibling `p` elements each containing exactly one `a` with the preserved button class and preserved URL. No scripts, styles, inline handlers, forms, tables, images, new IDs, new data hooks, or broad sanitizer were introduced.
- Contract and unchanged boundaries:
  - The alert remains inside the Settings field content path and does not enter `$captured_notices_html`.
  - `includes/admin-ui/shell.php`, `vms_admin_ui_explicit_notice_allowed_html()`, `vms_admin_ui_rich_explicit_notice_allowed_html()`, the separate `$actions_html` contract, the fixed `default_venue_set` redirect notice family, the Settings integrity-scan result family, and the entitlement image-sync family remain unchanged.
- Focused coverage and validation: new coverage lives in `tests/settings-default-venue-alert-output-remediation.php`, which proves unchanged Settings registration and field callback, unchanged select control and query shapes, finite alert-state normalization, exact error/warning markup contracts, inert title escaping, preserved nonce-protected `Fix now` URL shape, renderer no-read behavior, and continued page-local non-captured placement. Validation ran with `php -l includes/admin/settings-page.php`, `php -l tests/settings-default-venue-alert-output-remediation.php`, `php tests/settings-default-venue-alert-output-remediation.php`, `php tests/settings-integrity-scan-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / direct-renderer based and did not copy the shared runtime file into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open because the tracker still carried the separate entitlement image-sync structured-result family, the shared Administrator-shell raw `$captured_notices_html` / `$content_html` boundaries, and broader Event Plans partial / AJAX output boundaries. `WPORG-24R` later reclassifies entitlement image-sync as accepted finite output, keeps the raw shell sinks as intentional architecture outside the parent, removes the stale broader Event Plan HTML residual, and closes `WPORG-24`.

### `WPORG-24J` Guided Tours reset-success notice output remediation result

- Result: the Guided Tours page-local reset-success notice family now renders through `VMS_Tours_Admin::get_reset_notice_context()` and `VMS_Tours_Admin::render_reset_notice()` instead of interleaving the request-derived condition and nested markup directly inside `VMS_Tours_Admin::render_page_content()`.
- Selected family and preserved lifecycle:
  - Guided Tours page registration remains unchanged: `VMS_Tours_Admin::register_menu()` still registers the `vms-guided-tours` submenu under `vms-dashboard` with capability `manage_options`, and `VMS_Tours_Admin::render_page()` still blocks non-`manage_options` viewers with `Insufficient permissions.` before rendering.
  - The reset action lifecycle remains unchanged: `VMS_Tours_Admin::init()` still registers `admin_post_vms_tours_reset_my_state`; `VMS_Tours_Admin::handle_reset_my_state()` still requires `is_user_logged_in()` plus `current_user_can('read')`, still verifies `check_admin_referer('vms_tours_reset_my_state')`, still calls `$this->storage->reset_user_state(get_current_user_id());`, and still redirects to `admin.php?page=vms-guided-tours&vms_tours_reset_my_state=1`.
  - The new context builder normalizes only the existing finite state vocabulary `hidden` and `reset_success` from the exact existing non-empty query-flag behavior. Alternate non-empty values still show the notice; empty or absent values still hide it. The final renderer performs no request, provider, storage, or reset reads and emits only the existing fixed translated copy.
  - Placement remains page-local and unchanged: `render_page_content()` still opens `<div class="vms-tours-admin-page" data-vms-tour="guided-tours.settings">`, the reset-success notice still renders immediately inside that wrapper before the global settings form, the reset form remains `data-vms-tour="guided-tours.reset-progress"`, and the notice still stays out of shared shell captured-notice extraction.
- Finite markup contract and unchanged boundaries:
  - Final notice markup remains exact and finite: outer `<div class="notice notice-success is-dismissible" data-vms-tour="guided-tours.reset-notice">`, child `<p>`, and visible copy `Your tour progress has been reset.` No `id`, no ARIA attributes, no links, no buttons, no forms, no scripts, no styles, and no inline handlers were introduced.
  - `includes/admin-ui/shell.php`, raw `$captured_notices_html`, raw `$content_html`, raw `$actions_html`, `vms_admin_ui_explicit_notice_allowed_html()`, `vms_admin_ui_rich_explicit_notice_allowed_html()`, Guided Tours service selectors, Guided Tours JavaScript, and broader onboarding / tour-definition behavior remain unchanged.
- Focused coverage and validation: new coverage lives in `tests/guided-tours-reset-notice-output-remediation.php`, and `tests/administrator-explicit-notice-output-remediation.php` now also proves the new Guided Tours page-local builder / renderer split. The focused Guided Tours test covers unchanged page registration, unchanged `manage_options` and reset-capability failures, unchanged nonce action and redirect destination, preserved non-empty alternate-value visibility, exact finite notice markup, zero-read renderer behavior, unchanged page-local ordering before the global settings form, preserved reset form / nonce markup, continued separation from shell notice extraction, unchanged shell allowlists, and unchanged absence of a Guided Tours shell notice callback. Validation ran with `php -l includes/tours/class-vms-tours-admin.php`, `php -l tests/guided-tours-reset-notice-output-remediation.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/guided-tours-reset-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / direct-renderer based and did not copy the shared runtime file into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open because the tracker still carried the separate entitlement image-sync structured-result family, the shared Administrator-shell raw `$captured_notices_html` / `$content_html` boundaries, the separate Schedule and Vendor Availability content-local notice families, and broader Event Plans partial / AJAX output boundaries. Later accepted child slices plus `WPORG-24R` closed those historical gaps.

### `WPORG-24K` Staff Certifications pending-review warning output remediation result

- Result: the Staff Certifications pending-review Administrator warning now renders through `vms_staff_certifications_get_pending_review_warning_context()` and `vms_staff_certifications_render_pending_review_warning()` from the named hook callback `vms_staff_certifications_render_pending_review_admin_notice()` instead of interleaving the capability check, screen guard, count lookup, URL build, and richer warning markup directly inside an anonymous `admin_notices` closure.
- Selected family and preserved lifecycle:
  - Global hook registration remains unchanged in timing and scope: `includes/admin/staff-certifications.php` still registers the warning on `admin_notices` at the default priority and accepted-argument behavior, and the warning still remains separate from the page-local empty-state shell notice family.
  - Capability and visibility remain unchanged: the warning still returns early unless `current_user_can('manage_options')` is true, still suppresses itself only on the exact Staff Certifications screen guard `$screen && isset($screen->id) && $screen->id === 'vms_page_vms-staff-certifications'`, and still appears on other administration screens when pending items exist.
  - The count producer remains unchanged: `vms_staff_certifications_pending_count()` still delegates to `vms_staffing_get_pending_staff_qualification_count()`, which still returns `count(vms_staffing_get_staff_qualification_review_items('pending_verification'))`. The underlying provider still loads `vms_staff` posts with `post_status` limited to `publish`, `draft`, `pending`, and `private`, reads qualification rows from staff post meta through `vms_staffing_get_staff_qualifications($staff_id)`, filters rows by exact sanitized `pending_verification` status, and counts each matching row without deduping. Trashed and auto-draft staff posts stay excluded by the `get_posts(...)` status list; rows in `active`, `rejected`, `expired`, and `inactive` stay excluded by the status filter.
  - The new context builder normalizes only finite values: `show`, nonnegative `pending_count`, and the fixed `review_url` from `admin_url('admin.php?page=vms-staff-certifications')`. The final renderer performs no capability, screen, database, metadata, taxonomy, option, provider, request, or URL-construction reads and emits only the existing fixed translated copy selected by `_n(...)`.
- Finite markup contract and unchanged boundaries:
  - Final warning markup remains exact and finite: outer `<div class="notice notice-warning is-dismissible vms-staff-certifications-admin-notice">`, child `<p>`, child `<strong>` containing only the translated count sentence, then one literal space plus child `<a href="...">Open review queue</a>`. No `id`, no `target`, no `rel`, no ARIA attributes, no `data-*` attributes, and no other attributes were introduced on the notice or link.
  - `includes/admin-ui/shell.php`, Administrator-shell allowlists, raw `$captured_notices_html`, raw `$content_html`, raw `$actions_html`, the page-local Staff Certifications empty-state notice, certification review/edit/save/mutation workflows, and production JavaScript remain unchanged.
- Focused coverage and validation: new coverage lives in `tests/staff-certifications-pending-review-notice-output-remediation.php`, and `tests/administrator-explicit-notice-output-remediation.php` now also proves the new named callback plus builder / renderer split. The focused Staff Certifications test covers unchanged `admin_notices` registration, unchanged default priority and accepted-args behavior, unchanged `manage_options` and screen-guard visibility, unchanged `pending_verification` count producer and staff-post status inventory, zero-pending hidden behavior, exact singular and plural markup, safe normalization of negative and malformed counts, escaped review URLs, zero-read renderer behavior, unchanged separation from the page-local empty-state notice, unchanged shell allowlists, unchanged absence of extra link attributes, and unchanged absence of certification review helper calls inside the renderer. Validation ran with `php -l includes/admin/staff-certifications.php`, `php -l tests/staff-certifications-pending-review-notice-output-remediation.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/staff-certifications-pending-review-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / direct-renderer based and did not copy the shared runtime file into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open because the tracker still carried the separate entitlement image-sync structured-result family, the shared Administrator-shell raw `$captured_notices_html` / `$content_html` boundaries, the remaining Schedule unpublished-venue rich notice family, the Social Sharing event-panel lazy-load HTML family, and broader Event Plans partial / AJAX output boundaries. Later accepted child slices plus `WPORG-24R` closed those historical gaps.

### `WPORG-24L` Vendor Availability list-view empty-state output remediation result

- Result: the Vendor Availability list-view empty-state notice now renders through `vms_vendor_availability_get_list_empty_state_notice_context()` and `vms_vendor_availability_render_list_empty_state_notice()` instead of interleaving the list-empty condition and nested fragment directly inside `vms_render_vendor_availability_list_view()`.
- Selected family and preserved lifecycle:
  - Menu registration and page flow remain unchanged: `includes/admin/menu.php` still registers `vms-vendor-availability` under `vms-dashboard`, `vms_render_vendor_availability_page()` still delegates to `vms_render_vendor_availability_page_content()`, and that page renderer still computes `$selected_day_rows` from the existing filtered vendor/day path before calling `vms_render_vendor_availability_list_view($selected_day_rows, ...)`.
  - The empty-state condition remains exact: the new context builder receives the existing `$rows` argument and normalizes only one finite key, `show`, from the unchanged `empty($rows)` condition. No filter, provider, query, sort, count, or availability logic moved into the builder.
  - The list-view lifecycle remains unchanged: `vms_render_vendor_availability_list_view()` still opens the same `.vms-va-list` wrapper and section heading, still renders the empty-state notice immediately after that heading when `empty($rows)` is true through the new context key, still closes the wrapper, and still returns before the table markup. Non-empty execution still proceeds into the existing grouped table path.
- Finite markup contract and unchanged boundaries:
  - Final empty-state markup remains exact and finite: outer `<div class="notice notice-info inline">`, one direct child `<p>`, and visible copy `No vendors matched the current filters for this date.` No `id`, no ARIA attributes, no `data-*` attributes, no boolean attributes, no links, no buttons, no `strong`, no `span`, and no other elements or attributes were introduced.
  - The new renderer performs no database reads, provider reads, capability checks, screen reads, URL construction, meta reads or writes, option reads or writes, user-meta reads or writes, or mutations. It emits only the fixed translated message through `esc_html__()`.
  - `includes/admin-ui/shell.php`, Administrator-shell allowlists, raw `$captured_notices_html`, raw `$content_html`, raw `$actions_html`, Vendor Availability filters, grouping, row ordering, counts, booking links, and month/list rendering outside this one fragment remain unchanged.
- Focused coverage and validation: new coverage lives in `tests/vendor-availability-list-empty-state-output-remediation.php`, and `tests/administrator-explicit-notice-output-remediation.php` now also proves the new Vendor Availability page-local builder / renderer split while preserving the exact content-local fragment. The focused Vendor Availability test covers function existence, finite `show` context, exact hidden and visible behavior, zero-read renderer execution, exact DOM/attribute contract, inert malicious context values, unchanged empty-state nesting inside `.vms-va-list`, unchanged early return before the table path, unchanged absence of non-empty table markup in the empty-state lifecycle, and unchanged non-empty list behavior through the adjacent UX regression. Validation ran with `php -l includes/admin/vendor-availability.php`, `php -l tests/vendor-availability-list-empty-state-output-remediation.php`, `php -l tests/vendor-availability-ux.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/vendor-availability-list-empty-state-output-remediation.php`, `php tests/vendor-availability-ux.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / direct-renderer based and did not copy the shared runtime file into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open because the tracker still carried the separate entitlement image-sync structured-result family, the shared Administrator-shell raw `$captured_notices_html` / `$content_html` boundaries, the remaining Schedule unpublished-venue rich notice family, the Social Sharing event-panel lazy-load HTML family, and broader Event Plans partial / AJAX output boundaries. Later accepted child slices plus `WPORG-24R` closed those historical gaps.

### `WPORG-24M` Schedule invalid-bounds notice output remediation result

- Result: the repeated Schedule invalid-bounds notice family now renders through `vms_schedule_get_invalid_bounds_notice_context()` and `vms_schedule_render_invalid_bounds_notice()` instead of interleaving the same direct fragment inside all four Schedule view helpers.
- Selected family and preserved lifecycle:
  - Schedule menu registration and page flow remain unchanged: `includes/admin/menu.php` still registers the `vms-schedule` submenu under `vms-dashboard`, `vms_render_schedule_page()` in `includes/admin/schedule.php` still routes through `vms_admin_ui_render_shell(..., 'vms_render_schedule_page_content')` when available, and `vms_render_schedule_page_content()` still selects the same selected-venue versus all-venues and list versus calendar helper path before any view helper runs.
  - The exact target functions remain `vms_render_schedule_list_view()`, `vms_render_schedule_calendar_view()`, `vms_render_schedule_list_view_all()`, and `vms_render_schedule_calendar_view_all()` in `includes/admin/schedule.php`.
  - Each caller keeps its own original validation work in place. The selected-venue list helper still validates `$start_ymd` and `$end_ymd` through `vms_sch_parse_ymd()`. The selected-venue calendar helper still validates through `strtotime()`. The all-venues list helper still validates through `vms_sch_parse_ymd()`. The all-venues calendar helper still validates through `vms_sch_parse_ymd()`. No parsing moved into the new builder or renderer.
  - The new context builder accepts only the already-computed invalid/show state and returns only the finite key `show`. The final renderer accepts only that finite context array, performs no parsing, provider/database/capability/screen/URL/option/meta/user-meta/nonce/mutation work, and emits only the existing fixed fragment.
  - All four callers still return immediately after rendering the invalid-bounds notice and before their ordinary view-body path. Valid bounds still continue into the preexisting selected-list, selected-calendar, all-list, and all-calendar body markup unchanged.
- Finite markup contract and unchanged boundaries:
  - Final invalid-bounds markup remains exact and finite: outer `<div class="notice notice-error">`, one direct child `<p>`, and visible copy `Schedule window bounds were invalid.` The copy still has no translation wrapper. No `id`, no ARIA attributes, no `data-*` attributes, no boolean attributes, no links, no buttons, no `strong`, no `span`, and no additional elements or attributes were introduced.
  - The four old direct-echo branches are gone as independent sinks. The file now contains exactly one direct copy of the fragment inside the page-local renderer, and each target helper routes its existing invalid condition through that shared renderer.
  - `includes/admin-ui/shell.php`, Administrator-shell allowlists, raw `$captured_notices_html`, raw `$content_html`, raw `$actions_html`, the Schedule no-selection warning, the Schedule no-venues warning, the richer unpublished-venue/action-required notice families, Schedule calculations, queries, filters, grouping, ordering, URLs, and ordinary body markup remain unchanged in this slice. A later dedicated `WPORG-24N` pass closes the warning pair while leaving the richer unpublished-venue family untouched.
- Focused coverage and validation: new coverage lives in `tests/schedule-invalid-bounds-output-remediation.php`, and `tests/administrator-explicit-notice-output-remediation.php` now also proves the new Schedule page-local builder / renderer split while preserving the untouched warning and rich notice families. The focused Schedule test covers function existence, the finite `show` context, hidden and visible renderer behavior, exact DOM/attribute contract, inert malformed context values, zero-read renderer execution, proof that the old direct fragment now exists only once in source, proof that all four target helpers call the same renderer and still return immediately, proof that each helper preserves its original parser choice and invalid condition, proof that valid bounds still reach each helper’s existing view body, and proof that the notice remains content-local and outside the Administrator shell. Validation ran with `php -l includes/admin/schedule.php`, `php -l tests/schedule-invalid-bounds-output-remediation.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/schedule-invalid-bounds-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, the focused Schedule test inventory search, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / direct-renderer based and did not copy the shared runtime file into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open because the tracker still carried the separate entitlement image-sync structured-result family, the shared Administrator-shell raw `$captured_notices_html` / `$content_html` boundaries, the remaining Schedule unpublished-venue rich notice family, the Social Sharing event-panel lazy-load HTML family, and broader Event Plans partial / AJAX output boundaries. Later accepted child slices plus `WPORG-24R` closed those historical gaps.

### `WPORG-24N` Schedule scope warning notice output remediation result

- Result: the Schedule no-selection and no-venues warning family now renders through `vms_schedule_get_scope_warning_notice_context()` and `vms_schedule_render_scope_warning_notice()` instead of interleaving the two direct fragments inside `vms_render_schedule_page_content()`.
- Selected family and preserved lifecycle:
  - Schedule menu registration and page flow remain unchanged: `includes/admin/menu.php` still registers the `vms-schedule` submenu under `vms-dashboard`, `vms_render_schedule_page()` in `includes/admin/schedule.php` still routes through `vms_admin_ui_render_shell(..., 'vms_render_schedule_page_content')` when available, and `vms_render_schedule_page_content()` still owns the same scope selection, selected-venue fallback work, and unpublished-venue guard ordering before ordinary view helpers run.
  - The no-selection branch remains exact: `vms_render_schedule_page_content()` still computes the unchanged condition `$scope === 'venue' && (int) $venue_id <= 0`, now passes only that finite show state plus the fixed variant `no_selection` into the new builder, still emits the warning inside `.vms-admin-schedule-content`, still closes the wrapper, and still returns before any view-body markup.
  - The no-venues branch remains exact: the all-scope path still performs the same pre-branch `vms_sch_get_all_venue_ids()` read, now normalizes only `empty($venue_ids)` plus the fixed variant `no_venues` through the new builder, still emits the warning inside `.vms-admin-schedule-content`, still closes the wrapper, and still returns before any all-venues view-body markup.
  - The new context builder accepts only the already-computed show state plus one finite variant from `no_selection` or `no_venues`. It performs no provider/database/capability/screen/URL/option/meta/user-meta/nonce/mutation work. The final renderer accepts only that finite context array, performs no reads or mutations, and emits only the two existing fixed fragments.
- Finite markup contract and unchanged boundaries:
  - Final warning markup remains exact and finite for both variants: outer `<div class="notice notice-warning">`, one direct child `<p>`, and visible copy either `Select a venue to view its schedule.` or `No venues found to display.` The copy still has no translation wrapper. No `id`, no ARIA attributes, no `data-*` attributes, no boolean attributes, no links, no buttons, no `strong`, no `span`, and no additional elements or attributes were introduced.
  - The two old direct-echo branches are gone as independent sinks. `includes/admin/schedule.php` now contains exactly one direct copy of each warning fragment inside the page-local renderer, and both early-return branches route through that shared renderer.
  - `includes/admin-ui/shell.php`, Administrator-shell allowlists, raw `$captured_notices_html`, raw `$content_html`, raw `$actions_html`, the richer unpublished-venue/action-required notice families, the invalid-bounds renderer family, Schedule calculations, candidate reads, all-venue reads, filters, grouping, ordering, URLs, and ordinary body markup remain unchanged.
- Focused coverage and validation: new coverage lives in `tests/schedule-warning-notice-output-remediation.php`, and `tests/administrator-explicit-notice-output-remediation.php` now also proves the new Schedule scope warning builder / renderer split while preserving the exact content-local fragments. The focused Schedule warning test covers function existence, the finite `show` plus `variant` context, invalid-variant normalization, hidden and visible renderer behavior for both variants, exact DOM/attribute contracts, inert malformed context values, zero-read renderer execution, proof that each warning fragment now exists only once in source, proof that both early-return branches call the same renderer, proof that the no-selection branch still returns before any scope/body markup, proof that the no-venues branch still performs the all-venue read before returning, proof that the unpublished-venue guards remain separate, and proof that the warnings remain content-local and outside the Administrator shell. Validation ran with `php -l includes/admin/schedule.php`, `php -l tests/schedule-warning-notice-output-remediation.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/schedule-warning-notice-output-remediation.php`, `php tests/schedule-invalid-bounds-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / direct-renderer based and did not copy the shared runtime file into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open because the tracker still carried the separate entitlement image-sync structured-result family, the shared Administrator-shell raw `$captured_notices_html` / `$content_html` boundaries, the remaining Schedule unpublished-venue rich notice family, the Social Sharing event-panel lazy-load HTML family, and broader Event Plans partial / AJAX output boundaries. Later accepted child slices plus `WPORG-24R` closed those historical gaps.

### `WPORG-24O` Schedule unpublished-venue rich notice output remediation result

- Result: the two Schedule unpublished-venue rich notice branches now render through `vms_schedule_get_unpublished_venue_notice_context()` and `vms_schedule_render_unpublished_venue_notice()` instead of interleaving the rich error/action fragments directly inside `vms_render_schedule_page_content()`.
- Selected family and preserved lifecycle:
  - Schedule menu registration and page flow remain unchanged: `includes/admin/menu.php` still registers the `vms-schedule` submenu under `vms-dashboard`, `vms_render_schedule_page()` in `includes/admin/schedule.php` still routes through `vms_admin_ui_render_shell(..., 'vms_render_schedule_page_content')` when available, and `vms_render_schedule_page_content()` still owns the same scope selection, stored-venue fallback work, and rich unpublished-venue guard ordering before the warning family or ordinary view helpers run.
  - The single-candidate unpublished branch remains exact: `vms_render_schedule_page_content()` still resolves the existing candidate probe through `vms_sch_get_schedule_venue_candidates(2)`, still normalizes the candidate list locally, still applies the branch only when `count($venue_candidates) === 1`, still reads that candidate status through `get_post_status($only_id)`, still treats only `publish` and `private` as accepted statuses, and still reads `get_the_title($only_unpublished_id)`, `get_edit_post_link($only_unpublished_id, 'raw')`, and fallback `admin_url('post.php?post=' . (int) $only_unpublished_id . '&action=edit')` in the same order before rendering.
  - The selected-venue unpublished branch remains exact: `vms_render_schedule_page_content()` still applies the branch only when `$scope === 'venue' && (int) $venue_id > 0`, still reads the selected venue status through `get_post_status((int) $venue_id)`, still treats only `publish` and `private` as accepted statuses, and still reads `get_the_title((int) $venue_id)`, `get_edit_post_link((int) $venue_id, 'raw')`, and fallback `admin_url('post.php?post=' . (int) $venue_id . '&action=edit')` in the same order before rendering.
  - The new context builder accepts only finite values from the existing branch-local reads: `show`, `variant`, `show_title`, `title`, `status`, and `edit_url`. The supported variants remain exactly `single_unpublished` and `selected_unpublished`; unsupported or malformed variants collapse to hidden no-output context. The builder performs no candidate/provider reads, post-status reads, title reads, edit-link reads, URL construction, request reads, capability checks, screen reads, option/meta/user-meta reads or writes, nonce work, or mutations.
  - The final renderer accepts only that finite context array, performs no reads or mutations, and emits only the two existing fixed translated notice branches. Both original branches now call the same renderer, still close `.vms-admin-schedule-content`, and still return immediately before any downstream Schedule scope/body markup.
- Finite markup contract and unchanged boundaries:
  - Final rich markup remains exact and finite for both variants: outer `<div class="notice notice-error">`, exactly two direct `<p>` children, first paragraph containing one attribute-free `<strong>` heading plus fixed translated body copy, optional title `<span class="vms-muted">` only when the existing branch had a non-empty title, required status `<span class="vms-muted">`, and second paragraph containing exactly one `<a class="button button-primary" href="...">Open venue to publish</a>`.
  - The exact translated strings remain unchanged: `Action required:`, `Your only venue is not published, so Schedule cannot load availability.`, `Venue is not published:`, `Publish this venue to enable schedule availability.`, and `Open venue to publish`, all still through `esc_html__()` with text domain `backstage-venue-manager`. There are no placeholders or plural branches in this family.
  - Title, status, and URL handling remain unchanged at output: title still uses `esc_html()` only when non-empty, status still renders inside literal parentheses through `esc_html()`, and the action URL still renders through `esc_url()`. No new `id`, ARIA, `data-*`, `target`, `rel`, boolean attributes, inline styles, forms, extra links, extra elements, or extra classes were introduced.
  - `includes/admin-ui/shell.php`, Administrator-shell allowlists, raw `$captured_notices_html`, raw `$content_html`, raw `$actions_html`, the `WPORG-24M` invalid-bounds renderer family, the `WPORG-24N` warning renderer family, Social Sharing, Settings entitlement image-sync, Schedule provider/query/filter/view logic, and ordinary body markup remain unchanged.
- Focused coverage and validation: new coverage lives in `tests/schedule-unpublished-venue-notice-output-remediation.php`, and the adjacent Schedule/admin notice tests continue to cover the neighboring families. The focused unpublished-venue test proves function existence, finite variant vocabulary, hidden invalid-variant behavior, exact builder keys, builder no-read/no-mutation behavior, renderer no-read/no-mutation behavior, exact translated copy for both variants, exact title-visible and title-hidden behavior, inert hostile title/status/URL context, exact DOM/attribute contract, proof that both page-content branches call the same renderer, proof that both original conditions and read/fallback orders remain unchanged, proof that both branches still close the wrapper and return immediately, proof that published/private paths remain outside this family, and proof that `WPORG-24M`, `WPORG-24N`, and the Administrator shell contracts remain separate. Validation ran with `php -l includes/admin/schedule.php`, `php -l tests/schedule-unpublished-venue-notice-output-remediation.php`, `php -l tests/schedule-warning-notice-output-remediation.php`, `php -l tests/schedule-invalid-bounds-output-remediation.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/schedule-unpublished-venue-notice-output-remediation.php`, `php tests/schedule-warning-notice-output-remediation.php`, `php tests/schedule-invalid-bounds-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / direct-renderer based and did not copy the shared runtime file into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open because the tracker still carried the separate entitlement image-sync structured-result family, the shared Administrator-shell raw `$captured_notices_html` / `$content_html` boundaries, the Social Sharing event-panel lazy-load HTML family, and broader Event Plans partial / AJAX output boundaries. Later accepted child slices plus `WPORG-24R` closed those historical gaps.

### `WPORG-24P` Social Sharing event-panel lazy-load output characterization result

- Result: added focused regression coverage in `tests/social-event-panel-lazy-load-output-characterization.php` and `tests/social-event-panel-lazy-load-js-characterization.php` to freeze the current coupled Social Sharing event-panel lazy-load contract without changing production PHP or JavaScript.
- Selected family and preserved lifecycle:
  - Production files remained unchanged: `includes/social-share/event-plan-panel.php`, `includes/social-share/admin.php`, and `assets/js/vms-social-admin.js` were inspected and characterized only. Metabox registration still lives on `add_meta_boxes_vms_event_plan`, detached footer-form rendering still lives on `admin_footer`, the authenticated lazy-load endpoint still lives on `wp_ajax_vms_social_load_event_panel`, and `vms_social_enqueue_admin_assets()` still enqueues `vms-social-admin` only on the Social Sharing admin page and `vms_event_plan` edit screens with no `wp_localize_script()` path.
  - PHP producers remain exact: `vms_social_render_event_panel()` still emits either the permission paragraph, the collapsed lazy shell, or the synchronous `vms_social_event_panel_markup()` HTML plus request-local `vms_social_event_panel_register_footer_forms()` state; `vms_social_event_panel_render_footer_forms()` still turns that request-local registry back into `vms_social_event_panel_footer_forms_html()` output on `admin_footer`; and `vms_social_ajax_load_event_panel()` still validates `post_id`, post type, edit capability, Social Sharing manage capability, and `check_ajax_referer('vms_social_load_event_panel', 'nonce')` before returning only `html` and `footer_forms_html`.
  - The coupled markup/payload family remains exact and unchanged: the collapsed shell still uses one `div.vms-social-event-panel-shell` with `data-vms-social-lazy`, `data-vms-social-post-id`, `data-vms-social-url`, and `data-vms-social-nonce`; the shared main HTML still contains the top nonce field pair, descriptive paragraphs, optional unpublished warning notice, platform-card sections, copy buttons with `data-copy-text`, optional external share links with `target="_blank"` and `rel="noopener"`, queue summary paragraphs, queue controls with `form=` attributes, and optional cancel/retry operations; and detached footer forms still use the derived `vms-social-*-form-<id>` IDs, hidden `_wpnonce`, `action`, `event_plan_id`, optional `queue_id`, and `tab=queue` fields.
  - JavaScript consumer behavior remains exact: `assets/js/vms-social-admin.js` still lazy-loads on DOM ready, metabox toggle click, and jQuery `postbox-toggled`; still posts only `action=vms_social_load_event_panel`, `post_id`, and `nonce`; still assigns the PHP payload through `shell.innerHTML = String(payload.data.html)` and parses detached forms through `wrapper.innerHTML = String(html)`; still removes any existing matching `form[id]` before appending the new form node to `document.body`; and still falls back to the exact client-local paragraph `<p class="description">Unable to load social sharing tools right now. Reload and try again.</p>` instead of rendering server error messages.
- Focused coverage and validation: the new PHP-focused characterization test proves exact registrations, enqueue conditions, no localization path, collapsed shell attributes, shared synchronous/AJAX markup production, shared footer-form production/registry behavior, exact explicit JSON error payloads, exact success keys `html` plus `footer_forms_html`, full main HTML element/attribute inventory, detached footer-form element/attribute inventory, visible-control/detached-form ID coupling, queue/cancel/retry ID derivation, read-only load-path mutation behavior, and the current external-read inventory. The new JS-focused characterization test proves the exact lazy shell selector, POST field set, action name, duplicate-load guards, DOM-ready/click/jQuery triggers, `shell.innerHTML` sink, `wrapper.innerHTML` sink, `form[id]` selection, replace-before-append behavior, `document.body` append target, exact generic fallback markup, and the absence of client-side server-message rendering. Validation ran with `php -l tests/social-event-panel-lazy-load-output-characterization.php`, `php -l tests/social-event-panel-lazy-load-js-characterization.php`, `php tests/social-event-panel-lazy-load-output-characterization.php`, `php tests/social-event-panel-lazy-load-js-characterization.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / source-characterization based and did not copy the shared runtime files into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open. This characterized `WPORG-24P` slice froze the current coupled Social Sharing event-panel lazy-load family as the accepted baseline, and the later `WPORG-24Q` slice resolved that production Social Sharing implementation boundary. `WPORG-24R` later reconciles the separate entitlement image-sync structured-result family as accepted finite output, keeps the shared Administrator-shell raw `$captured_notices_html` / `$content_html` sinks outside the parent, removes the stale broader Event Plan HTML residual, and closes `WPORG-24`.

### `WPORG-24Q` Social Sharing event-panel finite renderer boundaries result

- Result: the production Social Sharing event-panel family in `includes/social-share/event-plan-panel.php` now routes through two explicit finite builder/renderer splits while preserving the exact public two-part PHP/AJAX/JavaScript contract frozen by `WPORG-24P`.
- Selected family and preserved lifecycle:
  - The unchanged public architecture remains exact: `vms_social_render_event_panel()` still emits the permission paragraph, the collapsed lazy shell, or the synchronous `vms_social_event_panel_markup()` HTML plus request-local `vms_social_event_panel_register_footer_forms()` state; `vms_social_event_panel_render_footer_forms()` still replays the request-local registry through `vms_social_event_panel_footer_forms_html()` on `admin_footer`; and `vms_social_ajax_load_event_panel()` still validates `post_id`, post type, edit capability, Social Sharing manage capability, and `check_ajax_referer('vms_social_load_event_panel', 'nonce')` before returning only `html` and `footer_forms_html`.
  - The main fragment now splits cleanly between `vms_social_build_event_panel_view()` and read-free `vms_social_render_event_panel_html()`: the builder owns event-context reads, enabled/template override reads, post-flag reads, latest queue reads, template/default/template-body reads, Social Sharing settings reads, share-payload preparation, nonce-field extraction, queue-form ID derivation, and the finite platform/queue context; the renderer accepts only that finite view and emits the exact current nonce field pair, descriptive copy, optional unpublished warning, platform cards, copy buttons, external share links, queue summary/error paragraphs, queue controls, and cancel/retry controls with unchanged ordering, attributes, and translation behavior.
  - The detached footer forms now split cleanly between `vms_social_build_event_panel_footer_forms_view()` and read-free `vms_social_render_event_panel_footer_forms_markup()`: the builder owns admin-post URL construction, derived queue/cancel/retry IDs, queue-ID normalization, and nonce generation; the renderer accepts only that finite view and emits the exact current one-form / three-form detached markup with unchanged IDs, `method`, `action`, hidden `_wpnonce`, hidden `action`, hidden `event_plan_id`, optional hidden `queue_id`, and hidden `tab=queue`.
  - JavaScript and coupling remain exact: `includes/social-share/admin.php` and `assets/js/vms-social-admin.js` stayed unchanged; the lazy shell still uses the same `data-vms-social-*` attributes; AJAX success keys remain `html` and `footer_forms_html`; the detached queue/cancel/retry forms keep the same `vms-social-*-form-<id>` naming scheme; visible controls still use the same `form=` values; `shell.innerHTML`, `wrapper.innerHTML`, replace-before-append, and the generic fallback paragraph all remain unchanged.
- Focused coverage and validation: new coverage lives in `tests/social-event-panel-finite-renderer-output-remediation.php`, which source-asserts the new builder/renderer call graph and read-free renderer boundaries, runtime-asserts that both renderers perform zero provider/meta/template/settings/queue/URL/nonce/mutation reads, proves finite builder key sets for the main and footer views, proves hostile text/URL/ID/status/error contexts cannot inject markup, attributes, event handlers, unsafe URL attributes, or extra forms, proves the main renderer preserves both the current queued/warning state and the reduced default/no-queue state, proves the footer renderer preserves both the current one-form and three-form states, and proves visible queue-control `form=` values still match detached form IDs. Validation ran with `php -l includes/social-share/event-plan-panel.php`, `php -l tests/social-event-panel-finite-renderer-output-remediation.php`, `php -l tests/social-event-panel-lazy-load-output-characterization.php`, `php -l tests/social-event-panel-lazy-load-js-characterization.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/social-event-panel-finite-renderer-output-remediation.php`, `php tests/social-event-panel-lazy-load-output-characterization.php`, `php tests/social-event-panel-lazy-load-js-characterization.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/settings-integrity-scan-output-remediation.php`, `php tests/event-plan-supporting-vendor-options-output-remediation.php`, `php tests/event-plan-ticket-ui-overrides-isolated.php`, `php tests/event-plan-calendar-unpublished-suppress-save.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Local bootstrap limitation: because the local WordPress bootstrap still loads the installed sibling `vms` plugin instead of `packages/vms-github-reconcile`, this slice stayed package-source / direct-renderer based and did not copy the shared runtime file into the installed tree.
- Historical status at the time of this slice: `WPORG-24` remained open. This accepted `WPORG-24Q` slice resolved the Social Sharing event-panel implementation family, while the tracker still carried the separate entitlement image-sync structured-result family, the shared Administrator-shell raw `$captured_notices_html` / `$content_html` boundaries, and the broader Event Plans partial / AJAX output note. `WPORG-24R` later reconciles those residuals and closes `WPORG-24`.

### `WPORG-24R` residual tracker reconciliation and parent closure result

- Result: documentation-only `WPORG-24R` reconciled the stale `WPORG-24` residual tracker in `docs/wporg-remediation-ledger.md` and `docs/WPORG_PREREVIEW_REMEDIATION.md` without changing production PHP, JavaScript, tests, assets, packaging, deployment state, or the protected stash.
- Status: `verified`. `WPORG-24` is now closed under terminal status `verified`: every tracked boundary is either normalized under accepted children, finite and verified safe, intentionally retained architecture, or explicitly outside the parent.
- Closure basis:
  - Shared Administrator-shell raw `$captured_notices_html` and `$content_html` remain intentional page-owned renderer output. The shell's explicit notice and action contracts stay unchanged, and any future shell redesign belongs outside `WPORG-24`.
  - Settings entitlement image-sync remains accepted and closed based on finite markup, sink-specific escaping, capability and nonce gating, read-only rendering, and the adjacent focused evidence already on record.
  - `WPORG-24P` characterized the coupled Social Sharing event-panel family and `WPORG-24Q` normalized its finite renderer boundaries, so Social Sharing no longer remains a parent residual.
  - Every current Event Plan HTML-transporting AJAX family already maps to an accepted tested child, and remaining handlers are data-only JSON, so the generic broader Event Plan follow-up residual was stale and is now removed.
- Outside-parent note: any future trusted printable-export-shell inventory, including `vms_safety_render_export_shell()` in `includes/safety/admin.php`, belongs to a separate non-blocking issue outside `WPORG-24` and does not block resubmission.
- Focused verification rerun for this documentation-only closeout: `tests/administrator-explicit-notice-output-remediation.php`; `tests/settings-integrity-scan-output-remediation.php`; `tests/settings-default-venue-alert-output-remediation.php`; `tests/social-event-panel-finite-renderer-output-remediation.php`; `tests/social-event-panel-lazy-load-output-characterization.php`; `tests/social-event-panel-lazy-load-js-characterization.php`; `tests/event-plan-comp-options-output-remediation.php`; `tests/event-plan-supporting-vendor-options-output-remediation.php`; `tests/event-plan-readiness-details-output-remediation.php`; `tests/event-plan-staff-output-remediation.php`; `tests/event-plan-secondary-vendors-lazy-load-output-remediation.php`; `tests/event-plan-secondary-vendors-save-output-remediation.php`; `tests/event-plan-ticket-ui-overrides-isolated.php`; `tests/event-plan-calendar-unpublished-suppress-save.php`; `tests/admin-notice-scope-remediation.php`; `tests/decoded-json-validation.php`; `git diff --check`.

### `WPORG-24 E1` Event Plan compensation-options AJAX output-contract result

- Result: the Event Plan compensation-options AJAX `html` family now routes through the family-specific `VMS_Admin_Event_Plans::render_event_plan_compensation_options_response_html()` path plus local default-tile, package-tile, package-empty-state, and tile-attribute helpers instead of the earlier generic `render_comp_option_tiles_html()` helper name.
- Selected family and producer / consumer contract:
  - AJAX producer: `VMS_Admin_Event_Plans::ajax_get_event_plan_comp_options()` in `includes/cpt/event-plans.php`, registered only on `wp_ajax_vms_get_event_plan_comp_options`.
  - Authentication and request lifecycle remain unchanged: `current_user_can('manage_options')`, `check_ajax_referer('vms_comp_options', 'nonce')`, `$_POST['venue_id']` and `$_POST['vendor_id']` via `absint()`, `$_POST['event_date']` and `$_POST['selected_opt']` via `wp_unslash()` plus `sanitize_text_field()`.
  - Response vocabulary remains unchanged: success still uses `wp_send_json_success(array('html' => $html, 'max_guarantee' => ...))`; explicit JSON errors remain `Not allowed` with status `403` and `Comp options helper not loaded` with status `500`; invalid nonce remains core AJAX `-1`; no `wp_ajax_nopriv_*` hook exists.
  - JavaScript consumer remains `assets/js/vms-event-plan-compensation.js`, still sending the same POST action / nonce / request fields, still reading `data.data.html` and `data.data.max_guarantee`, and still replacing `#vms-comp-options` with `wrap.innerHTML = data.data.html;`.
- Local fragment contract and preserved branch family:
  - The response `html` fragment remains complete and local to this family: defaults row, optional scale legend, no-venue package prompt, venue-selected package empty state, enabled / disabled default tiles, enabled / disabled package tiles, selected state, and highest-guaranteed badge state all still live under the same `html` key and the same JS insertion path.
  - The renderer emits only hard-coded `div`, `button`, `strong`, `p`, and `em` markup with direct `esc_html()` / `esc_attr()`, exact `data-*` tile attributes, and no scripts, styles, IDs, ARIA, or event-handler attributes. No `wp_kses_post()`, shared shell contract, or broad allowlist was introduced.
  - Dynamic text remains limited to fixed translated labels plus package titles, holiday names, and normalized compensation values sourced through `vms_get_event_plan_comp_options(...)`; HTML-like stored values remain inert at render time.
- Operation counts and adjacent-scope confirmation:
  - The handler still performs exactly one capability check, one nonce check, one `vms_get_event_plan_comp_options(...)` provider read, zero Event Plan reads, zero mutations, and one success write plus the same two explicit JSON error writes.
  - Supporting-vendor options, readiness-details, save responses, shared Administrator-shell contracts, raw notice sinks, and other Event Plan output families remained untouched in this slice.
- Focused coverage and validation: new coverage lives in `tests/event-plan-comp-options-output-remediation.php`, which exercises the authenticated hook / no-nopriv boundary, preserved request-field sanitation, the unchanged success / error vocabulary, exact `html` + `max_guarantee` payload shape, no-venue and no-packages branches, enabled / disabled / selected tile states, inert HTML-like stored values, the finite element / attribute inventory, and the unchanged JavaScript `data.html` + `innerHTML` consumer contract. Validation ran with `php -l includes/cpt/event-plans.php`, `php -l tests/event-plan-comp-options-output-remediation.php`, `php tests/event-plan-comp-options-output-remediation.php`, `php tests/event-plan-compensation-refresh-inline-js-remediation.php`, `php tests/event-plan-compensation-shell-inline-js-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. This accepted compensation-options slice closed the compensation-options AJAX response family, but supporting-vendor options, readiness-details, and other Event Plans partial / AJAX output boundaries still remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Event Plan supporting-vendor options AJAX output-contract result

- Result: the Event Plan supporting-vendor options AJAX `primary_html` + `supporting_html` family now routes through the local `VMS_Admin_Event_Plans::build_event_plan_supporting_vendor_options_response_payload()` path plus dedicated primary/supporting fragment wrappers instead of relaying pre-rendered HTML through `build_event_plan_vendor_option_context(...)`.
- Selected family and producer / consumer contract:
  - AJAX producer: `VMS_Admin_Event_Plans::ajax_load_event_plan_supporting_vendor_options()` in `includes/cpt/event-plans.php`, registered only on `wp_ajax_vms_load_event_plan_supporting_vendor_options`.
  - Authentication and request lifecycle remain unchanged: `current_user_can('edit_post', $post_id)`, `check_ajax_referer('vms_event_plan_admin_section', 'nonce')`, and `$_POST['post_id']` via `absint()` with no additional request fields.
  - Response vocabulary remains unchanged: success still uses `wp_send_json_success(array('primary_html' => $primary_html, 'supporting_html' => $supporting_html))`; explicit JSON errors remain `Invalid Event Plan.` with status `400` and `Not allowed` with status `403`; invalid nonce remains core AJAX `-1`; no `wp_ajax_nopriv_*` hook exists.
  - JavaScript consumer remains `assets/js/vms-lineup-schedule-admin.js`, still sending the same POST action / nonce / request fields, still reading `data.primary_html` and `data.supporting_html`, and still replacing the cached template and live `<select>` contents through `innerHTML`.
- Local fragment contract and preserved branch family:
  - The response fragments remain option-only and family-local: `primary_html` still emits the primary placeholder plus vendor `<option>` rows with `data-vendor-title` and the tax `data-*` attributes, while `supporting_html` still emits the supporting placeholder plus vendor `<option>` rows with `data-vendor-title` and optional `data-lineup-support-default-fee`.
  - The new local payload builder performs one `get_event_plan_vendor_boot_summary(...)` read against already resolved Event Plan bundle + vendor-post inputs, returns the same `primary_rows` / `supporting_rows` inventory for local logging, and fans out to the dedicated primary/supporting fragment wrappers, which in turn preserve the existing option renderers and exact output ordering.
  - Dynamic text remains limited to fixed translated placeholders plus vendor titles, availability suffixes, supporting default fee hints, and tax-status labels sourced through the existing vendor boot summary pipeline; HTML-like vendor values remain inert through direct `esc_html()` / `esc_attr()` in the underlying option renderers; no scripts, styles, IDs, ARIA, event-handler attributes, shared shell contracts, or broad allowlists were introduced.
- Operation counts and adjacent-scope confirmation:
  - The handler still performs exactly one capability check, one nonce check, one Event Plan meta-bundle read, one `get_posts(...)` vendor list read, one local response-payload build, zero mutations, and one success write plus the same two explicit JSON error writes.
  - `build_event_plan_vendor_option_context(...)`, the initial page-render path, compensation options, readiness-details, Event Plan saves, shared Administrator-shell contracts, and other Event Plan output families remained untouched in this slice.
- Focused coverage and validation: new coverage lives in `tests/event-plan-supporting-vendor-options-output-remediation.php`, which source-asserts the authenticated hook / no-nopriv boundary, preserved request-field normalization, unchanged success / error vocabulary, exact `primary_html` + `supporting_html` payload shape, family-specific payload builder and wrapper boundaries, inert HTML-like vendor values, the finite `<option>` / attribute inventory for both fragments, and the unchanged JavaScript `innerHTML` consumer contract through a package-local fragment-renderer harness. Validation ran with `php -l includes/cpt/event-plans.php`, `php -l tests/event-plan-supporting-vendor-options-output-remediation.php`, `php tests/event-plan-supporting-vendor-options-output-remediation.php`, `php tests/event-plan-comp-options-output-remediation.php`, `php tests/event-plan-primary-vendor-tax-inline-js-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. This accepted supporting-vendor options slice closed the supporting-vendor options AJAX response family, but readiness-details and other Event Plans partial / AJAX output boundaries still remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Event Plan readiness-details AJAX output-contract result

- Result: the Event Plan readiness-details lazy-load `html` family now routes through the local `VMS_Admin_Event_Plans::build_event_plan_readiness_details_response_payload()` path plus readiness-specific summary-list, warning-notice, linked-TEC, ticketing, and secondary-vendor text renderers instead of relaying a captured `readiness-details` partial through the shared lazy-section response.
- Selected family and producer / consumer contract:
  - AJAX producer: the readiness branch inside `VMS_Admin_Event_Plans::ajax_load_event_plan_admin_section()` in `includes/cpt/event-plans.php`, registered only on `wp_ajax_vms_load_event_plan_admin_section`.
  - Authentication and request lifecycle remain unchanged: `current_user_can('edit_post', $post_id)`, `check_ajax_referer('vms_event_plan_admin_section', 'nonce')`, `$_POST['post_id']` via `absint()`, and `$_POST['section']` via `wp_unslash()` plus `sanitize_key()`. The readiness family is selected only when `section === 'readiness_details'`.
  - Response vocabulary remains unchanged: readiness success still uses `wp_send_json_success(array('html' => $html, 'section' => $section))`; shared explicit JSON errors remain `Invalid Event Plan.` `400`, `Not allowed` `403`, `Section not supported.` `400`, `Event Plan not found.` `404`, and `Section not implemented.` `400`; invalid nonce remains core AJAX `-1`; no `wp_ajax_nopriv_*` hook exists.
  - JavaScript consumer remains `assets/js/vms-event-plan-shell.js`, still sending the same POST action / `post_id` / `section` / nonce fields, still reading `data.html`, and still replacing the readiness section body through `body.innerHTML = payload.data.html;`.
- Local fragment contract and preserved branch family:
  - The readiness `html` fragment remains one complete card with finite local markup only: outer `div.vms-ep-card.vms-ep-card--white.vms-ep-card--readiness-details`; one status `p.description`; optional `ul.vms-ep-inline-list` summary rows; either `div.notice.notice-warning.inline.vms-notice.vms-notice--warning` with `p > strong` plus `ul > li` warning items, or `div.notice.notice-success.inline.vms-notice` with one `p`; then three trailing `p.description` rows for linked TEC, configured tickets/add-ons, and secondary-vendor warning counts.
  - The renderer emits only `div`, `p`, `ul`, `li`, and `strong` elements with `class` as the only attribute family used. No IDs, `data-*`, ARIA, links, buttons, tables, images, inline styles, scripts, or event-handler attributes are emitted.
  - Dynamic text remains limited to fixed translated labels plus normalized readiness data from `get_event_plan_readiness_detail_context(...)`: `status_label`, `summary_rows[*].label`, `summary_rows[*].value`, `warning_items[*]`, linked TEC title/status, ticket/add-on counts, and secondary-vendor counts. HTML-like stored or provider values remain inert through direct `esc_html()` or `esc_html__()` formatting. No provider-returned markup crosses the boundary.
- Operation counts and adjacent-scope confirmation:
  - For readiness requests, the shared endpoint still performs one capability check, one nonce check, one Event Plan type validation, one `get_post(...)` read, one section-support check, zero mutations, and one success write.
  - The readiness family now performs one `get_event_plan_readiness_detail_context(...)` resolution inside the local payload builder and renders the fragment once. That detail-context call still owns the same underlying Event Plan meta-bundle, secondary-vendor boot, readiness boot, linked TEC, ticketing, add-on, and integrity reads as before; the renderer itself performs zero provider calls and zero database reads.
  - Compensation-options, supporting-vendor options, staff lazy-load, secondary-vendor lazy-load, save-response families, shared Administrator-shell contracts, raw notice sinks, and other Event Plan output families remained untouched in this slice.
- Focused coverage and validation: new coverage lives in `tests/event-plan-readiness-details-output-remediation.php`, which source-asserts the shared authenticated action / no-nopriv boundary, preserved request-field normalization, unchanged readiness success/error vocabulary, the exact `html` + `section` response keys, the local readiness payload builder and sub-renderers, the finite element / attribute inventory, inert HTML-like readiness text, the unchanged shell `innerHTML` consumer contract, and DOM-level parity with the legacy readiness partial for warning, all-ready, and empty-context branches through a package-local renderer harness. Because the local WordPress bootstrap still loads the installed `vms` plugin instead of `packages/vms-github-reconcile`, this focused test stays package-source / direct-renderer based rather than mutating the installed plugin tree. Validation ran with `php -l includes/cpt/event-plans.php`, `php -l tests/event-plan-readiness-details-output-remediation.php`, `php -l tests/event-plan-dead-editor-scripts-partial-removal.php`, `php tests/event-plan-readiness-details-output-remediation.php`, `php tests/event-plan-shell-controller-inline-js-remediation.php`, `php tests/event-plan-dead-editor-scripts-partial-removal.php`, `php tests/event-plan-comp-options-output-remediation.php`, `php tests/event-plan-supporting-vendor-options-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. This accepted readiness-details slice closed the readiness-details lazy-load response family, but save-response families and other Event Plans partial / AJAX output boundaries still remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Event Plan staff AJAX output-contract result

- Result: the Event Plan staff lazy-load `html` family now routes through the local `VMS_Admin_Event_Plans::build_event_plan_staff_response_payload()` path plus staff-specific normalized context builders and finite renderer helpers instead of relaying a captured `staff` partial through the shared lazy-section response.
- Selected family and producer / consumer contract:
  - AJAX producer: the staff branch inside `VMS_Admin_Event_Plans::ajax_load_event_plan_admin_section()` in `includes/cpt/event-plans.php`, registered only on `wp_ajax_vms_load_event_plan_admin_section`.
  - Authentication and request lifecycle remain unchanged: `current_user_can('edit_post', $post_id)`, `check_ajax_referer('vms_event_plan_admin_section', 'nonce')`, `$_POST['post_id']` via `absint()`, and `$_POST['section']` via `wp_unslash()` plus `sanitize_key()`. The staff family is selected only when `section === 'staff'`.
  - Response vocabulary remains unchanged: staff success still uses `wp_send_json_success(array('html' => $html, 'section' => $section))`; shared explicit JSON errors remain `Invalid Event Plan.` `400`, `Not allowed` `403`, `Section not supported.` `400`, `Event Plan not found.` `404`, and `Section not implemented.` `400`; invalid nonce remains core AJAX `-1`; no `wp_ajax_nopriv_*` hook exists.
  - JavaScript consumer remains `assets/js/vms-event-plan-shell.js`, still sending the same POST action / `post_id` / `section` / nonce fields, still reading `data.html`, still replacing the staff section body through `body.innerHTML = payload.data.html;`, and still calling `window.vmsEventPlanInitStaff(body)` after insertion.
- Local fragment contract and normalized data path:
  - The old ambiguous sink was `capture_event_plan_partial('staff', ...)` inside the shared lazy-load branch. That handoff is removed for AJAX responses only; the non-AJAX page render still keeps the live `staff` partial, so no other Event Plan family was folded into this slice.
  - The new data path is `get_event_plan_staff_render_context(...)` for the existing package-owned staffing reads, `build_event_plan_staff_response_context(...)` plus candidate/tax-badge normalization helpers for the family-local response array, and `render_event_plan_staff_response_html(...)` plus dedicated alert/template/role/candidate/badge render helpers for the final fragment. Provider and database-backed reads stay in the builder layer; the renderer group performs no provider calls or database reads.
  - The response `html` fragment remains one complete local staffing editor card with finite markup only: outer `div.vms-ep-card.vms-ep-card--white.vms-ep-card--staff`; top-level `p.description` summary rows; optional `div.notice.notice-warning.inline` with `p > strong` plus `ul > li` alerts; one `div.vms-ep-inline-card.vms-mb-12` template controls block with `strong`, `p`, `label`, `select`, `option`, and `button`; two hidden `input` fields; one `div.vms-ep-staff-wrap` root; then either a single no-roles `p.description` or repeated `div.vms-ep-staff-role...` cards containing `div`, `p`, `label`, `input`, `select`, `option`, and `span` elements only.
  - Permitted attributes remain finite and code-owned: `class`; the existing `id="vms-ep-staff-headcount-summary"`; fixed `name` / `type` / `value` / `min` / `step`; fixed `role="group"`; fixed `aria-label` on the role candidate grid and tax badges; the established `data-vms-*` / `data-role-*` hooks already consumed by `assets/js/vms-event-plan-staff.js`; one existing inline `style` on the alert `<ul>`; and the standard `checked`, `disabled`, and `selected` boolean attributes. No scripts, styles, links, tables, images, arbitrary `data-*`, or event-handler attributes were introduced.
  - Dynamic text remains limited to fixed translated labels plus normalized staffing values: headcount summary text, template alerts, applied/recommended template summary, template option labels, role names, role summaries, threshold copy, qualification summary text, candidate titles, eligibility/tax badge text, and badge notes. HTML-like stored/provider values remain inert through direct `esc_html()` / `esc_attr()` at output.
- Operation counts and adjacent-scope confirmation:
  - For staff requests, the shared endpoint still performs one capability check, one nonce check, one Event Plan type validation, one `get_post(...)` read, one section-support check, zero mutations, and one success write.
  - The staff family now performs one `get_post_meta(..., '_vms_staff_assignments', true)` read in the AJAX branch, one `get_event_plan_staff_render_context(...)` resolution inside the local payload builder, one local normalization pass for alerts / templates / roles / candidates / badge rows, and one final fragment render. The renderer itself performs zero provider/database reads.
  - Compensation-options, supporting-vendor options, readiness-details, secondary-vendor lazy-load/save families, shared Administrator-shell contracts, raw notice sinks, and other Event Plan output families remained untouched in this slice.
- Focused coverage and validation: new coverage lives in `tests/event-plan-staff-output-remediation.php`, which source-asserts the shared authenticated action / no-nopriv boundary, preserved request-field normalization, unchanged staff success/error vocabulary, the exact `html` + `section` response keys, the local staff payload builder, removal of the AJAX captured-partial sink, renderer-group isolation from provider/database reads, the finite element / attribute inventory, inert HTML-like staffing text, the unchanged shell `innerHTML` + `window.vmsEventPlanInitStaff(body)` consumer contract, and the no-roles / alert / role-card / assigned-ineligible / no-candidates branches through a package-local direct-renderer harness. Because the local WordPress bootstrap still loads the installed `vms` plugin instead of `packages/vms-github-reconcile`, this focused test stays package-source / direct-renderer based rather than mutating the installed plugin tree.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. This accepted staff slice closed the staff lazy-load response family, but secondary-vendor save/lazy-load output boundaries and other Event Plans partial / AJAX output work still remained separate follow-up slices until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Event Plan Secondary Vendors lazy-load AJAX output-contract result

- Result: the Event Plan Secondary Vendors lazy-load `html` family now routes through the local `VMS_Admin_Event_Plans::build_event_plan_secondary_vendors_lazy_load_response_payload()` path plus secondary-vendor-specific context/config/render helpers instead of relaying a captured `secondary-vendors` partial through the shared `get_event_plan_secondary_vendors_module_payload()` helper.
- Selected family and producer / consumer contract:
  - AJAX producer: the `secondary_vendors` branch inside `VMS_Admin_Event_Plans::ajax_load_event_plan_admin_section()` in `includes/cpt/event-plans.php`, registered only on `wp_ajax_vms_load_event_plan_admin_section`.
  - Authentication and request lifecycle remain unchanged: `current_user_can('edit_post', $post_id)`, `check_ajax_referer('vms_event_plan_admin_section', 'nonce')`, `$_POST['post_id']` via `absint()`, and `$_POST['section']` via `wp_unslash()` plus `sanitize_key()`. The family is selected only when `section === 'secondary_vendors'`.
  - Response vocabulary remains unchanged: lazy-load success still uses `wp_send_json_success(array('html' => $html, 'section' => $section, 'has_data' => ..., 'summary_meta' => ..., 'module_owner' => ...))`; shared explicit JSON errors remain `Invalid Event Plan.` `400`, `Not allowed` `403`, `Section not supported.` `400`, `Event Plan not found.` `404`, and `Section not implemented.` `400`; invalid nonce remains core AJAX `-1`; no `wp_ajax_nopriv_*` hook exists.
  - JavaScript consumer remains `assets/js/vms-event-plan-shell.js`, still sending the same POST action / `post_id` / `section` / nonce fields, still reading `data.html`, still replacing the lazy body through `body.innerHTML = payload.data.html;`, and still calling `window.vmsEventPlanInitSecondaryVendors(body)` after insertion.
- Local fragment contract and preserved branch family:
  - The old ambiguous sink was the shared `get_event_plan_secondary_vendors_module_payload()` helper, which built both save-response and lazy-load HTML from the captured `secondary-vendors` partial. This slice removes that helper from the lazy-load branch only. The separate save-response family in `ajax_save_event_plan_secondary_vendors()` remains on the old helper and remains an intentionally separate follow-up boundary.
  - The new lazy-load data path keeps the same package-owned provider work in the builder layer: one Event Plan meta-bundle read, one vendor list read, one `get_event_plan_secondary_vendor_boot_summary(...)` resolution, one vendor-category snapshot read, one local context/config normalization pass, and one final renderer pass. The renderer group itself now emits the fragment through finite secondary-vendor helpers for group summaries, group rows, status badges, templates, and the vendor-category notice, with zero provider or database reads inside the renderer.
  - The response `html` fragment remains one complete local Secondary Vendors editor section with a finite fixed element inventory only: leading `p.description`; one outer `div#vms-secondary-vendors-section`; two hidden `input` fields; one inert `<script type="application/json" data-vms-secondary-config>` tag in the same location as the legacy partial; optional `div.notice notice-info`; optional `p.description.vms-secondary-vendor-legend`; one live `div#vms-secondary-vendor-groups` tree containing fixed `div` / `label` / `span` / `select` / `option` / `input` / `button` / `a` / `p` / `ul` / `li` / `strong` children; one actions `p`; one live-status `p`; two `<template>` blocks; and one vendor-category sync `div.notice`. Dynamic text remains limited to fixed translated labels plus normalized secondary-vendor boot-summary values and normalized vendor-category snapshot rows; HTML-like stored values remain inert through direct `esc_html()` / `esc_attr()` output.
  - The inert config payload keeps the same consumer contract as the legacy partial: the lazy renderer emits the exact tag `<script type="application/json" data-vms-secondary-config>` with `wp_json_encode((array) ($context['secondary_config'] ?? array()))`, while the legacy partial still emits the same tag through `wp_json_encode($secondary_config)`. No custom inline JS is introduced. `assets/js/vms-event-plan-secondary-vendors.js` still reads the tag with `const configNode = section.querySelector('[data-vms-secondary-config]');` and `config = configNode ? JSON.parse(String(configNode.textContent || '{}')) : {};`. The JSON stays inert in HTML because core `wp_json_encode()` uses `json_encode()` default slash escaping, so a hostile `</script>` sequence is emitted as `<\/script>` text inside the inert tag rather than terminating it.
- Operation counts and adjacent-scope confirmation:
  - For Secondary Vendors lazy-load requests, the shared endpoint still performs one capability check, one nonce check, one Event Plan type validation, one `get_post(...)` read, one section-support check, zero mutations, and one success write.
  - The lazy-load family now performs one `get_event_plan_meta_bundle(...)` read, one `get_posts(...)` vendor list read, one `get_event_plan_secondary_vendor_boot_summary(...)` resolution, one `vms_event_plan_collect_vendor_category_snapshot(...)` read, one local context/config build, and one final fragment render. The renderer itself performs zero provider/database reads.
  - The separate save-response family remains unchanged and still uses `get_event_plan_secondary_vendors_module_payload()` plus the captured partial. Compensation-options, supporting-vendor options, readiness-details, staff lazy-load, shared Administrator-shell contracts, raw notice sinks, and other Event Plan output families remained untouched in this slice.
- Focused coverage and validation: new coverage lives in `tests/event-plan-secondary-vendors-lazy-load-output-remediation.php`, which source-asserts the shared authenticated action / no-nopriv boundary, preserved request-field normalization, unchanged lazy-load success/error vocabulary, the exact `html` + `section` + `has_data` + `summary_meta` + `module_owner` response keys, the lazy-load-only builder swap, explicit save-response separation, shared-parser boolean parity for `open_for_dispatch` and `allow_over_capacity`, renderer-group isolation from provider/database reads, the finite element / attribute inventory, inert hostile vendor/config text including `</script><script>alert(1)</script>`, the unchanged shell `innerHTML` + `window.vmsEventPlanInitSecondaryVendors(body)` consumer contract, and DOM-level parity with the legacy `secondary-vendors` partial for representative rich and empty contexts through a package-local direct-renderer harness. Because the local WordPress bootstrap still loads the installed `vms` plugin instead of `packages/vms-github-reconcile`, this focused test stays package-source / direct-renderer based rather than mutating the installed plugin tree. Validation ran with `php -l includes/cpt/event-plans.php`, `php -l tests/event-plan-secondary-vendors-lazy-load-output-remediation.php`, `php -l tests/event-plan-shell-controller-inline-js-remediation.php`, `php -l tests/event-plan-secondary-vendor-inline-js-remediation.php`, `php -l tests/event-plan-dead-editor-scripts-partial-removal.php`, `php tests/event-plan-secondary-vendors-lazy-load-output-remediation.php`, `php tests/event-plan-shell-controller-inline-js-remediation.php`, `php tests/event-plan-comp-options-output-remediation.php`, `php tests/event-plan-supporting-vendor-options-output-remediation.php`, `php tests/event-plan-readiness-details-output-remediation.php`, `php tests/event-plan-staff-output-remediation.php`, `php tests/event-plan-secondary-vendor-bootstrap-remediation.php`, `php tests/event-plan-secondary-vendor-inline-js-remediation.php`, `php tests/event-plan-module-reopen-and-market-layout.php`, `php tests/event-plan-dead-editor-scripts-partial-removal.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24 E1` remained `PARTIALLY STALE` / open. This accepted Secondary Vendors slice closed the lazy-load response family only; the separate Secondary Vendors save-response family and other Event Plans partial / AJAX output boundaries still remained separate follow-up work until later accepted child slices and `WPORG-24R` closed the parent.

### `WPORG-24 E1` Event Plan Secondary Vendors save-response AJAX output-contract result

- Result: the Event Plan Secondary Vendors save-response `html` family now routes through the local `VMS_Admin_Event_Plans::build_event_plan_secondary_vendors_save_response_payload()` path plus save-response-specific context/config/render helpers instead of obtaining post-mutation HTML from the old ambiguous `get_event_plan_secondary_vendors_module_payload()` captured-partial sink. The accepted lazy-load family remains separate on `build_event_plan_secondary_vendors_lazy_load_response_payload()` and was not reused here.
- Selected family and producer / consumer contract:
  - AJAX producer: `VMS_Admin_Event_Plans::ajax_save_event_plan_secondary_vendors()` in `includes/cpt/event-plans.php`, registered only on `wp_ajax_vms_save_event_plan_secondary_vendors`.
  - Request / capability / nonce lifecycle remains unchanged: `$_POST['post_id']` via `absint()`; `current_user_can('edit_post', $post_id)`; `check_ajax_referer('vms_event_plan_secondary_vendors_save', 'nonce')`; then the unchanged mutation entry point `vms_event_plan_save_secondary_vendors_module($post_id, (array) $_POST)`.
  - The mutation helper still delegates request normalization to `vms_event_plan_resolve_secondary_vendor_submission()`, which still consumes `vms_secondary_vendor_assignments`, `vms_secondary_vendor_type`, `vms_secondary_vendor_ids`, and `vms_clear_secondary_vendors`, still unslashes posted arrays / IDs / type strings with `wp_unslash()`, still canonicalizes nested assignment fields through `vms_event_plan_normalize_secondary_vendor_assignment_map()`, and still preserves the shared boolean parser for `open_for_dispatch` default-true and `allow_over_capacity` default-false behavior.
  - Response vocabulary remains unchanged: save success still emits `html` string, `has_data` integer `0|1`, `summary_meta` string, `module_owner` string, `changed` integer `0|1`, `dirty_fields` sanitized string array, `repair_reasons` sanitized string array, `queued_calendar_maintenance` integer `0|1`, and `message` string. Explicit handler errors remain `Invalid Event Plan.` `400`, `Not allowed` `403`, and mutation-helper failures through `code` + sanitized `message` with `400` for `vms_secondary_vendor_over_capacity` or `500` otherwise; invalid nonce remains core AJAX `-1`; no `wp_ajax_nopriv_*` hook exists.
  - JavaScript consumer remains `assets/js/vms-event-plan-secondary-vendors.js`, still posting the same `action` / `post_id` / `nonce` / `vms_clear_secondary_vendors` fields plus the same nested `vms_secondary_vendor_assignments[...]` group rows, still reading `payload.data.html`, `payload.data.has_data`, `payload.data.summary_meta`, `payload.data.changed`, and `payload.data.message`, still replacing the same `.vms-collapsible-body` through `body.innerHTML = payload.data.html;`, and still calling `window.vmsEventPlanInitSecondaryVendors(body)` after replacement. No production JavaScript change was required.
- Local fragment contract and preserved branch family:
  - The old ambiguous sink remains source-visible only as the legacy `get_event_plan_secondary_vendors_module_payload()` helper, which still captures the `secondary-vendors` partial for historical / non-save evidence paths. The save handler no longer calls that helper.
  - The new save-response data path keeps all provider / metadata / taxonomy / post reads in the builder layer: one `get_event_plan_meta_bundle(...)` read, one `get_posts(...)` vendor-list read, one `get_event_plan_secondary_vendor_boot_summary(...)` resolution, one `vms_event_plan_collect_vendor_category_snapshot(...)` read, one local context/config normalization pass, and one final renderer pass. The save renderer group itself performs zero provider or database reads.
  - The save `html` fragment remains one complete local Secondary Vendors editor section with the same finite markup contract as the legacy partial: leading `p.description`; one outer `div#vms-secondary-vendors-section`; two hidden `input` fields; one inert `script[type="application/json"][data-vms-secondary-config]` node; optional empty-state `div.notice.notice-info.inline.vms-notice.vms-notice--info`; optional `p.description.vms-secondary-vendor-legend`; one live `div#vms-secondary-vendor-groups` tree containing fixed `div` / `label` / `span` / `select` / `option` / `input` / `button` / `a` / `p` / `ul` / `li` / `strong` children; one actions `p`; one live-status `p`; two `<template>` blocks; and one vendor-category-sync `div.notice.notice-info.inline.vms-notice.vms-notice--info`. Dynamic text remains limited to fixed translated labels plus normalized secondary-vendor boot-summary values and normalized vendor-category snapshot rows; HTML-like stored values remain inert through direct `esc_html()` / `esc_attr()` output.
  - The inert config contract remains unchanged for the initializer: exactly one `script[type="application/json"][data-vms-secondary-config]` node in the save fragment, no executable inline script, no additional script element, no handler attributes, and JSON emitted through `wp_json_encode((array) ($context['secondary_config'] ?? array()))`. `assets/js/vms-event-plan-secondary-vendors.js` still reads the node through `const configNode = section.querySelector('[data-vms-secondary-config]');` and `config = configNode ? JSON.parse(String(configNode.textContent || '{}')) : {};`. Hostile `</script><script>alert(1)</script>` content remains inert because the encoded JSON payload stores it as `<\/script>` text inside the application/json node rather than creating a second script element.
- Operation counts and adjacent-scope confirmation:
  - For Secondary Vendors save requests, the endpoint still performs one Event Plan type validation, one capability check, one nonce check, one mutation call, one post-mutation payload rebuild, and one success write.
  - Dirty-field calculation, repair-reason calculation, vendor-category snapshot refresh, calendar-maintenance queueing, and `Additional Vendors saved.` / `No Additional Vendor changes to save.` message selection remain in the unchanged mutation helper.
  - Accepted Compensation-options, supporting-vendor options, readiness-details, staff lazy-load, Secondary Vendors lazy-load, shared Administrator-shell contracts, raw notice sinks, and other Event Plan output families remained untouched in this slice.
- Focused coverage and validation: new coverage lives in `tests/event-plan-secondary-vendors-save-output-remediation.php`, which source-asserts the authenticated save hook / no-nopriv boundary, unchanged save request validation and success/error vocabulary, continued mutation-helper delegation, separation from the accepted lazy-load renderer, builder-layer read isolation, renderer-layer no-read guarantees, finite markup inventory, inert hostile config proof, boolean-parser parity, unchanged JavaScript replacement / initialization behavior, and DOM-level parity with the legacy `secondary-vendors` partial for representative rich and empty contexts through a package-local direct-renderer harness. Because the local WordPress bootstrap still loads the installed `vms` plugin instead of `packages/vms-github-reconcile`, this focused test stays package-source / direct-renderer based rather than mutating the installed plugin tree.
- Historical status at the time of this slice: `WPORG-24` remained open because the tracker still carried a broader Event Plan output-contract follow-up note. `WPORG-24R` later removes that stale residual after confirming that every current Event Plan HTML family already maps to an accepted tested child and remaining handlers are data-only JSON.

### `WPORG-24G` Event JSON-LD fallback script-sink result

- Result: the package-owned fallback Event JSON-LD emitter on `wp_head` now routes `vms_event_details_schema()` through `vms_event_details_encode_fallback_json_ld(array $schema): string` before echoing the unchanged `<script type="application/ld+json" class="vms-event-json-ld" data-vms-schema-mode="fallback">...</script>` wrapper.
- Selected family and producer / consumer contract:
  - Only the package-owned fallback emitter was changed: `vms_event_details_print_json_ld()` still registers on `add_action('wp_head', 'vms_event_details_print_json_ld', 30);` in `includes/public/event-details.php:792-824`.
  - Guard and fallback behavior remain unchanged: admin/non-singular exits, queried-event validation, `!vms_event_details_tec_schema_filters_available()` default-print gating, `vms_event_details_print_json_ld` filter opt-in/veto behavior, empty-schema no-output behavior, and encode-failure no-output behavior all remain intact.
  - `vms_event_details_schema()` remains the sole structured-data producer for this sink.
  - The new output boundary uses `wp_json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)` inside `vms_event_details_encode_fallback_json_ld()`, preserving decoded Event / Place / Organization / MusicGroup / Offer values while preventing literal `</script>` breakout in inline `application/ld+json`.
- Adjacent boundaries unchanged:
  - `vms_event_details_filter_tec_event_schema()` and `vms_event_details_filter_tec_json_ld_markup()` were inspected and left unchanged; the TEC-owned final JSON-LD emitter path remains separate.
  - Event Plan code, shared Administrator-shell output contracts, and visible event-page markup were not changed in this slice.
- Focused coverage and validation: new coverage lives in `tests/event-details-fallback-json-ld-output-remediation.php`, which proves the same `wp_head` registration, the same schema producer, the same fixed script attributes, the single-script DOM invariant, inert hostile `</script><script>alert(1)</script>` handling, preserved decoded URLs and Unicode, preserved Event / Place / Organization / MusicGroup / Offer vocabularies, no broad allowlist, and unchanged TEC hook registrations. Validation ran with `php -l includes/public/event-details.php`, `php -l tests/event-details-fallback-json-ld-output-remediation.php`, `php tests/event-details-fallback-json-ld-output-remediation.php`, `php tests/event-details-schema-normalization.php`, `php tests/plan-your-visit-sidebar-context.php`, `php tests/public-event-sidebar-guards.php`, `php tests/vendor-apply-inline-js-remediation.php`, `php tests/decoded-json-validation.php`, and `git diff --check`.
- Historical status at the time of this slice: `WPORG-24` remained open. This accepted `WPORG-24G` slice hardened only the package-owned fallback JSON-LD script sink, while the TEC-owned final emitters and broader `WPORG-24` boundaries remained separate. `WPORG-24R` later closes the parent by classifying the shared Administrator-shell raw page-owned sinks as intentional architecture outside the parent, accepting Settings entitlement image-sync as finite and verified safe, and removing the stale generic Event Plan HTML residual.

## F. Prefixing and Collision Safety

Status:

- No confirmed collision defect found in this pass.
- Strong compatibility-sensitive "do not blind-rename" conclusion.

Representative evidence:

- Taxonomies are prefixed: `includes/taxonomies/vendor-category.php:35`; `includes/taxonomies/vendor-type.php:314`
- REST namespaces are prefixed: `includes/rest/class-vms-rest-tours.php:15-39`; `includes/modules/admissions/rest.php:848-878`
- Shortcodes are prefixed: `includes/portal/vendor-portal.php:5212`; `includes/portal/staff-portal.php:15`; `includes/integrations/ticketing-verifications.php:1623`
- AJAX actions are prefixed: `includes/tours/class-vms-tours-service.php:76-77`; `includes/integrations/ticketing.php:526-822`; `includes/integrations/ticketing-phase-b.php:1980-2053`

Conclusion:

- The internal `vms` prefix is already pervasive and materially namespaced.
- Blindly renaming functions, actions, routes, handles, options, or meta keys to `backstage_venue_manager` would be a compatibility migration, not a safe scanner cleanup.
- Recommended batch: `WPORG-26` as a documentation / justification pass only unless a genuine unprefixed collision is later found.

## G. Hard-coded AJAX Endpoint Paths

Status:

- No confirmed runtime hard-coded AJAX URL defect found in this pass.
- One clear false-positive class identified.

Evidence:

- Runtime URL generation is generally correct with `admin_url('admin-ajax.php')`, including `includes/integrations/ticketing-rules-v2.php:6757-6765`, `includes/portal/vendor-portal.php:6221`, `includes/portal/staff-portal.php:2565`, `includes/social-share/event-plan-panel.php:452`, `includes/admin/addons/class-vms-admin-addons.php:64`, and `includes/modules/status-notices/admin-ui.php:71`.
- The hard-coded `/wp-admin/admin-ajax.php` strings in `includes/core/slow-request-logger.php:292-306` are diagnostic normalization keys, not live browser request construction.

Conclusion:

- This category is currently explanation-only.
- Recommended batch: none before the higher-risk categories above.

## H. File Upload Handling

Status:

- The original `H1` / `H2` / `H3` upload-handling findings are now fully remediated by committed `WPORG-20B`.
- `WPORG-21` remains as the historical tracking identifier for `H1` / `H2` / `H3`, but it is no longer an open duplicate implementation batch because the committed `WPORG-20B` work already completed that scope.
- `includes/integrations/ticketing-verifications.php:2130-2178` remains the preferred local reference implementation for upload prevalidation.

### `H1` Tax-profile upload hardening is now completed by committed `WPORG-20B`

- Severity: High
- Confidence: Confirmed
- References: `includes/admin/tax-profile-admin-metabox.php:102-118`; `includes/portal/vendor-tax-profile.php:121-137`; `includes/core/private-files.php:541-714`
- Why WordPress.org may object: client-reported MIME types were previously trusted before upload acceptance.
- Recommended remediation: route these uploads through server-side validated helper boundaries before persistence and serve them back through authenticated brokered download URLs. That work is now completed by commit `d1cdfbd80b05c8254cdc413d0e1bbb821ca13492` (`Harden uploaded file handling`).
- Compatibility or regression risk: Medium because both admin and portal flows touch operator workflows.
- Suggested remediation batch ID: `WPORG-20B`, historical verifier `WPORG-21`

### `H2` Event-plan CSV import hardening is now completed by committed `WPORG-20B`

- Severity: Medium
- Confidence: Confirmed
- References: `includes/admin/data-tools/actions-event-plan-import.php:13-54`; `includes/services/event-plan-import/event-plan-import-engine.php`
- Why WordPress.org may object: the importer previously stored uploads before sufficiently validating content and MIME.
- Recommended remediation: validate MIME, extension, and the normalized upload structure before persistence, then keep generated importer artifacts behind safe private storage keys. That work is now completed by commit `d1cdfbd80b05c8254cdc413d0e1bbb821ca13492` (`Harden uploaded file handling`).
- Compatibility or regression risk: Medium because the importer is operationally sensitive.
- Suggested remediation batch ID: `WPORG-20B`, historical verifier `WPORG-21`

### `H3` Private operational-file hardening is now completed by committed `WPORG-20B`

- Severity: Medium
- Confidence: Confirmed
- References: `includes/safety/private-files.php:177-221`; `includes/core/private-files.php:355-714`; `includes/core/staffing.php:620-690`
- Why WordPress.org may object: MIME trust and file-path persistence were previously too loose for private operational-file handling.
- Recommended remediation: validate file content and extension before persistence, record the verified type, broker downloads through authenticated handlers, and stop introducing new raw absolute-path persistence for these flows. That work is now completed by commit `d1cdfbd80b05c8254cdc413d0e1bbb821ca13492` (`Harden uploaded file handling`).
- Compatibility or regression risk: Medium because this storage layer is intentionally private and should not be rewritten casually.
- Suggested remediation batch ID: `WPORG-20B`, historical verifier `WPORG-21`

Acceptable pattern:

- `includes/integrations/ticketing-verifications.php:2130-2178` already uses `wp_check_filetype_and_ext()`, size checks, image optimization, and guarded file movement. That flow is the best local pattern to copy.

## I. Output Buffering

Status:

- Original reviewer lifecycle concerns are now closed under `WPORG-25`.
- Current repository evidence shows the surviving output buffers in this scope are either same-function local captures or the one request-global AJAX opener with verified explicit ownership.

### `I1` Global AJAX output buffer now has verified explicit response ownership

- Severity: Medium
- Confidence: Confirmed
- Historical reviewer citations from July 1, 2026: `includes/integrations/load.php:8` `ob_start();` plus the historical cleanup examples in `includes/integrations/ticketing.php`; these line numbers are preserved as reviewer-source history only.
- Current runtime owner family:
  - `includes/integrations/load.php` still opens one request-global AJAX buffer only under `DOING_AJAX` and the boolean ownership flag.
  - The seven legacy ticketing admin callbacks in `includes/integrations/ticketing.php` now terminate through `vms_ticketing_ajax_send_success()` / `vms_ticketing_ajax_send_error()`, which route through `vms_ticketing_ajax_attach_noise()` and close the owned buffer in the same logical response flow.
  - The three public V2 cart/context callbacks in `includes/integrations/ticketing-rules-v2.php` terminate through `vms_ticketing_v2_ajax_send_success()` / `vms_ticketing_v2_ajax_send_error()`, which discard the owned buffer before delegating to WordPress JSON responders.
  - The eleven Phase B callbacks in `includes/integrations/ticketing-phase-b.php` terminate through those cleanup-only wrappers or the approved `vms_ticketing_v2_ajax_send_json_success_fast()` helper, which drains all active levels before sending the payload.
  - The two customer-claims callbacks in `includes/integrations/ticketing-claims-customer.php` terminate through the same cleanup-only wrappers.
- Direct evidence: `tests/ticketing-output-buffer-lifecycle-characterization.php`; `tests/ticketing-v2-ajax-output-buffer-ownership.php`; `tests/ticketing-phase-b-ajax-output-buffer-ownership.php`; `tests/ticketing-claims-ajax-output-buffer-ownership.php`.
- Current verified outcome: no direct-send AJAX responder remains beneath the opener, no reachable nested plugin-owned buffer was found in the audited callback paths, and the remaining boolean ownership flag is a theoretical compatibility note rather than a demonstrated current defect.
- Final status: `WPORG-25` verified this family without changing the request-global opener itself.

### `I2` Ticketing Rules V2 full-page callback buffers were replaced with native TEC boundaries

- Severity: Medium
- Confidence: Confirmed
- Historical reviewer citations from July 1, 2026: `includes/integrations/ticketing-rules-v2.php:5860` for the old My Tickets callback buffer and `includes/integrations/ticketing-rules-v2.php:7113` for the old server-mount callback buffer; these line numbers are preserved as historical reviewer-source references only because the cited runtime has now been removed.
- Current My Tickets architecture:
  - No My Tickets `template_redirect` buffer registration remains.
  - No My Tickets `ob_start()` or full-page regex rewrite remains.
  - Native ownership now uses `tec_tickets_my_tickets_link_ticket_count_by_type` via `vms_ticketing_v2_filter_my_tickets_link_ticket_count_by_type()`.
  - The VMS active-ticket-count helper remains unchanged, zero count suppresses the notice, Event Tickets still owns singular or plural wording, and frontend `myActiveTicketCount` localization remains intact.
- Current server-mount architecture:
  - No server-mount `template_redirect` registration, boot callback, output callback, rendered-HTML disabled-row stripping, or PHP submit-button regex rewrite remains.
  - Reserved add-ons now mount through `tribe_template_before_include_html:tickets/v2/tickets/footer` using `vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount()`.
  - Disabled ticket products now suppress natively through `tribe_tickets_get_tickets_query_args` using `vms_ticketing_v2_filter_disabled_ticket_query_args()` after the unchanged cancelled-event filter.
  - `vms_ticketing_v2_append_entitlements_to_tec_event()` remains as a fallback only when native footer placement did not succeed.
  - Shortcode and manual renderer contracts remain intact, the PHP renderer remains declarative, and no inline-controller or runtime-sidecar dependency remains in PHP.
- Direct evidence: `tests/ticketing-my-tickets-notice-source-filter-remediation.php`; `tests/ticketing-server-mount-native-footer-remediation.php`; `tests/ticketing-disabled-ticket-native-suppression-remediation.php`; `tests/ticketing-server-controls-inline-js-remediation.php`; `tests/ticketing-output-buffer-lifecycle-characterization.php`; `tests/event-plan-legacy-ticketing-integration-smoke.php`.
- Final status: `WPORG-25` verified this family and no equivalent full-page callback-buffer residual remains.

## J. Internationalization

Status:

- `WPORG-18A` parser-compliance remediation is now applied and verified in the mirror and corresponding live tree.
- `WPORG-18B` translator-comment and placeholder-order remediation is now applied and verified in the mirror and corresponding live tree.
- `WPORG-18D` final semantic translator-comment audit is now applied and verified in the mirror and corresponding live tree.
- No actionable i18n parser, translator-comment, or placeholder-order findings remain in the verified working tree.

Evidence count:

- Pre-remediation `vms_i18n_runtime()` call sites found in this pass: `54`
- Pre-remediation `VMS_TEXTDOMAIN` references found in this pass: `22`
- Direct runtime gettext-parser violations confirmed before remediation: `5` in the mirror runtime tree, with matching live-tree violations in the corresponding files

### `J1` Dynamic gettext wrapper and non-literal domain usage break parser compatibility

- Severity: High
- Confidence: Confirmed
- References: `includes/core/registry/admin-menu.php:19-30`; `includes/core/registry/admin-menu.php:81-214`; `includes/core/registry/statuses.php:13-21`; `includes/core/registry/statuses.php:34-70`; `includes/admin-ui/nav.php:781-787`; `includes/tours/tours.php:64`; `includes/modules/staff-tasks/notifications.php:337`; `includes/social-share/queue-runner.php:66`
- Why WordPress.org may object: WordPress.org translation tooling expects literal source strings and literal text domains in gettext-family calls. A wrapper that accepts `$text` and `$domain` at runtime prevents reliable extraction.
- Recommended remediation: convert these wrapper calls back to literal gettext invocations or redesign the wrapper so it does not obscure literal strings from parsers. Do not pass dynamic runtime labels through gettext unless the source strings remain literal at the call site. That remediation is now applied in the working tree and verified by zero token-aware parser violations across mirror/live, successful mirror POT extraction, and a clean live `wp plugin check vms --checks=i18n_usage --slug=backstage-venue-manager` run.
- Compatibility or regression risk: Medium because the wrapper is spread across admin registry and status label surfaces.
- Suggested remediation batch ID: `WPORG-18A`

Already resolved note:

- Many direct gettext calls already use the literal `'backstage-venue-manager'` domain correctly. The blocker is the remaining wrapper and constant-mediated path, not the entire tree.

## K. Admin Notices and Dashboard Scope

Status:

- `WPORG-23` is complete in the current mirror history.
- One pre-existing top-nav system that should be documented, not conflated with the global notice problem.

### `K1` First-run notice is now restricted to VMS-owned admin screens

- Severity: High
- Confidence: Confirmed complete
- References: `includes/admin-ui/context.php`; `includes/admin/admin-notices.php`
- Remediation outcome: the existing first-run/setup notice still uses the same option flag, dismissal action/nonce, CTA, capability gate, notice class, and copy, but now returns early unless the current screen is a real VMS-owned admin screen recognized through the shared notice-scope predicate.
- Operator-visible effect: unrelated WordPress admin screens such as Dashboard, Plugins, Posts, Pages, Media, Comments, Users, Appearance, Tools, Settings, unrelated CPTs, and unrelated plugin pages no longer receive this notice.
- Compatibility or regression risk: Low.
- Suggested remediation batch ID: `WPORG-23`

### `K2` Diagnostics and payment-health notices are now restricted to VMS-owned or exact Ticket Integrity screens

- Severity: High
- Confidence: Confirmed complete
- References: `includes/admin-ui/context.php`; `includes/runtime-guards.php`; `includes/ticketing/ticket-integrity-payment-gateway-health.php`
- Remediation outcome: both global health/diagnostic notice systems now use the shared notice-scope predicate, which requires a real current screen object, rejects AJAX/REST/cron/front-end contexts, and then delegates to the existing VMS screen classifier.
- Operator-visible effect: runtime diagnostics and payment-gateway health notices continue to render on VMS-owned screens, including Event Plan edit/new and the dedicated Ticket Integrity page, while unrelated WordPress admin screens no longer receive them.
- Compatibility or regression risk: Low to Medium because operators may rely on current visibility.
- Suggested remediation batch ID: `WPORG-23`

Explain-only note:

- `includes/admin-ui/nav.php:547` uses `all_admin_notices`, but that top-nav system is intentionally VMS-screen scoped and should be reviewed separately from the global notices above.

## L. Licensing and Third-Party Dependencies

Status:

- No current package-scope licensing blocker remains in the verified public-core package.
- One clean dependency inventory item worth preserving.

Bundled / included dependency inventory from this pass:

| Dependency | Evidence | License | GPL compatibility | Included in public package? | Notes |
| --- | --- | --- | --- | --- | --- |
| Driver.js | `assets/vendor/driverjs/LICENSE.txt:1-13` | MIT | Compatible | Yes | License file present in source. |

External-service inventory visible in the current readme:

- Cloudflare Turnstile: `readme.txt:80-84`
- QRServer / goQR.me: `readme.txt:86-89`
- Vendor-provided ICS URLs: `readme.txt:92-95`
- Operator-configured webhook endpoints: `readme.txt:97-100`

Conclusion:

- The bundled Driver.js dependency looks acceptable from the evidence inspected here.
- The historical add-ons / Freemius package-scope behavior tracked in `A1` is no longer present in the current public-core package.
- `WPORG-27` remains appropriate only for any later broad dependency-inventory or tooling-reproducibility review beyond this verified public-package prereview.

## M. Release Metadata and Package Consistency

Status:

- The public release metadata boundary remains verified: the mirror source and disposable public package still align on `1.2.0`.
- `WPORG-28R` reopened packaged residual reconciliation on `2026-07-25`, so submission readiness is no longer treated as resolved by `WPORG-28Q` alone.
- Remaining WordPress.org submission actions and live-production convergence work stay separate from the verified metadata boundary, but they remain blocked behind the reopened `WPORG-28R` packaged audit.

### `M1` Public-core release metadata boundary is now defined and verified

- Severity: High
- Confidence: Confirmed
- References: `vendor-management-system.php:3-13`; `readme.txt:4-9`; `vms-build.txt:1`; `vms/vendor-management-system.php:3-13`; `vms/readme.txt:4-9`; `vms/vms-build.txt:1`
- Current status: the mirror source and fresh disposable public package now align on public version `1.2.0`, public slug `backstage-venue-manager`, matching packaged header and readme metadata, and build marker `1.2.0`, while the sibling live local `vms` tree intentionally remains `1.1.0` as a separate production-convergence boundary rather than a public-package blocker.
- Recommended remediation: keep the `1.2.0` metadata boundary unchanged, but do not treat it as authorization for final artifact preparation, upload/submission, or reviewer communication until the reopened packaged residual audit in `WPORG-28R` is resolved.
- Compatibility or regression risk: Low.
- Suggested remediation batch ID: `WPORG-28R`

Decision notes:

- The current release tooling now resolves the public package directory / slug expectation explicitly: public packages build as `backstage-venue-manager/`, while the local runtime directory may remain `vms`.
- `vms.php:1-12` is currently a compatibility shim that delegates to `vendor-management-system.php`. That remains acceptable internally, and the release builder plus compatibility harness now validate the public ZIP folder and canonical public basename deliberately rather than inferring them from the checkout root.
- The fresh `WPORG-28Q` package audit proved the packaged header version, readme stable tag, changelog, upgrade notice, and `vms-build.txt` marker all align on `1.2.0` without any runtime or release-metadata edit in that closeout; `WPORG-28R` later confirmed the same metadata alignment while reopening the packaged residual finding audit.

## N. Plugin Check and Scanner Reproducibility

Status:

- Packaged static Plugin Check is now reproducible enough to use as a final local prereview gate when invoked against a concrete extracted public package and its known leading WP-CLI deprecation noise is normalized away.
- Live or runtime-oriented Plugin Check workflows still remain more brittle in this local environment.
- This section records release-gate tooling context and is not part of the `15` confirmed / `6` likely remediation-item totals above.

Commands run in this audit:

| Command | Result |
| --- | --- |
| `php -v` | `PHP 8.5.3 (cli)` |
| `wp --info` | `WP-CLI 2.12.0`; emits PHP 8.5 deprecation noise from bundled dependencies before normal output |
| `wp plugin check --help` | Command path is present locally, but the invocation fatals with `Invalid plugin: Plugin parameter must not be empty.` in the current environment |
| `phpcs --version` | `command not found` |
| `rg --files packages/vms-github-reconcile/scripts packages/vms-github-reconcile/tests` | Project-specific release and compatibility scripts are present |
| `rg --files packages/vms-github-reconcile | rg '(^|/)(composer\\.json|package\\.json|phpcs\\.xml|phpcs\\.xml\\.dist)$'` | No local Composer, npm, or PHPCS config file found at the audited repo root |

Supporting evidence from the local Plugin Check installation:

- `plugin-check/readme.txt:21-23` confirms `wp plugin check` can scan a plugin file, path, or ZIP.
- `plugin-check/readme.txt:22` also confirms runtime checks need the `--require=./wp-content/plugins/plugin-check/cli.php` workaround.

Historical project-specific audit assets already present:

- `docs/plugin-check-1.0.0-raw.txt`
- `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`
- `docs/WPORG_PLUGIN_CHECK_HEATMAP_1.0.0.md`

Conclusion:

- The later `WPORG-28Q` closeout proved that a fresh packaged `wp plugin check` rerun can return exit `0` plus a valid strict-json payload when invoked against the extracted public package with a concrete target and the documented field export.
- `phpcs` is still missing, WP-CLI still emits PHP 8.5 deprecation noise ahead of the JSON payload, and live or runtime `--require` workflows remain outside this verified packaged gate.
- `WPORG-27` remains appropriate only for any future broader dependency-inventory or tooling review beyond this final public-package prereview.

## Proposed Codex Task Sequence

Recommended follow-up order, keeping each pass narrow:

1. `WPORG-19A - Nonce verification input normalization`
   - Scope: `C1` normalization/sanitization only
   - Result: completed in the current working tree
2. `WPORG-19B - Missing nonce and capability/authorization follow-up`
   - Scope: remaining section C concerns outside normalization
   - Result: completed in the current working tree after a full runtime handler inventory; no additional missing-nonce additions were required, and the remaining shared object-level / direct-dispatch authorization gaps were hardened without broad functional changes
3. `WPORG-20A - Ordinary request-global sanitization`
   - Scope: request globals, server values, request wrappers, redirect parameters, escape-versus-sanitize fixes, and `FILTER_UNSAFE_RAW`
   - Result: completed in the current working tree with shared mirror/live runtime alignment
4. `WPORG-20B - Upload transport and MIME handling follow-up`
   - Scope: upload transport, MIME/type trust, and file-move validation paths intentionally excluded from `WPORG-20A`
   - Result: completed by committed mirror change `d1cdfbd80b05c8254cdc413d0e1bbb821ca13492` (`Harden uploaded file handling`)
5. `WPORG-20C - Decoded JSON and structured-payload validation`
   - Scope: decoded JSON/body shape validation, response-shape review, and schema-like per-key checks intentionally excluded from `WPORG-20A`
   - Result: completed in the current mirror history, including the corrective tours REST JSON boundary fix
6. `WPORG-21 - Upload handling hardening`
   - Scope: `H1`, `H2`, `H3`
   - Result: historical tracking identifier only; the mapped `H1` / `H2` / `H3` implementation was fully completed by committed `WPORG-20B`, so no separate `WPORG-21` implementation batch remains open unless a future verification uncovers a concrete regression
7. `WPORG-22 - Inline asset enqueue migration`
   - Scope: `B1`, `B2`, `B3`, `B4`, `B5`
   - Goal: move executable JS/CSS out of inline PHP output
   - Result: completed in the current mirror history. `B1` is closed by the Event Plan external-asset closeout; `B2`, `B3`, `B4`, and `B5` remain completed by their recorded sub-passes.
8. `WPORG-23 - Admin notice scope`
   - Scope: `K1`, `K2`
   - Goal: keep notices on VMS-owned screens only
   - Result: completed in the current mirror history via a shared notice-scope helper in `includes/admin-ui/context.php`, with the first-run, runtime-diagnostic, and payment-health notices limited to VMS-owned or exact Ticket Integrity screens only
9. `WPORG-24 - Output escaping contract pass`
   - Scope: `E1`
   - Goal: separate genuine escaping defects from safe HTML/JSON patterns
   - Result: completed by the accepted `WPORG-24A` through `WPORG-24Q` slices and the documentation-only `WPORG-24R` closeout; no active `WPORG-24` implementation residual remains
10. `WPORG-25 - Output buffer lifecycle review`
   - Scope: `I1`, `I2`
   - Goal: document and tighten buffer ownership without blind removals
   - Result: completed by the characterization baseline `2f8d8e16bb5a6842f5e2aae60cb027aa1cb30d3e` (`Characterize ticketing output buffer lifecycle`), the implementation commits `b5de3cd39f528b39b430cf12feec1725262f7fdb` (`Add explicit V2 AJAX buffer cleanup wrappers`), `e65d62de12e16708a8c755d7ac8a89f265cf16cb` (`Normalize Phase B AJAX buffer cleanup ownership`), `92bc9ff8e226c716a851a639662b0e6943933ee4` (`Route customer-claims AJAX through cleanup wrappers`), `5458dad4e0e7e430bd2a8b0d961582decc44108c` (`Replace My Tickets full-page buffer with TEC count filter`), `3b23ec96b59be61fc06be68bd7faf00aed852352` (`Replace server-mount buffer with native footer placement`), and the documentation-only closeout below; no active `WPORG-25` lifecycle residual remains in the current repository
11. `WPORG-26 - Prefix and collision review`
    - Scope: section F only
    - Goal: document why the existing `vms` internal namespace is intentional and compatibility-sensitive
12. `WPORG-27 - Dependency, licensing, and tooling reproducibility verification`
    - Scope: section L and section N
    - Goal: final dependency inventory, disclosure check, and reproducible scanner setup
13. `WPORG-28 - Release metadata and packaging validation`
    - Scope: `M1`
    - Goal: choose the public version and validate final ZIP / slug expectations
    - Result: the `1.2.0` public-core decision and disposable package validation remain complete; `WPORG-28R` reopened submission readiness, `WPORG-28R-A` removed the lone new packaged warning, and the parent remains blocked by the surviving unmapped or still-blocking residual families

## Findings Requiring User or Product-Owner Decisions

1. Whether to authorize a new runtime remediation child for the packaged residual families reopened under `WPORG-28R` before any WordPress.org artifact preparation, slug-reservation request, corrected upload, or reviewer communication.
2. Whether to authorize the separate Outreach extraction, duplicate-core safety, and live-production replacement or migration sequence for the active `vms` runtime after the reopened `WPORG-28R` package audit is resolved.

## Findings Requiring Explanation Rather Than Code Changes

- TEC-owned final JSON-LD markup after the separate package-owned fallback hardening in `includes/public/event-details.php:780-824`
- `application/json` state blobs in `includes/admin/addons/views/page-addons.php:53`, `includes/admin/vendor-command-center.php:1498`, `includes/admin/vendor-command-center.php:1556`, and `includes/cpt/event-plans/partials/secondary-vendors.php:465`
- Log-normalization strings in `includes/core/slow-request-logger.php:292-306`
- Established `vms` internal namespace across CPTs, AJAX, REST, and shortcodes
- Read-only status-notice request-context collection in `includes/modules/status-notices/front.php:26-29` and `includes/modules/status-notices/front.php:84`
- Driver.js license compatibility and inclusion status

## Final Release-Gate Checklist

- [x] Decide whether the public package will retain any premium add-ons / licensing surface.
- [x] Resolve the public-core release version and synchronize all public metadata markers.
- [x] Run the final `WPORG-18B` parser/extraction audit after the `WPORG-18A` code remediation.
- [x] Complete `WPORG-19A` nonce verification input normalization in legacy save, admin-post, AJAX, REST-wrapper, and frontend mutation handlers.
- [x] Complete `WPORG-19B` missing-nonce and capability/authorization follow-up before packaging the final public submission build.
- [x] Complete `WPORG-20A` ordinary request-global sanitization, redirect allowlisting, and server-value normalization without mixing upload or decoded-JSON refactors.
- [x] Complete `WPORG-20B` upload transport and MIME/type hardening across tax-profile, import, and private-file flows.
- [x] Complete `WPORG-20C` decoded JSON / structured-body validation after the ordinary request-global pass.
- [x] Complete `WPORG-22` inline asset enqueue migration for B1-B5.
- [x] Scope all admin notices to VMS-owned screens.
- [x] Re-run Plugin Check in a controlled release-gate environment with a concrete plugin target and documented runtime/static mode.
- [x] Reconfirm external-service disclosures after the package-scope decisions above.
- [x] Validate the final public ZIP folder, slug, and version before any packaging or submission work.

## WPORG-17B Result

Date: 2026-07-10

### Summary

- Result: `PASS`
- Scope completed: package-scope trialware, premium add-on, and Freemius remediation in the public core plugin, applied to both `packages/vms-github-reconcile` and `vms/`
- Starting mirror HEAD: `1b82ba0336b93f998503d53a1f646229c63226af`
- Ending mirror HEAD after working changes in this batch: unchanged locally; no commit created in this task

### What Was Confirmed

- The WordPress.org-facing core package did not contain any currently registered core module marked `'premium' => true`.
- The noncompliant surface was concentrated in a dedicated add-ons admin subsystem that bundled:
  - a `Premium Add-ons` menu page,
  - ZIP installation and update actions,
  - license-key storage and validation UI,
  - custom Freemius remote licensing calls,
  - Freemius reachability diagnostics,
  - add-on manifest metadata for separately distributed products.
- The current bundled add-on manifest identified:
  - `Meta Ads Builder` as a separate companion plugin target (`vms-meta-ads/vms-meta-ads.php`),
  - `Show Risk Advisor` as a separate companion plugin target (`vmsx-weather-risk/vmsx-weather-risk.php`),
  - `Social Sharing` as a core-bundled feature, not a separately distributed paid add-on.
- Other named items from the prereview brief were either absent from the inspected current public package as premium bundles, or were ordinary core/plugin-integration references rather than bundled locked implementations.

### What Was Changed

- Removed the bundled premium add-ons / Freemius admin subsystem from the public core plugin load path in both trees.
- Removed the `vms-addons` page link from VMS admin navigation and screen-context routing in both trees.
- Removed the `vms-addons` page registration from the admin-page registry in both trees.
- Revised the core-module compatibility helper so the WordPress.org package no longer reads `vms_premium_modules_enabled` or ships local premium-license enforcement logic by default.
- Updated both `readme.txt` files to remove Freemius as a current external service and to describe optional add-ons as separately distributed companion plugins that are detected, not installed/licensed/unlocked, by the core plugin.

### What Was Removed

- Mirror and live removals:
  - `assets/admin/addons/addons.css`
  - `assets/admin/addons/addons.js`
  - `assets/admin/addons/manifest-addons.json`
  - `includes/admin/addons/class-vms-addons-health.php`
  - `includes/admin/addons/class-vms-addons-installer.php`
  - `includes/admin/addons/class-vms-addons-licensing.php`
  - `includes/admin/addons/class-vms-addons-logger.php`
  - `includes/admin/addons/class-vms-addons-manifest.php`
  - `includes/admin/addons/class-vms-admin-addons.php`
  - `includes/admin/addons/views/page-addons.php`

### What Was Retained and Why

- `vms_module_is_licensed()`, `vms_module_is_enabled()`, and the `vms_premium_module_licensed` filter name were retained as compatibility hooks so separately distributed companion plugins can continue to report their own availability state without forcing a broad identifier migration.
- In the remediated public core package, those identifiers no longer read license options, perform paid-plan or trial checks, enforce quota state, or trigger remote entitlement / license validation. `vms_module_is_licensed()` now defaults registered modules to available and only exposes a compatibility filter that separate companion plugins may use for their own availability reporting.
- No currently bundled core module is registered with `'premium' => true`, so the retained compatibility layer does not disable or hide any local functionality shipped in the WordPress.org core package.
- The public plugin remains fully functional without license validation, while companion plugins remain separately distributed rather than bundled in disabled form inside the WordPress.org package.
- Generic companion-plugin discovery and extension points were retained, including:
  - existing `vms_register_admin_page()` / registry contracts,
  - existing page/context identifiers other companion plugins may rely on,
  - passive companion-plugin references such as `Meta Ads Builder not active` and the vendor-portal dashboard extension hook for companion add-ons.
- Core runtime features such as Social Sharing, ticketing add-ons / entitlements, and other non-licensing add-on terminology were retained because they describe included functionality or neutral compatibility hooks rather than paid unlock flows.

### Package-Boundary Conclusions

- `Backstage Venue Manager` on WordPress.org should ship as a fully functional core plugin without the bundled premium add-ons / Freemius management surface.
- `Meta Ads Builder` and `Show Risk Advisor` are separate companion-plugin targets, not bundled free-core functionality that should remain in disabled form inside the WordPress.org package.
- `Social Sharing` is core-bundled functionality and should remain in the public package.
- No code path reviewed in this batch still requires a license key, payment, entitlement response, or Freemius account to unlock code that remains bundled in the WordPress.org core package.

### Freemius Conclusions

- No Freemius SDK or bootstrap was found in the current public package.
- The Freemius-related code in scope was a custom licensing / validation / diagnostics layer used only for add-on licensing and premium-management workflows.
- Final disposition for the public core package: removed.
- Freemius was also removed from the WordPress.org-facing readme external-services disclosure because it is no longer part of the public core package after this batch.

### External Services After Remediation

- Retained in the public core package:
  - Cloudflare Turnstile
  - QRServer / goQR.me
  - Vendor-provided ICS calendar URLs
  - Operator-configured webhook endpoints
- Removed from the public core package:
  - Freemius licensing / validation / health-check traffic

### Remaining Product-Owner Decisions

- No new WPORG-17B stop-condition decision was required to complete this batch.
- Previously documented release-level decisions still remain outside this batch, including the `1.0.0` vs `1.1.0` metadata decision and final public package / slug validation.

### Remaining Follow-Up Batches

- `WPORG-26` — Prefix and collision review if a dedicated explanation-only pass is later authorized
- `WPORG-27` — Dependency, licensing, and tooling reproducibility verification for the final release gate
- `WPORG-28` — Release metadata and packaged prereview validation

### Verification Results

- Confirmed mirror starting HEAD matched `1b82ba0336b93f998503d53a1f646229c63226af`.
- Confirmed mirror worktree started clean and `stash@{0}` remained unchanged in this batch.
- Confirmed the bundled add-ons admin load points, page wiring, UI assets, and Freemius-specific runtime files were removed from both mirror and live trees.
- Confirmed the core readme no longer lists Freemius as an active external service and no longer describes core-plugin licensing actions for optional add-ons.
- Confirmed the public core package no longer reads `vms_premium_modules_enabled` for local module unlocking.
- Confirmed separately distributed companion-plugin compatibility hooks were retained rather than renamed.
- Detailed command verification for this batch is recorded in the task report rather than duplicated inline here.

## WPORG-18A Result

Date: 2026-07-10

### Summary

- Result: `PASS`
- Scope completed: parser-compliance remediation for wrapper-mediated gettext usage and dynamic Event Plan notice strings, applied to both `packages/vms-github-reconcile` and `vms/`
- Starting mirror HEAD: `99bdeaddeab7e1181573f886169ae60588c52d39`
- Ending mirror HEAD after working changes in this batch: unchanged locally; no commit created in this task

### Audit Totals

- Post-remediation gettext-family runtime calls audited with token-aware scans: `9,576` in `packages/vms-github-reconcile`, `10,903` in `vms/`, `20,479` combined
- Direct non-literal source-string findings fixed: `5` in the mirror runtime tree and the `5` corresponding live-tree matches
- Direct non-literal context findings fixed: `0`
- Direct non-literal domain findings fixed: `1` in the mirror runtime tree and the `1` corresponding live-tree match
- Wrapper-mediated parser blockers removed from core runtime: `54` pre-remediation `vms_i18n_runtime()` call sites in the mirror, with corresponding live-tree matches

### Wrappers Reviewed

- Reviewed wrapper: `vms_i18n_runtime()`
- Additional project-defined gettext wrappers found in the audited mirror/live runtime trees: none confirmed beyond `vms_i18n_runtime()`

### `vms_i18n_runtime()` Disposition

- Retained the function name and callable signature as a compatibility shim.
- Removed its internal generic `__($text, $domain)` behavior so core runtime code no longer feeds arbitrary runtime values into gettext through the wrapper.
- Converted core runtime call sites to direct literal gettext calls using the literal domain `'backstage-venue-manager'`.

### What Was Changed

- Replaced core wrapper-mediated labels in:
  - `includes/core/registry/admin-menu.php`
  - `includes/core/registry/statuses.php`
  - `includes/admin-ui/nav.php`
  - `includes/tours/tours.php`
  - `includes/social-share/queue-runner.php`
  - `includes/modules/staff-tasks/notifications.php`
- Rewrote the four dynamic Event Plan admin notices in `includes/cpt/event-plans.php` to use literal gettext strings with placeholders instead of translating `$msg` at runtime.
- Applied the same semantic changes to the corresponding files in `vms/`.

### Files Changed

- Mirror:
  - `packages/vms-github-reconcile/includes/core/registry/admin-menu.php`
  - `packages/vms-github-reconcile/includes/core/registry/statuses.php`
  - `packages/vms-github-reconcile/includes/admin-ui/nav.php`
  - `packages/vms-github-reconcile/includes/tours/tours.php`
  - `packages/vms-github-reconcile/includes/social-share/queue-runner.php`
  - `packages/vms-github-reconcile/includes/modules/staff-tasks/notifications.php`
  - `packages/vms-github-reconcile/includes/cpt/event-plans.php`
  - `packages/vms-github-reconcile/docs/WPORG_PREREVIEW_REMEDIATION.md`
- Live:
  - `vms/includes/core/registry/admin-menu.php`
  - `vms/includes/core/registry/statuses.php`
  - `vms/includes/admin-ui/nav.php`
  - `vms/includes/tours/tours.php`
  - `vms/includes/social-share/queue-runner.php`
  - `vms/includes/modules/staff-tasks/notifications.php`
  - `vms/includes/cpt/event-plans.php`

### Compatibility Decisions

- Preserved `vms_i18n_runtime()` as a callable compatibility shim rather than deleting or renaming it.
- Preserved `VMS_TEXTDOMAIN` itself; only removed it from gettext execution paths.
- Did not rename internal `vms` identifiers, hooks, menu slugs, options, or metadata.

### Translator-Comment Improvements

- Added translators comments for the remediated Event Plan notice templates that now use placeholders for:
  - default pay label plus action label
  - default pay label fallback warning
  - selected guarantee, maximum guarantee, and action label
  - selected guarantee plus maximum guarantee fallback warning

### Verification Performed

- Confirmed mirror start state:
  - `git rev-parse HEAD` matched `99bdeaddeab7e1181573f886169ae60588c52d39`
  - `git status --short` was clean before edits
  - `stash@{0}` existed as `WPORG-16D preserve unrelated sidebar+doc work` and was not touched
- Ran token-aware runtime gettext scans before remediation to locate non-literal source/domain findings in both trees.
- Re-ran targeted wrapper and gettext searches after remediation.
- Ran `git status --short`, `git diff --check`, and `php -l` on each changed PHP file in both trees.
- Ran the listed local project tests and a safe local extraction check where available in the current environment.
- Ran `wp plugin check vms --checks=i18n_usage --slug=backstage-venue-manager` against the local site environment. With the slug override in place, the earlier `TextDomainMismatch` noise disappeared, but Plugin Check still reported broader pre-existing placeholder-comment issues outside this narrow remediation batch.

### Remaining I18n Questions / False Positives

- A token scan can false-positive on gettext calls that place a `translators:` comment directly inside the argument list, such as `includes/modules/admissions/pass-claims.php`; manual inspection confirmed that cited call still uses a literal source string and literal domain.
- The broader `MissingTranslatorsComment`, unordered-placeholder, and mixed-placeholder findings that remained after `WPORG-18A` were subsequently remediated in `WPORG-18B`.
- Final mirror POT extraction, mirror/live token-aware scans, and the live `wp plugin check vms --checks=i18n_usage --slug=backstage-venue-manager` run are now clean; see `WPORG-18B Result` below.

## WPORG-18B Result

Date: 2026-07-10

### Summary

- Result: `PASS`
- Scope completed: translator-comment remediation, placeholder numbering/order remediation, final extraction verification, and mirror/live alignment for the combined `WPORG-18` batch
- Starting mirror HEAD: `99bdeaddeab7e1181573f886169ae60588c52d39`
- Ending mirror HEAD after working changes in this batch: unchanged locally; no commit created in this task

### Baseline Plugin Check Totals

- Baseline live run recorded `685` actionable i18n findings:
  - `664` `WordPress.WP.I18n.MissingTranslatorsComment`
  - `20` `WordPress.WP.I18n.UnorderedPlaceholdersText`
  - `1` `WordPress.WP.I18n.MixedOrderedPlaceholdersText`
- Baseline findings spanned `68` PHP files in the combined source snapshot.
- Split at capture time:
  - `545` findings in files present in both the mirror and live trees (`529` missing-comment, `16` unordered-placeholder)
  - `140` findings in live-only outreach/admissions files absent from the mirror (`135` missing-comment, `4` unordered-placeholder, `1` mixed-placeholder)
- Live-only files absent from the mirror:
  - `vms/includes/modules/admissions/outreach-recipients.php`
  - `vms/includes/modules/admissions/outreach.php`
  - `vms/includes/modules/outreach/admin-ui.php`
  - `vms/includes/modules/outreach/contacts.php`

### Remediation Completed

- Inserted parser-visible translator comments in bulk with a tokenized helper:
  - `511` comments in `packages/vms-github-reconcile`
  - `661` comments in `vms`
- Applied targeted manual follow-up where bulk comments were not extractor-visible in mixed PHP/HTML blocks and where multi-value strings still needed numbering or ordering fixes.
- Numbered `21` scanner-flagged multi-value placeholder sequences and corrected the `1` mixed ordered-placeholder defect.
- Resolved the final mirror/live alignment miss in `includes/modules/email-followups/admin-ui.php` so the mirror now matches the already-clean live placeholder ordering for the manual-send notice.

### Verification Performed

- `git diff --check`: clean
- `php -l` passed for all `66` changed mirror PHP files
- `php -l` passed for the `66` shared live counterparts plus the `4` additional live-only outreach/admissions files absent from the mirror
- Token-aware gettext scan after remediation:
  - mirror: `9,576` audited gettext-family calls, `0` non-literal source/context/domain violations
  - live: `10,903` audited gettext-family calls, `0` non-literal source/context/domain violations
- Remaining `vms_i18n_runtime()` references: compatibility-shim definitions only in:
  - `packages/vms-github-reconcile/includes/core/registry/admin-menu.php`
  - `vms/includes/core/registry/admin-menu.php`
- Final mirror multi-placeholder scan: `0` remaining unnumbered multi-placeholder gettext strings
- POT extraction:
  - `wp i18n make-pot` succeeded for `packages/vms-github-reconcile`
  - generated a `31,060`-line POT at `packages/vms-github-reconcile/.codex-temp/backstage-venue-manager.pot`
  - verified extracted updated strings/comments including:
    - `Manual send step complete: %1$d sent, %2$d skipped, %3$d errors.`
    - `Last updated: %1$s by %2$s`
    - `Because %1$s was cancelled, we have issued an event credit for %2$s.`
- Plugin Check:
  - `wp plugin check vms --checks=i18n_usage --slug=backstage-venue-manager` completed successfully
  - final result: `Success: Checks complete. No errors found.`
  - stderr contained only upstream WP-CLI / PHP `8.5` deprecation noise, not plugin findings
- Existing project tests passed:
  - `php tests/runtime-stub-guards.php`
  - `php tests/release-compatibility-harness.php`
  - `php tests/public-release-build-pipeline.php`

### Notes / Acceptable Follow-Up

- The live Plugin Check result is clean and the mirror extraction/parser checks are clean.
- Many bulk-added translator comments still use heuristic fallback wording rather than fully hand-curated prose. They are extractor-visible and no longer trigger Plugin Check, but a later translator-copy review could improve specificity without changing runtime behavior.

### Mirror / Live Alignment

- Mirror change set at closeout: `docs/WPORG_PREREVIEW_REMEDIATION.md` plus the `66` modified mirror PHP files reported by final `git diff --name-status`.
- Live change set at closeout: the same `66` runtime paths under `vms/` plus the `4` live-only outreach/admissions files listed above.
- Shared i18n remediations are now aligned across the `66` changed mirror PHP files and their corresponding `vms/` runtime paths.
- Additional live-only outreach/admissions fixes were applied only in the local `vms/` tree because those modules do not exist in the mirror.
- `WPORG-18` is now ready for the combined remediation commit whenever requested.

## WPORG-18D Result

Date: 2026-07-10

### Summary

- Result: `PASS`
- Scope completed: final semantic audit of WPORG-18B translator comments, correction of materially misleading heuristic comments in mirror/live, rerun of the parser and extraction gates, and closeout confirmation that the combined `WPORG-18` remediation is ready to close.

### Semantic Audit Findings

- Reviewed all `519` changed mirror translator comments introduced or modified by the combined `WPORG-18` diff.
- Corrected `26` materially inaccurate mirror translator comments and applied matching live-tree updates for the corresponding runtime strings.
- Corrected misleading placeholder descriptions that had been inferred as the wrong value type, including:
  - linked TEC status labels described as URLs,
  - failed-vendor counts, ticket numbers, gallery photo numbers, and maximum admitted party size described as email addresses or URLs,
  - submitted vendor/act names, guest labels, ticket labels, event titles, and linked TEC product ID lists described as the wrong semantic value,
  - formatted last-snapshot timestamps and enabled ticket titles described as the wrong placeholder type,
  - pass resend success and failure placeholders that had the email address and failure message descriptions reversed.
- `252` changed mirror translator comments still match generic fallback wording patterns such as `human-readable value used in this message`, `number used in this message`, or `date or time value`; these were retained after value-trace review because the placeholder type, position, and meaning remained accurate enough and no materially inaccurate descriptions remained.

### Verification Re-Run

- Workspace safety reconfirmed:
  - mirror HEAD remained `99bdeaddeab7e1181573f886169ae60588c52d39` before the final closeout commit step,
  - `git diff --check` stayed clean,
  - `.codex-temp` remained untracked,
  - `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work` remained untouched.
- `php -l` passed for all `66` changed mirror PHP files.
- `php -l` passed for the `66` shared live counterparts plus the `4` additional live-only outreach/admissions files absent from the mirror.
- Token-aware gettext scan after the semantic fixes:
  - mirror: `9,576` audited gettext-family calls, `0` non-literal source/context/domain violations,
  - live: `10,903` audited gettext-family calls, `0` non-literal source/context/domain violations.
- Multi-placeholder scan after the semantic fixes:
  - mirror: `0` remaining unnumbered or mixed multi-placeholder gettext strings,
  - live: `0` remaining unnumbered or mixed multi-placeholder gettext strings.
- Remaining `vms_i18n_runtime()` references stayed limited to the compatibility-shim definitions in:
  - `packages/vms-github-reconcile/includes/core/registry/admin-menu.php`
  - `vms/includes/core/registry/admin-menu.php`
- Mirror POT extraction re-ran successfully:
  - regenerated `packages/vms-github-reconcile/.codex-temp/backstage-venue-manager.pot`,
  - generated `31,060` lines,
  - reconfirmed extracted numbered-placeholder strings and translator comments including:
    - `Last updated: %1$s by %2$s`,
    - `Because %1$s was cancelled, we have issued an event credit for %2$s.`,
    - `Manual send step complete: %1$d sent, %2$d skipped, %3$d errors.`
- Live Plugin Check re-ran successfully:
  - `wp plugin check vms --checks=i18n_usage --slug=backstage-venue-manager`
  - final result: `Success: Checks complete. No errors found.`
  - stderr contained only upstream WP-CLI / PHP `8.5` deprecation noise, not plugin findings.
- Existing project tests still passed:
  - `php tests/runtime-stub-guards.php`
  - `php tests/release-compatibility-harness.php`
  - `php tests/public-release-build-pipeline.php`

### Closeout

- No actionable i18n parser, translator-comment, or placeholder-order issues remain in the current verified WPORG-18 working tree.
- The combined `WPORG-18` remediation is cleared for the single local commit `Make plugin translations parser compliant`.

## WPORG-19A Result

Date: 2026-07-10

### Summary

- Result: `PASS`
- Scope completed: nonce verification input normalization and sanitization across the audited mirror runtime tree, corresponding live runtime paths, and the one live-only local admissions file that has no mirror counterpart.
- Starting mirror HEAD: `f95b50e77c78752af2964c5eb6c36f85026aae4e`
- Ending mirror HEAD after working changes in this batch: unchanged locally; no commit created in this task
- Protected stash status: `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work` remained untouched.

### Audit Totals

- Total mirror verification/helper paths audited: `246`
  - `wp_verify_nonce()`: `103`
  - `check_admin_referer()`: `108`
  - `check_ajax_referer()`: `35`
  - `wp_nonce_ays()`: `0`
- Total live verification/helper paths audited: `270`
  - `wp_verify_nonce()`: `113`
  - `check_admin_referer()`: `122`
  - `check_ajax_referer()`: `35`
  - `wp_nonce_ays()`: `0`
- Direct request-derived `wp_verify_nonce()` paths reviewed:
  - mirror: `94`
  - live: `104` (`94` shared paths plus `10` live-only outreach paths)
- Direct request-derived paths corrected:
  - mirror: `84`
  - live: `94`
- Already-compliant direct request-derived paths retained:
  - mirror: `10`
  - live: `10`
- Helper-managed paths retained:
  - mirror: `143` (`108` admin, `35` ajax)
  - live: `157` (`122` admin, `35` ajax)
- Wrapper / REST verification paths reviewed: `9`
- Wrapper / REST paths corrected: `4`
- Invalid-shape guards added:
  - mirror: `86` (`84` direct request paths plus `2` REST/object-shape guards)
  - live: `96`
- Non-request `wp_verify_nonce()` paths left unchanged: `0`
- Comments, fixtures, and false positives in the audited runtime/helper totals: `0`
- Missing-nonce findings not fully inventoried in this normalization-only batch:
  - Later completed in `WPORG-19B`: no additional missing-nonce defects were found.
- Capability / authorization findings not fully inventoried in this normalization-only batch:
  - Later completed in `WPORG-19B`: the complete runtime inventory found and fixed the remaining shared object-level and direct-dispatch authorization gaps.

### Files Changed

- Mirror runtime PHP files changed (`41`):
  - `includes/admin/data-tools/actions-event-plan-import.php`
  - `includes/admin/event-command-center.php`
  - `includes/admin/event-feedback.php`
  - `includes/admin/holidays.php`
  - `includes/admin/schedule.php`
  - `includes/admin/season-dates.php`
  - `includes/admin/staff-tax-sidebar.php`
  - `includes/admin/staff-user-link.php`
  - `includes/admin/staff-vendor-link.php`
  - `includes/admin/staff-worker-type.php`
  - `includes/admin/staffing.php`
  - `includes/admin/tax-bypass.php`
  - `includes/admin/tax-profile-admin-metabox.php`
  - `includes/admin/vendor-comp-packages.php`
  - `includes/admin/vendor-details.php`
  - `includes/admin/vendor-staff-link.php`
  - `includes/admin/vendor-user-link.php`
  - `includes/admin/venue-comp-defaults.php`
  - `includes/admin/venue-context.php`
  - `includes/admin/venue-duplicate-templates.php`
  - `includes/core/event-credits.php`
  - `includes/core/tours/class-vms-tours.php`
  - `includes/core/vendor-application-confirmation.php`
  - `includes/cpt/event-plans.php`
  - `includes/cpt/ratings.php`
  - `includes/cpt/staff.php`
  - `includes/cpt/vendors.php`
  - `includes/cpt/venues.php`
  - `includes/integrations/ticketing-rules-v2.php`
  - `includes/integrations/ticketing-verifications.php`
  - `includes/modules/admissions/admin-ui.php`
  - `includes/modules/admissions/pass-claims.php`
  - `includes/modules/admissions/rest.php`
  - `includes/modules/admissions/vendor-guest-portal.php`
  - `includes/modules/staff-tasks/admin-ui.php`
  - `includes/portal/staff-portal.php`
  - `includes/portal/vendor-portal.php`
  - `includes/portal/vendor-tax-profile.php`
  - `includes/public/event-feedback.php`
  - `includes/runtime-guards.php`
  - `includes/vendor-applications.php`
- Mirror tests changed (`1`):
  - `tests/nonce-input-normalization.php`
- Mirror documentation changed (`1`):
  - `docs/WPORG_PREREVIEW_REMEDIATION.md`
- Corresponding shared live runtime files changed: the same `41` relative runtime paths listed above, rooted at `vms/` instead of `packages/vms-github-reconcile/`.
- Live-only runtime file changed:
  - `vms/includes/modules/admissions/outreach-recipients.php`

### Live-Only Alignment Explanation

- `vms/includes/modules/admissions/outreach-recipients.php` has no corresponding file in `packages/vms-github-reconcile`.
- The live tree contains newer unreconciled admissions outreach functionality that is absent from the release mirror; `rg` and direct path checks found no relocated mirror equivalent.
- The live-only change normalized `10` direct request-derived `_wpnonce` verification paths there so the local runtime stays aligned with the shared WPORG-19A hardening approach.
- That live-only fix was retained, not reverted, because it is a legitimate local runtime hardening change outside the release mirror packaging set.

### Representative WordPress.org Examples

- `includes/cpt/venues.php`
  - Disposition: all three direct save-post nonce checks now reject array-shaped input, unslash once, sanitize once, and preserve the existing early-return behavior.
- `includes/portal/staff-portal.php`
  - Disposition: all seven audited portal nonce boundaries now use the normalized guarded pattern; no nonce action strings, field names, or failure messages changed.
- `includes/admin/staff-worker-type.php`
  - Disposition: the metabox save handler now guards against non-scalar request shapes before unslashing and verifying the existing nonce.
- `includes/modules/staff-tasks/admin-ui.php`
  - Disposition: the four existing admin-post / admin-get nonce paths remain normalized, and the follow-up also normalized the AJAX `nonce` request key without changing the JSON error contract.

### Verification and Searches Run

- Workspace checks:
  - `git diff --check`: clean
  - `git diff --stat`: inspected before and after follow-up edits
  - `git diff --name-status`: inspected before and after follow-up edits
- Syntax verification:
  - `php -l` passed for all changed mirror PHP files
  - `php -l` passed for the corresponding shared live PHP files plus `vms/includes/modules/admissions/outreach-recipients.php`
- Narrow deterministic / existing tests passed:
  - `php tests/nonce-input-normalization.php`
  - `php tests/admissions-rest-permissions.php`
  - `php tests/runtime-stub-guards.php`
  - `php tests/release-compatibility-harness.php`
  - `php tests/public-release-build-pipeline.php`
  - `php tests/event-plan-calendar-resync-isolated.php`
  - `php tests/event-plan-editor-vendor-preservation.php`
  - `php tests/ticket-checkout-safety-hardening.php`
- Search / audit results:
  - Token-aware Python context audit over every mirror/live `wp_verify_nonce()` call site confirmed `94` mirror direct request-derived paths and `9` wrapper/REST sites.
  - Residual direct request/global unsafe-pattern search for raw request values, raw string casts, and unguarded `wp_unslash()` / `sanitize_text_field()` / `wp_verify_nonce()` inputs returned no remaining unsafe runtime hits after the follow-up patch.
  - Diff review confirmed no nonce action strings changed.
  - Diff review confirmed no nonce field names / request-key names changed.
- Plugin Check status:
  - `wp plugin check` is installed and `wp plugin check` help resolves locally.
  - Repeated attempts to run a machine-readable `wp plugin check vms` scan in this environment exited `1` before returning parseable results because WP-CLI `2.12.0` on PHP `8.5` emitted upstream deprecation noise from `vendor/wp-cli/php-cli-tools/lib/cli/Colors.php:95`.
  - Exact disposition for this batch: Plugin Check availability confirmed, but no usable nonce/security result was produced from the local runtime environment without changing toolchain state.

### Remaining Risks and Required Follow-Up

- `WPORG-19A` intentionally did not add missing nonces to handlers that currently lacked them; the later `WPORG-19B` follow-up confirmed no additional missing-nonce defects in the complete runtime inventory.
- `WPORG-19A` intentionally did not broaden or tighten capabilities, roles, ownership rules, or endpoint visibility in the normalization patch itself; the later `WPORG-19B` batch handled the needed object-level authorization hardening without changing business logic.
- The current verified working tree closes the nonce input normalization / sanitization part of section C, the targeted follow-up authorization hardening tracked in `WPORG-19B`, the ordinary request-global cleanup tracked in `WPORG-20A`, the committed upload hardening tracked in `WPORG-20B`, the decoded JSON / structured-payload hardening tracked in `WPORG-20C`, the historical inline asset enqueue migration tracked in `WPORG-22` B1-B5 plus the accepted residual closeouts `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-F`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, `WPORG-22R-L`, and `WPORG-22R-M`, the admin-notice scope remediation tracked in `WPORG-23`, and the output-escaping contract work tracked in `WPORG-24`; the formal documentation-only closeout below now marks `WPORG-22R` terminally `verified`, with no executable inline JS/CSS remaining inside the tracked `WPORG-22R` runtime family and only unrelated executable inline assets remaining elsewhere in the repository outside this parent.

## WPORG-19B Result

Date: 2026-07-10

### Summary

- Result: `PASS`
- Scope completed: complete mirror/live runtime handler inventory for section C, targeted object-level and direct-dispatch authorization hardening, focused behavioral regression coverage, and remediation-inventory closeout for the remaining nonce/permission batch.
- Starting mirror HEAD: `85ded6ed23ff773b883da72a067d8a59bb6a9e60`
- Ending mirror HEAD after working changes in this batch: unchanged locally; no commit created in this task
- Protected stash status: `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work` remained untouched.

### Inventory Coverage

- Mirror classified handler entries audited: `348`
  - Inventory unit: deduped runtime handler entries with `16` unique REST route patterns
  - Additional REST verification: `17` method-specific route registrations reviewed (`/admissions` GET and POST were reviewed separately even though the deduped inventory counts the shared path once)
- Live classified handler entries audited: `374`
  - Shared runtime surface matches the mirror inventory shape
  - Live-only delta: `26` handlers outside the WordPress.org release package
    - `vms/includes/modules/admissions/outreach-recipients.php`: `17`
    - `vms/includes/modules/admissions/outreach.php`: `2`
    - `vms/includes/modules/admissions/pass-claims.php`: `1`
    - `vms/includes/modules/outreach/admin-ui.php`: `5`
    - `vms/includes/modules/outreach/outreach.php`: `1`

### Classification Totals

| Class | Mirror | Live | Notes |
| --- | ---: | ---: | --- |
| `1` | `201` | `201` | Nonce plus capability/ownership protected mutation or sensitive request handler |
| `2` | `0` | `0` | Missing nonce |
| `3` | `0` | `0` | Missing capability or role authorization |
| `4` | `0` | `0` | Missing ownership or object authorization |
| `5` | `17` | `17` | Public or semi-public signed-token / equivalent protected flow |
| `6` | `7` | `7` | Intentionally public, read-only, or otherwise non-sensitive endpoint |
| `7` | `16` | `16` | Dedupe-counted REST route patterns with explicit `permission_callback` |
| `8` | `97` | `97` | Internal-only bootstrap, migration, rewrite, registration, observer, or profiler callback |
| `9` | `10` | `10` | Duplicate dispatcher or save-path callback protected at a shared boundary |
| `10` | `0` | `0` | Test, fixture, comment, dead code, or false positive |
| `11` | `0` | `0` | Ambiguous contract requiring a product or security decision |
| `12` | `0` | `26` | Live-only or outside the WordPress.org release package |

Reconciliation:

- Mirror: `201 + 17 + 7 + 16 + 97 + 10 = 348`
- Live: mirror `348` + live-only `26` = `374`

### Authorization Boundaries Changed

- Replaced broad `edit_posts` gates with object-aware `edit_post()` checks in `7` shared mutation handlers:
  - `includes/vendor-applications.php` `vms_vendor_applications_handle_edit_screen_decision`
  - `includes/vendor-applications.php` `vms_vendor_applications_handle_approve`
  - `includes/vendor-applications.php` `vms_vendor_applications_handle_reject`
  - `includes/vendor-applications.php` `vms_vendor_applications_handle_repair_vendor`
  - `includes/vendor-applications.php` `vms_vendor_applications_handle_resync_vendor`
  - `includes/helpers.php` `vms_vendor_handle_mark_reviewed`
  - `includes/admin/venue-duplicate-templates.php` `vms_handle_create_venue_from_template`
- Added matching object-aware gates to `2` supporting vendor-application admin UI boundaries in `includes/vendor-applications.php` so review controls only render for operators who can edit the specific application.
- Completed the full inventory by hardening `2` additional shared direct-dispatch handlers that were not part of the earlier seven-handler patch:
  - `includes/admin/season-dates.php` `vms_sd_maybe_handle_post` now enforces the existing filtered admin capability before any POST mutation path
  - `includes/cpt/event-plans.php` admin-edit safety-net closure now enforces `current_user_can('edit_post', $post_id)` before any vendor-integrity mutation
- Added focused mirror behavioral regression coverage in `tests/authorization-boundary-hardening.php` to prevent future reintroduction of broad-object or missing handler-local authorization at those boundaries.
- Applied the same shared runtime changes to the corresponding files in `vms/`.

### Files Changed

- Mirror runtime PHP files changed (`5`):
  - `includes/vendor-applications.php`
  - `includes/helpers.php`
  - `includes/admin/venue-duplicate-templates.php`
  - `includes/admin/season-dates.php`
  - `includes/cpt/event-plans.php`
- Mirror tests changed (`1`):
  - `tests/authorization-boundary-hardening.php`
- Mirror documentation changed (`1`):
  - `docs/WPORG_PREREVIEW_REMEDIATION.md`
- Corresponding shared live runtime files changed:
  - `vms/includes/vendor-applications.php`
  - `vms/includes/helpers.php`
  - `vms/includes/admin/venue-duplicate-templates.php`
  - `vms/includes/admin/season-dates.php`
  - `vms/includes/cpt/event-plans.php`
- Live-only runtime files changed in this batch: `0`

### Findings Summary

- Missing nonce result:
  - No additional missing-nonce defects were found in the completed runtime inventory.
  - `20` `$stop = false` nonce checks were re-reviewed; every match enforces the returned value before continuing.
- Missing capability / object-authorization result:
  - The original seven shared mutation handlers and two supporting vendor-application UI gates remain correctly hardened.
  - The complete runtime inventory uncovered `2` additional shared direct-dispatch capability gaps (`season-dates` POST handling and the Event Plan edit-screen vendor-integrity guard); both are now fixed in mirror and live.
  - No remaining audited mutation handler relies only on broad `edit_posts` when mutating a request-supplied specific object.
- REST findings:
  - `17` method-specific route registrations reviewed, covering `16` deduped route patterns in the classified inventory.
  - Every registration has an explicit `permission_callback`.
  - `0` registrations use `__return_true`.
  - Mutation routes stay behind `manage_options`, tours capability checks plus REST nonces, or admissions check-in/manage callbacks plus REST nonces.
- AJAX and admin-post findings:
  - Authenticated AJAX handlers reviewed: `41`
  - `nopriv` AJAX handlers reviewed: `4`
  - `admin_post` handlers reviewed: `144`
  - `admin_post_nopriv` handlers reviewed: `2`
  - `5` admin-post wrappers intentionally rely on a shared protected helper boundary rather than duplicating their own nonce/cap checks.
  - `1` authenticated AJAX endpoint (`vms_get_venue_comp_defaults`) intentionally remains nonce-free because it is `manage_options`-gated and read-only.
- Save-post findings:
  - Total reviewed: `41`
  - Explicit nonce-protected callbacks: `26`
  - Object-aware `edit_post` or equivalent callbacks: `28`
  - Internal observers / profilers / cache-bust hooks not independently HTTP-invokable: `13`
  - Shared post-save boundary callbacks intentionally relying on the protected save dispatcher: `4`
- Public and token-flow findings:
  - Category `5` handlers reviewed: `17`
  - Trust models reviewed: confirmation-email lookup keys, invitation / scanner / pass-claim tokens, customer/cart nonce flows, and ownership-linked portal submissions
  - No ambiguous token-binding, expiration, or revocation contract blocked this batch

### Verification Performed

- Confirmed mirror start state matched `85ded6ed23ff773b883da72a067d8a59bb6a9e60`, the worktree started clean, and `stash@{0}` remained untouched.
- Reviewed the full mirror diff for nonce action strings, nonce request-key names, capability or hook churn, routes, redirects, and business-logic changes; no unintended functional drift was introduced.
- Confirmed the shared runtime hardening remains aligned between `packages/vms-github-reconcile` and `vms/` for the five changed runtime files.
- Replaced the original source-pattern-only authorization test with behavioral mirror-source coverage in `tests/authorization-boundary-hardening.php`.
- Re-ran the required mirror test suite:
  - `tests/authorization-boundary-hardening.php`
  - `tests/nonce-input-normalization.php`
  - `tests/admissions-rest-permissions.php`
  - `tests/runtime-stub-guards.php`
  - `tests/release-compatibility-harness.php`
  - `tests/public-release-build-pipeline.php`
  - `tests/event-plan-calendar-resync-isolated.php`
  - `tests/event-plan-editor-vendor-preservation.php`
  - `tests/ticket-checkout-safety-hardening.php`
- Ran `php -l` on every changed mirror and live PHP file and re-ran `git diff --check`.
- Retained the existing Plugin Check environment note: WP-CLI `2.12.0` under PHP `8.5` still fails in its `php-cli-tools` dependency, so no fresh parseable Plugin Check result was added in this batch.
- Detailed command verification for this batch is recorded in the task report rather than duplicated inline here.

### Result

- The nonce / permissions category can remain marked complete.
- No remaining WPORG-19B runtime handler in the audited mirror or live surfaces is classified as missing a nonce, missing capability/role authorization, missing ownership/object authorization, or requiring an unresolved authorization-contract decision.

## WPORG-20A Result

Date: 2026-07-11

### Summary

- Result: `PASS`
- Scope completed: ordinary request-global sanitization, request-wrapper shape guards, redirect allowlisting, server-value normalization, request-derived form repopulation cleanup, and the specific `FILTER_UNSAFE_RAW` remediation requested for `WPORG-20A`.
- Explicit exclusions preserved in this batch: upload transport / MIME handling (`WPORG-20B`) and decoded JSON / structured-payload validation (`WPORG-20C`).
- Starting mirror HEAD: `1b2dde1c7cd88741676d96cb4cd8a302b799dd22`
- Ending mirror HEAD after working changes in this batch: unchanged locally; no commit created in this task.
- Protected stash status: `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work` remained untouched.

### Inventory Coverage

- Mirror logical occurrences audited: `85`
  - Inventory unit: deduped request/global handling boundaries reviewed to the point of a final WPORG-20A classification, rather than raw grep line hits.
  - Search scope included direct `$_GET` / `$_POST` / `$_REQUEST` / `$_SERVER` access, request wrappers, redirect parameters, server-value normalization, and escape-versus-sanitize patterns across the mirror runtime tree.
- Live logical occurrences audited: `91`
  - Shared runtime surface matches the mirror inventory for the audited WPORG-20A boundaries.
  - Live-only delta reviewed: `6` files outside the public mirror (`admin-ui/nav.php`, `modules/admissions/outreach-recipients.php`, `modules/admissions/outreach.php`, `modules/outreach/admin-ui.php`, `modules/outreach/outreach.php`, `ticketing/ticket-integrity-payment-gateway-health.php`).

### Classification Totals

| Class | Mirror | Live | Notes |
| --- | ---: | ---: | --- |
| `1` | `27` | `27` | Correctly existence-checked, normalized, and validated |
| `2` | `11` | `11` | Required `wp_unslash()` plus scalar sanitization and is now remediated |
| `3` | `7` | `7` | Required restrictive validation and is now remediated |
| `4` | `8` | `8` | Required scalar or array-shape guards and is now remediated |
| `5` | `4` | `4` | Raw value is safe only because a reviewed downstream helper validates it |
| `6` | `5` | `5` | Escape-only handling had substituted for sanitization and is now remediated |
| `7` | `0` | `0` | Raw `filter_input()` followed by an immediately sufficient validator |
| `8` | `1` | `1` | `filter_input()` required remediation and is now remediated |
| `9` | `7` | `7` | Server value required normalization and is now remediated |
| `10` | `0` | `0` | Cookie or session normalization required |
| `11` | `6` | `6` | Redirect value required allowlisting and is now remediated |
| `12` | `0` | `0` | Non-user-controlled constant or internal value |
| `13` | `2` | `2` | Test, fixture, comment, dead code, or false positive |
| `14` | `4` | `4` | Upload-related finding deferred to `WPORG-20B` |
| `15` | `3` | `3` | JSON-decoding or decoded-structure finding deferred to `WPORG-20C` |
| `16` | `0` | `0` | Ambiguous contract requiring a decision |
| `17` | `0` | `6` | Live-only code outside the WordPress.org mirror |

Reconciliation:

- Mirror: `27 + 11 + 7 + 8 + 4 + 5 + 1 + 7 + 6 + 2 + 4 + 3 = 85`
- Live: mirror `85` + live-only `6` = `91`

### Files Changed

- Mirror runtime PHP files changed (`21`):
  - `includes/runtime-guards.php`
  - `includes/helpers.php`
  - `includes/admin-ui/context.php`
  - `includes/admin/docs-page.php`
  - `includes/admin/event-command-center.php`
  - `includes/admin/venue-context.php`
  - `includes/core/event-plan-performance.php`
  - `includes/core/plugin.php`
  - `includes/core/tours/class-vms-tours.php`
  - `includes/core/vendor-application-confirmation.php`
  - `includes/cpt/ratings.php`
  - `includes/integrations/ticketing-claims-admin.php`
  - `includes/integrations/ticketing-verifications.php`
  - `includes/modules/availability-date-dispatch/helpers.php`
  - `includes/modules/status-notices/front.php`
  - `includes/portal/staff-portal.php`
  - `includes/portal/vendor-portal.php`
  - `includes/ticketing/ticket-mutation-audit.php`
  - `includes/tours/class-vms-tours-screen.php`
  - `includes/tours/class-vms-tours-service.php`
  - `includes/vendor-applications.php`
- Mirror tests changed (`3`):
  - `tests/request-input-sanitization.php`
  - `tests/authorization-boundary-hardening.php`
  - `tests/bootstrap-wordpress.php`
- Mirror documentation changed (`1`):
  - `docs/WPORG_PREREVIEW_REMEDIATION.md`
- Corresponding shared live runtime files changed (`21`):
  - `vms/includes/runtime-guards.php`
  - `vms/includes/helpers.php`
  - `vms/includes/admin-ui/context.php`
  - `vms/includes/admin/docs-page.php`
  - `vms/includes/admin/event-command-center.php`
  - `vms/includes/admin/venue-context.php`
  - `vms/includes/core/event-plan-performance.php`
  - `vms/includes/core/plugin.php`
  - `vms/includes/core/tours/class-vms-tours.php`
  - `vms/includes/core/vendor-application-confirmation.php`
  - `vms/includes/cpt/ratings.php`
  - `vms/includes/integrations/ticketing-claims-admin.php`
  - `vms/includes/integrations/ticketing-verifications.php`
  - `vms/includes/modules/availability-date-dispatch/helpers.php`
  - `vms/includes/modules/status-notices/front.php`
  - `vms/includes/portal/staff-portal.php`
  - `vms/includes/portal/vendor-portal.php`
  - `vms/includes/ticketing/ticket-mutation-audit.php`
  - `vms/includes/tours/class-vms-tours-screen.php`
  - `vms/includes/tours/class-vms-tours-service.php`
  - `vms/includes/vendor-applications.php`
- Live-only runtime files changed in this batch: `0`

### Known WordPress.org Example Dispositions

- `includes/vendor-applications.php` `$_SERVER['HTTP_USER_AGENT']`: corrected via shared server helpers and length-bounded normalization.
- `includes/runtime-guards.php` `$_SERVER['REQUEST_URI']`: corrected via shared request-URI normalization.
- `includes/core/event-plan-performance.php` `$_SERVER['REQUEST_URI']`: corrected via normalized request context helpers.
- `includes/modules/status-notices/front.php` `$_SERVER['REQUEST_URI']`: corrected via normalized current-URI helpers.
- `includes/cpt/ratings.php` raw `$_POST` values passed to `esc_attr()`: corrected by normalizing submitted values before repopulation and preserving late escaping.
- `includes/helpers.php` raw `REQUEST_URI` in hidden `redirect_to`: corrected via validated local redirect fallback handling.
- `includes/admin/venue-context.php` raw `REQUEST_URI` in hidden `redirect_to`: corrected via validated local redirect fallback handling.
- `includes/portal/staff-portal.php` `FILTER_UNSAFE_RAW`: corrected by replacing the raw filter path with request-helper normalization plus the existing allowlist.

### Findings Summary

- Ordinary request-global findings remediated:
  - Added shared helper boundaries for scalar, textarea, email, key, absint, bool-flag, server, method, current-URI, local-redirect, remote-address, and user-agent normalization.
  - Replaced late-escape-only repopulation with sanitized request preservation in `includes/cpt/ratings.php` and `includes/portal/vendor-portal.php`.
  - Added shape guards to request wrappers and publicly reachable form handlers so malformed arrays no longer reach scalar-only operations.
- Server-input findings remediated:
  - `REQUEST_URI`, `REQUEST_METHOD`, `REMOTE_ADDR`, `HTTP_USER_AGENT`, and `HTTP_ACCEPT` review sites now normalize through shared helper paths or equivalent guard logic.
  - No cookie or session request reads were identified in the audited mirror or live runtime surfaces for this batch.
- `filter_input()` findings:
  - `1` actionable `FILTER_UNSAFE_RAW` path was found and remediated (`includes/portal/staff-portal.php`).
  - No remaining runtime `filter_input()` / `filter_input_array()` calls were found in the audited mirror or live trees after the patch.
- Redirect findings:
  - Request-derived `redirect_to`, `return_url`, and `_wp_http_referer` boundaries in `helpers.php`, `admin/venue-context.php`, `portal/vendor-portal.php`, `integrations/ticketing-claims-admin.php`, `integrations/ticketing-verifications.php`, and `core/vendor-application-confirmation.php` now use local allowlisting via `wp_validate_redirect()` through shared helpers or existing guarded paths.
- Deferred findings:
  - Upload-specific follow-up stayed in `WPORG-20B` at the time of this batch and was later completed there.
  - Decoded JSON / structured-payload follow-up stayed in `WPORG-20C` at the time of this batch and was later completed there.

### Verification Performed

- Verified the mirror start state matched `1b2dde1c7cd88741676d96cb4cd8a302b799dd22`, the latest subject was `Harden request authorization boundaries`, the worktree started clean, and `stash@{0}` remained untouched.
- Ran `php -l` on every changed mirror and live PHP file in this batch, including the focused follow-up test-harness changes; all passed.
- Expanded focused mirror coverage in `tests/request-input-sanitization.php` to cover scalar/shape handling, every shared request-helper family, redirect rejection/retention behavior, server-value normalization, ratings form repopulation/output escaping, and fail-closed malformed-input behavior before mutation.
- Added a test-only fail-closed bootstrap guard in `tests/bootstrap-wordpress.php` so a WordPress database bootstrap error now emits stderr and exits nonzero instead of being misread as a passing zero-exit HTML page.
- Re-ran focused regression tests successfully:
  - `php tests/request-input-sanitization.php`
  - `php tests/authorization-boundary-hardening.php`
  - `php tests/nonce-input-normalization.php`
  - `php tests/admissions-rest-permissions.php`
  - `php tests/runtime-stub-guards.php`
  - `php tests/release-compatibility-harness.php`
  - `php tests/public-release-build-pipeline.php`
- Diagnosed the earlier false-pass environment issue:
  - The three database-backed scripts bootstrap WordPress through `tests/bootstrap-wordpress.php`, which resolves `wp-load.php` by walking up from the mirror test directory into the Local site root.
  - `wp-config.php` targets the Local site database at `DB_NAME=local`, `DB_USER=root`, `DB_PASSWORD=root`, and `DB_HOST=localhost:/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock`.
  - The Local site-specific MySQL socket was missing because the existing Local-managed MySQL service for site `9UzgHTVC_` was not running, even though Local.app itself was open and another site’s MySQL process was active.
  - Safe remediation in the current local environment: started the existing site-managed MySQL `8.0.35` service with its own Local-generated `my.cnf`, which restored `/Users/treyconey/Library/Application Support/Local/run/9UzgHTVC_/mysql/mysqld.sock` without changing database contents, credentials, or schema.
- Additional required scripts were then re-run and produced their normal success markers:
  - `php tests/event-plan-calendar-resync-isolated.php`
  - `php tests/event-plan-editor-vendor-preservation.php`
  - `php tests/ticket-checkout-safety-hardening.php`
  - `event plan calendar resync isolated: PASS`
  - `Event Plan editor vendor preservation test passed.`
  - `ticket-checkout-safety-hardening: OK`
- Post-fix search results:
  - No remaining runtime `filter_input()` hits in mirror or live.
  - No remaining raw request values passed directly into escaping functions in the mirror runtime tree.
  - Remaining raw request/global matches are limited to reviewed wrappers, existing nonce checks, upload exclusions, decoded-JSON exclusions, or live-only files outside the public mirror.
- Plugin Check status remains unchanged from earlier batches: WP-CLI `2.12.0` under PHP `8.5` still fails before returning a stable parseable result because of upstream `php-cli-tools` noise.

### Remaining Risks and Required Follow-Up

- `WPORG-20A` intentionally did not change upload transport, MIME trust, or file-move flows in that batch itself; the later committed `WPORG-20B` follow-up completed that work.
- `WPORG-20A` intentionally did not harden decoded JSON or structured response bodies in that batch itself; the later `WPORG-20C` follow-up completed that work, including the corrective tours REST JSON boundary fix.
- Local database-backed verification now passes again as long as the existing Local site MySQL service is running.

### Result

- The ordinary request-global remediation portion of section D can be treated as complete.
- No remaining audited mirror or shared-live ordinary request/global boundary reviewed for `WPORG-20A` is still classified as requiring unslashing, shape guards, restrictive scalar validation, redirect allowlisting, or server normalization.
- The focused coverage gap and the database-backed regression gap are closed; `WPORG-20A` is ready to commit.

## WPORG-20B Result

Date: 2026-07-11

### Summary

- Result: `PASS`
- Commit: `d1cdfbd80b05c8254cdc413d0e1bbb821ca13492`
- Subject: `Harden uploaded file handling`
- Scope completed: uploaded-file structure and trust validation, content-based MIME/type validation, public media prevalidation before `media_handle_upload()` where still applicable, hardened private operational-file handling, brokered authenticated downloads, storage-key-backed private artifacts instead of new raw absolute-path persistence, and focused importer / ticketing proof upload hardening.
- Protected stash status: `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work` remained untouched.

### Completed Runtime Boundaries

- Tax-profile uploads:
  - `includes/admin/tax-profile-admin-metabox.php`
  - `includes/portal/vendor-tax-profile.php`
  - `includes/portal/staff-portal.php`
  - now route W-9 uploads through `vms_private_w9_store_upload()`, store private-file IDs plus storage-kind metadata, and expose brokered authenticated download URLs instead of trusting client MIME input or persisting new raw public paths.
- Event-plan importer uploads and artifacts:
  - `includes/admin/data-tools/actions-event-plan-import.php`
  - `includes/services/event-plan-import/event-plan-import-engine.php`
  - now validate upload structure and MIME before persistence, then store source CSV, rows JSON, reports, and snapshots via safe private storage keys.
- Private operational-file handling:
  - `includes/core/private-files.php`
  - `includes/safety/private-files.php`
  - `includes/core/staffing.php`
  - now validate uploads before persistence, record verified MIME metadata, broker authenticated downloads, and avoid introducing new raw absolute-path persistence for these flows.
- Ticketing proof and supporting upload paths:
  - `includes/integrations/ticketing-verifications.php`
  - `includes/portal/vendor-portal.php`
  - retain or extend content-based prevalidation before attachment or private-file storage.

### Tests and Verification

- Added focused regression coverage in `tests/upload-validation-guards.php`.
- Complementary proof normalization coverage remains in `tests/verification-proof-normalization.php`.
- No push, deployment, package, submission, or production change occurred in this batch.

## WPORG-20C Result

Date: 2026-07-11

### Summary

- Result: `PASS`
- Scope completed: decoded JSON syntax, top-level shape, bounded body/file size checks, and narrow schema validation for the audited first-party request, importer-cache, and remote-response paths in `packages/vms-github-reconcile`.
- Compatibility floors preserved: WordPress `6.8`, PHP `8.3`.
- Shared helpers added in `includes/runtime-guards.php` intentionally stop at decode, top-level token, list-vs-object, and bounded-stream utilities; route-specific handlers still own their own field rules.

### Completed Runtime Boundaries

- Remote-service JSON:
  - `includes/vendor-applications.php` now validates the Turnstile siteverify body as a bounded JSON object with an explicit boolean `success` field before treating the request as verified.
- Request-derived JSON / structured bodies:
  - `includes/integrations/ticketing-phase-b.php` now validates tier-save, commit-item, and config/template payload shapes before the existing Phase B normalizers run.
  - `includes/integrations/ticketing-rules-v2.php` now reads bounded JSON request bodies and validates atomic-add / silent-add ticket, add-on, variation, and claim-assignment shapes before cart mutation.
  - `includes/rest/class-vms-rest-tours.php` now rejects malformed, scalar, and list-shaped REST JSON payloads for the drift-report mutation routes before runtime drift or scan-report replacement work begins, while still distinguishing `[]` from `{}`.
  - `includes/integrations/ticketing-claims-customer.php` now validates the decoded `existing_counts` object rather than accepting any decoded array.
- Uploaded/imported or cache-derived JSON:
  - `includes/services/event-plan-import/event-plan-import-engine.php` now validates preview-row and revert-snapshot JSON file size, top-level object shape, and required nested list/object boundaries before commit/revert work.
- Stored JSON from request-derived data:
  - `includes/modules/admissions/pass-claims.php` now validates `venue_ids_json` as a JSON list of venue IDs before use.
  - `includes/modules/admissions/vendor-guest-portal.php` and `includes/modules/admissions/admission-tokens.php` now reject malformed `claim_meta` JSON instead of trusting any decoded array.
  - `includes/core/calendar-feed.php` plus the legacy fallback in `includes/admin/settings-page.php` now treat JSON map inputs as object-only contracts rather than accepting list-shaped decodes.

### Tests and Verification

- Added focused regression coverage in `tests/decoded-json-validation.php` for the new decode/shape validators, including the tours REST object-boundary fix.
- Existing required verification for this batch should include:
  - `php tests/request-input-sanitization.php`
  - `php tests/upload-validation-guards.php`
  - `php tests/verification-proof-normalization.php`
  - `php tests/decoded-json-validation.php`

### Residual Risk

- This batch intentionally does not change upload transport/MIME architecture from `WPORG-20B`.
- The residual raw `json_decode()` inventory outside these shared validators is now reconciled separately under the `WPORG-20C-R` closeout below; this `WPORG-20C` result remains limited to the shared validator and direct request/import/runtime boundaries introduced here.

## WPORG-20C-R Result

Date: 2026-07-23

### Summary

- Result: `PASS`
- Status: `verified`
- Scope completed: documentation-only terminal closeout of residual decoded-JSON parent `WPORG-20C-R` in `docs/wporg-remediation-ledger.md` and `docs/WPORG_PREREVIEW_REMEDIATION.md` only; no runtime PHP, JavaScript, tests, live-tree files, packaging files, or protected-stash state changed in this closeout.
- Relevant child commits:
  - `41ff8b9fdb0bf719172ecb59d75f7bd84bcdd937` `Normalize Ticketing Rules stored claim-assignment reads`
  - `93f0b32746c9b2c897e73a4e290bd868b952568b` `Guard Social queue snapshot account selection`
  - `7f92ae2985ca9ec0d9259c2cfdd04cedeeb5e965` `Guard Staff Tasks stored signature JSON`
  - `a452d088cfaf251bbffb4c24beb7917d8aaa2292` `Guard Staff Tasks checklist overrides JSON`
  - `c2040655b409f7027791358e4451ff517aa00834` `Characterize Event Plan Review JSON boundaries`
  - `42189c9f91b9269403adc1cd55cad1ed5b488014` `Guard Event Plan Review stored JSON state`
  - `0a5c74fccc6339635f0887cf8b67b758a33739c8` `Characterize Tours stored user-state JSON fallbacks`
- Retained packaged evidence: the clean public-package build and packaged Plugin Check reruns recorded on `2026-07-22` under `WPORG-20A-S` remain adequate for this parent; no fresh packaged scan was required in this closeout.

### Protected Behavior

- Ticketing Rules stored claim-assignment reads:
  - `tests/decoded-json-validation.php` proves `vms_ticketing_v2_decode_stored_claim_assignment_rows()` accepts only JSON lists for stored rows, rejects object/scalar/malformed/UTF-8/depth failures, preserves the direct PHP-array branch, preserves the legacy seat-meta assignee fallback, and keeps the mirror/live helper contract synchronized where required.
- Social queue snapshot account selection:
  - `tests/social-share-queue-snapshot-json-remediation.php` proves invalid snapshots and invalid queued accounts fail before account/provider routing, perform zero provider or venue-map lookups, do not publish or mutate auth state, and still preserve exactly one provider lookup on the valid queued and rendered-preview paths.
- Staff Tasks stored signature JSON:
  - `tests/staff-tasks-signature-json-remediation.php` proves missing, valid, and invalid stored signature states remain distinct, malformed/list/scalar/null/schema/UTF-8/depth failures all fail closed, and sequential reads do not leak prior valid state.
- Staff Tasks checklist overrides JSON:
  - `tests/staff-tasks-overrides-json-remediation.php` proves invalid rows are skipped before seen-marking, duplicate suppression, or mutation, invalid rows do not consume duplicate slots, later valid duplicate-template rows remain eligible, and missing overrides still preserve template defaults.
- Event Plan Review snapshot and changes JSON:
  - `tests/event-plan-review-json-characterization.php` proves invalid baselines cannot clear, overwrite, or fabricate stored review state, invalid derived changes stay review-visible through the existing integrity warning path, and valid baselines still repair or clear stale invalid derived changes canonically.
- Tours stored user-state compatibility readers:
  - `tests/tours-user-state-json-characterization.php` proves the JSON, PHP-array, current, and legacy fallbacks remain compatibility readers only, malformed state can re-show tours or lose completion/progress visibility only, and the only accepted residual artifact is an optional malformed nested value stringifying as `Array` in an admin-only label.

### Accepted Residual Raw Decodes

- Intentional helper internals:
  - `includes/runtime-guards.php`
- Validated specialized decoders:
  - `includes/social-share/queue-runner.php`
  - `includes/integrations/ticketing-rules-v2.php`
  - `includes/modules/staff-tasks/generator.php`
  - `includes/modules/staff-tasks/store.php`
  - `includes/core/event-plan-review.php`
- Accepted low-risk raw decodes:
  - `includes/social-share/crypto.php`: plugin-authored encrypted payloads, fail-closed
  - `includes/social-share/queue-repo.php`: plugin-authored `meta_json`, annotation-only, no secrets, authorization, provider-selection, or routing dependency
  - `includes/integrations/ticketing-claims-framework.php`: admin log/source context only, no ownership, authorization, financial, or ticket mutation
  - `includes/core/staffing.php`: computed admin summary payloads; malformed fallback loses summary display only
  - `includes/tours/class-vms-tours-storage.php`
  - `includes/core/tours/class-vms-tours.php`
  - Tours malformed state can re-show tours or lose completion/progress visibility only, has no privileged mutation or external side effect, and the optional malformed nested-value admin-label cleanup is not a release blocker.
- No-action diagnostic or remote-error decodes:
  - `includes/ticketing/ticket-inventory-forensics.php`
  - `includes/integrations/square-ticket-mirror.php`
- Final residual disposition: no unsafe residual direct `json_decode()` consumer remains in the mirror parent boundary, no runtime child remains open, and this closeout does not claim any new live-only decode remediation, push, submission, or packaging rerun.

## WPORG-21 Result

Date: 2026-07-11

### Summary

- Result: `PASS`
- Historical scope `H1` / `H2` / `H3` is fully completed by committed `WPORG-20B` (`d1cdfbd80b05c8254cdc413d0e1bbb821ca13492`, `Harden uploaded file handling`).
- `WPORG-21` remains preserved as the historical verifier identifier for those three upload findings, but it is no longer an open duplicate implementation batch.
- No separate `WPORG-21` implementation commit is needed unless a future verification uncovers a concrete regression in those already-remediated upload boundaries.

### H1 / H2 / H3 Reconciliation

- `H1` tax-profile upload trust path:
  - completed by validated W-9 private upload helpers in the admin, vendor portal, and staff portal flows, plus brokered authenticated downloads.
- `H2` event-plan CSV import prevalidation:
  - completed by `vms_upload_read_file()` plus `vms_validate_uploaded_file()` before persistence, followed by storage-key-backed importer artifacts.
- `H3` private operational-file MIME trust and path persistence:
  - completed by validated private-file storage, authenticated brokered downloads, and storage-key resolution instead of new raw absolute-path persistence for these flows.

### Next Actual Incomplete Batch

- Historical `WPORG-22` B1-B5 remain completed by the recorded inline asset remediation passes.
- `WPORG-22R-A` is now completed by the Reference Keys Map clipboard-helper closeout recorded below.
- `WPORG-22R-B` and `WPORG-22R-G` are now completed by the Holidays and Tax Bypass helper closeout recorded below.
- `WPORG-22R-C` is now completed by the dedicated Event Plan Import reconciliation and helper externalization closeout recorded below.
- `WPORG-22R-D` and `WPORG-22R-H` are now completed by the Staff Tasks and ADD module-admin helper closeout recorded below.
- `WPORG-22R-F` is now completed by the Vendor Compensation helper closeout recorded below.
- `WPORG-22R-I` is now completed by the ADD admin menu-badge asset closeout recorded below.
- `WPORG-22R-J` is now completed by the standalone ADD public-shell CSS closeout recorded below.
- `WPORG-22R-K` is now completed by the Staff CPT qualifications helper closeout recorded below.
- `WPORG-22R-L` is now completed by the Staffing admin helper closeout recorded below.
- `WPORG-22R-M` is now completed by the Staff Portal runtime-helper closeout recorded below.
- The formal documentation-only parent closeout below now marks `WPORG-22R` terminally `verified`; no further known runtime implementation child or parent follow-up remains.
- `WPORG-23` is now completed by the admin-notice scope remediation recorded below.
- `WPORG-24` is now closed by the accepted child inventory plus documentation-only `WPORG-24R`.
- The documentation-only `WPORG-20C-R` closeout above now marks the decoded-JSON residual parent terminally `verified`; no further runtime child remains under that parent.
- `WPORG-21` was not reopened in this corrective pass.

The next actual incomplete batch order is now:

1. `WPORG-28`

## WPORG-25 Result

Date: 2026-07-25

### Summary

- Result: `PASS`
- Status: `verified`
- Original reviewer requirement from July 1, 2026: every plugin-owned `ob_start()` must pair with an explicit close in the same logical flow, without hooks or bypassable paths.
- Historical reviewer examples: the former My Tickets callback buffer in `includes/integrations/ticketing-rules-v2.php`, the request-global AJAX opener in `includes/integrations/load.php`, and the former Ticketing Rules V2 server-mount callback buffer in `includes/integrations/ticketing-rules-v2.php`.
- `I1` outcome: the request-global AJAX opener remains in `includes/integrations/load.php`, but current source and focused tests now prove explicit responder ownership across all audited callback families.
  - Seven legacy ticketing admin callbacks terminate through `vms_ticketing_ajax_send_success()` / `vms_ticketing_ajax_send_error()`.
  - Three public V2 cart/context callbacks terminate through `vms_ticketing_v2_ajax_send_success()` / `vms_ticketing_v2_ajax_send_error()`.
  - Eleven Phase B callbacks terminate through those cleanup-only wrappers or `vms_ticketing_v2_ajax_send_json_success_fast()`.
  - Two customer-claims callbacks terminate through the cleanup-only wrappers.
  - No direct-send AJAX responder remains beneath the opener, and no reachable nested plugin-owned buffer was found in the audited callback paths.
- `I2` outcome: both original Ticketing Rules V2 full-page callback-buffer families were removed.
  - My Tickets now uses native `tec_tickets_my_tickets_link_ticket_count_by_type` ownership through `vms_ticketing_v2_filter_my_tickets_link_ticket_count_by_type()`.
  - Reserved add-ons now use native `tribe_template_before_include_html:tickets/v2/tickets/footer` ownership through `vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount()`.
  - Disabled ticket products now use native `tribe_tickets_get_tickets_query_args` ownership through `vms_ticketing_v2_filter_disabled_ticket_query_args()`.
  - The automatic append path remains fallback-only when native footer placement did not succeed, shortcode/manual renderer behavior remains intact, cancellation enforcement remains intact, and the main frontend bundle remains the controller owner.
- Remaining buffer inventory inside `includes/`: `67` `ob_start()` sites, `67` `ob_get_clean()` sites, `3` `ob_end_clean()` sites, and `1` `ob_get_contents()` site.
  - `66` `ob_start()` sites are same-function fragment or template captures that close with `ob_get_clean()` in the same logical flow, including `vms_admin_ui_render_shell()` and `vms_ticketing_v2_render_entitlements_block()`.
  - The remaining request-global AJAX opener in `includes/integrations/load.php` is explicitly closed by `vms_ticketing_ajax_attach_noise()`, `vms_ticketing_ajax_discard_owned_buffer()`, or `vms_ticketing_v2_ajax_send_json_success_fast()`.
  - No materially equivalent unowned lifecycle remains under `WPORG-25`.
- Mirror/live result: exact parity was confirmed for the AJAX opener wrappers, My Tickets native filter, native footer placement, native disabled-ticket suppression, cancellation enforcement, shortcode callback, and automatic append fallback. Accepted unrelated drift remains in the live declarative renderer body plus two live-only V2 AJAX callback bodies and does not reopen this parent.
- Boolean ownership limitation: the `vms_ajax_ob_started` flag remains a nonblocking theoretical compatibility note because it tracks one owned opener rather than stack depth, but the audited callback families do not demonstrate a reachable nested plugin-owned buffer or bypassed close.

### Direct Evidence

- `tests/ticketing-output-buffer-lifecycle-characterization.php` proves the surviving AJAX opener, wrapper ownership, native My Tickets ownership, native footer placement, and native disabled-ticket suppression architecture.
- `tests/ticketing-v2-ajax-output-buffer-ownership.php` proves the three public V2 cart/context callbacks clean up before JSON termination.
- `tests/ticketing-phase-b-ajax-output-buffer-ownership.php` proves the eleven Phase B callbacks clean up through the approved wrappers or fast helper.
- `tests/ticketing-claims-ajax-output-buffer-ownership.php` proves both customer-claims callbacks clean up before JSON termination.
- `tests/ticketing-my-tickets-notice-source-filter-remediation.php` proves My Tickets now uses the native TEC count-source filter rather than a full-page buffer.
- `tests/ticketing-server-mount-native-footer-remediation.php` proves the former server-mount family is gone and reserved add-ons now mount through the native footer boundary.
- `tests/ticketing-disabled-ticket-native-suppression-remediation.php` proves disabled ticket products now suppress through the native query boundary instead of post-render HTML stripping.
- `tests/ticketing-server-controls-inline-js-remediation.php` proves PHP no longer owns the removed inline controller or dormant sidecar dependency.
- `tests/ticket-claims-assignee-validation.php` and `tests/event-plan-legacy-ticketing-integration-smoke.php` provide continuity coverage across the affected ticketing flows.

### Read-Only Design and Audit Chain

- `WPORG-25B`, `WPORG-25D`, `WPORG-25F`, `WPORG-25H`, `WPORG-25I`, `WPORG-25K`, and `WPORG-25L` were architecture or audit gates only; no commit was expected for those read-only children.

### Remaining Follow-Up

- `WPORG-25` is terminal under `verified`.
- `WPORG-28Q` still proves that the public package can be rebuilt, validated, and rescanned on `2026-07-25`, and `WPORG-28R-A` through `WPORG-28R-F6` later removed the packaged `NEW_FINDING`, `UNMAPPED`, and alternative-function residuals without changing the surviving blocker families. `WPORG-28R-G0` then decomposed the remaining blocker roadmap, `WPORG-28R-G1` removed the first `59` admin-module nonce/input blockers, and `WPORG-28R-G2` removed the next `140` admin dashboard and secondary-settings nonce/input blockers. The current packaged residual baseline is now `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1644`, with the remaining ordered implementation roadmap recorded as `WPORG-28R-G3` through `WPORG-28R-G17`.
- External slug-reservation, corrected-upload, and reviewer-reply work remain separately blocked under `Review-2 Name/Slug Closeout`, `WPORG-28R`, and `Review-13 Final Actions`.

## WPORG-28Q Result

Date: 2026-07-25

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28Q`
- Starting mirror HEAD: `3dcd8e70ed00f8885f34334eaa3f0272bc2d3b62` (`Correct WPORG-25 remediation commit hashes`)
- Starting parent: `c8c81d44ea9780a6196a0112049b07ceae4ae403`
- Fresh build command: `php scripts/build-public-release.php --output-dir <temp> --force`
- Fresh build result: `backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `b78cc45e49c096c4abc45221aa07842cc034e425154438bad3fc748fc987f532`
- Fresh package identity: root `backstage-venue-manager/`, main plugin file `vendor-management-system.php`, public slug `backstage-venue-manager`, public version `1.2.0`
- Fresh structure audit: `379` packaged files, `48` extracted directories including the package root, no symlinks, no broken links, no nested archives, no `docs/`, `tests/`, `scripts/`, `dist/`, or `AGENTS.md`, no `Freemius`, and no `Backstage Outreach` package content
- Fresh metadata audit: packaged Plugin Name `Backstage Venue Manager`, Version `1.2.0`, Text Domain `backstage-venue-manager`, Requires at least `6.8`, Requires PHP `8.3`, Tested up to `7.0`, Stable tag `1.2.0`, changelog `1.2.0`, upgrade notice `1.2.0`, and `vms-build.txt` marker `1.2.0`
- Compatibility boundary preserved: `vms.php` remains an internal compatibility shim only, `VMS_PLUGIN_SLUG` remains intentionally internal, and the public package does not reintroduce a slug or header mismatch
- Focused verification reruns passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Fresh packaged Plugin Check command: `WP_CLI_PHP_ARGS='-d error_reporting=24575 -d display_errors=0' wp --path='<local-wp-root>' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '<temp>/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Fresh packaged Plugin Check result: exit `0`, leading WP-CLI PHP 8.5 deprecation noise normalized away before JSON parsing, `309` errors, `1709` warnings, `2018` total messages, reported `OffloadedContent=1`, reported `ExceptionNotEscaped=4`, and `MissingVersion=0`, `unexpected_markdown_file=0`, `MissingTranslatorsComment=0`
- Readiness conclusion: `WPORG-28Q` established metadata/build reproducibility and a stable packaged strict-json scan only. `WPORG-28R` later withdrew the submission-readiness conclusion after the final rule-family reconciliation found one new packaged finding plus multiple unmapped or still-blocking families, `WPORG-28R-A` later removed that lone new finding, `WPORG-28R-B` later mapped the remaining Turnstile `OffloadedContent` occurrence, `WPORG-28R-C1` plus `WPORG-28R-C2` later removed the full packaged `ExceptionNotEscaped` family, and `WPORG-28R-E1` plus `WPORG-28R-E2` later removed the full packaged `SuppressFilters` family without closing the remaining blocked families.

### What Changed

- Rebuilt the public package from committed source in a disposable directory and audited the resulting ZIP and extracted package instead of relying on historical artifacts.
- Re-ran the focused release harnesses and the packaged strict-json Plugin Check gate against the fresh extracted public package.
- Reconciled the stale `WPORG-28`, `A1`, `M1`, Plugin Check gate, and release-checklist wording in `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` to the fresh `1.2.0` package evidence.

### Non-Actions

- No runtime, test, release-metadata, builder, or live-tree file changed in this slice.
- No upload, deployment, submission, reviewer reply, tag, push, production change, live replacement, or migration occurred.

## WPORG-28R Result

Date: 2026-07-25

### Summary

- Result: `BLOCKED`
- Exact finding identifier: `WPORG-28R`
- Starting mirror HEAD: `52ab3603629688f569887d1bd204d03925ca860a` (`Verify final public package prereview`)
- Starting parent: `3dcd8e70ed00f8885f34334eaa3f0272bc2d3b62`
- Fresh build command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28r.B7JVEU/build --force`
- Fresh build result: `/tmp/wporg-28r.B7JVEU/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `1455608bdc7a4b785d88f66245ea5bd984f06da55d6b71e961a800c465b9682e`
- Reproducibility note: a second fresh rebuild on the same `2026-07-25` source state produced `/tmp/wporg-28r-repro.mWtjGY/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `8effb3e071f5779d3106763183cbfb92030ba899fb39208874e713cb60a3cf19`, while both extracted builds shared the same packaged-content manifest SHA-256 `07e6e41cfe6cdd38cef41552f8b72ad7230bd0b2efc1fb73f514ab629fdcdb86`; the archive hash is therefore non-deterministic and is not treated as evidence of packaged-content drift.
- Fresh packaged Plugin Check command: `WP_CLI_PHP_ARGS='-d error_reporting=24575 -d display_errors=0' wp --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28r.B7JVEU/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`; the same leading WP-CLI / dependency deprecation noise still preceded the JSON payload and was normalized away before parsing.
- Fresh packaged Plugin Check result: exit `0`, `309` errors, `1709` warnings, `2018` total findings, `34` unique rule codes
- Exact comparison with `WPORG-28Q`: the fresh `2026-07-25` rerun reproduced the same `309` errors, `1709` warnings, and `2018` total findings recorded under `WPORG-28Q`, but the earlier conclusion that those findings were fully reconciled is not supported by repository evidence.
- Exact comparison with the prior packaged residual baseline from `docs/plugin-check-1.0.0-2026-07-22-raw.txt`: current `2018 / 309 / 1709 / 34` versus prior `2044 / 330 / 1714 / 36`; `WordPress.WP.I18n.MissingTranslatorsComment` (`20`), `WordPress.WP.EnqueuedResourceParameters.MissingVersion` (`1`), and `unexpected_markdown_file` (`1`) are now gone; `WordPress.Security.NonceVerification.Missing` dropped from `89` to `85`; `WordPress.WP.AlternativeFunctions.strip_tags_strip_tags` dropped from `2` to `1`; and `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in` appeared as a new current packaged warning.
- Initial rule-family classification totals from this pass: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=128`, `NEW_FINDING=1`, `UNMAPPED=46`, `SUBMISSION_BLOCKER=1843`
- Current parent totals after `WPORG-28R-F6`: `265` errors, `1707` warnings, `1972` total findings, `20` unique rule codes, with `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1843`
- Defensible readiness outcome: `Outcome C — Reopen remediation`
- Exact next action: `WPORG-28R-G0` now replaces the former open-ended post-`F6` state with the ordered residual blocker roadmap in `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`; the exact next implementation child is `WPORG-28R-G1 — Admin module form boundaries`.

### Mapping Method

- Parsed the fresh strict JSON payload from `/tmp/wporg-28r.B7JVEU/plugin-check.strict.json` after normalizing the leading WP-CLI / dependency deprecation noise.
- Compared every current rule code and count against `docs/plugin-check-1.0.0-2026-07-22-raw.txt`, `docs/plugin-check-1.0.0-raw.txt`, `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`, `docs/WPORG_PLUGIN_CHECK_HEATMAP_1.0.0.md`, `docs/WPORG_PREREVIEW_REMEDIATION.md`, `docs/wporg-remediation-ledger.md`, and `docs/wporg-review-source.md`.
- Treated a finding family as `KNOWN_NONBLOCKING` only when pre-`WPORG-28Q` repository evidence already documented the current packaged boundary as intentionally retained, technically acceptable, or explicitly closed under a verified child.
- Treated any family with no demonstrated pre-`WPORG-28Q` tracker coverage as `UNMAPPED`, even when the same code appeared in earlier raw artifacts.
- Treated any current family whose latest durable packaged triage still said `BLOCKER` or `SHOULD FIX BEFORE SUBMISSION`, with no later package-residual closeout superseding that state, as `SUBMISSION_BLOCKER`.
- Treated any rule code absent from the `2026-07-22` packaged residual baseline as `NEW_FINDING` unless a narrower prior packaged or tracker reconciliation already covered the exact current boundary.

### Error-Family Disposition

- `WordPress.Security.EscapeOutput.OutputNotEscaped` (`127`, prior packaged `127`) is `KNOWN_NONBLOCKING`. Current repository evidence maps the surviving output-contract boundaries to verified `WPORG-24` / `WPORG-24R` children and accepted page-owned renderer architecture rather than to an open parent defect.
- `WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet` (`1`, prior packaged `1`) is `KNOWN_NONBLOCKING`. Current repository evidence maps this exact `includes/modules/availability-date-dispatch/public.php:79` standalone-document `<link rel="stylesheet">` path to verified `WPORG-22R-J`.
- `PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent` (`1`, prior packaged `1`) is now `KNOWN_NONBLOCKING`. `WPORG-28R-B` demonstrated that the current occurrence is the Cloudflare Turnstile client at `includes/vendor-applications.php:2376`, loaded only from the fully configured `[vms_vendor_apply]` public form, with exact continuity to `fd67f51` (`Clarify Turnstile disclosure and tighten vendor apply enqueue guard`). The packaged `readme.txt:80-85` clearly documents the provider, purpose, activation condition, browser contact, server-side verification, and service/privacy links, and the current official WordPress.org guidance permits documented external service code while prohibiting offloaded assets unrelated to a service. Final WordPress.org acceptance is still not guaranteed because Plugin Check `2.0.0` emits a generic remote-resource error with no docs link or service-specific exception for this SDK URL.
- `WordPress.Security.EscapeOutput.ExceptionNotEscaped` (`0`, prior packaged `4`) is now fully reconciled. `WPORG-28R-C1` closed the two ticketing-owned packaged hits in `includes/integrations/ticketing-rules-v2.php`, and `WPORG-28R-C2` closed the two packaged `includes/social-share/providers/class-provider-webhook.php` occurrences (`114:40` and `119:67`) by documenting the internal webhook plain-text exception boundary with two line-specific suppressions and a focused boundary test. The rule code disappeared from the post-edit package and no unrelated packaged count increased.
- `PluginCheck.Security.DirectDB.UnescapedDBParameter` (`143`, prior packaged `143`; `44` errors + `99` warnings), `WordPress.DB.PreparedSQL.NotPrepared` (`67`, prior packaged `67`), `WordPress.DB.DirectDatabaseQuery.DirectQuery` (`298`, prior packaged `298`), `WordPress.DB.DirectDatabaseQuery.NoCaching` (`260`, prior packaged `260`), `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` (`137`, prior packaged `137`), `WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare` (`7`, prior packaged `7`), `WordPress.DB.SlowDBQuery.slow_db_query_meta_query` (`93`, prior packaged `93`), `WordPress.DB.SlowDBQuery.slow_db_query_meta_key` (`69`, prior packaged `69`), and `WordPress.DB.SlowDBQuery.slow_db_query_meta_value` (`8`, prior packaged `8`) remain `SUBMISSION_BLOCKER`. The latest durable packaged triage still labeled the DB / SQL family `BLOCKER`, and no later package-residual closeout superseded that state.
- `WordPress.Security.NonceVerification.Recommended` (`372`, prior packaged `372`), `WordPress.Security.NonceVerification.Missing` (`85`, prior packaged `89`), `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` (`134`, prior packaged `134`), `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` (`100`, prior packaged `100`), and `WordPress.Security.ValidatedSanitizedInput.InputNotValidated` (`3`, prior packaged `3`) remain `SUBMISSION_BLOCKER`. The latest durable packaged triage still labeled nonce/input handling `BLOCKER`, and the later `WPORG-19` / `WPORG-20` runtime slices never reconciled the remaining packaged counts or exact current packaged boundaries rule by rule.
- `WordPress.DateTime.RestrictedFunctions.date_date` (`25`, prior packaged `25`), `WordPress.PHP.DevelopmentFunctions.error_log_error_log` (`41`, prior packaged `41`), and `WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace` (`1`, prior packaged `1`) remain `SUBMISSION_BLOCKER`. The latest durable packaged triage still marked the date/time and logging families `SHOULD FIX BEFORE SUBMISSION`, and no later packaged residual closeout downgraded or accepted the current remaining counts.
- `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` (`0`, prior packaged `12`), `WordPress.WP.AlternativeFunctions.file_system_operations_fopen` (`0`, prior packaged `3`), and `WordPress.WP.AlternativeFunctions.file_system_operations_fread` (`0`, prior packaged `1`) are now fully reconciled. `WPORG-28R-F0` proved that the then-current family was exactly `38` rows across six ordered runtime children, `WPORG-28R-F1` later removed the pure helper-substitution rows (`parse_url_parse_url` `2 -> 0` and `strip_tags_strip_tags` `1 -> 0`), `WPORG-28R-F2` later removed the five admin `fclose('php://output')` rows with line-specific bounded-stream suppressions, `WPORG-28R-F3` later removed the two private-download `readfile()` rows, the six private-root `unlink()` rows, the two `chmod()` rows, and the two `is_writable()` rows, `WPORG-28R-F4` later removed the remaining Event Plan import `readfile()` row, the remaining Event Plan import `chmod()` row, two Event Plan import `unlink()` rows, two Event Plan import `fopen()` rows, and five Event Plan import `fclose()` rows, `WPORG-28R-F5` later removed the slow-request logger `unlink()`, `rename()`, and `is_writable()` rows, and `WPORG-28R-F6` has now removed the final bounded runtime-stream `fopen()`, `fread()`, and `fclose()` rows with occurrence-specific suppressions only. The packaged alternative-function family is therefore now absent from the public package, and total `UNMAPPED` now sits at `0`.
- `WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters` (`0`, prior packaged `2`) is now fully reconciled. `WPORG-28R-E1` removed the packaged `includes/ticketing/ticket-integrity-monitor.php:787:17` occurrence by documenting the bounded canonical integrity-scan query requirement with a line-specific suppression and focused boundary test, and `WPORG-28R-E2` removed the packaged `includes/taxonomies/vendor-type.php:431:13` occurrence by documenting that the one-time vendor-taxonomy canonicalization query uses `get_posts()`, which already defaults `suppress_filters` to `true`, so the retained explicit argument is redundant but behaviorally unchanged. The rule code disappeared from the post-edit package and no unrelated packaged count increased.

### New and Unmapped Residuals

- `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in` (`1`, prior packaged `0`) was the triggering `NEW_FINDING` in this pass. The occurrence was `includes/integrations/ticketing-rules-v2.php:7476`, in the later `WPORG-25` disabled-ticket suppression path that appends excluded ticket IDs through the native `tribe_tickets_get_tickets_query_args` boundary, and it is now closed under `WPORG-28R-A`.
- `Squiz.PHP.DiscouragedFunctions.Discouraged` (`0`, prior packaged `1`) is now fully reconciled. `WPORG-28R-F0` proved that the remaining packaged `includes/admin/settings-page.php:569` row came from Plugin Check `2.0.0`'s local `Squiz.PHP.DiscouragedFunctions` override for `set_time_limit()`, and `WPORG-28R-F2` has now removed that exact packaged occurrence with a line-specific suppression documenting the bounded administrator export path and the lack of a WordPress-native execution-limit replacement.

### Readiness Outcome

- `WPORG-28Q` correctly proved that the public package can be rebuilt, structure-audited, and rescanned on `2026-07-25`, but it overstated readiness by treating all remaining packaged findings as reconciled residuals.
- No repository evidence currently demonstrates that WordPress.org will accept the current packaged `ERROR` families merely because the packaged Plugin Check command exits `0`.
- After `WPORG-28R-F6`, the parent still remains blocked because the current residual baseline is `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1843`, and the still-blocking packaged DB / SQL, nonce / input, and date / logging families remain unresolved.
- The defensible current state is therefore `Outcome C — Reopen remediation`, not a ready-for-upload or fully reconciled closeout.

### Non-Actions

- No runtime, test, release-metadata, builder, or live-tree file changed in this slice.
- No upload, deployment, submission, reviewer reply, tag, push, production change, live replacement, or migration occurred.

## WPORG-28R-A Result

Date: 2026-07-25

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-A`
- Starting mirror HEAD: `f1f8d83b3de8ec8dae8e3c591dd15d7b7fd7613d` (`Reopen packaged Plugin Check remediation`)
- Starting parent: `52ab3603629688f569887d1bd204d03925ca860a`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Established public/live boundary before edit: public `includes/integrations/ticketing-rules-v2.php` SHA-256 `1aad70a8e6380e264a3d135cabe7575346019fbbbede52acdc5606bae42e37cd` versus read-only live `../../vms/includes/integrations/ticketing-rules-v2.php` SHA-256 `2de83c1b76a9cffe5fedfa47d6842aac137e93aecb0d56db96fb0fd5ea757235`; the files remained globally divergent because of unrelated JSON request-validation remediation, benefits-link behavior, and minor formatting drift.
- Exact target comparison before edit: only the hook registration, `vms_ticketing_v2_filter_disabled_ticket_query_args()`, `vms_ticketing_v2_disabled_ticket_products_for_plan()`, `vms_ticketing_v2_ticket_query_event_meta_keys()`, and `vms_ticketing_v2_event_id_from_ticket_query_args()` were compared. The public callback at approximately `7439-7476` and the live callback at approximately `7209-7246` were semantically equivalent, full-file parity was not attempted, and the live file remained outside edit scope.
- Query characterization: `add_filter('tribe_tickets_get_tickets_query_args', 'vms_ticketing_v2_filter_disabled_ticket_query_args', 30, 1);` modifies the public Event Tickets ticket query boundary, resolves the event from direct args, `meta_query`, or singular `tribe_events` fallback, resolves the plan via `vms_ticketing_v2_find_plan_id_by_tec_event_id()`, derives disabled IDs from `vms_ticketing_v2_disabled_ticket_products_for_plan()`, and merges those plan-scoped disabled ticket IDs into incoming `post__not_in` while preserving unrelated constraints.
- Disabled-ID scope and behavior: the excluded IDs are plan-scoped, bounded by saved plan configuration plus pending-sync map entries and child variations, the practical maximum cardinality remains the finite disabled ticket set for one resolved plan, no-plan or no-disabled cases leave the query unchanged, existing `post__not_in` values are merged and deduplicated, existing `post__in` values are preserved as-is, and non-ticket queries or cancelled-event handling remain unaffected.
- Pre-edit focused verification passed: `php tests/ticketing-disabled-ticket-native-suppression-remediation.php` and `php tests/event-plan-legacy-ticketing-integration-smoke.php`.
- Pre-edit package build command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28ra-pre.6SjHAj/build --force`
- Pre-edit package result: `/tmp/wporg-28ra-pre.6SjHAj/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `5e981800f8d78fc12c583d3ee5b047169d4215bc53d6228822de5978946402a8`
- Pre-edit packaged Plugin Check command: `WP_CLI_PHP_ARGS='-d error_reporting=24575 -d display_errors=0' wp --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28ra-pre.6SjHAj/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Pre-edit packaged Plugin Check result: exit `0`, target `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in` count `1` at packaged `includes/integrations/ticketing-rules-v2.php:7476`, `309` errors, `1709` warnings, `2018` total findings, and `34` unique rule codes.
- Implementation decision gate: `Option A` was rejected because the helper exposes only disabled IDs, not a complete authoritative allowlist of valid public ticket posts; `Option B` was unavailable because the authoritative query assembly does not occur earlier in this bounded file; `Option C` was accepted because the exclusion list is plan-scoped and bounded, query-level exclusion preserves pagination and result counts, and project conventions already permit line-specific `phpcs:ignore` usage.
- Runtime remediation applied: added a single line-specific `phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in` immediately above the existing `post__not_in` merge in `vms_ticketing_v2_filter_disabled_ticket_query_args()` with a bounded plan-scope and pagination/counts justification; runtime behavior did not change.
- Focused test update: `tests/ticketing-disabled-ticket-native-suppression-remediation.php` now asserts the exact one-line suppression token, and no other focused test file changed.
- Post-edit syntax and focused verification passed: `php -l includes/integrations/ticketing-rules-v2.php`, `php tests/ticketing-disabled-ticket-native-suppression-remediation.php`, `php tests/event-plan-legacy-ticketing-integration-smoke.php`, and the additional narrowly relevant `php tests/ticketing-output-buffer-lifecycle-characterization.php`.
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`.
- Post-edit source checksums: public `includes/integrations/ticketing-rules-v2.php` SHA-256 `18b4a12eb7e83bd3d8a8b0f9d360a1744579808b0a3a61ba401918fbe83f3477`; live `../../vms/includes/integrations/ticketing-rules-v2.php` remained `2de83c1b76a9cffe5fedfa47d6842aac137e93aecb0d56db96fb0fd5ea757235`.
- Post-edit package build: the exact `php scripts/build-public-release.php --output-dir /tmp/wporg-28ra-post.PadP96/build --force` attempt failed only because the builder enforces a clean worktree once runtime/test edits exist, so the successful verification rerun used `php scripts/build-public-release.php --output-dir /tmp/wporg-28ra-post2.hpJlqH/build --force --allow-dirty`.
- Post-edit package result: `/tmp/wporg-28ra-post2.hpJlqH/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `67fc5c5ddd381184800498b61fcce2deff46f5942d9de99e50d9fb6d7d5fe277`, `427` extracted package files, root `backstage-venue-manager/`, entry file `vendor-management-system.php`, version `1.2.0`, and text domain `backstage-venue-manager`.
- Post-edit packaged Plugin Check result: exit `0`, `309` errors, `1708` warnings, `2017` total findings, `33` unique rule codes, and target `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in` count `0`.
- Exact pre/post code-count delta: only `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in` changed, from `1` to `0`; no unrelated finding increased and no new rule code appeared.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=128`, `NEW_FINDING=0`, `UNMAPPED=46`, `SUBMISSION_BLOCKER=1843`.
- Readiness conclusion: this child closes the lone new packaged finding, but `WPORG-28R` remains blocked by the surviving `UNMAPPED` and `SUBMISSION_BLOCKER` families. Do not treat the current package as ready for final artifact preparation, upload/submission, reviewer communication, slug reservation, or production convergence.

### What Changed

- Added the bounded one-line `phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in` comment above the existing disabled-ticket `post__not_in` merge in `includes/integrations/ticketing-rules-v2.php`.
- Added the exact suppression-token assertion to `tests/ticketing-disabled-ticket-native-suppression-remediation.php`.
- Rebuilt the public package in disposable directories, reran the focused ticketing suites plus release/regression harnesses, and reran packaged strict-json Plugin Check from the extracted public package.
- Updated `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` so the parent `WPORG-28R` state now reflects the resolved `NEW_FINDING=0` package baseline without claiming final package readiness.

### Non-Actions

- The live `../../vms/includes/integrations/ticketing-rules-v2.php` file remained read-only and unchanged; full mirror/live convergence was not attempted.
- No other runtime PHP file, build script, package manifest, release metadata file, asset, add-on, migration, production tree, or unrelated test was edited.
- No upload, deployment, submission, reviewer reply, push, tag, production change, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-B Result

Date: 2026-07-25

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-B`
- Starting mirror HEAD: `736f398af9ffbd1814807212243aa3c03189a0cc` (`Document bounded disabled ticket exclusion`)
- Starting parent: `f1f8d83b3de8ec8dae8e3c591dd15d7b7fd7613d`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Pre-edit package build command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rb-pre.4RnyrA/build --force`
- Pre-edit package result: `/tmp/wporg-28rb-pre.4RnyrA/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `e4fc136c3fc286b57fdd8d993291f7fb6f28ea7086b6ae87165dd79b101eb24b`
- Pre-edit packaged Plugin Check command: `WP_CLI_PHP_ARGS='-d error_reporting=24575 -d display_errors=0' wp --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28rb-pre.4RnyrA/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`
- Pre-edit packaged Plugin Check result: exit `0`, `309` errors, `1708` warnings, `2017` total findings, `33` unique rule codes, target count `1`
- Exact target occurrence: strict JSON row `{file:\"/privateincludes/vendor-applications.php\", line:2376, column:9, type:\"ERROR\", code:\"PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent\", message:\"Found call to wp_enqueue_script() with external resource. Offloading scripts to your servers or any remote service is disallowed.\", docs:\"\"}`; the empty docs field confirms the emitted occurrence does not link to a rule page directly.
- Exact external resource: `https://challenges.cloudflare.com/turnstile/v0/api.js`
- Resource type: JavaScript
- Service provider and purpose: Cloudflare Turnstile, used as optional anti-abuse protection for the public Vendor Application form.
- Exact enqueue boundary: `vms_vendor_apply_shortcode()` enqueues script handle `cf-turnstile` and renders the widget only inside shortcode `[vms_vendor_apply]`; there is no broad `wp_enqueue_scripts`, admin, REST, AJAX, cron, or CLI enqueue hook.
- Guard behavior: the resource loads only after `vms_vendor_apply_turnstile_is_configured()` confirms both site key and secret key, only on the active rendered form path, only once per render, and never from visitor-controlled URLs. If either key is missing, the public form is unavailable and the client is not loaded. Administrators can disable the integration by omitting either key.
- Data boundary: loading the client may disclose ordinary browser request data plus the public site key; server-side verification sends only the Turnstile response token, visitor IP address, and secret-key-authenticated verification request; the Vendor Application form contents are not sent to Cloudflare through this integration.
- Packaged disclosure state: packaged `readme.txt:80-85` identifies provider, purpose, activation condition, browser contact, server-side verification, and service/privacy links; the current shortcode also withholds the form and remote client when incomplete, and only shows the administrator-facing incomplete-config diagnostic to users with `manage_options`.
- Prior Turnstile remediation continuity: `fd67f51` (`Clarify Turnstile disclosure and tighten vendor apply enqueue guard`) introduced `vms_vendor_apply_turnstile_is_configured()`, the current two-key gate, the current `VMS_VERSION`-versioned Turnstile enqueue, the current readme disclosure block, and the focused contract test. No later commit weakened that guard or disclosure; later history changed only unrelated `readme.txt` public-release metadata.
- Official policy and rule evidence: the installed Plugin Check sniff `PluginCheck/Sniffs/CodeAnalysis/EnqueuedResourceOffloadingSniff.php` generically flags any enqueued remote resource and its helper pattern explicitly matches `cloudflare.com`. The current official WordPress.org developer docs say Software as a Service is permitted when clearly documented, external code from documented services is permitted, offloaded assets unrelated to a service are prohibited, and third-party services must be clearly disclosed with links and privacy/terms references.
- Decision outcome: `Outcome A — Required disclosed service resource`
- Classification result: this exact occurrence is now reconciled as a project-level `KNOWN_NONBLOCKING` residual for `WPORG-28R`, but final WordPress.org acceptance is not guaranteed because Plugin Check still reports it as a generic `ERROR` with no service exception.
- Focused verification reruns passed: `php tests/vendor-apply-turnstile-contract-remediation.php` and `php tests/vendor-apply-inline-js-remediation.php`
- Broader regression reruns passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Live reference: inspected read-only `../../vms/includes/vendor-applications.php` with SHA-256 `e440227fc398fe14234d897d89bc62fe8c37f7bebe13367dcb99e9a8b8d2cfdd`; it remained unchanged throughout this child.
- Package-postcheck note: no runtime, readme, or test file changed in this child, so no post-edit package rerun was required; the packaged counts remain the reproduced pre-edit values above because the packaged boundary itself did not change.
- Final parent totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=45`, `SUBMISSION_BLOCKER=1843`, while the current packaged scan itself remains `309` errors, `1708` warnings, `2017` total findings, and `33` unique rule codes.
- Readiness conclusion: this child reconciles the Turnstile `OffloadedContent` occurrence for project tracking only. `WPORG-28R` and overall submission readiness remain blocked by the remaining `UNMAPPED` and `SUBMISSION_BLOCKER` families.

### What Changed

- Reproduced the clean packaged `OffloadedContent` occurrence from committed source in a disposable directory and normalized the strict JSON result for current evidence.
- Re-verified the current Turnstile enqueue guard, disclosure copy, and prior remediation continuity against the existing source, current packaged `readme.txt`, the focused Turnstile contract tests, the installed Plugin Check sniff, and the current official WordPress.org service-disclosure guidance.
- Updated `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` to map this exact current occurrence from `UNMAPPED` to `KNOWN_NONBLOCKING` without changing runtime behavior or suppressing the rule.

### Non-Actions

- No runtime PHP file, `readme.txt`, focused test file, build script, asset, package manifest, live-tree file, or release metadata file changed in this child.
- No suppression was added for `PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent`; the packaged occurrence remains visible for reviewer evaluation.
- No upload, deployment, submission, reviewer reply, push, tag, production change, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-C1 Result

Date: 2026-07-25

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-C1`
- Starting mirror HEAD: `9c15884cecc1fa7e133d129d01a185b3bda37e37` (`Reconcile Turnstile offloaded content`)
- Starting parent: `736f398af9ffbd1814807212243aa3c03189a0cc`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Read-only live checksum stayed fixed at `2de83c1b76a9cffe5fedfa47d6842aac137e93aecb0d56db96fb0fd5ea757235` before and after this child; the live `../../vms/includes/integrations/ticketing-rules-v2.php` file remained unchanged throughout.
- Pre-edit package build command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rc1-pre.pxzDtB/build --force`
- Pre-edit package result: `/tmp/wporg-28rc1-pre.pxzDtB/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `9a0e40f650610a58b48cb714658aa65cfe93ab92b30cc5a4f65f02b6b8faf757` and build timestamp `2026-07-26T00:10:12+00:00` UTC
- Pre-edit packaged Plugin Check command: `WP_CLI_PHP_ARGS='-d error_reporting=24575 -d display_errors=0' wp --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28rc1-pre.pxzDtB/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`, PHPCS `3.13.5`, and WordPressCS `3.3.0`
- Pre-edit packaged Plugin Check result: exit `0`, `309` errors, `1708` warnings, `2017` total findings, `33` unique rule codes, and `WordPress.Security.EscapeOutput.ExceptionNotEscaped=4` across exactly two packaged ticketing rows (`includes/integrations/ticketing-rules-v2.php:8376:4`, `:8432:25`) plus two packaged webhook rows (`includes/social-share/providers/class-provider-webhook.php:114:40`, `:119:67`)
- Installed rule evidence: the bundled WordPressCS `EscapeOutputSniff` listens for `T_THROW`, scans exception constructor parameters outside `try/catch`, and emits the dedicated `ExceptionNotEscaped` code; the bundled WPCS changelog records that behavior as the 3.0.0+ exception-message extension.
- Exact ticketing constructions: `vms_ticketing_v2_store_api_checkout_update_order_meta()` threw `new Exception(implode("\n", $messages))`; `vms_ticketing_v2_store_api_validate_add_to_cart()` threw `new Exception($message)`.
- Message provenance and shape: `$messages` came from `vms_ticketing_v2_capture_checkout_blocker_error_messages()`, which temporarily cleared Woo notices, ran the bounded cart validators, harvested only Woo `error` notices through `vms_ticketing_v2_atomic_error_notices()`, stripped all tags with `wp_strip_all_tags()`, sanitized each string with `sanitize_text_field()`, deduplicated, and returned a `string[]`; `$message` came from the first non-empty sanitized candidate in `vms_ticketing_v2_atomic_error_notices()` after `vms_ticketing_v2_validate_add_to_cart()` populated Woo error notices, with the existing fallback translation `This item could not be added to cart.` when no notice text remained.
- Data-class characterization: the two exception messages can contain translated validation copy, event or product titles, product-group labels, program labels, counts, and seat numbers after plain-text normalization; claim-assignment inputs normalize assignee emails and seat values before validation, but user-facing messages do not echo raw email addresses; no raw request arrays, nested objects, HTML fragments, secrets, tokens, nonces, raw JSON bodies, or authorization material reach either exception payload.
- Caller, catch, and sink characterization: the two functions are registered on `woocommerce_store_api_checkout_update_order_meta` and `woocommerce_store_api_validate_add_to_cart`; WooCommerce Store API `CartController` explicitly expects `woocommerce_store_api_validate_add_to_cart` callbacks to throw `\Exception` to block the request; Store API `AbstractRoute` and `Checkout` catch generic `\Exception` and convert `getMessage()` into REST/JSON error payloads; the sibling `woocommerce_store_api_cart_errors` path adds the same plain-text blocker messages to `WP_Error`, and adjacent AJAX boundaries in the same file also intentionally expose plain-text `message`, `errors`, `notice_messages`, and `checkout_blocker_messages` arrays in JSON. Escaping at exception construction would therefore corrupt the existing machine-readable plain-text contract instead of protecting a direct HTML sink.
- Decision outcome: `Outcome A — bounded plain-text Store API exception sinks`
- Runtime remediation applied: added exactly two same-line `phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped` comments on the two Store API `throw new Exception(...)` statements in `includes/integrations/ticketing-rules-v2.php`; no webhook code, live-tree code, or broader runtime behavior changed.
- Focused test added: `tests/ticketing-exception-message-boundary-remediation.php` asserts the exact two suppression tokens, proves the webhook file stays untouched, and characterizes the ticketing exception messages as normalized plain-text strings for the Store API `WP_Error` / exception JSON boundary.
- Post-edit syntax and focused verification passed: `php -l includes/integrations/ticketing-rules-v2.php`, `php tests/ticketing-exception-message-boundary-remediation.php`, `php tests/ticketing-v2-ajax-output-buffer-ownership.php`, and `php tests/ticket-checkout-safety-hardening.php`
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Post-edit source checksums: public `includes/integrations/ticketing-rules-v2.php` SHA-256 `e89a8055c8df52229dab2cef129304dd14dd46e7a045316f2c6366b7bbcfaa40`; live `../../vms/includes/integrations/ticketing-rules-v2.php` remained `2de83c1b76a9cffe5fedfa47d6842aac137e93aecb0d56db96fb0fd5ea757235`
- Post-edit package build: the exact `php scripts/build-public-release.php --output-dir /tmp/wporg-28rc1-post.pO0H7c/build --force` attempt failed only because the builder enforced the dirty-worktree policy at `2026-07-26T00:20:19+00:00` UTC, so the successful verification rerun used `php scripts/build-public-release.php --output-dir /tmp/wporg-28rc1-post2.3xeZFs/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rc1-post2.3xeZFs/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `6c05bee0e1016b8b667de181d618462b75f27c02ce9e8d7e3a8847b85067bac7`, `379` packaged files, root `backstage-venue-manager/`, entry file `vendor-management-system.php`, version `1.2.0`, and text domain `backstage-venue-manager`
- Post-edit packaged Plugin Check result: exit `0`, `307` errors, `1708` warnings, `2015` total findings, `33` unique rule codes, and `WordPress.Security.EscapeOutput.ExceptionNotEscaped=2` with the inventory now reduced exactly to `includes/social-share/providers/class-provider-webhook.php:114:40` and `:119:67`
- Exact pre/post code-count delta: only `WordPress.Security.EscapeOutput.ExceptionNotEscaped` changed, from `4` to `2`; the two removed rows were exactly the packaged ticketing-owned occurrences, no unrelated finding increased, and no new rule code appeared.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=43`, `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child closes the ticketing half of the packaged `ExceptionNotEscaped` family, but `WPORG-28R` remains blocked by the two remaining unmapped webhook occurrences plus the surviving broader `UNMAPPED` and `SUBMISSION_BLOCKER` families. Do not treat the current package as ready for final artifact preparation, upload/submission, reviewer communication, slug reservation, or production convergence.

### What Changed

- Added the two bounded same-line `phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped` comments on the Store API ticketing exception throws in `includes/integrations/ticketing-rules-v2.php`.
- Added `tests/ticketing-exception-message-boundary-remediation.php` to assert the exact suppression ownership and the plain-text Store API exception / `WP_Error` JSON contract.
- Rebuilt the public package in disposable directories, reran the focused ticketing suites plus release/regression harnesses, and reran packaged strict-json Plugin Check from the extracted edited public package.
- Updated `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` so the parent `WPORG-28R` state now reflects the `307 / 1708 / 2015 / 33` packaged baseline and the remaining webhook-only `ExceptionNotEscaped` residual.

### Non-Actions

- The live `../../vms/includes/integrations/ticketing-rules-v2.php` file remained read-only and unchanged; full mirror/live convergence was not attempted.
- The webhook runtime file `includes/social-share/providers/class-provider-webhook.php` remained unchanged; the two packaged webhook `ExceptionNotEscaped` rows are still outstanding for the next child.
- No other runtime PHP file, build script, package manifest, release metadata file, asset, add-on, migration, production tree, or unrelated test was edited.
- No upload, deployment, submission, reviewer reply, push, tag, production change, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-C2 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-C2`
- Starting mirror HEAD: `19ff1874918e4ffc7ec60c75ccc12b70628396d4` (`Document ticketing exception boundaries`)
- Starting parent: `9c15884cecc1fa7e133d129d01a185b3bda37e37`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Read-only live checksum stayed fixed at `5d16d03221308777db149890bdd43022712b254f5b8cffdee4dec8d5af774240` before and after this child; the live `../../vms/includes/social-share/providers/class-provider-webhook.php` file remained unchanged throughout.
- Pre-edit package build command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rc2-pre.DEh52F/build --force`
- Pre-edit package result: `/tmp/wporg-28rc2-pre.DEh52F/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `0437302222c8306b8fc411a84e551d663277bd56a45e1fe3c1ad50addc6e73ce` and build timestamp `2026-07-26T01:59:23+00:00` UTC
- Pre-edit packaged Plugin Check command: `WP_CLI_PHP_ARGS='-d error_reporting=24575 -d display_errors=0' wp --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28rc2-pre.DEh52F/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`, PHPCS `3.13.5`, and WordPressCS `3.3.0`
- Pre-edit packaged Plugin Check result: exit `0`, `307` errors, `1708` warnings, `2015` total findings, `33` unique rule codes, and `WordPress.Security.EscapeOutput.ExceptionNotEscaped=2` across exactly the packaged webhook rows `includes/social-share/providers/class-provider-webhook.php:114:40` and `:119:67`; no packaged ticketing occurrence remained.
- Installed rule evidence: the bundled WordPressCS `EscapeOutputSniff` scans `throw` statements, checks each exception-constructor parameter individually, skips throws inside `try` blocks, and emits the dedicated `ExceptionNotEscaped` code; the bundled WordPressCS `3.3.0` changelog records this as the 3.0.0+ exception-message extension and explicitly notes that the separate error code supports line-specific ignores.
- Exact webhook constructions: `VMS_Social_Provider_Webhook::publish()` threw `new RuntimeException((string) $response->get_error_message())` on the `WP_Error` path and `new RuntimeException('Webhook returned HTTP ' . $code)` on the non-2xx HTTP path; the same method also retained the unflagged static throws `Webhook URL is missing.` and `Unable to encode webhook payload.`.
- Message provenance and shape: the first dynamic message came from `WP_Error::get_error_message()` after `wp_remote_post()` returned a `WP_Error`; current WordPress `WP_Http::request()` wraps `WpOrg\Requests\Exception` into `new WP_Error('http_request_failed', $e->getMessage())`, and the Requests cURL transport constructs messages like `cURL error %s: %s` from an integer transport code plus a transport-reason string. The second dynamic value was `$code = (int) wp_remote_retrieve_response_code($response)`, a bounded integer HTTP status. Neither exception message includes the response body, request body, headers, signature, destination ID, or explicit endpoint text assembled by the provider.
- Caller, catch, and sink characterization: repository search found the only supported `publish()` call at `includes/social-share/queue-runner.php:337` inside the surrounding `try` at line `335`, with a `catch (Throwable $error)` at line `362`; the queue runner normalizes the message through `sanitize_text_field()` at line `369`, stores `last_error_message` through queue writes sanitized again in `includes/social-share/queue-repo.php:397` and `:535-536`, persists the auth-expired side path through `vms_social_account_set_auth_state(..., array('last_error' => $message))` at `includes/social-share/queue-runner.php:372`, renders stored queue errors with `esc_html()` in `includes/social-share/admin.php:506` and `includes/social-share/event-plan-panel.php:463`, and JSON-encodes audit details after `vms_social_sanitize_details()` in `includes/social-share/audit.php:69-70`. No direct REST, AJAX, email, CLI, or PHP-log sink for these webhook exception messages was found in the current repository, and no direct invocation of `publish()` outside the queue runner was found.
- Decision outcome: `Outcome A — bounded internal plain-text webhook exception sinks`
- Runtime remediation applied: added exactly two same-line `phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped` comments on the two webhook `throw new RuntimeException(...)` statements in `includes/social-share/providers/class-provider-webhook.php`; runtime behavior, request method, headers, timeout, success behavior, retry behavior, queue schema, and audit schema did not change.
- Focused test added: `tests/social-share-webhook-exception-boundary-remediation.php` asserts the exact two webhook suppressions, proves the ticketing suppressions remain unchanged, verifies the webhook request contract, verifies the direct `WP_Error` and HTTP `403` exception messages, and proves the queue runner sanitizes/stores/audits the resulting plain-text diagnostics while the downstream renderers retain contextual escaping.
- Post-edit syntax and focused verification passed: `php -l includes/social-share/providers/class-provider-webhook.php`, `php -l tests/social-share-webhook-exception-boundary-remediation.php`, `php tests/social-share-webhook-exception-boundary-remediation.php`, `php tests/social-share-queue-snapshot-json-remediation.php`, and `php tests/social-event-panel-finite-renderer-output-remediation.php`
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Post-edit source checksums: mirror `includes/social-share/providers/class-provider-webhook.php` `89b3b62ae395497878fc269eccade771179fee9cca33723695838617788d3cdd`; mirror `tests/social-share-webhook-exception-boundary-remediation.php` `9044db4ab79bbca35be412e227de1754c250f051ce1305e350d442e6aded5090`; live `../../vms/includes/social-share/providers/class-provider-webhook.php` remained `5d16d03221308777db149890bdd43022712b254f5b8cffdee4dec8d5af774240`
- Post-edit package build: the authorized dirty-worktree verification rerun used `php scripts/build-public-release.php --output-dir /tmp/wporg-28rc2-post.FoOEEU/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rc2-post.FoOEEU/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `77ae0bfc82c78cb79e54faf03799ac66f1a5170bb3b23a247a6e55e608f86869`, `379` packaged files, root `backstage-venue-manager/`, entry file `vendor-management-system.php`, version `1.2.0`, and text domain `backstage-venue-manager`
- Post-edit packaged Plugin Check result: exit `0`, `305` errors, `1708` warnings, `2013` total findings, `32` unique rule codes, and `WordPress.Security.EscapeOutput.ExceptionNotEscaped=0`
- Exact pre/post code-count delta: only `WordPress.Security.EscapeOutput.ExceptionNotEscaped` changed, from `2` to `0`; no unrelated finding increased, no new rule code appeared, and the rule code disappeared entirely from the post-edit package.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=41`, `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child closes the webhook half of the packaged `ExceptionNotEscaped` family and fully reconciles that rule family through `WPORG-28R-C1` and `WPORG-28R-C2`, but `WPORG-28R` remains blocked by the surviving `UNMAPPED` and `SUBMISSION_BLOCKER` families. Do not treat the current package as ready for final artifact preparation, upload/submission, reviewer communication, slug reservation, or production convergence.

### What Changed

- Added the two bounded same-line `phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped` comments on the webhook provider exception throws in `includes/social-share/providers/class-provider-webhook.php`.
- Added `tests/social-share-webhook-exception-boundary-remediation.php` to assert suppression ownership, the unchanged ticketing suppression count, the preserved webhook request and exception contracts, and the sanitize/store/escape sink chain.
- Rebuilt the public package in disposable directories, reran the focused webhook and social-share continuity suites plus the release/regression harnesses, and reran packaged strict-json Plugin Check from the extracted edited public package.
- Updated `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` so the parent `WPORG-28R` state now reflects the `305 / 1708 / 2013 / 32` packaged baseline, `UNMAPPED=41`, and the fully reconciled `ExceptionNotEscaped` family.

### Non-Actions

- The live `../../vms/includes/social-share/providers/class-provider-webhook.php` file remained read-only and unchanged; no mirror/live copy or convergence work was attempted.
- No queue runner, queue repository, admin renderer, event-panel renderer, audit helper, ticketing source, build script, package manifest, release metadata file, asset, add-on, migration, production tree, or unrelated test file was edited.
- No upload, deployment, submission, reviewer reply, push, tag, production change, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-E1 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-E1`
- Starting mirror HEAD: `a5b6e3b9fb46adc87743ccde87e3e2b174b2405e` (`Document webhook exception boundaries`)
- Starting parent: `19ff1874918e4ffc7ec60c75ccc12b70628396d4`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Read-only live checksum stayed fixed at `066eeaf16b910c930d4ad23eeca2b48669dbc889713d62dafcc80a7c58848122` before and after this child; the live `../../vms/includes/ticketing/ticket-integrity-monitor.php` file remained unchanged throughout.
- Deferred vendor-taxonomy checksum stayed fixed at `4ae832840023a8cd2d4c9a805e839927b003f71b29e0efa61bc1415944ff8c87` before and after this child; `includes/taxonomies/vendor-type.php` remained unchanged throughout.
- Pre-edit package build command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28re1-pre.gfe2mX/build --force`
- Pre-edit package result: `/tmp/wporg-28re1-pre.gfe2mX/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `b8163c449aad9d32ea2536b958ec21bcc7ebb18edaa09072c654aa9bb4ec8629`
- Pre-edit packaged Plugin Check command: `WP_CLI_PHP_ARGS='-d error_reporting=24575 -d display_errors=0' wp --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28re1-pre.gfe2mX/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`, PHPCS `3.13.5`, WordPressCS `3.3.0`, and VIPWPCS `3.0.1`
- Pre-edit packaged Plugin Check result: exit `0`, `305` errors, `1708` warnings, `2013` total findings, `32` unique rule codes, and `WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters=2` across exactly the packaged Ticket Integrity row `includes/ticketing/ticket-integrity-monitor.php:787:17` plus the deferred vendor-taxonomy row `includes/taxonomies/vendor-type.php:431:13`
- Query characterization: `vms_ticket_integrity_build_targets()` in `includes/ticketing/ticket-integrity-monitor.php` builds a bounded `WP_Query` over published `vms_event_plan` posts with `posts_per_page => $batch_size`, `paged => $paged`, `fields => 'ids'`, `no_found_rows => false`, `meta_key => '_vms_event_date'`, `orderby => 'meta_value'`, `meta_type => 'DATE'`, `order => 'ASC'`, a two-clause `meta_query` covering the `[ $start_date, $end_date ]` event-date window plus linked TEC event IDs greater than zero, `update_post_meta_cache => false`, `update_post_term_cache => false`, `cache_results => false`, `lazy_load_term_meta => false`, and literal `suppress_filters => true`
- Lifecycle and authorization: the query runs batch-wise through `vms_ticket_integrity_scan_all()`, with higher-level callers limited to the daily cron runner, the stale/missing State of the Range refresh path, and the nonce-protected `manage_options` manual admin scan; no unauthenticated public request path invokes this query, and single-event spot scans do not use it
- Core behavior: installed WordPress `7.0.1` `WP_Query` defaults `suppress_filters` to `false`, still runs `pre_get_posts` and `posts_pre_query` before the suppression-gated SQL/result hooks, and would change runtime behavior if the explicit argument were deleted; `get_posts()` separately defaults to `suppress_filters => true`, so swapping APIs would alter pagination and totals while merely hiding identical behavior from Plugin Check was not accepted
- Repository filter and rule evidence: current repository query filters for `vms_event_plan` are admin-screen or main-query constrained and do not currently alter this internal batch query, but generic third-party visibility/localization filters could hide canonical plans and create false-negative integrity gaps; installed VIPWPCS `WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters` flags only literal `true`, does not distinguish query APIs or bounded internal scans, treats the pattern as a risky compatibility/performance heuristic rather than proof of a defect, and supports line-specific `phpcs:ignore` suppression
- Decision outcome: `Outcome B — canonical unfiltered integrity scan data is required`
- Runtime remediation applied: added exactly one line-specific `phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters` immediately above the existing `suppress_filters => true` argument in `includes/ticketing/ticket-integrity-monitor.php`; runtime query behavior did not change
- Focused test added: `tests/ticket-integrity-query-filter-boundary-remediation.php` asserts the exact suppression token, the unchanged deferred vendor-taxonomy checksum, the unchanged live monitor checksum, the full query-argument contract, the call graph and capability/nonce boundaries, the canonical result set under simulated filter interference, the inactive-target behavior, and the existing batch-size cap
- Post-edit syntax and focused verification passed: `php -l includes/ticketing/ticket-integrity-monitor.php`, `php -l tests/ticket-integrity-query-filter-boundary-remediation.php`, `php tests/ticket-integrity-query-filter-boundary-remediation.php`, `php tests/ticket-integrity-scan-lock.php`, `php tests/state-of-range-delivery-state.php`, and `php tests/state-of-range-upcoming-filter.php`
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Post-edit package build: the authorized dirty-worktree verification rerun used `php scripts/build-public-release.php --output-dir /tmp/wporg-28re1-post.zwAarA/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28re1-post.zwAarA/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `fc9c64abc74d47783a1f4962d95fcc36db4040ece6629bd1aa7f54f3ba7d66be`, `379` packaged files, root `backstage-venue-manager/`, entry file `vendor-management-system.php`, version `1.2.0`, and text domain `backstage-venue-manager`
- Post-edit packaged Plugin Check result: exit `0`, `304` errors, `1708` warnings, `2012` total findings, `32` unique rule codes, and `WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters=1` with only the deferred packaged vendor-taxonomy row `includes/taxonomies/vendor-type.php:431:13` remaining
- Exact pre/post code-count delta: only `WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters` changed, from `2` to `1`; the removed row was exactly the packaged Ticket Integrity occurrence, no unrelated finding increased, no new rule code appeared, and no direct-DB or slow-query family count changed
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=40`, `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child closes only the Ticket Integrity occurrence. The full packaged `SuppressFilters_suppress_filters` family remains open because the separate vendor-taxonomy occurrence still requires `WPORG-28R-E2`, and `WPORG-28R` overall remains blocked.

### What Changed

- Added one bounded line-specific `phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters` comment above `suppress_filters => true` in `includes/ticketing/ticket-integrity-monitor.php`.
- Added `tests/ticket-integrity-query-filter-boundary-remediation.php` to assert suppression ownership, the unchanged deferred and live checksums, the canonical query contract, and the bounded invocation and batching behavior.
- Rebuilt the public package in disposable directories, reran the focused Ticket Integrity suites plus the release/regression harnesses, and reran packaged strict-json Plugin Check from the extracted edited public package.
- Updated `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` so the parent `WPORG-28R` state now reflects the `304 / 1708 / 2012 / 32` packaged baseline, `UNMAPPED=40`, and the remaining vendor-taxonomy `SuppressFilters` occurrence for `WPORG-28R-E2`.

### Non-Actions

- The live `../../vms/includes/ticketing/ticket-integrity-monitor.php` file remained read-only and unchanged; no mirror/live copy or convergence work was attempted.
- The deferred `includes/taxonomies/vendor-type.php` file remained read-only and unchanged; that separate packaged occurrence is still open for `WPORG-28R-E2`.
- No other Ticket Integrity runtime file, social-share file, Event Plan file, build script, package manifest, release metadata file, asset, add-on, migration, production tree, or unrelated test file was edited.
- No upload, deployment, submission, reviewer reply, push, tag, production change, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-E2 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-E2`
- Starting mirror HEAD: `ad37850bb2fa0fe223932d3d162991392375fa9a` (`Document ticket integrity filter suppression`)
- Starting parent: `a5b6e3b9fb46adc87743ccde87e3e2b174b2405e`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Read-only live checksum stayed fixed at `4ae832840023a8cd2d4c9a805e839927b003f71b29e0efa61bc1415944ff8c87` before and after this child; the live `../../vms/includes/taxonomies/vendor-type.php` file remained unchanged throughout, and the target function, init registration, and directly used helpers stayed semantically equivalent to the mirror before edit.
- Protected Ticket Integrity checksums stayed fixed at `27770ef0be288290a7f7d5e5e7a92ee27e93f79e55d9f95d29637671415dcdfc` for `includes/ticketing/ticket-integrity-monitor.php` and `0e630063e869cd6ce6816a4c5cfb4710a9bf29e90d1c37f5b9fd5bffeb50beac` for `tests/ticket-integrity-query-filter-boundary-remediation.php`; both protected files remained unchanged throughout.
- Pre-edit package build command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28re2-pre.8ehiaZ/build --force`
- Pre-edit package result: `/tmp/wporg-28re2-pre.8ehiaZ/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `e6e52bc2dc31c303ebf540576d6ad3299a8e8115fc9addf325c6996cf8790ece`
- Pre-edit packaged Plugin Check command: `WP_CLI_PHP_ARGS='-d error_reporting=24575 -d display_errors=0' wp --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28re2-pre.8ehiaZ/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`, PHPCS `3.13.5`, WordPressCS `3.3.0`, and VIPWPCS `3.0.1`
- Pre-edit packaged Plugin Check result: exit `0`, `304` errors, `1708` warnings, `2012` total findings, `32` unique rule codes, and `WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters=1` at only packaged `includes/taxonomies/vendor-type.php:431:13`; no packaged Ticket Integrity occurrence remained.
- Query characterization: `vms_vendor_type_maybe_canonicalize_terms(): void` in `includes/taxonomies/vendor-type.php` runs one-time canonical vendor-type repair work on `init`, normalizes alias vendor-type terms plus legacy `_vms_secondary_vendor_type` event-plan meta, and uses `get_posts([ 'post_type' => 'vms_event_plan', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'suppress_filters' => true ])` over all `vms_event_plan` IDs.
- Lifecycle and authorization: the query is registered through `add_action('init', 'vms_vendor_type_maybe_canonicalize_terms', 22);` after taxonomy registration and default-term seeding, short-circuits once option `vms_vendor_type_canonicalized_v1` is set, can run during normal plugin initialization across admin/frontend/CLI/REST, accepts no visitor-controlled inputs, and stays inside administrator-controlled taxonomy/meta repair state.
- Core behavior: installed WordPress `7.0.1` `get_posts()` already defaults `suppress_filters` to `true` and `no_found_rows` to `true`, so deleting the explicit argument would not change runtime behavior; `pre_get_posts` and `posts_pre_query` still run before the suppression-gated SQL/result hooks, while switching to `WP_Query` would change API semantics unless suppression stayed explicit.
- Repository filter and rule evidence: current repository `vms_event_plan` query filters are admin-screen or main-query constrained and do not currently affect this internal init-time migration query; installed VIPWPCS `WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters` flags only literal `true`, does not distinguish query APIs or redundant `get_posts()` defaults, and supports line-specific `phpcs:ignore` suppression.
- Decision outcome: `Outcome C — explicit argument is redundant`
- Runtime remediation applied: added exactly one line-specific `phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters` immediately above the existing `suppress_filters => true` argument in `includes/taxonomies/vendor-type.php`; runtime query behavior did not change.
- Focused test added: `tests/vendor-type-query-filter-boundary-remediation.php` asserts the exact suppression token and one-line ownership, the unchanged live vendor-taxonomy checksum, the unchanged protected Ticket Integrity checksums, the full canonicalization query-argument contract, the init hook boundary, alias-term merge/meta-copy behavior, legacy secondary-vendor-type normalization, and the option guard short-circuit.
- Post-edit syntax and focused verification passed: `php -l includes/taxonomies/vendor-type.php`, `php -l tests/vendor-type-query-filter-boundary-remediation.php`, `php tests/vendor-type-query-filter-boundary-remediation.php`, `php tests/event-plan-secondary-vendor-assignments.php`, `php tests/event-plan-editor-vendor-preservation.php`, and `php tests/event-plan-calendar-resync-isolated.php`
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Post-edit package build: the authorized dirty-worktree verification rerun used `php scripts/build-public-release.php --output-dir /tmp/wporg-28re2-post.edcDEd/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28re2-post.edcDEd/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `a163fbd4178cabe9dae2866e54483ca34d96142203bea3da79821f7afac121e0`, `379` packaged files, root `backstage-venue-manager/`, entry file `vendor-management-system.php`, version `1.2.0`, and text domain `backstage-venue-manager`
- Post-edit packaged Plugin Check result: exit `0`, `303` errors, `1708` warnings, `2011` total findings, `31` unique rule codes, and `WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters=0`
- Exact pre/post code-count delta: only `WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters` changed, from `1` to `0`; no unrelated finding increased, no new rule code appeared, and no direct-DB or slow-query family count changed.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=39`, `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child closes the vendor-taxonomy occurrence and fully reconciles the packaged `SuppressFilters_suppress_filters` family through `WPORG-28R-E1` and `WPORG-28R-E2`, but `WPORG-28R` remains blocked by the surviving `UNMAPPED` and `SUBMISSION_BLOCKER` families. Do not treat the current package as ready for final artifact preparation, upload/submission, reviewer communication, slug reservation, or production convergence.

### What Changed

- Added one bounded line-specific `phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters` comment above `suppress_filters => true` in `includes/taxonomies/vendor-type.php`.
- Added `tests/vendor-type-query-filter-boundary-remediation.php` to assert suppression ownership, the unchanged live and protected-file checksums, the canonicalization query contract, and the bounded one-time migration behavior.
- Rebuilt the public package in disposable directories, reran the focused vendor-taxonomy continuity suites plus the release/regression harnesses, and reran packaged strict-json Plugin Check from the extracted edited public package.
- Updated `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` so the parent `WPORG-28R` state now reflects the `303 / 1708 / 2011 / 31` packaged baseline, `UNMAPPED=39`, and the fully reconciled `SuppressFilters` family.

### Non-Actions

- The live `../../vms/includes/taxonomies/vendor-type.php` file remained read-only and unchanged; no mirror/live copy or convergence work was attempted.
- The protected `includes/ticketing/ticket-integrity-monitor.php` and `tests/ticket-integrity-query-filter-boundary-remediation.php` files remained read-only and unchanged.
- No other taxonomy file, vendor application or portal file, build script, package manifest, release metadata file, asset, add-on, migration, production tree, or unrelated test file was edited.
- No upload, deployment, submission, reviewer reply, push, tag, production change, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-F0 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-F0`
- Starting mirror HEAD: `405410e2713e80f44657e19a2aae0a9ce807d6d2` (`Document vendor taxonomy filter suppression`)
- Starting parent: `ad37850bb2fa0fe223932d3d162991392375fa9a`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Fresh build command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf0-pre.2JcEBj/build --force`
- Fresh build result: `/tmp/wporg-28rf0-pre.2JcEBj/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `27e0e21b91f2b81af1f0abd47c00e1ab3c30457271c49a631f0c4fc01b2e5619`
- Fresh packaged Plugin Check command: `WP_CLI_PHP_ARGS='-d error_reporting=24575 -d display_errors=0' wp --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28rf0-pre.2JcEBj/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`, PHPCS `3.13.5`, WordPressCS `3.3.0`, and VIPWPCS `3.0.1`
- Fresh packaged Plugin Check result: exit `0`, `303` errors, `1708` warnings, `2011` total findings, and `31` unique rule codes
- Exact `UNMAPPED` proof: the current `Squiz.PHP.DiscouragedFunctions.Discouraged` (`1`) plus the ten current `WordPress.WP.AlternativeFunctions.*` codes (`38`) total exactly `39`; every current `UNMAPPED` row belongs to those `11` codes, no other rule code contributes to `UNMAPPED`, none of the `39` rows was already reconciled by `WPORG-28R-A`, `WPORG-28R-B`, `WPORG-28R-C1`, `WPORG-28R-C2`, `WPORG-28R-E1`, or `WPORG-28R-E2`, and no previously reconciled rule reappeared in this clean package.
- Rule-source clarification: Plugin Check `2.0.0` overrides `Squiz.PHP.DiscouragedFunctions` in `phpcs-rulesets/plugin-check.ruleset.xml` to forbid `set_time_limit()`, while WordPressCS `AlternativeFunctionsSniff` separately maps `parse_url()` to `wp_parse_url()`, `strip_tags()` to `wp_strip_all_tags()`, `unlink()` to `wp_delete_file()`, `rename()` to `WP_Filesystem::move()`, and the remaining direct file operations to the generic `WP_Filesystem` family. The current package therefore contains one Plugin Check ruleset override row plus `38` WordPressCS alternative-function rows, not two unrelated residual families.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=39`, and `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child makes no runtime change. It verifies exact ownership, subsystem grouping, live-parity expectations, and ordered implementation scope for the remaining `39` packaged alternative-function rows only. `WPORG-28R` stays blocked until the follow-up runtime children and a fresh packaged rerun complete.

### Ownership Map

- `WPORG-28R-F1` pure helper substitutions (`3` rows): `includes/core/slow-request-logger.php:75,76` in `vms_slow_request_logger_parse_request_uri()` use `parse_url()` only for request-path and query extraction, and `includes/integrations/ticketing.php:55` in `vms_ticketing_ajax_attach_noise()` trims admin-only AJAX debug noise with `strip_tags()`. Both files are shared with live and currently hash-match their live counterparts. Existing focused evidence already covers these surfaces through `tests/slow-request-logger-request-input-characterization.php`, `tests/event-plan-performance-request-id-remediation.php`, `tests/ticketing-output-buffer-lifecycle-characterization.php`, and `tests/ticketing-v2-ajax-output-buffer-ownership.php`. Operational category: helper substitution only, with no streaming, atomicity, or permission-preservation requirement. Preliminary disposition: true defects and the safest first runtime child.
- `WPORG-28R-F2` admin CSV/output-stream and timeout boundaries (`6` rows): `includes/admin/settings-page.php:569,595` in `vms_handle_ticketing_stock_csv()`, `includes/admin/square-sync-protection.php:121` in the CSV export closure, `includes/modules/admissions/admin-ui.php:166` in `vms_admission_export_csv()`, and `includes/modules/admissions/pass-claims.php:1493,1681` in the two pass-claims export handlers. These rows all sit behind administrator capability and nonce gates and write CSV directly to `php://output`; `settings-page.php`, `square-sync-protection.php`, and `pass-claims.php` differ from live overall but the target export handlers remain semantically equivalent, while `modules/admissions/admin-ui.php` hash-matches live. Existing focused evidence already covers the broader surrounding files through `tests/settings-integrity-scan-output-remediation.php`, `tests/settings-default-venue-alert-output-remediation.php`, `tests/administrator-explicit-notice-output-remediation.php`, and the pass-claims public output suites. Operational requirements: streaming, header-before-body ordering, and possible long-running exports. Preliminary disposition: `set_time_limit()` has no WordPress-native replacement and the `fclose('php://output')` rows are not `WP_Filesystem` candidates, so this child must characterize justified-native retention or safe omission rather than blanket replacement.
- `WPORG-28R-F3` shared private-file, proof-image, and safety-download boundaries (`12` rows): `includes/core/private-files.php:402,594,599,614,637`, `includes/safety/private-files.php:263`, `includes/integrations/ticketing-verifications.php:798,835,884`, and `includes/helpers/image-normalization.php:24,86,90`. These rows cover WordPress-managed uploads, plugin-private storage, authenticated file downloads, verification-proof cleanup, and JPEG normalization. `includes/core/private-files.php` has no live counterpart; `includes/safety/private-files.php` and `includes/integrations/ticketing-verifications.php` differ from live overall but the target download/store/delete helpers remain present and semantically aligned; `includes/helpers/image-normalization.php` hash-matches live. Existing focused evidence already covers these subsystem boundaries through `tests/private-file-upload-api-remediation.php`, `tests/verification-proof-normalization.php`, and `tests/upload-validation-guards.php`. Operational requirements: local-path guarantees, download streaming, controlled deletion, and explicit `0640` permission preservation. Preliminary disposition: `readfile()` is likely justified for streamed downloads, `unlink()` and `is_writable()` may be replaceable only if `wp_delete_file()` or `wp_is_writable()` preserve the current private-path rollback guarantees exactly, and `chmod()` likely remains a local-path-native boundary rather than a `WP_Filesystem` credential flow.
- `WPORG-28R-F4` Event Plan import staged-file lifecycle (`11` rows): `includes/admin/data-tools/actions-event-plan-import.php:152,157,311,462` plus `includes/services/event-plan-import/event-plan-import-engine.php:291,942,949,965,1466,1496,1506`. These rows own administrator-selected CSV staging, preview rollback, preview-report downloads, preview CSV generation, and generated-artifact cleanup. Both files differ from live overall, but the target handlers and engine helpers remain semantically equivalent at the audited boundaries. Existing focused evidence already covers this subsystem through `tests/event-plan-import-upload-api-remediation.php`, `tests/event-plan-import-rows-payload-output-remediation.php`, `tests/event-plan-import-inline-js-remediation.php`, and `tests/decoded-json-validation.php`. Operational requirements: deterministic storage keys, local temporary-file ownership, safe early-return handle closure, streamed downloads, and rollback cleanup. Preliminary disposition: no generic `WP_Filesystem` swap is justified without re-proving the import rollback and preview/download contracts.
- `WPORG-28R-F5` slow-request logger local log rotation (`3` rows): `includes/core/slow-request-logger.php:399,401,413` in `vms_slow_request_logger_rotate_file()` and `vms_slow_request_logger_write_entry()` handle `unlink()`, `rename()`, and `is_writable()` for the local diagnostic log. The shared mirror and live files no longer hash-match overall because the mirror-only `WPORG-28R-F1` `wp_parse_url()` substitution remains intentionally unreconciled in live, but the mapped rotation lines and helper intent stay semantically equivalent. Existing focused evidence now covers this boundary through `tests/slow-request-logger-request-input-characterization.php`, `tests/slow-request-logger-url-helper-remediation.php`, `tests/slow-request-logger-rotation-boundary-remediation.php`, and `tests/event-plan-performance-request-id-remediation.php`. Operational requirements: atomic rotation, local-path guarantees, and no filesystem-credential prompts during request shutdown. Preliminary disposition: `WP_Filesystem::move()` is not an obviously safe drop-in for the atomic rename boundary, so this child must characterize whether only the helper substitutions are appropriate or whether the current local rotation remains justified native behavior.
- `WPORG-28R-F6` runtime guard bounded stream reader (`4` rows): `includes/runtime-guards.php:294,312,314,333` in `vms_read_limited_stream()` use `fopen()`, `fread()`, and `fclose()` to consume a bounded request stream; the current caller is `includes/integrations/ticketing-rules-v2.php:3307` with `php://input`. The mirror `includes/runtime-guards.php` differs from live overall and the live `../../vms/includes/runtime-guards.php` file has no semantically equivalent helper, so this is an intentional mirror/live divergence boundary instead of a shared exact-match file. Existing focused evidence already covers the surrounding request and guard behavior through `tests/request-input-sanitization.php`, `tests/upload-validation-guards.php`, `tests/runtime-stub-guards.php`, and `tests/public-calendar-user-agent-view-characterization.php`. Operational requirements: bounded streaming, byte-cap enforcement, and native local stream support. Preliminary disposition: `WP_Filesystem` is technically inappropriate for `php://input`, so this child should remain isolated and last.

### Ordered Work Sequence

1. `WPORG-28R-F1`: replace the three pure helper-substitution rows (`wp_parse_url()` plus `wp_strip_all_tags()`).
2. `WPORG-28R-F2`: characterize the six admin CSV/output-stream and `set_time_limit()` rows.
3. `WPORG-28R-F3`: reconcile the twelve shared private-file, proof-image, and safety-download rows.
4. `WPORG-28R-F4`: reconcile the eleven Event Plan import staged-file rows.
5. `WPORG-28R-F5`: reconcile the three slow-request logger local-rotation rows.
6. `WPORG-28R-F6`: reconcile the four runtime-guard bounded-stream rows.
7. Rebuild the public package and rerun packaged strict-json Plugin Check after the runtime children land.
8. Reclassify the fresh residual baseline before any final artifact preparation or reviewer communication.
9. Keep `Review-2 Name/Slug Closeout` and `Review-13 Final Actions` blocked until the fresh post-remediation package proves `WPORG-28R` is no longer blocked.

### What Changed

- Updated `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` only.
- Recorded the exact `39`-row ownership map, file inventory, live-parity boundaries, existing focused-evidence inventory, and the ordered `WPORG-28R-F1` through `WPORG-28R-F6` runtime roadmap.
- Updated the current `WPORG-28` and `WPORG-28R` documentation so the parent now points at the concrete `F1` first child instead of the earlier generic `WPORG-28R-F` placeholder.

### Non-Actions

- No runtime, test, build, package, metadata, asset, live-tree, or production file changed in this child.
- No upload, deployment, submission, reviewer reply, push, tag, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-F1 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-F1`
- Starting mirror HEAD: `53a6b1b7571b865478f111f4680eec5f0a4c35f3` (`Map packaged alternative function findings`)
- Starting parent: `405410e2713e80f44657e19a2aae0a9ce807d6d2`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Pre-edit packaged target rows: `WordPress.WP.AlternativeFunctions.parse_url_parse_url` at `includes/core/slow-request-logger.php:75:106` and `:76:115`, plus `WordPress.WP.AlternativeFunctions.strip_tags_strip_tags` at `includes/integrations/ticketing.php:55:19`
- Target runtime characterization: `vms_slow_request_logger_parse_request_uri()` reads a trimmed scalar `REQUEST_URI` through `vms_request_server_value()` and consumes only `PHP_URL_PATH` plus `PHP_URL_QUERY`, while `vms_ticketing_ajax_attach_noise()` consumes owned AJAX output-buffer text only and exposes the cleaned result only to `manage_options` users in `_vms_ajax_noise`.
- Helper comparison: installed WordPress `7.0.1` `wp_parse_url()` matched native `parse_url()` for the actual path/query component reads across HTTPS, port, query+fragment, protocol-relative, root-relative, IPv6, user-info, malformed, and empty representative inputs with no warnings in the exercised cases; installed `wp_strip_all_tags()` matched `trim(strip_tags())` for plain text, simple and nested tags, entities, malformed tags, and ticket-title-shaped input, while intentionally removing script/style contents and preserving visible line breaks only with `$remove_breaks=false`.
- Per-occurrence decision: all three packaged helper rows closed under `Outcome A`, using direct `wp_parse_url()` replacement for the two request-component reads and `wp_strip_all_tags((string) $noise, false)` for the admin-only AJAX noise cleaner.
- Runtime changes: `includes/core/slow-request-logger.php` now calls `wp_parse_url()` directly for `PHP_URL_PATH` and `PHP_URL_QUERY`; `includes/integrations/ticketing.php` now cleans owned AJAX buffer noise with `wp_strip_all_tags((string) $noise, false)`.
- Focused tests added: `tests/slow-request-logger-url-helper-remediation.php` and `tests/ticketing-text-helper-remediation.php`
- Focused and surrounding reruns passed: `php tests/slow-request-logger-url-helper-remediation.php`, `php tests/ticketing-text-helper-remediation.php`, `php tests/slow-request-logger-request-input-characterization.php`, `php tests/ticketing-output-buffer-lifecycle-characterization.php`, and `php tests/ticketing-v2-ajax-output-buffer-ownership.php`
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Live reference outcome: both read-only live helper files existed and matched the pre-edit mirror hashes (`../../vms/includes/core/slow-request-logger.php` `933c174f4377dc3534b1c1d2efbafb049159fcf4b8f4c20d277529a5a656f098`; `../../vms/includes/integrations/ticketing.php` `f3b3d4914bb80b0fb56c3e1fc320945656fee036d2df2a1a484f276a514b36da`). The live files remained unchanged throughout this child while the mirror changed only in the authorized helper lines.
- Post-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf1-post.Yet6nj/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rf1-post.Yet6nj/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `f83a7b029ae18d007ae77467187dbcd50d24904eeab6789afaad7250d1a21966`
- Post-edit packaged Plugin Check result: exit `0`, `300` errors, `1708` warnings, `2008` total findings, `29` unique rule codes, `parse_url_parse_url=0`, and `strip_tags_strip_tags=0`
- Exact pre/post code-count delta: only `WordPress.WP.AlternativeFunctions.parse_url_parse_url` changed `2 -> 0` and `WordPress.WP.AlternativeFunctions.strip_tags_strip_tags` changed `1 -> 0`; no unrelated packaged count increased and no new rule code appeared.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=36`, and `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child removes the pure helper-substitution rows only. `WPORG-28R` remains blocked by the surviving `UNMAPPED` and `SUBMISSION_BLOCKER` families, and the next ordered child is `WPORG-28R-F2`.

### What Changed

- Replaced the two native request-component `parse_url()` calls in `includes/core/slow-request-logger.php` with direct `wp_parse_url()` calls.
- Replaced the admin-only AJAX noise `trim(strip_tags())` boundary in `includes/integrations/ticketing.php` with `wp_strip_all_tags((string) $noise, false)` so visible line breaks remain intact while script/style contents are dropped.
- Added `tests/slow-request-logger-url-helper-remediation.php` to prove the exact `wp_parse_url()` ownership, native-helper removal, representative component equivalence, and safe malformed-input fallback behavior.
- Added `tests/ticketing-text-helper-remediation.php` to prove the exact `wp_strip_all_tags()` ownership, native-helper removal, preserved plain-text and line-break behavior, and intentional script/style-content removal for admin-only AJAX noise.
- Updated `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` so the current `WPORG-28` and `WPORG-28R` state now reflects the `300 / 1708 / 2008 / 29` packaged baseline, `UNMAPPED=36`, and the `WPORG-28R-F2` next-child pointer.

### Non-Actions

- The live `../../vms/includes/core/slow-request-logger.php` and `../../vms/includes/integrations/ticketing.php` files remained read-only and unchanged; no mirror/live copy or convergence work was attempted.
- No other runtime file, build script, metadata file, asset, package manifest, add-on, migration, or unrelated test file changed in this child.
- No upload, deployment, submission, reviewer reply, push, tag, activation, deactivation, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-F2 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-F2`
- Starting mirror HEAD: `8d7bdb6a00bb7dc17abc98959db43a35c5fa2e4c` (`Use WordPress URL and text helpers`)
- Starting parent: `53a6b1b7571b865478f111f4680eec5f0a4c35f3`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Exact committed ownership and fresh packaged repro matched on all six F2 rows: `/privateincludes/admin/settings-page.php:569` `Squiz.PHP.DiscouragedFunctions.Discouraged`, `/privateincludes/admin/settings-page.php:595` `WordPress.WP.AlternativeFunctions.file_system_operations_fclose`, `/privateincludes/admin/square-sync-protection.php:121` `WordPress.WP.AlternativeFunctions.file_system_operations_fclose`, `/privateincludes/modules/admissions/admin-ui.php:166` `WordPress.WP.AlternativeFunctions.file_system_operations_fclose`, and `/privateincludes/modules/admissions/pass-claims.php:1493,1681` `WordPress.WP.AlternativeFunctions.file_system_operations_fclose`.
- Pre-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf2-pre.1QgUFn/build --force`
- Pre-edit package result: `/tmp/wporg-28rf2-pre.1QgUFn/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `ab1f3a9dab0c9a28ad10777c608e90b9dd74b9e7cb93484f26116333cc9ecfec`
- Pre-edit packaged Plugin Check result: exit `0`, `300` errors, `1708` warnings, `2008` total findings, `29` unique rule codes
- Export-boundary characterization: every F2 occurrence belongs to an administrator-only `admin_post_*` CSV export path with nonce verification, direct `php://output` streaming, no local filesystem path, and request termination immediately after export. `settings-page.php`, `square-sync-protection.php`, and `pass-claims.php` differ from live overall but their target export handlers remain semantically equivalent; `includes/modules/admissions/admin-ui.php` hash-matches live exactly.
- `WP_Filesystem` suitability: installed WordPress `7.0.1` still exposes `WP_Filesystem_Base::put_contents( $file, $contents, $mode = false )` and credential-driven setup in `wp-admin/includes/file.php`; it works on filesystem paths and buffered string contents rather than on the existing HTTP response stream, so it is not a credible replacement for `php://output` without changing buffering, headers, or credential behavior.
- Installed-rule characterization: Plugin Check `2.0.0` locally overrides `Squiz.PHP.DiscouragedFunctions` in `phpcs-rulesets/plugin-check.ruleset.xml` to add `set_time_limit`, while WordPressCS `3.3.0` `AlternativeFunctionsSniff` explicitly allowlists `php://output` for `fopen()` but cannot infer that already-opened stream type when later seeing `fclose()`, leaving the close calls to report under the generic filesystem alternative-function rule.
- Per-occurrence decision: all six rows closed under bounded native-retention documentation. The `set_time_limit()` call remains only on the administrator-only ticketing stock CSV export with a line-specific suppression explaining the bounded transient-backed export and the lack of a WordPress-native replacement. The five `fclose('php://output')` rows remain with line-specific suppressions explaining that they close bounded administrator HTTP response streams rather than plugin-owned files.
- Focused tests added and rerun: `php tests/admin-export-stream-boundary-remediation.php`
- Surrounding reruns passed: `php tests/settings-integrity-scan-output-remediation.php`, `php tests/settings-default-venue-alert-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/pass-claims-public-shell-output-remediation.php`, `php tests/pass-claims-public-status-output-remediation.php`, `php tests/pass-claims-public-claimed-card-output-remediation.php`, `php tests/pass-claims-public-success-output-remediation.php`, and `php tests/pass-claims-public-form-output-remediation.php`
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Post-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf2-post.Yh296i/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rf2-post.Yh296i/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `4ed0049374a1a41986c61c0d770e5a0ca795398293eb84b6772f536392c6c540`
- Post-edit packaged Plugin Check result: exit `0`, `295` errors, `1707` warnings, `2002` total findings, `28` unique rule codes, `Squiz.PHP.DiscouragedFunctions.Discouraged=0`, and `WordPress.WP.AlternativeFunctions.file_system_operations_fclose=7`
- Exact pre/post code-count delta: only `Squiz.PHP.DiscouragedFunctions.Discouraged` changed `1 -> 0` and `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` changed `12 -> 7`; no unrelated packaged count increased and no new rule code appeared.
- Exact removed packaged rows: the pre/post strict-json comparison removed only the six F2-owned rows listed above and added no new rows, proving that no F3-F6 occurrence moved.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=30`, and `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child removes the admin CSV/output-stream and timeout residual only. `WPORG-28R` remains blocked by the surviving `UNMAPPED` and `SUBMISSION_BLOCKER` families, and the next ordered child is `WPORG-28R-F3`.

### What Changed

- Added one line-specific `Squiz.PHP.DiscouragedFunctions.Discouraged` suppression to `includes/admin/settings-page.php` on the bounded administrator ticketing stock CSV `set_time_limit()` call.
- Added five line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` suppressions to the `php://output` close calls in `includes/admin/settings-page.php`, `includes/admin/square-sync-protection.php`, `includes/modules/admissions/admin-ui.php`, and `includes/modules/admissions/pass-claims.php`.
- Added `tests/admin-export-stream-boundary-remediation.php` to prove the exact six suppression locations, the admin-post/capability/nonce boundaries, the direct `php://output` streaming contract, the preserved CSV header strings, and the absence of broader fopen/file-level suppressions or `WP_Filesystem` rewrites.
- Updated `docs/WPORG_PREREVIEW_REMEDIATION.md` and `docs/wporg-remediation-ledger.md` so the current `WPORG-28` and `WPORG-28R` state now reflects the `295 / 1707 / 2002 / 28` packaged baseline, `UNMAPPED=30`, and the `WPORG-28R-F3` next-child pointer.

### Non-Actions

- The live `../../vms/includes/admin/settings-page.php`, `../../vms/includes/admin/square-sync-protection.php`, `../../vms/includes/modules/admissions/admin-ui.php`, and `../../vms/includes/modules/admissions/pass-claims.php` files remained read-only and unchanged; no mirror/live copy or convergence work was attempted.
- No CSV schema, row ordering, filenames, authorization, nonce action, download headers, disk buffering, build script, metadata file, asset, package manifest, add-on, migration, or unrelated test file changed in this child.
- No upload, deployment, submission, reviewer reply, push, tag, activation, deactivation, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-F3 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-F3`
- Starting mirror HEAD: `a58e9496ca5034edb38132ec9ae40fa615fe76ca` (`Document bounded admin export streams`)
- Starting parent: `8d7bdb6a00bb7dc17abc98959db43a35c5fa2e4c`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Disposable cleanup verification: `/tmp/wporg-28rf2-pre.1QgUFn` and `/tmp/wporg-28rf2-post.Yh296i` both resolved under `/tmp`, neither path was a symlink into the repository or live plugin tree, both were removed with targeted Python `shutil.rmtree()` cleanup, and both were then confirmed absent.
- Exact committed ownership and fresh packaged repro matched on all twelve F3 rows: `/privateincludes/core/private-files.php:402,594,599,614,637`, `/privateincludes/safety/private-files.php:263`, `/privateincludes/integrations/ticketing-verifications.php:798,835,884`, and `/privateincludes/helpers/image-normalization.php:24,86,90`.
- Rule-family inventory for this child: `WordPress.WP.AlternativeFunctions.file_system_operations_readfile` (`2`), `WordPress.WP.AlternativeFunctions.unlink_unlink` (`6`), `WordPress.WP.AlternativeFunctions.file_system_operations_chmod` (`2`), and `WordPress.WP.AlternativeFunctions.file_system_operations_is_writable` (`2`).
- Authorized runtime files: `includes/core/private-files.php`, `includes/safety/private-files.php`, `includes/integrations/ticketing-verifications.php`, and `includes/helpers/image-normalization.php`.
- Read-only live references: `includes/core/private-files.php` has no live counterpart; `includes/safety/private-files.php` and `includes/integrations/ticketing-verifications.php` differ from live overall but their mapped download/store/delete helpers remain semantically aligned; `includes/helpers/image-normalization.php` hash-matches live exactly.
- Pre-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf3-pre.zOUylj/build --force`
- Pre-edit package result: `/tmp/wporg-28rf3-pre.zOUylj/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `f63c67ebda748a1e4468cc4cf65d523871d67aaece39291a81e077c78ea6287d`
- Pre-edit packaged Plugin Check result: `295` errors, `1707` warnings, `2002` total findings, `28` unique rule codes, with the exact twelve F3 rows reproduced from the clean package.
- Private-download characterization: the `readfile()` rows sit only in `vms_private_files_stream_path()` and the fallback branch in `vms_safety_private_file_download_handler()`. Every current caller first resolves a brokered file payload, validates the local path against an allowed root, applies object-level or capability checks plus nonce verification, sets explicit download headers, and terminates immediately after streaming. No path is caller-supplied as a raw absolute file path, and no WordPress API provides a behaviorally equivalent streamed-response wrapper for these local private files.
- Proof-image characterization: the verification image path enters through validated uploads, normalizes only image proof variants to JPEG under the private `verifications` root, preserves `0640`, and deletes failed rollbacks only after root validation. Non-image proofs already route through the shared private-file broker and reuse the exact validated MIME map.
- Safety-download characterization: safety documents remain rooted in brokered private storage, use `current_user_can(vms_safety_view_capability())` plus `check_admin_referer()` before path lookup, and log downloads before streaming the response.
- WordPress API suitability: `wp_is_writable()` is a direct semantic replacement for the two directory writability checks and preserves Windows-specific handling via core; `wp_delete_file()` is a direct semantic replacement for the six bounded private-root cleanup deletions because the existing code already constrains each path to a validated plugin-controlled root before deletion. `WP_Filesystem` remains unsuitable for the two streaming rows and the two `0640` permission rows because it would introduce credential-driven or buffered-file semantics that do not match the current local-path operations.
- Per-occurrence outcome: the six `unlink()` rows normalized to `wp_delete_file()`, the two `is_writable()` rows normalized to `wp_is_writable()`, and the two `readfile()` plus two `chmod()` rows stayed as justified bounded native operations with line-specific suppressions only.
- Focused tests added and rerun: `php tests/private-file-operations-boundary-remediation.php`
- Existing directly relevant reruns passed: `php tests/private-file-upload-api-remediation.php`, `php tests/verification-proof-normalization.php`, `php tests/upload-validation-guards.php`, and `php tests/vendor-tax-profile-strict-post-remediation.php`
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Post-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf3-post.j1oKau/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rf3-post.j1oKau/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `14c69a01959b04fdec326a9f2e4e0395cc763ed2b2087763ecb41f105616c838`
- Post-edit packaged Plugin Check result: exit `0`, `283` errors, `1707` warnings, `1990` total findings, and `28` unique rule codes
- Exact pre/post code-count delta: only `WordPress.WP.AlternativeFunctions.unlink_unlink` changed `9 -> 3`, `WordPress.WP.AlternativeFunctions.file_system_operations_is_writable` changed `3 -> 1`, `WordPress.WP.AlternativeFunctions.file_system_operations_readfile` changed `3 -> 1`, and `WordPress.WP.AlternativeFunctions.file_system_operations_chmod` changed `3 -> 1`; no unrelated packaged count increased and no new rule code appeared.
- Exact removed packaged rows: the pre/post strict-json comparison removed only the twelve F3-owned rows listed above and added no new rows, proving that no F4-F6 occurrence moved.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=18`, and `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child removes the shared private-file, proof-image, and safety-download residual only. `WPORG-28R` remains blocked by the surviving `UNMAPPED` and `SUBMISSION_BLOCKER` families, and the next ordered child is `WPORG-28R-F4`.

### What Changed

- Replaced the six bounded private-root cleanup `unlink()` calls with `wp_delete_file()` in `includes/core/private-files.php`, `includes/integrations/ticketing-verifications.php`, and `includes/helpers/image-normalization.php`.
- Replaced the two bounded directory writability checks with `wp_is_writable()` in `includes/integrations/ticketing-verifications.php` and `includes/helpers/image-normalization.php`.
- Added two line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_readfile` suppressions to the shared private stream helper and the safety download fallback, documenting that these rows stream already-authorized validated local files directly to the response with no equivalent WordPress streamed-download abstraction.
- Added two line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_chmod` suppressions to the private broker and image-normalization paths, documenting the existing `0640` requirement on validated plugin-controlled files and the mismatch between those local permission operations and `WP_Filesystem` credential flows.
- Added `tests/private-file-operations-boundary-remediation.php` to prove the exact four suppressions, the six `wp_delete_file()` replacements, the two `wp_is_writable()` replacements, the retained authorization/nonces/path guards on every download handler, and the absence of broader filesystem suppressions.
- Updated `tests/private-file-upload-api-remediation.php` and `tests/verification-proof-normalization.php` so the focused pure-PHP regression harnesses understand the core `wp_delete_file()` and `wp_is_writable()` wrappers and no longer depend on a stale slice-specific diff guard.

### Non-Actions

- No live file changed. `includes/core/private-files.php` still has no live counterpart, and the live `../../vms/includes/safety/private-files.php`, `../../vms/includes/integrations/ticketing-verifications.php`, and `../../vms/includes/helpers/image-normalization.php` files remained read-only throughout this child.
- No Event Plan import staged-file code, slow-request logger rotation code, runtime-guard stream code, admin CSV export code, plugin metadata, build scripts, manifests, assets, database schema, or unrelated tests changed in this child.
- No upload, deployment, submission, reviewer reply, push, tag, activation, deactivation, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-F4 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-F4`
- Starting mirror HEAD: `289795c87c5b116e9fa65a4a1320eec9ab108014` (`Normalize and document private-file operations`)
- Starting parent: `a58e9496ca5034edb38132ec9ae40fa615fe76ca`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Exact committed ownership and fresh packaged repro matched on all eleven F4 rows: `/privateincludes/admin/data-tools/actions-event-plan-import.php:152,157,311,462` plus `/privateincludes/services/event-plan-import/event-plan-import-engine.php:291,942,949,965,1466,1496,1506`.
- Rule-family inventory for this child: `WordPress.WP.AlternativeFunctions.unlink_unlink` (`2`), `WordPress.WP.AlternativeFunctions.file_system_operations_chmod` (`1`), `WordPress.WP.AlternativeFunctions.file_system_operations_readfile` (`1`), `WordPress.WP.AlternativeFunctions.file_system_operations_fopen` (`2`), and `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` (`5`).
- Authorized runtime files: `includes/admin/data-tools/actions-event-plan-import.php` and `includes/services/event-plan-import/event-plan-import-engine.php`.
- Read-only live references: both authorized files differ from live overall, but the audited upload, staging, preview, report-download, and cleanup boundaries remained semantically aligned; no live file changed in this child.
- Pre-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf4-pre.y8yuJ4/build --force`
- Pre-edit package result: `/tmp/wporg-28rf4-pre.y8yuJ4/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `6308025b11dabc4d703eafe1375e83ccf1ac53ffb0a6cd9b3a6ce3efdefb081e`
- Pre-edit packaged Plugin Check result: exit `0`, `283` errors, `1707` warnings, `1990` total findings, `28` unique rule codes, with the exact eleven F4 rows reproduced from the clean package.
- Import upload and staging characterization: the preview upload and preview-report download handlers stay behind `manage_options` plus dedicated nonces, the CSV upload still routes through the shared upload validators and bounded MIME/size guards, staged artifacts stay rooted under the private `event-plan-imports` bucket with deterministic `<token>-source.csv`, `<token>-rows.json`, `<token>-preview-report.csv`, and `<token>-before-snapshot.json` names, and the existing safe-path guard still constrains every resolved file path to the expected private roots before cleanup or download.
- Stream and parser characterization: the preview parser still reads the validated staged CSV incrementally with a local `'rb'` handle, the preview report writer still writes the generated report to the validated staged path with a local `'wb'` handle, the sample CSV still streams directly to `php://output`, and the rows/snapshot JSON readers still retain the existing `5 * 1024 * 1024` file-size ceiling plus `10000` decoded-row cap.
- WordPress API suitability: `wp_delete_file()` is a direct semantic replacement for the preview-upload rollback deletion and the stored-file cleanup deletion because both paths are already validated against the plugin-controlled private import roots before deletion. `WP_Filesystem` remains unsuitable for the retained `chmod()`, `readfile()`, `fopen()`, and `fclose()` boundaries because those operations depend on deterministic local staged-file ownership, direct HTTP response streaming, or exact native handle lifecycle rather than credential-mediated buffered filesystem writes.
- Per-occurrence outcome: the two `unlink()` rows normalized to `wp_delete_file()`, while the remaining `chmod()`, `readfile()`, `fopen()`, and `fclose()` rows stayed as justified bounded native operations with line-specific suppressions only.
- Focused tests added and rerun: `php tests/event-plan-import-file-operations-boundary-remediation.php`
- Existing directly relevant reruns passed: `php tests/event-plan-import-upload-api-remediation.php`, `php tests/event-plan-import-rows-payload-output-remediation.php`, `php tests/event-plan-import-inline-js-remediation.php`, `php tests/decoded-json-validation.php`, and `php tests/upload-validation-guards.php`
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Post-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf4-post.LmQZEH/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rf4-post.LmQZEH/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `d754686efc3e28b625fe97a187dc820393e0470463f8f9b4f3c4bbfc445776f2`
- Post-edit packaged Plugin Check result: exit `0`, `272` errors, `1707` warnings, `1979` total findings, `26` unique rule codes
- Exact pre/post code-count delta: `WordPress.WP.AlternativeFunctions.file_system_operations_chmod` changed `1 -> 0`, `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` changed `7 -> 2`, `WordPress.WP.AlternativeFunctions.file_system_operations_fopen` changed `3 -> 1`, `WordPress.WP.AlternativeFunctions.file_system_operations_readfile` changed `1 -> 0`, and `WordPress.WP.AlternativeFunctions.unlink_unlink` changed `3 -> 1`; no unrelated packaged code count increased and no new rule code appeared.
- Exact removed packaged rows: the pre/post strict-json comparison removed the eleven F4-owned rows, and the only added row was the unchanged `WordPress.DB.SlowDBQuery.slow_db_query_meta_query` occurrence in the authorized engine file after its line number shifted from `2112` to `2116`; the rule count stayed unchanged, no F5/F6 occurrence moved, and the surviving F5/F6 packaged rows remained byte-for-byte the same findings at the same files and codes.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=7`, and `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child removes the Event Plan import staged-file residual only. `WPORG-28R` remains blocked by the surviving `UNMAPPED` and `SUBMISSION_BLOCKER` families, and the next ordered child is `WPORG-28R-F5`.

### What Changed

- Replaced the preview-upload rollback `@unlink()` call with `wp_delete_file()` in `includes/admin/data-tools/actions-event-plan-import.php`.
- Replaced the staged-file cleanup `@unlink()` call with `wp_delete_file()` in `includes/services/event-plan-import/event-plan-import-engine.php`.
- Added one line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_chmod` suppression to the validated private staging CSV permission boundary, one line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_readfile` suppression to the nonce-gated preview-report download boundary, and one line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` suppression to the bounded sample CSV `php://output` stream close.
- Refactored the preview CSV reader and preview report writer to single `try`/`finally` handle-lifecycle boundaries with line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_fopen` and `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` suppressions documenting the bounded staged-file stream contract.
- Added `tests/event-plan-import-file-operations-boundary-remediation.php` to prove the exact suppression counts, the two `wp_delete_file()` replacements, the preserved capability/nonce/path guards, the bounded CSV/report stream lifecycle, and the absence of broader `WP_Filesystem` rewrites.
- Updated `tests/event-plan-import-upload-api-remediation.php` so the focused pure-PHP harness understands the `wp_delete_file()` rollback path and asserts the retained private-root boundary instead of the removed direct `@unlink()` calls.

### Non-Actions

- The live `../../vms/includes/admin/data-tools/actions-event-plan-import.php` and `../../vms/includes/services/event-plan-import/event-plan-import-engine.php` files remained read-only and unchanged; no mirror/live copy or convergence work was attempted.
- No slow-request logger rotation file, runtime-guard stream file, private-file download file, admin export file, build script, package manifest, release metadata file, asset, add-on, migration, production tree, or unrelated test file changed in this child.
- No upload, deployment, submission, reviewer reply, push, tag, activation, deactivation, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-F5 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-F5`
- Starting mirror HEAD: `34d764967a992c7c37326a3e742961a4ac37ea74` (`Normalize and document Event Plan import staging`)
- Starting parent: `289795c87c5b116e9fa65a4a1320eec9ab108014`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Exact committed ownership and fresh packaged repro matched on all three F5 rows: `/privateincludes/core/slow-request-logger.php:399,401,413`.
- Rule-family inventory for this child: `WordPress.WP.AlternativeFunctions.unlink_unlink` (`1`), `WordPress.WP.AlternativeFunctions.rename_rename` (`1`), and `WordPress.WP.AlternativeFunctions.file_system_operations_is_writable` (`1`).
- Authorized runtime file: `includes/core/slow-request-logger.php`
- Read-only live reference: the mirror `includes/core/slow-request-logger.php` and live `../../vms/includes/core/slow-request-logger.php` no longer hash-match overall because the mirror-only `WPORG-28R-F1` `wp_parse_url()` substitution remains intentionally unreconciled in live, but the mapped rotation lines and helper intent stayed semantically equivalent at the audited boundary and the live file remained unchanged.
- Pre-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf5-pre.pNm0CC/build --force`
- Pre-edit package result: `/tmp/wporg-28rf5-pre.pNm0CC/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `765b3a64bde549e0af461c32645a3f70f3f45a2fe36e1bd321a0d34d61d2b82c`
- Pre-edit packaged Plugin Check result: exit `0`, `272` errors, `1707` warnings, and `1979` total findings with `26` unique rule codes, with the exact three F5-owned rows reproduced from the clean package and the four F6 rows unchanged.
- Logger lifecycle characterization: the logger still bootstraps on load, captures request-start state only for matched slow-request scopes, records threshold-triggered shutdown payloads as newline-delimited JSON, appends with `FILE_APPEND | LOCK_EX`, rotates only when the active log reaches `5 * 1024 * 1024` bytes, and retains exactly one same-directory generation at `<path>.1`.
- Storage and privacy boundary: the log path still resolves from `VMS_SLOW_REQUEST_LOGGER_PATH` when defined, otherwise from `WP_CONTENT_DIR . '/vms-slow-request.log'` or the fallback `dirname(__DIR__, 3) . '/vms-slow-request.log'`; no request value contributes to the path, no log-location change was made in this child, normalized URIs still redact secrets, source IPs remain hashed, and the logged payload shape remains unchanged. The default locations may be web-accessible on some deployments and use a predictable filename, but this child did not move the log because no bounded fix within the authorized file proved necessary beyond the mapped rotation operations themselves.
- WordPress API suitability: `wp_is_writable()` is a direct semantic replacement for the directory writability gate and preserves WordPress core's Windows-specific behavior; `wp_delete_file_from_directory()` is a direct semantic replacement for the retained-generation cleanup while adding an explicit same-directory boundary; `WP_Filesystem::move()` remains unsuitable for the active-log promotion because shutdown-path rotation must avoid credential prompts and the direct implementation may fall back to copy/delete semantics rather than a guaranteed same-directory native rename.
- Per-occurrence outcome: the retained-generation `unlink()` row closed under `Outcome A` by normalizing to `wp_delete_file_from_directory()`, the directory `is_writable()` row closed under `Outcome A` by normalizing to `wp_is_writable()`, and the active-log `rename()` row closed under `Outcome B` with a single line-specific suppression because the bounded same-directory native rename remains the least-weak local rotation operation available in this shutdown path.
- Focused tests added and rerun: `php tests/slow-request-logger-rotation-boundary-remediation.php`
- Existing directly relevant reruns passed: `php tests/slow-request-logger-request-input-characterization.php`, `php tests/slow-request-logger-url-helper-remediation.php`, and `php tests/event-plan-performance-request-id-remediation.php`
- Regression gates passed: `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php`
- Post-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf5-post.9MqqQd/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rf5-post.9MqqQd/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `f7810fad77f40b422f6f67aa11a52c69990b7a272f2435e9f9b55aed5bb977e3`
- Post-edit packaged Plugin Check result: exit `0`, `269` errors, `1707` warnings, and `1976` total findings with `23` unique rule codes
- Exact pre/post code-count delta: only `WordPress.WP.AlternativeFunctions.file_system_operations_is_writable` changed `1 -> 0`, `WordPress.WP.AlternativeFunctions.rename_rename` changed `1 -> 0`, and `WordPress.WP.AlternativeFunctions.unlink_unlink` changed `1 -> 0`; no unrelated packaged code count increased and no new rule code appeared.
- Exact removed packaged rows: the pre/post strict-json comparison removed only the three F5-owned rows, left the four F6 packaged rows byte-for-byte unchanged, and introduced no new strict-json row.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=4`, and `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child removes the slow-request logger local-rotation residual only. `WPORG-28R` remains blocked by the surviving `UNMAPPED` and `SUBMISSION_BLOCKER` families, and the next ordered child is `WPORG-28R-F6`.

### What Changed

- Replaced the retained-generation cleanup `@unlink()` call with `wp_delete_file_from_directory($rotated, dirname($path))` in `includes/core/slow-request-logger.php`.
- Replaced the logger directory writability check with `wp_is_writable()` in `includes/core/slow-request-logger.php`.
- Added one line-specific `WordPress.WP.AlternativeFunctions.rename_rename` suppression to the same-directory active-log promotion, documenting why native `rename()` remains the least-weak bounded local rotation operation here.
- Added `tests/slow-request-logger-rotation-boundary-remediation.php` to prove the exact suppression count, the `wp_delete_file_from_directory()` and `wp_is_writable()` replacements, the preserved single-generation rotation lifecycle, the bounded retained-generation delete target, and the no-`WP_Filesystem` decision.
- Updated `tests/slow-request-logger-request-input-characterization.php` so the pure-PHP logger harness understands `wp_is_writable()` and `wp_delete_file_from_directory()`.

### Non-Actions

- The live `../../vms/includes/core/slow-request-logger.php` file remained read-only and unchanged; no mirror/live copy or convergence work was attempted.
- No runtime-guard stream file, Event Plan import file, private-file download file, admin export file, build script, package manifest, release metadata file, asset, add-on, migration, production tree, or unrelated test file changed in this child.
- No log-location move, logged-field change, threshold change, privacy-redaction change, upload, deployment, submission, reviewer reply, push, tag, activation, deactivation, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-F6 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-F6`
- Starting mirror HEAD: `a2ab76b254399dc0fe2cbc99cbc4605885bc70cd` (`Normalize and document slow-request log rotation`)
- Starting parent: `34d764967a992c7c37326a3e742961a4ac37ea74`
- Baseline verification: `./scripts/codex-preflight.sh`, `git rev-parse HEAD`, `git log -1 --format='%s'`, `git rev-parse HEAD^`, `git status --short`, `git diff --check`, `git diff --cached --check`, `git diff --cached --name-only`, and `git stash list` all matched the required clean starting state, including protected stash `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`.
- Exact F6 ownership was confirmed unchanged from the committed F5 post-check: `/privateincludes/runtime-guards.php:294:14` `WordPress.WP.AlternativeFunctions.file_system_operations_fopen`, `/privateincludes/runtime-guards.php:312:13` `WordPress.WP.AlternativeFunctions.file_system_operations_fread`, `/privateincludes/runtime-guards.php:314:5` `WordPress.WP.AlternativeFunctions.file_system_operations_fclose`, and `/privateincludes/runtime-guards.php:333:3` `WordPress.WP.AlternativeFunctions.file_system_operations_fclose`.
- Authorized runtime file: `includes/runtime-guards.php`
- Enclosing helper and lifecycle: all four rows belong to `vms_read_limited_stream()` in `includes/runtime-guards.php`, introduced by `39d8d7cd36f3c576415cf45d51770b40801df2ac` (`Validate decoded JSON payloads`) on `2026-07-11`; the current caller remains `vms_ticketing_v2_read_json_request_payload(int $max_bytes): array` in `includes/integrations/ticketing-rules-v2.php`, reached only from the frontend Woo/ticketing AJAX handlers `vms_ticketing_v2_ajax_atomic_add_to_cart()` and `vms_ticketing_v2_ajax_silent_add()`.
- Path provenance and trust boundary: the current stream URI is the hardcoded literal `php://input` in `vms_ticketing_v2_read_json_request_payload()`. No request-controlled, user-controlled, database-controlled, constant-controlled, or filter-controlled value contributes to the path, and the stream may legitimately exist outside the plugin directory because it is the PHP request-body input stream rather than a plugin-owned file path.
- Stream semantics: `@fopen($stream_uri, 'rb')` opens the request body in binary read mode, the loop computes `$remaining = ($max_bytes + 1) - strlen($data)` and reads `fread($handle, min(8192, $remaining))`, the helper bounds the capture to at most `max_bytes + 1` bytes so oversize detection can fail closed, open failure and read failure both return `array('ok' => false, 'data' => '', 'too_large' => false)`, `@fopen()` suppresses open warnings, `fread()` and both `fclose()` calls were previously unsuppressed, and the two `fclose()` rows close the same successfully opened stream on mutually exclusive read-failure versus successful-loop lifecycles.
- Existing focused evidence before editing already covered surrounding request and guard behavior through `tests/request-input-sanitization.php`, `tests/upload-validation-guards.php`, `tests/runtime-stub-guards.php`, `tests/public-calendar-user-agent-view-characterization.php`, and the event-plan / release harnesses; this child added a dedicated stream-boundary characterization harness for the exact helper and call contract.
- WordPress helper suitability: installed WordPress `7.0.1` `WP_Filesystem` APIs still operate on filesystem paths and buffered contents, may require transport or credential setup, and do not provide a behaviorally equivalent bounded `php://input` streaming primitive. WordPressCS `3.3.0` already allowlists literal local streams like `php://input` for direct `fopen()`, but the current helper passes the literal through `$stream_uri`, so the sniff cannot infer the same bounded local-stream guarantee. A whole-body helper such as `file_get_contents()` or `WP_Filesystem_Direct::get_contents()` would weaken the current bounded-read, warning, and bootstrap behavior.
- Per-occurrence decision: all four rows close under `Outcome B — retain native operation with narrow suppression` because the native bounded request-body stream lifecycle is materially required and the safer equivalent abstraction is not available.
- Pre-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf6-pre.s9LAzG/build --force`
- Pre-edit package result: `/tmp/wporg-28rf6-pre.s9LAzG/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `fe421224c2dfc9bb229a507e0241b3da5669f98b5e26f8634c4a295dba5efcc6`
- Pre-edit packaged root and build audit: extracted package root `/tmp/wporg-28rf6-pre.s9LAzG/extracted/backstage-venue-manager`; build report `PASS`; exclusions and package-root checks passed.
- Pre-edit packaged Plugin Check command: `php -d error_reporting=0 -d display_errors=0 "$(which wp)" --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28rf6-pre.s9LAzG/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Packaged Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`, PHPCS `3.13.5`, WordPressCS `3.3.0`, and VIPWPCS `3.0.1`; the Homebrew `wp` phar's PHP `8.5` deprecation noise was normalized away by invoking `wp` through `php -d error_reporting=0 -d display_errors=0`.
- Pre-edit packaged Plugin Check result: exit `0`, `269` errors, `1707` warnings, `1976` total findings, and `23` unique rule codes, with exact F6 rule counts `file_system_operations_fopen=1`, `file_system_operations_fread=1`, and `file_system_operations_fclose=2`.
- Focused tests added and rerun: `php tests/runtime-guard-stream-boundary-remediation.php`
- Existing directly relevant reruns passed: `php tests/request-input-sanitization.php`, `php tests/upload-validation-guards.php`, `php tests/public-calendar-user-agent-view-characterization.php`, `php tests/runtime-stub-guards.php`, `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/event-plan-legacy-ticketing-integration-smoke.php`
- Post-edit package build: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rf6-post.vdryoe/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rf6-post.vdryoe/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `cdee817b7ecf130b0fd62b57d2a398ceeacc6c8e0dfa8952738330b6e7c8f15f`
- Post-edit packaged root and build audit: extracted package root `/tmp/wporg-28rf6-post.vdryoe/extracted/backstage-venue-manager`; build report `PASS`; exclusions and package-root checks passed.
- Post-edit packaged Plugin Check result: exit `0`, `265` errors, `1707` warnings, `1972` total findings, and `20` unique rule codes
- Exact pre/post code-count delta: only `WordPress.WP.AlternativeFunctions.file_system_operations_fopen` changed `1 -> 0`, `WordPress.WP.AlternativeFunctions.file_system_operations_fread` changed `1 -> 0`, and `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` changed `2 -> 0`; no unrelated packaged code count increased and no new rule code appeared.
- Exact removed packaged rows: the pre/post strict-json comparison removed only the four F6-owned rows and added no new strict-json row.
- Current parent classification totals after this child: `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1843`
- Readiness conclusion: this child removes the final packaged alternative-function residual only. `WPORG-28R` remains blocked by the still-blocking packaged DB / SQL, nonce / input, and date / logging families, and no further ordered `WPORG-28R-F*` child remains.

### What Changed

- Added one line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_fopen` suppression to `@fopen($stream_uri, 'rb')`, documenting that the current caller is the hardcoded bounded request-body stream `php://input` and that no behaviorally equivalent WordPress filesystem helper exists for this lifecycle.
- Added one line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_fread` suppression to the bounded `fread($handle, min(8192, $remaining))` loop, documenting the retained `max_bytes + 1` overflow-detection contract and the need to preserve incremental bounded reads rather than falling back to an unbounded whole-body helper.
- Added one line-specific `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` suppression to the read-failure cleanup path and one to the successful-loop cleanup path, documenting that both closes apply to the same bounded request-body stream on separate mutually exclusive lifecycles.
- Added `tests/runtime-guard-stream-boundary-remediation.php` to prove the exact suppression counts, retained `($max_bytes + 1)` bound, retained `min(8192, $remaining)` incremental-read contract, hardcoded `php://input` caller provenance, the two `65536` ticketing call sites, success / oversize / read-failure / open-failure behavior, lack of warning leakage, and the no-`WP_Filesystem` / no-`file_get_contents()` decision within the helper.

### Non-Actions

- The live `../../vms/includes/runtime-guards.php` file remained read-only and unchanged; no mirror/live copy or convergence work was attempted.
- No ticketing runtime, build script, package manifest, release metadata file, sibling live tree file, or unrelated remediation family changed in this child.
- No upload, deployment, submission, reviewer reply, push, tag, activation, deactivation, live replacement, migration, stash mutation, or WordPress.org action occurred.

## WPORG-28R-G0 Result

Date: 2026-07-26

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-G0`
- Starting mirror HEAD: `fc2976605aa45f3417c610e91ba8600900f95270` (`Document bounded runtime guard input stream`)
- Starting parent: `a2ab76b254399dc0fe2cbc99cbc4605885bc70cd`
- Scope: documentation and planning only; no runtime, test, builder, manifest, asset, or live-tree change was authorized
- Fresh build command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rg0.4SPYrI/build --force`
- Fresh build result: `/tmp/wporg-28rg0.4SPYrI/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `a492923143612bb9264deabbc5e97ac2f82027eddccbdfc0ccc757a6c5bf46d4`
- Fresh packaged Plugin Check result: exit `0`, `265` errors, `1707` warnings, `1972` total findings, and `20` unique rule codes, with `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1843`
- Current blocker-family totals: DB/SQL `1082`, nonce/input `694`, date/time `25`, and logging `42`
- Current packaged `WordPress.Security.EscapeOutput.OutputNotEscaped` remains `KNOWN_NONBLOCKING` and is not part of the `1843` blockers
- Why the `F` series did not reduce `SUBMISSION_BLOCKER`: `WPORG-28R-F0` through `WPORG-28R-F6` removed only the packaged alternative-function, `NEW_FINDING`, and `UNMAPPED` residuals; the still-blocking DB/SQL, nonce/input, date/time, and logging rows reproduced unchanged and therefore required a new `G` roadmap instead of another `F` child
- Complete child reconciliation now lives in `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`, with exact family sums `G1-G7 = 694`, `G8-G13 = 1082`, `G14-G15 = 25`, and `G16-G17 = 42`
- Exact next implementation child: `WPORG-28R-G1 — Admin module form boundaries` (`59` rows across `includes/modules/staff-tasks/admin-ui.php`, `includes/modules/email-followups/admin-ui.php`, `includes/admin/event-feedback.php`, and `includes/admin/season-dates.php`)

### Ordered Roadmap

1. `WPORG-28R-G1 — Admin module form boundaries` (`59`, input)
2. `WPORG-28R-G2 — Admin dashboard and secondary settings boundaries` (`140`, input)
3. `WPORG-28R-G3 — Event Plan editor and core request boundaries` (`164`, input)
4. `WPORG-28R-G4 — Ancillary CPT save boundaries` (`39`, input)
5. `WPORG-28R-G5 — Public, portal, and vendor-application request boundaries` (`103`, input)
6. `WPORG-28R-G6 — Ticketing, admissions, and availability request boundaries` (`147`, input)
7. `WPORG-28R-G7 — Shared request helpers, bootstrap, and compatibility reads` (`42`, input)
8. `WPORG-28R-G8 — Staffing and staff-task query boundaries` (`233`, DB/SQL)
9. `WPORG-28R-G9 — Admissions and claim-state query boundaries` (`255`, DB/SQL)
10. `WPORG-28R-G10 — Ticketing, availability, and integrity query boundaries` (`248`, DB/SQL)
11. `WPORG-28R-G11 — Vendor, portal, and payables query boundaries` (`103`, DB/SQL)
12. `WPORG-28R-G12 — Social queue, background processing, and private-file query boundaries` (`115`, DB/SQL)
13. `WPORG-28R-G13 — Reporting, schema, meta-query, and cache-policy long tail` (`128`, DB/SQL)
14. `WPORG-28R-G14 — Display formatting, identifiers, and admin date labels` (`11`, date/time)
15. `WPORG-28R-G15 — Business windows, scheduling, and persisted timestamp boundaries` (`14`, date/time)
16. `WPORG-28R-G16 — Operational failure and service logging` (`26`, logging)
17. `WPORG-28R-G17 — Development diagnostics, profiling, and trace logging` (`16`, logging)

### Current Parent State

- `WPORG-28R-G0` is terminal because the roadmap now reconciles all `1843` blocker rows with no unowned or multiply-owned occurrence.
- `WPORG-28R` remains blocked until every `G1` through `G17` child closes and a fresh packaged strict-json rerun proves `SUBMISSION_BLOCKER=0`.
- `WPORG-28`, `WPORG-28Q`, `Review-2 Name/Slug Closeout`, and `Review-13 Final Actions` all remain open or blocked exactly as reflected in the ledger.
- Slug reservation, corrected upload, and reviewer communication remain unauthorized while `WPORG-28R` stays blocked.

### Non-Actions

- No runtime, test, builder, release-metadata, or live-tree file changed in this slice.
- No upload, submission, reviewer reply, push, tag, deployment, activation, deactivation, migration, or production change occurred.

## WPORG-28R-G1 Result

Date: 2026-07-27

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-G1`
- Starting mirror HEAD: `9d919a487b829015b64252f858e7bf54f96b84f1` (`Decompose remaining prereview blocker families`)
- Starting parent: `fc2976605aa45f3417c610e91ba8600900f95270`
- Authorized runtime scope: `includes/modules/staff-tasks/admin-ui.php`, `includes/modules/email-followups/admin-ui.php`, `includes/admin/event-feedback.php`, and `includes/admin/season-dates.php`
- Authorized support scope used: `tests/strict-post-gate-remediation.php`, `tests/admin-request-method-wrapper-remediation.php`, `tests/administrator-explicit-notice-output-remediation.php`, `tests/authorization-boundary-hardening.php`, `docs/WPORG_PREREVIEW_REMEDIATION.md`, `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`, and `docs/wporg-remediation-ledger.md`
- Live-tree rule preserved: the sibling `../../vms/` tree remained read-only and unchanged throughout this child
- Pre-edit package command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rg1-pre.F3FPdk/build --force`
- Pre-edit package result: `/tmp/wporg-28rg1-pre.F3FPdk/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `f035b9e43f8069b9eb4a7f2bfee935754d4408f16f15a3ae8896d7c0706b4ed5`
- Pre-edit packaged Plugin Check command: `php -d error_reporting=0 -d display_errors=0 "$(which wp)" --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28rg1-pre.F3FPdk/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Packaged Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`, PHPCS `3.13.5`, WordPressCS `3.3.0`, and VIPWPCS `3.0.1`; the Homebrew `wp` phar's PHP `8.5` deprecation noise was normalized away by invoking `wp` through `php -d error_reporting=0 -d display_errors=0`
- Pre-edit packaged Plugin Check result: exit `0`, `265` errors, `1707` warnings, `1972` total findings, `20` unique rule codes, `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1843`
- Exact G1 ownership: `59` packaged rows (`WordPress.Security.NonceVerification.Recommended=38`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized=15`, `WordPress.Security.ValidatedSanitizedInput.MissingUnslash=6`) across the four authorized runtime files only

### Exact Packaged Ownership

- `includes/admin/event-feedback.php`: `40:17 Recommended`, `40:56 Recommended`, `125:52 Recommended`, `125:76 Recommended`, `125:76 InputNotSanitized`, `125:76 MissingUnslash`, `127:53 Recommended`, `134:35 Recommended`, `134:68 Recommended`, `165:15 Recommended`, `168:14 Recommended`, `169:37 Recommended`
- `includes/admin/season-dates.php`: `197:22 InputNotSanitized`, `197:22 MissingUnslash`, `538:21 Recommended`, `538:59 Recommended`, `544:29 Recommended`, `544:61 Recommended`, `608:18 Recommended`, `608:62 Recommended`, `609:18 Recommended`, `609:61 Recommended`
- `includes/modules/email-followups/admin-ui.php`: `46:17 Recommended`, `46:56 Recommended`, `70:16 Recommended`, `70:54 Recommended`, `92:19 Recommended`, `92:86 Recommended`, `96:17 Recommended`, `96:71 Recommended`, `318:16 Recommended`, `318:49 Recommended`, `335:35 Recommended`, `335:68 Recommended`, `340:22 Recommended`, `340:66 Recommended`, `461:24 Recommended`, `461:70 Recommended`, `554:102 InputNotSanitized`, `554:102 MissingUnslash`, `656:117 InputNotSanitized`
- `includes/modules/staff-tasks/admin-ui.php`: `658:41 Recommended`, `663:23 Recommended`, `792:77 InputNotSanitized`, `876:63 InputNotSanitized`, `878:34 InputNotSanitized`, `880:77 InputNotSanitized`, `883:83 InputNotSanitized`, `1066:18 Recommended`, `1066:57 Recommended`, `1144:63 InputNotSanitized`, `1146:34 InputNotSanitized`, `1151:77 InputNotSanitized`, `1602:21 InputNotSanitized`, `1602:21 MissingUnslash`, `1637:50 InputNotSanitized`, `1787:33 InputNotSanitized`, `1787:33 MissingUnslash`, `1940:59 MissingUnslash`

### Boundary Characterization

- Email Follow-Ups and Event Feedback use `ADMIN_PAGE` read-only query-string state for page gating, tabs, selected event-plan context, preview context, cache keys, and redirect notices. Those reads remain capability-gated by the existing admin screen registration and were moved behind narrow unslashing helpers instead of adding inappropriate GET nonces.
- Season Dates mixes `ADMIN_PAGE` display state (`page`, `venue_id`, `vms_notice`, `vms_error`) with existing state-changing administrator form handlers. The literal `REQUEST_METHOD === 'POST'` boundary must remain direct because `tests/strict-post-gate-remediation.php` and `tests/admin-request-method-wrapper-remediation.php` verify that exact POST gate contract.
- Staff Tasks mixes read-only return-page and admin-page context with state-changing assignment, one-off task, checklist-template, and settings payloads. The return context stays allowlisted to known admin destinations; mutation handlers keep their existing nonce/capability boundaries while their scalar enums, due-date text, checklist order, offset text, and digest-time values are now normalized at the request edge.
- Existing capability and nonce boundaries were preserved on every mutation path. No nonce was removed, no capability check was weakened, and no read-only admin filter or notice path was converted into a nonce-bearing state mutation.
- Raw-value exceptions stayed narrow: the Email Follow-Ups structured settings array still flows to `vms_email_followups_sanitize_settings()` under a line-specific `InputNotSanitized` ignore, and the Season Dates / Staff Tasks exact POST helpers keep direct `REQUEST_METHOD` comparisons under line-specific `InputNotSanitized` + `MissingUnslash` ignores.
- Outcome mix: direct correction handled the unslashing/sanitization/allowlisting changes; retained-boundary suppressions were limited to the new read-only request helpers, the two exact POST method gates, and the structured Email Follow-Ups settings-array handoff; no broader redesign or blocked `Outcome C` case was needed.

### Runtime And Test Changes

- Email Follow-Ups now centralizes read-only `$_GET` reads in `vms_email_followups_query_arg()`, keeps those reads unslashed and caller-sanitized, unslashes the structured settings payload before `vms_email_followups_sanitize_settings()`, unslashes the posted tab key, and sanitizes selected recipient emails before the existing normalization helper.
- Event Feedback now centralizes read-only admin query state in `vms_feedback_admin_query_arg()` plus `vms_feedback_admin_has_query_arg()`, keeping page-state keys, selected plan IDs, and redirect notices unslashed while leaving sanitization and casting at the call sites.
- Season Dates now uses `vms_sd_query_arg()` for read-only render-state inputs, while `vms_sd_maybe_handle_post()` retains the exact literal POST-method gate under an occurrence-specific suppression comment.
- Staff Tasks now uses `vms_tasks_admin_request_arg()` for read-only redirect return context, keeps the page gate on the existing query helper, adds `sanitize_key()` before the custom enum sanitizers, normalizes due-date and offset text with `sanitize_text_field()`, unslashes `notify_digest_time`, and sanitizes checklist `priority_order` before integer casting.
- `tests/strict-post-gate-remediation.php` now asserts that Season Dates exposes `vms_sd_query_arg()` and still normalizes its page slug through the read-only helper without weakening the exact POST gate contract.
- `tests/authorization-boundary-hardening.php` now evaluates `vms_sd_query_arg()` before exercising `vms_sd_maybe_handle_post()`, keeping the pure-PHP authorization harness aligned with the extracted helper dependency.
- `tests/administrator-explicit-notice-output-remediation.php` now asserts the new Email Follow-Ups and Event Feedback read-only helper boundaries while preserving the existing sanitized notice, selected-plan, cache-key, and delete-status contracts.
- PHP syntax checks all passed: `php -l includes/modules/email-followups/admin-ui.php`, `php -l includes/admin/event-feedback.php`, `php -l includes/admin/season-dates.php`, and `php -l includes/modules/staff-tasks/admin-ui.php`.

### Verification

- Existing focused suites passed before and after the runtime edits: `php tests/strict-post-gate-remediation.php`, `php tests/admin-request-method-wrapper-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, and `php tests/authorization-boundary-hardening.php`
- Required non-mutating regression gates passed: `php tests/runtime-stub-guards.php`, `php tests/release-compatibility-harness.php`, and `php tests/public-release-build-pipeline.php`
- Post-edit package command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rg1-post.ZjwkDI/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rg1-post.ZjwkDI/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `7e5e34af6ee93beeb7ddc07849197211c58832057869b29608679f033240b5f5`
- Post-edit packaged Plugin Check result: exit `0`, `265` errors, `1648` warnings, `1913` total findings, `20` unique rule codes, `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1784`
- Exact G1 code deltas: `WordPress.Security.NonceVerification.Recommended 372 -> 334 (-38)`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 134 -> 119 (-15)`, and `WordPress.Security.ValidatedSanitizedInput.MissingUnslash 100 -> 94 (-6)`; every other rule-code count remained unchanged
- Exact G1 row result: the `59` owned packaged rows listed above were removed exactly, no G1 row remained in the post-edit package, and no new G1 row appeared
- Exact added-row audit: the only post-edit strict-json additions were same-file line shifts for deferred DB and accepted `OutputNotEscaped` rows at `includes/admin/event-feedback.php:242:13`, `:697:83`, `includes/admin/season-dates.php:741:142`, `includes/modules/staff-tasks/admin-ui.php:274:17`, `:275:4`, `:497:17`, `:1287:78`, `:1700:87`, `:1851:92`, and `:1964:86`; the matching removed rows were the same deferred families at their old line numbers
- Deferred-family continuity: the same-file deferred DB counts in `includes/modules/staff-tasks/admin-ui.php`, `includes/admin/event-feedback.php`, and `includes/admin/season-dates.php` stayed exactly `5` rows (`DirectQuery=1`, `NoCaching=1`, `InterpolatedNotPrepared=1`, `slow_db_query_meta_key=2`), and the accepted packaged `WordPress.Security.EscapeOutput.OutputNotEscaped` total stayed exactly `127`

### Current Parent State

- Current blocker-family totals after `WPORG-28R-G1`: DB/SQL `1082`, nonce/input `635`, date/time `25`, and logging `42`
- `WPORG-28R-G1` is terminal under `verified`
- `WPORG-28R` remains blocked until every remaining `G2` through `G17` child closes and a fresh packaged strict-json rerun proves `SUBMISSION_BLOCKER=0`
- `WPORG-28`, `WPORG-28Q`, `Review-2 Name/Slug Closeout`, and `Review-13 Final Actions` all remain blocked or limited exactly as reflected in the ledger
- Exact next implementation child: `WPORG-28R-G2 — Admin dashboard and secondary settings boundaries`

### Non-Actions

- No same-file DB/SQL remediation assigned to `G8` or `G13` was attempted.
- No accepted `OutputNotEscaped` boundary was changed.
- No sibling live-tree file, builder, manifest, asset, package metadata, push, tag, upload, deployment, submission, reviewer reply, stash mutation, or external WordPress.org action occurred.

## WPORG-28R-G2 Result

Date: 2026-07-27

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-28R-G2`
- Starting mirror HEAD: `305a154f638c68699525a65fbde14535db500a57` (`Reconcile admin module form boundaries`)
- Starting parent: `9d919a487b829015b64252f858e7bf54f96b84f1`
- Authorized runtime scope: `includes/admin/approvals-review-queue.php`, `includes/admin/continuity-binder.php`, `includes/admin/express-bar.php`, `includes/admin/integrity-calendar-reconcile.php`, `includes/admin/integrity-venue-reconcile.php`, `includes/admin/menu.php`, `includes/admin/reference/keys-map.php`, `includes/admin/schedule.php`, `includes/admin/settings-page.php`, `includes/admin/settings/class-vms-settings-tours.php`, `includes/admin/settings/notifications-user-profile.php`, `includes/admin/square-sync-protection.php`, `includes/admin/staff-tax-sidebar.php`, `includes/admin/staffing.php`, `includes/admin/tax-bypass-ajax.php`, `includes/admin/tax-profile-admin-metabox.php`, `includes/admin/vendor-booking-onboarding.php`, `includes/admin/vendor-command-center.php`, `includes/admin/vendor-comp-packages.php`, `includes/admin/vendor-details.php`, `includes/admin/vendor-staff-link.php`, `includes/admin/vendor-user-link.php`, `includes/admin/venue-calendar.php`, `includes/admin/venue-comp-defaults.php`, `includes/admin/venue-context.php`, and `includes/safety/admin.php`
- Authorized support scope used: `tests/authorization-boundary-hardening.php`, `tests/strict-post-gate-remediation.php`, `tests/reference-keys-map-inline-js-remediation.php`, `tests/vendor-compensation-inline-js-remediation.php`, `tests/administrator-explicit-notice-output-remediation.php`, `tests/settings-integrity-scan-output-remediation.php`, `tests/settings-default-venue-alert-output-remediation.php`, `tests/schedule-invalid-bounds-output-remediation.php`, `tests/schedule-warning-notice-output-remediation.php`, `tests/schedule-unpublished-venue-notice-output-remediation.php`, `tests/staffing-admin-inline-assets-remediation.php`, `tests/admin-selector-redirect-uri-remediation.php`, `tests/private-file-upload-api-remediation.php`, `docs/WPORG_PREREVIEW_REMEDIATION.md`, `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`, and `docs/wporg-remediation-ledger.md`
- Live-tree rule preserved: the sibling `../../vms/` tree remained read-only and unchanged throughout this child
- Pre-edit package command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rg2-pre.4W5bdg/build --force`
- Pre-edit package result: `/tmp/wporg-28rg2-pre.4W5bdg/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `6a606d3bf173e1f124cec04c22ef6981ab622c4ba7224ca108d798ab5fcffc9f`
- Pre-edit packaged Plugin Check command: `php -d error_reporting=0 -d display_errors=0 "$(which wp)" --path='/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public' --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check '/tmp/wporg-28rg2-pre.4W5bdg/extracted/backstage-venue-manager' --slug=backstage-venue-manager --mode=new --format=strict-json --fields=file,line,column,type,code,message,docs`
- Packaged Plugin Check environment: PHP `8.5.3`, WP-CLI `2.12.0`, WordPress `7.0.1`, Plugin Check `2.0.0`, PHPCS `3.13.5`, WordPressCS `3.3.0`, and VIPWPCS `3.0.1`; the Homebrew `wp` phar's PHP `8.5` deprecation noise was normalized away by invoking `wp` through `php -d error_reporting=0 -d display_errors=0`
- Pre-edit packaged Plugin Check result: exit `0`, `265` errors, `1648` warnings, `1913` total findings, `20` unique rule codes, `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1784`
- Exact G2 ownership: `140` packaged rows (`WordPress.Security.NonceVerification.Missing=10`, `WordPress.Security.NonceVerification.Recommended=76`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized=28`, `WordPress.Security.ValidatedSanitizedInput.MissingUnslash=26`) across the twenty-six authorized runtime files only

### Exact Packaged Ownership

- `includes/admin/approvals-review-queue.php`: `1064:17 Recommended`, `1064:56 Recommended`
- `includes/admin/continuity-binder.php`: `5:19 Recommended`, `5:58 Recommended`, `177:16 Recommended`, `177:37 Recommended`, `207:24 Recommended`, `207:42 Recommended`, `298:77 InputNotSanitized`, `298:77 MissingUnslash`
- `includes/admin/express-bar.php`: `125:35 Recommended`, `125:68 Recommended`
- `includes/admin/integrity-calendar-reconcile.php`: `23:51 InputNotSanitized`, `23:51 MissingUnslash`, `157:16 Recommended`, `157:58 Recommended`, `158:20 Recommended`, `158:50 Recommended`, `217:18 Recommended`, `217:42 Recommended`
- `includes/admin/integrity-venue-reconcile.php`: `23:51 InputNotSanitized`, `23:51 MissingUnslash`, `101:16 Recommended`, `101:58 Recommended`, `102:20 Recommended`, `102:50 Recommended`, `161:18 Recommended`, `161:42 Recommended`
- `includes/admin/menu.php`: `388:17 Recommended`, `388:56 Recommended`
- `includes/admin/reference/keys-map.php`: `16:20 Recommended`, `16:48 Recommended`, `17:42 Recommended`
- `includes/admin/schedule.php`: `192:64 MissingUnslash`, `384:19 Recommended`, `384:65 Recommended`, `384:65 MissingUnslash`, `393:29 Recommended`, `393:69 Recommended`, `413:15 Recommended`, `414:29 Recommended`, `473:15 Recommended`, `474:32 Recommended`, `491:15 Recommended`, `492:35 Recommended`, `518:24 Recommended`, `518:63 Recommended`, `795:24 Recommended`, `795:52 Recommended`, `796:30 Recommended`, `796:58 Recommended`
- `includes/admin/settings-page.php`: `1755:13 Recommended`, `1755:46 Recommended`, `2488:13 Recommended`, `2600:29 Recommended`, `2601:28 Recommended`, `2898:32 Recommended`
- `includes/admin/settings/class-vms-settings-tours.php`: `60:15 Recommended`
- `includes/admin/settings/notifications-user-profile.php`: `65:43 Missing`, `65:43 MissingUnslash`, `70:42 Missing`, `70:42 MissingUnslash`, `76:65 Missing`, `77:63 Missing`, `78:68 Missing`
- `includes/admin/square-sync-protection.php`: `217:25 Recommended`, `217:77 Recommended`
- `includes/admin/staff-tax-sidebar.php`: `148:50 Recommended`, `167:50 Recommended`, `184:15 Recommended`, `188:48 Recommended`, `389:22 Missing`
- `includes/admin/staffing.php`: `268:25 InputNotSanitized`, `347:18 Recommended`, `347:46 Recommended`, `348:39 Recommended`
- `includes/admin/tax-bypass-ajax.php`: `14:68 MissingUnslash`, `15:69 MissingUnslash`
- `includes/admin/tax-profile-admin-metabox.php`: `56:22 Missing`, `56:69 Missing`
- `includes/admin/vendor-booking-onboarding.php`: `105:94 InputNotSanitized`, `109:77 InputNotSanitized`, `110:71 InputNotSanitized`, `111:99 InputNotSanitized`
- `includes/admin/vendor-command-center.php`: `1946:131 InputNotSanitized`, `1947:111 InputNotSanitized`
- `includes/admin/vendor-comp-packages.php`: `392:85 InputNotSanitized`, `397:105 InputNotSanitized`, `405:102 InputNotSanitized`, `412:95 InputNotSanitized`, `413:92 InputNotSanitized`, `420:123 InputNotSanitized`, `421:119 InputNotSanitized`, `422:123 InputNotSanitized`, `423:133 InputNotSanitized`, `424:121 InputNotSanitized`
- `includes/admin/vendor-details.php`: `142:22 Missing`, `143:46 Missing`, `344:11 InputNotSanitized`, `344:11 MissingUnslash`, `402:11 InputNotSanitized`, `402:11 MissingUnslash`
- `includes/admin/vendor-staff-link.php`: `183:59 Recommended`
- `includes/admin/vendor-user-link.php`: `278:68 InputNotSanitized`, `278:68 MissingUnslash`
- `includes/admin/venue-calendar.php`: `34:23 Recommended`, `34:51 Recommended`, `37:17 Recommended`, `37:63 Recommended`
- `includes/admin/venue-comp-defaults.php`: `64:19 InputNotSanitized`, `64:19 MissingUnslash`
- `includes/admin/venue-context.php`: `91:22 Recommended`, `92:18 Recommended`, `204:9 InputNotSanitized`, `204:9 MissingUnslash`, `262:9 InputNotSanitized`, `262:9 MissingUnslash`
- `includes/safety/admin.php`: `73:16 Recommended`, `73:54 Recommended`, `117:19 Recommended`, `117:89 Recommended`, `121:17 Recommended`, `121:74 Recommended`, `145:17 Recommended`, `145:56 Recommended`, `265:24 Recommended`, `265:55 Recommended`, `443:89 MissingUnslash`, `444:85 MissingUnslash`, `448:98 MissingUnslash`, `449:94 MissingUnslash`, `450:99 MissingUnslash`, `455:49 InputNotSanitized`, `642:42 MissingUnslash`, `661:46 InputNotSanitized`, `669:84 MissingUnslash`, `670:82 MissingUnslash`, `853:42 MissingUnslash`, `854:50 MissingUnslash`, `890:42 MissingUnslash`

### Boundary Characterization

- The owned rows split across `ADMIN_DASHBOARD`, `ADMIN_SETTINGS`, `ADMIN_PAGE`, `ADMIN_POST`, `ADMIN_AJAX`, `NOTICE_STATE`, and `REDIRECT_STATE` boundaries. Most `Recommended` rows were read-only dashboard/settings query-string state controlling tabs, sections, selected IDs, notice rendering, redirect return targets, or admin-side tool views; those reads now normalize through helper-backed unslashing instead of gaining inappropriate GET nonces.
- The state-changing boundaries already had the right broader lifecycle gates and were preserved in place: settings persistence remains under the existing options/forms pipeline, admin-post routing in `includes/admin/venue-context.php` keeps its nonce/capability validation before redirect persistence, and AJAX/state mutations in the tax-bypass and related admin helpers retain their existing authorization ordering.
- `includes/admin/settings/notifications-user-profile.php` was the one true missing nonce family: the child now performs `check_admin_referer('update-user_' . $user_id)` before reading the notification profile fields while preserving the existing user-profile save lifecycle.
- Raw-value exceptions stayed narrow and intentional. Structured settings arrays in the settings/admin helpers still flow into their existing downstream sanitizers under exact `InputNotSanitized` suppressions, and the raw `$_FILES` array in `includes/safety/admin.php` remains an occurrence-specific retained boundary because the upload handlers need the native file-structure semantics.
- Outcome mix: direct correction handled helper-backed reads, unslashing, semantic sanitization, allowlisting, and the explicit user-profile nonce gate; retained-boundary suppressions were limited to read-only admin GET state the sniff cannot recognize, downstream-owned structured arrays, and raw `$_FILES`; no broader redesign or blocked `Outcome C` case was needed.

### Runtime And Test Changes

- Added helper-backed request normalization across the bounded admin dashboard/settings files so read-only `$_GET` / `$_POST` scalars now flow through `vms_request_read_*()` helpers instead of direct array reads, while keeping call-site casting, allowlisting, and validation aligned with each screen's current semantics.
- Preserved exact read-only versus mutation distinctions: dashboard tabs, notice state, searches, selected IDs, and redirect views remained nonce-less where they only affect display, while mutation paths kept their existing capability and nonce proof ordering intact.
- Tightened user-profile and vendor/admin mutation handling by reading and sanitizing scalar fields at the request edge, decoding or normalizing only after the request value is safely bounded, and leaving downstream structured sanitizers in control of settings arrays.
- Updated the pure-PHP focused harnesses so they provide the same request-helper stubs the runtime now expects, accept the helper-backed source assertions where the mirror-only remediation intentionally leaves `../../vms/` untouched, and align notice expectations with the new exact `=== '1'` settings notice gates.
- The mirror/live parity harnesses for vendor compensation and staffing now keep the shared JS/CSS asset synchronization checks while treating the live PHP files as read-only comparators, matching the task's explicit prohibition on changing `../../vms/`.
- PHP syntax checks passed for every changed PHP file, and the child left builders, manifests, package metadata, assets, and the live local plugin tree unchanged.

### Verification

- Existing focused suites passed before or after the runtime edits as applicable: `php tests/authorization-boundary-hardening.php`, `php tests/strict-post-gate-remediation.php`, `php tests/reference-keys-map-inline-js-remediation.php`, `php tests/vendor-compensation-inline-js-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/settings-integrity-scan-output-remediation.php`, `php tests/settings-default-venue-alert-output-remediation.php`, `php tests/schedule-invalid-bounds-output-remediation.php`, `php tests/schedule-warning-notice-output-remediation.php`, `php tests/schedule-unpublished-venue-notice-output-remediation.php`, `php tests/staffing-admin-inline-assets-remediation.php`, `php tests/admin-selector-redirect-uri-remediation.php`, and `php tests/private-file-upload-api-remediation.php`
- Required non-mutating regression gates passed: `php tests/runtime-stub-guards.php`, `php tests/release-compatibility-harness.php`, and `php tests/public-release-build-pipeline.php`
- PHP lint passed on every changed PHP file: `git diff --name-only -- '*.php' | while IFS= read -r file; do php -l "$file" >/dev/null || exit 1; done`
- Post-edit package command: `php scripts/build-public-release.php --output-dir /tmp/wporg-28rg2-postfinal.5wJnce/build --force --allow-dirty`
- Post-edit package result: `/tmp/wporg-28rg2-postfinal.5wJnce/build/backstage-venue-manager-1.2.0-public-release.zip` with SHA-256 `2aa3bce162fbb8703a053e5468a5fa47482a2b9f17a36af274c60cbf336a87ab`
- Post-edit packaged Plugin Check result: exit `0`, `265` errors, `1508` warnings, `1773` total findings, `20` unique rule codes, `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1644`
- Exact G2 code deltas: `WordPress.Security.NonceVerification.Missing 85 -> 75 (-10)`, `WordPress.Security.NonceVerification.Recommended 334 -> 258 (-76)`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 119 -> 91 (-28)`, and `WordPress.Security.ValidatedSanitizedInput.MissingUnslash 94 -> 68 (-26)`; every other rule-code count remained unchanged
- Exact G2 row result: the `140` owned packaged rows listed above were removed exactly, no G2 row remained in the post-edit package, and no new G2 row appeared
- Exact added-row audit: the only post-edit strict-json additions were same-file line shifts for already-deferred DB/date and accepted `OutputNotEscaped` rows at `includes/admin/continuity-binder.php:234:14`, `:285:62`, `includes/admin/express-bar.php:139:13`, `:140:13`, `:237:18`, `includes/admin/integrity-calendar-reconcile.php:366:5`, `includes/admin/schedule.php:282:9`, `includes/admin/settings-page.php:1786:8`, `:1980:10`, `includes/admin/settings/class-vms-settings-tours.php:115:21`, `includes/admin/square-sync-protection.php:256:101`, `includes/admin/staff-tax-sidebar.php:400:85`, `includes/admin/tax-profile-admin-metabox.php:114:123`, `includes/admin/venue-calendar.php:84:10`, `includes/admin/venue-context.php:127:43`, `includes/safety/admin.php:381:37`, `:518:14`, `:1057:121`, and `:1083:18`; the matching removed rows were the same deferred families at their old line numbers
- Deferred-family continuity: the same-file deferred DB/date/logging counts in the G2 file set stayed exactly `20` rows (`DirectQuery=2`, `NoCaching=2`, `slow_db_query_meta_key=2`, `slow_db_query_meta_query=7`, `slow_db_query_meta_value=1`, `date=3`, and `error_log=3`), the same-file accepted `WordPress.Security.EscapeOutput.OutputNotEscaped` counts in the G2 file set stayed exactly `20`, and the global packaged `WordPress.Security.EscapeOutput.OutputNotEscaped` total stayed exactly `127`

### Current Parent State

- Current blocker-family totals after `WPORG-28R-G2`: DB/SQL `1082`, nonce/input `495`, date/time `25`, and logging `42`
- `WPORG-28R-G2` is terminal under `verified`
- `WPORG-28R` remains blocked until every remaining `G3` through `G17` child closes and a fresh packaged strict-json rerun proves `SUBMISSION_BLOCKER=0`
- `WPORG-28`, `WPORG-28Q`, `Review-2 Name/Slug Closeout`, and `Review-13 Final Actions` all remain blocked or limited exactly as reflected in the ledger
- Exact next implementation child: `WPORG-28R-G3 — Event Plan editor and core request boundaries`

### Non-Actions

- No same-file DB/SQL remediation assigned to `G8`, `G11`, or `G13` was attempted.
- No same-file date/time remediation assigned to `G14` was attempted.
- No same-file logging remediation assigned to `G16` or `G17` was attempted.
- No accepted `OutputNotEscaped` boundary was changed.
- No sibling live-tree file, builder, manifest, asset, package metadata, push, tag, upload, deployment, submission, reviewer reply, stash mutation, or external WordPress.org action occurred.

## WPORG-20A-S Result

Date: 2026-07-21

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-20A-S`
- Scope completed in this slice: only the Event Plan performance request-time seed inside `vms_event_plan_perf_request_id()` in `includes/core/event-plan-performance.php` and the synchronized live counterpart
- Helper-backed replacement: `vms_request_server_value('REQUEST_TIME_FLOAT')`
- Preserved request-ID contract: the seed order still remains `microtime(true)`, `wp_rand(1000, 999999)`, request-time seed, and `vms_request_current_uri()`; the request ID still uses `hash('sha256', ...)`, still truncates to 12 characters, and still reuses the static request-local cache
- Preserved downstream use: trace logging in `vms_event_plan_perf_log()` and transient lock payloads in `vms_event_plan_perf_job_set_lock()` still receive the same derived `request_id` key and no raw request-time value is persisted separately
- Accepted normalization: missing, empty, whitespace-only, and non-scalar `REQUEST_TIME_FLOAT` values now fail closed to an empty seed through the helper; ordinary scalar seeds remain part of the derived request ID without direct array, resource, or object coercion
- Focused test added: `php tests/event-plan-performance-request-id-remediation.php`
- Residual-family status at the time of this slice: the Slow Request Logger direct `REQUEST_TIME_FLOAT` read remains unchanged and accepted as timing-only, while the current Runtime Guards and Ticketing Phase B direct timing diagnostics remain deferred pending fresh packaged evidence and the parent `WPORG-20A-S` closeout audit

### What Changed

- Replaced the direct `$_SERVER['REQUEST_TIME_FLOAT']` request-time seed in `vms_event_plan_perf_request_id()` with `vms_request_server_value('REQUEST_TIME_FLOAT')`.
- Synchronized the same runtime change into the live local `vms` tree while keeping the mirror/live Event Plan performance files byte-identical.
- Added the focused request-ID characterization test and refreshed the `WPORG-20A-S` tracker entries under commit subject `Normalize Event Plan performance request-time seed` without claiming a fresh packaged Plugin Check rerun.

### Non-Actions

- Did not change the accepted Slow Request Logger timing read.
- Did not change the deferred Runtime Guards or Ticketing Phase B timing reads.
- Did not rerun packaged Plugin Check, build a package, push, or submit anything.

### Final Closeout Update

Date: 2026-07-22

- Result: `PASS`
- Fresh public package evidence: `php scripts/build-public-release.php --output-dir <temp> --force` produced `backstage-venue-manager-1.0.0-public-release.zip` with SHA-256 `83f9661c3059f23a7921ff2e9db119f430ecf5c5594a83c74bcf9505dbc81f7a`.
- Fresh packaged Plugin Check evidence: clean packaged `wp plugin check` reruns exited `0`, including the field-attributed `--mode=new --format=strict-json --fields=file,line,column,type,code,message,docs` export; the normalized raw packaged findings were retained separately in `docs/plugin-check-1.0.0-2026-07-22-raw.txt` without overwriting the historical `docs/plugin-check-1.0.0-raw.txt`.
- Historical packaged direct-server rows reconciled: the stale `docs/plugin-check-1.0.0-raw.txt` hits for `includes/core/event-plan-performance.php`, `includes/core/slow-request-logger.php`, `includes/runtime-guards.php`, and `includes/integrations/ticketing-phase-b.php` no longer reproduce for `REQUEST_TIME_FLOAT`, `REQUEST_URI`, `HTTP_USER_AGENT`, `REMOTE_ADDR`, or `HTTP_ACCEPT` in the fresh packaged scan.
- Current packaged `$_SERVER` residuals are limited to the centralized `vms_request_server_value()` helper in `includes/runtime-guards.php` and the exact-case POST helpers in `includes/admin/season-dates.php`, `includes/modules/staff-tasks/admin-ui.php`, `includes/portal/staff-portal.php`, and `includes/portal/vendor-tax-profile.php`.
- Residual disposition: the helper hit is a bounded false positive on the shared unslash-and-scalar guard, and the four exact-POST helpers remain intentionally direct so non-scalar and mixed-case request methods fail closed without routing through the broader request helper.
- Accepted timing-only reads remain in `includes/runtime-guards.php`, `includes/core/slow-request-logger.php`, and `includes/integrations/ticketing-phase-b.php`, but the fresh packaged scan did not surface them as current direct-server blockers.
- Scope boundary confirmed: the fresh packaged warnings that remain in `includes/core/event-plan-performance.php` and `includes/integrations/ticketing-phase-b.php` are current `$_REQUEST`/`$_POST` request-body findings, not residual `$_SERVER` findings under this parent.
- Focused verification reruns passed: `php tests/event-plan-performance-request-id-remediation.php`, `php tests/vendor-tax-profile-strict-post-remediation.php`, `php tests/strict-post-gate-remediation.php`, `php tests/admin-request-method-wrapper-remediation.php`, `php tests/slow-request-logger-request-input-characterization.php`, `php tests/event-feedback-request-hash-characterization.php`, `php tests/public-calendar-user-agent-view-characterization.php`, `php tests/request-input-sanitization.php`, `php tests/runtime-stub-guards.php`, `php tests/public-release-build-pipeline.php`, `php tests/release-compatibility-harness.php`, and `php tests/event-plan-legacy-ticketing-integration-smoke.php`.
- Parent status: `WPORG-20A-S` is now terminally `verified`.

## WPORG-22R-A Result

Date: 2026-07-18

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-22R-A`
- Scope completed: only the Reference Keys Map clipboard helper on the `vms-reference-keys-map` admin page
- Entry point: `vms_admin_reference_keys_map_page()` in `includes/admin/reference/keys-map.php`
- External asset used: `assets/js/vms-reference-keys-map.js`
- Enqueue scope: a dedicated `admin_enqueue_scripts` callback now loads the asset only when `page=vms-reference-keys-map` and the current user already satisfies the existing `manage_options` boundary
- Inert handoff: the existing default, success, and failure labels now flow through escaped `data-vms-copy-*` attributes on the existing `#vms-copy-keys-map` button
- Preserved behavior: the helper still targets the existing `#vms-copy-keys-map` button and `#vms-keys-map-text` textarea, still focuses and selects the textarea before calling `document.execCommand('copy')`, and still restores the default label after `1500ms`
- Tests added: `php tests/reference-keys-map-inline-js-remediation.php`
- Historical residual-family status at the time of this slice: historical `WPORG-22` B1-B5 remained closed while `WPORG-22R` still had additional residual inline-asset children; the formal parent closeout below later closes `WPORG-22R`.

### What Changed

- Removed the executable clipboard `<script>` block from `includes/admin/reference/keys-map.php`.
- Added the page-scoped `assets/js/vms-reference-keys-map.js` asset and loaded it only on the existing Reference Keys Map admin page.
- Preserved the page registration, capability, textarea payload rendering, DOM IDs, button copy behavior, and label timing while moving only the executable listener into the external asset.

### Non-Actions

- No other residual inline emitter was changed in this slice.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22R-B/G Result

Date: 2026-07-18

### Summary

- Result: `PASS`
- Exact finding identifiers: `WPORG-22R-B`, `WPORG-22R-G`
- Scope completed: only the Holidays bulk-selection helper on the `vms-holidays` admin page and the Tax Bypass required-field shim on Vendor/Staff edit screens
- External assets used: `assets/js/vms-holidays-admin.js` and `assets/js/vms-tax-bypass-admin.js`
- Enqueue scope: Holidays now uses a dedicated `admin_enqueue_scripts` callback that loads only when `page=vms-holidays` and the current user already satisfies the existing `manage_options` boundary; Tax Bypass now uses a dedicated `admin_enqueue_scripts` callback that loads only on `post` / `post-new` screens whose post type remains `vms_vendor` or `vms_staff` and whose current user already satisfies the existing `manage_options` boundary
- Preserved behavior: the Holidays helper still targets the existing `#vms_holidays_select_all` control and `.vms_holidays_row_cb` row selector, still runs as an immediate post-markup change handler, and still propagates the controlling checked state without adding a disabled-row filter; the Tax Bypass shim still targets the exact existing tax/address/payee selectors and still removes both `required` and `aria-required` without altering save, validation, nonce, or business-rule behavior
- Tests added: `php tests/wporg-22r-holidays-tax-bypass-inline-js-remediation.php`
- Sync proof: the focused verification rerun plus final `cmp -s` checks confirmed both mirror/live PHP pairs and both mirror/live JS pairs remain byte-for-byte synchronized
- Deferred sibling: `WPORG-22R-C` stayed open in this earlier slice because the current live `includes/admin/data-tools/page-event-plan-import.php` materially predated the mirror implementation and still required a dedicated reconciliation gate before any helper externalization
- Historical residual-family status at the time of this slice: historical `WPORG-22` B1-B5 remained closed, `WPORG-22R-A`, `WPORG-22R-B`, and `WPORG-22R-G` were already closed, and `WPORG-22R-C` plus the remaining residual inline-asset children were still pending in this earlier slice; the formal parent closeout below later closes `WPORG-22R`.

### What Changed

- Removed the executable Holidays bulk-selection `<script>` block from `includes/admin/holidays.php` and moved that helper into the page-scoped `assets/js/vms-holidays-admin.js` asset.
- Removed the executable Tax Bypass required-field `<script>` block from `includes/admin/tax-bypass.php` and moved that shim into the screen-scoped `assets/js/vms-tax-bypass-admin.js` asset.
- Preserved the existing page/screen registration, capabilities, selectors, and runtime behavior while moving only the executable listeners into external assets.

### Non-Actions

- No Event Plan Import file or asset changed in this slice; `WPORG-22R-C` stayed deferred until the dedicated reconciliation closeout below.

## WPORG-22R-C Result

Date: 2026-07-18

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-22R-C`
- Scope completed: only the Event Plan Import commit-selection helper on the hidden `vms-import-event-plans` admin page, plus the required live page and live Administrator shell reconciliation
- Entry point: `vms_event_plan_import_render_main_content()` in `includes/admin/data-tools/page-event-plan-import.php`
- External asset used: `assets/js/vms-event-plan-import.js`
- Enqueue scope: a dedicated `admin_enqueue_scripts` callback now loads the asset only when `page=vms-import-event-plans` and the current user already satisfies the existing `manage_options` boundary
- Inert handoff: the exact selected-required alert text now flows through escaped `data-vms-selected-required-message` on the existing `#vms-epcsv-commit-form`
- Preserved behavior: the helper still targets the existing `#vms-epcsv-commit-form`, `.vms-epcsv-row-check`, selected/all scope controls, `#vms-epcsv-selected-count`, `#vms-epcsv-select-all`, and `#vms-epcsv-clear-all`; still computes the initial selected count; still updates counts on checkbox changes; still selects or clears every rendered eligible row; and still cancels submission only when selected scope is active with zero checked rows
- Mirror/live reconciliation: the live `includes/admin/data-tools/page-event-plan-import.php` file is now synchronized to the current mirror implementation, the live `includes/admin-ui/shell.php` file is now synchronized to the unchanged mirror shell contract, and the live `assets/js/vms-event-plan-import.js` file matches the new mirror asset byte for byte
- Preserved page contracts: notice rendering, local rows-payload error rendering, `rows_json_storage_key` / legacy `rows_json_path` fallback, preview/report/redirect/rollback/cleanup lifecycle, and explicit notice shell routing remain unchanged
- Tests added: `php tests/event-plan-import-inline-js-remediation.php`
- Sync proof: focused verification reruns plus final `cmp -s` checks confirmed the mirror/live page pair, shell pair, and JS pair remain byte-for-byte synchronized
- Historical residual-family status at the time of this slice: historical `WPORG-22` B1-B5 remained closed, `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, and `WPORG-22R-G` were already closed, and additional residual inline-asset children still remained at that time; the formal parent closeout below later closes `WPORG-22R`.

### What Changed

- Removed the executable commit-selection `<script>` block from `includes/admin/data-tools/page-event-plan-import.php`.
- Added the page-scoped `assets/js/vms-event-plan-import.js` asset and loaded it only on the existing hidden Event Plan Import admin page.
- Preserved the hidden page registration, capability, rows-payload fallback, notice routing, preview/report/redirect/rollback/cleanup behavior, DOM IDs, selectors, and selected-row guard while moving only the executable helper into the external asset.
- Synchronized the live Event Plan Import page and live Administrator shell to the current mirror runtime contracts required by the explicit-notice path.

### Non-Actions

- No Event Plan Import actions, engine, runtime guards, private-file helpers, loaders, or other runtime files changed in this slice.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22R-D/H Result

Date: 2026-07-18

### Summary

- Result: `PASS`
- Exact finding identifiers: `WPORG-22R-D`, `WPORG-22R-H`
- Scope completed: only the Staff Tasks admin-page helpers in `vms_tasks_render_tasks_page()` and `vms_tasks_render_checklist_templates_page()`, plus the ADD Dispatch request-builder helper in `vms_add_dispatch_render_request_builder()`
- External assets used: `assets/js/vms-tasks-admin-pages.js` and `assets/js/vms-add-dispatch-admin.js`
- Enqueue scope: Staff Tasks now uses a dedicated `admin_enqueue_scripts` callback that loads only when `page=vms-tasks` or `page=vms-checklist-templates` and the current user already satisfies the existing `vms_tasks_current_user_can_manage_all()` boundary; ADD now keeps its existing stylesheet loader but adds the request-builder asset only when `page=vms-add-dispatch` and the current user already satisfies the existing `manage_options` boundary
- Inert data boundaries: no new executable data bridge was introduced; Staff Tasks continues to use the existing DOM IDs plus the existing checklist-option `data-scope` attributes, while ADD continues to read the existing `data-vms-add-*` row attributes and existing hidden-field contract
- Preserved behavior: the Staff Tasks asset still toggles the exact existing event/venue/assignment/recurrence/checklist controls on the Tasks page and the exact existing scope/apply-mode/venue/event-type rows on the Checklist Templates page, including the same initial-state sync and reset behavior; the ADD asset still drives the existing recipient review table, hidden-field sync, eligibility labels/details, select-all, clear-all, selected/eligible counts, filter-driven eligibility recalculation, and disabled send-button state without altering submission, notices, persistence, or public response behavior
- Mirror/live synchronization: the live `includes/modules/staff-tasks/admin-ui.php`, live `includes/modules/availability-date-dispatch/admin-ui.php`, live `assets/js/vms-tasks-admin-pages.js`, and live `assets/js/vms-add-dispatch-admin.js` files now match the mirror byte for byte
- Tests added: `php tests/wporg-22r-module-admin-helpers-inline-js-remediation.php`
- Supporting verification retained: the focused rerun also keeps the existing ADD pill/public-shell proofs and the Event Plan dead-editor/metabox proof in place
- Historical preserved residual at the time of this slice: the separate ADD menu-badge CSS and JS emitters in `includes/modules/availability-date-dispatch/admin-ui.php` stayed intentionally unchanged for the later `WPORG-22R-I` closeout
- Historical residual-family status at the time of this slice: historical `WPORG-22` B1-B5 remained closed, `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-F`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, and `WPORG-22R-L` were already closed, and only the later Staff Portal residual work remained at that time; the formal parent closeout below later closes `WPORG-22R`.

### What Changed

- Removed the executable create-task and checklist-scope `<script>` blocks from `includes/modules/staff-tasks/admin-ui.php` and moved both helpers into the page-scoped `assets/js/vms-tasks-admin-pages.js` asset.
- Removed the executable ADD request-builder `<script>` block from `includes/modules/availability-date-dispatch/admin-ui.php` and moved that helper into the page-scoped `assets/js/vms-add-dispatch-admin.js` asset.
- Preserved the exact page registrations, capabilities, DOM IDs, selectors, hidden fields, existing `data-scope` / `data-vms-add-*` attributes, and runtime state transitions while moving only the executable listeners into external assets.
- Synchronized the live Staff Tasks and ADD admin runtime files to the current mirror contracts required by this residual closeout.

### Non-Actions

- The ADD menu-badge CSS and JS emitters in `includes/modules/availability-date-dispatch/admin-ui.php` were intentionally left unchanged for the separate `WPORG-22R-I` slice.
- The ADD public shell in `includes/modules/availability-date-dispatch/public.php` was not changed.
- The Staff Tasks Event Plan metabox asset path `assets/js/vms-tasks-event-plan-metabox.js` and its enqueue contract were not changed.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22R-I Result

Date: 2026-07-18

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-22R-I`
- Scope completed: only the ADD Dispatch admin menu-badge CSS and JS emitters in `vms_add_dispatch_render_menu_badge_css()` and `vms_add_dispatch_render_menu_badge_js()`
- External assets used: the shared `assets/css/vms-admin-menu.css` stylesheet and the new admin-only `assets/js/vms-admin-menu.js` asset
- Asset gate: the existing `manage_options` and positive-count gate remains the exact owner for both menu-badge functions, while the script now enqueues through `admin_enqueue_scripts` for authorized admins with a positive pending count across wp-admin
- Dynamic handoff: the only runtime value passed into the asset is the existing pending count from `vms_add_dispatch_current_pending_count()`, exposed through inert localized config on the `vms-admin-menu` script handle
- Preserved behavior: the badge still uses the exact top-level and submenu selectors, the existing awaiting-mod / pending-count markup, the `beforeend` insertion point, the DOM-ready timing branch, the no-target no-op path, and duplicate-badge prevention
- Focused verification: `php tests/add-dispatch-menu-badge-inline-assets-remediation.php`, `php tests/add-dispatch-pill-output-remediation.php`, `php tests/add-dispatch-public-shell-output-remediation.php`, and `php tests/event-plan-secondary-vendor-capacity-and-add.php` passed in this closeout
- Known unrelated baseline retained: `php tests/add-dispatch-open-vendor-needs.php` still fails with the exact adjudicated missing-primary diagnostic and remains unrelated to this slice
- Historical residual-family status at the time of this slice: historical `WPORG-22` B1-B5 remained closed, `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, and `WPORG-22R-L` were already closed, and only the later Staff Portal residual work remained at that time; the formal parent closeout below later closes `WPORG-22R`.

### What Changed

- Removed the executable ADD menu-badge `<style>` and `<script>` emitters from `includes/modules/availability-date-dispatch/admin-ui.php`.
- Moved the static badge rules into `assets/css/vms-admin-menu.css`.
- Added the admin-only `assets/js/vms-admin-menu.js` asset and wired it through the existing ADD capability and positive-count gate with inert localized pending-count config.
- Preserved the exact selectors, markup, insertion point, duplicate prevention, and timing while synchronizing the live ADD admin PHP, live shared admin-menu CSS, and live admin-menu JS to the mirror contracts.

### Non-Actions

- The ADD request-builder asset `assets/js/vms-add-dispatch-admin.js` was not changed.
- The ADD public shell in `includes/modules/availability-date-dispatch/public.php` was not changed in this slice, and its separate public-shell CSS residual later closed under `WPORG-22R-J`.
- No ADD helper/query/open-needs logic changed, and the known missing-primary open-needs failure remained identical.
- `WPORG-24` and `Review-10` remain closed.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22R-F Result

Date: 2026-07-20

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-22R-F`
- Scope completed: only the Vendor Compensation admin metabox helpers in `vms_render_comp_package_meta_box()` and `vms_render_vendor_defaults_metabox()`
- External asset used: `assets/js/vms-compensation-admin.js`
- Enqueue scope: Comp Packages now use a dedicated `admin_enqueue_scripts` callback that loads only on `post` / `post-new` screens whose post type remains `vms_comp_package`; Vendor Defaults now use a dedicated `admin_enqueue_scripts` callback that loads only on `post` / `post-new` screens whose post type remains `vms_vendor`
- Inert handoff: Comp Packages pass only the Base Pay / Flat Fee label strings through localized config; Vendor Defaults pass only the translated preview/summary/attendance-copy strings through localized config while continuing to read template terms from the existing `data-terms` option attribute
- Preserved behavior: the Comp Package asset still targets the exact type, label, help, and attendance-mode selectors and still preserves the same show/hide logic, initial refresh, and mode-specific row toggles; the Vendor Defaults asset still preserves the exact template-copy path, summary-card output, attendance-bonus calculations, preview-table rendering, field visibility, label swaps, initial-state refresh, and save-facing field contract without changing save handlers, validation, defaults, tax logic, or persistence
- Mirror/live synchronization: the live `includes/admin/vendor-comp-packages.php`, live `includes/admin/vendor-details.php`, and live `assets/js/vms-compensation-admin.js` files now match the mirror byte for byte
- Focused verification: `php tests/vendor-compensation-inline-js-remediation.php` plus the required PHP/Node lint and mirror/live `cmp -s` checks passed in this closeout
- Historical residual-family status at the time of this slice: historical `WPORG-22` B1-B5 remained closed, `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-F`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, and `WPORG-22R-L` were already closed, and only the later Staff Portal residual work remained at that time; the formal parent closeout below later closes `WPORG-22R`.

### What Changed

- Removed the executable Comp Package metabox `<script>` block from `includes/admin/vendor-comp-packages.php`.
- Removed the executable Vendor Defaults metabox `<script>` block from `includes/admin/vendor-details.php`.
- Added the shared `assets/js/vms-compensation-admin.js` asset and loaded it only on the exact Comp Package and Vendor edit/new screens.
- Preserved the exact metabox registrations, post types, save hooks, nonces, field names, template `data-terms` bridge, preview calculations, selectors, initial-state sync, and save-facing DOM while moving only the executable UI listeners into the external asset.
- Synchronized the live Vendor Compensation PHP and JS runtime files to the current mirror contracts required by this residual closeout.

### Non-Actions

- No Staffing, Staff Portal, Staff CPT, or ADD public-shell residual emitter changed in this slice.
- `WPORG-24` and `Review-10` remain closed.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22R-J Result

Date: 2026-07-20

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-22R-J`
- Scope completed: only the standalone ADD public-shell CSS in `vms_add_dispatch_render_public_shell()`
- Entry point: `vms_add_dispatch_render_public_shell()` in `includes/modules/availability-date-dispatch/public.php`
- External asset used: `assets/css/vms-add-dispatch-public-shell.css`
- Loading mechanism: the standalone public shell now resolves a same-origin versioned stylesheet URL through `vms_add_dispatch_public_shell_stylesheet_url()` and emits one escaped `<link rel="stylesheet">` inside the existing `<head>` without adding `wp_head()` or any global enqueue
- Versioning contract: the stylesheet URL uses `vms_asset_version()` with `VMS_VERSION` fallback and appends the `ver` query through `add_query_arg()`
- Preserved behavior: the public shell still uses the same `template_redirect` gate, rewrite tag/rule, `status_header(200)`, `nocache_headers()`, standalone document wrapper, allowlisted `wp_kses()` fragment sink, escaped title/action URLs, notices, response-recording lifecycle, and explicit `exit`
- Mirror/live reconciliation: the live `includes/modules/availability-date-dispatch/public.php` and live `assets/css/vms-add-dispatch-public-shell.css` files now match the mirror byte for byte; the live runtime also picks up the already-current mirror `wp_kses()` sink hardening that previously existed only in the mirror
- Tests added: `php tests/add-dispatch-public-shell-inline-css-remediation.php`
- Historical residual-family status at the time of this slice: historical `WPORG-22` B1-B5 remained closed, `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-F`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, and `WPORG-22R-L` were already closed, and only the later Staff Portal residual work remained at that time; the formal parent closeout below later closes `WPORG-22R`.

### What Changed

- Removed the static inline ADD public-shell `<style>` block from `includes/modules/availability-date-dispatch/public.php`.
- Added the standalone `assets/css/vms-add-dispatch-public-shell.css` stylesheet and kept the migrated rule order, declarations, selectors, and media query exactly intact.
- Added the narrow `vms_add_dispatch_public_shell_stylesheet_url()` helper so the standalone shell can emit one escaped versioned stylesheet `<link>` in its existing document `<head>` without altering routing, headers, notices, or response logic.
- Synchronized the live ADD public-shell PHP and CSS runtime files to the current mirror contracts required by this residual closeout.

### Non-Actions

- No ADD admin request-builder, admin menu-badge, helper/open-needs, staffing, Staff Portal, or Staff CPT residual emitter changed in this slice.
- The existing `tests/add-dispatch-public-shell-output-remediation.php` proof remained unchanged because it did not depend on inline CSS ownership.
- `WPORG-24` and `Review-10` remain closed.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22R-K Result

Date: 2026-07-20

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-22R-K`
- Scope completed: only the Staff qualifications metabox helper in `includes/cpt/staff.php`
- Entry point: the Staff qualifications metabox callback registered on `add_meta_boxes_vms_staff`
- External asset used: `assets/js/vms-staff-cpt-admin.js`
- Enqueue scope: a dedicated `admin_enqueue_scripts` callback now loads the asset only on `post` / `post-new` screens whose post type remains `vms_staff`
- Inert handoff: the asset receives only translated labels and ordered status labels through the localized `vmsStaffCptAdmin` config object; no nonces, URLs, or record payloads are exported
- Preserved behavior: the helper still targets the existing `#vms-staff-qualification-add` and `#vms-staff-qualifications-list` nodes, still appends the same qualification row fields and hidden values including `storage_kind` and `source`, still delegates `.vms-staff-qualification-remove` clicks, and still clears the last remaining row's inputs before resetting status to `active`
- Mirror/live synchronization: the live `includes/cpt/staff.php` and live `assets/js/vms-staff-cpt-admin.js` files now match the mirror byte for byte
- Tests added: `php tests/staff-cpt-inline-js-remediation.php`
- Historical residual-family status at the time of this slice: historical `WPORG-22` B1-B5 remained closed, `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-F`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, and `WPORG-22R-L` were already closed, and only the later Staff Portal residual work remained at that time; the formal parent closeout below later closes `WPORG-22R`.

### What Changed

- Removed the executable Staff qualifications `<script>` block from `includes/cpt/staff.php`.
- Added the screen-scoped `assets/js/vms-staff-cpt-admin.js` asset and loaded it only on the exact Staff edit/new screens.
- Preserved the existing Staff CPT registration, qualifications metabox registration, nonces, save hooks, selectors, save-facing field names, proof-download fallback, and row-reset behavior while moving only the executable helper into the external asset.
- Synchronized the live Staff CPT PHP and JS runtime files to the current mirror contracts required by this residual closeout.

### Non-Actions

- `includes/admin/staffing.php` was not changed in this slice; later `WPORG-22R-L` and the formal parent closeout below completed that residual work.
- `includes/portal/staff-portal.php` was not changed in this slice; later `WPORG-22R-M` and the formal parent closeout below completed that residual work.
- No other verified `WPORG-22R` child source file changed in this slice.
- `WPORG-24` and `Review-10` remain closed.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22R-L Result

Date: 2026-07-20

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-22R-L`
- Scope completed: only the Staff Roles qualification-builder helper plus the Staffing Templates page inline style/controller emitters in `includes/admin/staffing.php`
- Entry points: `vms_staffing_admin_render_required_qualification_rows()` on the Staff Roles taxonomy add/edit forms plus `vms_staffing_admin_render_templates_page()`
- External assets used: `assets/js/vms-staffing-admin.js` and `assets/css/vms-staffing-admin.css`
- Enqueue scope: a dedicated `admin_enqueue_scripts` callback now loads the JS only on the exact `edit-tags` / `term` screens for taxonomy `vms_staff_role` under post type `vms_staff` and on the `vms-staffing-templates` admin page, while the stylesheet loads only on the templates page
- Inert handoff: the JS now reads only inert `<template>` markup and existing escaped DOM attributes already rendered by PHP; no nonces, URLs, or record payloads are exported into inline JS
- Preserved behavior: the qualification builder still targets the existing `[data-vms-qualification-builder="1"]`, `[data-vms-qualification-rows="1"]`, and `[data-vms-qualification-add="1"]` nodes, still appends the same Qualification/Enforcement rows, still delegates remove clicks, and still clears the last remaining row before resetting mode to `warn`; the Templates page still targets `#vms-tpl-slots` and `#vms-tpl-add-row`, still appends the same slot-card markup, still toggles the exact absolute/relative/end-field visibility and disabled state, still shows the existing absolute warning under the same timing conditions, and still preserves the one-row minimum removal guard
- Mirror/live synchronization: the live `includes/admin/staffing.php`, `assets/js/vms-staffing-admin.js`, and `assets/css/vms-staffing-admin.css` files now match the mirror byte for byte
- Tests added: `php tests/staffing-admin-inline-assets-remediation.php`
- Historical residual-family status at the time of this slice: historical `WPORG-22` B1-B5 remained closed, `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-F`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, and `WPORG-22R-L` were already closed, and only the later Staff Portal residual work remained at that time; the formal parent closeout below later closes `WPORG-22R`.

### What Changed

- Removed the executable qualification-builder `<script>` block from the Staff Roles taxonomy helper in `includes/admin/staffing.php`.
- Removed the static inline Templates page `<style>` block and the executable Templates page `<script>` block from `includes/admin/staffing.php`.
- Added the screen/page-scoped `assets/js/vms-staffing-admin.js` asset and the page-scoped `assets/css/vms-staffing-admin.css` stylesheet, and loaded them only under the exact Staff Roles taxonomy and Staffing Templates boundaries.
- Preserved the existing Staff Roles taxonomy wiring, Templates page registration, `manage_options` boundary, nonce/save handlers, field names, selectors, absolute-warning contract, and save-facing DOM while moving only the executable/static inline asset ownership into the external files.
- Synchronized the live Staffing admin PHP, JS, and CSS runtime files to the current mirror contracts required by this residual closeout.

### Non-Actions

- `includes/portal/staff-portal.php` was not changed in this slice; later `WPORG-22R-M` and the formal parent closeout below completed that residual work.
- `includes/cpt/staff.php` and the other verified `WPORG-22R` child source files were not changed in this slice.
- `WPORG-24` and `Review-10` remain closed.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22R-M Result

Date: 2026-07-20

### Summary

- Result: `PASS`
- Exact finding identifier: `WPORG-22R-M`
- Scope completed: only the final two Staff Portal availability inline JavaScript emitters in `includes/portal/staff-portal.php`
- Entry points: `vms_staff_portal_shortcode()` plus `vms_staff_portal_render_availability_manual()`
- External asset used: `assets/js/vms-staff-portal.js`
- Enqueue scope: the normal shortcode lifecycle now loads the asset only when `vms_staff_portal_shortcode()` resolves `tab=availability`; no shared loader, public shell, or standalone document change was required
- Inert handoff: the manual-availability form now exposes only `data-vms-staff-availability="1"`, `data-vms-staff-availability-ajax-url`, and `data-vms-staff-availability-nonce`, preserving the existing `admin_url('admin-ajax.php')` endpoint and `vms_staff_avail_ajax` nonce boundary without exporting broader record state
- Exact emitter count and behavior: this slice closes exactly two executable emitters, the `window.VMS_STAFF_AV` bootstrap block and the manual-availability autosave controller that handled the Unset -> Available -> Unavailable cycle, optimistic local updates, month-count recompute, revert-on-failure behavior, status text, and `beforeunload` warning
- Preserved behavior: the asset still targets `#vms-portal-root`, `.vms-staff-av-form`, `.vms-av-autosave`, `.vms-staff-av-btn`, `.vms-av-hidden`, `.vms-av-badge-status`, `.vms-av-src`, `.vms-av-month`, and `.vms-av-counts`; still posts the same `vms_staff_save_manual_availability_day` action, `nonce`, `date`, and `state` payload; still uses the existing staff-linked logged-in portal boundary plus `check_ajax_referer('vms_staff_avail_ajax', 'nonce')`; and still preserves the same save/failure messages, source-icon removal, locked assigned-shift behavior, and fallback-save path
- Mirror/live synchronization: the live `includes/portal/staff-portal.php` and `assets/js/vms-staff-portal.js` files now match the mirror byte for byte
- Tests added: `php tests/staff-portal-inline-js-remediation.php`
- Current residual-family status: historical `WPORG-22` B1-B5 remain closed, `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-F`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, `WPORG-22R-L`, and `WPORG-22R-M` are now closed, and the formal documentation-only parent closeout below now marks `WPORG-22R` terminally `verified`.

### What Changed

- Removed the executable `window.VMS_STAFF_AV` bootstrap `<script>` block from `vms_staff_portal_render_availability_manual()`.
- Removed the executable manual-availability autosave controller `<script>` block from `vms_staff_portal_render_availability_manual()`.
- Added the shortcode-scoped `assets/js/vms-staff-portal.js` asset and loaded it only on the Staff Portal availability tab.
- Preserved the server-side staff identity lookup, linked-profile checks, nonces, form fields, action URLs, AJAX handler, persistence logic, notices, and output contracts while moving only the client-side autosave ownership into the external asset.
- Synchronized the live Staff Portal PHP and JS runtime files to the current mirror contracts required by this residual closeout.

### Non-Actions

- `includes/admin/staffing.php` and `includes/cpt/staff.php` were not changed in this slice.
- No other verified `WPORG-22R` child source file changed in this slice.
- `WPORG-24` and `Review-10` remain closed.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

### `WPORG-22R` parent closeout result

- Result: documentation-only closeout of parent `WPORG-22R` in `docs/wporg-remediation-ledger.md` and `docs/WPORG_PREREVIEW_REMEDIATION.md` without changing runtime PHP, tests, assets, version metadata, packaging files, mirror/live runtime files, or the protected stash.
- Status: `verified`. `WPORG-22R` is now closed under terminal status `verified`: all known runtime children are verified, no executable inline JS/CSS remains inside the tracked `WPORG-22R` runtime family, and no further `WPORG-22R` work remains.
- Closure basis:
  - All known runtime children `WPORG-22R-A`, `WPORG-22R-B`, `WPORG-22R-C`, `WPORG-22R-D`, `WPORG-22R-F`, `WPORG-22R-G`, `WPORG-22R-H`, `WPORG-22R-I`, `WPORG-22R-J`, `WPORG-22R-K`, `WPORG-22R-L`, and `WPORG-22R-M` are verified.
  - Stale-test reconciliation commit `db9f19d7c14bb36c06f6467af04d5ac62af62566` (`Reconcile WPORG-22R stale tests`) reconciled exactly 17 stale remediation tests and changed no runtime, asset, live-tree, version, or packaging files.
  - `php -l` passed on all 17 maintained test files, all 17 maintained tests passed, and all 22 required support/sentinel tests passed.
  - `tests/event-plan-import-upload-api-remediation.php` passed with the accepted diagnostic `[VMS EPCSV] Preview build failed: Preview build failed.`.
  - `tests/add-dispatch-open-vendor-needs.php` still fails with `Future Event Plan with missing Primary Vendor should appear in ADD open needs.` and remains outside `WPORG-22R`.
- Final scan command: `rg -ni "<script\\b|<style\\b|wp_add_inline_script\\(|wp_add_inline_style\\(" vendor-management-system.php vms.php includes | sort`.
- Final scoped scan conclusion: no executable inline JS/CSS remains inside the tracked `WPORG-22R` runtime family; inert `application/json` and structured-data carriers remain intentionally present; unrelated executable inline assets remain elsewhere in the repository outside this parent.
- No runtime regression, stale-test regression, evidence gap, invalid maintenance boundary, changed unrelated baseline, or other closeout blocker was found.

## Review-10 Upload APIs Result

Date: 2026-07-18

### Summary

- Result: `PASS`
- Review family status: closed and verified.
- Relevant commits:
  - `88a90cce7087e2d013493236b0280da6d1bd31ea` `Use WordPress upload API for Event Plan import preview`
  - `7441c7008d8bd38834f4f8cb50b84e61d6fabda2` `Use WordPress upload API for private files`
- Both plugin-owned raw upload movers identified in this review family were replaced with `wp_handle_upload()`.
- The Event Plan preview path keeps its private staging directory, deterministic `<token>-source.csv` destination, existing preview/report storage keys, rollback, commit cleanup, notice, redirect, and filter-cleanup behavior.
- The shared private-file broker keeps its UUID storage keys, private buckets, exact caller MIME boundaries, original-filename metadata, related-post metadata, `0640` permission behavior, registration, replacement cleanup, deletion/expiration behavior, and verification-image bypass behavior.
- No attachment creation or returned public-URL behavior was introduced by either slice.
- Focused regression coverage now verifies both the Event Plan and shared private-file success/failure boundaries.
- No live plugin-owned raw HTTP-upload mover remains in the mirror `includes/` tree for this review family.
- This closes the final known upload-API implementation target.

### Focused Verification

- `php tests/event-plan-import-upload-api-remediation.php`
- `php tests/private-file-upload-api-remediation.php`
- `php tests/upload-validation-guards.php`
- `php tests/verification-proof-normalization.php`

### Source Reconciliation

- Mirror `includes/` no longer contains any live `move_uploaded_file(` match.
- Remaining alternative-upload API matches in mirror production are existing WordPress media-attachment flows in `includes/portal/vendor-portal.php` using `media_handle_upload()`, which are outside this raw-mover review family.
- The only current production `rename()` match is the slow-request log rotation path in `includes/core/slow-request-logger.php`, which is not an HTTP-upload mover.

## WPORG-23 Result

Date: 2026-07-12

### Summary

- Result: `PASS`
- Exact finding identifiers: `K1`, `K2`
- Shared helper ownership: `includes/admin-ui/context.php` via `vms_admin_ui_is_admin_notice_screen()`
- Notice entry points: `includes/admin/admin-notices.php`, `includes/runtime-guards.php`, `includes/ticketing/ticket-integrity-payment-gateway-health.php`
- Test coverage: `php tests/admin-notice-scope-remediation.php`, `php tests/request-input-sanitization.php`, `php tests/vendor-apply-admin-css-remediation.php`, and `php tests/ticket-integrity-inline-css-remediation.php`
- Current `WPORG-23` status: complete; the first-run, runtime-diagnostic, and payment-health notices are now restricted to VMS-owned or exact Ticket Integrity screens, and unrelated WordPress admin screens no longer receive them.
- Separate backlog explicitly unchanged: notice placement, top-navigation relocation, below-menu polish, and other non-WPORG-23 admin-message presentation work remain outside this batch.

### What Changed

- Added a shared admin-notice screen predicate in `includes/admin-ui/context.php` that requires a real current screen object, rejects AJAX/REST/cron/front-end contexts, and then delegates to the existing VMS screen classifier.
- Scoped the first-run notice in `includes/admin/admin-notices.php` to that shared predicate while preserving its option flag, dismissal URL/action/nonce, CTA, capability gate, text, and success notice class.
- Scoped the runtime diagnostics notice in `includes/runtime-guards.php` to that shared predicate while preserving its queue/seen-state behavior, warning severity, message rendering, and diagnostic logging.
- Scoped the payment-gateway health notice in `includes/ticketing/ticket-integrity-payment-gateway-health.php` to that shared predicate while preserving its critical-state check, severity, message, first-detected timestamp, and Ticket Integrity CTA.
- Kept Event Plan edit/new screens eligible through the existing VMS screen classifier and kept the dedicated Ticket Integrity page eligible through its exact `page=vms-ticket-integrity` screen context.

### Non-Actions

- No VMS top-navigation, notice relocation, notice container, CSS, below-menu placement polish, or unrelated post-action notice behavior was changed in this batch.
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B5 Result

Date: 2026-07-11

### Summary

- Result: `PASS`
- Exact finding identifier: `B5`
- Entry point: `includes/admin/ticket-integrity-page.php`
- CSS asset used: `assets/css/admin-ticket-integrity.css` via the existing `vms-admin-ticket-integrity` stylesheet handle
- Test coverage: `php tests/ticket-integrity-inline-css-remediation.php` plus existing `php tests/ticket-integrity-scan-lock.php`
- Current `WPORG-22` status: B1-B5 are now complete; this B5 slice remains the Ticket Integrity CSS closeout.

### What Changed

- Removed the Ticket Integrity menu-badge inline `<style>` emitter from `includes/admin/ticket-integrity-page.php`.
- Moved the static `#adminmenu .vms-ticket-integrity-alert-badge` rules into `assets/css/admin-ticket-integrity.css`.
- Kept the existing badge/no-badge decision logic intact and broadened only the stylesheet enqueue condition so the badge CSS is available on admin screens where the badge markup can render.

### Non-Actions

- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B1 Dead Partial Result
Date: 2026-07-12
### Summary
- Result: `PASS`
- Exact finding identifier: `B1`
- Scope completed: only the first B1 slice, deleting the unreferenced Event Plan `includes/cpt/event-plans/partials/editor-scripts.php` partial
- Reachability proof: checked direct includes, `capture_event_plan_partial()` callers, the generic partial-path helper, lazy AJAX section mapping, hook/filter entry points, tests, and current package/build references; no active first-party runtime caller was found
- Deleted file characteristics: approximately `1,835` lines of stale scanner-visible inline executable JavaScript, including obsolete selectors and older workflow contracts that no longer match the live Event Plan editor
- Live runtime retained: `includes/cpt/event-plans.php` and active partials continue to own the current Event Plan inline controllers; no script was moved or rewritten in this slice
- Tests added: `php tests/event-plan-dead-editor-scripts-partial-removal.php`
- Validation run: `php tests/event-plan-module-reopen-and-market-layout.php`, `php tests/event-plan-editor-vendor-preservation.php`, and `php tests/event-plan-legacy-ticketing-integration-smoke.php`
- Later B1 slices completed the live Event Plan inline blocks after this dead-partial slice.
- `B2`, `B3`, `B4`, and `B5` remain completed by the result sections below
- Current `WPORG-22` status: complete after the final B1 closeout.
- The known legacy ticketing smoke-test failure remains a documented pre-existing `WPORG-27` AJAX capture-test issue, not a new B1 regression
### What Changed
- Deleted only the dead `includes/cpt/event-plans/partials/editor-scripts.php` partial.
- Added a focused source-level regression test that proves the partial is absent, no runtime PHP or active partial inventory still references it, the live Event Plan script surface remains in `includes/cpt/event-plans.php`, the existing module assets/enqueue paths remain unchanged, and the Secondary Vendors `application/json` payload is still present.
- Left `includes/cpt/event-plans.php`, all active Event Plan partials, and all Event Plan assets unchanged in this slice.
### Non-Actions
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B1 Secondary Vendor Bootstrap Result
Date: 2026-07-12
### Summary
- Result: `PASS`
- Exact finding identifier: `B1`
- Scope completed: only the second B1 slice, removing the redundant executable inline Secondary Vendors bootstrap bridge from `includes/cpt/event-plans/partials/secondary-vendors.php`
- Duplication proof: the partial-level bridge only called `window.vmsEventPlanInitSecondaryVendors(document)`, while the live full-page Event Plan controller in `includes/cpt/event-plans.php` still self-initializes on page boot and the lazy-section success path still reinitializes after injecting returned markup
- Remaining runtime retained: the `application/json` secondary-vendor configuration payload, all Additional Vendors markup, data attributes, templates, hidden fields, save controls, badges, market targeting, capacity UI, and existing PHP conditions remain unchanged
- Behavior scope: no Secondary Vendors persistence, AJAX, assignment, compatibility-index, or wording behavior changed in this slice
- Historical controller status at this intermediate slice: the main Secondary Vendors controller still lived inline in `includes/cpt/event-plans.php`; later B1 closeout moved it into `assets/js/vms-event-plan-secondary-vendors.js`.
- Tests added: `php tests/event-plan-secondary-vendor-bootstrap-remediation.php`
- Validation run: `php tests/event-plan-secondary-vendor-assignments.php`, `php tests/event-plan-secondary-vendor-capacity-and-add.php`, `php tests/event-plan-editor-vendor-preservation.php`, `php tests/event-plan-module-reopen-and-market-layout.php`, and `php tests/event-plan-legacy-ticketing-integration-smoke.php`
- Later B1 slices completed the live Event Plan inline controllers after this bootstrap slice.
- `B2`, `B3`, `B4`, and `B5` remain completed by the result sections below
- Current `WPORG-22` status: complete after the final B1 closeout.
- The known legacy ticketing smoke-test failure remains a documented pre-existing `WPORG-27` AJAX capture-test issue, not a new B1 regression
### What Changed
- Removed only the redundant executable inline `<script>` bridge from `includes/cpt/event-plans/partials/secondary-vendors.php`.
- Added a focused source-level regression test that proves the partial still contains only the non-executable JSON payload and markup contracts, while the full-page and lazy-load initialization paths remain active in `includes/cpt/event-plans.php`.
- Left `includes/cpt/event-plans.php`, all other Event Plan partials, and all JavaScript/CSS assets unchanged in this slice.
### Non-Actions
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B1 Ticketing Focus Helper Result
Date: 2026-07-12
### Summary
- Result: `PASS`
- Exact finding identifier: `B1`
- Scope completed: only the next B1 slice, moving the small passive Event Plan ticketing-focus helper from `includes/cpt/event-plans.php` into the existing `assets/admin-ticketing.js` asset
- Configuration handoff: no new inline config was emitted; the asset now reads the existing `vms_ep_load_section=ticketing_v2` request state directly and safely no-ops when the Event Plan ticketing wrapper is absent
- Behavior retained: the migrated helper still performs only a deferred scroll-to-top of the Ticketing metabox plus a deferred focus on the first control inside `#vms-ticketing-v2-source`
- Non-intersection proof at this slice: the generic server-requested `_vms_admin_scroll_to` helper was intentionally unchanged here, and no Ticketing Rules V2, cart, save, AJAX, metadata, or legacy ticketing behavior changed in this slice; later B1 slices moved the generic scroll helper and remaining live controllers.
- Existing asset owner: `assets/admin-ticketing.js` was already enqueued on `post.php` and `post-new.php`, so no enqueue or asset-ownership expansion was required
- Tests added: `php tests/event-plan-ticketing-focus-inline-js-remediation.php`
- Validation run: `php tests/event-plan-ticket-ui-overrides-isolated.php`, `php tests/event-plan-module-reopen-and-market-layout.php`, `php tests/event-plan-editor-vendor-preservation.php`, and `php tests/event-plan-legacy-ticketing-integration-smoke.php`
- Later B1 slices completed the generic server-requested scroll helper and remaining live inline Event Plan controllers.
- `B2`, `B3`, `B4`, and `B5` remain completed by the result sections below
- Current `WPORG-22` status: complete after the final B1 closeout.
- The known legacy ticketing smoke-test failure remains a documented pre-existing `WPORG-27` AJAX capture-test issue, not a new B1 regression
### Pre-Existing Validation Exceptions
- Carried forward from the prior B1 slice and not rerun here: `php tests/add-dispatch-open-vendor-needs.php` remains a proven pre-existing `WPORG-27` failure because a future Event Plan with a missing Primary Vendor did not appear in ADD open needs.
- Carried forward from the prior B1 slice and not rerun here: `php tests/add-dispatch-assignment-review.php` remains blocked in the local test environment because `wp-load.php` could not be located.
### What Changed
- Removed only the passive inline ticketing-focus `<script>` block from `includes/cpt/event-plans.php`.
- Added a small guarded helper to `assets/admin-ticketing.js` that reads the existing requested-section query parameter, scrolls the same `#vms_event_plan_ticketing_v2` target, and focuses the same first ticketing control without mutating data.
- Added a focused source-level regression test that proves the inline helper is gone, the generic scroll helper remains, the existing asset owns the passive focus behavior, and no new asset or mutation behavior was introduced.
### Non-Actions
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B1 Generic Scroll Helper Result
Date: 2026-07-12
### Summary
- Result: `PASS`
- Exact finding identifier: `B1`
- Scope completed: only the next B1 slice, moving the generic server-requested Event Plan `_vms_admin_scroll_to` helper from `includes/cpt/event-plans.php` into the new `assets/js/vms-event-plan-shell.js` asset
- Non-executable handoff: the Event Plan shell now exposes the existing target element ID only through `data-vms-scroll-target` on the stable `.vms-ep-basic-grid` wrapper when a server scroll target is present
- Behavior retained: the migrated shell helper still performs only a deferred `document.getElementById()` lookup followed by `scrollIntoView({ behavior: 'smooth', block: 'start' })` after `150ms`; it does not focus, save, fetch, mutate history/storage, or submit forms
- Enqueue scope: the new `vms-event-plan-shell.js` asset is enqueued only on `post.php` and `post-new.php` screens for the `vms_event_plan` post type through the existing Event Plan admin asset loader in `includes/admin-ui/assets.php`
- Separation of concerns: the previously migrated ticketing-focus helper remains separately owned by `assets/admin-ticketing.js` and was not changed in this slice
- Historical remaining shell scope at this slice: the larger Event Plan shell controller, section-reopen handling, dirty-state logic, and lazy-load orchestration still required later B1 work; the final B1 closeout has since externalized that runtime.
- Tests added: `php tests/event-plan-generic-scroll-inline-js-remediation.php`
- Validation run: `php tests/event-plan-ticketing-focus-inline-js-remediation.php`, `php tests/event-plan-module-reopen-and-market-layout.php`, `php tests/event-plan-editor-vendor-preservation.php`, and `php tests/event-plan-legacy-ticketing-integration-smoke.php`
- Later B1 closeout completed the remaining live inline blocks.
- `B2`, `B3`, `B4`, and `B5` remain completed by the result sections below
- Current `WPORG-22` status: complete after the final B1 closeout.
- Known `WPORG-27` test exceptions remain unchanged
### What Changed
- Removed only the passive inline `_vms_admin_scroll_to` `<script>` block from `includes/cpt/event-plans.php`.
- Added one escaped non-executable `data-vms-scroll-target` marker to the existing Event Plan basic-grid wrapper when a server-selected target is present.
- Added the new `assets/js/vms-event-plan-shell.js` asset and enqueued it only on Event Plan edit/new screens through `includes/admin-ui/assets.php`.
- Added a focused source-level regression test that proves the inline helper is gone, the server target contract remains, the new shell asset owns the generic scroll behavior, and `admin-ticketing.js` still owns ticketing focus separately.
### Non-Actions
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B2 Modal Result
Date: 2026-07-12
### Summary
- Result: `PASS`
- Exact finding identifier: `B2`
- Scope completed: only the first B2 slice, removing the obsolete executable inline Vendor Portal modal controller from `vms_vendor_portal_shortcode()` in `includes/portal/vendor-portal.php`
- Proof outcome: no first-party runtime PHP emits `.vms-av-event-trigger` or any `data-vms-modal-*` attributes, no first-party executable code calls `window.VMSPortalCalendarModalOpen` or depends on `window.__vmsPortalModalInlineLoaded`, and `assets/js/vms-portal-calendar-modal.js` remains unused and unenqueued
- Active runtime path retained: live Vendor Portal event-detail markup still uses `.vms-public-cal`, `.vms-cal-entry`, and `.vms-cal-pop`, with behavior still owned by the existing `assets/js/vms-public-calendar.js` enqueue in the Vendor Portal path
- Deliberate non-action: the duplicate historical asset `assets/js/vms-portal-calendar-modal.js` was left unchanged and unenqueued in this slice
- Tests added: `php tests/vendor-portal-modal-inline-js-remediation.php`
- `B1` has since been completed by the Event Plan final closeout.
- `B3`, `B4`, and `B5` remain completed by the result sections above
- Later B2 slices completed passive portal shell listeners, inline form-submit attributes, availability open-state UI, and availability autosave.
- Current `WPORG-22` status: complete after B1-B5 closeout.
### What Changed
- Removed only the dead executable inline Vendor Portal modal-controller `<script>` block from `includes/portal/vendor-portal.php`.
- Preserved the surrounding shortcode output buffering, markup, live `.vms-public-cal` event-detail rendering, and the current `vms-public-calendar` enqueue path.
- Confirmed the dead modal controller contract had no active first-party selector, markup, global, caller, registration, or enqueue dependency before removing it.
### Non-Actions
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B2 Shell Result
Date: 2026-07-12
### Summary
- Result: `PASS`
- Exact finding identifier: `B2`
- Scope completed: the second B2 slice, externalizing the passive Vendor Portal shell listeners into `assets/js/vms-vendor-portal.js`
- Migrated in this slice: the narrow-layout listener, the stale Opportunities/Open Dates navigation cleanup, the three inline form-submit attributes, and the passive All Vendors accordion behavior
- Non-behavioral confirmation: no availability persistence, no `window.VMS_AV` configuration, no localStorage/cookie open-state logic, and no availability AJAX/autosave behavior changed in this slice
- B2 slice 1 remains completed by the modal-removal result above
- Later B2 slices completed availability method open-state/localStorage behavior, month accordion cookie restoration, and manual availability autosave plus its configuration handoff.
- `B1` has since been completed by the Event Plan final closeout.
- `B3`, `B4`, and `B5` remain completed by the result sections below
- Current `WPORG-22` status: complete after B1-B5 closeout.
### What Changed
- Added the narrowly scoped `vms-vendor-portal` frontend asset and enqueued it only from the Vendor Portal shortcode render path.
- Removed the targeted passive inline shell scripts from `includes/portal/vendor-portal.php`.
- Replaced the three `onchange="this.form.submit()"` attributes with external-listener markers consumed by the new asset.
- Preserved the active `vms-public-calendar` popover path and left the remaining availability inline scripts for later B2 slices.
### Non-Actions
- No deployment, push, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B2 Autosave Result
Date: 2026-07-12
### Summary
- Result: `PASS`
- Exact finding identifier: `B2`
- Scope completed: the final B2 slice, externalizing the Vendor Portal manual availability autosave controller into the existing `assets/js/vms-vendor-portal.js` asset
- Executable configuration removed: `window.VMS_AV` no longer renders from `includes/portal/vendor-portal.php`
- Non-executable configuration handoff: scoped `<script type="application/json" data-vms-portal-config="availability">...</script>` payload rendered beside the individual availability UI
- Configuration fields included: AJAX endpoint, availability nonce, preview vendor id, and preview nonce
- Non-behavioral confirmation: the AJAX endpoint, request action, and server-side handler remained unchanged
- Preserved behavior: preview-vendor mode, pending-save tracking, status messaging, icon updates, per-day accessibility labels, month count recomputation, failure-state handling, and the existing leave-page warning condition
- Tests added and run: `php tests/vendor-portal-availability-autosave-remediation.php`, `php tests/vendor-portal-availability-autosave-ajax.php`, plus the previously completed B2 focused tests and `php tests/vendor-availability-ux.php`
- `B2` slices 1 through 3 remain completed by the earlier result sections above
- `B2` is now fully completed
- `B3`, `B4`, and `B5` remain completed by the result sections below
- `B1` has since been completed by the Event Plan final closeout.
- Current `WPORG-22` status: complete after B1-B5 closeout.
### What Changed
- Removed the executable Vendor Portal autosave inline script from `includes/portal/vendor-portal.php`.
- Replaced the executable autosave global bootstrap with a narrowly scoped JSON payload consumed only by the external Vendor Portal asset.
- Extended `vms-vendor-portal.js` to own the manual availability autosave controller while preserving the existing button, hidden-input, icon, badge, count, preview, and warning contracts.
- Left the existing AJAX handler, nonce action, request fields, preview authorization flow, and availability record persistence path unchanged.
### Non-Actions
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B2 Availability State Result
Date: 2026-07-12
### Summary
- Result: `PASS`
- Exact finding identifier: `B2`
- Scope completed: the third B2 slice, externalizing the Vendor Portal availability open-state UI into the existing `assets/js/vms-vendor-portal.js` asset
- Migrated in this slice: availability-method accordion restore/store behavior using the unchanged `vms_av_open_method` browser-state key, and individual availability month restore/store behavior using the unchanged `vms_av_open_ym` browser-state key
- Non-behavioral confirmation: no network, database, preview-vendor, autosave, or availability-record persistence behavior changed in this slice
- `B2` slices 1 and 2 remain completed by the result sections above
- Later B2 autosave work completed the manual availability autosave controller and its safe configuration handoff.
- `B1` has since been completed by the Event Plan final closeout.
- `B3`, `B4`, and `B5` remain completed by the result sections below
- Current `WPORG-22` status: complete after B1-B5 closeout.
### What Changed
- Removed only the two targeted executable inline availability open-state `<script>` blocks from `includes/portal/vendor-portal.php`.
- Extended the existing `vms-vendor-portal` asset to restore/store the current availability method via `localStorage` and the current availability month via the existing cookie contract.
- Preserved the existing `data-method`, `data-ym`, and `data-today-ym` markup and left the autosave controller plus its `window.VMS_AV` configuration in PHP for the final B2 slice.
### Non-Actions
- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B3 JS Result

Date: 2026-07-11

### Summary

- Result: `PASS`
- Exact finding identifier: `B3`
- Vendor Applications renderer: `vms_vendor_apply_shortcode()` in `includes/vendor-applications.php`
- External asset used: `assets/js/vms-vendor-apply.js`
- Configuration handoff: page-local `<script type="application/json" id="vms-vendor-apply-variant-map">`
- Completing commit: `4981e8ac671181a78af699fc726cc1059c426c28` (`Move vendor application script to asset`)
- Tests added: `php tests/vendor-apply-inline-js-remediation.php`
- `B5` remains completed by the Ticket Integrity CSS sub-pass above
- The remaining admin-only CSS portion of `B3` is completed by the follow-up sub-pass immediately below

### What Changed

- Removed the executable inline Vendor Applications form script from `includes/vendor-applications.php`.
- Enqueued `vms-vendor-apply` only when the public Vendor Applications form renders.
- Preserved the existing vendor-type, label, social-field, concession-field, and band-required toggle behavior by moving the same logic into the external asset and feeding only the form-variant map through a non-executable JSON payload.

### Non-Actions

- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B3 CSS Result

Date: 2026-07-12

### Summary

- Result: `PASS`
- Exact finding identifier: `B3`
- Remaining inline CSS source: `vms_vendor_applications_admin_css()` on the Vendor Applications admin list screen in `includes/vendor-applications.php`
- External asset used: `assets/css/vms-admin.css` via the existing `vms-admin` stylesheet handle on Vendor Applications CPT admin screens
- Tests added: `php tests/vendor-apply-admin-css-remediation.php`
- Public Vendor Applications JS was already completed by committed `4981e8ac671181a78af699fc726cc1059c426c28` (`Move vendor application script to asset`)
- Public Vendor Applications CSS already lived in `assets/css/vms-portal.css` and `assets/css/vms-ui.css`
- `B5` remains completed by the Ticket Integrity CSS sub-pass above
- Current `WPORG-22` status: complete after B1-B5 closeout.

### What Changed

- Removed the `admin_head-edit.php` inline CSS emitter from `includes/vendor-applications.php`.
- Moved the Vendor Applications list-screen status-pill presentation into `assets/css/vms-admin.css`, scoped to the Vendor Applications CPT admin screens so the historical colors and sizing remain unchanged on that screen only.
- Kept the existing `vms-status-pill` and `vms-pill-*` class contract intact, so the PHP renderers and the earlier public JS remediation stay decoupled from this admin-only styling pass.

### Non-Actions

- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## WPORG-22 B4 Result

Date: 2026-07-12

### Summary

- Result: `PASS`
- Exact finding identifier: `B4`
- Completing commit: `8096682beaea18a91650f37e26675810f1a341ff` (`Move ticketing controls script to asset`)
- Original inline controller source: the server-controls `<script>` block in `vms_ticketing_v2_render_entitlements_block()` within `includes/integrations/ticketing-rules-v2.php`
- External asset reused: `assets/vms-ticketing-front.js`
- Duplication audit conclusion: the removed inline controller was redundant; the main `vms-ticketing-front` bundle already booted the same server-controls flow, while the separate `assets/vms-ticketing-front-server-controls.js` sidecar existed only as an unused parallel implementation and was not the active runtime owner
- Configuration and data handoff retained: `vmsTicketingFront.atomicAddUrl`, `vmsTicketingFront.atomicAddNonce`, `vmsTicketingFront.cartUrl`, `vmsTicketingFront.tecEventId`, `vmsTicketingFront.eventPlanId`, plus the existing `#vms-reserved-addons` `data-vms-*` payload for qualifying-ticket IDs, prior/cart quantities, pool quantities, selector mode, and per-add-on limits
- Tests added: `php tests/ticketing-server-controls-inline-js-remediation.php`
- `B3` and `B5` remain completed by the result sections above
- Current `WPORG-22` status: complete after B1-B5 closeout.

### Passing B4 Validation

- `php tests/ticketing-server-controls-inline-js-remediation.php` passed.
- `php tests/request-input-sanitization.php` passed.
- `php tests/decoded-json-validation.php` passed.
- `php -l includes/integrations/ticketing-rules-v2.php` passed.
- `git diff --check` was clean for the B4 pass.

### Pre-Existing Validation Exception

- Command: `php tests/event-plan-legacy-ticketing-integration-smoke.php`
- Exit code: `1`
- Failure point: the test's AJAX response-capture helper failed while decoding the response from `vms_ticketing_search_tec_events`.
- Failure signature: the AJAX callback emitted a successful JSON payload, but the helper's capture buffer was empty when the assertion attempted to decode it.
- Pre-B4 comparison: a detached-worktree run against `05e3c81eb16781fc8d64c7d3d46e3cb517529d36` failed with the same material signature, including the same assertion point, the same AJAX action, the same successful JSON payload type, the same empty capture-buffer behavior, and the same exit code; only generated event identifiers and stdout/stderr ordering differed.
- Failing path: `tests/event-plan-legacy-ticketing-integration-smoke.php` and `includes/integrations/ticketing.php`.
- Non-intersection with B4 commit: the B4 committed files were `docs/WPORG_PREREVIEW_REMEDIATION.md`, `includes/integrations/ticketing-rules-v2.php`, and `tests/ticketing-server-controls-inline-js-remediation.php`, so B4 does not touch the failing execution path.
- Conclusion: this is a proven pre-existing identical failure, not a B4 regression, and no B4 corrective runtime code change is required.

### Follow-Up Requirement

- The legacy ticketing AJAX capture-test defect in `php tests/event-plan-legacy-ticketing-integration-smoke.php` must be repaired or otherwise resolved before `WPORG-27`'s reproducible final test gate can be considered successful.

### What Changed

- Removed the executable inline Ticketing Rules V2 server-controls controller from `includes/integrations/ticketing-rules-v2.php`.
- Kept the existing server-rendered add-on markup and `data-vms-*` configuration contract intact so the already-enqueued `vms-ticketing-front` bundle continues to initialize the controls without any PHP-emitted executable JavaScript.
- Removed the dead inline-controller ownership marker and the stale unused server-controls sidecar-path setup from the PHP enqueue path so the runtime now matches the actual asset ownership model.

### Non-Actions

- No push, deployment, packaging, ZIP creation, tag, submission, production change, staging change, or reviewer reply occurred.

## Non-Actions in This Audit

This audit did not:

- deploy,
- stage or production-connect,
- package a release,
- tag,
- push,
- submit to WordPress.org,
- modify the live local plugin runtime,
- change tests,
- change release scripts,
- change version markers,
- or modify production data.

That note applied to the earlier docs-only audit pass; later result sections capture subsequent remediation batches that changed runtime code in the mirror and live trees.

## Public Release Slug Separation Result

Date: 2026-07-22

### Summary

- Result: `PASS`
- Scope completed in this slice: public-release builder identity, disposable release-compatibility harness identity, focused release-harness tests, mirror/live lifecycle basename compatibility, and canonical packaging documentation
- Public package contract now implemented in the current repository: `backstage-venue-manager/` ZIP root, `backstage-venue-manager-<version>-public-release.zip` artifact naming, plugin header `Text Domain: backstage-venue-manager`, and canonical public bootstrap path `backstage-venue-manager/vendor-management-system.php`
- Internal compatibility identity preserved: `VMS_PLUGIN_SLUG` remains `vms`, the main bootstrap filename remains `vendor-management-system.php`, and the sibling local live tree may remain installed as `vms/`
- Lifecycle compatibility added for both known package basenames: `includes/activation.php` now derives the current plugin basename from its containing folder, and mirror/live `includes/runtime-guards.php` now accept the exact `vms/vendor-management-system.php` and `backstage-venue-manager/vendor-management-system.php` lifecycle basenames without widening to arbitrary folders
- Focused release tests updated: `php tests/public-release-build-pipeline.php` now proves the separated public slug/root contract, and `php tests/release-compatibility-harness.php` plus `tests/compatibility/collect-state.php` now prove public extracted-package recognition, internal live-tree recognition, basename-specific build-version lookup, and arbitrary-basename rejection
- Parent status update: the later `WPORG-20A-S` closeout on 2026-07-22 supplied the fresh package build evidence, packaged Plugin Check reruns, residual direct-server adjudication, and final documentation reconciliation that were still pending when this slice landed

### What Changed

- Introduced an explicit build-only public package slug in `scripts/lib/public-release.php` and stopped deriving the public ZIP root, artifact filename, and text-domain validation target from `VMS_PLUGIN_SLUG` or the source checkout folder name.
- Updated `scripts/lib/release-compatibility.php` and `scripts/test-release-compatibility.php` so the harness expects public artifacts rooted at `backstage-venue-manager/`, still recognizes historical/internal `vms` baselines, and targets the installed plugin by exact basename rather than assuming the public artifact continues to activate as `vms`.
- Updated the mirror and authorized live runtime lifecycle files so activation/deactivation fingerprinting and Runtime Guards lifecycle recognition work for both the internal live-tree folder and the public package folder without changing any option, database, REST, AJAX, nonce, or storage namespace.
- Refreshed `docs/public-release-packaging.md` so the canonical packaging contract, compatibility examples, extracted-path examples, and Plugin Check examples all use `backstage-venue-manager` for the public package while explicitly preserving internal `vms` identifiers where they are still intentional.

### Non-Actions

- Did not change `vendor-management-system.php`, `readme.txt`, `includes/core/registry/constants.php`, `VMS_PLUGIN_SLUG`, release metadata, or version markers.
- Did not claim WordPress.org-side slug reservation, a corrected upload, a reviewer reply, or any final external resubmission action.
- Did not treat this implementation slice as a packaged Plugin Check rerun or final closeout pass.
