# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: temporary packaged plugin slug extracted from `dist/wporg-04h/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `b66aded43d758b2d8bc5de66b57f8ceb8e69927d89eb91c6dadf1a26ed9a734c`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3491` total findings
- `1203` errors
- `2288` warnings

Comparison to the prior packaged RC from `WPORG-04G`:

- `3554` -> `3491` total (`-63`)
- `1266` -> `1203` errors (`-63`)
- `2288` -> `2288` warnings (`0`)

## WPORG-04H Batch

- `includes/admin/event-command-center.php`
  - `79` -> `15`
  - `63` -> `0` errors
  - `16` -> `15` warnings
  - wrapped final helper-generated HTML at the render boundary with `wp_kses()`
  - normalized one `external_url` request read and the two `$_GET` reads already in this file
  - replaced the remaining display-only `date()` sort normalization with `DateTimeImmutable`
  - added missing `translators:` comments on placeholder strings
- focused validation
  - no dedicated Event Command Center regression exists in `tests/`
  - the packaged rerun used a temporary extracted plugin slug under the local WordPress install, leaving the installed `vms/` copy untouched

Files touched:

- `includes/admin/event-command-center.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all TEC publish/resync and ticket cleanup paths
- the remaining nonce-verification recommendations and the one `slow_db_query_meta_key` warning in `event-command-center.php`
- all broad SQL and nonce/input follow-up outside this admin render-only batch

Risk notes:

- selected runtime file is a low-risk admin-only reporting surface outside Event Plans
- runtime changes stayed on final output escaping, request normalization already local to the file, `translators:` comments, and display-only date handling
- Event Plans runtime logic and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `738` -> `692` (`-46`)
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `296` -> `284` (`-12`)
- `WordPress.DateTime.RestrictedFunctions.date_date`: `46` -> `45` (`-1`)
- packaged nonce/input grouping: `1248` -> `1247` (`-1`)

## Highest-Density Files

| File | Total | Errors | Warnings | Primary pressure |
| --- | ---: | ---: | ---: | --- |
| `includes/cpt/event-plans.php` | `241` | `108` | `133` | nonce/input + i18n + escaping |
| `includes/modules/admissions/pass-claims.php` | `173` | `23` | `150` | DB/SQL |
| `includes/core/staffing.php` | `153` | `38` | `115` | DB/SQL |
| `includes/portal/vendor-portal.php` | `152` | `80` | `72` | escaping + nonce/input |
| `includes/integrations/ticketing-verifications.php` | `102` | `40` | `62` | nonce/input + i18n |
| `includes/modules/availability-date-dispatch/helpers.php` | `96` | `19` | `77` | DB/SQL |
| `includes/integrations/ticketing-rules-v2.php` | `94` | `65` | `29` | i18n |
| `includes/modules/staff-tasks/store.php` | `90` | `17` | `73` | DB/SQL |
| `includes/vendor-applications.php` | `90` | `15` | `75` | nonce/input |
| `includes/portal/staff-portal.php` | `86` | `46` | `40` | escaping + nonce/input |
| `includes/modules/admissions/vendor-guest-portal.php` | `75` | `36` | `39` | escaping + DB/SQL |

## Category Hotspots

| Category | Current count | Highest-density files |
| --- | ---: | --- |
| Nonce and input handling | `1247` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1119` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `284` `OutputNotEscaped` findings | `includes/portal/vendor-portal.php`, `includes/portal/staff-portal.php`, `includes/admin/staffing.php`, `includes/public/venue-calendar-shortcode.php` |
| I18n placeholder comments and ordering | `692` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `45` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04I`
- Scope:
  - take the next safe admin-only render/i18n batch in `includes/admin/staffing.php`
  - keep the pass out of Event Plans runtime, ticketing mutations, refund/cancellation flows, vendor-assignment saves, staffing mutations, and publish/TEC sync paths
