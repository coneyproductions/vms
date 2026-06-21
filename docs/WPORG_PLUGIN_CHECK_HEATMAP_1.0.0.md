# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: temporary packaged plugin slug extracted from `dist/wporg-04l/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `2814fe4b4867cfb67a03cef47c135dacf785963e0e46cf47af5282a40c80d03b`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3290` total findings
- `1061` errors
- `2229` warnings

Comparison to the prior packaged RC from `WPORG-04K`:

- `3319` -> `3290` total (`-29`)
- `1078` -> `1061` errors (`-17`)
- `2241` -> `2229` warnings (`-12`)

## WPORG-04L Batch

- `includes/public/venue-calendar-shortcode.php`
  - `29` -> `0`
  - `17` -> `0` errors
  - `12` -> `0` warnings
  - selected because the heatmap still nominated it as the safest remaining public render hotspot outside portal mutation paths and Event Plans runtime
  - wrapped returned markup at final output boundaries with direct `wp_kses()` calls using a narrow allowlist
  - replaced raw media wrapper concatenation with direct escaped `<a>` / `<div>` output branches
  - normalized read-only `ym`, `venue_id`, `venue`, `view`, `show_past`, and user-agent reads through helper sanitizers using `wp_unslash()` plus narrow sanitization
- focused validation
  - no focused public calendar regression exists in `tests/`
  - the packaged rerun used a temporary extracted plugin slug under the local WordPress install, leaving the installed `vms/` copy untouched

Files touched:

- `includes/public/venue-calendar-shortcode.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all TEC publish/resync and ticket cleanup paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this public render/read-only-filter batch
- all broad SQL follow-up and the `includes/public/vendor-profiles.php` slow-query warnings

Risk notes:

- selected runtime file is a front-end public calendar surface, but the chosen changes stayed on final output escaping, escaped media wrapper markup, and read-only filter parsing
- Event Plans runtime logic and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `208` -> `191` (`-17`)
- `WordPress.Security.NonceVerification.Recommended`: `607` -> `597` (`-10`)
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`: `258` -> `256` (`-2`)
- packaged nonce/input grouping: `1210` -> `1198` (`-12`)
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
| Escaping and output safety | `191` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/public/vendor-profiles.php` |
| I18n placeholder comments and ordering | `658` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04M`
- Scope:
  - shift the next safe public render/i18n batch to `includes/public/vendor-profiles.php`
  - keep the pass limited to placeholder comments and final output escaping only
  - leave the file's two slow-query warnings untouched
  - keep the pass out of Event Plans runtime, portal/profile-save flows, availability mutations, ticketing mutations, refund/cancellation flows, vendor-assignment saves, staffing mutations, and publish/TEC sync paths
