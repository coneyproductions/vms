# Build Notes 1.0.0

Date: 2026-06-19

## Purpose

Capture the approved WordPress.org identity pass (`WPORG-01B`), the compliance-gate evidence from `WPORG-02`, the blocker cleanup plus RC rebuild work from `WPORG-03`, the first packaged blocker-density pass from `WPORG-04A`, the budget-calculator plus limited Event Plans batch from `WPORG-04B`, and the protected Event Plans audit slice from `WPORG-04D`.

## Files Changed

- `vendor-management-system.php`
- `readme.txt`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `LICENSE.txt`
- `scripts/lib/public-release.php`
- `includes/admin/budget-calculator.php`
- `includes/cpt/event-plans.php`
- `includes/admin/goals-forecast.php`
- `includes/social-share/event-plan-panel.php`
- `tests/bootstrap-wordpress.php`
- `tests/ticket-checkout-safety-hardening.php`
- `tests/event-plan-legacy-ticketing-integration-smoke.php`
- `tests/event-plan-ticket-ui-overrides-isolated.php`
- `tests/compatibility/collect-state.php`
- `tests/compatibility/seed-upgrade-fixtures.php`
- `tests/public-release-build-pipeline.php`
- direct-access guard fixes in packaged PHP files under:
  - `includes/cpt/event-plans/partials/`
  - `includes/cpt/ratings.php`
  - `includes/admin/addons/views/page-addons.php`
  - `includes/helpers/schedule-helpers.php`
- `docs/WPORG_COMPLIANCE_REPORT_1.0.0.md`
- `docs/WPORG_EVENT_PLANS_HARDENING_MAP_1.0.0.md`
- `docs/WPORG_PLUGIN_CHECK_HEATMAP_1.0.0.md`
- `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`
- `docs/WPORG_READINESS_CHECKLIST.md`
- `docs/WPORG_METADATA_AUDIT.md`
- `docs/WPORG_RELEASE_NOTES_1.0.0.md`
- `docs/plugin-check-1.0.0-raw.txt`
- `BUILD-NOTES-1.0.0.md`

## Runtime Change Scope

- Intended runtime change scope:
  - metadata only for the WordPress.org minimum fields in the earlier passes,
  - direct-access guards only for packaged PHP files flagged by Plugin Check in `WPORG-03`,
  - narrow admin/request-handling normalization in `WPORG-04A`,
  - admin-only request hardening in `budget-calculator.php` plus a nonce-gated Event Plans list-toggle micro-slice in `WPORG-04B`,
  - one additional protected Event Plans admin-list helper/output slice in `WPORG-04D`.
- Functional code paths, database schemas, uninstall behavior, and add-on behavior were not intentionally changed beyond those narrow release-safety and request-safety adjustments.
- The plugin header version and `VMS_VERSION` constant remain public `1.0.0`.

## Version Marker Updates

- Previous internal repo lineage before this public pass: `0.2.24.748`
- Last proven public artifact before this RC: `0.2.24.747`
- Public version applied in this pass: `1.0.0`
- Canonical version markers remain synchronized in:
  - `vendor-management-system.php`
  - `includes/core/registry/constants.php`
  - `vms-build.txt`
  - `readme.txt` stable tag

## Validation Commands Run

- `git diff --check`
  - PASS in both `WPORG-02` and the final `WPORG-03` validation pass
- `php -l vendor-management-system.php`
  - PASS
- `php -l includes/core/registry/constants.php`
  - PASS
- PHP `8.3.30` lint proof:
  - PASS for `vendor-management-system.php`
  - PASS for `includes/core/registry/constants.php`
- direct WordPress boot smoke under PHP `8.3.30`
  - PASS (`VMS_BOOT_OK`)
- `php tests/public-release-build-pipeline.php`
  - PASS after the nested `wp-load.php` resolver fix
- `php -l includes/admin/goals-forecast.php`
  - PASS
- `php -l includes/social-share/event-plan-panel.php`
  - PASS
- `php -l includes/admin/budget-calculator.php`
  - PASS
- `php -l includes/cpt/event-plans.php`
  - PASS
- `php tests/event-plan-ticket-ui-overrides-isolated.php`
  - PASS after making the vendor-type fixture helper reusable on an already-seeded local site
