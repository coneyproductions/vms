# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-19

## Scope

- Scan target: installed package built from `dist/wporg-04d/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `7987b619acec510e397677074eba3f0442a8511b2a5492112583fc5f7ea9e6f3`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3692` total findings
- `1316` errors
- `2376` warnings

Comparison to the prior packaged RC from `WPORG-04B`:

- `3695` -> `3692` total (`-3`)
- `1317` -> `1316` errors (`-1`)
- `2378` -> `2376` warnings (`-2`)

## WPORG-04D Batch

- `includes/cpt/event-plans.php`
  - `244` -> `241`
  - limited the micro-slice to the existing admin Event Plans list `include_drafts` block
  - added an explicit raw-read PHPCS ignore to the centralized list-filter helper
  - escaped the rendered help button with `wp_kses_post()` at final output

Files touched:

- `includes/cpt/event-plans.php`

Findings intentionally deferred:

- all remaining `save_event_plan_meta()` request hardening
- all publish validation and live refund request flows
- all TEC publish/resync, ticket cleanup, vendor assignment, staffing, and cancellation mutation paths
- all broad render-block escaping outside the protected list-toggle area

Risk notes:

- `event-plans.php`: low risk for the selected slice; admin list/filter output only
- save/publish/ticketing/cancellation/vendor/staffing/TEC/Woo mutation paths were intentionally untouched

Net effect of the selected batch:

- `WordPress.Security.NonceVerification.Recommended`: `644` -> `643` (`-1`)
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`: `274` -> `273` (`-1`)
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `314` -> `313` (`-1`)

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
| Nonce and input handling | `1335` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1119` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `313` `OutputNotEscaped` findings | `includes/portal/vendor-portal.php`, `includes/portal/staff-portal.php`, `includes/admin/staffing.php`, `includes/public/venue-calendar-shortcode.php` |
| I18n placeholder comments and ordering | `783` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/admin/event-command-center.php`, `includes/integrations/ticketing-verifications.php` |
| Date/time API usage | `50` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/admin/vendor-availability.php` |
| Development logging | `42` `error_log()` findings | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04F`
- Scope:
  - take a dedicated high-risk Event Plans request/save hardening pass with new regression coverage around `save_event_plan_meta()`, validation, live refunds, and TEC/ticketing side effects
  - keep another low-risk non-Event-Plans batch as a separate `WPORG-04E` option if the immediate priority is packaged-count reduction over Event Plan hardening depth
