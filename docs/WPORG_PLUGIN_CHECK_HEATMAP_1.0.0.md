# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: temporary packaged plugin slug extracted from `dist/wporg-04i/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `aceda39376ec454c49106a1a41ec88a96ec5ff49acfb97ae730308c93120aaa8`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3435` total findings
- `1179` errors
- `2256` warnings

Comparison to the prior packaged RC from `WPORG-04H`:

- `3491` -> `3435` total (`-56`)
- `1203` -> `1179` errors (`-24`)
- `2288` -> `2256` warnings (`-32`)

## WPORG-04I Batch

- `includes/admin/staffing.php`
  - `59` -> `3`
  - `24` -> `0` errors
  - `35` -> `3` warnings
  - moved template-payload normalization behind verified request data instead of direct helper-level `$_POST` reads
  - escaped template row field names at the final output site and wrapped helper-generated option/row HTML with explicit allowlists
  - added the missing `translators:` comments in the template list and rollup results
  - prepared the rollup dirty-count query with `%i` and `%d`
- focused validation
  - no dedicated staffing admin regression exists in `tests/`
  - the packaged rerun used a temporary extracted plugin slug under the local WordPress install, leaving the installed `vms/` copy untouched

Files touched:

- `includes/admin/staffing.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all TEC publish/resync and ticket cleanup paths
- the remaining role-meta input warning and the direct-query/no-caching pair in `staffing.php`
- all broad SQL and nonce/input follow-up outside this admin render-only batch

Risk notes:

- selected runtime file is a low-risk admin-only staffing management surface outside Event Plans
- runtime changes stayed on final output escaping, verified request normalization, `translators:` comments, and one narrow behavior-preserving rollup count query preparation
- Event Plans runtime logic and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `692` -> `685` (`-7`)
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `284` -> `263` (`-21`)
- packaged nonce/input grouping: `1247` -> `1217` (`-30`)
- packaged DB/SQL grouping: `1119` -> `1117` (`-2`)

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
| Nonce and input handling | `1217` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1117` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `267` `EscapeOutput` findings | `includes/portal/vendor-portal.php`, `includes/portal/staff-portal.php`, `includes/public/venue-calendar-shortcode.php`, `includes/cpt/event-plans.php` |
| I18n placeholder comments and ordering | `685` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `45` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04J`
- Scope:
  - take the next safe render/i18n batch in `includes/portal/staff-portal.php`
  - keep the pass out of Event Plans runtime, ticketing mutations, refund/cancellation flows, vendor-assignment saves, staffing mutations, and publish/TEC sync paths
