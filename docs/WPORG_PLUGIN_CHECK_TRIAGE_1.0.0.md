# WordPress.org Plugin Check Triage 1.0.0

Date: 2026-06-22

## Scope

- Raw output saved at `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`
- Scan target for current counts: extracted packaged directory from `dist/wporg-07b/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree, leaving the local `vms/` install untouched
- Current artifact SHA-256: `275f5ecf22f4170f1824ce85617bfad10e51d9d7db8237fa4de89d69e173adbc`
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

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-1506` total findings
- `-748` errors
- `-758` warnings

Net reduction from `WPORG-07A`:

- `-8` total findings
- `-8` errors
- `0` warnings

## Fixed In This Pass

- 07B candidate scan summary
  - `includes/modules/admissions/pass-claims.php` - `173` total / `23` errors / `150` warnings / `133` DB/SQL findings - dominant `DirectQuery`, `NoCaching`, `UnescapedDBParameter`, `InterpolatedNotPrepared`, and `NotPrepared` - query type: mixed file, but selected slice is read-only aggregation/report queries - request or user input reaches the selected queries: no direct value interpolation; only admin report tab/export scope chooses which static aggregate runs - runtime context: admin-only reports tab plus admin report CSV export - risk `medium` - selected because four report helpers were isolated to admin reporting, already read-only, and could be prepared without changing joins, grouping, ordering, limits, or result shape
  - `includes/core/staffing.php` - `153` total / `38` errors / `115` warnings / `122` DB/SQL findings - dominant schema introspection, direct-query/no-caching, unescaped DB parameters, and interpolated table SQL - query type: mixed read/write runtime helpers - request or user input reaches the queries: indirect/shared runtime access - runtime context: core staffing runtime, admin, and scheduling helpers - risk `high` - skipped because read and write behavior remain tightly interleaved through shared staffing helpers
  - `includes/modules/staff-tasks/store.php` - `90` total / `17` errors / `73` warnings / `90` DB/SQL findings - dominant direct-query/no-caching and not-prepared repository queries - query type: create/update/delete repository - request or user input reaches the queries: yes - runtime context: staff-task CRUD store - risk `high` - skipped because the file is mutation-centric rather than a report/read-only slice
  - `includes/modules/availability-date-dispatch/helpers.php` - `96` total / `19` errors / `77` warnings / `85` DB/SQL findings - dominant direct-query/no-caching, unescaped DB parameters, and interpolated SQL - query type: mixed helper/runtime queries - request or user input reaches the queries: indirect through assignment and scheduling flows - runtime context: ADD dispatch and vendor-assignment helpers - risk `high` - skipped because assignment behavior dominates the remaining queries
  - `includes/social-share/queue-repo.php` - `73` total / `7` errors / `66` warnings / `73` DB/SQL findings - dominant repository direct queries plus queue/account/map mutations - query type: mixed repository reads and writes - request or user input reaches the queries: indirect via admin settings and queue actions - runtime context: social queue/account runtime - risk `high` - skipped because the file is mutation-heavy
  - `includes/modules/admissions/rest.php` - `65` total / `11` errors / `54` warnings / `58` DB/SQL findings - dominant admissions REST reads/writes, unfinished prepare, and logging pressure - query type: mixed REST read/write runtime - request or user input reaches the queries: yes - runtime context: admissions AJAX/REST runtime - risk `high` - skipped because request handling and mutation are interleaved
  - `includes/integrations/ticketing-claims-framework.php` - `50` total / `16` errors / `34` warnings / `49` DB/SQL findings - dominant grants/reservations/log/schema query helpers - query type: mixed runtime and write coordination - request or user input reaches the queries: indirect through ticketing claims workflows - runtime context: ticketing/claims integration - risk `high` - skipped because schema, reservation, and runtime mutation behavior are interleaved
  - `includes/core/vendor-user-links.php` - `36` total / `7` errors / `29` warnings / `36` DB/SQL findings - dominant direct-query/no-caching plus dynamic read helpers and unfinished prepare - query type: mixed read/write linkage helpers - request or user input reaches the queries: indirect via portal/admin link lookups - runtime context: shared portal/access-link runtime - risk `medium`/`high` - skipped because it underpins portal/access-control linkage and still mixes reads with write coordination
  - `includes/modules/admissions/vendor-guest-portal.php` - `75` total / `36` errors / `39` warnings / `35` DB/SQL findings - dominant guest-portal DB reads plus public output pressure - query type: mixed portal reads and public handling - request or user input reaches the queries: yes - runtime context: vendor/public guest portal - risk `high` - skipped because public request handling remains mixed with the DB helpers
  - `includes/core/goals-forecast.php` - `32` total / `0` errors / `32` warnings / `31` DB/SQL findings - dominant direct-query/no-caching plus remaining write-path identifier warnings - query type: mixed read/write reporting helpers - request or user input reaches the remaining queries: no direct request input in the previously selected read slice - runtime context: admin-only goals forecast reporting - risk `medium` - skipped because `WPORG-07A` already exhausted the clearly isolated low-risk read helpers and the remaining warnings are outside that narrow read-only slice
