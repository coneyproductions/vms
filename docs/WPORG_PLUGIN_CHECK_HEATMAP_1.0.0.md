# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-04y/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `25fb74d421406702ac95fa7238573a4ff08b9f64b380840bb3c8f3e02cfae7b9`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3147` total findings
- `922` errors
- `2225` warnings

Comparison to the prior packaged RC from `WPORG-04X`:

- `3150` -> `3147` total (`-3`)
- `925` -> `922` errors (`-3`)
- `2225` -> `2225` warnings (`0`)

## WPORG-04Y Batch

- 04Y candidate scan summary
  - `includes/admin/cancelled-event-cost-review.php` - `3` total / `3` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`3`) - isolated admin metabox placeholder strings - risk `low` - selected because it was the highest-yield low-risk admin-only isolated cluster in the final scan
  - `includes/admin/settings/class-vms-settings-notifications.php` - `2` total / `2` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`2`) - isolated admin settings placeholder strings - risk `low` - skipped because it offered lower yield than the selected file and now belongs in the i18n remainder phase
  - `includes/public/event-details.php` - `2` total / `2` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`2`) - isolated public-facing placeholder strings - risk `medium` - skipped because the final isolated-safe batch stayed on the admin-only target and this file now fits the i18n remainder phase
  - `includes/modules/email-followups/templates.php` - `3` total / `3` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`3`) - isolated template timing labels but directly notification-template-adjacent - risk `medium` - skipped into the runtime-aware high-risk / i18n remainder follow-up
  - `includes/admin/staff-certifications.php` - `1` total / `1` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`1`) - isolated admin count string - risk `low` - skipped because it offered lower yield than the selected file and is not enough to justify another isolated-safe batch
  - `includes/admin-ui/shell.php` - `4` total / `4` errors / `0` warnings - dominant `WordPress.Security.EscapeOutput.OutputNotEscaped` (`4`) - shared render boundary for callback-provided HTML fragments - risk `medium` - skipped as the escaping remainder phase rather than isolated-safe work
  - `includes/public/calendar-ics.php` - `8` total / `5` errors / `3` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`4`) with request-parsing and raw-output coupling - risk `high` - skipped into the runtime-aware high-risk / escaping remainder follow-up
  - `includes/helpers.php` - `15` total / `6` errors / `9` warnings - mixed date/time, i18n, escaping, request, and logging findings across shared helper logic - risk `high` - skipped into phased follow-up rather than isolated-safe work
  - `includes/admin/settings-page.php` - `48` total / `20` errors / `28` warnings - mixed nonce/input, escaping, i18n, and date findings across a broad settings surface - risk `high` - skipped into the nonce/input plus escaping phases
  - `includes/core/event-feedback.php` - `11` total / `5` errors / `6` warnings - translator-comment errors mixed with DB/input behavior in feedback notification paths - risk `high` - skipped into runtime-aware high-risk follow-up
- `includes/admin/cancelled-event-cost-review.php`
  - `3` -> `0`
  - `3` -> `0` errors
  - `0` -> `0` warnings
  - cleared the file's `3` placeholder-comment errors by adding `translators:` comments only to the existing labor, vendor/direct, and total-loaded estimate strings in the cancelled-event cost review metabox
- focused validation
  - no focused cancelled-event-cost-review regression exists in `tests/`
  - `php -l includes/admin/cancelled-event-cost-review.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-04y --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-04y-plugin-check.raw.txt` and promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` and `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` warnings outside the selected file scope without introducing any new Plugin Check code categories

Files touched:

- `includes/admin/cancelled-event-cost-review.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all shared admin-shell, calendar ICS payload, ticket-integrity, availability-dispatch, follow-up template, settings-page, and portal/auth render work that is still mixed into broader runtime flows
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all ticketing, checkout, refund, cancellation, and TEC publish/resync paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this comment-only i18n batch
- all broader SQL, nonce/input, and shared-runtime follow-up outside `includes/admin/cancelled-event-cost-review.php`

Risk notes:

- selected file is an admin-only cost-review metabox, and the chosen changes stayed on `translators:` comments for existing placeholder strings only without altering values, formatting, or metabox behavior
- shared admin shell, calendar ICS output, ticketing, vendor confirmation, portal/auth, Event Plans runtime, refunds, helpers, settings pages, and other broader surfaces were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `555` -> `552` (`-3`)
- `I18n placeholder comments and ordering`: `571` -> `568` (`-3`)
- `includes/admin/cancelled-event-cost-review.php`: `3` -> `0` (`-3`)
- observed packaged rerun-only root-file steady state outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
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
| Escaping and output safety | `161` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Phase

- Post-`WPORG-04Y` phased follow-up
- Scope:
  - start a nonce/input phase centered on `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, and `includes/integrations/ticketing-verifications.php`, with regression coverage before widening into mutation paths
  - follow with a DB/SQL phase that prioritizes `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL issues in admissions, staffing, staff-task, and queue/store helpers before generic direct-query/no-caching warnings
  - keep a separate i18n remainder phase for low-yield placeholder-comment leftovers such as `includes/admin/settings/class-vms-settings-notifications.php`, `includes/public/event-details.php`, and `includes/admin/staff-certifications.php` after the security-heavy phases move forward
  - reserve an escaping remainder phase for shared render boundaries including `includes/admin-ui/shell.php`, Staff Portal surfaces, vendor-guest output, and other callback-driven HTML paths
  - finish with a runtime-aware high-risk phase for shared helpers, calendar ICS output, notification-adjacent code, ticketing, cancellation/refund, portal/auth, and Event Plans save/publish flows
