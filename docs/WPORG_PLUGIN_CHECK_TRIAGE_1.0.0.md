# WordPress.org Plugin Check Triage 1.0.0

Date: 2026-06-20

## Scope

- Raw output saved at `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`
- Scan target for current counts: installed package built from `dist/wporg-04g/vms-1.0.0-public-release.zip`
- Current artifact SHA-256: `e2f4f6a45593b26c319dea37b4179f174e54558aa25acdc0a1131f6cbe553f6d`
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

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-1013` total findings
- `-380` errors
- `-633` warnings

Net reduction from `WPORG-04E`:

- `-51` total findings
- `-50` errors
- `-1` warnings

## Fixed In This Pass

- `includes/admin/vendor-command-center.php`
  - `51` findings -> `29`
  - `22` errors -> `0`
  - cleared the file's `OutputNotEscaped` and `MissingTranslatorsComment` errors without changing vendor workflow logic
- `includes/admin/vendor-availability.php`
  - `50` findings -> `22`
  - `28` errors -> `0`
  - cleared the file's `OutputNotEscaped`, `MissingTranslatorsComment`, and `date()` findings without changing booking or save behavior
- Focused packaged-validation test bootstraps
  - `tests/vendor-availability-ux.php` and `tests/add-dispatch-open-vendor-needs.php` now use `tests/bootstrap-wordpress.php`
  - `vendor-availability-ux` passes from the nested repo workspace
  - `add-dispatch-open-vendor-needs` still fails on a pre-existing missing-primary-vendor visibility assertion outside this render-only batch

Code-level deltas visible in the packaged scan:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `767` -> `738`
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `313` -> `296`
- `WordPress.DateTime.RestrictedFunctions.date_date`: `50` -> `46`

No new Plugin Check codes appeared in this pass.

## Current Category Triage

| Category | Count | Representative files | Classification | Recommended strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `1248` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` | BLOCKER | This pass stayed deliberately outside request/save logic. The remaining high-density nonce/input work is concentrated in `save_event_plan_meta()` and adjacent high-risk Event Plans and integration flows. |
| Database and SQL safety | `1118` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` | BLOCKER | Prioritize `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL findings before generic direct-query/no-caching warnings. |
| Escaping and output safety | `300` `OutputNotEscaped` findings | `includes/portal/vendor-portal.php`, `includes/portal/staff-portal.php`, `includes/admin/staffing.php`, `includes/public/venue-calendar-shortcode.php` | BLOCKER | Event Plans still has some render-surface escaping debt, but the next safe high-yield escape/i18n targets remain outside Event Plans. |
| I18n placeholder comments and ordering | `760` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/admin/event-command-center.php`, `includes/integrations/ticketing-verifications.php` | SHOULD FIX BEFORE SUBMISSION | Continue adding `translators:` comments and ordered placeholders after the remaining blocker categories are materially reduced. |
| Date/time API usage | `46` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/admin/vendor-availability.php` | SHOULD FIX BEFORE SUBMISSION | Review each remaining `date()` use. Convert display-only paths to explicit timezone-safe helpers and leave local-time-sensitive cases for deliberate follow-up review. |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` | SHOULD FIX BEFORE SUBMISSION | Remove or hard-gate residual development logging that is still reachable in packaged code. |

## Event Plans Conclusions

- The Event Plans file remains the highest-density packaged file at `241` findings.
- No Event Plans runtime findings were changed in `WPORG-04G`.
- The low-risk admin list/helper surface is now nearly exhausted.
- Remaining Event Plans findings are dominated by:
  - `save_event_plan_meta()` and adjacent request/save logic
  - the main Event Plan details render block tied to integration state
  - cancellation/refund, legacy ticket cleanup, and TEC/ticketing side-effect paths

## Recommended Next Task

- `WPORG-04H`
- Scope:
  - take the next safe admin-only error-heavy batch in `includes/admin/event-command-center.php`
  - limit the pass to `translators:` comments plus final render-surface escaping, using the same narrow validation loop as `WPORG-04G`
