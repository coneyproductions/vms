# WordPress.org Plugin Check Triage 1.0.0

Date: 2026-06-22

## Scope

- Raw output saved at `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`
- Scan target for current counts: extracted packaged directory from `dist/wporg-12d/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree, leaving the local `vms/` install untouched
- Current artifact SHA-256: `be5008b2dd6afd04d2c1c06eedbe60f931b3e1d25c8acff0801159792abf0384`
- Heatmap companion: `docs/WPORG_PLUGIN_CHECK_HEATMAP_1.0.0.md`
- Event Plans audit companion: `docs/WPORG_EVENT_PLANS_HARDENING_MAP_1.0.0.md`

## Before / After Counts

| Run | Target | Total | Errors | Warnings | Notes |
| --- | --- | ---: | ---: | ---: | --- |
| `WPORG-02` baseline | disposable source-tree copy | `4567` | `1646` | `2921` | Included repo-only files and packaging noise. |
| `WPORG-03` packaged RC, before direct-access guards | installed packaged plugin | `3900` | `1342` | `2558` | Removed repo-only markdown/docs/test noise. |
| `WPORG-03` packaged RC, final | installed packaged plugin | `3888` | `1330` | `2558` | Removed `missing_direct_file_access_protection` entirely. |
| `WPORG-04A` packaged RC, final | installed packaged plugin | `3808` | `1329` | `2479` | Cleared the `goals-forecast` request batch and reduced `event-plan-panel` to four DB warnings. |
| `WPORG-04B` packaged RC, final | installed packaged plugin | `3695` | `1317` | `2378` | Cleared the `budget-calculator` request batch and limited Event Plans to the first nonce-gated admin-list slice. |
| `WPORG-04D` packaged RC, final | installed packaged plugin | `3692` | `1316` | `2376` | Audited Event Plans in depth and applied one protected admin-list helper/output slice only. |
| `WPORG-04E` packaged RC, final | temporary packaged plugin slug | `3605` | `1316` | `2289` | Cleared the safe high-density batch in `includes/admin/due-dates.php` and `includes/admin/holidays.php` outside Event Plans. |
| `WPORG-04G` packaged RC, final | temporary packaged plugin slug | `3554` | `1266` | `2288` | Cleared the safe error-heavy render/i18n/date batch in `includes/admin/vendor-command-center.php` and `includes/admin/vendor-availability.php` without widening into Event Plans runtime or mutation paths. |
| `WPORG-04H` packaged RC, final | temporary packaged plugin slug | `3491` | `1203` | `2288` | Cleared the safe admin-only Event Command Center render/i18n/date batch in `includes/admin/event-command-center.php` without widening into Event Plans runtime or mutation paths. |
| `WPORG-04I` packaged RC, final | temporary packaged plugin slug | `3435` | `1179` | `2256` | Cleared the safe staffing-admin escaping/i18n batch in `includes/admin/staffing.php`, leaving only one role-meta input warning plus the rollup count direct-query/no-caching pair. |
| `WPORG-04J` packaged RC, final | temporary packaged plugin slug | `3408` | `1158` | `2250` | Cleared the safe Staff Portal render/i18n/read-only-query batch in `includes/portal/staff-portal.php` without widening into auth, profile-save, upload, or availability save logic. |
| `WPORG-04K` packaged RC, final | temporary packaged plugin slug | `3319` | `1078` | `2241` | Cleared the safe Vendor Portal render/i18n/read-only-query batch in `includes/portal/vendor-portal.php` without widening into auth, profile-save, upload, availability save, or Event Plans runtime logic. |
| `WPORG-04L` packaged RC, final | temporary packaged plugin slug | `3290` | `1061` | `2229` | Cleared the safe public calendar render/read-only-filter batch in `includes/public/venue-calendar-shortcode.php` without widening into portal mutation paths, query logic changes, or Event Plans runtime logic. |
| `WPORG-04M` packaged RC, final | temporary packaged plugin slug | `3278` | `1049` | `2229` | Cleared the safe public vendor profiles render/i18n batch in `includes/public/vendor-profiles.php` without widening into query logic changes, portal mutation paths, or Event Plans runtime logic. |
| `WPORG-04N` packaged RC, final | temporary packaged plugin slug | `3274` | `1045` | `2229` | Cleared the safe public vendor profile template render batch in `includes/public/templates/vendor-profile.php` without widening into request handling, query logic changes, or Event Plans runtime logic. |
| `WPORG-04O` packaged RC, final | temporary packaged plugin slug | `3270` | `1045` | `2225` | Cleared the safe social template-engine read-only SQL batch in `includes/social-share/template-engine.php` without widening into query intent changes, social queue/posting mutations, or Event Plans runtime logic. |
| `WPORG-04P` packaged RC, final | temporary packaged plugin slug | `3268` | `1043` | `2225` | Cleared the safe social audit read-only SQL error batch in `includes/social-share/audit.php` without widening into audit writes, social queue/posting mutations, notification behavior, or Event Plans runtime logic. |
| `WPORG-04Q` packaged RC, final | extracted packaged directory outside local site tree | `3255` | `1031` | `2224` | Cleared the safe lineup-schedule translator-comment batch in `includes/core/lineup-schedule.php`; the rerun also stopped emitting one pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope. |
| `WPORG-04R` packaged RC, final | extracted packaged directory outside local site tree | `3224` | `999` | `2225` | Cleared the safe vendor-user-links translator-comment batch in `includes/core/vendor-user-links.php`; the rerun also reintroduced the previously observed `plugin_header_nonexistent_domain_path` warning outside the selected file scope. |
| `WPORG-04S` packaged RC, final | extracted packaged directory outside local site tree | `3205` | `980` | `2225` | Cleared the safe event-plan-review translator-comment batch in `includes/core/event-plan-review.php`; the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope without introducing any new Plugin Check code categories. |
| `WPORG-04T` packaged RC, final | extracted packaged directory outside local site tree | `3175` | `950` | `2225` | Cleared the safe admin-schedule render/date hotspot batch in `includes/admin/schedule.php`; the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope without introducing any new Plugin Check code categories. |
| `WPORG-04U` packaged RC, final | extracted packaged directory outside local site tree | `3170` | `945` | `2225` | Cleared the safe staff-list-columns render/i18n hotspot batch in `includes/admin/staff-list-columns.php`; the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope without introducing any new Plugin Check code categories. |
| `WPORG-04V` packaged RC, final | extracted packaged directory outside local site tree | `3163` | `938` | `2225` | Cleared the medium-risk approvals-review-queue render/i18n hotspot batch in `includes/admin/approvals-review-queue.php`; the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope without introducing any new Plugin Check code categories. |
| `WPORG-04W` packaged RC, final | extracted packaged directory outside local site tree | `3158` | `933` | `2225` | Cleared the admin UI dashboard render/i18n hotspot batch in `includes/admin/menu.php`; the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope without introducing any new Plugin Check code categories. |
| `WPORG-04X` packaged RC, final | extracted packaged directory outside local site tree | `3150` | `925` | `2225` | Cleared the vendor alert translator-comment hotspot batch in `includes/core/vendor-document-alerts.php`; the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` and `load_plugin_textdomainFound` warnings outside the selected file scope without introducing any new Plugin Check code categories. |
| `WPORG-04Y` packaged RC, final | extracted packaged directory outside local site tree | `3147` | `922` | `2225` | Cleared the final isolated-safe translator-comment hotspot batch in `includes/admin/cancelled-event-cost-review.php`; the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` and `load_plugin_textdomainFound` warnings outside the selected file scope without introducing any new Plugin Check code categories. |
| `WPORG-05A` packaged RC, final | extracted packaged directory outside local site tree | `3124` | `922` | `2202` | Cleared the read-only admin availability nonce/input hotspot batch in `includes/admin/vendor-availability.php`; the rerun removed the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope while leaving the standing `load_plugin_textdomainFound` warning unchanged and introducing no new Plugin Check code categories. |
| `WPORG-05B` packaged RC, final | extracted packaged directory outside local site tree | `3108` | `922` | `2186` | Cleared the read-only vendor-list admin-filter nonce/input hotspot batch in `includes/admin/vendor-list-ui.php`; the rerun reintroduced the previously seen `plugin_header_nonexistent_domain_path` warning, cleared one unrelated `slow_db_query_meta_key` warning in `includes/helpers/checkin-close.php`, left the standing `load_plugin_textdomainFound` warning unchanged, and introduced no previously unseen Plugin Check code categories. |
| `WPORG-05C` packaged RC, final | extracted packaged directory outside local site tree | `3103` | `922` | `2181` | Cleared the read-only event-profitability report nonce/input hotspot batch in `includes/admin/event-profitability-report.php`; the rerun preserved the standing `plugin_header_nonexistent_domain_path` and `load_plugin_textdomainFound` warnings, reintroduced one unrelated `slow_db_query_meta_key` warning in `includes/helpers/checkin-close.php`, and introduced no previously unseen Plugin Check code categories. |
| `WPORG-05D` packaged RC, final | extracted packaged directory outside local site tree | `3098` | `922` | `2176` | Cleared the read-only docs-page nonce/input hotspot batch in `includes/admin/docs-page.php`; the rerun preserved the standing `plugin_header_nonexistent_domain_path`, `includes/helpers/checkin-close.php`, and `load_plugin_textdomainFound` warnings outside the selected file scope and introduced no previously unseen Plugin Check code categories. |
| `WPORG-05E` packaged RC, final | extracted packaged directory outside local site tree | `3092` | `922` | `2170` | Cleared the final low-risk shared admin routing helper in `includes/admin-ui/context.php`; the rerun preserved the standing `plugin_header_nonexistent_domain_path`, `includes/helpers/checkin-close.php`, and `load_plugin_textdomainFound` warnings outside the selected file scope and introduced no previously unseen Plugin Check code categories. |
| `WPORG-06A` packaged RC, final | extracted packaged directory outside local site tree | `3082` | `913` | `2169` | Cleared the first safe settings-page escaping/output hotspot batch in `includes/admin/settings-page.php`; the rerun no longer emitted the previously standing `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, and left the standing `load_plugin_textdomainFound` warning unchanged. |
| `WPORG-06B` packaged RC, final | extracted packaged directory outside local site tree | `3079` | `909` | `2170` | Cleared the second safe escaping/output hotspot batch in `includes/admin/vendor-list-ui.php`; the rerun reintroduced the previously observed `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomainFound` warning unchanged, and introduced no previously unseen Plugin Check code categories. |
| `WPORG-06C` packaged RC, final | extracted packaged directory outside local site tree | `3076` | `906` | `2170` | Cleared the third safe escaping/output hotspot batch in `includes/admin/vendor-list-columns.php`; the rerun no longer emitted the previously observed `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomainFound` warning unchanged, and introduced no previously unseen Plugin Check code categories. |
| `WPORG-07A` packaged RC, final | extracted packaged directory outside local site tree | `3069` | `906` | `2163` | Cleared the first low-risk DB/SQL triage batch in `includes/core/goals-forecast.php`; the rerun again dropped the previously oscillating `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomainFound` warning unchanged, and introduced no previously unseen Plugin Check code categories. |
| `WPORG-07B` packaged RC, final | extracted packaged directory outside local site tree | `3061` | `898` | `2163` | Cleared the second low-risk DB/SQL batch in the admin-only report helpers inside `includes/modules/admissions/pass-claims.php`; the rerun reintroduced the previously observed `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomainFound` warning unchanged, and introduced no previously unseen Plugin Check code categories. |
| `WPORG-08A` packaged RC, final | extracted packaged directory outside local site tree | `3041` | `877` | `2164` | Cleared the first cautious i18n placeholder/comment batch in `includes/admin/ticket-integrity-page.php`; the normalized rerun left `plugin_header_nonexistent_domain_path`, `includes/helpers/checkin-close.php`, and `load_plugin_textdomainFound` unchanged outside the selected file scope and introduced no previously unseen Plugin Check code categories. |
| `WPORG-08B` packaged RC, final | extracted packaged directory outside local site tree | `3033` | `869` | `2164` | Cleared the second cautious i18n placeholder/comment batch in `includes/admin/settings-page.php`; the normalized rerun re-associated the standing `plugin_header_nonexistent_domain_path` warning to `vendor-management-system.php`, left `includes/helpers/checkin-close.php` and `load_plugin_textdomainFound` unchanged outside the selected file scope, and introduced no previously unseen Plugin Check code categories. |
| `WPORG-09A` packaged RC, final | extracted packaged directory outside local site tree | `3030` | `867` | `2163` | Cleared the first cautious date/time display-only batch in `includes/admin/settings-page.php`; the normalized rerun dropped the previously oscillating `plugin_header_nonexistent_domain_path` warning, left `includes/helpers/checkin-close.php` and `load_plugin_textdomainFound` unchanged outside the selected file scope, and introduced no previously unseen Plugin Check code categories. |
| `WPORG-10A` packaged RC, final | extracted packaged directory outside local site tree | `3029` | `867` | `2162` | Cleared the first cautious logging dev-trace batch in `includes/core/plugin.php`; the normalized rerun reintroduced the previously oscillating `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` and `load_plugin_textdomainFound` unchanged, and introduced no previously unseen Plugin Check code categories. |
| `WPORG-11A` packaged RC, final | extracted packaged directory outside local site tree | `3019` | `865` | `2154` | Cleared the isolated pass-claims DB/SQL reporting batch in `includes/modules/admissions/pass-claims.php`; the normalized rerun reduced SQL-preparation/security findings without adding new Plugin Check code categories. |
| `WPORG-12A` packaged RC, planning only | extracted packaged directory outside local site tree | `3019` | `865` | `2154` | Docs-only nonce/input mutation roadmap; no package rebuild or rerun because the WP.org tracking docs are excluded from the public ZIP. |
| `WPORG-12B` packaged RC, final | extracted packaged directory outside local site tree | `3001` | `865` | `2136` | Cleared the first bounded admin-only nonce/input mutation batch in `includes/admin/settings-page.php`; the normalized rerun removed eighteen selected-file nonce/input warnings without adding new Plugin Check code categories. |
| `WPORG-12C` packaged RC, final | extracted packaged directory outside local site tree | `2997` | `865` | `2132` | Cleared the bounded status-notices nonce/input mutation batch in `includes/modules/status-notices/admin-ui.php`; the normalized rerun removed the remaining selected-file input/unslash warnings without adding new Plugin Check code categories. |
| `WPORG-12D` packaged RC, final | extracted packaged directory outside local site tree | `2985` | `865` | `2120` | Cleared the bounded ticket-integrity nonce/input mutation batch in `includes/admin/ticket-integrity-page.php`; the normalized rerun removed the selected-file input/unslash warnings plus four duplicate read-only `Recommended` hits without adding new Plugin Check code categories. |

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-1548` total findings
- `-781` errors
- `-767` warnings

Net reduction from `WPORG-11A`:

- `-34` total findings
- `0` errors
- `-34` warnings

Net reduction from `WPORG-12B`:

- `-16` total findings
- `0` errors
- `-16` warnings

Net reduction from `WPORG-12C`:

- `-12` total findings
- `0` errors
- `-12` warnings

Net reduction from `WPORG-10A`:

- `-28` total findings
- `-2` errors
- `-26` warnings

## Fixed In This Pass

- 12D selected mutation-flow batch
  - applied bounded request normalization in `includes/admin/ticket-integrity-page.php`
  - kept the mutation scope inside the existing settings save and admin test-email handlers, then normalized the same-page admin notice/filter query round-trips those handlers rely on
  - retained the existing `manage_options` and `check_admin_referer()` checks and matched the existing downstream email/key sanitizers so saved values, report output, redirects, and test-send behavior stayed unchanged
  - intentionally deferred scan/rebuild/duplicate-cleanup/export branch changes and the remaining read-only query `Recommended` residue because they have no matching nonce flow in the current UI
- selected-file outcome
  - `includes/admin/ticket-integrity-page.php`: `27` -> `14`
  - `7` -> `7` errors
  - `20` -> `7` warnings
  - nonce/input subset `20` -> `7`
  - removed `WordPress.Security.ValidatedSanitizedInput.MissingUnslash x6`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized x3`, and `WordPress.Security.NonceVerification.Recommended x4` from the selected file
  - remaining nonce/input findings in the selected file are `WordPress.Security.NonceVerification.Recommended x7` on read-only admin query/filter/notice/page reads
