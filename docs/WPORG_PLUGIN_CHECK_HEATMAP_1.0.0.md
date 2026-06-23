# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-22

## Scope

- Scan target: extracted packaged directory from `dist/wporg-11a/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `f9abd751234a27cd981b74c00bfd3fc33dc2d2cb24c519e682ed9c0c6c18c875`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3019` total findings
- `865` errors
- `2154` warnings

Comparison to the prior packaged RC from `WPORG-10A`:

- `3029` -> `3019` total (`-10`)
- `867` -> `865` errors (`-2`)
- `2162` -> `2154` warnings (`-8`)

## WPORG-11A Batch

- 11A candidate scan summary
  - reviewed the highest-density remaining DB/SQL files from the packaged `WPORG-10A` baseline before editing
  - selected `includes/modules/admissions/pass-claims.php` because it still had a second isolated admin/reporting-only read subset after `WPORG-07B`: sources, batches, individual token lookups, and token list reporting
  - limited edits to identifier and value preparation in existing read helpers only; selected columns, joins, filters, ordering, limits, return shapes, exports, claim writes, public token claim logic, and handlers stayed unchanged
  - skipped higher-density candidates that were mixed with staffing runtime, task writes, vendor assignment runtime, queue mutation/state, REST mutations, ticketing checkout claims, portal/public flows, or previously selected DB/SQL work
- DB/SQL candidate scan

| File | Total | Errors | Warnings | DB/SQL | Dominant DB/SQL codes | Surface | Risk | Decision |
| --- | ---: | ---: | ---: | ---: | --- | --- | --- | --- |
| `includes/modules/admissions/pass-claims.php` | `165` | `15` | `150` | `125` | `DirectQuery x44`, `NoCaching x39`, `InterpolatedNotPrepared x19`, `UnescapedDBParameter x18`, `NotPrepared x1` | admin admissions sources/batches/token reporting | `low` | Selected: isolated read-only admin/report helper subset remained after `WPORG-07B`. |
| `includes/core/staffing.php` | `153` | `38` | `115` | `122` | `DirectQuery x38`, `NoCaching x31`, `InterpolatedNotPrepared x22`, `UnescapedDBParameter x21`, `NotPrepared x4` | staffing schema/runtime/dashboard/writes | `high` | Skipped: mixed runtime, schema, dashboard, and write behavior. |
| `includes/modules/staff-tasks/store.php` | `90` | `17` | `73` | `90` | direct query/preparation warnings | staff task store writes/runtime | `high` | Skipped: task-store mutation behavior. |
| `includes/modules/availability-date-dispatch/helpers.php` | `96` | `19` | `77` | `85` | direct query/preparation warnings | vendor assignment and ADD runtime | `high` | Skipped: vendor assignment/runtime behavior. |
| `includes/social-share/queue-repo.php` | `73` | `7` | `66` | `73` | direct query/preparation warnings | social queue state/posting | `medium/high` | Skipped: queue state and posting writes. |
| `includes/modules/admissions/rest.php` | `65` | `11` | `54` | `58` | direct query/preparation warnings | admissions REST mutations | `high` | Skipped: REST mutation surface. |
| `includes/integrations/ticketing-claims-framework.php` | `50` | `16` | `34` | `49` | direct query/preparation warnings | ticketing checkout claims/schema | `high` | Skipped: checkout and claim mutation behavior. |
| `includes/core/vendor-user-links.php` | `36` | `7` | `29` | `36` | direct query/preparation warnings | vendor-user auth/link mapping | `high` | Skipped: auth/link mapping and write behavior. |
| `includes/modules/admissions/vendor-guest-portal.php` | `75` | `36` | `39` | `35` | direct query/preparation warnings | vendor guest portal public/mutation flow | `high` | Skipped: public portal request and cancel/add flows. |
| `includes/core/goals-forecast.php` | `32` | `0` | `32` | `31` | direct query/preparation warnings | goals forecast reporting | `medium` | Skipped: already selected in `WPORG-07A`; avoid repeating prior selected-file scope. |
- `includes/modules/admissions/pass-claims.php`
  - `165` -> `155`
  - `15` -> `13` errors
  - `150` -> `142` warnings
  - DB/SQL subset `125` -> `115`
  - `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `18` -> `12` (`-6`)
  - `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`: `19` -> `12` (`-7`)
  - `WordPress.DB.PreparedSQL.NotPrepared`: `1` -> `0` (`-1`)
  - `WordPress.DB.DirectDatabaseQuery.DirectQuery`: `44` -> `46` (`+2`)
  - `WordPress.DB.DirectDatabaseQuery.NoCaching`: `39` -> `41` (`+2`)
  - the direct-query/no-cache count increased because the former interpolated reads are now explicit prepared `$wpdb` calls; the net DB/SQL result still reduced `10` selected-file findings
- focused validation
  - no focused regression exists in `tests/` for the selected pass-claims admin/report helper subset
  - `php -l includes/modules/admissions/pass-claims.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-11a --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory at `/tmp/vms-wporg-11a.bIPrbJ/vms` outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-11a-plugin-check.raw.txt` and `test-results/wporg-11a-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`

Files touched:

- `includes/modules/admissions/pass-claims.php`

Findings intentionally deferred:

- all remaining staffing, staff-task, availability-dispatch, social-queue, admissions REST, ticketing claims, vendor-user auth/link, portal/public, and Event Plans DB/SQL work outside this isolated reporting slice
- all remaining nonce/input, escaping/output, i18n, date/time, logging, and other runtime follow-up outside `includes/modules/admissions/pass-claims.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file still contains admissions claim and token behavior, but this pass stayed inside admin/report read helpers only
- no save, delete, activation, upload, portal/auth, checkout, ticketing mutation, admissions mutation, cron, Event Plans runtime, or query-intent paths were changed

