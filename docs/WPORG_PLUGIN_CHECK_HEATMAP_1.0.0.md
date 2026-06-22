# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-05e/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `66d1fdd1cfcb6e5fb3af92f66a9b329a57c96fb28078b1a57bb47b4237ddad55`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3092` total findings
- `922` errors
- `2170` warnings

Comparison to the prior packaged RC from `WPORG-05D`:

- `3098` -> `3092` total (`-6`)
- `922` -> `922` errors (`0`)
- `2176` -> `2170` warnings (`-6`)

## WPORG-05E Batch

- 05E candidate scan summary
  - `includes/admin-ui/context.php` - `6` total / `0` errors / `6` warnings - `6` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended` - shared admin page/post-type/post routing only - risk `low` - selected because the findings stayed isolated to read-only shell/context routing values and could be centralized without changing screen fallback, shell routing, planning memory, or active-cluster behavior
  - `includes/modules/status-notices/admin-ui.php` - `24` total / `2` errors / `22` warnings - `22` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`, and `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` - read-only view/result/filter/query reads mixed with edit/save/toggle/bulk/trash handlers - risk `medium` - skipped because mutation routes remain coupled in the same file
  - `includes/admin/vendor-command-center.php` - `29` total / `0` errors / `29` warnings - `27` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended` plus `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` with two slow-query companions - read-only search/account/onboarding/payables/template-scope filters mixed with template reset/save/send POST handlers - risk `medium` - skipped because mutation-adjacent email-template flows share the file
  - `includes/admin/event-command-center.php` - `15` total / `0` errors / `15` warnings - `14` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended` with one slow-query companion - read-only notice/plan/page selectors mixed with promo-upload and plan-editor mutation flows - risk `medium` - skipped because upload/mutation code is coupled in the same surface
  - `includes/admin/schedule.php` - `22` total / `0` errors / `22` warnings - `21` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended`, `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`, and `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` - read-only date/view routing mixed with create-event-plan action handling - risk `medium` - skipped because action handling and nonce consumption share the file
  - `includes/modules/staff-tasks/admin-ui.php` - `56` total / `8` errors / `48` warnings - `44` nonce/input findings - dominant `WordPress.Security.NonceVerification.Recommended`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`, and `WordPress.Security.EscapeOutput.OutputNotEscaped` - task transitions, assignment updates, one-off creation, AJAX, and settings/template saves share the file - risk `high` - skipped because mutation-heavy staffing flows dominate the surface
  - `includes/portal/staff-portal.php` - `59` total / `25` errors / `34` warnings - `30` nonce/input findings - dominant `WordPress.Security.EscapeOutput.OutputNotEscaped`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`, and `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` - certification uploads, employee-packet acknowledgement, tax save, and availability-save flows share the file - risk `high` - skipped because save/upload paths dominate the remaining findings
  - `includes/vendor-applications.php` - `90` total / `15` errors / `75` warnings - `61` nonce/input findings - dominant `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`, `WordPress.Security.NonceVerification.Recommended`, and `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` - admin approve/reject/repair/resync actions and public vendor application submission share the file - risk `high` - skipped because approval and submission flows dominate the surface
- `includes/admin-ui/context.php`
  - `6` -> `0`
  - `0` -> `0` errors
  - `6` -> `0` warnings
  - centralized the read-only `page`, `post_type`, and `post` query reads behind one helper while preserving screen fallback, shell routing, planning memory, and active-cluster behavior
- focused validation
  - no dedicated `context` regression exists in `tests/`
  - `php -l includes/admin-ui/context.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-05e --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-05e-plugin-check.raw.txt` and promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the rerun preserved the standing `plugin_header_nonexistent_domain_path`, `includes/helpers/checkin-close.php`, and `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` warnings, and introduced no previously unseen Plugin Check code categories

Files touched:

- `includes/admin-ui/context.php`

Findings intentionally deferred:

- all remaining mutation-coupled admin nonce/input work in `status-notices/admin-ui.php`, `vendor-command-center.php`, `event-command-center.php`, and `schedule.php`
- all staffing, staff-portal, vendor-application, ticketing, checkout, refund, cancellation, upload, approval, and portal/auth hardening outside this shared read-only context-helper batch
- all Event Plans runtime request/save/publish follow-up
- all broader SQL, nonce/input, i18n, escaping, logging, and shared-runtime follow-up outside `includes/admin-ui/context.php`

Risk notes:

- selected file is a shared admin helper, but the chosen changes stayed on read-only routing values already used for shell detection and planning-context memory without altering fallback behavior
- no save, action, upload, delete, approval, refund, ticketing, portal/auth, or Event Plans runtime paths were changed

Net effect of the selected batch:

- `WordPress.Security.NonceVerification.Recommended`: `559` -> `553` (`-6`)
- `Nonce and input handling`: `1149` -> `1143` (`-6`)
- `Database and SQL safety`: `1101` -> `1101` (`0`)
- `includes/admin-ui/context.php`: `6` -> `0` (`-6`)
- observed packaged rerun-only steady state outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `1`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
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
| Escaping and output safety | `161` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `568` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Phase

- Post-`WPORG-05E` phased follow-up
- Scope:
  - pause the read-only nonce/input phase after `includes/admin-ui/context.php`; no additional low-risk read-only slice remains
  - follow with a DB/SQL phase that prioritizes `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `PreparedSQL.NotPrepared`, and interpolated SQL issues in admissions, staffing, staff-task, and queue/store helpers before generic direct-query/no-caching warnings
  - reserve the next nonce/input phase for mutation-coupled admin, portal, vendor-application, ticketing, and Event Plans/integration flows once dedicated regression coverage is in place
  - keep a separate i18n remainder phase for low-yield placeholder-comment leftovers such as `includes/admin/settings/class-vms-settings-notifications.php`, `includes/public/event-details.php`, and `includes/admin/staff-certifications.php` after the security-heavy phases move forward
  - reserve an escaping remainder phase for shared render boundaries including `includes/admin-ui/shell.php`, Staff Portal surfaces, vendor-guest output, and other callback-driven HTML paths
  - finish with a runtime-aware high-risk phase for shared helpers, calendar ICS output, notification-adjacent code, ticketing, cancellation/refund, portal/auth, and Event Plans save/publish flows