- exact request reads changed
  - admin page gate `$_GET['page']` now normalizes through `sanitize_key( wp_unslash( ... ) )`
  - settings handler `$_POST['alert_recipient']` and `$_POST['daily_report_recipient']` now normalize through `sanitize_email( wp_unslash( ... ) )`
  - settings handler `$_POST['payment_gateway_health_interval']` now normalizes through `sanitize_key( wp_unslash( ... ) )`
  - admin test-email handler `$_POST['test_recipient']` now normalizes through `sanitize_email( wp_unslash( ... ) )`
  - same-page notice/filter query reads `tim_notice`, `detail`, `red`, `yellow`, `recipient`, and `event` now normalize through the same single-access `wp_unslash()` plus existing `sanitize_key()`, `sanitize_text_field()`, `sanitize_email()`, and `absint()` patterns
- existing guards relied on
  - settings save: `current_user_can( 'manage_options' )` plus `check_admin_referer( 'vms_ticket_integrity_save_settings' )`
  - admin test email: `current_user_can( 'manage_options' )` plus `check_admin_referer( 'vms_ticket_integrity_send_daily_report_test' )`
- branches deferred from this batch
  - manual scan, event scan, rebuild, duplicate-cleanup, and report-export request reads
  - reason: those branches already sit behind capability/nonces, but they touch scan, repair, cleanup, or export behavior more directly and were not needed to clear the current packaged input/unslash residue
  - remaining read-only page/query/notice hits
  - reason: the remaining `Recommended` warnings have no matching nonce flow in the current UI, so adding nonce checks here would widen behavior rather than just normalize guarded mutation inputs
- manual QA checklist for the selected slice
  - menu/location: `wp-admin/admin.php?page=vms-ticket-integrity`
  - form/action tested: save Ticket Integrity settings via the existing `admin_post_vms_ticket_integrity_save_settings` flow
  - form/action tested: send the State of the Range admin test email via the existing `admin_post_vms_ticket_integrity_send_daily_report_test` flow
  - query behavior tested: same-page admin notice banners after each successful action and the `?event=<plan_id>` event filter on the same screen
  - fields/actions affected: `alert_recipient`, `daily_report_recipient`, `payment_gateway_health_interval`, `test_recipient`, and the read-only `page`, `tim_notice`, `detail`, `red`, `yellow`, `recipient`, and `event` query reads
  - nonce/capability expectation: both selected mutation handlers should continue to require `manage_options` plus their existing matching nonce
  - redirect/admin notice expectation: equivalent successful actions should keep the same redirect targets, notice copy, event filtering, and email-recipient fallback behavior as before
  - negative case: submit a saved action URL with a missing or invalid nonce and confirm WordPress rejects it without saving settings or sending the admin test email
  - equivalence expectation: for equivalent user input, saved values, admin test recipient fallback, and ticket-integrity screen results should remain unchanged before vs. after this batch