Net effect of the selected batch:

- packaged totals: `3029` -> `3019` findings, `867` -> `865` errors, `2162` -> `2154` warnings
- DB/SQL safety: `1087` -> `1077` (`-10`)
- `includes/modules/admissions/pass-claims.php`: `165` -> `155` (`-10`)
- no previously unseen Plugin Check code categories appeared

## WPORG-10A Batch

- 10A candidate scan summary
  - reviewed all twenty-two packaged logging-hit files from the `WPORG-09A` baseline and then triaged sixteen candidates in depth across public, REST, cron, admin, and Event Plans surfaces before editing
  - searched the repo for existing logging wrappers and found several scoped abstractions (`VMS_Addons_Logger`, `vms_notify_insert_log()`, `vms_admission_audit_log()`, `vms_due_append_log()`, `vms_safety_audit_log()`, `vms_square_ticket_mirror_log()`, `vms_event_plan_perf_log()`, `vms_email_followups_log()`), but no generic Plugin Check-safe project logger that could replace arbitrary `error_log()` calls without changing diagnostic behavior
  - no low-risk candidate met the preferred `3`-finding threshold; every `3+` file was tied to public submissions, REST mutations, cron generation, imports, runtime guards, ticket mutation auditing, or excluded Event Plans / ticketing support diagnostics
  - selected `includes/core/plugin.php` because its two findings were admin-only `VMS_DEBUG_ADMIN_HOOKS` asset-bootstrap traces, clearly temporary development logging, and behavior-neutral to remove
  - skipped the remaining low-risk one-finding admin/dev candidates because they were either smaller than the selected two-finding slice or more operationally meaningful than the gated asset traces
- logging candidate scan

