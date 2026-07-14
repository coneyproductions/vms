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
| `A1` | A, L | High | Confirmed | `includes/admin/addons/class-vms-admin-addons.php:15-45`; `includes/admin/addons/class-vms-addons-licensing.php:61-124`; `assets/admin/addons/manifest-addons.json:21-36` | Public package ships premium add-on discovery, installer, license storage, and Freemius remote unlock operations. | Medium | `WPORG-17B` |
| `J1` | J | High | Confirmed | `includes/core/registry/admin-menu.php:19-30`; `includes/core/registry/statuses.php:13-21` | Dynamic gettext wrapper and non-literal domain usage were remediated in the working tree; final `WPORG-18` verification now shows zero parser violations and zero actionable i18n Plugin Check findings. | Medium | `WPORG-18A`, `WPORG-18B` |
| `J2` | J | High | Confirmed | `includes/admin/continuity-binder.php:266`; `includes/core/event-credits.php:380`; `includes/core/event-plan-save-profiler.php:1476`; `includes/modules/email-followups/admin-ui.php:604`; `vms/includes/modules/admissions/outreach-recipients.php:1861` | Translator-comment, placeholder-order, and final semantic comment-audit findings were remediated in the working tree; `WPORG-18D` corrected the remaining misleading heuristic comments and the final verification suite stayed clean. | Low to Medium | `WPORG-18B`, `WPORG-18D` |
| `M1` | M | High | Confirmed | `vendor-management-system.php:3-13`; `readme.txt:4-9`; `vms-build.txt:1`; `vms/vendor-management-system.php:3-13`; `vms/readme.txt:4-9`; `vms/vms-build.txt:1` | Mirror release metadata says `1.0.0`; live local plugin says `1.1.0`. Packaging decision is blocked until versions are reconciled. | Low | `WPORG-28` |
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
| `I1` | I | Medium | Likely | `includes/integrations/load.php:4-9`; `includes/integrations/ticketing.php:44-58` | Global AJAX output buffering only stays safe if every response path reaches the cleanup helper. | Medium | `WPORG-25` |
| `I2` | I | Medium | Likely | `includes/integrations/ticketing-rules-v2.php:5860`; `includes/integrations/ticketing-rules-v2.php:7113` | Hook-scoped callback buffers in Ticketing Rules V2 are lifecycle-fragile and need architecture review before edits. | Medium | `WPORG-25` |

## Findings Already Resolved, Acceptable, or Compatibility-Sensitive

- Core bundled modules are not currently marked as premium-gated. Representative refs: `includes/modules/admissions/admissions.php:19-26`, `includes/modules/status-notices/status-notices.php:14-21`, `includes/modules/staff-tasks/staff-tasks.php:20-27`, `includes/modules/email-followups/email-followups.php:5-10`, `includes/modules/availability-date-dispatch/availability-date-dispatch.php:17-24`.
- Structured data output in `includes/public/event-details.php:669-674` is JSON-LD, not executable application logic.
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

- One confirmed public-package concern.
- One important "already resolved / not a blocker by itself" clarification.

### `A1` Public premium add-ons installer and Freemius licensing surface

- Severity: High
- Confidence: Confirmed
- References: `includes/admin/addons/class-vms-admin-addons.php:15-45`; `includes/admin/addons/class-vms-admin-addons.php:219-273`; `includes/admin/addons/class-vms-addons-licensing.php:61-124`; `includes/admin/addons/views/page-addons.php:15`; `assets/admin/addons/manifest-addons.json:21-36`
- Why WordPress.org may object: the public plugin still exposes a `Premium Add-ons` admin area, ZIP installation/update actions, license-key storage, and Freemius activate/validate/deactivate operations. Even if premium code mostly lives in separate plugins, the shipped package still presents a monetized add-on control surface.
- Recommended remediation: remove or heavily reduce the add-ons installer/licensing surface from the WordPress.org package, or convert it into a purely informational compatibility screen that does not upload, activate, validate, or monetize add-ons from within the public plugin.
- Compatibility or regression risk: Medium. The lowest-risk path is packaging-scope removal or conditional omission of the premium-management UI, not broad internal refactoring.
- Suggested remediation batch ID: `WPORG-17B`

Acceptable / already resolved notes:

