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
| `H1` | D, H | High | Confirmed | `includes/admin/tax-profile-admin-metabox.php:35-38`; `includes/portal/vendor-tax-profile.php:117-129` | Tax-profile upload handlers trust `$_FILES['type']` before `media_handle_upload()`. | Medium | `WPORG-21` |
| `K1` | K | High | Confirmed | `includes/admin/admin-notices.php:16-64` | First-run notice is global, promotional, and not scoped to VMS screens. | Low | `WPORG-23` |
| `K2` | K | High | Confirmed | `includes/runtime-guards.php:100-108`; `includes/ticketing/ticket-integrity-payment-gateway-health.php:1044-1052` | Diagnostics and payment-gateway notices are hooked globally to `admin_notices` without VMS-screen gating. | Low to Medium | `WPORG-23` |
| `C1` | C | Medium | Confirmed | `includes/cpt/venues.php:266-269`; `includes/cpt/ratings.php:177-180`; `includes/admin/staff-worker-type.php:75-76`; `includes/admin/venue-context.php:169-170`; `includes/vendor-applications.php:1728-1729`; `includes/portal/vendor-tax-profile.php:92-93`; `includes/admin/tax-profile-admin-metabox.php:35-38` | `WPORG-19A` working-tree remediation now normalizes and sanitizes nonce verification inputs across the audited direct request and wrapper/REST paths; missing-nonce and broader capability follow-up remains deferred to `WPORG-19B`. | Low to Medium | `WPORG-19A`, `WPORG-19B` |
| `D1` | D | Medium | Confirmed | `includes/portal/staff-portal.php:1755-1758` | `filter_input(..., FILTER_UNSAFE_RAW)` remains in a live request path. | Low | `WPORG-20` |
| `H2` | D, H | Medium | Confirmed | `includes/admin/data-tools/actions-event-plan-import.php:13-33` | CSV import stores uploaded files with `move_uploaded_file()` before type/content validation beyond filename intent. | Medium | `WPORG-21` |
| `H3` | D, H | Medium | Confirmed | `includes/safety/private-files.php:163-180` | Private-file storage derives MIME from the original filename after moving the file, not from content. | Medium | `WPORG-21` |
| `B1` | B | Medium | Confirmed | `includes/cpt/event-plans.php:5870,6537,6563,6591,6629,7113,7306,8171,8420,8591,8775,8789`; `includes/cpt/event-plans/partials/editor-scripts.php:2,30,697,723,761,783,806,1050,1223,1472,1643,1826` | Event Plans and its editor partials still contain dense inline executable JavaScript. | Medium to High | `WPORG-22` |
| `B2` | B | Medium | Confirmed | `includes/portal/vendor-portal.php:4690,4701,4738,5068,5635,6219,6231,6490,6651,6893` | Vendor Portal contains inline scripts and inline event handlers such as `onchange="this.form.submit()"`. | Medium | `WPORG-22` |
| `B3` | B | Medium | Confirmed | `includes/vendor-applications.php:1393,2487` | Vendor Applications renders inline `<style>` and inline executable `<script>` blocks. | Low to Medium | `WPORG-22` |
| `B4` | B | Medium | Confirmed | `includes/integrations/ticketing-rules-v2.php:7917` | Ticketing Rules V2 still emits a large inline executable script block. | Medium | `WPORG-22` |
| `B5` | B | Low | Confirmed | `includes/admin/ticket-integrity-page.php:2412` | Ticket Integrity admin page emits inline CSS directly in PHP. | Low | `WPORG-22` |
| `D2` | D | Medium | Likely | `includes/vendor-applications.php:2142-2180`; `includes/vendor-applications.php:2186-2194` | Turnstile verification and request-fingerprint code need stricter response-shape handling and explicit normalization review. | Low to Medium | `WPORG-20` |
| `D3` | D | Medium | Likely | `includes/integrations/ticketing-phase-b.php:1936-1955` | Ticketing Phase B accepts JSON-decoded arrays after light type checks; shape validation is still thin. | Medium | `WPORG-20` |
| `D4` | D | Medium | Likely | `includes/integrations/ticketing-rules-v2.php:9044-9072`; `includes/integrations/ticketing-rules-v2.php:9430-9431` | Ticketing Rules V2 JSON-body handlers do nonce checks and normalization, but still need structured payload review before blind hardening. | Medium | `WPORG-20` |
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

