# WordPress.org Plugin Check Triage 1.0.0

Date: 2026-06-22

## Scope

- Raw output saved at `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`
- Scan target for current counts: extracted packaged directory from `dist/wporg-11a/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree, leaving the local `vms/` install untouched
- Current artifact SHA-256: `f9abd751234a27cd981b74c00bfd3fc33dc2d2cb24c519e682ed9c0c6c18c875`
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

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-1548` total findings
- `-781` errors
- `-767` warnings

Net reduction from `WPORG-10A`:

- `-10` total findings
- `-2` errors
- `-8` warnings

## Fixed In This Pass

- 11A candidate scan summary
  - detailed DB/SQL candidate scan coverage is recorded in `docs/WPORG_PLUGIN_CHECK_HEATMAP_1.0.0.md`
  - reviewed the highest-density remaining DB/SQL files from the packaged `WPORG-10A` baseline before editing
  - selected `includes/modules/admissions/pass-claims.php` because it still had an isolated admin/reporting-only read subset after `WPORG-07B`: sources, batches, individual token lookups, and token list reporting
  - skipped higher-density candidates that were mixed with staffing runtime, task writes, vendor assignment runtime, queue mutation/state, REST mutations, ticketing checkout claims, portal/public flows, or previously selected DB/SQL work
- `includes/modules/admissions/pass-claims.php`
  - `165` findings -> `155`
  - `15` errors -> `13`
  - `150` warnings -> `142`
  - DB/SQL subset `125` -> `115`
  - limited the pass to preparing existing admin/report read-helper table identifiers and values; selected columns, joins, filters, ordering, limits, return shapes, exports, claim writes, public token claim logic, and handlers stayed unchanged
- Focused validation for this batch
  - no focused regression exists in `tests/` for the selected pass-claims admin/report helper subset
  - `php -l includes/modules/admissions/pass-claims.php` passed
  - `git diff --check` passed
  - validation stayed on PHP lint, whitespace safety, public-release build, package integrity, and a rerun of packaged Plugin Check against an extracted packaged directory outside the local site tree
  - `php scripts/build-public-release.php --output-dir dist/wporg-11a --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - normalized packaged findings were saved to `test-results/wporg-11a-plugin-check.raw.txt` and `test-results/wporg-11a-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`

Code-level deltas visible in the packaged scan:

- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `148` -> `142`
- `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`: `143` -> `136`
- `WordPress.DB.PreparedSQL.NotPrepared`: `68` -> `67`
- `WordPress.DB.DirectDatabaseQuery.DirectQuery`: `294` -> `296`
- `WordPress.DB.DirectDatabaseQuery.NoCaching`: `256` -> `258`
- `includes/modules/admissions/pass-claims.php`: `165` -> `155`

No previously unseen Plugin Check codes appeared in this pass. The direct-query/no-cache counts increased by two because the formerly interpolated reads are now explicit prepared `$wpdb` calls; the selected file still netted a `10`-finding DB/SQL reduction.

## Current Category Triage

| Category | Count | Representative files | Classification | Recommended strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `1143` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` | BLOCKER | `WPORG-05E` closed the last low-risk read-only slice. `WPORG-11A` stayed on DB/SQL read helpers again, so the remaining high-density nonce/input work is still concentrated in mutation-coupled admin, portal, ticketing, and Event Plans/integration flows that need dedicated regression coverage before hardening. |
| Database and SQL safety | `1077` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` | BLOCKER | Continue isolated admin/reporting read slices if another low-risk candidate exists; otherwise pause DB/SQL and prepare nonce/input mutation-flow planning with dedicated regression coverage. |
| Escaping and output safety | `145` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` | BLOCKER | Keep the escape-only audit paused after `WPORG-06C`; the remaining candidates are shared allowed-HTML boundaries, public/portal surfaces, metabox/save flows, vendor-assignment dashboards, or excluded Event Plans slices rather than isolated admin-only display targets. |
| I18n placeholder comments and ordering | `539` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` | SHOULD FIX BEFORE SUBMISSION | Pause targeted i18n after `WPORG-08B` unless another equivalently isolated admin-only translator-comment or placeholder-ordering slice is identified; otherwise switch back to blocker coverage. |
| Date/time API usage | `25` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` | SHOULD FIX BEFORE SUBMISSION | Pause the date/time phase after `WPORG-09A` unless another admin-only display-only slice appears; the remaining higher-density candidates are scheduling, sales-window, notification, payables, portal-stamping, Event Plans, or shared runtime helpers. |
| Development logging | `41` findings (`40` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` | SHOULD FIX BEFORE SUBMISSION | Pause the logging phase after `WPORG-10A` unless another equivalently isolated admin-only dev-trace slice appears; the remaining files are operationally meaningful or runtime-sensitive. |

## Event Plans Conclusions

- The Event Plans file remains the highest-density packaged file at `241` findings.
- No Event Plans runtime findings were changed in `WPORG-08A`, `WPORG-08B`, `WPORG-09A`, `WPORG-10A`, or `WPORG-11A`.
- The selected `pass-claims.php` pass stayed outside Event Plans runtime and mutation logic again.
- The read-only nonce/input phase remains closed after `WPORG-05E`, the escape-only phase remains paused after `WPORG-06C`, and `WPORG-11A` stayed completely outside Event Plans runtime.
- Remaining Event Plans findings are dominated by:
  - `save_event_plan_meta()` and adjacent request/save logic
  - the main Event Plan details render block tied to integration state
  - cancellation/refund, legacy ticket cleanup, and TEC/ticketing side-effect paths

## Recommended Next Task

- Post-`WPORG-11A` phased follow-up
- Scope:
  - continue one more isolated DB/SQL reporting pass only if the next candidate is another read-only admin/report helper with stable query intent; otherwise shift to nonce/input mutation-flow planning
  - pause further logging cleanup unless another equivalently isolated admin-only dev-trace or debug-only slice appears; the remaining logging files are public submissions, REST mutations, cron generation, runtime guards, notification/ticketing diagnostics, taxonomy repair, or excluded Event Plans work
  - keep the date/time phase paused unless another equivalently isolated admin-only display/report/list/settings slice appears; the remaining higher-density date/time files are scheduling, ticket-window, digest/notification, payables, portal-stamping, Event Plans, or shared runtime helpers
  - keep targeted i18n paused unless another equivalently isolated admin-only translator-comment slice is identified; the highest-density remaining i18n files are now Event Plans, checkout/ticketing, upload/verification, portal/public, email, or save-flow coupled
  - if the i18n phase resumes, keep it limited to translator comments, ordered placeholders, or literal `vms` text-domain corrections in admin-only diagnostics/list/report files
  - keep the escape-only phase paused after `WPORG-06C`; the remaining output-heavy files are shared boundaries, public/portal surfaces, vendor-assignment dashboards, metabox/save flows, or excluded Event Plans slices rather than isolated admin-only display targets
  - prepare nonce/input mutation-flow planning after the next isolated DB/SQL pass, or immediately if no equally safe DB/reporting candidate appears
