# WordPress.org Plugin Check Triage 1.0.0

Date: 2026-06-21

## Scope

- Raw output saved at `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`
- Scan target for current counts: extracted packaged directory from `dist/wporg-05c/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree, leaving the local `vms/` install untouched
- Current artifact SHA-256: `e2b6279b72adf456d5a15c5ed4f6d8dac4051380a09df027d483b7c1f7164c62`
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
| `WPORG-04E` packaged RC, final | installed packaged plugin | `3605` | `1316` | `2289` | Cleared the safe high-density batch in `includes/admin/due-dates.php` and `includes/admin/holidays.php` outside Event Plans. |
| `WPORG-04G` packaged RC, final | installed packaged plugin | `3554` | `1266` | `2288` | Cleared the safe error-heavy render/i18n/date batch in `includes/admin/vendor-command-center.php` and `includes/admin/vendor-availability.php` outside Event Plans. |
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

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-1469` total findings
- `-724` errors
- `-745` warnings

Net reduction from `WPORG-05C`:

- `-5` total findings
- `0` errors
- `-5` warnings

## Fixed In This Pass

- 05D candidate scan summary
  - `includes/admin/docs-page.php` - `6` total / `1` errors / `5` warnings - `5` nonce/input findings - dominant `NonceVerification.Recommended` with one `MissingUnslash` companion and one escaping error - read-only module/doc routing only - risk `low` - selected because the file stays isolated to GET-driven docs routing, has no mutation paths, and clears five warnings without changing module fallback or doc-selection semantics
  - `includes/admin-ui/context.php` - `6` total / `0` errors / `6` warnings - `6` nonce/input findings - dominant `NonceVerification.Recommended` - shared admin page/post-type/post routing only - risk `low` - skipped because it is a broader shared helper surface for similar yield
  - `includes/modules/status-notices/admin-ui.php` - `24` total / `2` errors / `22` warnings - `22` nonce/input findings - dominant `NonceVerification.Recommended`, `InputNotSanitized`, and `MissingUnslash` - read-only view/result/filter/query reads mixed with edit/save/toggle/bulk/trash handlers - risk `medium` - skipped because mutation routes remain coupled in the file
  - `includes/admin/vendor-command-center.php` - `29` total / `0` errors / `29` warnings - `27` nonce/input findings - dominant `NonceVerification.Recommended` and `InputNotSanitized` with slow-query companions - read-only filters mixed with template reset/save/send POST handlers - risk `medium` - skipped because mutation-adjacent code shares the file
  - `includes/admin/event-command-center.php` - `15` total / `0` errors / `15` warnings - `14` nonce/input findings - dominant `NonceVerification.Recommended` - read-only notice/plan/page selectors mixed with promo-upload handling - risk `medium` - skipped because upload/mutation code shares the file
  - `includes/admin/schedule.php` - `22` total / `0` errors / `22` warnings - `21` nonce/input findings - dominant `NonceVerification.Recommended`, `MissingUnslash`, and `InputNotSanitized` - read-only date/view routing mixed with create-event-plan action handling - risk `medium` - skipped because action code shares the file
  - `includes/admin/vendor-list-ui.php` - `5` total / `4` errors / `1` warnings - `0` current nonce/input findings - dominant `OutputNotEscaped` with one logging companion - read-only vendor list filters only - risk `low` - skipped because `WPORG-05B` already cleared its input warnings, leaving no remaining read-only nonce/input yield
  - `includes/admin/event-profitability-report.php` - `1` total / `1` errors / `0` warnings - `0` current nonce/input findings - dominant `OutputNotEscaped` - prior low-risk reference point only - risk `low` - skipped because `WPORG-05C` already cleared its read-only input warnings
- `includes/admin/docs-page.php`
  - `6` findings -> `1`
  - `1` errors -> `1`
  - `5` warnings -> `0`
  - cleared the file's read-only `mod` and `doc` input warnings by centralizing raw GET access behind one helper while preserving the existing default module, fallback module selection, and document routing semantics