- The inspected core module registrations are all marked `'premium' => false`: `includes/modules/admissions/admissions.php:19-26`, `includes/modules/status-notices/status-notices.php:14-21`, `includes/modules/staff-tasks/staff-tasks.php:20-27`, `includes/modules/email-followups/email-followups.php:5-10`, `includes/modules/availability-date-dispatch/availability-date-dispatch.php:17-24`.
- The current readme does disclose Freemius and other external services: `readme.txt:80-104`. Disclosure helps, but it does not by itself resolve the package-scope concern in `A1`.

## B. Inline JavaScript and CSS

Status:

- The original scanner-style hit table below is retained as historical prereview evidence.
- `WPORG-22` B1 through B5 are now completed in the current mirror history.
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

- `includes/public/event-details.php:669-674` uses `wp_json_encode()` to print JSON-LD structured data inside `<script type="application/ld+json">`. This is not executable runtime JS and should not be removed blindly.
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

- `includes/public/event-details.php:669-674` is the structured-data example that should remain explanation-only unless its schema payload itself is incorrect.

### `WPORG-24 E1` first portal-notice sink normalization result

- Result: the first narrow E1 slice normalized the remaining direct `vms_portal_notice()` sinks outside the main Vendor Portal in `includes/portal/vendor-tax-profile.php` and `includes/modules/admissions/vendor-guest-portal.php`.
- Contract: `vms_portal_notice()` still returns the same escaped `<div class="vms-notice vms-notice-{type}">...</div>` fragment; rendered classes, notice copy, and business logic are unchanged.
- Sink pattern: those two files now use the established `wp_kses_post(vms_portal_notice(...))` pattern already used by `includes/portal/vendor-portal.php`.
- Scope: `WPORG-24` remains open. Other trusted-HTML boundaries remain for later slices, including ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output.
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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Remaining trusted-HTML slices include ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output.

### `WPORG-24 E1` ADD dispatch pill output-contract result

- Result: ADD administrator status/source pill fragments now use a dedicated narrow final-output contract at every production sink.
- Production helper file inspected and changed: `includes/modules/availability-date-dispatch/admin-ui.php`.
- Contract: `vms_add_dispatch_pill_allowed_html()` permits only `span` with the `class` attribute.
- Helper behavior preserved: `vms_add_dispatch_status_pill()` and `vms_add_dispatch_source_pill()` still normalize status/source values with `sanitize_key()`, preserve the existing status/source label maps, preserve the neutral/success/warning/danger/source CSS classes, escape classes with `esc_attr()`, and escape labels with `esc_html()`.
- Complete production caller inventory normalized: status-pill sinks at `admin-ui.php:350`, `:366`, `:466`, `:525`, `:557`, `:724`, `:882`, and `:956`; source-pill sinks at `admin-ui.php:365`, `:523`, `:556`, `:831`, and `:955`.
- Boundary behavior: each sink applies `wp_kses(vms_add_dispatch_status_pill(...), vms_add_dispatch_pill_allowed_html())` or `wp_kses(vms_add_dispatch_source_pill(...), vms_add_dispatch_pill_allowed_html())`; no larger ADD table, row, card, form, or page HTML is filtered through the pill allowlist.
- Focused coverage and validation: `php -l includes/modules/availability-date-dispatch/admin-ui.php`, `php -l tests/add-dispatch-pill-output-remediation.php`, `php tests/add-dispatch-pill-output-remediation.php`, `php tests/event-plan-secondary-vendor-capacity-and-add.php`, and `git diff --check` passed. `php tests/add-dispatch-open-vendor-needs.php` still has the known unrelated `WPORG-27` missing-primary ADD visibility failure, and `php tests/add-dispatch-assignment-review.php` remains blocked in this local harness because `wp-load.php` could not be located.
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Remaining trusted-HTML slices include ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Remaining trusted-HTML slices include ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Remaining trusted-HTML slices include ADD public shell output, Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Remaining trusted-HTML slices include Administrator shell output, Event Plans partial/AJAX HTML, and Pass-claims output.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Remaining Administrator shell work is now limited to header action fragments, captured notice fragments, and full page content, each requiring their own narrower inventories rather than a shell-wide allowlist. Broader remaining `WPORG-24` boundaries still include Event Plans partial/AJAX HTML and Pass-claims output.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Remaining Administrator shell work still includes captured notice fragments, which require a page-family inventory, and full page content, which requires per-screen remediation. Broader remaining `WPORG-24` boundaries still include Event Plans partial/AJAX HTML and Pass-claims output.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Remaining Administrator shell captured-notice work still includes other package-owned simple families, rich linked and emphasis notice families, extra-attribute notice families, unknown/extensible captured sources, and full `$content_html`, which still requires per-screen remediation. Broader remaining `WPORG-24` boundaries still include Event Plans partial/AJAX HTML and Pass-claims output.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Remaining captured-notice work still includes other package-owned simple notices, rich linked and emphasis notices, extra-attribute notices, and unknown/extensible sources. Remaining Administrator shell work still includes source-by-source captured-notice reduction and per-screen remediation of full `$content_html`. Broader remaining `WPORG-24` boundaries still include Event Plans partial/AJAX HTML and Pass-claims output.

