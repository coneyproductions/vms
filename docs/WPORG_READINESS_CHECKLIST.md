# WordPress.org Readiness Checklist

Date: 2026-06-20
Scope: `WPORG-01B` metadata alignment, `WPORG-02` compliance gates, `WPORG-03` blocker cleanup, `WPORG-04A` first packaged blocker-density pass, `WPORG-04B` budget-calculator plus limited Event Plans micro-slice, `WPORG-04D` Event Plans blocker audit plus one protected micro-slice, and `WPORG-04E` safe non-Event-Plans request-normalization plus Event Plans bootstrap follow-up.

## Source State

- Branch: `work/unreleased-2026-06-18`
- HEAD: `076099d4c76812049b0cc5afba9443c43067dc6a` (`076099d`)
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
- [x] Plugin Check rerun against the installed packaged plugin.
- [x] Plugin Check raw output saved: `docs/plugin-check-1.0.0-raw.txt`
- [x] Plugin Check triage created: `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`
- [x] Plugin Check heatmap created: `docs/WPORG_PLUGIN_CHECK_HEATMAP_1.0.0.md`
- [x] Event Plans hardening map created: `docs/WPORG_EVENT_PLANS_HARDENING_MAP_1.0.0.md`
- [x] Packaged PHP direct-access guards fixed and confirmed removed from Plugin Check.
- [x] First high-density packaged blocker batch applied in `includes/admin/goals-forecast.php` and `includes/social-share/event-plan-panel.php`.
- [x] Second high-density packaged blocker batch applied in `includes/admin/budget-calculator.php` plus the limited admin-list micro-slice in `includes/cpt/event-plans.php`.
- [x] Protected Event Plans follow-up micro-slice applied in the admin list `include_drafts` helper/output block only.
- [x] Safe non-Event-Plans request-normalization batch applied in `includes/admin/due-dates.php` and `includes/admin/holidays.php`.
- [x] Seven remaining Event Plans regression scripts now pass from the nested repo workspace.
- [x] Current RC built: `dist/wporg-04e/vms-1.0.0-public-release.zip`
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
- Plugin Check was reduced from the `4567`-finding source-tree baseline to `3605` findings on the current packaged RC.
- `WPORG-04A` reduced the packaged RC from `3888` findings to `3808`, with `includes/admin/goals-forecast.php` cleared fully and `includes/social-share/event-plan-panel.php` reduced to four DB-only warnings.
- `WPORG-04B` reduced the packaged RC from `3808` findings to `3695`, with `includes/admin/budget-calculator.php` reduced from `111` findings to `2` and `includes/cpt/event-plans.php` reduced from `248` to `244` without touching Event Plan save or publish logic.
- `WPORG-04D` reduced the packaged RC from `3695` findings to `3692`, with `includes/cpt/event-plans.php` reduced from `244` to `241` while keeping save, publish, ticketing, cancellation, vendor, staffing, TEC, and Woo mutation paths untouched.
- `WPORG-04E` reduced the packaged RC from `3692` findings to `3605`, with `includes/admin/due-dates.php` reduced from `46` to `0` and `includes/admin/holidays.php` reduced from `41` to `0` without touching Event Plans runtime logic.
- The remaining Event Plans regression scripts that still hardcoded `wp-load.php` now use the shared bootstrap and pass from the nested repo workspace.
- The remaining submission risk is concentrated in real runtime code quality categories, not packaging metadata or repo-only files.

## Exit Condition For This Task

The current WordPress.org preparation stack is in a good handoff state when the repo has:

- proven and applied minimum WordPress/PHP metadata,
- a repo-root release builder that passes from the git-backed workspace,
- packaged Plugin Check raw output plus triage,
- a rebuilt `1.0.0` RC with updated reports,
- and a narrowed blocker list for `WPORG-04F`.
