# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: installed package built from `dist/wporg-04e/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `ca120b97c574ccdd72bb124defc8e712ed7291f4f9730d334423b6b1176d34be`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3605` total findings
- `1316` errors
- `2289` warnings

Comparison to the prior packaged RC from `WPORG-04D`:

- `3692` -> `3605` total (`-87`)
- `1316` -> `1316` errors (`0`)
- `2376` -> `2289` warnings (`-87`)

## WPORG-04E Batch

- `includes/admin/due-dates.php`
  - `46` -> `0`
  - added a centralized read-only admin query helper for routed message and filter parameters
  - unslashed and sanitized flagged `$_POST` values before use
  - sanitized textarea-based payee identifiers before parsing
- `includes/admin/holidays.php`
  - `41` -> `0`
  - centralized read-only GET, REQUEST, and POST helpers for admin routing and delegated reads
  - normalized flagged request access without changing the admin flow

Files touched:

- `includes/admin/due-dates.php`
- `includes/admin/holidays.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation and live refund request flows
- all TEC publish/resync, ticket cleanup, vendor assignment, staffing, and cancellation mutation paths
- all broad render-block escaping follow-up outside this admin-request batch

Risk notes:

- selected files are low-risk admin-only request surfaces outside Event Plans
- Event Plans runtime logic and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `WordPress.Security.NonceVerification.Recommended`: `643` -> `614` (`-29`)
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`: `273` -> `260` (`-13`)
- `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`: `268` -> `233` (`-35`)
- `WordPress.Security.NonceVerification.Missing`: `129` -> `119` (`-10`)

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
| `includes/admin/event-command-center.php` | `79` | `63` | `16` | i18n + escaping |
| `includes/modules/admissions/vendor-guest-portal.php` | `75` | `36` | `39` | escaping + DB/SQL |

## Category Hotspots

| Category | Current count | Highest-density files |
| --- | ---: | --- |
| Nonce and input handling | `1248` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1119` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `313` `OutputNotEscaped` findings | `includes/portal/vendor-portal.php`, `includes/portal/staff-portal.php`, `includes/admin/staffing.php`, `includes/public/venue-calendar-shortcode.php` |
| I18n placeholder comments and ordering | `783` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/admin/event-command-center.php`, `includes/integrations/ticketing-verifications.php` |
| Date/time API usage | `50` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/admin/vendor-availability.php` |
| Development logging | `42` `error_log()` findings | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04F`
- Scope:
  - take a dedicated high-risk Event Plans request/save hardening pass with new regression coverage around `save_event_plan_meta()`, validation, live refunds, and TEC/ticketing side effects
  - use the expanded nested-repo-safe Event Plans regression set as the gate before widening request changes there
