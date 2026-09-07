# Disposable staffing validation

These tests intentionally refuse to discover a normal Local WordPress installation. They require exactly `BVM_STAFFING_TEST_ROOT=/private/tmp/bvm-staffing-db/wordpress`, database `staffing_test`, and Unix socket `localhost:/private/tmp/bvm-staffing-db/mysql.sock`. Use a fresh standalone InnoDB server/data directory with networking disabled. Never change these checks to point at normal Local.

Copy WordPress core without its configuration or content. Create a fresh config/database, an administrator with ID 1, and a minimal theme. Disable WP-Cron; block `pre_wp_mail` and `pre_http_request` in a disposable MU plugin. Load this source checkout as the canonical Backstage Venue Manager plugin. Install the existing staffing schema, then run the explicit lifecycle migration as the disposable administrator. For the reschedule integration tests, install disposable WooCommerce/The Events Calendar copies and their schemas. PHP 8.3 with a 512 MB memory allowance was used for the final full integration runs.

With that fixture installed:

```sh
BVM_STAFFING_TEST_ROOT=/private/tmp/bvm-staffing-db/wordpress php tests/staffing-lifecycle-checkpoint-forensics.php
BVM_STAFFING_TEST_ROOT=/private/tmp/bvm-staffing-db/wordpress php tests/staffing-lifecycle/integration.php
BVM_STAFFING_TEST_ROOT=/private/tmp/bvm-staffing-db/wordpress php tests/staffing-lifecycle/deadlock.php
```

The forensic entry point runs the real transaction suite and independently coordinated concurrent workers before its eleven historical regression checks. `concurrency.php` may also be run directly. Workers inherit `PHP_BINARY` and the hard-bound environment. `integration.php` exercises real SQL, metadata and reschedule rollback, authorization/nonces, schema failure, connection loss, and shared rendering. `deadlock.php` deliberately creates a real InnoDB deadlock and proves explicit retry of the same operation. These are destructive fixtures only within the named disposable database; do not run them against an existing site.

The repository SQL suites use their existing disposable sibling-source comparison at `../../backstage-venue-manager`; copy this worktree's `includes/` and `assets/` there for source comparisons only. Do not synchronize either normal plugin tree for this task.

See `docs/bvm-staffing-lifecycle-implementation-report.md` for the design, migration/promotion gates, exact environment boundary, and limitations. Shut down the temporary HTTP/database servers and remove the browser-only login helper after validation. Keep only clearly identified disposable evidence needed for review.

Integration-wave fixture: the bootstrap also accepts exactly `/private/tmp/bvm-authority-integration-20260906/runtime/source-wordpress`, database `bvm_integration_source`, socket `localhost:/private/tmp/bvm-authority-integration-20260906/runtime/mysql.sock`. This is a separate fresh fixture, not a relaxation to discover arbitrary installations. Run `financial.php` for real SQL labor projection and `migration-receipt.php` for additive migration/history/idempotence proof in newly cloned disposable tables. Historical fixture and rollback artifacts are preserved.

`financial-provider.php` boots real active plugin lists in four separate requests using the integration fixture only. Set `BVM_FINANCIAL_PROVIDER_MODE` to `core-first`, `addon-first`, `inactive` or `absent`, arrange the matching disposable activation/file state first, and restore it afterward. It validates actual Financial/ECC/Event Plan/profitability rendering after canonical lifecycle mutations. Never redirect this test at normal Local.

Orphan gate regression: `preflight-no-mutation.php` creates and removes its own `bvm_orphan_gate_` tables in the allowlisted fixture. It runs both the standalone CLI and the runtime migration with missing slot/event/staff, active duplicate, invalid commitment, incompatible partial index, duplicate audit operation, nontransactional engine, and failed-query fixtures. Blocking receipts must preserve every schema, row, option/version canary and auto-increment, with zero mutation SQL issued.

`current-shape-rehearsal.php` additionally requires a newly initialized MySQL **8.0.35** at exactly `/private/tmp/bvm-orphan-shape-20260906/mysql.sock`, with networking and MySQL X disabled, and database `bvm_staffing_shape`. Import the separately authorized targeted capture into that disposable database only. The expected original counts are 8 assignments, 3,400 slots, 1,337 audit rows and 1,204 rollups; the original orphan IDs must be exactly 19/20/21. It proves the original gate cannot mutate, rehearses the precise three-row data repair and rollback, launches two independent migration workers against the repaired shape, validates every preserved row and idempotence, then restores the original disposable shape exactly. `BVM_STAFFING_REMOVE_TEST_CONTAINER=1` is a separate hypothetical fixture variant and never authorizes deleting normal-local containers. Retain private data/dumps outside the repository and webroot. Shut down and remove only these newly created disposable servers/data directories after testing.

