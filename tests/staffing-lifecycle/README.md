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
