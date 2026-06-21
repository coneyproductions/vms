# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: temporary packaged plugin slug extracted from `dist/wporg-04n/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `51c6d2c127845440ffce9eee2c07428ce67b5c8dc90a1b3208c6a0601680b8a9`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3274` total findings
- `1045` errors
- `2229` warnings

Comparison to the prior packaged RC from `WPORG-04M`:

- `3278` -> `3274` total (`-4`)
- `1049` -> `1045` errors (`-4`)
- `2229` -> `2229` warnings (`0`)

## WPORG-04N Batch

- `includes/public/templates/vendor-profile.php`
  - `4` -> `0`
  - `4` -> `0` errors
  - `0` -> `0` warnings
  - selected because it was the highest-value remaining safe public render file already nominated in the 04M handoff and could be cleared mechanically without touching request, query, or mutation logic
  - added one shared final-output allowlist for the vendor profile render surface
  - wrapped the social-links fragment, next-show card fragment, filtered profile content, and featured-video embed at the template output boundary
- focused validation
  - no focused vendor profile template regression exists in `tests/`
  - the packaged rerun used a temporary extracted plugin slug under the local WordPress install, leaving the installed `vms/` copy untouched

Files touched:

- `includes/public/templates/vendor-profile.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all TEC publish/resync and ticket cleanup paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this public render-only batch
- all broader SQL, nonce/input, and i18n follow-up outside this template file

Risk notes:

- selected runtime file is a front-end public vendor profile surface, but the chosen changes stayed on final output escaping of existing markup fragments only
- Event Plans runtime logic and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `187` -> `183` (`-4`)
- no new Plugin Check code categories appeared

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
| `includes/core/vendor-user-links.php` | `68` | `39` | `29` | escaping + i18n |
| `includes/integrations/ticketing-claims-admin.php` | `66` | `1` | `65` | nonce/input |
| `includes/modules/admissions/rest.php` | `65` | `11` | `54` | logging + nonce/input |
| `includes/portal/vendor-portal.php` | `63` | `0` | `63` | portal mutation input + DB/SQL |
| `includes/portal/staff-portal.php` | `59` | `25` | `34` | escaping + nonce/input |

## Category Hotspots

| Category | Current count | Highest-density files |
| --- | ---: | --- |
| Nonce and input handling | `1198` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1107` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `183` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `650` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04O`
- Scope:
  - shift the next safe shared admin render batch to `includes/admin-ui/shell.php`
  - keep the pass limited to final output escaping of existing shell fragments and notices only
  - leave caller-generated shell content behavior untouched
  - keep the pass out of Event Plans runtime, portal/profile-save flows, availability mutations, ticketing mutations, refund/cancellation flows, vendor-assignment saves, staffing mutations, and publish/TEC sync paths
