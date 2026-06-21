# WordPress.org Plugin Check Triage 1.0.0

Date: 2026-06-21

## Scope

- Raw output saved at `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`
- Scan target for current counts: temporary installed package extracted from `dist/wporg-04p/vms-1.0.0-public-release.zip` under a disposable plugin slug, leaving the local `vms/` install untouched
- Current artifact SHA-256: `720dc9a32f3609ebb54ef77227b0cf85123776554f7b62c347e8a77077fcf152`
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

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-1299` total findings
- `-603` errors
- `-696` warnings

Net reduction from `WPORG-04O`:

- `-2` total findings
- `-2` errors
- `0` warnings

## Fixed In This Pass

- `includes/social-share/audit.php`
  - `7` findings -> `5`
  - `2` errors -> `0`
  - `5` warnings -> `5`
  - cleared the file's real `UnescapedDBParameter` and `PreparedSQL.NotPrepared` errors plus both interpolated-SQL warnings by converting the audit table reference to a prepared `%i` identifier and moving the prepared SQL directly into the two existing read-only query branches
- Focused validation for this batch
  - no focused social audit regression exists in `tests/`
  - validation stayed on PHP lint, whitespace safety, public-release build, package integrity, and a rerun of packaged Plugin Check against a temporary extracted plugin slug under the local WordPress install
  - Plugin Check stderr captured external dependency deprecation noise under `test-results/wporg-04p-plugin-check.stderr.txt` while the cleaned raw findings stayed in `docs/plugin-check-1.0.0-raw.txt`

Code-level deltas visible in the packaged scan:

- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `156` -> `155`
- `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`: `148` -> `146`
- `WordPress.DB.PreparedSQL.NotPrepared`: `73` -> `72`
- `WordPress.DB.DirectDatabaseQuery.DirectQuery`: `293` -> `294`
- `WordPress.DB.DirectDatabaseQuery.NoCaching`: `255` -> `256`

No new Plugin Check codes appeared in this pass.

## Current Category Triage

| Category | Count | Representative files | Classification | Recommended strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `1198` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` | BLOCKER | This pass stayed deliberately outside portal mutation and Event Plans save logic. The remaining high-density nonce/input work is still concentrated in `save_event_plan_meta()` and adjacent high-risk portal and integration flows. |
| Database and SQL safety | `1101` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` | BLOCKER | Prioritize `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL findings before generic direct-query/no-caching warnings. |
| Escaping and output safety | `183` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` | BLOCKER | The highest-yield remaining escape work is now concentrated in the Staff Portal, shared admin render shells, and other render surfaces rather than the cleared public vendor profile files. |
| I18n placeholder comments and ordering | `650` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` | SHOULD FIX BEFORE SUBMISSION | Continue adding `translators:` comments and ordered placeholders after the remaining blocker categories are materially reduced. |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` | SHOULD FIX BEFORE SUBMISSION | Review each remaining `date()` use. Convert display-only paths to explicit timezone-safe helpers and leave local-time-sensitive cases for deliberate follow-up review. |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` | SHOULD FIX BEFORE SUBMISSION | Remove or hard-gate residual development logging that is still reachable in packaged code. |

## Event Plans Conclusions

- The Event Plans file remains the highest-density packaged file at `241` findings.
- No Event Plans runtime findings were changed in `WPORG-04P`.
- The low-risk admin list/helper surface is now nearly exhausted.
- Remaining Event Plans findings are dominated by:
  - `save_event_plan_meta()` and adjacent request/save logic
  - the main Event Plan details render block tied to integration state
  - cancellation/refund, legacy ticket cleanup, and TEC/ticketing side-effect paths

## Recommended Next Task

- `WPORG-04Q`
- Scope:
  - shift the next safe read-only SQL batch to `includes/modules/admissions/admin-ui.php`
  - keep the pass limited to the guest-list export CSV read query and table-identifier preparation only
  - leave the remaining `fclose()` filesystem finding, guest-list mutation behavior, REST handlers, permissions, and Event Plans runtime untouched
  - keep the pass out of ticketing/payment/refund/cancellation flows, portal/profile-save flows, availability mutations, staffing mutations, and publish/TEC sync paths