The normal-local read-only gate is `scripts/staffing-lifecycle-preflight.php`. It requires explicit Unix socket/database/prefix parameters and uses `MYSQL_PWD` for a password. It never loads WordPress and cannot perform promotion or migration. See the revised seven-step promotion plan in `docs/authority-integration/promotion-plan.md`.

Population gate coverage now includes orphan slots without assignments, wrong post types, missing role taxonomy, invalid slot/assignment values, inactive-slot commitments, cache-only orphan roots, malformed/duplicate rollups, missing base columns and invalid revisions. It also explicitly accepts trashed plans, canceled history and multiple shifts per role. Gate fixtures clone terms/taxonomy as well as the transactional tables; no normal Local table is used for destructive tests. See `docs/authority-integration/staffing-population-retention.md` for the evidence-backed retention policy.

## Resource supervision after the September 6 disk incident

For new integration validation, use `scripts/lib/bvm-disposable-db.py` to own the
foreground MySQL 8.0.35 server and test command. Do not repeat the historical
manual-server instructions above. The integration bootstrap now refuses an
unguarded runtime. The supervisor is test-only and hard-bound to the integration
runtime; it cannot adopt the normal Local server or an existing datadir/socket.
It requires a new evidence receipt path and the verified Local MySQL 8.0.35 binary.
Pass the fixture setup/test command after `--`; that command inherits
`BVM_DISPOSABLE_DB_SOCKET` and `BVM_DISPOSABLE_DB_GUARDED=1`.

Limits: 3 GiB available before launch and throughout the run; 8 MiB per generated
log; 15 minutes overall by default (CLI maximum one hour); 60 seconds startup.
Observed successful database logs are under 2 KiB. An independent supervisor
checks every 50 ms while the test command runs, also during initialization and
startup. All child process groups are stopped on success, failure, signal or
timeout, escalating a stuck shutdown to SIGKILL. The datadir is removed only
AFTER group exit is proven. A runaway logger skips the normal shutdown grace.
A process-exit failure retains the datadir rather than repeating the incident.
Polling permits a small transient overshoot; failed evidence is capped to a
1 MiB tail per log after its writers stop. Success removes disposable server
logs. Neither normal Local logging nor installed server configuration is changed.

The exclusive `.staffing-db-guard.lock` owns the new datadir. Existing locks or
sockets are collision failures, never cleanup targets. The receipt records PIDs,
peak log sizes, free space, exit proof and database residue removal. The fixture
command must also remove its own newly created WordPress runtime. As with other
process supervisors, SIGKILL of the supervisor itself or deliberate child session
escape is outside the normal signal/timeout guarantee; tests run foreground
children and do not daemonize them.

`python3 tests/staffing-lifecycle/resource-guard.py` uses real temporary child
processes to test success, failure, startup failure/hang, server exit, runaway
logging, timeout, signals, stuck shutdown, child-group cleanup, collisions and
low-disk rejection. It never connects to a database or normal Local.

`population-rehearsal.php` imports an explicitly supplied private capture via
`BVM_POPULATION_CAPTURE` and the verified target backup via
`BVM_POPULATION_BACKUP`, into a new `bvm_population_` prefix inside the supervised
fixture. It accepts exactly the stopped 165/100 population or its actual repaired
shape, validates full old-column history and two independent migration workers,
and proves idempotence and exact rollback. Captured Event Plans are compared
through the shared resolver, editor, full ECC and light ECC. Consumer cache
writes are rolled back; any consumed disposable auto-increment is restored only
after proving that all rows and every other schema byte remain identical.
Bulky unchanged posts/meta are compared using ordered hashes to avoid retaining
several 83,500-row PHP copies. No normal-local rollback is performed.

The complete private setup/runner and run receipts remain under
`app/bvm-local-rollbacks/staffing-population-resume-20260906/`. Captures, private
rows and normal-local maintenance scripts are deliberately outside Git/webroot.
The regular blocker suite now performs 176 no-mutation assertions. The integration
fixture restores its deliberately injected invalid statuses before subsequent
whole-population gates. The deadlock test requires a real increasing server
counter, using MariaDB's status variable or MySQL's `INNODB_METRICS` counter.
