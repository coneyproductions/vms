# BVM-Only Add-on Runtime Harness

This harness proves the historical official-five add-ons against a real, disposable WordPress runtime where Backstage Venue Manager is installed only at its public identity:

```text
backstage-venue-manager/vendor-management-system.php
```

It does not use the normal Local database, change the normal site's active plugins, expose `vms/vendor-management-system.php`, or modify add-on production source.

The default `official_five` suite remains the Phase 3 baseline. Phase 4 adds a separately identified `additional_first_party` suite without changing the original scenario definitions or canonical Phase 3 reports.

## Run

From the repository root:

```sh
BVM_COMPAT_PHP_BIN=/path/to/php scripts/test-bvm-addon-runtime-compatibility.sh
```

Run the Phase 4 additional-integration suite explicitly:

```sh
BVM_COMPAT_SUITE=additional_first_party \
BVM_COMPAT_PHP_BIN=/path/to/php \
scripts/test-bvm-addon-runtime-compatibility.sh
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

1. installs a time-limited, test-only MU guard in both the disposable runtime and the normal Local site, then waits for any already-started normal cron process to become quiescent;
2. hashes the normal site's serialized `active_plugins`, cron, Weather options/snapshots, `wp-config.php`, and complete database checksums while skipping normal plugins and themes;
3. copies the existing local WordPress core into a newly created temporary tree;
4. stages the repository BVM runtime under `backstage-venue-manager/`;
5. copies the installed official five, WooCommerce, and The Events Calendar into that tree;
6. rejects historical or nonexistent BVM bootstrap identities;
7. creates a uniquely named `bvm_compat_*` MariaDB database using locally available connection settings without copying credentials into tracked files;
8. installs WordPress and plugin schemas only in that empty disposable database;
9. leaves a deliberate residue canary in that database and proves a reserved-domain HTTP request is rejected by `pre_http_request` before transport in both process families;
10. runs the scenario matrix in fresh WP-CLI processes;
11. empties the fixture's active-plugin option, drops the disposable database, proves the database and canary are absent through `INFORMATION_SCHEMA`, and removes the temporary WordPress tree;
12. re-hashes the complete normal-site preservation manifest while the guard is still active, restores the exact prior MU-plugin filesystem state, and fails if any cleanup or preservation assertion differs.

The exit trap repeats guarded cleanup after ordinary failures and handled signals. A database name must match the test-only `bvm_compat_*` boundary before it may be dropped, and a successful drop is not accepted until the server reports that schema absent. The normal-site guard is never packaged, expires automatically after two hours if the harness is killed uncatchably, and is removed only when its bytes still match the task-owned source and state.

Run `scripts/test-bvm-test-containment.sh` to inject a failure immediately after the HTTP and residue canaries. The self-test requires exit `86` and asserts database, runtime, normal guard, and lock cleanup from the outer process.

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

## Phase 4 extension

The `additional_first_party` suite uses `scripts/test-bvm-additional-runtime-compatibility.sh` and the `additional-*` PHP files beside the Phase 3 probe. Historical Phase 4 evidence recorded DRM Events Bridge as blocked while its source authority was unresolved. The current Wave 2B profile instead stages the exact accepted Bridge 0.2.2 Git object, current Router 0.1.3 authority, and the reviewed Sponsorships 0.1.28 successor candidate. It also stages the original five and current accepted first-party candidates for supported-coexistence scenarios. Its activation, BVM-present, BVM-absent, load-order, third-party-dependency, REST/AJAX/cron-registration, and coexistence evidence is written under the distinct `bvm-additional-runtime-compatibility` report name.

## Historical Phase 6A record

Phase 6A originally supplied a separate immutable-source mode for Bridge 0.2.2, the then-accepted Router 0.1.4, and Commerce 0.2.12. Its reports remain historical evidence, but that obsolete mode is no longer selectable by the current harness. The current `mixed` profile below supersedes its source/version gates; no Phase 6A artifact was rewritten.

## Wave 2B current-authority mode

The default current additional profile hard-gates these reconciled sources:

- DRM Events Bridge 0.2.2 commit `b1efcc974233a3b43c2a9efa30533c6688f87320`, Git tree `3e2c1e49411c6811065f7f8eacca8fdaf736ed60`;
- DRM Event Router 0.1.3 commit `21fadbd00e2ccebeb42ef8cb0334160af6e8b288`, Git tree `52b6e45421e0809526c46c34c7997ec024cc56df`;
- DRM Calendar Intake 0.2.4 at clean commit `590f2ac40e346a5a1aae384a9e90b37d72b4cf80`;
- Sponsorships 0.1.28 from `../vms-sponsorships`, with reviewed tree-manifest SHA-256 `a1ec835bc577d86955ea010789319a9edd638e1d79bdb731e837a6276a9e47f5`;
- Commerce Discounts 0.2.13 and Weather Risk 0.1.12 from their accepted local candidates.

Bridge and Router are extracted from immutable Git objects rather than copied from the dirty Bridge worktree. Sponsorships is staged from the separate clean repository candidate and is never activated in the normal Local site. The Wave 2B source-authority test independently proves each immutable commit/tree, active-versus-candidate relationship, exact changed-path set, candidate status boundary, version markers, and source manifests.

After a controlled same-basename Bridge promotion moves the forensic Git worktree outside the web root, set `BVM_COMPAT_DRM_BRIDGE_REPO` to that preserved worktree. The harness still extracts commit `b1efcc9` from the immutable object store and refuses any other Git tree; it never treats the active release directory as repository history.

Both shell harnesses source `scripts/lib/bvm-test-containment.sh`. During a run, an independently launched normal Local web, FPM, direct `wp-cron.php`, or unrelated WP-CLI process fails closed while the initiating tokenized harness process and disposable runtime remain available. Reserved-domain canaries prove that WordPress HTTP is rejected before transport. The additional probe covers the Intake/Router/Bridge public contract, one REST registration, failure behavior, both load orders, BVM present/absent, and Sponsorships registry, Event Plan, capability, public rendering, package/application/assignment/fulfillment/metric, and fail-closed flows using only disposable synthetic rows.

When the tokenized process deliberately performs a normal-site acceptance boot, the guard raises BVM's performance-telemetry thresholds and short-circuits updates to `vms_resource_fingerprint_log` for that process. A bounded promotion probe may also list existing transient names in the state file's `normal_preserved_transients.transient` or `normal_preserved_transients.site_transient` arrays; the guard then returns their stored values without expiring or refreshing them. Existing operational options that a read-only bootstrap would otherwise rotate may be listed in `normal_preserved_options`, which returns their old values through WordPress's option-update filter. These controls keep a verification boot from appending performance telemetry or rotating known operational caches; they do not suppress unlisted application options, migration writes, or disposable-runtime telemetry.

## Wave 3A Calendar Feeds and Data Tools provider coverage

Wave 3A retains the same containment boundary and adds candidate-only staging for Backstage Calendar Feeds `0.1.4` and VMS Data Tools `0.5.55`. Installed Calendar Feeds `0.1.3` and installed Data Tools `0.5.54` are not promoted or modified.

The official harness now runs 19 scenarios. Its added `bvm-data-tools-directory-absent` scenario physically omits the Data Tools directory and requires BVM's provider resolver to return a safe unavailable result with no registered Data Tools provider. The ordinary Data Tools cases stage candidate `0.5.55` and assert both load orders, exactly one provider, provider/version/contract provenance, valid calculated empty data, Vendor Portal Website/Square scope, BVM recognition, and the existing menu/reporting surface. Inactive-but-files-present scenarios require Data Tools constants, bootstrap functions, provider callbacks, hooks, and cron/action registration to remain absent.

The additional harness now runs 52 scenarios. It adds Calendar Feeds BVM-first, add-on-first, and standalone/provider-absent cases. BVM-present cases require one `manage_options` BVM registry page and no duplicate top-level Backstage menu. Standalone mode requires one reachable fallback page and safe missing-Intake health. Both complete coexistence orders stage the Calendar and Data Tools candidates with accepted P0 BVM, Weather, Commerce, Intake, Router, Bridge, and Sponsorships.

The final PHP 8.3 receipts are:

- official 19/19: `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-addon-compat-report.Klttfp/`;
- additional 52/52: `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-addon-compat-report.X6Wj0k/`.

Both report database/runtime/residue/guard/lock cleanup, external HTTP blocked before transport, the independent Local process guard, unchanged normal cron/Weather/database state, and unchanged normal active plugins. The additional run also proves the preserved Bridge forensic worktree unchanged. Because Wave 2C promoted Bridge into a non-Git release directory, current additional runs must point `BVM_COMPAT_DRM_BRIDGE_REPO` at the preserved immutable object store; the final receipt used `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/bvm-local-rollbacks/wave2c-20260906T023120Z/drm-events-bridge/drm-events-bridge-0.2.1-preserved-tree` and still extracted accepted commit `b1efcc974233a3b43c2a9efa30533c6688f87320`.

Full Wave 3A architecture, source hashes, promotion/rollback plans, and A–Z evidence are in `docs/addon-compatibility/wave-3a-calendar-feeds-data-tools-provider.md`.

## Wave 3B-2 accepted Data Tools authority

Wave 3B-2 promoted the exact 81-file Data Tools `0.5.55` source, normalized tree `8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b`, to the canonical normal-local basename. For active-equivalent validation, set `BVM_COMPAT_DATA_TOOLS_SOURCE_DIR` to the installed `wp-content/plugins/vms-data-tools` directory. The retained companion tree is byte-identical, but the installed directory is now the accepted runtime authority rather than an unpromoted candidate.

The final PHP 8.3 post-promotion receipts are under `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/bvm-local-rollbacks/wave3b2-20260906T125640Z/data-tools`: official-five `19/19` in `post-promotion-official`, additional ecosystem `52/52` in `post-promotion-additional`, and installed normal-local provider acceptance `154/154` in `final-normal-local-acceptance`. Full promotion, rollback, state, and A–Z evidence is in `docs/addon-compatibility/wave-3b2-data-tools-controlled-local-promotion.md`.
