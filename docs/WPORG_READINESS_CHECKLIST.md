# WordPress.org Readiness Checklist

Date: 2026-06-22
Scope: `WPORG-01B` metadata alignment, `WPORG-02` compliance gates, `WPORG-03` blocker cleanup, `WPORG-04A` first packaged blocker-density pass, `WPORG-04B` budget-calculator plus limited Event Plans micro-slice, `WPORG-04D` Event Plans blocker audit plus one protected micro-slice, `WPORG-04E` safe non-Event-Plans request-normalization plus Event Plans bootstrap follow-up, `WPORG-04G` safe error-heavy admin render cleanup outside Event Plans, `WPORG-04H` safe Event Command Center Plugin Check cleanup, `WPORG-04I` staffing admin Plugin Check cleanup, `WPORG-04J` Staff Portal Plugin Check cleanup, `WPORG-04K` Vendor Portal Plugin Check cleanup, `WPORG-04L` public calendar Plugin Check cleanup, `WPORG-04M` public vendor profiles Plugin Check cleanup, `WPORG-04N` public vendor profile template Plugin Check cleanup, `WPORG-04O` social template-engine read-only SQL cleanup, `WPORG-04P` social audit SQL error cleanup, `WPORG-04Q` lineup-schedule i18n hotspot cleanup, `WPORG-04R` vendor-user-links i18n hotspot cleanup, `WPORG-04S` event-plan-review i18n hotspot cleanup, `WPORG-04T` admin-schedule render/date hotspot cleanup, `WPORG-04U` staff-list-columns render/i18n hotspot cleanup, `WPORG-04V` approvals-review-queue render/i18n hotspot cleanup, `WPORG-04W` admin UI dashboard render/i18n hotspot cleanup, `WPORG-04X` vendor alert translator-comment cleanup, `WPORG-04Y` final isolated-safe cancelled-event-cost-review cleanup, `WPORG-05A` read-only vendor-availability nonce/input cleanup, `WPORG-05B` read-only vendor-list admin-filter nonce/input cleanup, `WPORG-05C` read-only event-profitability report nonce/input cleanup, `WPORG-05D` read-only docs-page nonce/input cleanup, `WPORG-05E` read-only shared admin context nonce/input cleanup, `WPORG-06A` first settings-page escaping/output cleanup, `WPORG-06B` second vendor-list escaping/output cleanup, `WPORG-06C` third vendor-list-columns escaping/output cleanup, `WPORG-07A` first low-risk DB/SQL triage cleanup, `WPORG-07B` second low-risk DB/SQL hardening cleanup, `WPORG-08A` first cautious i18n placeholder/comment cleanup, `WPORG-08B` second cautious i18n placeholder/comment cleanup, `WPORG-09A` first cautious date/time display-only cleanup, `WPORG-10A` first cautious logging dev-trace cleanup, `WPORG-11A` isolated pass-claims DB/SQL reporting cleanup, `WPORG-12A` nonce/input mutation-flow planning, `WPORG-12B` first nonce/input mutation-flow hardening batch, `WPORG-12C` status-notices nonce/input mutation-flow hardening batch, and `WPORG-12D` ticket-integrity nonce/input mutation-flow hardening batch.

## Source State

- Branch: `work/unreleased-2026-06-18`
- HEAD at start of `WPORG-12D`: `ab2bcffe333ffeae3409cf086ccc9547174d76f9` (`ab2bcff`)
- Remote: `origin https://github.com/coneyproductions/vms.git`
- Proven baseline artifact before this release-candidate push: `0.2.24.747`
- Current public RC markers: `1.0.0`

## Completed