### `WPORG-24 E1` Square Sync Protection captured-notice reduction result

- Result: the Square Sync Protection screen now routes its simple scan/repair completion notice family through the existing Administrator shell explicit-notice path instead of leaving those fragments to be extracted into shared `$captured_notices_html`.
- Candidate inspection summary: both `includes/admin/square-sync-protection.php` and `includes/admin/staff-certifications.php` were inspected before editing. Square Sync Protection was selected because it had the smallest proven page-local boundary: one file owns the page registration, redirect helper, query-arg notice source, shell render call, and the exact two-branch simple notice family. Staff Certifications was deferred because the same file also owns a separate richer `admin_notices` warning family with `<strong>` and an `<a>` link, while the page-local empty-state notice is tied to a second data fetch / empty-state branch rather than a fixed redirect slug.
- Production files inspected: `includes/admin/square-sync-protection.php`, `includes/admin/staff-certifications.php`, `includes/admin/menu.php`, `includes/admin-ui/shell.php`, `tests/administrator-explicit-notice-output-remediation.php`, and the surrounding `WPORG-24` tracker notes.
- Production file changed: `includes/admin/square-sync-protection.php`.
- Exact Square Sync Protection notice inventory:
  - Eligible simple family: two top-level fragments, `<div class="notice notice-info"><p>Square Sync Protection scan complete.</p></div>` and `<div class="notice notice-success"><p>Square Sync Protection repair complete.</p></div>`, chosen solely from the sanitized `vms_square_notice` query slug values `scan_done` and `repair_done`.
  - Remaining captured notices on the selected page: none. No links, spans, emphasis tags, buttons, lists, IDs, `data-*`, inline styles, or third-party notice callbacks were found in that notice family.
  - Unselected candidate disposition: Staff Certifications remains unmigrated in this pass. Its page-local empty-state `<div class="notice notice-success inline"><p>…</p></div>` still exists in the content callback, and its richer off-page `admin_notices` warning family remains a separate later boundary.
