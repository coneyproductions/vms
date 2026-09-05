# VMS WordPress.org Submission Consultation Package — WPORG-15N

Prepared: 2026-06-23
Branch: `work/unreleased-2026-06-18`
Consultation document commit: `9877881e9a3996ec5d38ae5b445e9b2a7d3a86d1`
Release-candidate package source HEAD: `61952f860ac5487bb8f227b8776cb2edb51df7f4`
Package: `dist/wporg-15l/vms-1.0.0-public-release.zip`
Package SHA-256: `e448ddb1f7297d185176e51b50afa8c9a836f0f6707741b2cb5dfc1127b50783`

This document is a consultation and pre-review planning package for the current VMS public-release candidate. It is not a final submission certification, and it does not claim that the package is ready for WordPress.org acceptance without further reviewer guidance or additional hardening.

## 1. Title and purpose

This package is intended for WordPress.org reviewer consultation or internal pre-submission decision-making. Its purpose is to summarize the current release-candidate package, the current Plugin Check state, the remediation already completed, the QA coverage that exists, the debt that remains, and the specific questions that would benefit from reviewer guidance before further submission decisions are made.

## 2. Current release-candidate package

- Package path: `dist/wporg-15l/vms-1.0.0-public-release.zip`
- SHA-256: `e448ddb1f7297d185176e51b50afa8c9a836f0f6707741b2cb5dfc1127b50783`
- Plugin version: `1.0.0`
- Prior package inspections confirmed:
  - Top-level folder is `vms/`
  - No `.git/`
  - No top-level `docs/`
  - No `tests/`
  - No `dist/`
  - No leaked local paths found
  - `release-public-excludes.txt` is absent from the package
  - `vms-build.txt` is present as retained build metadata
- Package polish confirmations:
  - `plugin_header_nonexistent_domain_path=0`
  - `load_plugin_textdomainFound=0`

## 3. Current Plugin Check summary

- Fresh packaged Plugin Check result for the 15L release candidate: `2829 / 847 / 1982`
- Total findings: `2829`
- Errors: `847`
- Warnings: `1982`
- Grouped nonce/input count: `973`
- Files with findings: `151`

These counts remain substantial. They should be treated as a real submission-risk signal rather than background noise. The current question is not whether findings remain, but whether WordPress.org pre-review guidance should be requested before a larger hardening milestone is scheduled.

## 4. Remediation progress summary

| Batch | Total | Errors | Warnings | Nonce/Input |
| --- | ---: | ---: | ---: | ---: |
| 15A baseline | 2956 | 865 | 2091 | 1082 |
| 15B after | 2944 | 865 | 2079 | 1070 |
| 15C after | 2933 | 861 | 2072 | 1063 |
| 15D after | 2911 | 859 | 2052 | 1043 |
| 15E after | 2876 | 858 | 2018 | 1009 |
| 15G after | 2861 | 856 | 2005 | 996 |
| 15I after | 2858 | 853 | 2005 | 996 |
| 15K after | 2856 | 851 | 2005 | 996 |
| 15L after | 2829 | 847 | 1982 | 973 |

Net reduction from 15A to 15L:

- Total findings: `-127`
- Errors: `-18`
- Warnings: `-109`
- Grouped nonce/input: `-109`

Completed public-release prep work focused on low-risk, behavior-preserving cleanup:

- Metadata and i18n package polish
- Readme Turnstile disclosure clarification
- Package-root polish
- Admin-only nonce/input normalization
- Safe final-output escaping
- Missing translator comments
- Staff Tasks display and i18n-only partial cleanup
- No intentional runtime behavior changes

The trend is positive, but the remaining debt is still concentrated in larger coupled files and runtime-heavy paths.

## 5. Manual QA summary

Passed in prior QA passes:

- Exact zip reinstall
- Deactivate/reactivate smoke
- Major VMS admin screens HTTP 200
- Public screens HTTP 200
- Vendor Application Turnstile-enabled render
- Vendor Application local E2E submission smoke
- Logged-out and non-linked portal states
- Optional dependency inactive/active smoke for WooCommerce, The Events Calendar, Event Tickets, and Event Tickets Plus
- No new VMS fatals or uncaught exceptions observed in recent debug log tails

Not run or not fully re-run after the latest cleanup:

- Turnstile-disabled frontend mode
- Completed checkout or real order flow
- Event Plan save
- Linked vendor and staff portal users
- Cancellation and staff-notification execution paths
- Full Staff Tasks mutation/save/assignment/generation smoke after 15L
- Final post-15L manual QA smoke

Manual QA has improved confidence in packaging and basic runtime stability, but it has not certified the full plugin surface.

## 6. External service / Turnstile disclosure