| File | Total | Errors | Warnings | Logging | Dominant logging codes | Logging pattern | Surface | Existing safe abstraction | Risk | Decision |
| --- | ---: | ---: | ---: | ---: | --- | --- | --- | --- | --- | --- |
| `includes/vendor-applications.php` | `90` | `15` | `75` | `8` | `error_log x8` | vendor creation/link failures, Turnstile verification failures, taxonomy assignment failures | public vendor application + onboarding follow-up | `No generic safe wrapper nearby` | `high` | Skipped: public submission, anti-spam, and account-linking support diagnostics. |
| `includes/cpt/event-plans.php` | `241` | `108` | `133` | `4` | `error_log x4` | legacy ticket cleanup completion plus TEC sync failures | Event Plans runtime/save/integration | `Scoped perf logger exists nearby, but not an equivalent replacement` | `high` | Skipped: excluded file and runtime/integration diagnostics. |
| `includes/modules/admissions/rest.php` | `65` | `11` | `54` | `4` | `error_log x4` | create/update/check-in DB failure logs | admissions REST mutation | `Module audit log exists, but it does not replace DB failure logging` | `high` | Skipped: REST mutation support diagnostics. |
| `includes/admin/data-tools/actions-event-plan-import.php` | `11` | `3` | `8` | `3` | `error_log x3` | preview/commit/revert import failure logs | admin-only CSV import tool | `Admin notices exist, but no Plugin Check-safe persistent logger` | `medium` | Skipped: admin-only, but import failure logs are operationally useful and mutation-coupled. |
| `includes/modules/staff-tasks/generator.php` | `5` | `0` | `5` | `3` | `error_log x3` | nightly generator skipped/summary/failure logs | cron/task generation | `Subsystem action-log storage exists elsewhere, not equivalent here` | `high` | Skipped: cron health and generation diagnostics. |
| `includes/runtime-guards.php` | `30` | `1` | `29` | `2` | `error_log x2` | admin diagnostic echo-to-log plus heavy-admin trace payloads | runtime guards / admin diagnostics | `Scoped diagnostic queue exists, but not a safe replacement for trace logging` | `medium` | Skipped: mixed operational diagnostics and trace logging. |
| `includes/ticketing/ticket-mutation-audit.php` | `19` | `3` | `16` | `2` | `error_log x1`, `debug_backtrace x1` | ticket mutation trace and source capture | ticketing audit/runtime | `This file is the audit subsystem itself` | `high` | Skipped: ticket mutation support and audit-trail diagnostics. |
| `includes/core/plugin.php` | `10` | `0` | `10` | `2` | `error_log x2` | `VMS_DEBUG_ADMIN_HOOKS` asset enqueue/skip traces | admin-only asset bootstrap | `No` | `low` | Selected: gated dev-only admin traces; removing them is behavior-neutral. |
| `includes/taxonomies/vendor-type.php` | `3` | `1` | `2` | `2` | `error_log x2` | default-term ensure/delete failure logs | taxonomy bootstrap / repair | `No` | `medium` | Skipped: low count and still operational integrity diagnostics. |
| `includes/core/event-plan-save-profiler.php` | `32` | `17` | `15` | `1` | `error_log x1` | WP_DEBUG save-profile dump | Event Plan save-flow diagnostics | `Profile store exists, but the file is save-flow coupled` | `high` | Skipped: save-flow instrumentation outside safe scope. |
| `includes/admin/settings-page.php` | `29` | `1` | `28` | `1` | `error_log x1` | entitlement image sync summary fallback | admin-only ticketing maintenance tool | `Scoped helper exists, but it resolves to flagged error_log() in ticketing-phase-b` | `medium` | Skipped: ticketing maintenance diagnostic with support value. |
| `includes/core/goals-forecast.php` | `32` | `0` | `32` | `1` | `error_log x1` | provider/refresh failures via `vms_goals_log()` | reporting + refresh runtime | `Scoped wrapper exists, but it still uses error_log()` | `medium` | Skipped: operational refresh diagnostics, not temporary debug. |
| `includes/core/notifications.php` | `8` | `2` | `6` | `1` | `error_log x1` | failed notification log-row insert fallback | notification audit trail | `vms_notify_insert_log()` exists here; the flagged call is its failure fallback | `high` | Skipped: notification support and audit diagnostic. |
| `includes/admin/menu.php` | `3` | `0` | `3` | `1` | `error_log x1` | missing renderer warning under `WP_DEBUG` | admin dashboard bootstrap | `No` | `low` | Skipped: only one finding and more operational than the selected asset-trace slice. |
| `includes/tours/class-vms-tours-registry.php` | `1` | `0` | `1` | `1` | `error_log x1` | `VMS_TOURS_DEBUG` debug log | admin-only guided tours | `No` | `low` | Skipped: one-finding candidate, smaller than the selected two-finding slice. |
| `includes/admin/vendor-list-ui.php` | `1` | `0` | `1` | `1` | `error_log x1` | non-scalar meta fallback log | admin-only vendor list display | `No` | `medium` | Skipped: deliberate no-silent-failure diagnostic in read-only admin rendering. |
- `includes/core/plugin.php`
  - `10` -> `8`
  - `0` -> `0` errors
  - `10` -> `8` warnings
  - `2` -> `0` logging findings
  - limited the pass to removing the two `VMS_DEBUG_ADMIN_HOOKS` `error_log()` asset-trace branches only; asset loading, screen detection, styles/scripts, routes, and runtime behavior stayed unchanged