- `48` scanner-style hits across the six highest-signal files inspected in this pass.
- Five confirmed actionable clusters.
- Two acceptable structured-data/state-blob patterns that should not be "fixed" as if they were executable inline JS.

Inline-hit counts from this pass:

| File | Count |
| --- | ---: |
| `includes/cpt/event-plans.php` | `12` |
| `includes/cpt/event-plans/partials/editor-scripts.php` | `12` |
| `includes/portal/vendor-portal.php` | `10` |
| `includes/vendor-applications.php` | `2` |
| `includes/integrations/ticketing-rules-v2.php` | `1` |
| `includes/admin/ticket-integrity-page.php` | `1` |

### `B1` Event Plans and editor partials still ship dense inline executable JS

- Severity: Medium
- Confidence: Confirmed
- References: `includes/cpt/event-plans.php:5870,6537,6563,6591,6629,7113,7306,8171,8420,8591,8775,8789`; `includes/cpt/event-plans/partials/editor-scripts.php:2,30,697,723,761,783,806,1050,1223,1472,1643,1826`
- Why WordPress.org may object: large inline script blocks are a recurring Plugin Check target and make escaping, dependency management, and CSP-friendly packaging harder to reason about.
- Recommended remediation: split static behavior into enqueued assets, keep only minimal runtime configuration in `wp_add_inline_script()` or `application/json` payloads, and avoid PHP partials that emit raw `<script>` tags.
- Compatibility or regression risk: Medium to High because Event Plans is operationally dense and historically brittle.
- Suggested remediation batch ID: `WPORG-22`

### `B2` Vendor Portal still contains inline scripts and inline event handlers

- Severity: Medium
- Confidence: Confirmed
- References: `includes/portal/vendor-portal.php:4690,4701,4738,5068,5635,6219,6231,6490,6651,6893`
- Why WordPress.org may object: inline executable JS plus `onchange="this.form.submit()"` is a direct match for the prereview complaint.
- Recommended remediation: move runtime logic into enqueued assets and replace inline attributes with delegated event listeners.
- Compatibility or regression risk: Medium because the portal mixes auth, availability, and opportunity UI concerns.
- Suggested remediation batch ID: `WPORG-22`

### `B3` Vendor Applications still emits inline CSS and inline JS

- Severity: Medium
- Confidence: Confirmed
- References: `includes/vendor-applications.php:1393`; `includes/vendor-applications.php:2487`
- Why WordPress.org may object: scanner-visible inline styles and scripts remain in a public-facing submission flow.
- Recommended remediation: move CSS into a style handle and move behavior into an enqueued script, leaving only structured state in JSON if necessary.
- Compatibility or regression risk: Low to Medium.
- Suggested remediation batch ID: `WPORG-22`

### `B4` Ticketing Rules V2 still emits inline executable JS

- Severity: Medium
- Confidence: Confirmed
- References: `includes/integrations/ticketing-rules-v2.php:7917`
- Why WordPress.org may object: the runtime still prints executable script directly from PHP.
- Recommended remediation: move script logic into a versioned asset and feed dynamic config through `wp_add_inline_script()` or a JSON element.
- Compatibility or regression risk: Medium because this file also drives cart and claims flows.
- Suggested remediation batch ID: `WPORG-22`

### `B5` Ticket Integrity page still prints inline CSS directly

- Severity: Low
- Confidence: Confirmed
- References: `includes/admin/ticket-integrity-page.php:2412`
- Why WordPress.org may object: even small admin-only inline CSS still shows up as a direct scanner hit.
- Recommended remediation: replace with an enqueued admin stylesheet or `wp_add_inline_style()` attached to an existing handle.
- Compatibility or regression risk: Low.
- Suggested remediation batch ID: `WPORG-22`

