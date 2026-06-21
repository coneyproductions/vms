# WordPress.org Plugin Check Triage 1.0.0

Date: 2026-06-21

## Scope

- Raw output saved at `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`
- Scan target for current counts: extracted packaged directory from `dist/wporg-04q/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree, leaving the local `vms/` install untouched
- Current artifact SHA-256: `bdb050f722c55de68a34c1690a7f8143f024801e638a7f00f1a14975c96d3671`
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

Net reduction from the `WPORG-02` source-tree baseline to the current packaged RC:

- `-1312` total findings
- `-615` errors
- `-697` warnings

Net reduction from `WPORG-04P`:

- `-13` total findings
- `-12` errors
- `-1` warnings

## Fixed In This Pass

- 04Q candidate scan summary
  - `includes/core/lineup-schedule.php` - `12` total / `12` errors / `0` warnings - risk `low` - selected
  - `includes/core/vendor-user-links.php` - `68` total / `39` errors / `29` warnings - risk `medium` - skipped
  - `includes/admin/schedule.php` - `52` total / `30` errors / `22` warnings - risk `medium/high` - skipped
  - `includes/modules/availability-date-dispatch/admin-ui.php` - `30` total / `21` errors / `9` warnings - risk `high` - skipped
  - `includes/core/event-credits.php` - `23` total / `16` errors / `7` warnings - risk `high` - skipped
- `includes/core/lineup-schedule.php`
  - `12` findings -> `0`
  - `12` errors -> `0`
  - `0` warnings -> `0`
  - cleared the file's full `MissingTranslatorsComment` set by adding `translators:` comments above the existing `_n()` and `__()` placeholder strings only
- Focused validation for this batch
  - no focused lineup-schedule regression exists in `tests/`
  - validation stayed on PHP lint, whitespace safety, public-release build, package integrity, and a rerun of packaged Plugin Check against an extracted packaged directory outside the local site tree
  - Plugin Check stderr captured WP-CLI deprecation noise under `test-results/wporg-04q-plugin-check.stderr.txt` while the cleaned raw findings stayed in `docs/plugin-check-1.0.0-raw.txt`

Code-level deltas visible in the packaged scan:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `634` -> `622`
- observed rerun-only delta outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `0`

No new Plugin Check codes appeared in this pass.

## Current Category Triage

| Category | Count | Representative files | Classification | Recommended strategy |
| --- | ---: | --- | --- | --- |
| Nonce and input handling | `1198` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` | BLOCKER | This pass stayed deliberately outside portal mutation and Event Plans save logic. The remaining high-density nonce/input work is still concentrated in `save_event_plan_meta()` and adjacent high-risk portal and integration flows. |
| Database and SQL safety | `1101` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` | BLOCKER | Prioritize `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL findings before generic direct-query/no-caching warnings. |
| Escaping and output safety | `183` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` | BLOCKER | The highest-yield remaining escape work is now concentrated in the Staff Portal, shared admin render shells, and other render surfaces rather than the cleared public vendor profile files. |
| I18n placeholder comments and ordering | `638` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` | SHOULD FIX BEFORE SUBMISSION | Continue adding `translators:` comments and ordered placeholders after the remaining blocker categories are materially reduced. |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` | SHOULD FIX BEFORE SUBMISSION | Review each remaining `date()` use. Convert display-only paths to explicit timezone-safe helpers and leave local-time-sensitive cases for deliberate follow-up review. |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` | SHOULD FIX BEFORE SUBMISSION | Remove or hard-gate residual development logging that is still reachable in packaged code. |

## Event Plans Conclusions

- The Event Plans file remains the highest-density packaged file at `241` findings.
- No Event Plans runtime findings were changed in `WPORG-04Q`.
- The low-risk admin list/helper surface is now nearly exhausted.
- Remaining Event Plans findings are dominated by:
  - `save_event_plan_meta()` and adjacent request/save logic
  - the main Event Plan details render block tied to integration state
  - cancellation/refund, legacy ticket cleanup, and TEC/ticketing side-effect paths

## Recommended Next Task

- `WPORG-04R`
- Scope:
  - repeat the deliberate hotspot scan before selecting the next file
  - prefer another i18n-only or read-only display/helper slice outside Event Plans, ticketing, refunds, portal-save, availability-save, and vendor-linking runtime
  - `includes/core/vendor-user-links.php` is the leading medium-risk candidate only if the pass can stay strictly on translator comments and avoid its DB/query branches