- Focused validation for this batch
  - no focused `docs-page` regression exists in `tests/`
  - `php -l includes/admin/docs-page.php` passed
  - `git diff --check` passed
  - validation stayed on PHP lint, whitespace safety, public-release build, package integrity, and a rerun of packaged Plugin Check against an extracted packaged directory outside the local site tree
  - `php scripts/build-public-release.php --output-dir dist/wporg-05d --force --allow-dirty` passed
  - normalized packaged findings were saved to `test-results/wporg-05d-plugin-check.raw.txt` and promoted into `docs/plugin-check-1.0.0-raw.txt`

Code-level deltas visible in the packaged scan:

- `WordPress.Security.NonceVerification.Recommended`: `563` -> `559`
- `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`: `228` -> `227`
- `Nonce and input handling`: `1154` -> `1149`
- `Database and SQL safety`: `1101` -> `1101`
- `includes/admin/docs-page.php`: `6` -> `1`
- observed rerun-only steady state outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `1`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`

No previously unseen Plugin Check codes appeared in this pass, and the standing `load_plugin_textdomain()` warning remained unchanged outside the selected file scope.

## Current Category Triage

| Category | Count | Representative files | Classification | Recommended strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `1149` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` | BLOCKER | This pass stayed deliberately outside portal mutation and Event Plans save logic. The remaining high-density nonce/input work is still concentrated in `save_event_plan_meta()` and adjacent high-risk portal and integration flows. |
| Database and SQL safety | `1101` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` | BLOCKER | Prioritize `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL findings before generic direct-query/no-caching warnings. |
| Escaping and output safety | `161` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` | BLOCKER | The highest-yield remaining escape work is now concentrated in the Staff Portal, shared admin shell boundaries, and other render paths rather than the cleared dashboard menu slice. |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` | SHOULD FIX BEFORE SUBMISSION | Continue adding `translators:` comments and ordered placeholders after the remaining blocker categories are materially reduced. |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` | SHOULD FIX BEFORE SUBMISSION | Review each remaining `date()` use. Convert display-only paths to explicit timezone-safe helpers and leave local-time-sensitive cases for deliberate follow-up review. |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` | SHOULD FIX BEFORE SUBMISSION | Remove or hard-gate residual development logging that is still reachable in packaged code. |

## Event Plans Conclusions

- The Event Plans file remains the highest-density packaged file at `241` findings.
- No Event Plans runtime findings were changed in `WPORG-05D`.
- The selected `docs-page.php` pass stayed completely outside Event Plans runtime and mutation logic.
- The read-only nonce/input phase now has one admin-filter file cleared completely, one admin-list file reduced to non-input leftovers, and both `event-profitability-report.php` and `docs-page.php` reduced to lone escaping leftovers before widening into mutation-coupled surfaces.
- Remaining Event Plans findings are dominated by:
  - `save_event_plan_meta()` and adjacent request/save logic
  - the main Event Plan details render block tied to integration state
  - cancellation/refund, legacy ticket cleanup, and TEC/ticketing side-effect paths

## Recommended Next Task

- Post-`WPORG-05D` phased follow-up
- Scope:
  - clear the remaining low-risk read-only admin surface in `includes/admin-ui/context.php` before widening into mutation-coupled admin screens such as `status-notices/admin-ui.php`, `vendor-command-center.php`, `event-command-center.php`, and `schedule.php`
  - follow with a DB/SQL phase that prioritizes `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL issues in admissions, staffing, staff-task, and queue/store helpers before generic direct-query/no-caching warnings
  - keep a separate i18n remainder phase for low-yield placeholder-comment leftovers such as `includes/admin/settings/class-vms-settings-notifications.php`, `includes/public/event-details.php`, and `includes/admin/staff-certifications.php` after the security-heavy phases move forward
  - reserve an escaping remainder phase for shared render boundaries including `includes/admin-ui/shell.php`, Staff Portal surfaces, vendor-guest output, and other callback-driven HTML paths
  - finish with a runtime-aware high-risk phase for shared helpers, calendar ICS output, notification-adjacent code, ticketing, cancellation/refund, portal/auth, and Event Plans save/publish flows
