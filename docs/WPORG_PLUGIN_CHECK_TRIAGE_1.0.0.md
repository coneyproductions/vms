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
| `WPORG-12A` packaged RC, planning only | extracted packaged directory outside local site tree | `3019` | `865` | `2154` | Docs-only nonce/input mutation roadmap; no package rebuild or rerun because the WP.org tracking docs are excluded from the public ZIP. |

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-1548` total findings
- `-781` errors
- `-767` warnings

Net reduction from `WPORG-11A`:

- `0` total findings
- `0` errors
- `0` warnings

Net reduction from `WPORG-10A`:

- `-10` total findings
- `-2` errors
- `-8` warnings

## Fixed In This Pass

- 12A planning-only summary
  - recorded the remaining nonce/input mutation roadmap in the normal WP.org tracking docs only
  - did not edit runtime PHP, tests, or packaged artifacts
  - did not rebuild the public ZIP or rerun packaged Plugin Check because the tracking docs are excluded from the public package
  - classified the top twenty nonce/input-heavy files, which account for `739 / 1143` remaining nonce/input findings across `89` files in the current packaged baseline
- roadmap outcome
  - the first three recommended code batches are `includes/admin/settings-page.php`, `includes/admin/ticket-integrity-page.php`, and `includes/modules/status-notices/admin-ui.php`
  - the highest-risk deferred surfaces remain Event Plans runtime, portal/public submissions, ticketing/upload flows, and mixed save-post/admin-public handlers
  - detailed per-file workflow, guard, coverage, and phase notes are captured in `docs/WPORG_PLUGIN_CHECK_HEATMAP_1.0.0.md`
- validation for this pass
  - docs-only validation stays on `git diff --check` plus `git diff --cached --check` before commit

Code-level deltas visible in the packaged scan:

- none in `WPORG-12A`; the packaged baseline remains the `WPORG-11A` result

No previously unseen Plugin Check codes appeared in this pass because the packaged scan was not rerun and no runtime code changed.

## Current Category Triage

| Category | Count | Representative files | Classification | Recommended strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `1143` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` | BLOCKER | `WPORG-12A` documented the mutation-flow roadmap. Start the first admin-only normalization batch in `includes/admin/settings-page.php`, then move to `includes/admin/ticket-integrity-page.php` and `includes/modules/status-notices/admin-ui.php`. Require focused automated coverage before ticketing, portal/public, upload, admissions-claim, or Event Plans/runtime hardening. |
| Database and SQL safety | `1077` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` | BLOCKER | Pause further DB/SQL work unless a new isolated admin/reporting read slice appears. The next planned execution phase should be nonce/input mutation hardening, not another broad SQL pass. |
| Escaping and output safety | `145` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` | BLOCKER | Keep the escape-only audit paused after `WPORG-06C`; the remaining candidates are shared allowed-HTML boundaries, public/portal surfaces, metabox/save flows, vendor-assignment dashboards, or excluded Event Plans slices rather than isolated admin-only display targets. |
| I18n placeholder comments and ordering | `539` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` | SHOULD FIX BEFORE SUBMISSION | Pause targeted i18n after `WPORG-08B` unless another equivalently isolated admin-only translator-comment or placeholder-ordering slice is identified; otherwise switch back to blocker coverage. |
| Date/time API usage | `25` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` | SHOULD FIX BEFORE SUBMISSION | Pause the date/time phase after `WPORG-09A` unless another admin-only display-only slice appears; the remaining higher-density candidates are scheduling, sales-window, notification, payables, portal-stamping, Event Plans, or shared runtime helpers. |
| Development logging | `41` findings (`40` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` | SHOULD FIX BEFORE SUBMISSION | Pause the logging phase after `WPORG-10A` unless another equivalently isolated admin-only dev-trace slice appears; the remaining files are operationally meaningful or runtime-sensitive. |

## Nonce/Input Mutation Roadmap

- packaged planning baseline
  - counts remain `3019 / 865 / 2154` from `WPORG-11A`
  - no public-release rebuild or Plugin Check rerun was needed for `WPORG-12A`
  - the top twenty nonce/input files account for `739 / 1143` remaining findings
- first code batches after this planning pass

| Target | Workflow | Expected reduction | Guard pattern | Coverage gate | Why first |
| --- | --- | ---: | --- | --- | --- |
| `includes/admin/settings-page.php` | admin settings save and stock-tool request normalization | `~18-24` | keep existing `manage_options` and nonce checks; normalize request values only | manual admin QA is sufficient if scope stays on normalization | best guard scaffolding and lowest coupling among remaining mutation files |
| `includes/admin/ticket-integrity-page.php` | report settings, preview, dry-run, send/test, export normalization | `~14-20` | keep existing `manage_options` and `check_admin_referer()` usage; normalize request values only | reuse adjacent report/lock tests and add manual QA for send/test/export | bounded admin reporting surface with nearby validation evidence |
| `includes/modules/status-notices/admin-ui.php` | save, duplicate, toggle, trash, bulk, and editor/list request normalization | `~18-22` | keep existing custom-capability and nonce patterns; normalize route/action/request values only | manual admin QA is sufficient if behavior stays unchanged | bounded admin-only state transitions with existing guard scaffolding |

- deferred until after submission or until dedicated coverage exists
  - `includes/cpt/event-plans.php`
  - `includes/vendor-applications.php`
  - `includes/integrations/ticketing-verifications.php`
  - `includes/integrations/ticketing-claims-admin.php`
  - `includes/portal/vendor-portal.php`
  - `includes/portal/staff-portal.php`
  - `includes/modules/admissions/pass-claims.php`
  - `includes/cpt/venues.php`
  - `includes/safety/admin.php`
  - `includes/public/event-feedback.php`
  - `includes/cpt/ratings.php`
  - `includes/admin/schedule.php`
- test recommendation
  - the first admin-only normalization batch does not need new automated coverage if it stays limited to unslash/sanitize/allowlist handling around existing nonce and capability checks and is paired with manual admin QA
  - add focused automated coverage before touching ticketing, portal/public submission, upload/import, admissions claim, or Event Plans/runtime nonce/input flows

## Event Plans Conclusions

- The Event Plans file remains the highest-density packaged file at `241` findings.
- No Event Plans runtime findings were changed in `WPORG-08A`, `WPORG-08B`, `WPORG-09A`, `WPORG-10A`, `WPORG-11A`, or the docs-only `WPORG-12A` pass.
- The selected `pass-claims.php` pass stayed outside Event Plans runtime and mutation logic again.
- The read-only nonce/input phase remains closed after `WPORG-05E`, the escape-only phase remains paused after `WPORG-06C`, and `WPORG-12A` only documented the deferred Event Plans roadmap.
- Remaining Event Plans findings are dominated by:
  - `save_event_plan_meta()` and adjacent request/save logic
  - the main Event Plan details render block tied to integration state
  - cancellation/refund, legacy ticket cleanup, and TEC/ticketing side-effect paths

## Recommended Next Task

- Next execution target
  - start the first mutation-flow nonce/input code batch in `includes/admin/settings-page.php`
- Scope
  - keep the first batch limited to request normalization around already-existing nonce and capability checks
  - do not widen into behavior changes, new nonce fields, new actions, or adjacent ticketing/runtime rewrites
  - keep DB/SQL, logging, date/time, targeted i18n, and escape-only follow-up paused unless a new isolated admin-only slice materially safer than the first nonce batch appears