- packaged outcome
  - overall packaged totals: `2997` -> `2985`
  - overall packaged warnings: `2132` -> `2120`
  - overall packaged errors: unchanged at `865`
  - overall nonce/input surface: `1123` -> `1110`
  - normalized rerun side effects outside selected file scope: none observed; `plugin_header_nonexistent_domain_path` remained absent and `load_plugin_textdomainFound` remained steady at `1`
- validation for this pass
  - `php -l includes/admin/ticket-integrity-page.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-12d --force --allow-dirty` passed
  - packaged Plugin Check reran against `/tmp/vms-wporg-12d.FjfAIt/vms` outside the local site tree, leaving the local `vms/` install untouched
  - no focused ticket-integrity admin-handler regression exists in `tests/`

No previously unseen Plugin Check codes appeared in this pass.

## Current Category Triage

- Fresh packaged coordinated-Wave-1 baseline from `2026-08-07`: `175` errors, `345` warnings, `520` total findings, `14` unique rule codes, `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=125`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=395`.
- Durable machine-readable counts and provenance are in `docs/wporg-current-scan-state.json`; normalized strict JSON is `/tmp/wporg-wave1-integrated.TBbwkn/plugin-check/plugin-check.final.strict.json`.
- The packaged scan reports `privateincludes/...` paths. The roadmap below uses the corresponding mirror `includes/...` ownership files that future implementation children must edit.
- Coordinated Wave 1 removed the exact `21` Phase B, `36` vendor-user-link, and `73` social-queue DB/SQL rows, while excluding the dormant `includes/safety/` prototype removed another `4` DB/SQL and `4` mapped nonblocking output rows from the public package. No unrelated file/code count increased.

| Category | Current packaged count | Representative files | Current classification | Current strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `0` | closed by `WPORG-28R-G1` through `WPORG-28R-G6-T5` | `verified` | No packaged nonce/input blocker rows remain; keep `WPORG-28R-G7` retired unless a future strict-JSON rerun creates newly unmapped nonce/input rows. |
| Database and SQL safety | `328` | `includes/integrations/ticketing-rules-v2.php`, `includes/core/vendor-application-confirmation.php`, `includes/core/goals-forecast.php`, `includes/core/registry/vendor-schema.php` | `SUBMISSION_BLOCKER` | Continue the independent remaining `G10` through `G13` repositories; Phase B, vendor-user-links, and social queue are now scanner-zero for DB/SQL. |
| Date/time API usage | `25` | `includes/modules/staff-tasks/notifications.php`, `includes/ticketing/ticket-integrity-monitor.php`, `includes/helpers.php` | `SUBMISSION_BLOCKER` | Execute `WPORG-28R-G14` and `WPORG-28R-G15` after the earlier request and query boundaries they depend on. |
| Development logging | `42` | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php`, `includes/modules/staff-tasks/generator.php` | `SUBMISSION_BLOCKER` | Execute `WPORG-28R-G16` and `WPORG-28R-G17` after the earlier lifecycle work clarifies which logs remain operational, which are dev traces, and which can be safely gated or removed. |
| Escaping and output safety | `123` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` | `KNOWN_NONBLOCKING` | Keep paused. The current packaged `OutputNotEscaped` family is already mapped to accepted `WPORG-24` / `WPORG-24R` boundaries and is not part of the `395` submission blockers; the four-row decrease is solely the excluded dormant Safety prototype. |

## Residual Submission-Blocker Roadmap

- `WPORG-28R-G0` now owns the complete residual blocker decomposition. Every current blocker row belongs to exactly one implementation child below.
- Remaining family sums reconcile exactly: `G10 95 + G11 67 + G12 38 + G13 128 = 328`, `G14-G15 = 25`, `G16-G17 = 42`, grand total `395`. The nonce/input family is closed at `0`.
- Every implementation child still reruns `php tests/release-compatibility-harness.php`, `php tests/public-release-build-pipeline.php`, and `php tests/runtime-stub-guards.php` in addition to the focused suites listed below.
- Every implementation child closes only its owned packaged rows; unrelated families must remain count-for-count unchanged except for documented same-file line movement.

### Nonce/Input Children

#### `WPORG-28R-G1 — Admin Module Form Boundaries`

- Status: `verified` on `2026-07-27`
- Pre-edit count: `59` (`Recommended 38`, `InputNotSanitized 15`, `MissingUnslash 6`); current remaining count: `0`
- Files: `includes/modules/staff-tasks/admin-ui.php`, `includes/modules/email-followups/admin-ui.php`, `includes/admin/event-feedback.php`, `includes/admin/season-dates.php`
- Lifecycle / subsystem: `ADMIN_PAGE`, `ADMIN_POST`, `FORM_SUBMISSION`; Staff Tasks, Email Follow-Ups, Event Feedback, Season Dates
- Boundary: retained the existing capability and nonce gates on mutation paths; moved read-only GET page state and redirect notices behind narrow unslashing helpers; preserved the exact literal `REQUEST_METHOD === 'POST'` boundaries required by the focused harnesses; tightened semantic unslashing and allowlisting for Staff Tasks fields plus Email Follow-Ups settings and recipient payloads without adding blanket nonces to GET-only controls
- Focused tests: `tests/strict-post-gate-remediation.php`, `tests/admin-request-method-wrapper-remediation.php`, `tests/administrator-explicit-notice-output-remediation.php`, `tests/authorization-boundary-hardening.php`
- Closure / defers: owned packaged input rows `59 -> 0`; the only post-edit added strict-json rows were same-file line shifts for already-deferred DB and accepted `OutputNotEscaped` findings, with all related counts unchanged; same-file DB rows still defer to `G8` / `G13`, and accepted `OutputNotEscaped` rows stay outside the blocker roadmap

#### `WPORG-28R-G2 — Admin Dashboard And Secondary Settings Boundaries`

- Status: `verified` on `2026-07-27`
- Pre-edit count: `140` (`Missing 10`, `Recommended 76`, `InputNotSanitized 28`, `MissingUnslash 26`); current remaining count: `0`
- Files: `includes/safety/admin.php`, `includes/admin/schedule.php`, `includes/admin/vendor-comp-packages.php`, `includes/admin/settings-page.php`, `includes/admin/integrity-calendar-reconcile.php`, `includes/admin/integrity-venue-reconcile.php`, `includes/admin/continuity-binder.php`, `includes/admin/vendor-details.php`, `includes/admin/venue-context.php`, `includes/admin/staff-tax-sidebar.php`, `includes/admin/venue-calendar.php`, `includes/admin/vendor-booking-onboarding.php`, `includes/admin/approvals-review-queue.php`, `includes/admin/menu.php`, `includes/admin/reference/keys-map.php`, `includes/admin/vendor-user-link.php`, `includes/admin/venue-comp-defaults.php`, `includes/admin/tax-bypass-ajax.php`, `includes/admin/settings/notifications-user-profile.php`, `includes/admin/settings/class-vms-settings-tours.php`, `includes/admin/staffing.php`, `includes/admin/vendor-command-center.php`, `includes/admin/express-bar.php`, `includes/admin/tax-profile-admin-metabox.php`, `includes/admin/vendor-staff-link.php`, `includes/admin/square-sync-protection.php`
- Lifecycle / subsystem: `ADMIN_DASHBOARD`, `ADMIN_SETTINGS`, `ADMIN_PAGE`, `ADMIN_POST`, `ADMIN_AJAX`, `NOTICE_STATE`, `REDIRECT_STATE`; mixed dashboard filters, secondary settings, review queues, user-profile saves, venue/staff routing, and vendor/staff side tools
- Boundary: helper-backed read-only GET state now covers dashboard filters, notices, view tabs, selected IDs, and redirect state without adding inappropriate nonces; existing mutation capability, nonce, and WordPress lifecycle verification boundaries stayed in place for user-profile, AJAX, admin-post, settings, and queue actions; structured settings arrays and raw `$_FILES` payloads remain occurrence-specific retained boundaries with narrow suppressions where the downstream sanitizers or upload handlers already own normalization
- Focused tests: `tests/authorization-boundary-hardening.php`, `tests/strict-post-gate-remediation.php`, `tests/reference-keys-map-inline-js-remediation.php`, `tests/vendor-compensation-inline-js-remediation.php`, `tests/administrator-explicit-notice-output-remediation.php`, `tests/settings-integrity-scan-output-remediation.php`, `tests/settings-default-venue-alert-output-remediation.php`, `tests/schedule-invalid-bounds-output-remediation.php`, `tests/schedule-warning-notice-output-remediation.php`, `tests/schedule-unpublished-venue-notice-output-remediation.php`, `tests/staffing-admin-inline-assets-remediation.php`, `tests/admin-selector-redirect-uri-remediation.php`, and `tests/private-file-upload-api-remediation.php`
- Closure / defers: owned packaged input rows `140 -> 0`; global code deltas were only `Missing 85 -> 75 (-10)`, `Recommended 334 -> 258 (-76)`, `InputNotSanitized 119 -> 91 (-28)`, and `MissingUnslash 94 -> 68 (-26)`; same-file deferred DB/date/logging rows in the authorized G2 files stayed exactly `20`, and same-file accepted `OutputNotEscaped` rows stayed exactly `20`

#### `WPORG-28R-G3 — Event Plan Editor And Core Request Boundaries`

- Status: `verified` on `2026-07-28`
- Pre-edit count: `164` (`Missing 23`, `Recommended 76`, `InputNotSanitized 30`, `InputNotValidated 3`, `MissingUnslash 32`); current remaining count: `0`
- Files: `includes/cpt/event-plans.php`, `includes/core/event-plan-performance.php`, `includes/core/event-plan-save-profiler.php`, `includes/core/event-plan-review.php`, `includes/cpt/event-plans/partials/workflow-status.php`
- Lifecycle / subsystem: `CPT_SAVE_HANDLER`, `ADMIN_PAGE`, `REPORTING`; Event Plan editor, save path, save profiler, review and workflow helpers
- Boundary: keep mutation, autosave, preview, and profiling paths distinct; preserve any raw values needed for signatures, IDs, or JSON payloads only where the current boundary proves that retention is intentional
- Focused tests: `tests/event-plan-legacy-ticketing-integration-smoke.php`, `tests/event-plan-performance-request-id-remediation.php`, `tests/event-plan-readiness-details-output-remediation.php`, `tests/decoded-json-validation.php`, and `tests/event-plan-review-json-characterization.php`
- Closure / defers: owned packaged input rows `164 -> 0`; global code deltas were only `Missing 75 -> 52 (-23)`, `Recommended 258 -> 182 (-76)`, `InputNotSanitized 91 -> 61 (-30)`, `InputNotValidated 3 -> 0 (-3)`, and `MissingUnslash 68 -> 36 (-32)`; same-file deferred DB/logging rows in the authorized G3 files stayed exactly `15`, same-file accepted `OutputNotEscaped` rows stayed exactly `48`, and `InputNotValidated` disappeared from the packaged rule-code set entirely

#### `WPORG-28R-G4 — Ancillary CPT Save Boundaries`

- Status: `verified` on `2026-07-28`
- Pre-edit count: `39` (`Missing 14`, `Recommended 10`, `InputNotSanitized 7`, `MissingUnslash 8`); current remaining count: `0`
- Files: `includes/cpt/venues.php`, `includes/cpt/ratings.php`, `includes/cpt/vendors.php`, `includes/cpt/staff.php`
- Lifecycle / subsystem: `CPT_SAVE_HANDLER`; Venue, Ratings, Vendor, and Staff editors
- Boundary: keep save-handler authorization and nonce proof separate from read-only admin filters; normalize scalar IDs, arrays, and unslashing at the save edge rather than deeper in persistence helpers
- Focused tests: `tests/staff-cpt-inline-js-remediation.php`, `tests/authorization-boundary-hardening.php`, `tests/vendor-tax-profile-strict-post-remediation.php`, and `tests/request-input-sanitization.php`
- Closure / defers: owned packaged input rows `39 -> 0`; global code deltas were only `Missing 52 -> 38 (-14)`, `Recommended 182 -> 172 (-10)`, `InputNotSanitized 61 -> 54 (-7)`, and `MissingUnslash 36 -> 28 (-8)`; same-file deferred DB rows in `includes/cpt/vendors.php` and `includes/cpt/ratings.php` stayed exactly `4`, and the same-file accepted `WordPress.Security.EscapeOutput.OutputNotEscaped` rows in `includes/cpt/venues.php` and `includes/cpt/vendors.php` stayed exactly `2`.<br>Follow-up `WPORG-28R-G4-T1` then aligned the obsolete Staff CPT PHP mirror/live byte-identity assertion so the focused harness now proves the untouched live hash plus the expected mirror-only G4 divergence while keeping the Staff JS asset parity check exact.

#### `WPORG-28R-G5 — Public, Portal, And Vendor-Application Request Boundaries`

- Status: `verified` on `2026-07-29`
- Pre-edit count: `103` (`Missing 12`, `Recommended 46`, `InputNotSanitized 29`, `MissingUnslash 16`); current remaining count: `0`
- Files: `includes/portal/vendor-portal.php`, `includes/public/event-feedback.php`, `includes/vendor-applications.php`, `includes/portal/staff-portal.php`, `includes/portal/vendor-tax-profile.php`, `includes/public/express-bar.php`, `includes/public/calendar-ics.php`, `includes/modules/status-notices/front.php`, `includes/core/vendor-application-confirmation.php`
- Lifecycle / subsystem: `FRONTEND_RENDER`, `FORM_SUBMISSION`, `FRONTEND_AJAX`; vendor applications, vendor/staff portal flows, public feedback, and status-notice reads
- Boundary: kept public read-only URL state, portal tab/filter routing, confirmation lookups, and notice state behind helper-backed GET reads without adding blanket nonces; preserved the existing portal, upload, confirmation, redirect, and vendor-application mutation nonce sequence while narrowing request sources to `$_GET`, `$_POST`, and `$_FILES` only where the lifecycle was already method-specific; retained the established Turnstile, upload-validation, and structured-array boundaries without widening into DB, date, or logging work
- Focused tests: `tests/vendor-portal-availability-autosave-remediation.php`, `tests/vendor-tax-profile-strict-post-remediation.php`, `tests/vendor-apply-turnstile-contract-remediation.php`, `tests/event-feedback-request-hash-characterization.php`
- Closure / defers: owned packaged input rows `103 -> 0`; the only global code deltas were `Missing 38 -> 26 (-12)`, `Recommended 172 -> 126 (-46)`, `InputNotSanitized 54 -> 25 (-29)`, and `MissingUnslash 28 -> 12 (-16)`; same-file deferred DB rows in the G5 runtime file set stayed exactly `61`, same-file date rows stayed exactly `1`, same-file operational logging rows stayed exactly `10`, same-file accepted `WordPress.Security.EscapeOutput.OutputNotEscaped` rows stayed exactly `4`, and the same-file `PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent` row stayed exactly `1`

#### `WPORG-28R-G6 — Ticketing, Admissions, And Availability Request Boundaries`

- Status: `verified` on `2026-08-01` after verified children `WPORG-28R-G6-T1`, `WPORG-28R-G6-T2`, `WPORG-28R-G6-T3`, `WPORG-28R-G6-T4`, and `WPORG-28R-G6-T5`
- Original pre-edit count: `147` (`Missing 22`, `Recommended 94`, `InputNotSanitized 19`, `MissingUnslash 12`); current remaining count: `0`
- Files: `includes/admin/data-tools/actions-event-plan-import.php`, `includes/integrations/ticketing-phase-b.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/runtime-guards.php`, `includes/social-share/admin.php`, `includes/taxonomies/vendor-category.php`, `includes/ticketing/ticket-integrity-cron.php`, `includes/tours/class-vms-tours-service.php`
- Lifecycle / subsystem: `ADMIN_PAGE`, `ADMIN_POST`, `FRONTEND_AJAX`, `REST`, `FORM_SUBMISSION`; admissions claims, ticketing admin/support flows, ticketing AJAX, and availability dispatch
- Boundary: sequence request provenance fixes ahead of later SQL rewrites; preserve any raw payloads required for JSON bodies, signatures, or third-party cart semantics only where the current boundary proves that necessity
- Focused tests: `tests/ticket-claims-assignee-validation.php`, `tests/ticketing-v2-ajax-output-buffer-ownership.php`, `tests/public-calendar-user-agent-view-characterization.php`, `tests/pass-claims-public-form-output-remediation.php`
- Closure / defers: `WPORG-28R-G6-T1` removed the first `66` owned rows exactly, `WPORG-28R-G6-T2` removed the next `33`, `WPORG-28R-G6-T3` removed the next `34`, `WPORG-28R-G6-T4` removed the next `34`, and `WPORG-28R-G6-T5` removed the final `22`, reconciling the whole `189`-row / `122`-boundary G6 nonce/input family to zero. Same-file DB/date/logging rows still defer to `G9` / `G10`, `G15`, and `G16`.

##### `WPORG-28R-G6-T1 — Normalize Ticketing Claims And Verification Request State`

- Status: `verified` on `2026-07-30`
- Owned packaged rows: `66 -> 0` across `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-claims-customer.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, and `includes/integrations/ticketing.php`
- Distinct boundaries: `46`
- Exact rule distribution: `Recommended 64`, `InputNotSanitized 1`, `MissingUnslash 1`, `Missing 0`
- Boundary: moved read-only ticketing claims, verification-panel, Woo request-path, customer benefits, and admin asset-state URL reads behind subsystem-local helper wrappers with narrow read-only suppressions; preserved the existing mutation, nonce, upload, AJAX, REST, redirect, and capability boundaries untouched
- Focused tests: `tests/ticket-claims-assignee-validation.php`, `tests/ticketing-claims-ajax-output-buffer-ownership.php`, `tests/ticketing-rules-v2-request-path-normalization.php`, `tests/ticketing-v2-ajax-output-buffer-ownership.php`, `tests/ticketing-output-buffer-lifecycle-characterization.php`, `tests/verification-proof-normalization.php`, `tests/private-file-upload-api-remediation.php`, `tests/private-file-operations-boundary-remediation.php`, `tests/event-plan-legacy-ticketing-integration-smoke.php`, `tests/ticketing-claims-admin-request-state-remediation.php`, and `tests/ticketing-verifications-request-state-remediation.php`
- Closure / defers: post-edit package landed at `265` errors, `1136` warnings, `1401` total findings, and `19` unique rule codes with `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1272`; the global nonce/input family now stands at `Missing 26`, `Recommended 62`, `InputNotSanitized 24`, and `MissingUnslash 11` (`123` total); same-file residual nonce/input rows in the five touched files now total `35` and all map to later `T2` or `T5` ownership; the sibling live tree remained untouched and post-edit parity was intentionally not restored

