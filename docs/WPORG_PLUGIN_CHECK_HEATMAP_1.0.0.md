# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-06a/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `15ebdc2c93fc257d53f1da473e0734853f66b0aa2305539fc9e50465bb3293e2`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3082` total findings
- `913` errors
- `2169` warnings

Comparison to the prior packaged RC from `WPORG-05E`:

- `3092` -> `3082` total (`-10`)
- `922` -> `913` errors (`-9`)
- `2170` -> `2169` warnings (`-1`)

## WPORG-06A Batch

- 06A candidate scan summary
  - `includes/admin/settings-page.php` - `48` total / `20` errors / `28` warnings - `9` escaping findings - dominant `NonceVerification.Recommended`, `OutputNotEscaped`, `MissingTranslatorsComment`, and `InputNotSanitized` - admin-only settings page with plain text, button markup, dropdown output, and internal status/link/report rows - risk `low` - selected because the output contexts were obvious and the file could clear at least five escaping findings without touching save logic
  - `includes/admin-ui/shell.php` - `4` total / `4` errors / `0` warnings - `4` escaping findings - dominant `OutputNotEscaped` - admin-only shared shell boundary for actions, notices, and content HTML - risk `medium` - skipped because it is a shared allowed-HTML boundary with lower immediate yield
  - `includes/admin/vendor-list-ui.php` - `5` total / `4` errors / `1` warning - `4` escaping findings - dominant `OutputNotEscaped` and `error_log` - admin-only list-table pill markup - risk `low`/`medium` - skipped because the yield was only four escaping findings
  - `includes/admin/ticket-integrity-page.php` - `48` total / `28` errors / `20` warnings - `5` escaping findings - dominant `MissingTranslatorsComment`, `NonceVerification.Recommended`, `MissingUnslash`, and `OutputNotEscaped` - admin-only diagnostics, rebuild, export, and ticketing monitor surfaces - risk `medium`/`high` - skipped because output is mixed with repair and rebuild behavior
  - `includes/modules/staff-tasks/admin-ui.php` - `56` total / `8` errors / `48` warnings - `5` escaping findings - dominant `NonceVerification.Recommended`, `InputNotSanitized`, `OutputNotEscaped`, and `InputNotValidated` - admin-only but mutation-heavy staffing flows - risk `high` - skipped because task transitions, AJAX, and settings/template saves dominate the file
  - `includes/modules/availability-date-dispatch/admin-ui.php` - `30` total / `21` errors / `9` warnings - `14` escaping findings - dominant `OutputNotEscaped`, `NonceVerification.Recommended`, and `MissingTranslatorsComment` - admin-only ADD dispatch and vendor-assignment dashboard - risk `high` - skipped because it touches dispatch behavior
  - `includes/modules/admissions/vendor-guest-portal.php` - `75` total / `36` errors / `39` warnings - `14` escaping findings - dominant `MissingTranslatorsComment`, `OutputNotEscaped`, `DirectQuery`, and `NoCaching` - public/vendor guest portal - risk `high` - skipped because public portal output is mixed with DB and request logic
  - `includes/portal/staff-portal.php` - `59` total / `25` errors / `34` warnings - `23` escaping findings - dominant `OutputNotEscaped`, `InputNotSanitized`, `MissingUnslash`, and `InputNotValidated` - portal save/upload/profile/availability/tax surfaces - risk `high` - skipped because portal mutation flows dominate the remaining findings
- `includes/admin/settings-page.php`
  - `48` -> `39`
  - `20` -> `11` errors
  - `28` -> `28` warnings
  - `9` `OutputNotEscaped` findings -> `0`
  - limited the pass to final-output escaping for help buttons, preview rows, dropdown markup, status/link fragments, and display-only values without changing settings saves, routes, URLs, or report-generation behavior
- focused validation
  - no dedicated `settings-page` regression exists in `tests/`
  - `php -l includes/admin/settings-page.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-06a --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-06a-plugin-check.raw.txt` and promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the rerun no longer emitted `plugin_header_nonexistent_domain_path`, left `includes/helpers/checkin-close.php` steady at one warning, left `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` unchanged, and introduced no previously unseen Plugin Check code categories

Files touched:

- `includes/admin/settings-page.php`

Findings intentionally deferred:

- all remaining shared shell, vendor-list, ticket-integrity, staff-portal, vendor-guest, ADD dispatch, staffing, and portal output work outside this settings-page batch
- all nonce/input, SQL, i18n, date/time, logging, and other runtime follow-up outside `includes/admin/settings-page.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file is an admin-only settings surface, and the chosen changes stayed on final display escaping of existing strings and HTML fragments
- no save, action, upload, delete, approval, refund, dispatch, portal/auth, or Event Plans runtime paths were changed

Net effect of the selected batch:

- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `161` -> `152` (`-9`)
- `Escaping and output safety`: `161` -> `152` (`-9`)
- `includes/admin/settings-page.php`: `48` -> `39` (`-9`)
- packaged totals: `3092` -> `3082` findings, `922` -> `913` errors, `2170` -> `2169` warnings
- observed packaged rerun-only change outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `0`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
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
| Nonce and input handling | `1143` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1101` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `152` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Phase

- Post-`WPORG-06A` phased follow-up
- Scope:
  - continue the safe escaping/output phase with `includes/admin-ui/shell.php`, `includes/admin/vendor-list-ui.php`, and other isolated admin-only display surfaces before widening into public or mutation-coupled flows
  - keep the DB/SQL phase next in line, prioritizing `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL issues in admissions, staffing, staff-task, and queue/store helpers before generic direct-query/no-caching warnings
  - reserve the next nonce/input phase for mutation-coupled admin, portal, vendor-application, ticketing, and Event Plans/integration flows once dedicated regression coverage is in place
  - keep a separate i18n remainder phase for low-yield placeholder-comment leftovers such as `includes/admin/settings/class-vms-settings-notifications.php`, `includes/public/event-details.php`, and `includes/admin/staff-certifications.php` after the security-heavy phases move forward
  - finish with a runtime-aware high-risk phase for shared helpers, calendar ICS output, notification-adjacent code, ticketing, cancellation/refund, portal/auth, and Event Plans save/publish flows