- [x] Public name, URI, author, license, slug, and text-domain decisions applied.
- [x] Root `readme.txt` and root `LICENSE.txt` added and confirmed in packaged ZIPs.
- [x] Canonical `1.0.0` version markers aligned across the plugin header, `VMS_VERSION`, `vms-build.txt`, and readme stable tag.
- [x] Repo-root public ZIP builder fixed for the nested git-backed repo path.
- [x] Non-destructive build-pipeline coverage added for nested `wp-load.php` resolution.
- [x] Remaining isolated Event Plans regression scripts aligned to the shared WordPress bootstrap resolver.
- [x] Repo-root public ZIP built without `--skip-release-tests`.
- [x] Disposable compatibility matrix run on WordPress `7.0`.
- [x] Disposable compatibility matrix run on WordPress `6.8`.
- [x] PHP `8.3` lint, direct WordPress boot smoke, and repo-root release build proof completed.
- [x] `Requires at least: 6.8` applied to the plugin header and root `readme.txt`.
- [x] `Requires PHP: 8.3` applied to the plugin header and root `readme.txt`.
- [x] Readme validator rerun after metadata application.
- [x] Plugin Check rerun against an extracted packaged directory outside the local site tree.
- [x] Plugin Check raw output saved: `docs/plugin-check-1.0.0-raw.txt`
- [x] Plugin Check triage created: `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`
- [x] Plugin Check heatmap created: `docs/WPORG_PLUGIN_CHECK_HEATMAP_1.0.0.md`
- [x] Event Plans hardening map created: `docs/WPORG_EVENT_PLANS_HARDENING_MAP_1.0.0.md`
- [x] Packaged PHP direct-access guards fixed and confirmed removed from Plugin Check.
- [x] First high-density packaged blocker batch applied in `includes/admin/goals-forecast.php` and `includes/social-share/event-plan-panel.php`.
- [x] Second high-density packaged blocker batch applied in `includes/admin/budget-calculator.php` plus the limited admin-list micro-slice in `includes/cpt/event-plans.php`.
- [x] Protected Event Plans follow-up micro-slice applied in the admin list `include_drafts` helper/output block only.
- [x] Safe non-Event-Plans request-normalization batch applied in `includes/admin/due-dates.php` and `includes/admin/holidays.php`.
- [x] Safe error-heavy admin render batch applied in `includes/admin/vendor-command-center.php` and `includes/admin/vendor-availability.php`.
- [x] Safe Event Command Center render/i18n/date batch applied in `includes/admin/event-command-center.php`.
- [x] Safe staffing admin render/request/i18n/rollup-count batch applied in `includes/admin/staffing.php`.
- [x] Safe Staff Portal render/i18n/read-only-query batch applied in `includes/portal/staff-portal.php`.
- [x] Safe Vendor Portal render/i18n/read-only-query batch applied in `includes/portal/vendor-portal.php`.
- [x] Safe public calendar render/read-only-filter batch applied in `includes/public/venue-calendar-shortcode.php`.
- [x] Safe public vendor profiles render/i18n batch applied in `includes/public/vendor-profiles.php`.
- [x] Safe public vendor profile template render batch applied in `includes/public/templates/vendor-profile.php`.
- [x] Safe social template-engine read-only SQL batch applied in `includes/social-share/template-engine.php`.
- [x] Safe social audit SQL error batch applied in `includes/social-share/audit.php`.
- [x] Safe lineup-schedule translator-comment batch applied in `includes/core/lineup-schedule.php`.
- [x] Safe vendor-user-links translator-comment batch applied in `includes/core/vendor-user-links.php`.
- [x] Safe event-plan-review translator-comment batch applied in `includes/core/event-plan-review.php`.
- [x] Safe admin-schedule render/date hotspot batch applied in `includes/admin/schedule.php`.
- [x] Safe staff-list-columns render/i18n hotspot batch applied in `includes/admin/staff-list-columns.php`.
- [x] Medium-risk approvals-review-queue render/i18n hotspot batch applied in `includes/admin/approvals-review-queue.php`.
- [x] Safe admin UI dashboard render/i18n hotspot batch applied in `includes/admin/menu.php`.
- [x] Safe vendor alert translator-comment hotspot batch applied in `includes/core/vendor-document-alerts.php`.
- [x] Final isolated-safe cancelled-event-cost-review translator-comment hotspot batch applied in `includes/admin/cancelled-event-cost-review.php`.
- [x] Read-only vendor-availability nonce/input hotspot batch applied in `includes/admin/vendor-availability.php`.
- [x] Read-only vendor-list admin-filter nonce/input hotspot batch applied in `includes/admin/vendor-list-ui.php`.
- [x] Read-only event-profitability report nonce/input hotspot batch applied in `includes/admin/event-profitability-report.php`.
- [x] Read-only docs-page nonce/input hotspot batch applied in `includes/admin/docs-page.php`.
- [x] Read-only shared admin context nonce/input hotspot batch applied in `includes/admin-ui/context.php`.
- [x] First safe settings-page escaping/output hotspot batch applied in `includes/admin/settings-page.php`.
- [x] Second safe vendor-list escaping/output hotspot batch applied in `includes/admin/vendor-list-ui.php`.
- [x] Third safe vendor-list-columns escaping/output hotspot batch applied in `includes/admin/vendor-list-columns.php`.
- [x] First low-risk DB/SQL triage hotspot batch applied in `includes/core/goals-forecast.php`.
- [x] Second low-risk DB/SQL hardening batch applied in the admin report helpers inside `includes/modules/admissions/pass-claims.php`.
- [x] First cautious i18n placeholder/comment batch applied in `includes/admin/ticket-integrity-page.php`.
- [x] Second cautious i18n placeholder/comment batch applied in `includes/admin/settings-page.php`.
- [x] First cautious date/time display-only batch applied in `includes/admin/settings-page.php`.
- [x] First cautious logging dev-trace batch applied in `includes/core/plugin.php`.
- [x] Isolated pass-claims DB/SQL reporting batch applied in `includes/modules/admissions/pass-claims.php`.
- [x] First bounded nonce/input mutation-flow hardening batch applied in `includes/admin/settings-page.php`.
- [x] Status-notices bounded nonce/input mutation-flow hardening batch applied in `includes/modules/status-notices/admin-ui.php`.
- [x] Ticket-integrity bounded nonce/input mutation-flow hardening batch applied in `includes/admin/ticket-integrity-page.php`.
- [x] Seven remaining Event Plans regression scripts now pass from the nested repo workspace.
- [x] Two focused packaged-validation regressions now use the shared WordPress bootstrap resolver.
- [x] Current RC built: `dist/wporg-12d/vms-1.0.0-public-release.zip`
- [x] Compliance report updated: `docs/WPORG_COMPLIANCE_REPORT_1.0.0.md`
- [x] Nonce/input mutation-flow roadmap documented in the WP.org tracking docs without changing runtime PHP, tests, or the packaged artifact.