- Original captured-notice path: `vms_render_square_sync_protection_page_content()` read `$_GET['vms_square_notice']`, sanitized it with `sanitize_key()`, and echoed the scan/repair completion fragment before the card content. Because each fragment was a top-level `div.notice`, the shell extracted it into `$captured_notices_html`.
- New explicit-notice path: `vms_render_square_sync_protection_page()` now supplies `'notices_callback' => 'vms_square_sync_protection_render_admin_notice'`, and the new helper `vms_square_sync_protection_render_admin_notice()` echoes the unchanged scan/repair notice family through the shell’s existing `wp_kses($explicit_notices_html, vms_admin_ui_explicit_notice_allowed_html())` boundary. The content callback no longer emits those notice blocks.
- Conditions and dynamic-value origins: the notice is controlled entirely by the page-local `vms_square_notice` query parameter. Current producers are the same page-local admin-post handlers, which still redirect through `vms_square_sync_protection_redirect()` and normalize the outgoing slug with `sanitize_key()`. The explicit notice helper preserves that source by sanitizing the inbound query arg again and escaping the fixed message text with `esc_html__()`.
- Contract and scope confirmation: `vms_admin_ui_explicit_notice_allowed_html()` remains exactly `div[class]` and `p`; no shell allowlist or raw sink was broadened; shared `$captured_notices_html` remains raw and unresolved; shared `$content_html` remains raw and unresolved; Staff Certifications remained deferred; Pass Claims remains a separate `WPORG-24` boundary; no Event Plans partial/AJAX file was touched.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Square Sync Protection `notices_callback` wiring, the dedicated helper owner, removal of the original simple notices from the content callback, preservation of the exact info/success fragments, continued callback inventory limits, and the explicit deferral of Staff Certifications. Validation ran with `php -l includes/admin/square-sync-protection.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, Staff Certifications, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remain separate follow-up work.

### `WPORG-24 E1` Staff Certifications empty-state notice reduction result

- Result: the Staff Certifications page-local empty-state notice now routes through the existing Administrator shell explicit-notice path instead of being extracted into shared `$captured_notices_html`.
- Target boundary: only the page-local empty-state fragment in `includes/admin/staff-certifications.php` moved. The separate richer linked `admin_notices` warning family in the same file remains untouched and unresolved separately.
- Production files inspected: `includes/admin/staff-certifications.php`, `includes/admin/menu.php`, `includes/admin-ui/shell.php`, `tests/administrator-explicit-notice-output-remediation.php`, and the surrounding `WPORG-24` tracker notes.
- Production file changed: `includes/admin/staff-certifications.php`.
- Exact Staff Certifications empty-state inventory:
  - Eligible simple family: `<div class="notice notice-success inline"><p>No staff certifications are waiting for review.</p></div>` when the resolved pending-review dataset is empty.
  - Separate unresolved rich family: the file still hooks `admin_notices` and emits `<div class="notice notice-warning is-dismissible vms-staff-certifications-admin-notice"><p><strong>…</strong> <a …>…</a></p></div>` outside the page screen. That linked/emphasis family remains outside the explicit contract and was not moved.
- Shared dataset mechanism: `vms_render_staff_certifications_admin_page()` now resolves the pending-review dataset exactly once through `vms_staff_certifications_get_pending_review_items()`, then passes the same resolved `$pending` array into both a page-local `notices_callback` closure and the content-rendering closure. No global state, mutable static state, transients, options, or query changes were added.
- Original captured-notice path: `vms_render_staff_certifications_admin_page_content()` previously loaded `vms_staffing_get_staff_qualification_review_items('pending_verification')`, rendered the summary card, then echoed the inline success notice and returned when `empty($pending)` was true. Because the notice was a top-level `div.notice`, the shell extracted it into `$captured_notices_html`.
- New explicit-notice path: the same page renderer now supplies a page-local notice closure through `notices_callback`, which delegates to `vms_staff_certifications_render_empty_state_notice($pending)`. The content renderer receives the same resolved dataset and no longer emits the empty-state notice.
- Conditions and escaping preserved: the notice still depends only on `empty($pending)`, still uses `esc_html__('No staff certifications are waiting for review.', 'backstage-venue-manager')`, still keeps `notice-success inline`, still remains non-dismissible, and still emits only `div[class] > p`.
- Contract and scope confirmation: no explicit-notice contract expansion occurred; shared `$captured_notices_html` remains raw and unresolved; shared `$content_html` remains raw and unresolved; the richer linked `admin_notices` warning family remains untouched; Pass Claims remains a separate `WPORG-24` boundary.
- Focused coverage and validation: `tests/administrator-explicit-notice-output-remediation.php` now proves the Staff Certifications shell wiring, exact inline notice fragment, single-query page render behavior for empty and nonempty datasets, removal of the original content-path emission, unchanged nonempty content rendering, unchanged rich warning family source, unchanged explicit-notice contract, and unchanged raw shell sinks. Validation ran with `php -l includes/admin/staff-certifications.php`, `php -l tests/administrator-explicit-notice-output-remediation.php`, `php tests/administrator-explicit-notice-output-remediation.php`, `php tests/admin-notice-scope-remediation.php`, `php tests/portal-notice-sink-remediation.php`, and `git diff --check`.
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, the separate Staff Certifications rich linked warning family, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remain separate follow-up work.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, the deferred Event Plan Import and Email Follow-Ups families, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remain separate follow-up work.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, the separate Email Follow-Ups preview warning, Event Plan Import, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remain separate follow-up work.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, Event Plan Import, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remain separate follow-up work.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, the separate Event Plan Import rows-payload error boundary, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remain separate follow-up work.

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
- Status: `WPORG-24 E1` remains `PARTIALLY STALE` / open. Shared `$captured_notices_html`, shared `$content_html`, deferred Ticket Integrity and Vendor Availability content families, the separate Event Feedback missing-plan notice, the separate Event Plan Import rows-payload error boundary, Pass Claims, and the broader Event Plans partial/AJAX output boundaries remain separate follow-up work.

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

- No direct confirmed defect from this pass.
- Two likely lifecycle risks need an architecture-aware review.

### `I1` Global AJAX output buffer depends on all response paths reaching cleanup

- Severity: Medium
- Confidence: Likely
- References: `includes/integrations/load.php:4-9`; `includes/integrations/ticketing.php:44-58`
- Why WordPress.org may object: global buffering is fragile if any later response path bypasses the cleanup helper or nests buffers unexpectedly.
- Recommended remediation: inventory every AJAX response path that relies on this buffer before changing the pattern, then tighten ownership of start/cleanup scope.
- Compatibility or regression risk: Medium.
- Suggested remediation batch ID: `WPORG-25`

### `I2` Ticketing Rules V2 hook-scoped callback buffers are lifecycle-fragile

- Severity: Medium
- Confidence: Likely
- References: `includes/integrations/ticketing-rules-v2.php:5860`; `includes/integrations/ticketing-rules-v2.php:7113`
- Why WordPress.org may object: hook-scoped callback buffering can become difficult to reason about under early returns, nested buffering, or future hook-order changes.
- Recommended remediation: review the full lifecycle before replacing these buffers; do not remove them as a scanner-only cleanup.
- Compatibility or regression risk: Medium.
- Suggested remediation batch ID: `WPORG-25`

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

- One confirmed package-scope concern already tracked as `A1`.
- One clean dependency inventory item worth preserving.

Bundled / included dependency inventory from this pass:

| Dependency | Evidence | License | GPL compatibility | Included in public package? | Notes |
| --- | --- | --- | --- | --- | --- |
| Driver.js | `assets/vendor/driverjs/LICENSE.txt:1-13` | MIT | Compatible | Yes | License file present in source. |

External-service inventory visible in the current readme:

- Cloudflare Turnstile: `readme.txt:80-84`
- QRServer / goQR.me: `readme.txt:86-89`
- Freemius: `readme.txt:91-94`
- Vendor-provided ICS URLs: `readme.txt:96-99`
- Operator-configured webhook endpoints: `readme.txt:101-104`

Conclusion:

- The bundled Driver.js dependency looks acceptable from the evidence inspected here.
- The main licensing/dependency problem is not the MIT library; it is the add-ons / Freemius package-scope behavior already tracked in `A1`.
- Recommended batch: `WPORG-27` for a final dependency and disclosure verification pass after functional remediation.

## M. Release Metadata and Package Consistency

Status:

- One confirmed release blocker.
- Two related product-owner decisions.

### `M1` Mirror and live plugin metadata are out of sync

- Severity: High
- Confidence: Confirmed
- References: `vendor-management-system.php:3-13`; `readme.txt:4-9`; `vms-build.txt:1`; `vms/vendor-management-system.php:3-13`; `vms/readme.txt:4-9`; `vms/vms-build.txt:1`
- Why WordPress.org may object: the mirror source intended for public-release work says `1.0.0`, while the live local plugin source says `1.1.0`. That makes the next package/version choice ambiguous and risks readme/header drift.
- Recommended remediation: pick the intended public release version first, then synchronize plugin header, readme stable tag, build marker, changelog references, and any packaging provenance around that single version.
- Compatibility or regression risk: Low.
- Suggested remediation batch ID: `WPORG-28`

Decision notes:

- The public release directory / slug expectation still needs a packaging decision. The local runtime directory is `vms`, while the requested public slug is `backstage-venue-manager`.
- `vms.php:1-12` is currently a compatibility shim that delegates to `vendor-management-system.php`. That is acceptable internally, but the final ZIP folder and SVN slug should be validated deliberately rather than inferred.

## N. Plugin Check and Scanner Reproducibility

Status:

- Tooling is partially available, but current reproducibility is not clean enough to use as a release gate without another setup pass.
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

- Local source-based auditing was more reliable than a fresh full CLI rerun in this pass.
- Release-gate reproducibility is currently incomplete because:
  - `phpcs` is missing,
  - WP-CLI emits PHP 8.5 deprecation noise,
  - Plugin Check CLI behavior is brittle in this environment unless invoked with a concrete target and, for runtime checks, the additional `--require` setup.
- Recommended release-gate batch: `WPORG-27`

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
10. `WPORG-25 - Output buffer lifecycle review`
   - Scope: `I1`, `I2`
   - Goal: document and tighten buffer ownership without blind removals
11. `WPORG-26 - Prefix and collision review`
    - Scope: section F only
    - Goal: document why the existing `vms` internal namespace is intentional and compatibility-sensitive
12. `WPORG-27 - Dependency, licensing, and tooling reproducibility verification`
    - Scope: section L and section N
    - Goal: final dependency inventory, disclosure check, and reproducible scanner setup
13. `WPORG-28 - Release metadata and packaging validation`
    - Scope: `M1`
    - Goal: choose the public version and validate final ZIP / slug expectations

## Findings Requiring User or Product-Owner Decisions

1. Whether the WordPress.org package will ship any add-ons discovery/licensing UI at all, or whether that surface must be omitted entirely from the public build.
2. Whether the next public version should be `1.0.0` or `1.1.0`, given the current mirror/live metadata drift.
3. Whether the final public package folder / submission flow should preserve an internal `vms` compatibility bridge while presenting the WordPress.org slug as `backstage-venue-manager`.

## Findings Requiring Explanation Rather Than Code Changes

- JSON-LD structured data in `includes/public/event-details.php:669-674`
- `application/json` state blobs in `includes/admin/addons/views/page-addons.php:53`, `includes/admin/vendor-command-center.php:1498`, `includes/admin/vendor-command-center.php:1556`, and `includes/cpt/event-plans/partials/secondary-vendors.php:465`
- Log-normalization strings in `includes/core/slow-request-logger.php:292-306`
- Established `vms` internal namespace across CPTs, AJAX, REST, and shortcodes
- Read-only status-notice request-context collection in `includes/modules/status-notices/front.php:26-29` and `includes/modules/status-notices/front.php:84`
- Driver.js license compatibility and inclusion status

## Final Release-Gate Checklist

- [ ] Decide whether the public package will retain any premium add-ons / licensing surface.
- [ ] Resolve mirror vs live version drift and synchronize all public metadata markers.
- [x] Run the final `WPORG-18B` parser/extraction audit after the `WPORG-18A` code remediation.
- [x] Complete `WPORG-19A` nonce verification input normalization in legacy save, admin-post, AJAX, REST-wrapper, and frontend mutation handlers.
- [x] Complete `WPORG-19B` missing-nonce and capability/authorization follow-up before packaging the final public submission build.
- [x] Complete `WPORG-20A` ordinary request-global sanitization, redirect allowlisting, and server-value normalization without mixing upload or decoded-JSON refactors.
- [x] Complete `WPORG-20B` upload transport and MIME/type hardening across tax-profile, import, and private-file flows.
- [x] Complete `WPORG-20C` decoded JSON / structured-body validation after the ordinary request-global pass.
- [x] Complete `WPORG-22` inline asset enqueue migration for B1-B5.
- [x] Scope all admin notices to VMS-owned screens.
- [ ] Re-run Plugin Check in a controlled release-gate environment with a concrete plugin target and documented runtime/static mode.
- [ ] Reconfirm external-service disclosures after the package-scope decisions above.
- [ ] Validate the final public ZIP folder, slug, and version before any packaging or submission work.

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

- `WPORG-22` — Inline asset enqueue migration
- `WPORG-24` — Output escaping contract pass
- `WPORG-25` — Output buffer lifecycle review
- `WPORG-26` — Prefix and collision review
- `WPORG-27` — Dependency, licensing, and tooling reproducibility verification
- `WPORG-28` — Release metadata and packaging validation

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
- The current verified working tree closes the nonce input normalization / sanitization part of section C, the targeted follow-up authorization hardening tracked in `WPORG-19B`, the ordinary request-global cleanup tracked in `WPORG-20A`, the committed upload hardening tracked in `WPORG-20B`, the decoded JSON / structured-payload hardening tracked in `WPORG-20C`, the inline asset enqueue migration tracked in `WPORG-22`, and the admin-notice scope remediation tracked in `WPORG-23`; the next actual incomplete implementation batch in this inventory now starts at `WPORG-24`.

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
- Remaining `json_decode()` sites are either test/build tooling, static/internal compatibility state, encrypted internal payloads, or trusted first-party data paths where no current user-controlled or externally controlled boundary was identified in this audit.

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

- `WPORG-22` is now completed by the recorded B1-B5 inline asset remediation passes.
- `WPORG-23` is now completed by the admin-notice scope remediation recorded below.
- `WPORG-24` is now the next incomplete implementation batch.
- `WPORG-21` was not reopened in this corrective pass.

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
