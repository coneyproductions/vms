# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: installed package built from `dist/wporg-04g/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `e2f4f6a45593b26c319dea37b4179f174e54558aa25acdc0a1131f6cbe553f6d`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3554` total findings
- `1266` errors
- `2288` warnings

Comparison to the prior packaged RC from `WPORG-04E`:

- `3605` -> `3554` total (`-51`)
- `1316` -> `1266` errors (`-50`)
- `2289` -> `2288` warnings (`-1`)

## WPORG-04G Batch

- `includes/admin/vendor-command-center.php`
  - `51` -> `29`
  - `22` -> `0` errors
  - wrapped pill helper output at final render sites with `wp_kses_post()`
  - added missing `translators:` comments on placeholder strings
- `includes/admin/vendor-availability.php`
  - `50` -> `22`
  - `28` -> `0` errors
  - wrapped remaining pill helper output at final render sites
  - replaced the remaining display-only `date()` calls with `DateTimeImmutable` helpers
  - added missing `translators:` comments on placeholder strings
- focused packaged-validation regressions
  - `tests/vendor-availability-ux.php`
  - `tests/add-dispatch-open-vendor-needs.php`
  - both now use `tests/bootstrap-wordpress.php` instead of hardcoded `dirname(__DIR__, 4) . '/wp-load.php'`

Files touched:

- `includes/admin/vendor-command-center.php`
- `includes/admin/vendor-availability.php`
- `tests/vendor-availability-ux.php`
- `tests/add-dispatch-open-vendor-needs.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation and live refund request flows
- all TEC publish/resync, ticket cleanup, vendor assignment, staffing, and cancellation mutation paths
- all broad SQL and nonce/input follow-up outside this admin render-only batch

Risk notes:

- selected runtime files are low-risk admin-only reporting surfaces outside Event Plans
- runtime changes stayed on final output escaping, `translators:` comments, and display-only date handling
- Event Plans runtime logic and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `767` -> `738` (`-29`)
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `313` -> `296` (`-17`)
- `WordPress.DateTime.RestrictedFunctions.date_date`: `50` -> `46` (`-4`)

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
| Database and SQL safety | `1118` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `300` `OutputNotEscaped` findings | `includes/portal/vendor-portal.php`, `includes/portal/staff-portal.php`, `includes/admin/staffing.php`, `includes/public/venue-calendar-shortcode.php` |
| I18n placeholder comments and ordering | `760` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/admin/event-command-center.php`, `includes/integrations/ticketing-verifications.php` |
| Date/time API usage | `46` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/admin/vendor-availability.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04H`
- Scope:
  - take the next safe admin-only error-heavy batch in `includes/admin/event-command-center.php`
  - focus on `translators:` comments plus final render-surface escaping only, without widening into Event Plans runtime, ticketing mutations, or vendor assignment saves
