# WordPress.org Readiness Checklist

Date: 2026-06-21
Scope: `WPORG-01B` metadata alignment, `WPORG-02` compliance gates, `WPORG-03` blocker cleanup, `WPORG-04A` first packaged blocker-density pass, `WPORG-04B` budget-calculator plus limited Event Plans micro-slice, `WPORG-04D` Event Plans blocker audit plus one protected micro-slice, `WPORG-04E` safe non-Event-Plans request-normalization plus Event Plans bootstrap follow-up, `WPORG-04G` safe error-heavy admin render cleanup outside Event Plans, `WPORG-04H` safe Event Command Center Plugin Check cleanup, `WPORG-04I` staffing admin Plugin Check cleanup, `WPORG-04J` Staff Portal Plugin Check cleanup, `WPORG-04K` Vendor Portal Plugin Check cleanup, `WPORG-04L` public calendar Plugin Check cleanup, `WPORG-04M` public vendor profiles Plugin Check cleanup, `WPORG-04N` public vendor profile template Plugin Check cleanup, `WPORG-04O` social template-engine read-only SQL cleanup, `WPORG-04P` social audit SQL error cleanup, `WPORG-04Q` lineup-schedule i18n hotspot cleanup, `WPORG-04R` vendor-user-links i18n hotspot cleanup, and `WPORG-04S` event-plan-review i18n hotspot cleanup.

## Source State

- Branch: `work/unreleased-2026-06-18`
- HEAD: `c53cdf608dbea20985869a9eaa7bc111e413ac90` (`c53cdf6`)
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
- [x] Seven remaining Event Plans regression scripts now pass from the nested repo workspace.
- [x] Two focused packaged-validation regressions now use the shared WordPress bootstrap resolver.
- [x] Current RC built: `dist/wporg-04s/vms-1.0.0-public-release.zip`
- [x] Compliance report updated: `docs/WPORG_COMPLIANCE_REPORT_1.0.0.md`

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
- Plugin Check was reduced from the `4567`-finding source-tree baseline to `3205` findings on the current packaged RC.
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
- The remaining Event Plans regression scripts that still hardcoded `wp-load.php` now use the shared bootstrap and pass from the nested repo workspace.
- `tests/vendor-availability-ux.php` now passes from the nested repo workspace; `tests/add-dispatch-open-vendor-needs.php` still fails on a pre-existing visibility assertion outside the selected render-only batch.
- The remaining submission risk is concentrated in real runtime code quality categories, with one unchanged packaging metadata warning still visible in the packaged rerun.

## Exit Condition For This Task

The current WordPress.org preparation stack is in a good handoff state when the repo has:

- proven and applied minimum WordPress/PHP metadata,
- a repo-root release builder that passes from the git-backed workspace,
- packaged Plugin Check raw output plus triage,
- a rebuilt `1.0.0` RC with updated reports,
- and a narrowed blocker list for `WPORG-04T`.