## Open Blockers

- [ ] Remaining packaged Plugin Check blocker categories in runtime files:
  - nonce and input handling
  - escaping and output safety
  - SQL / direct database safety

## Open Non-Blockers

- [ ] PHPCS / WPCS setup and run
- [ ] Browser QA on the final RC
- [ ] Privacy exporter / eraser automation
- [ ] Uninstall cleanup tooling
- [ ] Screenshot, icon, and banner assets
- [ ] WordPress.org submission and post-approval SVN steps

## Policy State For `1.0.0`

- [x] Public name: `VMS – Venue Management System`
- [x] Public slug / text domain: `vms`
- [x] Public version: `1.0.0`
- [x] Minimum WordPress version: `6.8`
- [x] Minimum PHP version: `8.3`
- [x] Tested up to: `7.0`
- [x] Dependencies: optional, feature-gated, fail closed when absent
- [x] Uninstall: retain data by default
- [x] Privacy requests: manual handling for `1.0.0`
- [x] Telemetry: no passive telemetry
- [x] Add-ons: operator-initiated ZIP handling retained
- [x] Remote code delivery in core: none found in the add-on installer path

## Evidence Notes

- WordPress `6.8` and `7.0` both booted through the disposable lifecycle matrix without VMS fatals.
- PHP `8.3.30` now has direct lint, build, and WordPress boot evidence.
- The readme validator no longer reports missing or invalid minimum fields.
- Plugin Check was reduced from the `4567`-finding source-tree baseline to `2985` findings on the current packaged RC.
- `WPORG-04A` reduced the packaged RC from `3888` findings to `3808`, with `includes/admin/goals-forecast.php` cleared fully and `includes/social-share/event-plan-panel.php` reduced to four DB-only warnings.
- `WPORG-04B` reduced the packaged RC from `3808` findings to `3695`, with `includes/admin/budget-calculator.php` reduced from `111` findings to `2` and `includes/cpt/event-plans.php` reduced from `248` to `244` without touching Event Plan save or publish logic.
- `WPORG-04D` reduced the packaged RC from `3695` findings to `3692`, with `includes/cpt/event-plans.php` reduced from `244` to `241` while keeping save, publish, ticketing, cancellation, vendor, staffing, TEC, and Woo mutation paths untouched.
- `WPORG-04E` reduced the packaged RC from `3692` findings to `3605`, with `includes/admin/due-dates.php` reduced from `46` to `0` and `includes/admin/holidays.php` reduced from `41` to `0` without touching Event Plans runtime logic.
- `WPORG-04G` reduced the packaged RC from `3605` findings to `3554`, with `includes/admin/vendor-command-center.php` reduced from `51` to `29` and `includes/admin/vendor-availability.php` reduced from `50` to `22` while clearing both files to `0` Plugin Check errors.
- `WPORG-04H` reduced the packaged RC from `3554` findings to `3491`, with `includes/admin/event-command-center.php` reduced from `79` to `15` and cleared to `0` Plugin Check errors while leaving only nonce recommendations plus one slow-query warning in that file.
- `WPORG-04I` reduced the packaged RC from `3491` findings to `3435`, with `includes/admin/staffing.php` reduced from `59` to `3` and cleared to `0` Plugin Check errors while leaving one role-meta input warning plus the direct-query/no-caching pair in that file.
- `WPORG-04J` reduced the packaged RC from `3435` findings to `3408`, with `includes/portal/staff-portal.php` reduced from `86` to `59` while clearing its `MissingTranslatorsComment` findings and removing the read-only interpolated reporting-query findings without widening into auth, profile-save, upload, or availability-save logic.
- `WPORG-04K` reduced the packaged RC from `3408` findings to `3319`, with `includes/portal/vendor-portal.php` reduced from `152` to `63` and cleared to `0` Plugin Check errors while keeping the pass on final escaping, `translators:` comments, read-only request allowlisting, display-only date cleanup, and read-only reporting queries.
- `WPORG-04L` reduced the packaged RC from `3319` findings to `3290`, with `includes/public/venue-calendar-shortcode.php` reduced from `29` to `0` while keeping the pass on final escaping and read-only request parsing.
- `WPORG-04M` reduced the packaged RC from `3290` findings to `3278`, with `includes/public/vendor-profiles.php` reduced from `14` to `2` while keeping the pass on placeholder comments and final output escaping and leaving the file's two slow-query warnings untouched.
- `WPORG-04N` reduced the packaged RC from `3278` findings to `3274`, with `includes/public/templates/vendor-profile.php` reduced from `4` to `0` while keeping the pass on final output escaping of existing markup fragments only.
- `WPORG-04O` reduced the packaged RC from `3274` findings to `3270`, with `includes/social-share/template-engine.php` reduced from `8` to `4` while keeping the pass on read-only table-identifier preparation in two existing template lookup queries only.
- `WPORG-04P` reduced the packaged RC from `3270` findings to `3268`, with `includes/social-share/audit.php` reduced from `7` to `5` while clearing its file-level DB/SQL errors and keeping the pass on the existing read-only audit query branches only.
- `WPORG-04Q` reduced the packaged RC from `3268` findings to `3255`, with `includes/core/lineup-schedule.php` reduced from `12` to `0` while clearing its placeholder-comment errors only; the extracted-package rerun also no longer emitted one pre-existing domain-path warning outside the selected file scope.
- `WPORG-04R` reduced the packaged RC from `3255` findings to `3224`, with `includes/core/vendor-user-links.php` reduced from `68` to `36` while clearing its placeholder-comment errors only; the extracted-package rerun also reintroduced the previously observed domain-path warning outside the selected file scope.
- `WPORG-04S` reduced the packaged RC from `3224` findings to `3205`, with `includes/core/event-plan-review.php` reduced from `21` to `2` while clearing its placeholder-comment errors only; the extracted-package rerun left the previously observed domain-path warning unchanged outside the selected file scope.
- `WPORG-04T` reduced the packaged RC from `3205` findings to `3175`, with `includes/admin/schedule.php` reduced from `52` to `22` while clearing all `30` of its current errors through admin-render escaping and timezone-safe date cleanup only; the extracted-package rerun left the previously observed domain-path warning unchanged outside the selected file scope.
- `WPORG-04U` reduced the packaged RC from `3175` findings to `3170`, with `includes/admin/staff-list-columns.php` reduced from `7` to `2` while clearing all `5` of its current errors through translator comments and final output escaping only; the extracted-package rerun again left the previously observed domain-path warning unchanged outside the selected file scope.
- `WPORG-04V` reduced the packaged RC from `3170` findings to `3163`, with `includes/admin/approvals-review-queue.php` reduced from `11` to `4` while clearing all `7` of its current errors through translator comments, guided-tour helper HTML sanitization, and final provider URL escaping only; the extracted-package rerun again left the previously observed domain-path warning unchanged outside the selected file scope.
- `WPORG-04W` reduced the packaged RC from `3163` findings to `3158`, with `includes/admin/menu.php` reduced from `8` to `3` while clearing all `5` of its current errors through dashboard attr escaping, guided-tour helper HTML sanitization, and one translator comment only; the extracted-package rerun again left the previously observed domain-path warning unchanged outside the selected file scope.
- `WPORG-04X` reduced the packaged RC from `3158` findings to `3150`, with `includes/core/vendor-document-alerts.php` reduced from `8` to `0` while clearing all `8` of its current errors through translator comments only; the extracted-package rerun again left the previously observed domain-path warning unchanged outside the selected file scope and left the standing `load_plugin_textdomain()` warning unchanged as well.
- `WPORG-04Y` reduced the packaged RC from `3150` findings to `3147`, with `includes/admin/cancelled-event-cost-review.php` reduced from `3` to `0` while clearing all `3` of its current errors through translator comments only; the extracted-package rerun again left the previously observed domain-path warning unchanged outside the selected file scope and left the standing `load_plugin_textdomain()` warning unchanged as well.
- `WPORG-05A` reduced the packaged RC from `3147` findings to `3124`, with `includes/admin/vendor-availability.php` reduced from `22` to `0` while clearing all `22` of its current warnings through a read-only query helper only; the extracted-package rerun also stopped emitting the previously observed domain-path warning outside the selected file scope while leaving the standing `load_plugin_textdomain()` warning unchanged.
- `WPORG-05B` reduced the packaged RC from `3124` findings to `3108`, with `includes/admin/vendor-list-ui.php` reduced from `21` to `5` while clearing `16` read-only nonce/input warnings only; the extracted-package rerun reintroduced the previously seen domain-path warning outside the selected file scope, cleared one unrelated `slow_db_query_meta_key` warning in `includes/helpers/checkin-close.php`, and left the standing `load_plugin_textdomain()` warning unchanged.
- `WPORG-05C` reduced the packaged RC from `3108` findings to `3103`, with `includes/admin/event-profitability-report.php` reduced from `7` to `1` while clearing `6` read-only nonce/input warnings only; the extracted-package rerun preserved the standing domain-path warning outside the selected file scope, reintroduced one unrelated `slow_db_query_meta_key` warning in `includes/helpers/checkin-close.php`, and left the standing `load_plugin_textdomain()` warning unchanged.
- `WPORG-05D` reduced the packaged RC from `3103` findings to `3098`, with `includes/admin/docs-page.php` reduced from `6` to `1` while clearing `5` read-only nonce/input warnings only; the extracted-package rerun preserved the standing domain-path warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, and left the standing `load_plugin_textdomain()` warning unchanged.
- `WPORG-05E` reduced the packaged RC from `3098` findings to `3092`, with `includes/admin-ui/context.php` reduced from `6` to `0` while clearing `6` read-only nonce/input warnings only; the extracted-package rerun preserved the standing domain-path warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomain()` warning unchanged, and confirmed that no dedicated `context` regression exists in `tests/`.
- `WPORG-06A` reduced the packaged RC from `3092` findings to `3082`, with `includes/admin/settings-page.php` reduced from `48` to `39` while clearing all `9` of its current `OutputNotEscaped` findings through final-output escaping only; the extracted-package rerun no longer emitted the previously standing domain-path warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, and left the standing `load_plugin_textdomain()` warning unchanged.
- `WPORG-06B` reduced the packaged RC from `3082` findings to `3079`, with `includes/admin/vendor-list-ui.php` reduced from `5` to `1` while clearing all `4` of its current `OutputNotEscaped` findings through final-output escaping only; the extracted-package rerun reintroduced the previously observed domain-path warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomain()` warning unchanged, and confirmed that no previously unseen Plugin Check code categories appeared.
- `WPORG-06C` reduced the packaged RC from `3079` findings to `3076`, with `includes/admin/vendor-list-columns.php` reduced from `11` to `8` while clearing all `3` of its current `OutputNotEscaped` findings through final-output escaping only; the extracted-package rerun no longer emitted the previously oscillating domain-path warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomain()` warning unchanged, and confirmed that no previously unseen Plugin Check code categories appeared.
- `WPORG-07A` reduced the packaged RC from `3076` findings to `3069`, with `includes/core/goals-forecast.php` reduced from `38` to `32` and its DB/SQL subset reduced from `37` to `31` by preparing only the three existing read-only goal helpers; the extracted-package rerun again dropped the oscillating domain-path warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomain()` warning unchanged, and confirmed that no previously unseen Plugin Check code categories appeared.
- `WPORG-07B` reduced the packaged RC from `3069` findings to `3061`, with `includes/modules/admissions/pass-claims.php` reduced from `173` to `165` and its DB/SQL subset reduced from `133` to `125` by preparing only the four existing admin report helpers; the extracted-package rerun reintroduced the previously observed oscillating domain-path warning outside the selected file scope, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomain()` warning unchanged, and confirmed that no previously unseen Plugin Check code categories appeared.
- `WPORG-08A` reduced the packaged RC from `3061` findings to `3041`, with `includes/admin/ticket-integrity-page.php` reduced from `48` to `27` while clearing its `21` `MissingTranslatorsComment` findings through `translators:` comments only; the normalized extracted-package rerun left `plugin_header_nonexistent_domain_path`, `includes/helpers/checkin-close.php`, and the standing `load_plugin_textdomain()` warning unchanged outside the selected file scope and confirmed that no previously unseen Plugin Check code categories appeared.
- `WPORG-08B` reduced the packaged RC from `3041` findings to `3033`, with `includes/admin/settings-page.php` reduced from `39` to `31` while clearing its `8` `MissingTranslatorsComment` findings through `translators:` comments only; the normalized extracted-package rerun re-associated the standing `plugin_header_nonexistent_domain_path` warning to `vendor-management-system.php`, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomain()` warning unchanged, and confirmed that no previously unseen Plugin Check code categories appeared.
- `WPORG-09A` reduced the packaged RC from `3033` findings to `3030`, with `includes/admin/settings-page.php` reduced from `31` to `29` while clearing its remaining `2` `date()` findings through direct site-local `wp_date()` calls on existing transient timestamps only; the normalized extracted-package rerun dropped the previously oscillating `plugin_header_nonexistent_domain_path` warning, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomain()` warning unchanged, and confirmed that no previously unseen Plugin Check code categories appeared.
- `WPORG-10A` reduced the packaged RC from `3030` findings to `3029`, with `includes/core/plugin.php` reduced from `10` to `8` while clearing its remaining `2` gated `error_log()` asset traces only; the normalized extracted-package rerun reintroduced the previously oscillating `plugin_header_nonexistent_domain_path` warning, left `includes/helpers/checkin-close.php` steady at one warning, left the standing `load_plugin_textdomain()` warning unchanged, and confirmed that no previously unseen Plugin Check code categories appeared.
- `WPORG-11A` reduced the packaged RC from `3029` findings to `3019`, with `includes/modules/admissions/pass-claims.php` reduced from `165` to `155` and its DB/SQL subset reduced from `125` to `115` by preparing existing admin/report read-helper table identifiers and values only; the normalized extracted-package rerun introduced no previously unseen Plugin Check code categories.
- `WPORG-12A` kept the packaged baseline at `3019` findings because the pass was docs-only and the tracking-doc updates are excluded from the public ZIP; the next planned execution order is `includes/admin/settings-page.php`, then `includes/admin/ticket-integrity-page.php`, then `includes/modules/status-notices/admin-ui.php`.
- `WPORG-12B` reduced the packaged RC from `3019` findings to `3001`, with `includes/admin/settings-page.php` reduced from `29` to `11` while reducing its nonce/input subset from `24` to `6` through handler-only request normalization around the existing `manage_options` and `wp_verify_nonce()` checks; the six remaining nonce/input findings in that file are read-only notice-query `Recommended` warnings intentionally deferred from this batch, and the normalized extracted-package rerun introduced no previously unseen Plugin Check code categories.
- `WPORG-12C` reduced the packaged RC from `3001` findings to `2997`, with `includes/modules/status-notices/admin-ui.php` reduced from `24` to `22` while reducing its nonce/input subset from `22` to `20` through list-search plus guarded-handler request normalization around the existing custom capability and nonce checks; the remaining nonce/input findings in that file are the same `20` read-only `WordPress.Security.NonceVerification.Recommended` warnings intentionally deferred from this batch.
- `WPORG-12D` reduced the packaged RC from `2997` findings to `2985`, with `includes/admin/ticket-integrity-page.php` reduced from `27` to `14` while reducing its nonce/input subset from `20` to `7` through settings/test-email handler normalization plus same-page admin notice/filter query normalization around the existing `manage_options` and `check_admin_referer()` checks; the remaining nonce/input findings in that file are `7` read-only `WordPress.Security.NonceVerification.Recommended` warnings intentionally deferred from this batch.
- The remaining Event Plans regression scripts that still hardcoded `wp-load.php` now use the shared bootstrap and pass from the nested repo workspace.
- `tests/vendor-availability-ux.php` now passes from the nested repo workspace; no dedicated `vendor-list-columns` regression exists in `tests/`, and `tests/add-dispatch-open-vendor-needs.php` still fails on a pre-existing visibility assertion outside the selected render-only batch.
- The remaining submission risk is concentrated in real runtime code quality categories; the packaged rerun re-associated the oscillating domain-path warning to `vendor-management-system.php`, and the standing `load_plugin_textdomain()` warning plus the steady `includes/helpers/checkin-close.php` warning remain outside the selected file scope.

## Exit Condition For This Task

The current WordPress.org preparation stack is in a good handoff state when the repo has:

- proven and applied minimum WordPress/PHP metadata,
- a repo-root release builder that passes from the git-backed workspace,
- packaged Plugin Check raw output plus triage,
- a rebuilt `1.0.0` RC with updated reports,
- and a narrowed blocker list plus phased follow-up plan for the post-`WPORG-12D` security and runtime work.
