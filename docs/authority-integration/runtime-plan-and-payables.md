# Disposable compatibility runtime plan

Read-only investigation, 2026-09-06. Canonical preflight reported only expected original dirt; protected stash exists, paths match, diff check is clean. No source, Git, normal-local runtime, database, guard, or lock was changed. The standalone historical payables test was executed under PHP 8.3.33 and stopped at its missing-fixture assertion before loading runtime files.

## Safe harness strategy

Do not run either harness with its default WordPress root. `scripts/lib/bvm-test-containment.sh` intentionally writes an MU guard and JSON state into that root, creates a lock keyed by that root, executes WordPress against its database, and sends a loopback HTTP request to its `home` URL. It does not offer a no-normal-root switch.

Both harnesses can remain unchanged by providing a completely disposable **source WordPress site**, a separate disposable MariaDB server, and an independent loopback PHP server for that site. The harness then creates its second disposable scenario site and schema. All variables named `normal_*` describe the disposable source in this run; do not label these receipts as proof of normal Local FPM protection.

Create a new unique directory below `/private/tmp`; do not reuse the prior staffing database or fixtures. Copy WordPress core only from `/private/tmp/bvm-staffing-db/wordpress` (exclude wp-content and wp-config.php), or copy normal Local core read-only with equivalent exclusions. Install the source site into a fresh schema on a fresh `--skip-networking` MariaDB socket. Create its empty mu-plugins directory. Use home/siteurl `http://127.0.0.1:<unused-port>` and start an independent PHP `-S 127.0.0.1:<port> -t <disposable-source>` server. Disable cron, external HTTP, automatic updates and email on installation. Use synthetic administrator credentials. Never copy normal wp-config.php or database credentials.

Available binaries:

- PHP 8.3.33: `/opt/homebrew/opt/php@8.3/bin/php` (fresh `-v` verified).
- MariaDB: `/opt/homebrew/opt/mariadb@10.11/bin/mariadbd`; matching install/client binaries under that prefix. Previous disposable receipt reports MariaDB 10.11.19, WordPress 7.1; verify fresh runtime versions again.
- WP-CLI PHAR: `/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar`.

Initialize server with `mariadb-install-db --no-defaults --datadir=<fresh>/db --auth-root-authentication-method=normal`; launch `mariadbd --no-defaults --datadir=<fresh>/db --socket=<fresh>/mysql.sock --pid-file=<fresh>/mysql.pid --log-error=<fresh>/mysql.log --skip-networking`. Set source DB_HOST to `localhost:<fresh>/mysql.sock`. Place matching MariaDB client binaries on PATH for WP-CLI database operations.

Stage a read-only-source add-on directory in the disposable work area. Both harnesses rsync add-ons into their runtime. Additional suite requires a real clean Git checkout for drm-calendar-intake and reads exact Bridge and Router Git objects; use copied checkouts with metadata, not symlinks into normal runtime. Overlay the reconciled investor candidate into the staged vms-investor-portal path. Use accepted candidate paths for Data Tools, Commerce, Calendar Feeds and Sponsorships. Weather candidate comes from integrated repo companion-plugins, while the expected historical Weather ZIP is read/extracted from the staged archive directory; no ZIP creation needed.

Invocation after fresh source WordPress install and independently running loopback server (shell variables below must hold absolute disposable paths):

```sh
export PATH="/opt/homebrew/opt/mariadb@10.11/bin:$PATH"
export TMPDIR="$integration_tmp"
export BVM_COMPAT_PHP_BIN=/opt/homebrew/opt/php@8.3/bin/php
export BVM_COMPAT_WP_CLI_BIN=/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar
export BVM_COMPAT_WP_ROOT="$integration_tmp/source-wordpress"
export BVM_COMPAT_ADDON_ROOT="$integration_tmp/addon-sources"
export BVM_COMPAT_DRM_BRIDGE_REPO="$integration_tmp/addon-sources/drm-events-bridge"
export BVM_COMPAT_DRM_ROUTER_REPO="$integration_tmp/addon-sources/drm-event-router"
export BVM_COMPAT_SPONSORSHIPS_SOURCE_DIR="$integration_tmp/addon-sources/packages/vms-sponsorships"
BVM_COMPAT_OUTPUT_DIR="$integration_tmp/evidence/official" "$integrated_repo/scripts/test-bvm-addon-runtime-compatibility.sh"
BVM_COMPAT_OUTPUT_DIR="$integration_tmp/evidence/additional" "$integrated_repo/scripts/test-bvm-additional-runtime-compatibility.sh"
```

Official expected matrix: 19 scenarios. Additional expected accepted matrix: 52 scenarios. No harness was run during this investigation. Run sequentially; verify actual reports, activation logs, source hashes, cleanup TSVs, database absence and canary absence. Then shut down only owned PHP/MariaDB processes and remove only owned disposable source/schema files, preserving evidence. Retain normal-local before/after filesystem hashes independently without booting it. The process-boundary request goes only to the disposable server; the deliberately blocked WordPress HTTP canaries never reach transport.

## Historical payables classification

**C: original archived fixture genuinely unavailable in searched local evidence; the test also retains a stale fixture dependency.** `tests/g15-payables-tax-credit-dates.php:31` hard-codes `/tmp/wporg-dbzero-g14.qulnlt/plugin-check.strict.json`, then requires SHA-256 `c5fe4d23b3cdf632f239632a23f2c58f9ccf7b8e293ff4b9e71f65101527aa17`, 181 findings, 139 errors, 42 warnings, and exact historical row locations. Fresh execution exits 255 with `Authoritative DB-zero/G14 strict JSON is missing.` File inventory searches of `/private/tmp`, local rollback evidence, attachments, Downloads, Documents, Desktop, `.codex` and the Local site app tree found no candidate strict JSON. Existing remediation ledger explicitly states the original byte artifact is unavailable and not represented as reproducible.

Do not fabricate a JSON file at that path, alter the expected digest, or claim this test passed. Current `tests/g15-ticketing-date-windows.php` already migrated to committed `tests/fixtures/g14-g15-provenance-v2/provenance.json`, whose historical scan evidence includes the two payables rows. That versioned provenance is legitimate documentary evidence, but is not the missing byte-identical strict JSON and cannot silently substitute for its hash gate. A separate narrowly scoped modernization could follow ticketing's provenance approach while preserving all payables runtime/projection assertions; it was not performed here. Financial authority tests and current integration matrices establish different behavior and do not retroactively certify the historical G15 audit.
