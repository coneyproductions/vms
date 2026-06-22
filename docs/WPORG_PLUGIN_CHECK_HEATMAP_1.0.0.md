# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-22

## Scope

- Scan target: extracted packaged directory from `dist/wporg-06c/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `f8bf7787e7abe21a2834cd2ecaaab2c90ea9c39e7579c8ee2ad9e7e6a3938df2`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3076` total findings
- `906` errors
- `2170` warnings

Comparison to the prior packaged RC from `WPORG-06B`:

- `3079` -> `3076` total (`-3`)
- `909` -> `906` errors (`-3`)
- `2170` -> `2170` warnings (`0`)

## WPORG-06C Batch

- 06C candidate scan summary
  - `includes/portal/staff-portal.php` - `59` total / `25` errors / `34` warnings - `23` escaping findings - dominant `OutputNotEscaped`, `InputNotSanitized`, `MissingUnslash`, and `InputNotValidated` - mixed HTML text, badges/cards, hidden/input attrs, and allowed-HTML fragments - portal save/upload/profile/availability/tax surfaces - risk `high` - skipped because portal mutation flows dominate the remaining output work
  - `includes/cpt/event-plans.php` - `241` total / `108` errors / `133` warnings - `14` escaping findings - dominant nonce/input, i18n, and escaping - mixed admin partial HTML, lazy section output, and ticket/vendor/staff surfaces - Event Plans runtime/admin - risk `high` - skipped because the file is explicitly excluded from this batch
  - `includes/modules/admissions/vendor-guest-portal.php` - `75` total / `36` errors / `39` warnings - `14` escaping findings - dominant `MissingTranslatorsComment`, `OutputNotEscaped`, `DirectQuery`, and `NoCaching` - mixed notices, card/body HTML, help/tour output, and public responses - public/vendor guest portal - risk `high` - skipped because public output is mixed with request and DB logic
  - `includes/modules/availability-date-dispatch/admin-ui.php` - `30` total / `21` errors / `9` warnings - `14` escaping findings - dominant `OutputNotEscaped`, `NonceVerification.Recommended`, and `MissingTranslatorsComment` - mixed inline JS, status/source pills, and dashboard markup - admin-only ADD dispatch and vendor-assignment dashboard - risk `high` - skipped because dispatch behavior and assignment flows are coupled to the remaining output
  - `includes/modules/staff-tasks/admin-ui.php` - `56` total / `8` errors / `48` warnings - `5` escaping findings - dominant `NonceVerification.Recommended`, `InputNotSanitized`, `OutputNotEscaped`, and `InputNotValidated` - mixed help buttons, forms, tables, and template-builder markup - admin-only staffing flows - risk `high` - skipped because task, AJAX, and template-save behavior dominates the file
  - `includes/admin/ticket-integrity-page.php` - `48` total / `28` errors / `20` warnings - `5` escaping findings - dominant `MissingTranslatorsComment`, `NonceVerification.Recommended`, `MissingUnslash`, and `OutputNotEscaped` - mixed markdown export, facts tables, row attrs, and rebuild/export forms - admin-only diagnostics and export surface - risk `medium`/`high` - skipped because the remaining output is interleaved with repair, rebuild, and export actions
  - `includes/admin-ui/shell.php` - `4` total / `4` errors / `0` warnings - `4` escaping findings - dominant `OutputNotEscaped` - shared actions/notices/content allowed-HTML boundary - admin-only shared wrapper - risk `medium` - skipped because it is a shared allowed-HTML boundary with broader blast radius than this batch allowed
  - `includes/safety/admin.php` - `27` total / `4` errors / `23` warnings - `4` escaping findings - dominant `NonceVerification.Recommended`, `MissingUnslash`, `InputNotSanitized`, and `OutputNotEscaped` - mixed notices, tabs, and shell-fed allowed-HTML fragments - admin-only safety toolkit - risk `medium` - skipped because the remaining output is coupled to shared shell/help-button boundaries
  - `includes/admin/vendor-user-link.php` - `8` total / `4` errors / `4` warnings - `4` escaping findings - dominant `OutputNotEscaped`, `MissingUnslash`, and `InputNotSanitized` - select-option helper HTML and metabox form controls - admin-only metabox surface - risk `medium`/`high` - skipped because the remaining output is interleaved with save-form behavior
  - `includes/admin/vendor-list-columns.php` - `11` total / `3` errors / `8` warnings - `3` escaping findings - dominant `OutputNotEscaped` and `NonceVerification.Recommended` - list-table pill markup and tax-status span/title output - admin-only vendor list columns - risk `low` - selected because it was the last clearly isolated admin-only display slice left after the higher-yield files screened out as mixed, shared-boundary, excluded, public, or mutation-coupled
