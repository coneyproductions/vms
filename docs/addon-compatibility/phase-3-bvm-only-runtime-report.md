# BVM Add-on Compatibility Phase 3 Report

## Baseline

- Branch: `work/unreleased-2026-06-18`
- Starting HEAD: `74817b246c305bb16e9f09fd2201f699e3eac8a5`
- Starting worktree: clean
- Protected stash: `WPORG-16D preserve unrelated sidebar+doc work` present at `stash@{0}`
- BVM: repository root, `1.2.0`
- Events Slider: `../../vms-events-slider`, `1.0.9`
- Fill Dates: `../../vms-fill-dates`, `0.1.7` plus the installed Phase 2A/2B patch
- Data Tools: `../../vms-data-tools`, `0.5.53`
- Express Bar: `../../vms-express-bar`, `0.6.22`
- Refer-a-Friend: `../../vms-refer-a-friend`, `0.2.5`
- WooCommerce: `../../woocommerce`, `11.0.1`
- The Events Calendar: `../../the-events-calendar`, `6.17.3`
- WordPress: local core `7.1`
- PHP used for canonical evidence: `8.3.33`

## Isolation and identity proof

Each canonical run copied the local WordPress core into a new temporary tree and created a different, uniquely named `bvm_compat_*` database. WordPress, BVM, dependency, and add-on schemas were installed only in that empty database. Cleanup dropped the database and removed the WordPress tree before the normal site's activation state was checked again.

The normal site's pre/post active-plugin SHA-256 was identical:

```text
24c5bdcdfcf8e0759ffcdefce52810291dc4389fb9ad8d69d5a106a9dbd02088
```

The normal `wp-config.php` hash also remained unchanged. No command activated or deactivated a plugin in the normal site.

Runtime identity evidence:

```text
BVM active: yes
BVM plugin basename: backstage-venue-manager/vendor-management-system.php
BVM version: 1.2.0
VMS_PLUGIN_FILE: wp-content/plugins/backstage-venue-manager/vendor-management-system.php
Historical standalone VMS main file in fixture: no
Historical standalone VMS active: no
vms.php: absent
vms/vms.php: absent
backstage-venue-manager.php: absent
```

Representative Scenario A active plugins:

```text
backstage-venue-manager/vendor-management-system.php
the-events-calendar/the-events-calendar.php
vms-events-slider/vms-events-slider.php
```

The `VMS_*` constants and `vms_*` functions observed by the add-ons were declarations from BVM at its public path, not evidence of a second historical plugin.

## Runtime compatibility matrix

| Add-on | BVM Recognized | No Fatal | Menu | Notices | Core-Absent Behavior | Load Order | Overall |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Events Slider | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| Fill Dates | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| Data Tools | PASS | PASS | PASS | PASS | PASS | PASS | **PASS WITH DEBT — works through the current late menu bridge cleanup** |
| Express Bar | PASS | PASS | PASS | PASS | PASS | PASS | **PASS WITH DEBT — works but reconstructs current menu hooks** |
| Refer-a-Friend | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |

## Detailed add-on results

### Events Slider

- BVM recognition/API: all 9 consumed BVM function contracts and the calendar-feed cache-bust constant existed in both load orders.
- Menu: no BVM admin-menu dependency or unexpected entry.
- Notices: no false BVM dependency warning.
- Core absent: both slider shortcodes remained registered with The Events Calendar loaded.
- Load order: core-first and add-on-first both passed.
- Errors: no fatal, database error, owned warning/notice, exception, or `doing_it_wrong` event.
- Classification: **PASS — BVM-only runtime compatible**.

### Fill Dates

- BVM recognition/API: all 16 consumed BVM functions, `VMS_Tours_Service`, and four BVM hook contracts were available.
- Menu: exactly one `vms-fill-dates` entry under BVM, capability `manage_options`, callback resolved, and the stored hook equaled WordPress's returned `vms_page_vms-fill-dates` hook.
- Assets/tours: both consumed the returned hook; the former predicted hook was not needed.
- Notices: no false dependency notice with BVM; without BVM, exactly one native `admin_notices` error accurately named Backstage Venue Manager and did not rely on a submenu page.
- Core absent: BVM post types were correctly recognized as absent and failure remained graceful.
- Load order: core-first and add-on-first both passed.
- Errors: no fatal, database error, owned warning/notice, exception, or `doing_it_wrong` event.
- Classification: **PASS — BVM-only runtime compatible**.

### Data Tools

- BVM recognition/API: all 33 consumed BVM function contracts, including the `vms_core` feature-detection contract, plus its consumed class, runtime constants, and four conditional BVM hook callbacks were available.
- Menu: one usable BVM `vms-data-tools` entry remained after the full lifecycle; the temporary Tools entry was removed, no top-level duplicate remained, capability was `read`, and the BVM bridge plus Data Tools callback resolved.
- Notices: no false missing-core warning with BVM.
- Core absent: BVM-dependent runtime modules stayed unloaded and one dependency/bootstrap warning appeared.
- WooCommerce: loaded for reporting-path coverage in BVM-present and core-absent scenarios; no WooCommerce state was confused with BVM detection.
- Load order: core-first and add-on-first both passed.
- Errors: no fatal, database error, owned warning/notice, exception, or `doing_it_wrong` event.
- Classification: **PASS WITH DEBT** because correctness currently relies on the complete late menu bridge/removal lifecycle, even though no runtime defect was reproduced.

### Express Bar

