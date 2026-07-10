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

Highest-priority issues from a WordPress.org rejection-risk perspective:

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
| `J1` | J | High | Confirmed | `includes/core/registry/admin-menu.php:19-30`; `includes/core/registry/statuses.php:13-21` | Dynamic gettext wrapper and non-literal domain usage are not parser-compatible for WordPress.org translations. | Medium | `WPORG-18` |
| `M1` | M | High | Confirmed | `vendor-management-system.php:3-13`; `readme.txt:4-9`; `vms-build.txt:1`; `vms/vendor-management-system.php:3-13`; `vms/readme.txt:4-9`; `vms/vms-build.txt:1` | Mirror release metadata says `1.0.0`; live local plugin says `1.1.0`. Packaging decision is blocked until versions are reconciled. | Low | `WPORG-28` |
| `H1` | D, H | High | Confirmed | `includes/admin/tax-profile-admin-metabox.php:35-38`; `includes/portal/vendor-tax-profile.php:117-129` | Tax-profile upload handlers trust `$_FILES['type']` before `media_handle_upload()`. | Medium | `WPORG-21` |
| `K1` | K | High | Confirmed | `includes/admin/admin-notices.php:16-64` | First-run notice is global, promotional, and not scoped to VMS screens. | Low | `WPORG-23` |
| `K2` | K | High | Confirmed | `includes/runtime-guards.php:100-108`; `includes/ticketing/ticket-integrity-payment-gateway-health.php:1044-1052` | Diagnostics and payment-gateway notices are hooked globally to `admin_notices` without VMS-screen gating. | Low to Medium | `WPORG-23` |
| `C1` | C | Medium | Confirmed | `includes/cpt/venues.php:266-269`; `includes/cpt/ratings.php:177-180`; `includes/admin/staff-worker-type.php:75-76`; `includes/admin/venue-context.php:169-170`; `includes/vendor-applications.php:1728-1729`; `includes/portal/vendor-tax-profile.php:92-93`; `includes/admin/tax-profile-admin-metabox.php:35-38` | Multiple state-changing handlers pass raw submitted nonces directly to `wp_verify_nonce()` instead of unslashing first. | Low to Medium | `WPORG-19` |
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

- One confirmed hardening cluster.
- Several handlers already have the right capability/nonce shape and only need normalization cleanup, not new permission design.

### `C1` Multiple state-changing handlers verify raw submitted nonces without `wp_unslash()`

- Severity: Medium
- Confidence: Confirmed
- References: `includes/cpt/venues.php:266-269`; `includes/cpt/venues.php:382`; `includes/cpt/venues.php:536`; `includes/cpt/ratings.php:177-180`; `includes/cpt/ratings.php:404`; `includes/admin/staff-worker-type.php:75-76`; `includes/admin/venue-context.php:169-170`; `includes/admin/venue-context.php:224`; `includes/admin/venue-context.php:278`; `includes/admin/tax-profile-admin-metabox.php:35-38`; `includes/portal/vendor-tax-profile.php:92-93`; `includes/vendor-applications.php:1728-1729`; `includes/vendor-applications.php:2581-2582`
- Why WordPress.org may object: WordPress coding standards expect submitted nonce values to be unslashed before verification, and these paths mutate state.
- Recommended remediation: normalize each submitted nonce once with `wp_unslash()` plus string casting before `wp_verify_nonce()`, while keeping the existing capability and early-return behavior intact.
- Compatibility or regression risk: Low to Medium if limited to nonce normalization only.
- Suggested remediation batch ID: `WPORG-19`

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

- One confirmed high-priority blocker.
- The issue is broad enough to justify its own narrow batch.

Evidence count:

- `vms_i18n_runtime()` call sites found in this pass: `54`
- `VMS_TEXTDOMAIN` references found in this pass: `22`

### `J1` Dynamic gettext wrapper and non-literal domain usage break parser compatibility

- Severity: High
- Confidence: Confirmed
- References: `includes/core/registry/admin-menu.php:19-30`; `includes/core/registry/admin-menu.php:81-214`; `includes/core/registry/statuses.php:13-21`; `includes/core/registry/statuses.php:34-70`; `includes/admin-ui/nav.php:781-787`; `includes/tours/tours.php:64`; `includes/modules/staff-tasks/notifications.php:337`; `includes/social-share/queue-runner.php:66`
- Why WordPress.org may object: WordPress.org translation tooling expects literal source strings and literal text domains in gettext-family calls. A wrapper that accepts `$text` and `$domain` at runtime prevents reliable extraction.
- Recommended remediation: convert these wrapper calls back to literal gettext invocations or redesign the wrapper so it does not obscure literal strings from parsers. Do not pass dynamic runtime labels through gettext unless the source strings remain literal at the call site.
- Compatibility or regression risk: Medium because the wrapper is spread across admin registry and status label surfaces.
- Suggested remediation batch ID: `WPORG-18`

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

1. `WPORG-17B — Package-scope trialware/add-ons compliance`
   - Scope: `A1` only
   - Goal: remove or constrain the public premium add-ons / Freemius / installer surface
2. `WPORG-18 - Internationalization parser compliance`
   - Scope: `J1`
   - Goal: replace `vms_i18n_runtime()` call sites and non-literal domain usage
3. `WPORG-19 - Nonce and capability hardening`
   - Scope: `C1`
   - Goal: unslash nonce inputs in state-changing handlers without widening behavior
4. `WPORG-20 - Input sanitization and structured-payload review`
   - Scope: `D1`, `D2`, `D3`, `D4`
   - Goal: remove `FILTER_UNSAFE_RAW`, then validate JSON/body/fingerprint inputs deliberately
5. `WPORG-21 - Upload handling hardening`
   - Scope: `H1`, `H2`, `H3`
   - Goal: align uploads with content-based validation and private-storage justification
6. `WPORG-22 - Inline asset enqueue migration`
   - Scope: `B1`, `B2`, `B3`, `B4`, `B5`
   - Goal: move executable JS/CSS out of inline PHP output
7. `WPORG-23 - Admin notice scope`
   - Scope: `K1`, `K2`
   - Goal: keep notices on VMS-owned screens only
8. `WPORG-24 - Output escaping contract pass`
   - Scope: `E1`
   - Goal: separate genuine escaping defects from safe HTML/JSON patterns
9. `WPORG-25 - Output buffer lifecycle review`
   - Scope: `I1`, `I2`
   - Goal: document and tighten buffer ownership without blind removals
10. `WPORG-26 - Prefix and collision review`
    - Scope: section F only
    - Goal: document why the existing `vms` internal namespace is intentional and compatibility-sensitive
11. `WPORG-27 - Dependency, licensing, and tooling reproducibility verification`
    - Scope: section L and section N
    - Goal: final dependency inventory, disclosure check, and reproducible scanner setup
12. `WPORG-28 - Release metadata and packaging validation`
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
- [ ] Remove dynamic gettext wrapper usage that blocks translation extraction.
- [ ] Normalize nonce verification in legacy save, admin-post, and frontend mutation handlers.
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

- `WPORG-18` — Internationalization parser compliance
- `WPORG-19` — Nonce and capability hardening
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

The only intended codebase change from this task is this documentation file.