Acceptable / false-positive notes:

- `includes/public/event-details.php:669-674` uses `wp_json_encode()` to print JSON-LD structured data inside `<script type="application/ld+json">`. This is not executable runtime JS and should not be removed blindly.
- `includes/admin/addons/views/page-addons.php:53`, `includes/admin/vendor-command-center.php:1498`, `includes/admin/vendor-command-center.php:1556`, and `includes/cpt/event-plans/partials/secondary-vendors.php:465` emit `application/json` state payloads, which are materially different from executable `<script>` blocks.

## C. Nonces and Permissions

Status:

- `WPORG-19A` nonce verification input normalization is now applied in the current mirror/live working tree for the shared runtime paths, plus the live-only local outreach module.
- `WPORG-19B` is still required for missing-nonce additions and broader capability/authorization review that was intentionally kept out of this batch.
- Several handlers already had the right capability/nonce shape and only required normalization cleanup, not new permission design.

### `C1` Multiple state-changing handlers verify raw submitted nonces without `wp_unslash()`

- Severity: Medium
- Confidence: Confirmed
- References: `includes/cpt/venues.php:266-269`; `includes/cpt/venues.php:382`; `includes/cpt/venues.php:536`; `includes/cpt/ratings.php:177-180`; `includes/cpt/ratings.php:404`; `includes/admin/staff-worker-type.php:75-76`; `includes/admin/venue-context.php:169-170`; `includes/admin/venue-context.php:224`; `includes/admin/venue-context.php:278`; `includes/admin/tax-profile-admin-metabox.php:35-38`; `includes/portal/vendor-tax-profile.php:92-93`; `includes/vendor-applications.php:1728-1729`; `includes/vendor-applications.php:2581-2582`
- Why WordPress.org may object: WordPress coding standards expect submitted nonce values to be unslashed before verification, and these paths mutate state.
- Recommended remediation: normalize each submitted nonce once with `wp_unslash()` plus string casting before `wp_verify_nonce()`, while keeping the existing capability and early-return behavior intact. That normalization pass is now applied in the current `WPORG-19A` working tree; remaining missing-nonce and capability-scope work stays deferred to `WPORG-19B`.
- Compatibility or regression risk: Low to Medium if limited to nonce normalization only.
- Suggested remediation batch ID: `WPORG-19A`, `WPORG-19B`

Acceptable notes:

- Some request reads are read-only and do not need nonce checks merely to silence scanners.
- `includes/modules/staff-tasks/admin-ui.php:704`, `includes/modules/staff-tasks/admin-ui.php:778`, and `includes/modules/staff-tasks/admin-ui.php:843` already follow the expected unslash pattern and can be used as a local reference implementation.

## D. Input Sanitization and Validation

Status:

- One confirmed issue outside file-upload handling.
- Three likely deeper-review clusters.
- Upload-specific validation findings are tracked under section H to avoid duplication.

### `D1` `FILTER_UNSAFE_RAW` remains in a live staff-portal request path

- Severity: Medium
- Confidence: Confirmed
- References: `includes/portal/staff-portal.php:1755-1758`
- Why WordPress.org may object: `FILTER_UNSAFE_RAW` is a known red-flag pattern and invites automated review comments even when later normalization exists.
- Recommended remediation: replace the `filter_input()` call with direct request access normalized through `wp_unslash()` and the existing `sanitize_key()` allowlist path.
- Compatibility or regression risk: Low.
- Suggested remediation batch ID: `WPORG-20`

### `D2` Turnstile verification and fingerprint storage need tighter validation review

- Severity: Medium
- Confidence: Likely
- References: `includes/vendor-applications.php:2142-2180`; `includes/vendor-applications.php:2186-2194`
- Why WordPress.org may object: the flow trusts a minimally validated `json_decode()` result and records raw IP / user-agent values after trimming only.
- Recommended remediation: validate expected response keys explicitly, normalize request fingerprint fields consistently, and document why any raw values must be retained.
- Compatibility or regression risk: Low to Medium.
- Suggested remediation batch ID: `WPORG-20`

