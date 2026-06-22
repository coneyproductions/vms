# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-22

## Scope

- Scan target: extracted packaged directory from `dist/wporg-06b/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `8ea9fd47c875f2beac29011c811eda79112d02b03525e79bf60eda720aed6359`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3079` total findings
- `909` errors
- `2170` warnings

Comparison to the prior packaged RC from `WPORG-06A`:

- `3082` -> `3079` total (`-3`)
- `913` -> `909` errors (`-4`)
- `2169` -> `2170` warnings (`+1`)

## WPORG-06B Batch

- 06B candidate scan summary
  - `includes/portal/staff-portal.php` - `59` total / `25` errors / `34` warnings - `23` escaping findings - dominant `OutputNotEscaped`, `InputNotSanitized`, `MissingUnslash`, and `InputNotValidated` - mixed HTML text, badges/cards, hidden/input attrs, and allowed-HTML fragments - portal save/upload/profile/availability/tax surfaces - risk `high` - skipped because portal mutation flows dominate the remaining output work
  - `includes/modules/availability-date-dispatch/admin-ui.php` - `30` total / `21` errors / `9` warnings - `14` escaping findings - dominant `OutputNotEscaped`, `NonceVerification.Recommended`, and `MissingTranslatorsComment` - mixed inline JS, status/source pills, and dashboard markup - admin-only ADD dispatch and vendor-assignment dashboard - risk `high` - skipped because dispatch behavior and assignment flows are coupled to the remaining output
  - `includes/modules/admissions/vendor-guest-portal.php` - `75` total / `36` errors / `39` warnings - `14` escaping findings - dominant `MissingTranslatorsComment`, `OutputNotEscaped`, `DirectQuery`, and `NoCaching` - mixed notices, card/body HTML, help/tour output, and public responses - public/vendor guest portal - risk `high` - skipped because public output is mixed with request and DB logic
  - `includes/cpt/event-plans.php` - `241` total / `108` errors / `133` warnings - `14` escaping findings - dominant nonce/input, i18n, and escaping - mixed admin partial HTML, lazy section output, and ticket/vendor/staff surfaces - Event Plans runtime/admin - risk `high` - skipped because the file is explicitly excluded from this batch
  - `includes/modules/staff-tasks/admin-ui.php` - `56` total / `8` errors / `48` warnings - `5` escaping findings - dominant `NonceVerification.Recommended`, `InputNotSanitized`, `OutputNotEscaped`, and `InputNotValidated` - mixed help buttons, forms, tables, and template-builder markup - admin-only staffing flows - risk `high` - skipped because task, AJAX, and template-save behavior dominates the file
  - `includes/admin/ticket-integrity-page.php` - `48` total / `28` errors / `20` warnings - `5` escaping findings - dominant `MissingTranslatorsComment`, `NonceVerification.Recommended`, `MissingUnslash`, and `OutputNotEscaped` - mixed markdown export, facts tables, row attrs, and rebuild/export forms - admin-only diagnostics and export surface - risk `medium`/`high` - skipped because the remaining output is interleaved with repair, rebuild, and export actions
  - `includes/admin-ui/shell.php` - `4` total / `4` errors / `0` warnings - `4` escaping findings - dominant `OutputNotEscaped` - shared actions/notices/content allowed-HTML boundary - admin-only shared wrapper - risk `medium` - skipped because it is a shared allowed-HTML boundary with broader blast radius than this batch allowed
  - `includes/admin/vendor-list-ui.php` - `5` total / `4` errors / `1` warning - `4` escaping findings - dominant `OutputNotEscaped` and `error_log` - title attribute plus list-table pill markup with clear HTML intent - admin-only vendor list table - risk `low` - selected because all higher-yield files were mixed, mutation-coupled, excluded, or shared-boundary risky, and this was the safest remaining final-output-only slice even though it cleared four escaping findings instead of five
- `includes/admin/vendor-list-ui.php`
  - `5` -> `1`
  - `4` -> `0` errors
  - `1` -> `1` warnings
  - `4` `OutputNotEscaped` findings -> `0`
  - limited the pass to final-output escaping for the wrapper `title` attribute plus the existing tax/W-9/status pill markup, using a narrow `wp_kses()` allowlist for the already-intended `span` HTML without changing vendor queries, filters, storage, or list behavior
- focused validation
  - no dedicated `vendor-list-ui` regression exists in `tests/`
  - `php -l includes/admin/vendor-list-ui.php` passed
  - `git diff --check` passed after the 06B doc updates
  - `php scripts/build-public-release.php --output-dir dist/wporg-06b --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-06b-plugin-check.raw.txt` and `test-results/wporg-06b-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the rerun reintroduced the previously observed `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` unchanged, and introduced no previously unseen Plugin Check code categories

Files touched:

- `includes/admin/vendor-list-ui.php`

Findings intentionally deferred:

- all remaining shared shell, ticket-integrity, staff-portal, vendor-guest, ADD dispatch, staffing, and portal output work outside this vendor-list batch
- all nonce/input, SQL, i18n, date/time, logging, and other runtime follow-up outside `includes/admin/vendor-list-ui.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file is an admin-only list-table display surface, and the chosen changes stayed on final display escaping of existing strings and already-intended pill HTML
- no save, action, upload, delete, approval, refund, dispatch, portal/auth, or Event Plans runtime paths were changed

Net effect of the selected batch:

- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `152` -> `148` (`-4`)
- `Escaping and output safety`: `152` -> `148` (`-4`)
- `includes/admin/vendor-list-ui.php`: `5` -> `1` (`-4`)
- packaged totals: `3082` -> `3079` findings, `913` -> `909` errors, `2169` -> `2170` warnings
- observed packaged rerun-only change outside the selected file scope: `plugin_header_nonexistent_domain_path`: `0` -> `1`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
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
| Escaping and output safety | `148` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Phase

- Post-`WPORG-06B` phased follow-up
- Scope:
  - continue the safe escaping/output phase only if the next target is another isolated admin-only display surface; `includes/admin-ui/shell.php` is the clearest remaining candidate, but it is a shared allowed-HTML boundary and should be treated as materially higher risk than `vendor-list-ui.php`
  - if a similarly isolated display target is not available, pause the escape-only phase and switch to the DB/SQL phase, prioritizing `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL issues in admissions, staffing, staff-task, and queue/store helpers before generic direct-query/no-caching warnings
  - reserve the next nonce/input phase for mutation-coupled admin, portal, vendor-application, ticketing, and Event Plans/integration flows once dedicated regression coverage is in place
  - keep a separate i18n remainder phase for low-yield placeholder-comment leftovers such as `includes/admin/settings/class-vms-settings-notifications.php`, `includes/public/event-details.php`, and `includes/admin/staff-certifications.php` after the security-heavy phases move forward
  - finish with a runtime-aware high-risk phase for shared helpers, calendar ICS output, notification-adjacent code, ticketing, cancellation/refund, portal/auth, and Event Plans save/publish flows