- BVM recognition/API: both consumed BVM function contracts and both consumed BVM constants were available.
- Menu: exactly one `vms-express-bar` and one `vms-bar-menu` entry appeared under BVM; neither became a top-level entry.
- Hooks/assets: WordPress returned `vms_page_vms-express-bar` and `vms_page_vms-bar-menu`, matching current reconstructed-hook assumptions, and assets enqueued on both hooks.
- Notices: with BVM and WooCommerce, neither dependency warning appeared. With WooCommerce present/BVM absent, only the BVM warning appeared. With BVM present/WooCommerce absent, only the WooCommerce warning appeared.
- Core absent: graceful dependency behavior remained independent from WooCommerce.
- WooCommerce: loaded for principal and no-core scenarios; deliberately absent in the separate dependency-isolation scenario.
- Load order: core-first and add-on-first both passed.
- Errors: no fatal, database error, owned warning/notice, exception, or `doing_it_wrong` event.
- Classification: **PASS WITH DEBT** because the source still reconstructs hook suffixes instead of storing WordPress's returned hooks; runtime evidence shows the current assumptions are correct today.

### Refer-a-Friend

- BVM recognition/API: all 3 consumed BVM functions and the `vms_admin_register_pages` contract were available.
- Menu with BVM: the registry owned all six RAF routes, every route resolved once under BVM, and no standalone RAF top-level menu appeared.
- Menu without BVM: one intended `vms-raf` top-level menu and its five child routes appeared.
- Notices: no false BVM dependency notice in either mode.
- WooCommerce: loaded in BVM-present and core-absent scenarios so ticket/referral dependencies were available; no WooCommerce issue was misclassified as BVM compatibility.
- Load order: core-first and add-on-first both passed.
- Errors: no fatal, database error, owned warning/notice, exception, or `doing_it_wrong` event.
- Classification: **PASS — BVM-only runtime compatible**.

## Cross-add-on result

Scenario B passed twice:

- BVM, WooCommerce, The Events Calendar, then all official five;
- all official five, WooCommerce, The Events Calendar, then BVM.

Both orders completed the exercised lifecycle without fatal errors, database errors, owned warnings/notices, menu conflicts, duplicate registrations, broken callbacks, false dependency warnings, or notice collisions.

## Fill Dates regression preservation

The installed Phase 2 files used in both canonical runs had these hashes:

```text
includes/admin-page.php  739848cd8e834fff0f09d49c1d7acb1f4fa98336885d5f51724d05a46335b542
includes/tours.php       39cd093ba83d088ac3acd97456786089becef4cf496285e702902498e117662c
```

The Phase 2A/2B patch artifacts were unchanged. Both committed focused regressions passed after the runtime work:

```text
tests/fill-dates-menu-hook-compatibility.php
tests/fill-dates-admin-notice-placement.php
```

## Errors and non-compatibility signals

Across each canonical 18-scenario run:

- fatal errors: `0`
- database errors: `0`
- official-five/BVM runtime warnings or notices during the exercised probe lifecycle: `0`
- uncaught exceptions: `0`
- `doing_it_wrong` events during the exercised probe lifecycle: `0`
- upstream deprecations under PHP 8.3: `0`
- normalized translation-timing notice families captured during bootstrap: `1`
- expected normalized blocked-network update-warning families: `3`
- intentional Data Tools diagnostic entries: `16` (`4` normalized trace families across `4` Data Tools scenarios)
- unclassified log signals: `0`

The translation-timing notice is a pre-existing BVM bootstrap/i18n timing signal, not an add-on recognition or integration failure. The three update warnings prove external WordPress.org access was blocked in the fixture. None was hidden; all remain separately classified in the JSON evidence.

## New defects

No new functional BVM compatibility defect was discovered in the official five.

## Harness files and evidence

- `scripts/test-bvm-addon-runtime-compatibility.sh` — disposable runtime/database orchestration and scenario matrix.
- `tests/addon-compatibility/runtime-preload.php` — safe WP-CLI admin/request context.
- `tests/addon-compatibility/runtime-contracts.php` — explicit historical API/class/constant/hook contracts.
- `tests/addon-compatibility/runtime-contracts-test.php` — protects the 63-entry/53-function inventory.
- `tests/addon-compatibility/runtime-probe.php` — real WordPress lifecycle, identity, API, menu, notice, dependency, and load-order assertions.
- `tests/addon-compatibility/source-manifest.php` — deterministic version/tree/file hashes.
- `tests/addon-compatibility/build-report.php` — strict normalized report and compatibility classification.
- `docs/addon-compatibility/bvm-only-runtime-harness.md` — reproduction and isolation documentation.
- `test-results/bvm-addon-runtime-compatibility.report.json` and `.txt` — canonical normalized evidence.

No shared production file or sibling live-tree file changed.

## Validation and repeatability

Two newly created/destroyed canonical runs produced byte-identical reports:

```text
JSON SHA-256  3b9d63787c2048771491b82664e74c38d1f9c4bed9b782294ac79f8fb44218d2
Text SHA-256  979ae95aa5687fd7666c77bcd6a6330adbadbe615856b47a3ce562cacd8da587
```

Both runs reported database cleanup `PASS`, runtime-tree cleanup `PASS`, activation-schema setup `PASS`, and unchanged normal-site active plugins. Syntax, contract-count, Fill Dates regressions, repository diff checks, staged diff checks, final preflight, and final worktree state are recorded in the task handoff and commit.

## Recommended next milestone

Move to runtime auditing of the eleven additional first-party BVM integrations. Keep Express Bar returned-hook storage and Data Tools menu consolidation as evidence-backed technical-debt candidates, but do not open a production remediation milestone for either without a reproduced functional failure or separate hardening authorization.

No push, packaging, deployment, production/staging change, WordPress.org submission, reviewer reply, or compatibility deployment occurred in Phase 3.