##### `WPORG-28R-G6-T2 — Normalize ticketing mutation request boundaries`

- Status: `verified` on `2026-07-30`
- Owned packaged rows: `33 -> 0` across `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-claims-customer.php`, `includes/integrations/ticketing-verifications.php`, and `includes/integrations/ticketing.php`
- Distinct boundaries: `23`
- Exact rule distribution: `Missing 18`, `Recommended 0`, `InputNotSanitized 10`, `MissingUnslash 5`
- Boundary: kept the existing admin-post, AJAX, redirect, and user-profile nonce ordering intact while moving the remaining mutation/request-shape reads behind subsystem-local POST helpers; malformed `existing_counts`, verification labels/allowances/upload settings, profile arrays, and allowance arrays now fail closed before unslashing, the front-end claims validator now submits `existing_counts[...]` fields matching that tightened POST boundary, and the deferred whole-`$_POST` `includes/integrations/ticketing-rules-v2.php` rows remain assigned to `T5`
- Focused tests: `tests/ticket-claims-assignee-validation.php`, `tests/ticketing-claims-ajax-output-buffer-ownership.php`, `tests/ticketing-claims-mutation-request-remediation.php`, `tests/ticketing-verifications-mutation-request-remediation.php`, `tests/ticketing-search-request-remediation.php`, `tests/ticketing-output-buffer-lifecycle-characterization.php`, `tests/verification-proof-normalization.php`, `tests/private-file-upload-api-remediation.php`, `tests/private-file-operations-boundary-remediation.php`, `tests/event-plan-legacy-ticketing-integration-smoke.php`, `tests/ticketing-claims-admin-request-state-remediation.php`, `tests/ticketing-verifications-request-state-remediation.php`, `tests/authorization-boundary-hardening.php`, `tests/strict-post-gate-remediation.php`, and `tests/request-input-sanitization.php`
- Closure / defers: post-edit package landed at `265` errors, `1103` warnings, `1368` total findings, and `19` unique rule codes with `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1239`; the global nonce/input family now stands at `Missing 8`, `Recommended 62`, `InputNotSanitized 14`, and `MissingUnslash 6` (`90` total); no nonce/input row remains in the four touched runtime files, the sibling live tree remained untouched, and post-edit parity was intentionally not restored