### `D3` Ticketing Phase B JSON payload handling still needs shape validation

- Severity: Medium
- Confidence: Likely
- References: `includes/integrations/ticketing-phase-b.php:1936-1955`
- Why WordPress.org may object: JSON-decoded arrays are accepted after basic type checks, but there is limited per-key validation before later logic consumes them.
- Recommended remediation: add schema-like validation for required keys, types, and value ranges before accepting the decoded arrays.
- Compatibility or regression risk: Medium.
- Suggested remediation batch ID: `WPORG-20`

### `D4` Ticketing Rules V2 JSON-body handlers need careful structured-input review

- Severity: Medium
- Confidence: Likely
- References: `includes/integrations/ticketing-rules-v2.php:9044-9072`; `includes/integrations/ticketing-rules-v2.php:9430-9431`
- Why WordPress.org may object: raw request bodies are JSON-decoded and then normalized, but the payload contract is not fully explicit from the initial guard layer.
- Recommended remediation: keep the existing nonce and array normalization, then add targeted payload-schema validation instead of blanket `sanitize_text_field()` rewrites.
- Compatibility or regression risk: Medium.
- Suggested remediation batch ID: `WPORG-20`

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

- Three confirmed actionable findings.
- One acceptable reference implementation that should guide remediation.

### `H1` Tax-profile upload flows trust `$_FILES['type']`

- Severity: High
- Confidence: Confirmed
- References: `includes/admin/tax-profile-admin-metabox.php:35-38`; `includes/portal/vendor-tax-profile.php:117-129`
- Why WordPress.org may object: client-reported MIME types are not authoritative, and the current allowlist check happens before `media_handle_upload()`.
- Recommended remediation: replace the `$_FILES['type']` trust path with `wp_check_filetype_and_ext()` or equivalent server-side validation before accepting the upload.
- Compatibility or regression risk: Medium because both admin and portal flows touch operator workflows.
- Suggested remediation batch ID: `WPORG-21`

### `H2` Event-plan CSV import stores uploads before validating content type

- Severity: Medium
- Confidence: Confirmed
- References: `includes/admin/data-tools/actions-event-plan-import.php:13-33`
- Why WordPress.org may object: the importer confirms upload mechanics, then moves the file into storage without validating content or MIME beyond expected CSV intent.
- Recommended remediation: validate MIME, extension, and actual parseability before or immediately after storing the temporary file, and fail closed on mismatch.
- Compatibility or regression risk: Medium because the importer is operationally sensitive.
- Suggested remediation batch ID: `WPORG-21`

### `H3` Private-file storage infers MIME from filename after moving the file

- Severity: Medium
- Confidence: Confirmed
- References: `includes/safety/private-files.php:163-180`
- Why WordPress.org may object: MIME classification comes from `wp_check_filetype($filename)` after the file is already stored, which is filename-based and not content-based.
- Recommended remediation: validate file content and extension before persistence, then record the verified type rather than inferring it from the original filename alone.
- Compatibility or regression risk: Medium because this storage layer is intentionally private and should not be rewritten casually.
- Suggested remediation batch ID: `WPORG-21`

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

- Two confirmed actionable findings.
- One pre-existing top-nav system that should be documented, not conflated with the global notice problem.

### `K1` First-run notice is global, promotional, and not VMS-screen scoped

- Severity: High
- Confidence: Confirmed
- References: `includes/admin/admin-notices.php:16-64`
- Why WordPress.org may object: the notice appears anywhere in `wp-admin` for admins, includes setup-checklist copy, and presents an `Open VMS` CTA.
- Recommended remediation: restrict it to VMS-owned screens, minimize promotional wording, or remove it from the WordPress.org package.
- Compatibility or regression risk: Low.
- Suggested remediation batch ID: `WPORG-23`

### `K2` Diagnostics and payment-health notices render globally through `admin_notices`

