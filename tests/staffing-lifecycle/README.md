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