##### `WPORG-28R-G6-T3 — Admissions and availability read-only navigation/token state`

- Status: `verified` on `2026-07-30`
- Owned packaged rows: `34 -> 0` across `includes/modules/admissions/admin-ui.php`, `includes/modules/admissions/admission-tokens.php`, `includes/modules/admissions/pass-claims.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/modules/availability-date-dispatch/admin-ui.php`, and `includes/modules/availability-date-dispatch/helpers.php`
- Distinct boundaries: `16`
- Exact rule distribution: `Missing 0`, `Recommended 30`, `InputNotSanitized 2`, `MissingUnslash 2`
- Boundary: moved admissions and availability read-only page, tab, notice, filter, identifier, and token reads behind subsystem-local scalar, integer, boolean, and token helpers with narrow passive-read suppressions; preserved passive navigation without adding artificial nonces; preserved the existing verifier-facing token contracts; and left the lone same-file vendor-guest nested `$_POST['vms_vendor_guest_rules']` mutation boundary assigned to `T5`
- Focused tests: `tests/admissions-rest-permissions.php`, `tests/pass-claims-public-form-output-remediation.php`, `tests/pass-claims-public-shell-output-remediation.php`, `tests/pass-claims-public-status-output-remediation.php`, `tests/pass-claims-public-success-output-remediation.php`, `tests/portal-notice-sink-remediation.php`, `tests/add-dispatch-menu-badge-inline-assets-remediation.php`, `tests/admissions-read-only-request-state-remediation.php`, `tests/admission-token-request-state-remediation.php`, `tests/availability-date-dispatch-request-state-remediation.php`, `tests/runtime-stub-guards.php`, `tests/release-compatibility-harness.php`, `tests/public-release-build-pipeline.php`, `tests/authorization-boundary-hardening.php`, `tests/strict-post-gate-remediation.php`, and `tests/request-input-sanitization.php`
- Closure / defers: post-edit package landed at `265` errors, `1069` warnings, `1334` total findings, and `19` unique rule codes with `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1205`; the global nonce/input family now stands at `Missing 8`, `Recommended 32`, `InputNotSanitized 12`, and `MissingUnslash 4` (`56` total); no `T3`-owned row remained, no new rule code appeared, no unrelated rule count increased, the only same-file nonce/input residual in the six touched runtime files is the `T5` `vendor-guest-portal.php:912:21` mutation row, and the sibling live tree remained untouched with post-edit parity intentionally not restored

##### `WPORG-28R-G6-T4 — Shared wrappers, tours, and passive display-state helpers`

- Status: `verified` on `2026-07-30`
- Owned packaged rows: `34 -> 0` across `includes/core/plugin.php`, `includes/core/registry/admin-menu.php`, `includes/core/slow-request-logger.php`, `includes/core/tours/class-vms-tours.php`, `includes/helpers.php`, `includes/runtime-guards.php`, `includes/tours/class-vms-tours-screen.php`, and `includes/tours/class-vms-tours-service.php`
- Distinct boundaries: `20`
- Exact rule distribution: `Missing 0`, `Recommended 32`, `InputNotSanitized 2`, `MissingUnslash 0`
- Boundary: kept passive page, post-type, post-ID, and tours context reads nonce-free while moving the remaining shared display-state probes behind the existing narrow request helpers, added a finite allowlist to the dynamic request-value wrapper, normalized the slow-request logger action fallback through the shared scalar helper, and aligned the stale Tours mirror/live parity harness to the same drift-aware pattern already accepted for mirror-only request-boundary work
- Focused tests: `tests/reference-keys-map-inline-js-remediation.php`, `tests/slow-request-logger-request-input-characterization.php`, `tests/slow-request-logger-url-helper-remediation.php`, `tests/slow-request-logger-rotation-boundary-remediation.php`, `tests/admin-selector-redirect-uri-remediation.php`, `tests/tours-user-state-json-characterization.php`, `tests/guided-tours-reset-notice-output-remediation.php`, `tests/runtime-guard-request-state-remediation.php`, `tests/passive-display-state-remediation.php`, and `tests/tours-passive-request-state-remediation.php`
- Closure / defers: post-edit package landed at `265` errors, `1035` warnings, `1300` total findings, and `18` unique rule codes with `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1171`; the global nonce/input family now stands at `Missing 8`, `Recommended 0`, `InputNotSanitized 10`, and `MissingUnslash 4` (`22` total); no `T4`-owned row remained, all `22` preserved `T5` rows remained, no new rule code appeared, no unrelated rule count increased, and the only same-file nonce/input residuals in the T4-modified runtime files are the `T5` `runtime-guards.php` rows `B104`, `B113`, and `B114` plus the `tours/class-vms-tours-service.php` prefs-array row `B122`

##### `WPORG-28R-G6-T5 — Phase-B/V2 mutation arrays and remaining shared mutation helpers`

- Status: `verified` on `2026-08-01`
- Owned packaged rows: `22 -> 0` across `includes/admin/data-tools/actions-event-plan-import.php`, `includes/integrations/ticketing-phase-b.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/runtime-guards.php`, `includes/social-share/admin.php`, `includes/taxonomies/vendor-category.php`, `includes/ticketing/ticket-integrity-cron.php`, and `includes/tours/class-vms-tours-service.php`
- Distinct boundaries: `17 -> 0`
- Exact rule distribution: `Missing 8`, `Recommended 0`, `InputNotSanitized 10`, `MissingUnslash 4`
- Boundary: normalized structured mutation arrays and shared mutation helpers without changing existing nonce names, nonce actions, capabilities, authentication, ownership checks, hooks, redirects, responses, cron behavior, or persistence contracts; malformed top-level shapes fail closed, arrays are unslashed exactly once before schema handling, scalar fields reject arrays and objects, and passive runtime probes remain nonce-free with targeted justification.
- Focused tests: `tests/wporg-28r-g6-t5-mutation-request-boundaries.php`, `tests/ticketing-phase-b-ajax-output-buffer-ownership.php`, plus the frozen support inventory recorded at `/tmp/wporg-28rg6-t5-work.H3iuzL/pre-edit-support-scope.txt`
- Closure / defers: post-edit package landed at `265` errors, `1013` warnings, `1278` total findings, and `15` unique rule codes with `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=1149`; the global nonce/input family now stands at `Missing 0`, `Recommended 0`, `InputNotSanitized 0`, and `MissingUnslash 0`; all `22` T5 rows disappeared, no T1-T4 row returned, no new rule code appeared, and no unrelated rule count increased.

