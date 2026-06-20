# WordPress.org Compliance Report 1.0.0

Date: 2026-06-20

## Source State

- Branch: `work/unreleased-2026-06-18`
- HEAD: `71d7bbb6c302563a8812a7da85b45fb00fceb700` (`71d7bbb`)
- Remote: `origin https://github.com/coneyproductions/vms.git`
- WPORG-04E checkpoint state at the start of this task: committed and pushed
- Unrelated modified file left untouched: `docs/VMS ... Market Readiness Checklist (CANONICAL).txt`

## Tested Environment

- Local WordPress root: `.../Local Sites/serenade-range-local-test-site/app/public`
- WordPress runtime evidence:
  - `6.8` disposable lifecycle matrix from `WPORG-02`
  - `7.0` disposable lifecycle matrix from `WPORG-02`
  - current local site boot smoke during `WPORG-03`, `WPORG-04A`, `WPORG-04B`, `WPORG-04D`, `WPORG-04E`, and `WPORG-04G`
- PHP runtime evidence:
  - `8.5.3` from `WPORG-02`
  - `8.3.30` from Local binary during `WPORG-03`, `WPORG-04A`, `WPORG-04B`, `WPORG-04D`, `WPORG-04E`, and `WPORG-04G`
- MySQL: `8.0.35`
- WP-CLI: `2.12.0`
- Dependency versions used in lifecycle and smoke work:
  - WooCommerce `10.5.3`
  - The Events Calendar `6.15.17.1`
  - Event Tickets `5.27.4.1`
  - Event Tickets Plus `6.9.1`

## Final Metadata Decision

- Applied in `vendor-management-system.php` and `readme.txt`:
  - `Requires at least: 6.8`
  - `Requires PHP: 8.3`
  - `Tested up to: 7.0`
- Evidence for `Requires at least: 6.8`
  - WordPress `6.8` completed the disposable lifecycle and compatibility matrix in `WPORG-02` without VMS fatals.
- Evidence for `Requires PHP: 8.3`
  - Local PHP `8.3.30` binary was available.
  - `php -l` passed for `vendor-management-system.php` and `includes/core/registry/constants.php` under PHP `8.3`.
  - Direct WordPress boot smoke under PHP `8.3` loaded `vendor-management-system.php` successfully.
  - Repo-root public-release builder passed under PHP `8.3`, including the bundled release regression scripts.

## Version And Packaging Validation

- PASS: plugin header `Version`, `VMS_VERSION`, `vms-build.txt`, and `readme.txt` stable tag all resolve to `1.0.0`.
- PASS: header license, `readme.txt` license, and root `LICENSE.txt` are aligned on `GPLv2 or later`.
- PASS: slug and text domain remain `vms`.
- PASS: root `readme.txt` and root `LICENSE.txt` remain packaged.

Current rebuilt RC:

- Artifact: `dist/wporg-04g/vms-1.0.0-public-release.zip`
- SHA-256: `e2f4f6a45593b26c319dea37b4179f174e54558aa25acdc0a1131f6cbe553f6d`
- Package integrity: PASS

## Builder Status

The repo-root builder path issue remains fixed in this task, and the remaining packaged-validation regressions that still hardcoded `wp-load.php` were aligned to the shared bootstrap.

- Root cause:
  - bundled release regression scripts assumed `dirname(__DIR__, 4) . '/wp-load.php'`
- Fix applied:
  - added a shared test bootstrap resolver that accepts explicit `VMS_TEST_WP_LOAD` / `VMS_TEST_WORDPRESS_ROOT` inputs and otherwise searches upward safely
  - updated the release runner to pass the detected WordPress root into regression scripts
  - updated the non-destructive build-pipeline test harness
  - updated the remaining isolated Event Plans regressions that still hardcoded `dirname(__DIR__, 4) . '/wp-load.php'` to use `tests/bootstrap-wordpress.php`
  - updated `tests/vendor-availability-ux.php` and `tests/add-dispatch-open-vendor-needs.php` to use the same resolver
- Result:
  - repo-root build now passes without `--skip-release-tests`
  - builder no longer depends on the repo living directly at `wp-content/plugins/vms`
  - the seven remaining nested-repo-sensitive Event Plans tests still pass from this workspace
  - `vendor-availability-ux` now passes from this workspace
  - `add-dispatch-open-vendor-needs` still fails on a pre-existing missing-primary-vendor visibility assertion outside the selected `WPORG-04G` render-only batch

