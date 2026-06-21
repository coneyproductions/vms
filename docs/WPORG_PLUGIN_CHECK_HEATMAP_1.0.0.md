# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-20

## Scope

- Scan target: temporary packaged plugin slug extracted from `dist/wporg-04o/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `b5ff1494aa35b48e3d108f51d8efc584bacde4fbeceb433acca60ebdac06b690`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3270` total findings
- `1045` errors
- `2225` warnings

Comparison to the prior packaged RC from `WPORG-04N`:

- `3274` -> `3270` total (`-4`)
- `1045` -> `1045` errors (`0`)
- `2229` -> `2225` warnings (`-4`)

## WPORG-04O Batch

- `includes/social-share/template-engine.php`
  - `8` -> `4`
  - `0` -> `0` errors
  - `8` -> `4` warnings
  - selected because it was the highest-value remaining low-risk DB/SQL file in the handoff set that stayed on read-only template lookups and sat outside Event Plans runtime, ticketing, portal saves, and mutation-heavy social queue flows
  - limited the pass to table-identifier preparation only in two existing read-only lookups
  - converted the two dynamic table references in `vms_social_template_get()` and `vms_social_template_default_for_platform()` to prepared `%i` identifiers without changing selected columns, filters, ordering, limits, or return shape
- focused validation
  - no focused social template-engine regression exists in `tests/`
  - the packaged rerun used a temporary extracted plugin slug under the local WordPress install, leaving the installed `vms/` copy untouched

Files touched:

- `includes/social-share/template-engine.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all TEC publish/resync and ticket cleanup paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this read-only SQL batch
- all broader SQL, nonce/input, and i18n follow-up outside the two read-only social template lookup helpers

Risk notes:

- selected file is a social template lookup surface, but the chosen changes stayed on read-only table-identifier preparation in two existing queries only
- Event Plans runtime logic, social posting/queue mutation paths, and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `158` -> `156` (`-2`)
- `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`: `150` -> `148` (`-2`)
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
| Database and SQL safety | `1103` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `183` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `650` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04P`
- Scope:
  - shift the next safe read-only SQL batch to `includes/modules/admissions/admin-ui.php`
  - keep the pass limited to the guest-list export CSV read query and table-identifier preparation only
  - leave guest-list mutation behavior, REST handlers, permissions, and Event Plans runtime untouched
  - keep the pass out of ticketing mutations, refund/cancellation flows, portal/profile-save flows, availability mutations, staffing mutations, and publish/TEC sync paths