- focused validation
  - no focused regression exists in `tests/` for `includes/core/plugin.php` or the admin asset bootstrap trace gate
  - `php -l includes/core/plugin.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-10a --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory at `/tmp/vms-wporg-10a.5CuQQy/vms` outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-10a-plugin-check.raw.txt` and `test-results/wporg-10a-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the normalized packaged summary kept `includes/helpers/checkin-close.php` and `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` unchanged outside the selected file scope, while the previously oscillating `plugin_header_nonexistent_domain_path` warning reappeared in this rerun

Files touched:

- `includes/core/plugin.php`

Findings intentionally deferred:

- all remaining public application, REST mutation, cron generation, notification, runtime-guard, ticket mutation, taxonomy repair, Event Plans, and ticketing logging work outside this admin bootstrap slice
- all remaining nonce/input, escaping/output, DB/SQL, i18n, date/time, and other runtime follow-up outside `includes/core/plugin.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file is admin-only shared bootstrap code, but the chosen changes stayed strictly inside dev-only tracing branches gated by `VMS_DEBUG_ADMIN_HOOKS`
- no save, delete, activation, upload, portal/auth, checkout, ticketing mutation, admissions mutation, cron, Event Plans runtime, or query intent paths were changed

Net effect of the selected batch:

- `WordPress.PHP.DevelopmentFunctions.error_log_error_log`: `42` -> `40` (`-2`)
- `Development logging`: `43` -> `41` (`-2`)
- `includes/core/plugin.php`: `10` -> `8` (`-2`)
- packaged totals: `3030` -> `3029` findings, `867` -> `867` errors, `2163` -> `2162` warnings
- normalized packaged summary outside the selected file scope: `plugin_header_nonexistent_domain_path`: `0` -> `1`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
- no previously unseen Plugin Check code categories appeared

## WPORG-09A Batch

- 09A candidate scan summary
  - reviewed all fifteen remaining packaged `date()` candidate files from the `WPORG-08B` baseline before editing
  - no low-risk admin-only candidate met the preferred `3`-finding threshold; the three-plus finding files were shared runtime helpers, diagnostics/query builders, scheduling/digest logic, or ticket-window logic
  - selected `includes/admin/settings-page.php` because it is an admin-only settings/report surface and both remaining date/time findings were pure formatting of already-derived transient timestamps for notice display with explicit site-local timezone intent already in place
  - skipped the other low-risk admin-only candidates because they only offered one or two findings and were either less purely display-only or smaller than the selected file
- candidate scan

