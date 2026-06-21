# WordPress.org Compliance Report 1.0.0

Date: 2026-06-21

## Source State

- Branch: `work/unreleased-2026-06-18`
- HEAD at start of `WPORG-04U`: `a9a6ae471ad6dedc122d0ed38c4d50b251038613` (`a9a6ae4`)
- Remote: `origin https://github.com/coneyproductions/vms.git`
- WPORG-04T checkpoint state at the start of this task: committed and pushed
- Unrelated modified file left untouched: `docs/VMS ... Market Readiness Checklist (CANONICAL).txt`

## Tested Environment

- Local WordPress root: `.../Local Sites/serenade-range-local-test-site/app/public`
- WordPress runtime evidence:
  - `6.8` disposable lifecycle matrix from `WPORG-02`
  - `7.0` disposable lifecycle matrix from `WPORG-02`
  - current local site boot smoke during `WPORG-03`, `WPORG-04A`, `WPORG-04B`, `WPORG-04D`, `WPORG-04E`, `WPORG-04G`, `WPORG-04H`, `WPORG-04I`, `WPORG-04J`, `WPORG-04K`, `WPORG-04L`, `WPORG-04M`, `WPORG-04N`, `WPORG-04O`, `WPORG-04P`, `WPORG-04Q`, `WPORG-04R`, `WPORG-04S`, `WPORG-04T`, and `WPORG-04U`
- PHP runtime evidence:
  - `8.5.3` from `WPORG-02`
  - `8.3.30` from Local binary during `WPORG-03`, `WPORG-04A`, `WPORG-04B`, `WPORG-04D`, `WPORG-04E`, `WPORG-04G`, `WPORG-04H`, `WPORG-04I`, `WPORG-04J`, `WPORG-04K`, `WPORG-04L`, `WPORG-04M`, `WPORG-04N`, `WPORG-04O`, `WPORG-04P`, `WPORG-04Q`, `WPORG-04R`, `WPORG-04S`, `WPORG-04T`, and `WPORG-04U`
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

- Artifact: `dist/wporg-04u/vms-1.0.0-public-release.zip`
- SHA-256: `1da175f784580f21806ae4dc2aa2c214f94d83d032e8b04bd8c3666467399f4c`
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
- `php -l includes/social-share/template-engine.php`
  - PASS
- `php -l includes/social-share/audit.php`
  - PASS
- `php -l includes/core/lineup-schedule.php`
  - PASS
- `php -l includes/core/vendor-user-links.php`
  - PASS
