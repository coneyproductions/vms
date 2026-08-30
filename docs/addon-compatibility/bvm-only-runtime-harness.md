# BVM-Only Add-on Runtime Harness

This harness proves the historical official-five add-ons against a real, disposable WordPress runtime where Backstage Venue Manager is installed only at its public identity:

```text
backstage-venue-manager/vendor-management-system.php
```

It does not use the normal Local database, change the normal site's active plugins, expose `vms/vendor-management-system.php`, or modify add-on production source.

## Run

From the repository root:

```sh
BVM_COMPAT_PHP_BIN=/path/to/php scripts/test-bvm-addon-runtime-compatibility.sh
```

The harness auto-discovers the surrounding Local WordPress root and installed plugin directory. Override discovery only when needed:

```sh
BVM_COMPAT_WP_ROOT=/path/to/wordpress \
BVM_COMPAT_ADDON_ROOT=/path/to/wordpress/wp-content/plugins \
BVM_COMPAT_PHP_BIN=/path/to/php \
BVM_COMPAT_WP_CLI_BIN=/path/to/wp \
BVM_COMPAT_OUTPUT_DIR=/path/to/evidence \
scripts/test-bvm-addon-runtime-compatibility.sh
```

Use a PHP version supported by BVM and the installed dependencies. The canonical Phase 3 runs used PHP 8.3.

## Isolation contract

For every invocation, the harness:

1. hashes the normal site's serialized `active_plugins` option while skipping all plugins and themes;
2. copies the existing local WordPress core into a newly created temporary tree;
3. stages the repository BVM runtime under `backstage-venue-manager/`;
4. copies the installed official five, WooCommerce, and The Events Calendar into that tree;
5. rejects historical or nonexistent BVM bootstrap identities;
6. creates a uniquely named `bvm_compat_*` MariaDB database using locally available connection settings without copying credentials into tracked files;
7. installs WordPress and plugin schemas only in that empty disposable database;
8. runs the scenario matrix in fresh WP-CLI processes;
9. empties the fixture's active-plugin option, drops the disposable database, removes the temporary WordPress tree, and re-hashes the normal site's active plugins;
10. fails if cleanup or the normal-site hash comparison fails.

The exit trap repeats safe cleanup after interruptions. A database name must match the test-only `bvm_compat_*` boundary before it may be dropped. If database deletion fails, the exact name is printed prominently.

## Scenarios

The harness runs 18 scenarios:

- each official add-on with BVM as the only core, in both core-first and add-on-first plugin-file order;
- all five together with BVM, WooCommerce, and The Events Calendar, in both core-first and add-ons-first order;
- each add-on without BVM;
- Express Bar with BVM present and WooCommerce absent, complementing its no-BVM/WooCommerce-present scenario.

WooCommerce is loaded for Data Tools reporting coverage, Express Bar, and Refer-a-Friend. The Events Calendar is loaded for Events Slider. Dependency failures are asserted separately from BVM recognition.

The probe exercises real WordPress plugin loading plus `plugins_loaded`, `init`, `current_screen`, `admin_init`, `admin_menu`, `vms_admin_register_pages`, `admin_notices`, and relevant `admin_enqueue_scripts` callbacks. It does not invoke venue, event, vendor, ticket, order, or outreach business actions.

## Runtime contracts

`tests/addon-compatibility/runtime-contracts.php` records the historical inventory explicitly. The runtime probe validates live declarations after the requested plugin load order:

- 63 add-on/function consumption entries;
- 53 unique BVM functions;
- consumed BVM classes and constants that are actual runtime requirements;
- add-on callbacks attached to BVM hook contracts.

`tests/addon-compatibility/runtime-contracts-test.php` protects the 63/53 inventory shape. It is intentionally separate from the real WordPress probe; source-string presence is not accepted as runtime proof.

## Reports and strictness

The output directory contains:

- `bvm-addon-runtime-compatibility.report.json` — complete normalized scenario evidence;
- `bvm-addon-runtime-compatibility.report.txt` — concise matrix and scenario summary;
- `source-manifest.json` — versions, entry-file hashes, deterministic tree hashes, and Fill Dates Phase 2 file hashes;
- per-scenario raw/debug logs;
- `activation-setup.log` and `scenarios.tsv`.

Fatal errors, database errors, official-five/BVM warnings or notices during the exercised lifecycle, failed assertions, cleanup failures, and normal-site activation drift make the harness fail. Upstream deprecations, blocked-network update checks, translation-timing notices, and intentional diagnostic logging remain captured and separately classified rather than hidden.

The normalized JSON/text reports omit database names, temporary paths, credentials, secrets, and timestamps, allowing two clean runs to be compared byte-for-byte.

## Focused validation

```sh
sh -n scripts/test-bvm-addon-runtime-compatibility.sh
php -l tests/addon-compatibility/runtime-contracts.php
php -l tests/addon-compatibility/runtime-contracts-test.php
php -l tests/addon-compatibility/runtime-preload.php
php -l tests/addon-compatibility/runtime-probe.php
php -l tests/addon-compatibility/source-manifest.php
php -l tests/addon-compatibility/build-report.php
php tests/addon-compatibility/runtime-contracts-test.php
php tests/fill-dates-menu-hook-compatibility.php
php tests/fill-dates-admin-notice-placement.php
```

Run the complete shell harness twice and compare both normalized reports with `cmp` or SHA-256 before treating the matrix as repeatable.
