# Staff Tasks authority tests

Run `concurrency.php`; it loads `authority.php` and then `edge-cases.php`. `worker.php` is the independent-process race/crash runner. These are real WordPress/MySQL integration tests, not tests for a normal Local database.

Use the existing `tests/staffing-lifecycle/bootstrap.php` hard-bound disposable environment and `scripts/lib/bvm-disposable-db.py` supervisor. Install disposable WordPress, WooCommerce and TEC, activate BVM, install the existing staffing schema, and pass the no-mutation preflight. The suite explicitly migrates the accepted staffing lifecycle and Staff Tasks schema before creating synthetic fixtures. Disable external HTTP and intercept all mail before bootstrap; the suite additionally asserts post-commit transport visibility.

The supervisor must own the fresh private MySQL socket/datadir and remove it after process exit. Never substitute normal Local, staging or production credentials. The accepted test invocation, installation runner and 200-assertion output are retained in the Phase 3D evidence directory named in `docs/staff-tasks-phase3d/local-acceptance.md` (`run-disposable.py`, `final18-*`, `disposable-18.json`).

The main suite exercises task identity/lifecycle/timing/definitions, permissions, real competing processes, transaction rollback and process interruption, notification queue recovery and uncertain attempts, recurrence/DST, read containment and the existing staff UI projection. Expected injected queue-write failure logs a diagnostic. Keep failed-run and teardown receipts; do not call a test successful solely because assertions passed if owned database processes or residue remain.