| File | Total | Errors | Warnings | Date/time | Dominant date/time codes | Usage | Surface | Risk | Decision |
| --- | ---: | ---: | ---: | ---: | --- | --- | --- | --- | --- |
| `includes/admin/settings-page.php` | `31` | `3` | `28` | `2` | `RestrictedFunctions.date_date x2` | formatting-only of stored transient timestamps for admin notices | admin-only settings + report notices | `low` | Selected: pure display formatting of already-derived timestamps with explicit site-local timezone intent and no save/query/runtime behavior changes. |
| `includes/admin/ticket-integrity-page.php` | `27` | `7` | `20` | `2` | `RestrictedFunctions.date_date x2` | report-generation timestamp + export filename stamp | admin-only diagnostics/export | `low` | Skipped: safe, but smaller and less purely display-only than the selected settings-page notice timestamps. |
| `includes/admin/square-sync-protection.php` | `4` | `2` | `2` | `1` | `RestrictedFunctions.date_date x1` | formatting-only of last report timestamp | admin-only protection report | `low` | Skipped: only one finding; lower impact than the selected two-finding settings-page slice. |
| `includes/core/cli/state-of-range.php` | `1` | `1` | `0` | `1` | `RestrictedFunctions.date_date x1` | CLI display formatting | CLI/reporting | `medium` | Skipped: low submission value and only one finding compared with admin-only display targets. |
| `includes/ticketing/ticket-integrity-monitor.php` | `15` | `12` | `3` | `3` | `RestrictedFunctions.date_date x3` | mixed formatting + current-time range query building | admin diagnostics/runtime mixed | `medium` | Skipped: not a pure display-only slice because two findings define scan target date ranges. |
| `includes/helpers.php` | `15` | `6` | `9` | `3` | `RestrictedFunctions.date_date x3` | season normalization + weekday/open-date calculations | shared runtime helper | `high` | Skipped: shared venue season/open-day behavior is runtime-sensitive. |
| `includes/modules/staff-tasks/notifications.php` | `5` | `5` | `0` | `5` | `RestrictedFunctions.date_date x5` | due-soon windows, digest windows, daily run gating | cron/notifications runtime | `high` | Skipped: notification timing and digest windows are behavior-sensitive. |
| `includes/integrations/ticketing-phase-b.php` | `46` | `20` | `26` | `2` | `RestrictedFunctions.date_date x2` | sales-window defaults and current-time ticketing lookups | checkout/ticketing runtime | `high` | Skipped: ticket sales window behavior is explicitly out of scope. |
| `includes/core/payables.php` | `3` | `2` | `1` | `2` | `RestrictedFunctions.date_date x2` | bill-number fallback + due-date calculation | payables/runtime | `high` | Skipped: payables dates affect represented dates and downstream accounting logic. |
| `includes/core/event-credits.php` | `23` | `16` | `7` | `1` | `RestrictedFunctions.date_date x1` | current-date eligibility comparison | event-credit runtime | `high` | Skipped: changes could affect credit eligibility and user-visible availability. |
| `includes/portal/vendor-tax-profile.php` | `13` | `5` | `8` | `1` | `RestrictedFunctions.date_date x1` | received-date stamp on upload | portal mutation/save flow | `high` | Skipped: stored value stamping is not display-only. |
| `includes/admin/staff-tax-sidebar.php` | `8` | `1` | `7` | `1` | `RestrictedFunctions.date_date x1` | employee packet received/verified date stamps | admin mutation/save flow | `high` | Skipped: stored compliance dates are not display-only. |
| `includes/admin/tax-profile-admin-metabox.php` | `6` | `1` | `5` | `1` | `RestrictedFunctions.date_date x1` | W-9 received-date stamp | admin metabox save flow | `high` | Skipped: stored vendor tax dates are not display-only. |
| `includes/schedule/season-dates.php` | `1` | `1` | `0` | `1` | `RestrictedFunctions.date_date x1` | date-range expansion for season schedule payloads | schedule runtime | `high` | Skipped: schedule payload generation is behavior-sensitive. |
| `includes/cpt/event-plans/partials/time-lineup.php` | `2` | `2` | `0` | `1` | `RestrictedFunctions.date_date x1` | default bypass-until date generation | Event Plans admin/runtime | `high` | Skipped: Event Plans runtime surface is explicitly excluded. |
- `includes/admin/settings-page.php`
  - `31` -> `29`
  - `3` -> `1` errors
  - `28` -> `28` warnings
  - `2` -> `0` date/time findings
  - limited the pass to replacing the legacy `date()` fallbacks with direct site-local `wp_date()` calls on the existing transient timestamps only; visible copy, settings behavior, report behavior, routes, actions, and save logic remained unchanged
- focused validation
  - no focused admin-settings regression exists in `tests/` for `includes/admin/settings-page.php`
  - `php -l includes/admin/settings-page.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-09a --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory at `/tmp/vms-wporg-09a.vXsEPK/vms` outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-09a-plugin-check.raw.txt` and `test-results/wporg-09a-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the normalized packaged summary kept `includes/helpers/checkin-close.php` and `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` unchanged outside the selected file scope, while the previously oscillating `plugin_header_nonexistent_domain_path` warning disappeared in this rerun

Files touched:

- `includes/admin/settings-page.php`

Findings intentionally deferred:

- all remaining scheduling, payables, notifications, ticket-window, portal-stamping, CLI, shared-helper, and Event Plans date/time work outside this admin settings surface
- all remaining nonce/input, escaping/output, DB/SQL, i18n, logging, and other runtime follow-up outside `includes/admin/settings-page.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file is admin-only, but it still contains settings handlers and tool actions; the chosen changes stayed strictly in notice timestamp rendering
- no save, delete, activation, upload, portal/auth, checkout, ticketing mutation, admissions mutation, Event Plans runtime, or query intent paths were changed

Net effect of the selected batch:

- `WordPress.DateTime.RestrictedFunctions.date_date`: `27` -> `25` (`-2`)
- `includes/admin/settings-page.php`: `31` -> `29` (`-2`)
- packaged totals: `3033` -> `3030` findings, `869` -> `867` errors, `2164` -> `2163` warnings
- normalized packaged summary outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `0`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
- no previously unseen Plugin Check code categories appeared

