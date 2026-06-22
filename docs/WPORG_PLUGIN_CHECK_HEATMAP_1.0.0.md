# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-22

## Scope

- Scan target: extracted packaged directory from `dist/wporg-07a/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `94507b4c77d748be22553a042e573f0126336692b5d7cbb80d7a4b1fd748b6b2`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3069` total findings
- `906` errors
- `2163` warnings

Comparison to the prior packaged RC from `WPORG-06C`:

- `3076` -> `3069` total (`-7`)
- `906` -> `906` errors (`0`)
- `2170` -> `2163` warnings (`-7`)

## WPORG-07A Batch

- 07A candidate scan summary
  - `includes/modules/admissions/pass-claims.php` - `173` total / `23` errors / `150` warnings - `133` DB/SQL findings - dominant `DirectQuery`, `NoCaching`, `UnescapedDBParameter`, and interpolated/not-prepared SQL - admissions claims batch/report/export helpers - risk `medium`/`high` - skipped because the remaining reads are interleaved with admissions claim and export behavior
  - `includes/core/staffing.php` - `153` total / `38` errors / `115` warnings - `121` DB/SQL findings - dominant schema introspection, direct-query/no-caching, and interpolated table SQL - shared staffing/runtime helper surface - risk `high` - skipped because read and write behavior are tightly interleaved through core staffing helpers
  - `includes/modules/staff-tasks/store.php` - `90` total / `17` errors / `73` warnings - `89` DB/SQL findings - dominant direct-query/no-caching plus not-prepared store queries - staff-task CRUD store - risk `high` - skipped because the file is an explicit create/update/delete repository
  - `includes/modules/availability-date-dispatch/helpers.php` - `96` total / `19` errors / `77` warnings - `85` DB/SQL findings - dominant direct-query/no-caching, unescaped DB parameters, and interpolated SQL - ADD dispatch/vendor-assignment helpers - risk `high` - skipped because assignment and scheduling behavior dominates the remaining queries
  - `includes/social-share/queue-repo.php` - `73` total / `7` errors / `66` warnings - `73` DB/SQL findings - dominant repository direct queries and queue/account/template row mutations - social queue repository - risk `high` - skipped because the file is mutation-centric rather than a read-only reporting slice
  - `includes/modules/admissions/rest.php` - `65` total / `11` errors / `54` warnings - `58` DB/SQL findings - dominant admissions REST reads/writes mixed with nonce/input and logging pressure - admissions REST runtime - risk `high` - skipped because scan, check-in, and request mutation flows are mixed through the same file
  - `includes/integrations/ticketing-claims-framework.php` - `50` total / `16` errors / `34` warnings - `49` DB/SQL findings - dominant grants/reservations/log/schema query helpers - ticketing claims integration framework - risk `high` - skipped because schema, reservation, and mutation behavior are interleaved
  - `includes/core/goals-forecast.php` - `38` total / `0` errors / `38` warnings - `37` DB/SQL findings - dominant direct-query/no-caching, unescaped DB parameters, and interpolated table SQL - admin-only goals forecast reporting helpers - risk `low` - selected because the remaining issues were isolated to three read-only helpers and the repo already uses `%i` placeholders elsewhere
  - `includes/core/vendor-user-links.php` - `36` total / `7` errors / `29` warnings - `36` DB/SQL findings - dominant direct-query/no-caching plus mixed prepared/not-prepared dynamic read helpers - shared vendor/user access-link surface - risk `medium`/`high` - skipped because it underpins portal/access-control linkage and still mixes reads with write coordination
  - `includes/modules/admissions/vendor-guest-portal.php` - `75` total / `36` errors / `39` warnings - `35` DB/SQL findings - dominant guest-portal DB reads plus public output/i18n pressure - public/vendor guest portal - risk `high` - skipped because public response handling and request logic remain mixed with the DB helpers
- additional DB or adjacent files inspected but not selected: `includes/modules/admissions/admission-tokens.php`, `includes/core/registry/vendor-schema.php`, `includes/safety/private-files.php`, and `includes/admin/settings/class-vms-settings-tours.php`
- `includes/core/goals-forecast.php`
  - `38` -> `32`
  - `37` -> `31` DB/SQL findings
  - `5` `PluginCheck.Security.DirectDB.UnescapedDBParameter` findings -> `2`
  - `5` `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` findings -> `2`
  - limited the pass to `vms_goals_list()`, `vms_goals_get_goal()`, and `vms_goals_get_active_goal()` only, converting their existing table interpolation to `%i` identifier preparation without changing writes, save behavior, or goal selection logic
