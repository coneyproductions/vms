# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: temporary packaged plugin slug extracted from `dist/wporg-04p/vms-1.0.0-public-release.zip`
- Artifact SHA-256: `720dc9a32f3609ebb54ef77227b0cf85123776554f7b62c347e8a77077fcf152`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp plugin check vms --mode=new --format=json`

## Current Result

- `3268` total findings
- `1043` errors
- `2225` warnings

Comparison to the prior packaged RC from `WPORG-04O`:

- `3270` -> `3268` total (`-2`)
- `1045` -> `1043` errors (`-2`)
- `2225` -> `2225` warnings (`0`)

## WPORG-04P Batch

- `includes/social-share/audit.php`
  - `7` -> `5`
  - `2` -> `0` errors
  - `5` -> `5` warnings
  - selected because `includes/modules/admissions/admin-ui.php` remained the safest warning-only SQL follow-up, but `WPORG-04P` specifically needed a low-risk file with real DB/SQL errors, and `audit.php` was the smallest remaining read-only audit/reporting surface outside ticketing, notifications, admissions REST, vendor-linking, and portal-save flows
  - limited the pass to the existing read-only `vms_social_audit_recent()` query branches only
  - converted the dynamic audit table reference to prepared `%i` identifiers and moved the prepared SQL directly into each existing `get_results()` call without changing search behavior, ordering, limits, or result shape
- focused validation
  - no focused social audit regression exists in `tests/`
  - the packaged rerun used a temporary extracted plugin slug under the local WordPress install, leaving the installed `vms/` copy untouched

Files touched:

- `includes/social-share/audit.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all TEC publish/resync and ticket cleanup paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this read-only SQL batch
- all broader SQL, nonce/input, and i18n follow-up outside the selected social audit readback helper

Risk notes:

- selected file is a social audit readback surface, but the chosen changes stayed on prepared identifier handling and direct prepared query handoff inside the existing read-only search/no-search branches only
- Event Plans runtime logic, social posting/queue mutation paths, notifications, and other mutation-heavy flows were intentionally untouched

Net effect of the selected batch:

- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `156` -> `155` (`-1`)
- `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`: `148` -> `146` (`-2`)
- `WordPress.DB.PreparedSQL.NotPrepared`: `73` -> `72` (`-1`)
- `WordPress.DB.DirectDatabaseQuery.DirectQuery`: `293` -> `294` (`+1`)
- `WordPress.DB.DirectDatabaseQuery.NoCaching`: `255` -> `256` (`+1`)
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
| Database and SQL safety | `1101` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `183` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `650` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04Q`
- Scope:
  - shift the next safe read-only SQL batch to `includes/modules/admissions/admin-ui.php`
  - keep the pass limited to the guest-list export CSV read query and table-identifier preparation only
  - leave the remaining `fclose()` filesystem finding, guest-list mutation behavior, REST handlers, permissions, and Event Plans runtime untouched
  - keep the pass out of ticketing mutations, refund/cancellation flows, portal/profile-save flows, availability mutations, staffing mutations, and publish/TEC sync paths