## WPORG-08B Batch

- 08B candidate scan summary
  - reviewed ten i18n-heavy candidates from the packaged `WPORG-08A` baseline before editing
  - selected `includes/admin/settings-page.php` because it is the first remaining admin-only settings/reporting file with at least five safe `MissingTranslatorsComment` findings, no placeholder-order warnings, and fixes limited to `translators:` comments only
  - skipped the heavier files because they are Event Plans runtime, checkout/ticketing, upload/verification, portal/public, email-delivery, or save-flow coupled
- candidate scan

| File | Total | Errors | Warnings | I18n | Dominant i18n codes | Surface | Fix type | Risk | Decision |
| --- | ---: | ---: | ---: | ---: | --- | --- | --- | --- | --- |
| `includes/cpt/event-plans.php` | `241` | `108` | `133` | `93` | `MissingTranslatorsComment x83`, `NonSingularStringLiteralText x4`, `UnorderedPlaceholdersText x6` | public/admin mixed | mixed/unclear | `high` | Skipped: explicitly excluded Event Plans save/publish/runtime surface. |
| `includes/integrations/ticketing-rules-v2.php` | `94` | `65` | `29` | `57` | `MissingTranslatorsComment x54`, `UnorderedPlaceholdersText x3` | checkout/ticketing | translator comments + placeholder ordering | `high` | Skipped: checkout eligibility and cart-rule runtime. |
| `includes/integrations/ticketing-verifications.php` | `102` | `40` | `62` | `34` | `MissingTranslatorsComment x34` | checkout/ticketing + uploads | translator comments only | `high` | Skipped: upload, verification, and request-handling runtime. |
| `includes/core/staffing.php` | `153` | `38` | `115` | `31` | `MissingTranslatorsComment x29`, `UnorderedPlaceholdersText x2` | admin/runtime mixed | translator comments + placeholder ordering | `high` | Skipped: shared staffing runtime still mixes DB/runtime and admin behavior. |
| `includes/ticketing/ticket-integrity-daily-report.php` | `37` | `33` | `4` | `31` | `MissingTranslatorsComment x31` | admin email/report | translator comments only | `medium` | Skipped: email/report copy and delivery-state behavior are coupled. |
| `includes/ticketing/ticket-integrity-checks.php` | `21` | `21` | `0` | `21` | `MissingTranslatorsComment x21` | ticketing runtime | translator comments only | `high` | Skipped: runtime verification checks rather than display-only admin UI. |
| `includes/modules/admissions/vendor-guest-portal.php` | `75` | `36` | `39` | `19` | `MissingTranslatorsComment x19` | portal/public mixed | translator comments only | `high` | Skipped: vendor guest portal request/public behavior is mixed in the same file. |
| `includes/cpt/event-plans/partials/staff.php` | `19` | `19` | `0` | `18` | `MissingTranslatorsComment x18` | admin metabox partial | translator comments only | `high` | Skipped: partial belongs to excluded Event Plans save/publish flow. |
| `includes/core/event-plan-save-profiler.php` | `32` | `17` | `15` | `17` | `MissingTranslatorsComment x16`, `UnorderedPlaceholdersText x1` | save-flow diagnostics | translator comments + placeholder ordering | `high` | Skipped: Event Plan save instrumentation is tightly coupled to save flow. |
| `includes/admin/settings-page.php` | `39` | `11` | `28` | `8` | `MissingTranslatorsComment x8` | admin-only settings + report previews | translator comments only | `low` | Selected: admin-only settings surface with eight safe placeholder-comment fixes and no placeholder-order changes. |
- `includes/admin/settings-page.php`
  - `39` -> `31`
  - `8` -> `0` i18n findings
  - `11` -> `3` errors
  - limited the pass to adding `translators:` comments above the existing placeholder-bearing strings only; visible text, settings behavior, form fields, routes, actions, and save/report logic remained unchanged
