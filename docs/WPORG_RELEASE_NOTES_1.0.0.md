# WPORG Release Notes 1.0.0

Date: 2026-06-19

## Release Identity

- Public version: `1.0.0`
- Internal lineage: current repo state maps to internal `0.2.24.748`
- Last proven public artifact before this RC: `0.2.24.747`

## Intent

- No broad runtime refactor was performed in this pass.
- Functional behavior changes were limited to:
  - applying proven WordPress.org minimum metadata,
  - fixing repo-root release-test WordPress bootstrap resolution,
  - adding direct-access guards to a small set of packaged PHP files flagged by Plugin Check.
- Database schemas were not changed.
- Uninstall behavior was not changed.
- Add-on installation and licensing behavior were not changed.

## Metadata And Packaging Changes

- Applied `Requires at least: 6.8` to the plugin header and root `readme.txt`.
- Applied `Requires PHP: 8.3` to the plugin header and root `readme.txt`.
- Kept `Tested up to: 7.0`.
- Retained root `LICENSE.txt` and root `readme.txt` in the final package.
- Rebuilt the public RC after the builder fix and direct-access guard cleanup.

## Release Engineering Changes

- Added a shared test bootstrap resolver for `wp-load.php`.
- Updated bundled repo-root release regression scripts to use the shared resolver.
- Updated the release builder to pass an explicit WordPress root / `wp-load.php` path into those scripts when available.
- Added non-destructive build-pipeline coverage for nested plugin-root bootstrapping.

## Compatibility And Proof Status

- Current branch and HEAD remain `work/unreleased-2026-06-18` at `ce576ba3569bffde65e94709b4112f455c4e0cba`.
- WordPress `6.8` and `7.0` both have disposable lifecycle evidence from `WPORG-02`.
- PHP `8.3.30` now has direct lint, WordPress boot, and repo-root build proof from `WPORG-03`.
- Repo-root public-release build now passes without `--skip-release-tests`.
- Final RC artifact:
  - `dist/wporg-03-rc-final/vms-1.0.0-public-release.zip`
  - SHA-256 `37752f55c30d10939b12d5bb40cbd89ea902da9fca979ffd216e022b44f78593`

## Plugin Check Status

- Source-tree baseline from `WPORG-02`: `4567` findings
- Packaged RC after `WPORG-03`: `3888` findings
- Narrow packaged fix completed in this pass:
  - `missing_direct_file_access_protection` reduced from `12` to `0`
- Remaining blocker categories are now concentrated in:
  - nonce and input handling
  - escaping and output safety
  - SQL / direct database safety

## Remaining Release Gates

- Reduce the remaining packaged Plugin Check blocker categories in runtime files.
- Run focused browser/admin smoke on the final RC.
- Optionally add PHPCS/WPCS setup, screenshots, and directory assets in follow-up work.

## Exact Files Changed In `WPORG-03`

- `vendor-management-system.php`
- `readme.txt`
- `scripts/lib/public-release.php`
- `tests/bootstrap-wordpress.php`
- `tests/ticket-checkout-safety-hardening.php`
- `tests/event-plan-legacy-ticketing-integration-smoke.php`
- `tests/event-plan-ticket-ui-overrides-isolated.php`
- `tests/compatibility/collect-state.php`
- `tests/compatibility/seed-upgrade-fixtures.php`
- `tests/public-release-build-pipeline.php`
- packaged PHP direct-access guard fixes in:
  - `includes/cpt/event-plans/partials/*`
  - `includes/cpt/ratings.php`
  - `includes/admin/addons/views/page-addons.php`
  - `includes/helpers/schedule-helpers.php`
- `docs/WPORG_COMPLIANCE_REPORT_1.0.0.md`
- `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`
- `docs/WPORG_READINESS_CHECKLIST.md`
- `docs/WPORG_METADATA_AUDIT.md`
- `docs/WPORG_RELEASE_NOTES_1.0.0.md`
- `docs/plugin-check-1.0.0-raw.txt`
- `BUILD-NOTES-1.0.0.md`