- Severity: High
- Confidence: Confirmed
- References: `includes/runtime-guards.php:100-108`; `includes/ticketing/ticket-integrity-payment-gateway-health.php:1044-1052`
- Why WordPress.org may object: these notices can appear outside VMS screens and contribute to the dashboard-hijack concern under Guideline 11.
- Recommended remediation: gate both notice systems to VMS screens or convert them into screen-specific diagnostics inside existing VMS admin pages.
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
   - Result: completed in the current working tree; keep paired with `WPORG-19B` before packaging
2. `WPORG-19B - Missing nonce and capability/authorization follow-up`
   - Scope: remaining section C concerns outside normalization
   - Goal: add missing nonces where needed and review capability/authorization boundaries without broad functional changes
3. `WPORG-20 - Input sanitization and structured-payload review`
   - Scope: `D1`, `D2`, `D3`, `D4`
   - Goal: remove `FILTER_UNSAFE_RAW`, then validate JSON/body/fingerprint inputs deliberately
4. `WPORG-21 - Upload handling hardening`
   - Scope: `H1`, `H2`, `H3`
   - Goal: align uploads with content-based validation and private-storage justification
5. `WPORG-22 - Inline asset enqueue migration`
   - Scope: `B1`, `B2`, `B3`, `B4`, `B5`
   - Goal: move executable JS/CSS out of inline PHP output
6. `WPORG-23 - Admin notice scope`
   - Scope: `K1`, `K2`
   - Goal: keep notices on VMS-owned screens only
7. `WPORG-24 - Output escaping contract pass`
   - Scope: `E1`
   - Goal: separate genuine escaping defects from safe HTML/JSON patterns
8. `WPORG-25 - Output buffer lifecycle review`
   - Scope: `I1`, `I2`
   - Goal: document and tighten buffer ownership without blind removals
9. `WPORG-26 - Prefix and collision review`
    - Scope: section F only
    - Goal: document why the existing `vms` internal namespace is intentional and compatibility-sensitive
10. `WPORG-27 - Dependency, licensing, and tooling reproducibility verification`
    - Scope: section L and section N
    - Goal: final dependency inventory, disclosure check, and reproducible scanner setup
11. `WPORG-28 - Release metadata and packaging validation`
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
- [ ] Complete `WPORG-19B` missing-nonce and capability/authorization follow-up before packaging the final public submission build.
- [ ] Replace `FILTER_UNSAFE_RAW` and validate structured request bodies explicitly.
- [ ] Harden upload validation across tax-profile, import, and private-file flows.
- [ ] Migrate remaining inline executable JS/CSS into enqueued assets or approved inline helpers.
- [ ] Scope all admin notices to VMS-owned screens.
- [ ] Re-run Plugin Check in a controlled release-gate environment with a concrete plugin target and documented runtime/static mode.
- [ ] Reconfirm external-service disclosures after the package-scope decisions above.
- [ ] Validate the final public ZIP folder, slug, and version before any packaging or submission work.

## WPORG-17B Result

Date: 2026-07-10

### Summary

- Result: `PASS WITH NOTES`
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

- `WPORG-18B` — Internationalization parser compliance verification
- `WPORG-19B` — Missing nonce and capability/authorization follow-up
- `WPORG-20` — Input sanitization and structured-payload review
- `WPORG-21` — Upload handling hardening
- `WPORG-22` — Inline asset enqueue migration
- `WPORG-23` — Admin notice scope
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

- Result: `PASS WITH NOTES`
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

- Result: `PASS WITH FOLLOW-UP`
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
- Missing-nonce findings newly inventoried in this batch and deferred to `WPORG-19B`: `0`
- Capability / authorization findings newly inventoried in this batch and deferred to `WPORG-19B`: `0`

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

- `WPORG-19B` is still required.
- This batch intentionally did not add missing nonces to handlers that currently lack them.
- This batch intentionally did not broaden or tighten capabilities, roles, ownership rules, or endpoint visibility.
- The current verified working tree closes the nonce input normalization / sanitization part of section C only; broader missing-nonce and authorization review remains open by scope choice, not by a failed normalization check.

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