- focused validation
  - no focused admin-settings regression exists in `tests/` for `includes/admin/settings-page.php`
  - `php -l includes/admin/settings-page.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-08b --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-08b-plugin-check.raw.txt` and `test-results/wporg-08b-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the normalized packaged summary kept `includes/helpers/checkin-close.php` and `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` unchanged outside the selected file scope, while the standing `plugin_header_nonexistent_domain_path` warning was re-associated to `vendor-management-system.php`

Files touched:

- `includes/admin/settings-page.php`

Findings intentionally deferred:

- all remaining Event Plans, ticketing eligibility, verification, portal, save-profiler, email-report, and upload-coupled i18n work outside this admin settings surface
- all remaining nonce/input, escaping/output, DB/SQL, date/time, logging, and other runtime follow-up outside `includes/admin/settings-page.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file is admin-only, but it still contains settings handlers and tool actions; the chosen changes stayed strictly in screen-reader labels, display strings, and preview descriptions
- no save, delete, activation, upload, portal/auth, checkout, ticketing mutation, admissions mutation, Event Plans runtime, or query intent paths were changed

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `531` -> `523` (`-8`)
- `I18n placeholder comments and ordering`: `547` -> `539` (`-8`)
- `includes/admin/settings-page.php`: `39` -> `31` (`-8`)
- packaged totals: `3041` -> `3033` findings, `877` -> `869` errors, `2164` -> `2164` warnings
- normalized packaged summary outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `1` (re-associated to `vendor-management-system.php`), `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
- no previously unseen Plugin Check code categories appeared

## WPORG-08A Batch

- 08A candidate scan summary
  - reviewed ten i18n-heavy files from the packaged `WPORG-07B` baseline before editing
  - selected `includes/admin/ticket-integrity-page.php` because it is an admin-only diagnostics page and its current i18n pressure was entirely `MissingTranslatorsComment` placeholder guidance in render/reporting sections
  - skipped the other nine because they are Event Plans runtime, checkout/ticketing, upload/verification, portal/public, email-delivery, or save-flow coupled
- candidate scan

| File | Total | Errors | Warnings | I18n | Dominant i18n codes | Surface | Fix type | Risk | Decision |
| --- | ---: | ---: | ---: | ---: | --- | --- | --- | --- | --- |
| `includes/cpt/event-plans.php` | `241` | `108` | `133` | `93` | `MissingTranslatorsComment x83`, `NonSingularStringLiteralText x4`, `UnorderedPlaceholdersText x6` | public/admin mixed | mixed/unclear | `high` | Skipped: explicitly excluded Event Plans save/publish/runtime surface. |
| `includes/integrations/ticketing-rules-v2.php` | `94` | `65` | `29` | `57` | `MissingTranslatorsComment x54`, `UnorderedPlaceholdersText x3` | checkout/ticketing | translator comments + placeholder ordering | `high` | Skipped: checkout eligibility and cart-rule runtime. |
| `includes/integrations/ticketing-verifications.php` | `102` | `40` | `62` | `34` | `MissingTranslatorsComment x34` | checkout/ticketing + uploads | translator comments only | `high` | Skipped: upload, verification, and request-handling runtime. |
| `includes/core/staffing.php` | `153` | `38` | `115` | `31` | `MissingTranslatorsComment x29`, `UnorderedPlaceholdersText x2` | admin/runtime mixed | translator comments + placeholder ordering | `high` | Skipped: shared staffing runtime still mixes DB/runtime and admin behavior. |
| `includes/ticketing/ticket-integrity-daily-report.php` | `37` | `33` | `4` | `31` | `MissingTranslatorsComment x31` | admin email/report | translator comments only | `medium` | Skipped: email/report copy and delivery-state behavior are coupled. |
| `includes/admin/ticket-integrity-page.php` | `48` | `28` | `20` | `21` | `MissingTranslatorsComment x21` | admin-only diagnostics | translator comments only | `low` | Selected: admin-only diagnostic render file with placeholder-comment errors only. |
| `includes/ticketing/ticket-integrity-checks.php` | `21` | `21` | `0` | `21` | `MissingTranslatorsComment x21` | ticketing runtime | translator comments only | `high` | Skipped: runtime verification checks rather than display-only admin UI. |
| `includes/modules/admissions/vendor-guest-portal.php` | `75` | `36` | `39` | `19` | `MissingTranslatorsComment x19` | portal/public mixed | translator comments only | `high` | Skipped: vendor guest portal request/public behavior is mixed in the same file. |
| `includes/cpt/event-plans/partials/staff.php` | `19` | `19` | `0` | `18` | `MissingTranslatorsComment x18` | admin metabox partial | translator comments only | `high` | Skipped: partial belongs to excluded Event Plans save/publish flow. |
| `includes/core/event-plan-save-profiler.php` | `32` | `17` | `15` | `17` | `MissingTranslatorsComment x16`, `UnorderedPlaceholdersText x1` | save-flow diagnostics | translator comments + placeholder ordering | `high` | Skipped: Event Plan save instrumentation is tightly coupled to save flow. |
- `includes/admin/ticket-integrity-page.php`
  - `48` -> `27`
  - `21` -> `0` i18n findings
  - `28` -> `7` errors
  - limited the pass to adding `translators:` comments above the existing placeholder-bearing strings only; visible text, routes, actions, forms, and save/report behavior remained unchanged
- focused validation
  - no focused admin-page regression exists in `tests/` for `includes/admin/ticket-integrity-page.php`; adjacent ticket-integrity tests only cover scan locks and daily-report delivery helpers
  - `php -l includes/admin/ticket-integrity-page.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-08a --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - normalized packaged findings were saved to `test-results/wporg-08a-plugin-check.raw.txt` and `test-results/wporg-08a-plugin-check.summary.json`, then promoted into `docs/plugin-check-1.0.0-raw.txt`
  - the normalized packaged summary left `plugin_header_nonexistent_domain_path`, `includes/helpers/checkin-close.php`, and `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` unchanged outside the selected file scope, and introduced no previously unseen Plugin Check code categories