#### `WPORG-28R-G7 — Shared Request Helpers, Bootstrap, And Compatibility Reads`

- Status: `retired` on `2026-07-30`
- Count: `0`
- Scope note: the originally proposed shared-helper, bootstrap, tours, and compatibility read-only nonce/input rows were fully absorbed into the `WPORG-28R-G6-T4` and `WPORG-28R-G6-T5` partition. Both slices are now verified, and the post-`WPORG-28R-G6-T5` strict JSON contains zero packaged nonce/input rows with no separate `G7`-owned residual.
- Do not execute `WPORG-28R-G7` as a separate nonce/input child unless a future packaged strict-JSON rerun produces newly unmapped rows outside the preserved `G6` family.

### DB/SQL Children

#### `WPORG-28R-G8 — Staffing And Staff-Task Query Boundaries`

- Status: `verified` on `2026-08-02` after verified children `WPORG-28R-G8-T1`, `WPORG-28R-G8-T2`, `WPORG-28R-G8-T3`, `WPORG-28R-G8-T4`, and `WPORG-28R-G8-T5` preserved at `/tmp/wporg-28rg8-t1-work.2rY5sN`, `/tmp/wporg-28rg8-t2-work.54u1i4`, `/tmp/wporg-28rg8-t3-work.qiAdPz`, `/tmp/wporg-28rg8-t4-work.4CeoYs`, and `/tmp/wporg-28rg8-t5-work.ctlcQI`
- Original pre-edit count: `233` (`UnescapedDBParameter 36`, `DirectQuery 70`, `NoCaching 58`, `InterpolatedNotPrepared 36`, `NotPrepared 17`, `slow_meta_key 7`, `slow_meta_query 6`, `slow_meta_value 3`); current remaining count: `0`
- Distinct boundaries: `52 -> 0`
- Files: `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/staff-tasks/admin-ui.php`, `includes/modules/staff-tasks/db.php`, `includes/modules/staff-tasks/generator.php`, `includes/admin/staffing.php`, `includes/admin/staff-list-columns.php`, `includes/admin/staff-user-link.php`, `includes/admin/staff-vendor-link.php`, `includes/portal/staff-portal.php`, `includes/admin/vendor-staff-link.php`
- Lifecycle / subsystem: `ADMIN_PAGE`, `FORM_SUBMISSION`, `REPORTING`; staffing catalogs, task templates, assignments, roster views, and staff portal state
- Boundary: land prepared-statement and mutation-path fixes before cache-policy follow-up; preserve justified direct custom-table access where a core API is not the right abstraction
- Focused tests: `tests/staffing-final-repository-sql-remediation.php`, `tests/staffing-matrix-rollup-reporting-repository-sql-remediation.php`, `tests/staffing-repository-sql-remediation.php`, `tests/staff-task-instance-and-portal-repository-sql-remediation.php`, `tests/staff-tasks-repository-sql-remediation.php`, `tests/staff-tasks-overrides-json-remediation.php`, `tests/staff-tasks-signature-json-remediation.php`, `tests/authorization-boundary-hardening.php`, `tests/vendor-portal-availability-autosave-remediation.php`
- Fresh closeout evidence: `2026-08-02` dirty public package and packaged strict-json reruns reproduced `239` errors, `806` warnings, `1045` total findings, `15` unique rule codes, `KNOWN_ACCEPTED=0`, `KNOWN_NONBLOCKING=129`, `NEW_FINDING=0`, `UNMAPPED=0`, and `SUBMISSION_BLOCKER=916`; blocker-family totals now stand at `DB/SQL 849`, `nonce/input 0`, `date/time 25`, and `logging 42`, with empty build/plugin-check stderr.
- Decomposition:
  `WPORG-28R-G8-T1 — Staff Tasks template, checklist, and admin selector repositories` is now `verified`; it removed its owned `48` rows across `13` boundaries in `includes/modules/staff-tasks/store.php`, `includes/modules/staff-tasks/admin-ui.php`, and `includes/modules/staff-tasks/db.php` while preserving line-local live synchronization outside the targeted functions.
  `WPORG-28R-G8-T2 — Task-instance assignment, supersession, and staff-portal read models` is now `verified`; it removed its owned `53` rows across `12` boundaries in `includes/modules/staff-tasks/store.php` and `includes/portal/staff-portal.php`, preserved line-local live synchronization in both files, and replaced the bounded reverse-link `get_users()` fallback with a prepared `usermeta` lookup.
  `WPORG-28R-G8-T3 — Staffing template and event-slot repositories` is now `verified`; it removed its owned `62` rows across `12` boundaries in `includes/core/staffing.php`, preserved line-local live synchronization only inside the target functions, and landed bounded prepared template, slot, and assignment repository fixes.
  `WPORG-28R-G8-T4 — Staffing matrix, rollup, and reporting repositories` is now `verified`; it removed its owned `56` rows across `6` boundaries in `includes/core/staffing.php` and `includes/admin/staffing.php`, preserved owned-function mirror/live synchronization only inside the target helpers, and landed bounded prepared staffing-matrix, rollup, dirty-mark, and reporting repository fixes.
  `WPORG-28R-G8-T5 — Slow meta queries and reverse-link fallbacks` is now `verified`; it removed its owned `14` rows across `9` boundaries in `includes/admin/staff-list-columns.php`, `includes/admin/staff-user-link.php`, `includes/admin/staff-vendor-link.php`, `includes/admin/vendor-staff-link.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/admin-ui.php`, and `includes/modules/staff-tasks/generator.php`, preserved byte-identical full-file sync in the four fully synchronized files, and preserved owned-boundary parity only inside the three intentionally divergent files.
- Mirror/live audit: the original G8 inventory still shows `6` of the `11` owned files byte-identical end to end and the other `5` function-identical only; the T1-specific after-edit audit at `/tmp/wporg-28rg8-t1-work.2rY5sN/t1-mirror-live.after.tsv` confirms `store.php` and `db.php` remained byte-identical across mirror/live while `admin-ui.php` still diverges only outside the owned selector helper, the T2-specific after-edit audits at `/tmp/wporg-28rg8-t2-work.54u1i4/t2-mirror-live.after.tsv`, `/tmp/wporg-28rg8-t2-work.54u1i4/t2-target-functions.after.tsv`, and `/tmp/wporg-28rg8-t2-work.54u1i4/t2-live-untouched-regions.tsv` confirm the `store.php` target file stayed byte-identical across mirror/live and `staff-portal.php` still diverges only outside the owned portal helpers, the T3-specific after-edit audit at `/tmp/wporg-28rg8-t3-work.qiAdPz/post-edit-structure-final.1785651724` plus `/tmp/wporg-28rg8-t3-work.qiAdPz/function-parity-mirror.final.php` and `/tmp/wporg-28rg8-t3-work.qiAdPz/function-parity-live.final.php` confirm `staffing.php` still diverges only outside the owned template/event-slot helpers while every extracted T3 target function remained identical after sync, the T4-specific focused parity coverage in `tests/staffing-matrix-rollup-reporting-repository-sql-remediation.php` plus the synchronized mirror/live admin/core staffing helpers confirm the remaining full-file drift still stays outside the owned T4 functions after sync, and `tests/staffing-final-repository-sql-remediation.php` now proves the final T5-owned staffing-family bundles still satisfy the documented full-file and owned-boundary parity model.
- Preserved mapping note: the preserved `g8-boundaries.tsv` still assigns `G8-B002` to `vms_staff_user_link_metabox_render()` lines `26-79`, but the current packaged slow-meta query is the later `save_post_vms_staff` closure at line `131`; the final post-T5 ownership accounting now uses `/tmp/wporg-28rg8-t5-work.ctlcQI/scope/current-boundary-ranges.tsv`, and the stale mapping does not affect `T1`, `T2`, `T3`, `T4`, or terminal `T5` ownership counts.
- Closure / defers: owned packaged DB rows `233 -> 0`; verified children `T1`, `T2`, `T3`, `T4`, and `T5` removed the full staffing-family G8 inventory across all `52` boundaries, `WPORG-28R-G8` is now terminal under `verified`, the package now stands at `DB/SQL 849` and projected overall blockers `916`, and same-file logging in `includes/modules/staff-tasks/generator.php` remains deferred to `G17`

#### `WPORG-28R-G9 — Admissions And Claim-State Query Boundaries`

- Count: `255` (`UnescapedDBParameter 39`, `DirectQuery 82`, `NoCaching 74`, `InterpolatedNotPrepared 41`, `NotPrepared 7`, `UnfinishedPrepare 5`, `slow_meta_key 3`, `slow_meta_query 4`)
- Files: `includes/modules/admissions/pass-claims.php`, `includes/modules/admissions/rest.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/modules/admissions/admission-tokens.php`, `includes/modules/admissions/db.php`, `includes/modules/admissions/admin-ui.php`, `includes/modules/admissions/audit.php`
- Lifecycle / subsystem: `ADMIN_PAGE`, `ADMIN_POST`, `REST`, `FORM_SUBMISSION`; admissions claims, vendor guest portal, admission tokens, and admissions exports/support tools
- Boundary: separate direct mutation queries, read-only custom-table lookups, and incomplete placeholder preparation; do not collapse public claim flows and administrator maintenance into one blanket query rewrite
- Focused tests: `tests/pass-claims-public-form-output-remediation.php`, `tests/pass-claims-public-success-output-remediation.php`, `tests/pass-claims-public-status-output-remediation.php`, `tests/event-plan-legacy-ticketing-integration-smoke.php`
- Closure / defers: owned packaged DB rows `255 -> 0`; defer same-file input rows to `G6` and same-file operational logging in `includes/modules/admissions/rest.php` to `G16`

