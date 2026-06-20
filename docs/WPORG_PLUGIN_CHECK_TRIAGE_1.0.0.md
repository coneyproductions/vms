# WordPress.org Plugin Check Triage 1.0.0

Date: 2026-06-20

## Scope

- Raw output saved at `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`
- Scan target for current counts: temporary installed package extracted from `dist/wporg-04i/vms-1.0.0-public-release.zip` under a disposable plugin slug, leaving the local `vms/` install untouched
- Current artifact SHA-256: `aceda39376ec454c49106a1a41ec88a96ec5ff49acfb97ae730308c93120aaa8`
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

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-1132` total findings
- `-467` errors
- `-665` warnings

Net reduction from `WPORG-04H`:

- `-56` total findings
- `-24` errors
- `-32` warnings

## Fixed In This Pass

- `includes/admin/staffing.php`
  - `59` findings -> `3`
  - `24` errors -> `0`
  - `35` warnings -> `3`
  - cleared the file's `OutputNotEscaped`, `MissingTranslatorsComment`, `MissingUnslash`, `InputNotValidated`, most nonce/input noise around the template and rollup admin forms, and the unescaped/interpolated rollup-count query without widening into Event Plans runtime or staffing mutation logic
- Focused validation for this batch
  - no dedicated staffing admin regression exists in `tests/`
  - validation stayed on PHP lint, whitespace safety, public-release build, package integrity, and a rerun of packaged Plugin Check against a temporary extracted plugin slug under the local WordPress install
  - Plugin Check stderr captured external dependency deprecation noise under `test-results/wporg-04i-plugin-check.stderr.txt` while the cleaned raw findings stayed in `docs/plugin-check-1.0.0-raw.txt`

Code-level deltas visible in the packaged scan:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `692` -> `685`
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `284` -> `263`
- nonce/input handling by the packaged raw grouping: `1247` -> `1217`
- DB/SQL handling by the packaged raw grouping: `1119` -> `1117`

No new Plugin Check codes appeared in this pass.

## Current Category Triage

| Category | Count | Representative files | Classification | Recommended strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `1217` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` | BLOCKER | This pass stayed deliberately outside request/save logic. The remaining high-density nonce/input work is still concentrated in `save_event_plan_meta()` and adjacent high-risk Event Plans and integration flows. |
| Database and SQL safety | `1117` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` | BLOCKER | Prioritize `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL findings before generic direct-query/no-caching warnings. |
| Escaping and output safety | `267` `EscapeOutput` findings | `includes/portal/vendor-portal.php`, `includes/portal/staff-portal.php`, `includes/public/venue-calendar-shortcode.php`, `includes/cpt/event-plans.php` | BLOCKER | The highest-yield remaining escape/i18n targets are now in the staff and vendor portals rather than staffing admin. |
| I18n placeholder comments and ordering | `685` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` | SHOULD FIX BEFORE SUBMISSION | Continue adding `translators:` comments and ordered placeholders after the remaining blocker categories are materially reduced. |
| Date/time API usage | `45` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` | SHOULD FIX BEFORE SUBMISSION | Review each remaining `date()` use. Convert display-only paths to explicit timezone-safe helpers and leave local-time-sensitive cases for deliberate follow-up review. |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` | SHOULD FIX BEFORE SUBMISSION | Remove or hard-gate residual development logging that is still reachable in packaged code. |

## Event Plans Conclusions

- The Event Plans file remains the highest-density packaged file at `241` findings.
- No Event Plans runtime findings were changed in `WPORG-04I`.
- The low-risk admin list/helper surface is now nearly exhausted.
- Remaining Event Plans findings are dominated by:
  - `save_event_plan_meta()` and adjacent request/save logic
  - the main Event Plan details render block tied to integration state
  - cancellation/refund, legacy ticket cleanup, and TEC/ticketing side-effect paths

## Recommended Next Task

- `WPORG-04J`
- Scope:
  - take the next safe render/i18n batch in `includes/portal/staff-portal.php`
  - keep the pass out of Event Plans runtime, ticketing/payment/refund/cancellation flows, vendor-assignment saves, staffing mutations, and publish/TEC sync paths