- `php -l includes/core/event-plan-review.php`
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
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04h --force`
  - PASS
  - current artifact SHA-256: `b66aded43d758b2d8bc5de66b57f8ceb8e69927d89eb91c6dadf1a26ed9a734c`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04i --force`
  - PASS
  - current artifact SHA-256: `aceda39376ec454c49106a1a41ec88a96ec5ff49acfb97ae730308c93120aaa8`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04j --force`
  - PASS
  - current artifact SHA-256: `06905c9a2c62788056adf9d99857dce37df82e4f7f87a6e7fbb57df5c0d498c5`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04k --force`
  - PASS
  - current artifact SHA-256: `894cf8280489f4d52561be45e88b4ee317693ad2b61cc400c45ad41b4dceb209`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04l --force`
  - PASS
  - current artifact SHA-256: `2814fe4b4867cfb67a03cef47c135dacf785963e0e46cf47af5282a40c80d03b`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04m --force`
  - PASS
  - current artifact SHA-256: `08bbe1f22254facca50dfabb096ed06b45b06126efe1111d872ac5c3202ca1e3`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04n --force`
  - PASS
  - current artifact SHA-256: `51c6d2c127845440ffce9eee2c07428ce67b5c8dc90a1b3208c6a0601680b8a9`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04o --force`
  - PASS
  - current artifact SHA-256: `b5ff1494aa35b48e3d108f51d8efc584bacde4fbeceb433acca60ebdac06b690`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04p --force`
  - PASS
  - current artifact SHA-256: `720dc9a32f3609ebb54ef77227b0cf85123776554f7b62c347e8a77077fcf152`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04q --force`
  - PASS
  - current artifact SHA-256: `bdb050f722c55de68a34c1690a7f8143f024801e638a7f00f1a14975c96d3671`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04r --force`
  - PASS
  - current artifact SHA-256: `4f336a3eb71714ac703633ca0b8f7222ed371881372416779b9159ca9203dd5d`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04s --force`
  - PASS
  - current artifact SHA-256: `efd8df8bbbd0c823fcbc4aa5dfc999e7166d25c89ee163aaa59779198603886b`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04t --force`
  - PASS
  - current artifact SHA-256: `3943d3219317a3099c29d4d9678ae266c93aa762fa21b8852efc5f258fadb4ac`
- `php scripts/build-public-release.php --allow-dirty --output-dir dist/wporg-04u --force`
  - PASS
  - current artifact SHA-256: `1da175f784580f21806ae4dc2aa2c214f94d83d032e8b04bd8c3666467399f4c`

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

- `3170` total findings
- `945` errors
- `2225` warnings

Comparison:

- `WPORG-02` source-tree baseline: `4567` total / `1646` errors / `2921` warnings
- `WPORG-03` packaged-plugin run before direct-access guard fixes: `3900` total / `1342` errors / `2558` warnings
- `WPORG-03` packaged-plugin final: `3888` total / `1330` errors / `2558` warnings
- `WPORG-04A` packaged-plugin final: `3808` total / `1329` errors / `2479` warnings
- `WPORG-04B` packaged-plugin final: `3695` total / `1317` errors / `2378` warnings
- `WPORG-04D` packaged-plugin final: `3692` total / `1316` errors / `2376` warnings
- `WPORG-04E` packaged-plugin final: `3605` total / `1316` errors / `2289` warnings
- `WPORG-04G` packaged-plugin final: `3554` total / `1266` errors / `2288` warnings
- `WPORG-04H` packaged-plugin final: `3491` total / `1203` errors / `2288` warnings
- `WPORG-04I` packaged-plugin final: `3435` total / `1179` errors / `2256` warnings
- `WPORG-04J` packaged-plugin final: `3408` total / `1158` errors / `2250` warnings
- `WPORG-04K` packaged-plugin final: `3319` total / `1078` errors / `2241` warnings
- `WPORG-04L` packaged-plugin final: `3290` total / `1061` errors / `2229` warnings
- `WPORG-04M` packaged-plugin final: `3278` total / `1049` errors / `2229` warnings
- `WPORG-04N` packaged-plugin final: `3274` total / `1045` errors / `2229` warnings
- `WPORG-04O` packaged-plugin final: `3270` total / `1045` errors / `2225` warnings
- `WPORG-04P` packaged-plugin final: `3268` total / `1043` errors / `2225` warnings
- `WPORG-04Q` packaged-plugin final: `3255` total / `1031` errors / `2224` warnings
- `WPORG-04R` packaged-plugin final: `3224` total / `999` errors / `2225` warnings
- `WPORG-04S` packaged-plugin final: `3205` total / `980` errors / `2225` warnings
- `WPORG-04T` packaged-plugin final: `3175` total / `950` errors / `2225` warnings
- `WPORG-04U` packaged-plugin final: `3170` total / `945` errors / `2225` warnings

Dominant remaining codes:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `567`
- `WordPress.Security.NonceVerification.Recommended`: `597`
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`: `256`
- `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`: `232`
- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `169`
- `WordPress.DB.DirectDatabaseQuery.DirectQuery`: `294`
- `WordPress.DB.DirectDatabaseQuery.NoCaching`: `256`
- `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `155`
- `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`: `146`
- `WordPress.DB.PreparedSQL.NotPrepared`: `72`

High-level category counts:

- nonce and input handling: `1198`
- database and SQL safety: `1101`
- i18n placeholder comments / ordering: `583`
- escaping and output safety: `169`
- date/time API usage: `27`
- development logging: `43`

Packaged rerun note:

- No previously unseen Plugin Check code categories appeared in `WPORG-04U`.
- The selected `includes/admin/staff-list-columns.php` batch reduced the file from `7` findings (`5` errors / `2` warnings) to `2` warnings-only by clearing `4` `MissingTranslatorsComment` findings and `1` `EscapeOutput` finding without widening into staffing workflow, query-shape, or capability logic.
- The only non-target steady state outside `includes/admin/staff-list-columns.php` was the unchanged pre-existing `plugin_header_nonexistent_domain_path` warning.

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
- `includes/admin/event-command-center.php`: `79` -> `15`, with `63` -> `0` errors
- `includes/admin/staffing.php`: `59` -> `3`, with `24` -> `0` errors
- `includes/portal/staff-portal.php`: `86` -> `59`, with `46` -> `25` errors
- `includes/portal/vendor-portal.php`: `152` -> `63`, with `80` -> `0` errors
- `includes/public/venue-calendar-shortcode.php`: `29` -> `0`, with `17` -> `0` errors
- `includes/public/vendor-profiles.php`: `14` -> `2`, with `12` -> `0` errors
- `includes/public/templates/vendor-profile.php`: `4` -> `0`, with `4` -> `0` errors
- `includes/social-share/template-engine.php`: `8` -> `4`, with `0` -> `0` errors
- `includes/social-share/audit.php`: `7` -> `5`, with `2` -> `0` errors
- `includes/core/lineup-schedule.php`: `12` -> `0`, with `12` -> `0` errors
- `includes/core/vendor-user-links.php`: `68` -> `36`, with `39` -> `7` errors
- `includes/core/event-plan-review.php`: `21` -> `2`, with `19` -> `0` errors
- `includes/admin/schedule.php`: `52` -> `22`, with `30` -> `0` errors
- `includes/admin/staff-list-columns.php`: `7` -> `2`, with `5` -> `0` errors
- extracted-package rerun-only steady state outside selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `1` in `WPORG-04U`
- remaining isolated Event Plans regressions now use the shared bootstrap and pass from the nested repo workspace
- `tests/vendor-availability-ux.php` and `tests/add-dispatch-open-vendor-needs.php` now use the shared bootstrap resolver
- packaged nonce/input blocker surface: `1517` -> `1198`
- packaged i18n placeholder/comment surface: `792` -> `583`
- packaged output-escaping surface: `317` -> `169`
- packaged date/time surface: `86` -> `27`

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
| Plugin Check: nonce/input | `1198` remaining findings in mutating admin, portal, and admissions flows | BLOCKER | This pass stayed deliberately outside mutation paths, so Event Plans, portal save, and admissions request flows still need dedicated regression coverage before widening request hardening. | Partially |
| Plugin Check: escaping | `169` remaining `EscapeOutput` findings | BLOCKER | Shift the next render-surface audit toward the Staff Portal, shared admin render shells, approvals/menu surfaces, and the remaining public output sites. | Partially |
| Plugin Check: SQL safety | `1101` remaining DB/SQL findings, including `155` unescaped DB-parameter reports, `146` interpolated SQL reports, and `72` `PreparedSQL.NotPrepared` reports | BLOCKER | Prioritize real parameter-safety and preparation issues before generic direct-query/no-caching warnings. | Partially |

## Should Fix Before Submission

| Check | Finding | Classification | Recommended action | Safe fix applied |
| --- | --- | --- | --- | --- |
| Plugin Check: i18n | `583` placeholder-comment and ordering findings remain | SHOULD FIX BEFORE SUBMISSION | Add `translators:` comments and ordered placeholders in batches after security blockers | Partially |
| Plugin Check: date/time APIs | `27` `date()` findings remain | SHOULD FIX BEFORE SUBMISSION | Review each case and convert UTC-safe display paths to explicit timezone-safe helpers where appropriate | Partially |

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
| Browser QA | No manual browser/admin walkthrough was run in this pass | DEFER | Use `WPORG-04I` or final clean-site QA after the remaining blocker-reduction pass | No |

## Checks Not Run Or Limited

- PHPCS / WPCS
  - still not run because `phpcs`, `composer`, and repo config were absent
- activation-hook execution
  - the builder intentionally warns instead of mutating a WordPress site during activation-hook checks

## Recommended Next Task

- `WPORG-04V`
- Scope:
  - repeat the deliberate hotspot scan from the `WPORG-04U` packaged baseline and prefer another isolated admin-only render/i18n or final-escaping slice before widening into request, auth, refund, ticketing, or availability-save flows,
  - `includes/admin/approvals-review-queue.php`, `includes/admin/menu.php`, or `includes/admin-ui/shell.php` are better candidates than notification/date logic, shared helpers, or raw ICS output,
  - if packaging-warning cleanup is preferred over another runtime batch, handle the unchanged `plugin_header_nonexistent_domain_path` warning in a separate metadata micro-batch.
