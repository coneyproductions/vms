# Native Staff Tasks calendar acceptance

`integration.php` tests the installed calendar read/renderer against the existing hard-bound disposable WordPress/MySQL bootstrap. It uses canonical commands for synthetic tasks and dependency changes, then verifies placements, scoped filters, lifecycle, reschedule/pinning, Google health projection, server permissions, DST/timezone behavior, read containment, batch/single projection equivalence and bounded queries at realistic volume.

Run with `scripts/lib/bvm-disposable-db.py` and the disposable setup requirements in `tests/staff-tasks/README.md`. Set `BVM_CALENDAR_EVIDENCE` to a private writable evidence directory; HTML and `integration-result.json` are written there. The exact acceptance setup/installation runner is retained at `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-4b/run-disposable.py` (use a `calendar` run prefix). Never point this integration test at normal Local: it intentionally creates users, posts, task rows and 1,200 historical fixtures.

Final supervised run `calendar14` passes 238 assertions over 95 disposable tables, including the closed in-range review edge case. It observes zero calendar-read mutation/HTTP attempts and 15 queries at 200 visible tasks across 26 staff identities. The supervisor must also report `ok`, `no_process_remains` and `database_residue_absent` before considering a run complete.

Actual normal-local browser acceptance, 246-table containment, permission denial evidence, cleanup, transport/harness qualifications, regression receipts and source rollback are documented in `docs/staff-tasks-phase4b/local-acceptance.md`. No real Google account is used by this phase; existing Phase 4A real-account acceptance remains separate.