- `php tests/event-plan-calendar-resync-isolated.php`
  - ENVIRONMENT-LIMITED: hardcoded `dirname(__DIR__, 4) . '/wp-load.php'` path does not resolve from the nested git-backed repo
- `php tests/event-plan-calendar-unpublished-suppress-save.php`
  - ENVIRONMENT-LIMITED: hardcoded `dirname(__DIR__, 4) . '/wp-load.php'` path does not resolve from the nested git-backed repo
- `php tests/event-plan-editor-vendor-preservation.php`
  - ENVIRONMENT-LIMITED: hardcoded `dirname(__DIR__, 4) . '/wp-load.php'` path does not resolve from the nested git-backed repo
- `php tests/event-plan-secondary-vendor-assignments.php`
  - ENVIRONMENT-LIMITED: hardcoded `dirname(__DIR__, 4) . '/wp-load.php'` path does not resolve from the nested git-backed repo
- `php tests/event-plan-module-reopen-and-market-layout.php`
  - ENVIRONMENT-LIMITED: hardcoded `dirname(__DIR__, 4) . '/wp-load.php'` path does not resolve from the nested git-backed repo
- repo-root builder before fix
  - FAIL in `WPORG-02` due hardcoded `dirname(__DIR__, 4) . '/wp-load.php'` assumptions in bundled release scripts
- repo-root builder after fix
  - PASS with default CLI runtime
  - PASS with Local PHP `8.3.30`
- final rebuilt RC after `WPORG-03`
  - PASS: `dist/wporg-03-rc-final/vms-1.0.0-public-release.zip`
  - SHA-256: `37752f55c30d10939b12d5bb40cbd89ea902da9fca979ffd216e022b44f78593`
- current rebuilt RC after `WPORG-04A`
  - PASS: `dist/wporg-04a/vms-1.0.0-public-release.zip`
  - SHA-256: `fd97b45b61f9a1131d12b954080228cb0a441df172d04516597e513e0ba44a67`
- current rebuilt RC after `WPORG-04B`
  - PASS: `dist/wporg-04b/vms-1.0.0-public-release.zip`
  - SHA-256: `f04938e13855920759e68307946dcf73de31e4b411245392675522373baee5ef`
- current rebuilt RC after `WPORG-04D`
  - PASS: `dist/wporg-04d/vms-1.0.0-public-release.zip`
  - SHA-256: `7987b619acec510e397677074eba3f0442a8511b2a5492112583fc5f7ea9e6f3`
- package integrity
  - PASS
- official readme validator after metadata application
  - PASS on minimum fields
  - NOTE: uncommon tag `vendor management`, no donate link
- Plugin Check packaged runs
  - source-tree baseline from `WPORG-02`: `4567` findings
  - packaged run before direct-access guard fixes: `3900` findings
  - packaged final run: `3888` findings
  - packaged final run after `WPORG-04A`: `3808` findings
  - packaged final run after `WPORG-04B`: `3695` findings
  - packaged final run after `WPORG-04D`: `3692` findings
  - fixed category in this pass: `missing_direct_file_access_protection` reduced from `12` to `0`
  - `WPORG-04A` batch delta: `-80` total findings (`-1` errors / `-79` warnings)
  - `WPORG-04B` batch delta: `-113` total findings (`-12` errors / `-101` warnings)
  - `WPORG-04D` batch delta: `-3` total findings (`-1` errors / `-2` warnings)

## Minimum Version Decision

- Applied:
  - `Requires at least: 6.8`
  - `Requires PHP: 8.3`
- Evidence:
  - WordPress `6.8` lifecycle matrix from `WPORG-02`
  - WordPress `7.0` lifecycle matrix from `WPORG-02`
  - Local PHP `8.3.30` lint, build, and direct WordPress boot evidence from `WPORG-03`

## Remaining Blockers

- Packaged Plugin Check blocker categories in runtime files:
  - nonce and input handling
  - escaping and output safety
  - SQL / direct database safety

## Remaining Non-Blockers

- PHPCS / WPCS setup
- Browser QA
- Privacy exporter / eraser automation
- Uninstall cleanup tooling
- Optional WordPress.org screenshots and listing assets

## Rollback Note

If this RC pass is not kept, revert the metadata, builder/test bootstrap, direct-access guard, and documentation changes together before generating a different public artifact.
