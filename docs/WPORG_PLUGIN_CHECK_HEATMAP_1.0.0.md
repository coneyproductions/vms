# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-04u/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `1da175f784580f21806ae4dc2aa2c214f94d83d032e8b04bd8c3666467399f4c`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3170` total findings
- `945` errors
- `2225` warnings

Comparison to the prior packaged RC from `WPORG-04T`:

- `3175` -> `3170` total (`-5`)
- `950` -> `945` errors (`-5`)
- `2225` -> `2225` warnings (`0`)

## WPORG-04U Batch

- 04U candidate scan summary
  - `includes/admin/staff-list-columns.php` - `7` total / `5` errors / `2` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`4`) plus `WordPress.Security.EscapeOutput.OutputNotEscaped` (`1`) - isolated to admin list-column certification badges and contact render - risk `low` - selected as the safest remaining admin-only error slice
  - `includes/admin/approvals-review-queue.php` - `11` total / `7` errors / `4` warnings - dominant `WordPress.Security.EscapeOutput.OutputNotEscaped` (`4`) plus `WordPress.WP.I18n.MissingTranslatorsComment` (`3`) - mostly isolated to admin approvals render, but coupled to guided-tour helper HTML, provider URLs, and review workflow routing - risk `medium` - skipped in favor of the cleaner list-column surface
  - `includes/admin/menu.php` - `8` total / `5` errors / `3` warnings - dominant `WordPress.Security.EscapeOutput.OutputNotEscaped` (`4`) plus `WordPress.WP.I18n.MissingTranslatorsComment` (`1`) - admin dashboard render only, but includes helper-generated tour button HTML and pre-existing request/logging warnings - risk `medium` - skipped
  - `includes/admin-ui/shell.php` - `4` total / `4` errors / `0` warnings - dominant `WordPress.Security.EscapeOutput.OutputNotEscaped` (`4`) - isolated shared admin-shell output, but every fix would alter a common render boundary used across multiple screens - risk `medium` - skipped because the blast radius is wider than the file size suggests
  - `includes/public/calendar-ics.php` - `8` total / `5` errors / `3` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`4`) plus `WordPress.Security.EscapeOutput.OutputNotEscaped` (`1`) - mixed with read-only request parsing and raw ICS response output - risk `medium` - skipped because the calendar payload should not be blindly escaped
  - `includes/helpers.php` - `15` total / `6` errors / `9` warnings - dominant `WordPress.DateTime.RestrictedFunctions.date_date` (`3`), `WordPress.WP.I18n.MissingTranslatorsComment` (`2`), and `WordPress.Security.EscapeOutput.OutputNotEscaped` (`1`) - findings are mixed through shared helper/date utilities, query helpers, and dashboard form output - risk `high` - skipped
  - `includes/core/vendor-document-alerts.php` - `8` total / `8` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`8`) - isolated i18n only, but the file is notification-behavior-adjacent - risk `high` - skipped
  - `includes/modules/staff-tasks/notifications.php` - `5` total / `5` errors / `0` warnings - dominant `WordPress.DateTime.RestrictedFunctions.date_date` (`5`) - technically isolated, but directly in notification scheduling logic - risk `high` - skipped
- `includes/admin/staff-list-columns.php`
  - `7` -> `2`
  - `5` -> `0` errors
  - `2` -> `2` warnings
  - cleared the file's `4` translator-comment errors and `1` final-output escaping error while leaving the existing slow-query warnings untouched
  - limited the pass to certification count labels and final rendering of pre-escaped contact markup only
- focused validation
  - no focused staff-list-columns regression exists in `tests/`
  - `php -l includes/admin/staff-list-columns.php` passed
  - `git diff --check` passed
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - WP-CLI emitted the known phar deprecation line during the packaged rerun; the cleaned raw findings stayed in `docs/plugin-check-1.0.0-raw.txt`, and that noise was stripped from the packaged output stream during local validation
  - the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope without introducing any new Plugin Check code categories

Files touched:

- `includes/admin/staff-list-columns.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all approvals-queue, menu, shared admin-shell, ticket-integrity, availability-dispatch, and portal/auth render work that is still mixed into broader runtime flows
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all ticketing, checkout, refund, cancellation, and TEC publish/resync paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this admin-list render batch
- all broader SQL, nonce/input, and shared-runtime follow-up outside `includes/admin/staff-list-columns.php`

Risk notes:

- selected file is an admin-only list-column renderer, and the chosen changes stayed on placeholder comments plus final output of already-escaped contact fragments without altering queries, capabilities, or workflow routing
- approvals-queue, menu, shared admin shell, ticketing, vendor confirmation, portal/auth, Event Plans runtime, refunds, helpers, and other broader surfaces were intentionally untouched

Net effect of the selected batch:

- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `170` -> `169` (`-1`)
- `WordPress.WP.I18n.MissingTranslatorsComment`: `571` -> `567` (`-4`)
- `I18n placeholder comments and ordering`: `587` -> `583` (`-4`)
- `includes/admin/staff-list-columns.php`: `7` -> `2` (`-5`)
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
| Escaping and output safety | `169` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `583` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04V`
- Scope:
  - repeat the deliberate hotspot scan from the `WPORG-04U` packaged baseline and prefer another isolated admin-only render/i18n or final-escaping slice before widening into request, auth, refund, ticketing, or availability-save flows
  - `includes/admin/approvals-review-queue.php`, `includes/admin/menu.php`, or `includes/admin-ui/shell.php` are better candidates than notification/date logic, shared helpers, or raw ICS output
  - if packaging-warning cleanup is preferred over another code batch, handle the unchanged `plugin_header_nonexistent_domain_path` warning in a dedicated metadata micro-batch instead of widening runtime scope