#### `WPORG-28R-G10 — Ticketing, Availability, And Integrity Query Boundaries`

- Status: `in progress`; verified completed DB/SQL ownership is `153` (`132` from ADD plus ticketing claims and `21` from Phase B), leaving `95`.
- Count: `248` (`UnescapedDBParameter 36`, `DirectQuery 68`, `NoCaching 60`, `InterpolatedNotPrepared 30`, `NotPrepared 28`, `slow_meta_key 9`, `slow_meta_query 16`, `slow_meta_value 1`)
- Files: `includes/integrations/ticketing-claims-framework.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-phase-b.php`, `includes/integrations/ticketing.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php`, `includes/integrations/square-ticket-mirror.php`, `includes/integrations/square-sync-firewall.php`, `includes/ticketing/ticket-inventory-forensics.php`, `includes/ticketing/ticket-mutation-audit.php`, `includes/ticketing/ticket-integrity-daily-report.php`, `includes/ticketing/ticket-integrity-monitor.php`, `includes/ticketing/ticket-integrity-cron.php`, `includes/modules/availability-date-dispatch/helpers.php`
- Lifecycle / subsystem: `FRONTEND_AJAX`, `REST`, `BACKGROUND_QUEUE`, `REPORTING`, `ADMIN_PAGE`; ticketing claims, ticketing rules, availability dispatch, integrity monitors, and support diagnostics
- Boundary: land prepared-SQL and mutation/input provenance fixes before revisiting `NoCaching` or slow-meta-query rows; keep read-only reporting queries distinct from cart or claims mutations
- Focused tests: `tests/ticketing-output-buffer-lifecycle-characterization.php`, `tests/ticket-claims-assignee-validation.php`, `tests/public-calendar-user-agent-view-characterization.php`, `tests/event-plan-legacy-ticketing-integration-smoke.php`
- Closure / defers: owned packaged DB rows currently `248 -> 95`; Phase B DB/SQL is `21 -> 0` and retains only `2` date, `1` logging, and `1` mapped nonblocking output rows; defer those non-DB rows to `G15`, `G16`, and the accepted output inventory

#### `WPORG-28R-G11 — Vendor, Portal, And Payables Query Boundaries`

- Status: `in progress`; `includes/core/vendor-user-links.php` DB/SQL is verified `36 -> 0`, leaving `67`.
- Count: `103` (`UnescapedDBParameter 9`, `DirectQuery 21`, `NoCaching 20`, `InterpolatedNotPrepared 9`, `NotPrepared 7`, `UnfinishedPrepare 2`, `slow_meta_key 10`, `slow_meta_query 25`)
- Files: `includes/core/vendor-user-links.php`, `includes/portal/vendor-portal.php`, `includes/core/vendor-application-confirmation.php`, `includes/vendor-applications.php`, `includes/admin/vendor-command-center.php`, `includes/cpt/vendors.php`, `includes/core/vendor-booking-onboarding.php`, `includes/core/payables.php`, `includes/public/vendor-profiles.php`, `includes/taxonomies/vendor-category.php`, `includes/admin/vendors/tax-export-csv.php`
- Lifecycle / subsystem: `ADMIN_PAGE`, `FORM_SUBMISSION`, `FRONTEND_RENDER`, `REPORTING`; vendor onboarding, vendor profiles, portal views, payables, and vendor-link state
- Boundary: separate vendor/portal read models from onboarding or payables mutations; evaluate `NoCaching` rows per query instead of assuming every custom-table or meta lookup is replaceable
- Focused tests: `tests/vendor-tax-profile-strict-post-remediation.php`, `tests/vendor-portal-availability-autosave-remediation.php`, `tests/vendor-apply-turnstile-contract-remediation.php`, `tests/authorization-boundary-hardening.php`
- Closure / defers: owned packaged DB rows currently `103 -> 67`; defer same-file input rows to `G5` and same-file date / logging rows to `G15` / `G16`

#### `WPORG-28R-G12 — Social Queue, Background Processing, And Private-File Query Boundaries`

- Status: `in progress`; `includes/social-share/queue-repo.php` DB/SQL is verified `73 -> 0`, and excluding the dormant `includes/safety/` prototype removes its `4` DB/SQL rows from the public boundary, leaving `38`.
- Count: `115` (`UnescapedDBParameter 18`, `DirectQuery 39`, `NoCaching 31`, `InterpolatedNotPrepared 16`, `NotPrepared 5`, `slow_meta_key 2`, `slow_meta_query 4`)
- Files: `includes/social-share/queue-repo.php`, `includes/social-share/installer.php`, `includes/social-share/audit.php`, `includes/social-share/template-engine.php`, `includes/social-share/event-plan-panel.php`, `includes/core/notifications.php`, `includes/modules/email-followups/recipients.php`, `includes/modules/email-followups/scheduler.php`, `includes/core/private-files.php`, `includes/safety/private-files.php`, `includes/services/event-plan-import/event-plan-import-engine.php`
- Lifecycle / subsystem: `BACKGROUND_QUEUE`, `CRON`, `IMPORT_EXPORT`, `ADMIN_PAGE`; social queue persistence, background notifications, private-file indexes, and import-support lookups
- Boundary: keep queue/audit persistence separate from import/export or private-file read models; revisit `NoCaching` only after direct query safety and placeholder correctness are fixed
- Focused tests: `tests/social-share-queue-snapshot-json-remediation.php`, `tests/social-share-webhook-exception-boundary-remediation.php`, `tests/private-file-upload-api-remediation.php`, `tests/event-plan-import-upload-api-remediation.php`
- Closure / defers: owned packaged DB rows currently `115 -> 38`; defer same-file operational logging to `G16`

#### `WPORG-28R-G13 — Reporting, Schema, Meta-Query, And Cache-Policy Long Tail`

- Status: `in progress` under the coordinated execution plan; the verified Wave 1 scan still assigns all `128` rows here, and the independent goals/forecast repository slice is the first active G13 boundary.
- Count: `128` (`UnescapedDBParameter 5`, `DirectQuery 18`, `NoCaching 17`, `InterpolatedNotPrepared 5`, `NotPrepared 3`, `slow_meta_key 38`, `slow_meta_query 38`, `slow_meta_value 4`)
- Files: `includes/core/registry/vendor-schema.php`, `includes/db/migrations.php`, `includes/cpt/event-plans.php`, `includes/cpt/ratings.php`, `includes/core/event-feedback.php`, `includes/helpers.php`, `includes/admin/settings-page.php`, `includes/admin/express-bar.php`, `includes/admin/event-command-center.php`, `includes/admin/budget-calculator.php`, `includes/admin/venue-duplicate-templates.php`, `includes/admin/settings/class-vms-settings-tours.php`, `includes/admin/event-feedback.php`, `includes/admin/integrity-calendar-reconcile.php`, `includes/admin/approvals-review-queue.php`, `includes/admin/schedule.php`, `includes/core/calendar-feed.php`, `includes/core/calendar-ticket-counts.php`, `includes/core/cancellation-adapters.php`, `includes/core/cli/stale-check.php`, `includes/core/event-credits.php`, `includes/core/ticket-sales-resolver.php`, `includes/schedule/schedule.php`, `includes/helpers/checkin-close.php`, `includes/core/goals-forecast.php`
- Lifecycle / subsystem: `REPORTING`, `DIAGNOSTIC`, `CLI`, `OTHER`; schema helpers, long-tail admin reports, schedule summaries, event feedback aggregations, and slow-meta-query warnings
- Boundary: this is the deliberate tail bucket for reporting, schema, and meta-query warnings that should not block higher-risk mutation work; every query still needs occurrence-specific review before any suppression
- Focused tests: `tests/event-feedback-request-hash-characterization.php`, `tests/release-compatibility-harness.php`, `tests/public-release-build-pipeline.php`, `tests/runtime-stub-guards.php`
- Closure / defers: owned packaged DB rows `128 -> 0`; defer same-file date rows to `G14` / `G15` and same-file diagnostic logging to `G17`

### Date/Time Children

#### `WPORG-28R-G14 — Display Formatting, Identifiers, And Admin Date Labels`

- Count: `11` (`date_date 11`)
- Files: `includes/helpers.php`, `includes/admin/ticket-integrity-page.php`, `includes/admin/staff-tax-sidebar.php`, `includes/admin/tax-profile-admin-metabox.php`, `includes/admin/square-sync-protection.php`, `includes/cpt/event-plans/partials/time-lineup.php`, `includes/schedule/season-dates.php`, `includes/core/cli/state-of-range.php`
- Lifecycle / subsystem: `ADMIN_PAGE`, `CLI`, `FRONTEND_RENDER`; display formatting, report labels, export identifiers, and human-readable admin timestamps
- Boundary: replace native `date()` only where `wp_date()`, `gmdate()`, or `DateTimeImmutable` preserve the existing display or identifier contract
- Focused tests: `tests/public-calendar-user-agent-view-characterization.php`, `tests/authorization-boundary-hardening.php`, `tests/event-plan-legacy-ticketing-integration-smoke.php`
- Closure / defers: owned packaged date rows `11 -> 0`; defer business-window and persisted-timestamp work to `G15`

