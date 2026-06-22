# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-22

## Scope

- Scan target: extracted packaged directory from `dist/wporg-07b/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `275f5ecf22f4170f1824ce85617bfad10e51d9d7db8237fa4de89d69e173adbc`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3061` total findings
- `898` errors
- `2163` warnings

Comparison to the prior packaged RC from `WPORG-07A`:

- `3069` -> `3061` total (`-8`)
- `906` -> `898` errors (`-8`)
- `2163` -> `2163` warnings (`0`)

## WPORG-07B Batch

- 07B candidate scan summary
  - reviewed ten DB-heavy files before selection: `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php`, `includes/social-share/queue-repo.php`, `includes/modules/admissions/rest.php`, `includes/integrations/ticketing-claims-framework.php`, `includes/core/vendor-user-links.php`, `includes/modules/admissions/vendor-guest-portal.php`, and `includes/core/goals-forecast.php`
  - selected `includes/modules/admissions/pass-claims.php` because its four report aggregates were isolated to the admin reports tab and admin report CSV export, read-only, and preparable without changing query shape or runtime behavior
  - skipped the other nine because they remain mutation-coupled, public/portal-facing, ticketing-linked, access-control-linked, or already exhausted their last low-risk read-only slice
- `includes/modules/admissions/pass-claims.php`
  - `173` -> `165`
  - `133` -> `125` DB/SQL findings
  - `22` `PluginCheck.Security.DirectDB.UnescapedDBParameter` findings -> `18`
  - `5` `WordPress.DB.PreparedSQL.NotPrepared` findings -> `1`
  - limited the pass to `vms_pass_claims_reports_by_source()`, `vms_pass_claims_reports_by_batch()`, `vms_pass_claims_reports_source_events()`, and `vms_pass_claims_reports_by_event()` only
- focused validation
  - no dedicated `pass-claims` reporting regression exists in `tests/`
  - `php -l includes/modules/admissions/pass-claims.php` passed
  - `git diff --check` passed after the 07A doc updates
  - `php scripts/build-public-release.php --output-dir dist/wporg-07b --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-07b-plugin-check.raw.txt` and `test-results/wporg-07b-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the rerun reintroduced the previously observed `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` unchanged, and introduced no previously unseen Plugin Check code categories

Files touched:

- `includes/modules/admissions/pass-claims.php`

Findings intentionally deferred:

- all remaining admissions, staffing, staff-task, queue/store, ticketing-claims, vendor-user-link, guest-portal, and ADD helper DB work outside this pass-claims report batch
- all remaining nonce/input, escaping/output, i18n, date/time, logging, and other runtime follow-up outside `includes/modules/admissions/pass-claims.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file is a mixed admissions file, but the chosen changes stayed on the admin-only read-only report aggregates used by the reports tab and report export only
- no save, delete, activation, upload, portal/auth, queue/store mutation, ticketing mutation, admissions mutation, Event Plans runtime, or query intent paths were changed

Net effect of the selected batch:

- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `152` -> `148` (`-4`)
- `WordPress.DB.PreparedSQL.NotPrepared`: `72` -> `68` (`-4`)
- `Database and SQL safety`: `1095` -> `1087` (`-8`)
- `includes/modules/admissions/pass-claims.php`: `173` -> `165` (`-8`)
- packaged totals: `3069` -> `3061` findings, `906` -> `898` errors, `2163` -> `2163` warnings
- observed packaged rerun-only change outside the selected file scope: `plugin_header_nonexistent_domain_path`: `0` -> `1`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
- no previously unseen Plugin Check code categories appeared

## Highest-Density Files

| File | Total | Errors | Warnings | Primary pressure |
| --- | ---: | ---: | ---: | --- |
| `includes/cpt/event-plans.php` | `241` | `108` | `133` | nonce/input + i18n + escaping |
| `includes/modules/admissions/pass-claims.php` | `165` | `15` | `150` | DB/SQL |
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
| Database and SQL safety | `1087` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `145` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Phase

- Post-`WPORG-07B` phased follow-up
- Scope:
  - pause broad DB/SQL cleanup unless another equivalently isolated admin/reporting read-only slice is identified
  - if the DB/SQL phase continues, keep prioritizing real parameter-safety and preparation issues before generic direct-query/no-caching warnings
  - otherwise switch to a low-risk i18n remainder batch or prepare regression coverage for the mutation-coupled nonce/input backlog before widening security hardening again
  - keep the escape-only phase paused after `WPORG-06C`; the remaining output-heavy files are still shared boundaries, public/portal surfaces, metabox/save flows, vendor-assignment dashboards, or excluded Event Plans slices rather than isolated admin-only display targets
