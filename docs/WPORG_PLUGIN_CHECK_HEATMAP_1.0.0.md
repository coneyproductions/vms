# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-05b/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `ccbb20fe811dd86e0f92c88c0ed6acf8ded6730b33e09e05074f19c29ddf2e0d`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3108` total findings
- `922` errors
- `2186` warnings

Comparison to the prior packaged RC from `WPORG-05A`:

- `3124` -> `3108` total (`-16`)
- `922` -> `922` errors (`0`)
- `2202` -> `2186` warnings (`-16`)

## WPORG-05B Batch

- 05B candidate scan summary
  - `includes/admin/vendor-list-ui.php` - `21` total / `4` errors / `17` warnings - `16` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended` (`8`), `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` (`4`), and `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` (`4`) with four escaping errors and one logging warning - read-only vendor admin tax/W-9 list filters only - risk `low` - selected because the file contains only isolated GET-driven list filters, no mutation paths, and clears more than five warnings without changing list semantics
  - `includes/admin/event-profitability-report.php` - `7` total / `1` errors / `6` warnings - `6` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended` with one translator-comment error - read-only profitability report view/search filters only - risk `low` - skipped because it offered lower yield than the selected file
  - `includes/admin-ui/context.php` - `6` total / `0` errors / `6` warnings - `6` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended` - shared admin page/post-type/post routing only - risk `low` - skipped because it is a broader shared helper surface for similar yield
  - `includes/modules/status-notices/admin-ui.php` - `24` total / `2` errors / `22` warnings - `22` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`, and `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` - read-only view/result/filter/query reads mixed with edit/save/toggle/bulk/trash handlers - risk `medium` - skipped because mutation routes remain coupled in the same file
  - `includes/admin/vendor-command-center.php` - `29` total / `0` errors / `29` warnings - `27` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended` plus `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` with two slow-query companions - read-only search/account/onboarding/payables/template-scope filters mixed with template reset/save/send POST handlers - risk `medium` - skipped because mutation-adjacent email-template flows share the file
  - `includes/admin/event-command-center.php` - `15` total / `0` errors / `15` warnings - `14` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended` with one slow-query companion - read-only notice/plan/page selectors mixed with promo-upload and plan-editor mutation flows - risk `medium` - skipped because upload/mutation code is coupled in the same surface
  - `includes/admin/schedule.php` - `22` total / `0` errors / `22` warnings - `21` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended`, `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`, and `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` - read-only date/view routing mixed with create-event-plan action handling - risk `medium` - skipped because action handling and nonce consumption share the file
  - `includes/admin/docs-page.php` - `6` total / `1` errors / `5` warnings - `5` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended` with one `MissingUnslash` companion and one escaping error - read-only module/doc routing only - risk `low` - skipped because it offered lower yield than `vendor-list-ui.php` and already carries a neighboring escape error
- `includes/admin/vendor-list-ui.php`
  - `21` -> `5`
  - `4` -> `4` errors
  - `17` -> `1` warnings
  - routed the raw `vms_tax` and `vms_w9` admin list filters through a dedicated read-only helper plus explicit allowlists, preserving the existing filter semantics and selected-option behavior
- focused validation
  - no dedicated `vendor-list-ui` regression exists in `tests/`
  - `php -l includes/admin/vendor-list-ui.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-05b --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-05b-plugin-check.raw.txt` and promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the rerun reintroduced the previously seen `plugin_header_nonexistent_domain_path` warning, preserved the standing `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` warning, cleared one unrelated `slow_db_query_meta_key` warning in `includes/helpers/checkin-close.php`, and introduced no previously unseen Plugin Check code categories

Files touched:

- `includes/admin/vendor-list-ui.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all shared admin-shell, calendar ICS payload, ticket-integrity, availability-dispatch, follow-up template, settings-page, and portal/auth render work that is still mixed into broader runtime flows
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all ticketing, checkout, refund, cancellation, and TEC publish/resync paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this read-only admin vendor list batch
- all broader SQL, nonce/input, and shared-runtime follow-up outside `includes/admin/vendor-list-ui.php`

Risk notes:

- selected file is an admin-only vendor list screen, and the chosen changes stayed on a read-only query helper plus explicit allowlists without altering filter semantics, defaults, or results
- shared admin shell, calendar ICS output, ticketing, vendor confirmation, portal/auth, Event Plans runtime, refunds, helpers, settings pages, and other broader surfaces were intentionally untouched

Net effect of the selected batch:

- `WordPress.Security.NonceVerification.Recommended`: `577` -> `569` (`-8`)
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`: `254` -> `250` (`-4`)
- `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`: `232` -> `228` (`-4`)
- `Nonce and input handling`: `1176` -> `1160` (`-16`)
- `includes/admin/vendor-list-ui.php`: `21` -> `5` (`-16`)
- observed packaged rerun-only root-file shift outside the selected file scope: `plugin_header_nonexistent_domain_path`: `0` -> `1`, `WordPress.DB.SlowDBQuery.slow_db_query_meta_key`: `69` -> `68` via `includes/helpers/checkin-close.php`: `1` -> `0`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
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
| Nonce and input handling | `1160` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1100` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `161` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Phase

- Post-`WPORG-05B` phased follow-up
- Scope:
  - clear the remaining low-risk read-only admin surfaces in `includes/admin/event-profitability-report.php`, `includes/admin-ui/context.php`, and `includes/admin/docs-page.php` before widening into mutation-coupled admin screens such as `status-notices/admin-ui.php`, `vendor-command-center.php`, `event-command-center.php`, and `schedule.php`
  - follow with a DB/SQL phase that prioritizes `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL issues in admissions, staffing, staff-task, and queue/store helpers before generic direct-query/no-caching warnings
  - keep a separate i18n remainder phase for low-yield placeholder-comment leftovers such as `includes/admin/settings/class-vms-settings-notifications.php`, `includes/public/event-details.php`, and `includes/admin/staff-certifications.php` after the security-heavy phases move forward
  - reserve an escaping remainder phase for shared render boundaries including `includes/admin-ui/shell.php`, Staff Portal surfaces, vendor-guest output, and other callback-driven HTML paths
  - finish with a runtime-aware high-risk phase for shared helpers, calendar ICS output, notification-adjacent code, ticketing, cancellation/refund, portal/auth, and Event Plans save/publish flows