VMS uses Cloudflare Turnstile for vendor application spam protection when that feature is configured. In the current implementation:

- The client script is loaded on the vendor application form only when Turnstile is enabled and a site key is configured.
- Server-side verification sends the Turnstile token and visitor IP to Cloudflare.
- The readme has been clarified to disclose the external service behavior and privacy implications.
- Plugin Check still reports `PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent` x1 in `includes/vendor-applications.php`.

This is the clearest specific pre-review question in the current package. The present package does not assume that this usage is automatically acceptable; it should be treated as a reviewer consultation point.

## 7. Remaining technical debt by category

Errors still present:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `513`
  - Large i18n and translation-polish backlog. High volume, but generally safer to address than behavioral logic changes.
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `137`
  - Likely reviewer-facing security concern because it touches final output handling.
- `WordPress.DB.PreparedSQL.NotPrepared`: `67`
  - DB-layer concern that can overlap with real query-safety review.
- `WordPress.DateTime.RestrictedFunctions.date_date`: `25`
  - Lower-level standards debt, but still a rule violation spread across multiple runtime files.
- `WordPress.WP.I18n.UnorderedPlaceholdersText`: `16`
  - Translation quality debt that is smaller in count but still visible in review.
- File-operation and forbidden-function errors remain:
  - `file_system_operations_fclose`: `10`
  - `unlink_unlink`: `4`
  - `is_writable`: `3`
  - `Generic.PHP.ForbiddenFunctions.Found`: `3`
  - `readfile`: `3`
  - `chmod`: `2`
  - `fopen`: `2`
  - `rename`: `1`
  - These are likely pre-submission blocker candidates because they touch filesystem and restricted-function policy.
- Turnstile remote-script/offloaded-content: `1`
  - Specific external-service review concern rather than bulk standards debt.

Warnings still present:

- `WordPress.Security.NonceVerification.Recommended`: `414`
  - Broad mutation/read-path review signal with likely reviewer-facing security implications.