- focused validation
  - no dedicated `goals-forecast` regression exists in `tests/`
  - `php -l includes/core/goals-forecast.php` passed
  - `git diff --check` passed after the 07A doc updates
  - `php scripts/build-public-release.php --output-dir dist/wporg-07a --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-07a-plugin-check.raw.txt` and `test-results/wporg-07a-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the rerun again dropped the previously oscillating `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` unchanged, and introduced no previously unseen Plugin Check code categories

Files touched:

- `includes/core/goals-forecast.php`

Findings intentionally deferred:

- all remaining admissions, staffing, staff-task, queue/store, ticketing-claims, vendor-user-link, guest-portal, and ADD helper DB work outside this goals-forecast batch
- all remaining nonce/input, escaping/output, i18n, date/time, logging, and other runtime follow-up outside `includes/core/goals-forecast.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file is an admin-only read-only goals reporting surface, and the chosen changes stayed on table-identifier preparation for three existing read helpers only
- no save, delete, activation, upload, portal/auth, queue/store mutation, ticketing mutation, admissions mutation, Event Plans runtime, or query intent paths were changed

Net effect of the selected batch:

- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `155` -> `152` (`-3`)
- `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`: `146` -> `143` (`-3`)
- `Database and SQL safety`: `1101` -> `1095` (`-6`)
- `includes/core/goals-forecast.php`: `38` -> `32` (`-6`)
- packaged totals: `3076` -> `3069` findings, `906` -> `906` errors, `2170` -> `2163` warnings
- observed packaged rerun-only change outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `0`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
- no previously unseen Plugin Check code categories appeared

## Highest-Density Files

| File | Total | Errors | Warnings | Primary pressure |
| --- | ---: | ---: | ---: | --- |
| `includes/cpt/event-plans.php` | `241` | `108` | `133` | nonce/input + i18n + escaping |
| `includes/modules/admissions/pass-claims.php` | `173` | `23` | `150` | DB/SQL |
| `includes/core/staffing.php` | `153` | `38` | `115` | DB/SQL |
| `includes/integrations/ticketing-verifications.php` | `102` | `40` | `62` | nonce/input + i18n |
| `includes/modules/availability-date-dispatch/helpers.php` | `96` | `19` | `77` | DB/SQL |
| `includes/integrations/ticketing-rules-v2.php` | `94` | `65` | `29` | i18n |
| `includes/modules/staff-tasks/store.php` | `90` | `17` | `73` | DB/SQL |
| `includes/vendor-applications.php` | `90` | `15` | `75` | nonce/input |
| `includes/modules/admissions/vendor-guest-portal.php` | `75` | `36` | `39` | escaping + DB/SQL |
| `includes/social-share/queue-repo.php` | `73` | `7` | `66` | DB/SQL |
| `includes/integrations/ticketing-claims-admin.php` | `66` | `1` | `65` | nonce/input |
| `includes/modules/admissions/rest.php` | `65` | `11` | `54` | logging + nonce/input |
| `includes/portal/vendor-portal.php` | `63` | `0` | `63` | portal mutation input + DB/SQL |
| `includes/portal/staff-portal.php` | `59` | `25` | `34` | escaping + nonce/input |
| `includes/modules/staff-tasks/admin-ui.php` | `56` | `8` | `48` | nonce/input + escaping |

## Category Hotspots

| Category | Current count | Highest-density files |
| --- | ---: | --- |
| Nonce and input handling | `1143` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1095` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `145` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Phase

- Post-`WPORG-07A` phased follow-up
- Scope:
  - continue the DB/SQL phase while keeping to isolated read-only admin/reporting helpers where a behavior-preserving slice is obvious
  - prioritize remaining parameter-safety and preparation issues in admissions, staffing, staff-task, ADD helper, and queue/store files only when the candidate can be carved away from mutation, schema, auth, or export behavior; otherwise pause that file
  - keep the escape-only phase paused after `WPORG-06C`; the remaining output-heavy files are still shared boundaries, public/portal surfaces, metabox/save flows, vendor-assignment dashboards, or excluded Event Plans slices rather than isolated admin-only display targets
  - reserve the next nonce/input phase for mutation-coupled admin, portal, vendor-application, ticketing, and Event Plans/integration flows once dedicated regression coverage is in place
  - keep a separate i18n remainder phase for low-yield placeholder-comment leftovers such as `includes/admin/settings/class-vms-settings-notifications.php`, `includes/public/event-details.php`, and `includes/admin/staff-certifications.php` after the security-heavy phases move forward
