# WordPress.org Plugin Check Triage 1.0.0

Date: 2026-06-20

## Scope

- Raw output saved at `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`
- Scan target for current counts: installed package built from `dist/wporg-04e/vms-1.0.0-public-release.zip`
- Current artifact SHA-256: `ca120b97c574ccdd72bb124defc8e712ed7291f4f9730d334423b6b1176d34be`
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

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-962` total findings
- `-330` errors
- `-632` warnings

Net reduction from `WPORG-04D`:

- `-87` total findings
- `0` errors
- `-87` warnings

## Fixed In This Pass

- `includes/admin/due-dates.php`
  - `46` findings -> `0`
  - centralized read-only admin query access
  - unslashed and sanitized flagged request values before use
- `includes/admin/holidays.php`
  - `41` findings -> `0`
  - centralized read-only GET, REQUEST, and POST access for admin routing and delegated reads
- Event Plans test bootstraps
  - the seven remaining regression scripts that still hardcoded `wp-load.php` now use `tests/bootstrap-wordpress.php`
  - all seven pass from the nested repo workspace without changing Event Plans runtime logic

Code-level deltas visible in the packaged scan:

- `WordPress.Security.NonceVerification.Recommended`: `643` -> `614`
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`: `273` -> `260`
- `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`: `268` -> `233`
- `WordPress.Security.NonceVerification.Missing`: `129` -> `119`

No new Plugin Check codes appeared in this pass.

## Current Category Triage

| Category | Count | Representative files | Classification | Recommended strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `1248` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` | BLOCKER | The safe non-Event-Plans admin request batch is done. The remaining high-density nonce/input work is concentrated in `save_event_plan_meta()` and adjacent high-risk Event Plans and integration flows. |
| Database and SQL safety | `1119` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` | BLOCKER | Prioritize `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL findings before generic direct-query/no-caching warnings. |
| Escaping and output safety | `313` `OutputNotEscaped` findings | `includes/portal/vendor-portal.php`, `includes/portal/staff-portal.php`, `includes/admin/staffing.php`, `includes/public/venue-calendar-shortcode.php` | BLOCKER | Event Plans still has some render-surface escaping debt, but the next high-yield escaping targets are outside Event Plans. |
| I18n placeholder comments and ordering | `783` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/admin/event-command-center.php`, `includes/integrations/ticketing-verifications.php` | SHOULD FIX BEFORE SUBMISSION | Continue adding `translators:` comments and ordered placeholders after the remaining blocker categories are materially reduced. |
| Date/time API usage | `50` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/admin/vendor-availability.php` | SHOULD FIX BEFORE SUBMISSION | Review each `date()` use. Convert display-only UTC-safe paths to `gmdate()` and leave local-time-sensitive paths for deliberate follow-up review. |
| Development logging | `42` `error_log()` findings | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` | SHOULD FIX BEFORE SUBMISSION | Remove or hard-gate `error_log()` calls that are still reachable in packaged code. |

## Event Plans Conclusions

- The Event Plans file remains the highest-density packaged file at `241` findings.
- No Event Plans runtime findings were changed in `WPORG-04E`.
- The low-risk admin list/helper surface is now nearly exhausted.
- Remaining Event Plans findings are dominated by:
  - `save_event_plan_meta()` and adjacent request/save logic
  - the main Event Plan details render block tied to integration state
  - cancellation/refund, legacy ticket cleanup, and TEC/ticketing side-effect paths

## Recommended Next Task

- `WPORG-04F`
- Scope:
  - take a dedicated high-risk Event Plans request/save hardening pass with new regression coverage around `save_event_plan_meta()`, validation, live refunds, and TEC/ticketing side effects
  - use the now-aligned nested-repo-safe Event Plans regression suite as the gate before widening those changes