#### `WPORG-28R-G15 — Business Windows, Scheduling, And Persisted Timestamp Boundaries`

- Count: `14` (`date_date 14`)
- Files: `includes/modules/staff-tasks/notifications.php`, `includes/ticketing/ticket-integrity-monitor.php`, `includes/integrations/ticketing-phase-b.php`, `includes/core/payables.php`, `includes/portal/vendor-tax-profile.php`, `includes/core/event-credits.php`
- Lifecycle / subsystem: `CRON`, `REPORTING`, `FORM_SUBMISSION`, `FRONTEND_RENDER`; staff notifications, ticketing integrity windows, payables, vendor tax profile stamps, and event-credit business rules
- Boundary: preserve timezone-sensitive comparisons and persisted values; use `wp_date()`, `current_time()`, `gmdate()`, or `DateTimeImmutable` according to the existing UTC/local business rule
- Focused tests: `tests/vendor-tax-profile-strict-post-remediation.php`, `tests/event-plan-legacy-ticketing-integration-smoke.php`, `tests/ticket-integrity-query-filter-boundary-remediation.php`
- Closure / defers: owned packaged date rows `14 -> 0`; no later date child remains

### Logging Children

#### `WPORG-28R-G16 — Operational Failure And Service Logging`

- Count: `26` (`error_log 26`)
- Files: `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/admin/data-tools/actions-event-plan-import.php`, `includes/taxonomies/vendor-type.php`, `includes/runtime-guards.php`, `includes/integrations/ticketing-phase-b.php`, `includes/core/notifications.php`, `includes/core/vendor-application-confirmation.php`, `includes/ticketing/ticket-integrity-monitor.php`, `includes/admin/settings-page.php`, `includes/core/goals-forecast.php`
- Lifecycle / subsystem: `FORM_SUBMISSION`, `REST`, `IMPORT_EXPORT`, `BACKGROUND_QUEUE`, `BOOTSTRAP`; operational failures, service-edge retries, and bounded runtime diagnostics
- Boundary: preserve real operational failure evidence while removing dev-only noise, sensitive values, and recursion risks; do not route fatal-path logging through helpers that might require unavailable services
- Focused tests: `tests/vendor-apply-turnstile-contract-remediation.php`, `tests/event-plan-import-upload-api-remediation.php`, `tests/request-input-sanitization.php`, `tests/ticket-claims-assignee-validation.php`
- Closure / defers: owned packaged logging rows `26 -> 0`; defer development-only traces and profiler noise to `G17`

#### `WPORG-28R-G17 — Development Diagnostics, Profiling, And Trace Logging`

- Count: `16` (`error_log 15`, `debug_backtrace 1`)
- Files: `includes/cpt/event-plans.php`, `includes/modules/staff-tasks/generator.php`, `includes/ticketing/ticket-mutation-audit.php`, `includes/helpers.php`, `includes/tours/class-vms-tours-registry.php`, `includes/core/event-plan-save-profiler.php`, `includes/core/event-plan-performance.php`, `includes/admin/approvals-review-queue.php`, `includes/admin/menu.php`, `includes/admin/vendor-list-ui.php`
- Lifecycle / subsystem: `DIAGNOSTIC`, `REPORTING`, `ADMIN_PAGE`; developer traces, profilers, mutation audits, and tours/admin helpers
- Boundary: remove or gate pure dev traces, stack collection, and profiler leftovers after the earlier operational-logging child proves what must remain for production support
- Focused tests: `tests/event-plan-performance-request-id-remediation.php`, `tests/slow-request-logger-request-input-characterization.php`, `tests/staff-tasks-signature-json-remediation.php`, `tests/ticketing-output-buffer-lifecycle-characterization.php`
- Closure / defers: owned packaged logging rows `16 -> 0`; no later logging child remains

## Dependency Order

1. `WPORG-28R-G1` comes first because it reuses already-proven exact-POST and admin-form guard patterns in four bounded admin modules.
2. `WPORG-28R-G2` depends on `G1` because the broader dashboard/settings batch should reuse the same request-boundary conventions before widening into less isolated admin screens.
3. `WPORG-28R-G3` and `WPORG-28R-G4` depend on `G1` because CPT and Event Plan save flows are riskier than the admin-only module batch and should inherit the same normalization posture.
4. `WPORG-28R-G5` depends on `G1` because public and portal flows should not be the first place new nonce/input decisions are introduced.
5. `WPORG-28R-G6` depends on `G1` because ticketing and admissions mutations are broader, higher-risk surfaces that should follow the bounded admin request hardening pattern.
6. `WPORG-28R-G8` follows `G1` because the staff-task SQL work shares the same runtime files and should not begin before the request provenance inside those files is stabilized.
7. `WPORG-28R-G9` and `WPORG-28R-G10` depend on `G6` because query rewrites on admissions and ticketing code should follow the request-boundary fixes on the same flows.
8. `WPORG-28R-G11` depends on `G5` because vendor/portal query work is safer after the public and portal request boundaries stop drifting.
9. `WPORG-28R-G12` depends on `G7` because the background/queue and import-support queries share bootstrap and helper boundaries that must be characterized first.
10. `WPORG-28R-G13` follows `G3`, `G4`, and `G8-G12` because it is the deliberate reporting/schema/meta-query tail bucket after higher-risk mutation and helper work lands.
11. `WPORG-28R-G14` follows `G13` because display/identifier date formatting in shared report helpers should not be edited before the underlying reporting/query ownership is stable.
12. `WPORG-28R-G15` follows `G5`, `G10`, `G11`, and `G14` because timezone-sensitive business windows and persisted timestamps depend on earlier portal, ticketing, payables, and display-boundary characterization.
13. `WPORG-28R-G16` follows `G5`, `G6`, and `G12` because operational logging decisions are safer after the request and background lifecycles they describe are stabilized.
14. `WPORG-28R-G17` is last because development traces and profiler leftovers should only be removed after the earlier children prove what operational evidence must remain.

## First Implementation Child

- Child: `WPORG-28R-G1 — Admin module form boundaries`
- Exact ownership: `59` rows across `WordPress.Security.NonceVerification.Recommended` (`38`), `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` (`15`), and `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` (`6`)
- Exact files: `includes/modules/staff-tasks/admin-ui.php`, `includes/modules/email-followups/admin-ui.php`, `includes/admin/event-feedback.php`, `includes/admin/season-dates.php`
- Why first: this is the cleanest bounded admin-only request slice with existing exact-POST characterization, existing capability/nonce scaffolding, and no need to start in public, portal, ticketing, or Event Plan mutation paths
- Required source/test/doc scope: only the four mirror runtime files above plus the focused suites `tests/strict-post-gate-remediation.php`, `tests/admin-request-method-wrapper-remediation.php`, `tests/administrator-explicit-notice-output-remediation.php`, and `tests/authorization-boundary-hardening.php`, then the two remediation docs and ledger updates that record the owned delta
- Required pre-edit characterization: confirm the current packaged `59`-row ownership from the fresh strict JSON, re-verify the exact POST helper and notice-shell contracts, and confirm no same-file DB/output rows are accidentally pulled into scope
- Expected remediation outcome: close the owned packaged input rows by normalizing unslashing, allowlisting, and scalar handling while leaving read-only filters read-only and preserving existing mutation nonce/capability guards
- Required package comparison: a fresh post-edit package rerun must show only the owned `G1` input rows gone from those four files, with no unrelated family increase
- Completion criteria: `G1` owns the first exact packaged delta, `WPORG-28R` stays blocked, and the next child remains `WPORG-28R-G2`
- Explicit defers: same-file DB rows in `includes/modules/staff-tasks/admin-ui.php` defer to `G8`; same-file DB rows in `includes/admin/event-feedback.php` and `includes/admin/season-dates.php` defer to `G13`; accepted `OutputNotEscaped` rows in the touched files remain outside the blocker roadmap

## Parent Closeout Condition

- `WPORG-28R-G0` remains terminal because its original `1149`-row roadmap had no unowned or multiply-owned occurrence; subsequent verified children have reduced the current blocker total to `395` without changing ownership rules.
- `WPORG-28R` remains blocked until every `G1` through `G17` child closes and a fresh packaged strict-json rerun proves `SUBMISSION_BLOCKER=0`.
- `WPORG-28`, `WPORG-28Q`, `Review-2 Name/Slug Closeout`, and `Review-13 Final Actions` all remain open or blocked exactly as documented in the ledger.
- Slug reservation, corrected upload, and reviewer communication remain unauthorized until the parent is closed and explicit authorization is given.

## Recommended Next Task

- Active execution targets: `WPORG-28R-G10` Ticketing Rules V2 (`23` DB/SQL), `WPORG-28R-G11` vendor-application confirmation (`27` DB/SQL), and the independent `WPORG-28R-G13` goals/forecast repository (`31` DB/SQL).
- Scope: keep each target in an isolated worktree with its own executable SQL-contract test, synchronize only owned functions into intentionally divergent live files, and let the coordinator own shared docs, the aggregate package, and strict-json comparison.
- Scope guardrails: do not reopen verified `G1` through `G9` or retired `G7`; do not pull the same-file logging rows into these DB/SQL slices; do not treat the package as submission-ready until `G10` through `G17` close and the strict-json blocker count reaches zero.
