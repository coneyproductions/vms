# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: temporary packaged plugin slug extracted from `dist/wporg-04k/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `894cf8280489f4d52561be45e88b4ee317693ad2b61cc400c45ad41b4dceb209`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3319` total findings
- `1078` errors
- `2241` warnings

Comparison to the prior packaged RC from `WPORG-04J`:

- `3408` -> `3319` total (`-89`)
- `1158` -> `1078` errors (`-80`)
- `2250` -> `2241` warnings (`-9`)

## WPORG-04K Batch

- `includes/portal/vendor-portal.php`
  - `152` -> `63`
  - `80` -> `0` errors
  - `72` -> `63` warnings
  - wrapped generated portal notices, preview/media fragments, and option/details markup at the final output boundary with local escaping or allowlisted HTML
  - added the missing `translators:` comments for assignment detail strings, request-status copy, gallery labels, and other placeholder-bearing portal strings
  - allowlisted the read-only `tab`, `vendor_id`, and `lookback` request args instead of accepting arbitrary values
  - prepared the two read-only admissions-reporting queries with `%i` table placeholders and existing scalar placeholders
  - replaced one display-only fallback `date()` call with `gmdate()` in the next-booking helper
- focused validation
  - no focused Vendor Portal regression exists in `tests/`
  - the packaged rerun used a temporary extracted plugin slug under the local WordPress install, leaving the installed `vms/` copy untouched

Files touched:

- `includes/portal/vendor-portal.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all TEC publish/resync and ticket cleanup paths
- all vendor portal auth, profile-save, upload, availability save-path, and link-request input hardening outside render/output boundaries
- all broad SQL and nonce/input follow-up outside this portal render/i18n batch

Risk notes:

- selected runtime file is a front-end portal surface, but the chosen changes stayed on final output escaping, read-only request normalization, `translators:` comments, one display-only date helper, and two behavior-preserving reporting queries
- Event Plans runtime logic and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `669` -> `642` (`-27`)
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `260` -> `208` (`-52`)
- packaged nonce/input grouping: `1215` -> `1210` (`-5`)
- packaged DB/SQL grouping: `1111` -> `1107` (`-4`)
- packaged `date()` usage: `45` -> `44` (`-1`)

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
| `includes/portal/vendor-portal.php` | `63` | `0` | `63` | portal mutation input + DB/SQL |
| `includes/portal/staff-portal.php` | `59` | `25` | `34` | escaping + nonce/input |

## Category Hotspots

| Category | Current count | Highest-density files |
| --- | ---: | --- |
| Nonce and input handling | `1210` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1107` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `208` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/public/venue-calendar-shortcode.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php` |
| I18n placeholder comments and ordering | `658` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04L`
- Scope:
  - shift the next safe public render/i18n batch to `includes/public/venue-calendar-shortcode.php`, with small follow-up slices in `includes/public/vendor-profiles.php` or `includes/public/event-details.php` only if the main file lands cleanly
  - keep the pass out of Event Plans runtime, portal/profile-save flows, availability mutations, ticketing mutations, refund/cancellation flows, vendor-assignment saves, staffing mutations, and publish/TEC sync paths
