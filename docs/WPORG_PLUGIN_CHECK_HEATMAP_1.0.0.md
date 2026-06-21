# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: temporary packaged plugin slug extracted from `dist/wporg-04m/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `08bbe1f22254facca50dfabb096ed06b45b06126efe1111d872ac5c3202ca1e3`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3278` total findings
- `1049` errors
- `2229` warnings

Comparison to the prior packaged RC from `WPORG-04L`:

- `3290` -> `3278` total (`-12`)
- `1061` -> `1049` errors (`-12`)
- `2229` -> `2229` warnings (`0`)

## WPORG-04M Batch

- `includes/public/vendor-profiles.php`
  - `14` -> `2`
  - `12` -> `0` errors
  - `2` -> `2` warnings
  - selected because it was the highest-value remaining safe public render/i18n file already nominated in the 04L heatmap handoff and could be reduced to near-zero without query logic changes
  - added the missing `translators:` comments for the profile teaser and promo-video placeholder strings
  - wrapped inline social SVG output with a narrow local allowlist
  - wrapped the public promo-video player fragments with a narrow final-output allowlist while leaving the existing read-only upcoming-event query unchanged
- focused validation
  - no focused vendor profiles regression exists in `tests/`
  - the packaged rerun used a temporary extracted plugin slug under the local WordPress install, leaving the installed `vms/` copy untouched

Files touched:

- `includes/public/vendor-profiles.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all TEC publish/resync and ticket cleanup paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this public render/i18n batch
- the file's two existing slow-query warnings and all broader SQL follow-up

Risk notes:

- selected runtime file is a front-end public vendor profile surface, but the chosen changes stayed on placeholder comments and final output escaping of existing markup fragments
- Event Plans runtime logic and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `642` -> `634` (`-8`)
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `191` -> `187` (`-4`)
- packaged i18n placeholder-comment and ordering surface: `658` -> `650` (`-8`)
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
| Escaping and output safety | `187` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/public/templates/vendor-profile.php` |
| I18n placeholder comments and ordering | `650` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04N`
- Scope:
  - shift the next safe public render batch to `includes/public/templates/vendor-profile.php`
  - keep the pass limited to final output escaping only
  - leave broader vendor profile content behavior untouched
  - keep the pass out of Event Plans runtime, portal/profile-save flows, availability mutations, ticketing mutations, refund/cancellation flows, vendor-assignment saves, staffing mutations, and publish/TEC sync paths
