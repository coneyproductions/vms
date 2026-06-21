# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: temporary packaged plugin slug extracted from `dist/wporg-04j/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `06905c9a2c62788056adf9d99857dce37df82e4f7f87a6e7fbb57df5c0d498c5`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3408` total findings
- `1158` errors
- `2250` warnings

Comparison to the prior packaged RC from `WPORG-04I`:

- `3435` -> `3408` total (`-27`)
- `1179` -> `1158` errors (`-21`)
- `2256` -> `2250` warnings (`-6`)

## WPORG-04J Batch

- `includes/portal/staff-portal.php`
  - `86` -> `59`
  - `46` -> `25` errors
  - `40` -> `34` warnings
  - wrapped generated portal notices, badge fragments, and assignment card markup at the final output boundary with a local allowlist
  - added the missing `translators:` comments for certification detail strings, ticket counts, event status labels, and availability sync/save strings
  - allowlisted the read-only `tab` query arg instead of accepting arbitrary values
  - prepared the two read-only staffing-reporting queries with `%i` table placeholders and existing scalar placeholders
- focused validation
  - no focused Staff Portal regression exists in `tests/`
  - the packaged rerun used a temporary extracted plugin slug under the local WordPress install, leaving the installed `vms/` copy untouched

Files touched:

- `includes/portal/staff-portal.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all TEC publish/resync and ticket cleanup paths
- all staff portal auth, profile-save, upload, tax-profile save, and availability save-path input hardening outside render/output boundaries
- all broad SQL and nonce/input follow-up outside this portal render/i18n batch

Risk notes:

- selected runtime file is a front-end portal surface, but the chosen changes stayed on final output escaping, read-only tab normalization, `translators:` comments, and two behavior-preserving reporting queries
- Event Plans runtime logic and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `685` -> `669` (`-16`)
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `263` -> `260` (`-3`)
- packaged nonce/input grouping: `1217` -> `1215` (`-2`)
- packaged DB/SQL grouping: `1117` -> `1111` (`-6`)

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
| `includes/portal/staff-portal.php` | `59` | `25` | `34` | escaping + nonce/input |
| `includes/modules/admissions/vendor-guest-portal.php` | `75` | `36` | `39` | escaping + DB/SQL |

## Category Hotspots

| Category | Current count | Highest-density files |
| --- | ---: | --- |
| Nonce and input handling | `1215` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1111` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `264` `EscapeOutput` findings | `includes/portal/vendor-portal.php`, `includes/portal/staff-portal.php`, `includes/public/venue-calendar-shortcode.php`, `includes/cpt/event-plans.php` |
| I18n placeholder comments and ordering | `669` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `45` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04K`
- Scope:
  - shift the next safe render/i18n batch to `includes/portal/vendor-portal.php`
  - keep the pass out of Event Plans runtime, vendor profile-save flows, availability mutations, ticketing mutations, refund/cancellation flows, vendor-assignment saves, staffing mutations, and publish/TEC sync paths