## PHP 8.3 Proof

Commands executed with the Local PHP `8.3.30` binary:

- `php -l vendor-management-system.php`
  - PASS
- `php -l includes/core/registry/constants.php`
  - PASS
- direct WordPress boot smoke requiring `vendor-management-system.php`
  - PASS (`VMS_BOOT_OK`)
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-03-php83-proof --force`
  - PASS
  - proof artifact SHA-256: `e9a01197068239213309082bc37cea2b1667a40c505ed067c7b8f21033bbcae8`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04a --force`
  - PASS
  - current artifact SHA-256: `fd97b45b61f9a1131d12b954080228cb0a441df172d04516597e513e0ba44a67`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04b --force`
  - PASS
  - current artifact SHA-256: `f04938e13855920759e68307946dcf73de31e4b411245392675522373baee5ef`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04d --force`
  - PASS
  - artifact SHA-256: `7987b619acec510e397677074eba3f0442a8511b2a5492112583fc5f7ea9e6f3`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04e --force`
  - PASS
  - current artifact SHA-256: `ca120b97c574ccdd72bb124defc8e712ed7291f4f9730d334423b6b1176d34be`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04g --force`
  - PASS
  - current artifact SHA-256: `e2f4f6a45593b26c319dea37b4179f174e54558aa25acdc0a1131f6cbe553f6d`

## Readme Validator

- Reran the official validator after applying the proven minimums.
- Result:
  - No minimum-field warnings remain.
  - Notes only:
    - tag `vendor management` is not widely used
    - no donate link was found

## Plugin Check

Raw output:

- `docs/plugin-check-1.0.0-raw.txt`

Current packaged-plugin result:

- `3554` total findings
- `1266` errors
- `2288` warnings

Comparison:

- `WPORG-02` source-tree baseline: `4567` total / `1646` errors / `2921` warnings
- `WPORG-03` packaged-plugin run before direct-access guard fixes: `3900` total / `1342` errors / `2558` warnings
- `WPORG-03` packaged-plugin final: `3888` total / `1330` errors / `2558` warnings
- `WPORG-04A` packaged-plugin final: `3808` total / `1329` errors / `2479` warnings
- `WPORG-04B` packaged-plugin final: `3695` total / `1317` errors / `2378` warnings
- `WPORG-04D` packaged-plugin final: `3692` total / `1316` errors / `2376` warnings
- `WPORG-04E` packaged-plugin final: `3605` total / `1316` errors / `2289` warnings
- `WPORG-04G` packaged-plugin final: `3554` total / `1266` errors / `2288` warnings