Files touched:

- `includes/admin/ticket-integrity-page.php`

Findings intentionally deferred:

- all remaining Event Plans, ticketing eligibility, verification, portal, save-profiler, email-report, and upload-coupled i18n work outside this admin diagnostics page
- all remaining nonce/input, escaping/output, DB/SQL, date/time, logging, and other runtime follow-up outside `includes/admin/ticket-integrity-page.php`
- all Event Plans runtime request/save/publish follow-up

Risk notes:

- selected file is admin-only, but it still contains scan/send/settings handlers; the chosen changes stayed strictly in render/reporting string comments and did not alter those handlers
- no save, delete, activation, upload, portal/auth, checkout, ticketing mutation, admissions mutation, Event Plans runtime, or query intent paths were changed

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `552` -> `531` (`-21`)
- `I18n placeholder comments and ordering`: `568` -> `547` (`-21`)
- `includes/admin/ticket-integrity-page.php`: `48` -> `27` (`-21`)
- packaged totals: `3061` -> `3041` findings, `898` -> `877` errors, `2163` -> `2164` warnings
- normalized packaged summary outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `1`, `includes/helpers/checkin-close.php`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
- no previously unseen Plugin Check code categories appeared

## Highest-Density Files

| File | Total | Errors | Warnings | Primary pressure |
| --- | ---: | ---: | ---: | --- |
| `includes/cpt/event-plans.php` | `241` | `108` | `133` | nonce/input + i18n + escaping |
| `includes/modules/admissions/pass-claims.php` | `155` | `13` | `142` | DB/SQL |
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
| Database and SQL safety | `1077` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `145` `OutputNotEscaped` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `539` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `25` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `41` findings (`40` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Phase

- Post-`WPORG-11A` phased follow-up
- Scope:
  - continue one more isolated DB/SQL reporting pass only if the next candidate is another read-only admin/report helper with stable query intent; otherwise shift to nonce/input mutation-flow planning
  - pause further logging cleanup unless another equivalently isolated admin-only dev-trace or debug-only slice appears; the remaining higher-density logging files are public submissions, REST mutations, cron generators, runtime guards, notification/ticketing diagnostics, taxonomy repair, or excluded Event Plans work
  - keep the date/time phase paused after `WPORG-09A`; the remaining date/time files are scheduling, ticket-window, notification, payables, portal-stamping, CLI, Event Plans, or shared runtime helpers
  - keep targeted i18n paused unless another equivalently isolated admin-only translator-comment slice is identified; the highest-density remaining i18n files are Event Plans, checkout/ticketing, upload/verification, portal, email, or save-flow coupled
  - if the i18n phase resumes, keep it limited to translator comments, ordered placeholders, or literal `vms` text-domain corrections in admin-only diagnostics/list/report files
  - keep the escape-only phase paused after `WPORG-06C`; the remaining output-heavy files are still shared boundaries, public/portal surfaces, metabox/save flows, vendor-assignment dashboards, or excluded Event Plans slices rather than isolated admin-only display targets