- additional DB or adjacent files inspected but not selected: `includes/ticketing/ticket-integrity-daily-report.php`, `includes/social-share/audit.php`, `includes/social-share/installer.php`, and `includes/modules/admissions/admission-tokens.php`
- `includes/modules/admissions/pass-claims.php`
  - `173` findings -> `165`
  - `133` DB/SQL findings -> `125`
  - `22` `PluginCheck.Security.DirectDB.UnescapedDBParameter` findings -> `18`
  - `5` `WordPress.DB.PreparedSQL.NotPrepared` findings -> `1`
  - limited the pass to `vms_pass_claims_reports_by_source()`, `vms_pass_claims_reports_by_batch()`, `vms_pass_claims_reports_source_events()`, and `vms_pass_claims_reports_by_event()` only, preparing the existing table identifiers inline in the current read-only aggregate queries without changing columns, joins, grouping, ordering, limits, export shape, or public claim behavior
- Focused validation for this batch
  - no focused `pass-claims` reporting regression exists in `tests/`
  - `php -l includes/modules/admissions/pass-claims.php` passed
  - `git diff --check` passed
  - validation stayed on PHP lint, whitespace safety, public-release build, package integrity, and a rerun of packaged Plugin Check against an extracted packaged directory outside the local site tree
  - `php scripts/build-public-release.php --output-dir dist/wporg-07b --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - normalized packaged findings were saved to `test-results/wporg-07b-plugin-check.raw.txt` and `test-results/wporg-07b-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`

Code-level deltas visible in the packaged scan:

- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `152` -> `148`
- `WordPress.DB.PreparedSQL.NotPrepared`: `72` -> `68`
- `Database and SQL safety`: `1095` -> `1087`
- `includes/modules/admissions/pass-claims.php`: `173` -> `165`
- observed rerun-only change outside the selected file scope: `plugin_header_nonexistent_domain_path`: `0` -> `1`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`

No previously unseen Plugin Check codes appeared in this pass. The extracted-package rerun reintroduced the previously observed `plugin_header_nonexistent_domain_path` warning outside the selected file scope.

## Current Category Triage

| Category | Count | Representative files | Classification | Recommended strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `1143` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` | BLOCKER | `WPORG-05E` closed the last low-risk read-only slice. `WPORG-07B` stayed on DB-only query preparation again, so the remaining high-density nonce/input work is still concentrated in mutation-coupled admin, portal, ticketing, and Event Plans/integration flows that need dedicated regression coverage before hardening. |
| Database and SQL safety | `1087` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` | BLOCKER | Prioritize real parameter-safety and preparation issues before generic direct-query/no-caching warnings, but pause the DB/SQL phase whenever the candidate is no longer an isolated admin/reporting read slice. |
| Escaping and output safety | `145` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` | BLOCKER | Keep the escape-only audit paused after `WPORG-06C`; the remaining candidates are shared allowed-HTML boundaries, public/portal surfaces, metabox/save flows, vendor-assignment dashboards, or excluded Event Plans slices rather than isolated admin-only display targets. |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` | SHOULD FIX BEFORE SUBMISSION | Continue adding `translators:` comments and ordered placeholders after the remaining blocker categories are materially reduced. |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` | SHOULD FIX BEFORE SUBMISSION | Review each remaining `date()` use. Convert display-only paths to explicit timezone-safe helpers and leave local-time-sensitive cases for deliberate follow-up review. |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` | SHOULD FIX BEFORE SUBMISSION | Remove or hard-gate residual development logging that is still reachable in packaged code. |

## Event Plans Conclusions

- The Event Plans file remains the highest-density packaged file at `241` findings.
- No Event Plans runtime findings were changed in `WPORG-07B`.
- The selected `pass-claims.php` report-helper pass stayed outside Event Plans runtime and mutation logic.
- The read-only nonce/input phase remains closed after `WPORG-05E`, the escape-only phase remains paused after `WPORG-06C`, and `WPORG-07B` stayed completely outside Event Plans runtime.
- Remaining Event Plans findings are dominated by:
  - `save_event_plan_meta()` and adjacent request/save logic
  - the main Event Plan details render block tied to integration state
  - cancellation/refund, legacy ticket cleanup, and TEC/ticketing side-effect paths

## Recommended Next Task

- Post-`WPORG-07B` phased follow-up
- Scope:
  - pause broad DB/SQL cleanup unless another equivalently isolated admin/reporting read-only slice is identified; the remaining high-density DB files are now mostly mutation-coupled, public-facing, ticketing-linked, or portal-linked
  - if the DB/SQL phase continues, prioritize remaining parameter-safety and preparation issues only where the candidate can be carved away from mutation, schema, auth, checkout/ticketing, cron, or export behavior
  - otherwise switch to a low-risk i18n remainder batch or prepare regression coverage for the mutation-coupled nonce/input backlog before widening security hardening again
  - keep the escape-only phase paused after `WPORG-06C`; the remaining output-heavy files are shared boundaries, public/portal surfaces, vendor-assignment dashboards, metabox/save flows, or excluded Event Plans slices rather than isolated admin-only display targets