- `WordPress.Security.NonceVerification.Missing`: `94`
  - More serious nonce-related warning backlog in mixed admin and public request handling.
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`: `239`
  - Input-handling concern across many files and request surfaces.
- `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`: `207`
  - Input-normalization debt, often coupled to save handlers and request parsing.
- `WordPress.Security.ValidatedSanitizedInput.InputNotValidated`: `19`
  - Smaller count, but still tied to request integrity.
- `WordPress.DB.DirectDatabaseQuery.DirectQuery`: `296`
  - Large DB-layer concern in operational code paths.
- `WordPress.DB.DirectDatabaseQuery.NoCaching`: `258`
  - Advisory and performance debt that also signals direct-query concentration.
- `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`: `136`
  - Query-construction risk that likely needs targeted hardening before submission confidence improves materially.
- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `142`
  - Direct DB parameter handling concern and likely reviewer-facing blocker category.
- Slow-query warnings:
  - `slow_db_query_meta_query`: `94`
  - `slow_db_query_meta_key`: `69`
  - `slow_db_query_meta_value`: `8`
  - These are mainly advisory and performance-oriented, but they reinforce that heavy cleanup work belongs in a broader milestone.
- `WordPress.PHP.DevelopmentFunctions.error_log_error_log`: `40`
  - Debug residue that is easier to clean than coupled runtime logic, but no longer materially changes the broader submission picture.

Current hotspot files confirm that the remaining findings are clustered in large, coupled runtime files rather than isolated admin polish:

- `includes/cpt/event-plans.php`: `241 / 108 / 133 / nonce 121`
- `includes/modules/admissions/pass-claims.php`: `155 / 13 / 142 / 27`
- `includes/core/staffing.php`: `153 / 38 / 115 / 0`
- `includes/integrations/ticketing-verifications.php`: `102 / 40 / 62 / 56`
- `includes/modules/availability-date-dispatch/helpers.php`: `96 / 19 / 77 / 7`
- `includes/integrations/ticketing-rules-v2.php`: `94 / 65 / 29 / 11`
- `includes/modules/staff-tasks/store.php`: `90 / 17 / 73 / 0`
- `includes/vendor-applications.php`: `90 / 15 / 75 / 61`

## 8. Intentionally deferred coupled areas

Broad late-stage cleanup was intentionally deferred in the following files or areas because each one carries meaningful regression risk and lacks enough narrow-scope coverage for safe last-mile edits:

- `includes/cpt/event-plans.php`
  - Dense event-plan admin and runtime behavior with mixed request handling, output, and save logic.
- `includes/modules/admissions/pass-claims.php`
  - Admissions and claim flow logic with direct DB and public-facing behavior.
- `includes/core/staffing.php`
  - Core staffing data and query behavior with broad operational coupling.
- `includes/integrations/ticketing-verifications.php`
  - Ticket verification flow with public/runtime behavior and request-state coupling.
- `includes/modules/availability-date-dispatch/helpers.php`
  - Helper layer tied to dispatch logic and data-layer behavior.
- `includes/integrations/ticketing-rules-v2.php`
  - Runtime ticketing rules and validation logic with meaningful business behavior impact.
- `includes/modules/staff-tasks/store.php`
  - DB and mutation-heavy Staff Tasks storage logic.
- `includes/vendor-applications.php`
  - Public submission path, Turnstile handling, and external-service coupling.
- `includes/modules/admissions/vendor-guest-portal.php`
  - Portal-facing admissions logic with request and output coupling.
- `includes/social-share/queue-repo.php`
  - Queue and data-layer logic with direct query concentration.
- `includes/modules/staff-tasks/admin-ui.php`
  - Remaining residue is mostly tied to mutation/save/state/storage/help-button output rather than isolated display-only cleanup.
- `includes/portal/vendor-portal.php`
  - Public portal request and output path with insufficient regression coverage for late-stage edits.
- `includes/portal/staff-portal.php`
  - Staff portal runtime behavior and output path with similar regression risk.

The deferral decision was practical, not dismissive. Small admin-only batches continued while they were safe and useful, but the remaining work now belongs to a larger hardening effort.

## 9. Optional dependencies and integration-heavy nature

VMS is operationally specialized and integration-heavy. The plugin supports venue and event operations with compatibility paths or optional integrations involving:

- WooCommerce
- The Events Calendar
- Event Tickets
- Event Tickets Plus
- WooCommerce Square
- Cloudflare Turnstile
- Vendor, staff, admissions, and admin operations

High-level optional dependency smoke testing has been performed, but not every optional path has full automated regression coverage. That context matters because many of the remaining findings sit in files that are not isolated utility code; they are coupled to real operational flows, optional integrations, and role-based request handling.

## 10. Reviewer / pre-review questions

1. Is the Cloudflare Turnstile client script acceptable when it is optional, configuration-dependent, disclosed in the readme, and only loaded on the vendor application form?
2. Is there preferred wording or structure for the Turnstile external-service disclosure?
3. Are the remaining high-volume Plugin Check findings automatically disqualifying, or can they be addressed through a staged hardening roadmap after pre-review guidance?
4. Which categories should be prioritized first for review acceptance: output escaping, nonce/input handling, DB/prepared SQL, i18n translator comments, or remote-service handling?
5. Are optional and integration-heavy admin paths reviewed differently when they are guarded by optional dependencies and capability checks?
6. Should VMS defer submission until DB-layer and public-flow findings are materially reduced?

## 11. Suggested next paths

### Path A. Submit consultation / ask for pre-review guidance

Provide this package, disclose the current findings candidly, and request reviewer direction on the Turnstile/offloaded-content question and on which blocker categories must be reduced before formal submission. This is the recommended immediate next step.

### Path B. Pause public submission and begin a larger hardening milestone

Treat the current package as a checkpoint, then plan a broader refactor and regression cycle focused on DB-layer findings, public-flow request handling, output escaping, and restricted-function policy issues.

### Path C. Continue internal cleanup only in isolated low-risk files

Continue small admin-only cleanup batches where they are clearly safe, while acknowledging that this no longer materially changes the main submission risk because the remaining debt is concentrated in coupled runtime files.

## 12. Appendix: current package and validation references

- Branch: `work/unreleased-2026-06-18`
- Consultation document commit: `9877881e9a3996ec5d38ae5b445e9b2a7d3a86d1`
- Release-candidate package source HEAD: `61952f860ac5487bb8f227b8776cb2edb51df7f4`
- Package path: `dist/wporg-15l/vms-1.0.0-public-release.zip`
- SHA-256: `e448ddb1f7297d185176e51b50afa8c9a836f0f6707741b2cb5dfc1127b50783`
- Latest packaged Plugin Check command:

```bash
wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check "/tmp/wporg-15m.pkg.U7DbGW/vms" --slug=vms --mode=new --format=json
```

- Plugin Check stdout path: `/tmp/wporg-15m.plugin-check.stdout.XXXXXX.json`
- Plugin Check stderr path: `/tmp/wporg-15m.plugin-check.stderr.XXXXXX.log`
- Parsed analysis reference: `/tmp/wporg-15m.analysis.json`
- Known unrelated baseline failing test was intentionally not run:

```bash
php tests/add-dispatch-open-vendor-needs.php
```

- The canonical Market Readiness Checklist file remains intentionally unstaged, unrelated to this task, and excluded from this document.