- additional low-yield files inspected but not selected: `includes/admin/express-bar.php`, `includes/admin/continuity-binder.php`, `includes/core/vendor-application-confirmation.php`, and `includes/portal/vendor-tax-profile.php`
- `includes/admin/vendor-list-columns.php`
  - `11` -> `8`
  - `3` -> `0` errors
  - `8` -> `8` warnings
  - `3` `OutputNotEscaped` findings -> `0`
  - limited the pass to final-output escaping for the existing W-9 / 1099 pill helpers plus the complete/incomplete tax-status span markup, using a narrow `wp_kses()` allowlist for the already-intended `span` HTML without changing vendor meta reads, filters, sorting, storage, or list behavior
- focused validation
  - no dedicated `vendor-list-columns` regression exists in `tests/`
  - `php -l includes/admin/vendor-list-columns.php` passed
  - `git diff --check` passed after the 06C doc updates
  - `php scripts/build-public-release.php --output-dir dist/wporg-06c --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-06c-plugin-check.raw.txt` and `test-results/wporg-06c-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the rerun no longer emitted the previously observed `plugin_header_nonexistent_domain_path` warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` unchanged, and introduced no previously unseen Plugin Check code categories

Files touched:

- `includes/admin/vendor-list-columns.php`

Findings intentionally deferred:

- all remaining shared shell, safety-admin, vendor-user-link, ticket-integrity, staff-portal, vendor-guest, ADD dispatch, staffing, and portal output work outside this vendor-list-columns batch
- all nonce/input, SQL, i18n, date/time, logging, and other runtime follow-up outside `includes/admin/vendor-list-columns.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file is an admin-only read-only list-table display surface, and the chosen changes stayed on final display escaping of existing strings and already-intended pill HTML
- no save, action, upload, delete, approval, refund, dispatch, portal/auth, Event Plans runtime, or query intent paths were changed

Net effect of the selected batch:

- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `148` -> `145` (`-3`)
- `Escaping and output safety`: `148` -> `145` (`-3`)
- `includes/admin/vendor-list-columns.php`: `11` -> `8` (`-3`)
- packaged totals: `3079` -> `3076` findings, `909` -> `906` errors, `2170` -> `2170` warnings
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
| Escaping and output safety | `145` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Phase

- Post-`WPORG-06C` phased follow-up
- Scope:
  - pause the escape-only phase after `WPORG-06C`; the remaining output-heavy files are shared boundaries, public/portal surfaces, vendor-assignment dashboards, metabox/save flows, or excluded Event Plans slices rather than isolated admin-only display targets
  - switch next to the DB/SQL phase, prioritizing `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL issues in admissions, staffing, staff-task, ADD helper, and queue/store helpers before generic direct-query/no-caching warnings
  - reserve the next nonce/input phase for mutation-coupled admin, portal, vendor-application, ticketing, and Event Plans/integration flows once dedicated regression coverage is in place
  - keep a separate i18n remainder phase for low-yield placeholder-comment leftovers such as `includes/admin/settings/class-vms-settings-notifications.php`, `includes/public/event-details.php`, and `includes/admin/staff-certifications.php` after the security-heavy phases move forward
  - revisit the remaining escaping/output files only after the DB/SQL tranche or after new regression coverage makes a shared-boundary follow-up defensible