Dominant remaining codes:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `738`
- `WordPress.Security.NonceVerification.Recommended`: `614`
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`: `260`
- `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`: `233`
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `296`
- `WordPress.DB.DirectDatabaseQuery.DirectQuery`: `293`
- `WordPress.Security.NonceVerification.Missing`: `119`
- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `163`

High-level category counts:

- nonce and input handling: `1248`
- database and SQL safety: `1118`
- i18n placeholder comments / ordering: `760`
- escaping and output safety: `300`
- date/time API usage: `46`
- development logging: `43`

Fixed across this release-prep sequence:

- `missing_direct_file_access_protection`: `12` -> `0`
- `includes/admin/goals-forecast.php`: `66` -> `0`
- `includes/social-share/event-plan-panel.php`: `18` -> `4`
- `includes/admin/budget-calculator.php`: `111` -> `2`
- `includes/cpt/event-plans.php`: `244` -> `241` in this protected audit slice; `248` -> `241` across `WPORG-04B` plus `WPORG-04D`
- `includes/admin/due-dates.php`: `46` -> `0`
- `includes/admin/holidays.php`: `41` -> `0`
- `includes/admin/vendor-command-center.php`: `51` -> `29`, with `22` -> `0` errors
- `includes/admin/vendor-availability.php`: `50` -> `22`, with `28` -> `0` errors
- remaining isolated Event Plans regressions now use the shared bootstrap and pass from the nested repo workspace
- `tests/vendor-availability-ux.php` and `tests/add-dispatch-open-vendor-needs.php` now use the shared bootstrap resolver
- packaged nonce/input blocker surface: `1517` -> `1248`
- packaged i18n placeholder/comment surface: `792` -> `760`
- packaged output-escaping surface: `317` -> `300`

Detailed grouping and recommendations:

- `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`

## Add-on And External-Service Audit

The `WPORG-02` audit conclusions still hold.

- Add-on installer/updater recommendation remains `A`
  - retain manual operator-initiated ZIP handling for `1.0.0`
- Freemius, Turnstile, QRServer/goQR, ICS fetches, and operator-configured webhooks remain disclosed and feature-triggered rather than passive telemetry paths
- No new WordPress.org blocker was found in the add-on / remote-service policy review during this pass

## Blockers

| Check | Finding | Classification | Recommended action | Safe fix applied |
| --- | --- | --- | --- | --- |
| Plugin Check: nonce/input | `1248` remaining findings in mutating admin, portal, and admissions flows | BLOCKER | The safe non-Event-Plans admin request batch is done. Event Plans now needs dedicated regression coverage before widening request hardening deeper into save and integration paths. | Partially |
| Plugin Check: escaping | `300` remaining output-escaping findings | BLOCKER | Audit helper-return HTML and echoed fragments in the highest-density files first | Partially |
| Plugin Check: SQL safety | `1118` remaining DB/SQL findings, including `163` unescaped DB-parameter reports and `73` `PreparedSQL.NotPrepared` reports | BLOCKER | Prioritize real parameter-safety and preparation issues before generic direct-query/no-caching warnings | No |

## Should Fix Before Submission

| Check | Finding | Classification | Recommended action | Safe fix applied |
| --- | --- | --- | --- | --- |
| Plugin Check: i18n | `760` placeholder-comment and ordering findings remain | SHOULD FIX BEFORE SUBMISSION | Add `translators:` comments and ordered placeholders in batches after security blockers | Partially |
| Plugin Check: date/time APIs | `46` `date()` findings remain | SHOULD FIX BEFORE SUBMISSION | Review each case and convert UTC-safe display paths to explicit timezone-safe helpers where appropriate | Partially |
| Plugin Check: development logging | `43` remaining development-logging findings (`42` `error_log()` + `1` `debug_backtrace()`) | SHOULD FIX BEFORE SUBMISSION | Remove or hard-gate residual development logging in packaged code | No |

## Accept / Document

| Check | Finding | Classification | Recommended action | Safe fix applied |
| --- | --- | --- | --- | --- |
| Minimum metadata | `Requires at least: 6.8` and `Requires PHP: 8.3` are now proven and applied | ACCEPT / DOCUMENT | Keep future metadata changes tied to real runtime evidence | Yes |
| Repo-root builder and isolated regressions | Nested-path release builder now passes without `--skip-release-tests`, and the remaining Event Plans isolated regressions now reuse the shared bootstrap resolver | ACCEPT / DOCUMENT | Keep the shared WordPress bootstrap resolver and reusable test fixtures in place | Yes |
| Packaged metadata files | Root `readme.txt` and root `LICENSE.txt` remain present in the final ZIP | ACCEPT / DOCUMENT | Keep verifying this in release engineering | No |
| Dependency-absence loading | Builder and prior lifecycle evidence still support fail-closed optional dependency behavior | ACCEPT / DOCUMENT | Keep readme disclosure aligned with runtime behavior | No |
| Add-ons and external services | `WPORG-02` recommendation `A` remains valid | ACCEPT / DOCUMENT | Retain current disclosures unless those code paths change | No |

## Deferred / Not Run

| Check | Finding | Classification | Recommended action | Safe fix applied |
| --- | --- | --- | --- | --- |
| PHPCS / WPCS | Still not run locally | DEFER | Add a project-local PHPCS/WPCS toolchain in a separate task if coding-standard proof is needed | No |
| PHP 8.2 or lower runtime proof | PHP `8.2` binary exists locally, but this task only proved `8.3` and did not lower the declared minimum further | DEFER | Only lower the minimum after equivalent build and boot evidence exists | No |
| Browser QA | No manual browser/admin walkthrough was run in this pass | DEFER | Use `WPORG-04G` or final clean-site QA after the remaining blocker-reduction pass | No |

## Checks Not Run Or Limited

- PHPCS / WPCS
  - still not run because `phpcs`, `composer`, and repo config were absent
- activation-hook execution
  - the builder intentionally warns instead of mutating a WordPress site during activation-hook checks

## Recommended Next Task

- `WPORG-04H`
- Scope:
  - take the next safe admin-only error-heavy batch in `includes/admin/event-command-center.php`,
  - limit the pass to `translators:` comments plus final render-surface escaping, using the same narrow packaged-validation loop as `WPORG-04G`.
