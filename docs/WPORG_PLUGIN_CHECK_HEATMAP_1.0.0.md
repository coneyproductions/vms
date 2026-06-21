# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-04t/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `3943d3219317a3099c29d4d9678ae266c93aa762fa21b8852efc5f258fadb4ac`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3175` total findings
- `950` errors
- `2225` warnings

Comparison to the prior packaged RC from `WPORG-04S`:

- `3205` -> `3175` total (`-30`)
- `980` -> `950` errors (`-30`)
- `2225` -> `2225` warnings (`0`)

## WPORG-04T Batch

- 04T candidate scan summary
  - `includes/admin/schedule.php` - `52` total / `30` errors / `22` warnings - dominant `WordPress.DateTime.RestrictedFunctions.date_date` (`17`) plus `WordPress.Security.EscapeOutput.OutputNotEscaped` (`13`) - isolated to admin list/calendar render fragments and view-window helpers while the `admin_post_vms_create_event_plan` handler sits elsewhere in the file - risk `medium` - selected as the safest remaining isolated render/helper cluster
  - `includes/admin/settings-page.php` - `48` total / `20` errors / `28` warnings - dominant `EscapeOutput` (`9`), `MissingTranslatorsComment` (`8`), and `date()` (`2`) - findings are mixed into request handling, nonce checks, logging, file handles, and ticket-integrity tooling - risk `high` - skipped
  - `includes/modules/availability-date-dispatch/admin-ui.php` - `30` total / `21` errors / `9` warnings - dominant `EscapeOutput` (`14`) plus `MissingTranslatorsComment` (`7`) - render findings are mixed into availability-dispatch admin UI and accompanied by nonce warnings - risk `high` - skipped
  - `includes/admin/ticket-integrity-page.php` - `48` total / `28` errors / `20` warnings - dominant `MissingTranslatorsComment` (`21`), `EscapeOutput` (`5`), and `date()` (`2`) - findings sit in ticketing-integrity runtime and reporting paths - risk `high` - skipped
  - `includes/portal/staff-portal.php` - `59` total / `25` errors / `34` warnings - dominant `EscapeOutput` (`23`) - output findings are mixed into auth and portal request flows - risk `high` - skipped
  - `includes/core/vendor-application-confirmation.php` - `53` total / `19` errors / `34` warnings - dominant mixed i18n, escaping, request/auth, and DB/SQL codes - findings are not isolated from user-resolution and email-confirmation behavior - risk `high` - skipped
  - `includes/core/event-plan-save-profiler.php` - `32` total / `17` errors / `15` warnings - dominant `MissingTranslatorsComment` (`16`) - the file is Event Plans runtime-adjacent and outside this batch's allowed scope - risk `high` - skipped
- `includes/admin/schedule.php`
  - `52` -> `22`
  - `30` -> `0` errors
  - `22` -> `22` warnings
  - cleared the file's `17` `date()` findings and `13` final-output escaping findings while leaving the existing read-only request warnings and slow-query warning untouched
  - limited the pass to view-window helpers, date iteration, localized date formatting, and final escaping of existing admin markup fragments only
- focused validation
  - no focused admin-schedule regression exists in `tests/`
  - `php -l includes/admin/schedule.php` passed
  - `git diff --check` passed
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - WP-CLI emitted the known phar deprecation line during the packaged rerun; the cleaned raw findings stayed in `docs/plugin-check-1.0.0-raw.txt`, and stderr was captured in `test-results/wporg-04t-plugin-check.stderr.txt`
  - the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope without introducing any new Plugin Check code categories

Files touched:

- `includes/admin/schedule.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all settings-page, ticket-integrity, availability-dispatch, and portal/auth render work that is still mixed into mutation-heavy flows
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all ticketing, checkout, refund, cancellation, and TEC publish/resync paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this admin-render/date batch
- all broader SQL, nonce/input, and shared-runtime follow-up outside `includes/admin/schedule.php`

Risk notes:

- selected file contains an Event Plan creation handler, but the chosen changes stayed in admin-only render helpers and date/window utilities and did not alter nonce checks, create args, or mutation routing
- settings-page, ticketing, availability-dispatch, vendor confirmation, portal/auth, Event Plans runtime, refunds, and other request-heavy surfaces were intentionally untouched

Net effect of the selected batch:

- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `183` -> `170` (`-13`)
- `WordPress.DateTime.RestrictedFunctions.date_date`: `44` -> `27` (`-17`)
- `includes/admin/schedule.php`: `52` -> `22` (`-30`)
- observed packaged rerun-only non-target steady state outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `1` (`0`, unchanged)
- no previously unseen Plugin Check code categories appeared

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
| `includes/integrations/ticketing-claims-admin.php` | `66` | `1` | `65` | nonce/input |
| `includes/modules/admissions/rest.php` | `65` | `11` | `54` | logging + nonce/input |
| `includes/portal/vendor-portal.php` | `63` | `0` | `63` | portal mutation input + DB/SQL |
| `includes/portal/staff-portal.php` | `59` | `25` | `34` | escaping + nonce/input |
| `includes/modules/staff-tasks/admin-ui.php` | `56` | `8` | `48` | nonce/input + escaping |

## Category Hotspots

| Category | Current count | Highest-density files |
| --- | ---: | --- |
| Nonce and input handling | `1198` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1101` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `170` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `587` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04U`
- Scope:
  - repeat the deliberate hotspot scan from the `WPORG-04T` packaged baseline and prefer another isolated display-only date or render-only escaping batch before widening into request, auth, refund, ticketing, or availability-save flows
  - `includes/modules/staff-tasks/notifications.php` or shared helper/date surfaces are better candidates than ticketing, payables, or vendor-confirmation runtime files if the remaining `date()` calls stay isolated
  - if packaging-warning cleanup is preferred over another code batch, handle the unchanged `plugin_header_nonexistent_domain_path` warning in a dedicated metadata micro-batch instead of widening runtime scope
