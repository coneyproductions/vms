# Phase 5B test baseline

Runtime authority: `3f4049014fcb151b0bb2113f4f1a688642c0855a`. Test checkpoint: `c5d78f8820cba5b2a8993c24dd7e3a325b9ce8e7`. Closeout changes only tests, fixture tooling and documentation. Runtime delta: **none**.

Future acceptance exceptions: **none**. All active catalog executions must pass. A missing fixture is a setup failure, not an allowed test failure or silent skip. `bootstrap-wordpress.php` and helper libraries are support files, not executable acceptance entries. The 27 entries routed out of the standalone runner comprise 26 separately executed WordPress tests and one support file.

The complete A–T report, 65-entry checkpoint reconciliation, 250-entry active catalog, expanded commands, logs and cleanup receipts are retained under `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-5b/`. Start with `phase-5b-reconciliation-report.md`, `remaining-test-inventory.json`, `current-reproduction-matrix.json`, and `historical-exception-inventory.json`.

## Current contracts and retired history

See [the permanent evidence disposition](history/phase-5b-evidence-disposition.md). The original scanner certificates remain unavailable and their historical claims are retired as unprovable history. No historical PASS is fabricated. Current scanner, migration and runtime behavior have independent deterministic tests.

Payables date/W-9/Event Credit cases remain in `payables-tax-credit-date-contract.php`. `payables-canonical-wordpress-fixture.php` tests post-migration vendor/payee/venue/Event Plan relationships, three compensation arrangements, canonical lineup normalization, workflow status, monetary normalization, tax blocking, date/DST behavior and deletion of created posts. Core builds in-memory bills; it does not persist payable/payment records or execute payment runs. Separate staffing financial SQL coverage verifies planned/proposed/committed compensation; actual paid labor remains explicitly unavailable, with no invented payment records.

Historical staff notification and ticket date tests were separated into current date contracts at the checkpoint. Real notification delivery/task authority replaces obsolete generation and delivery mocks. Checklist fixtures retain corrupt legacy JSON, scoping, defaults and replay coverage. Source SQL tests distinguish row primitives from command wrappers and verify the current stable plan/template identity. Real task suites own transaction, revision, recurrence, rollback, permission and recovery behavior.

SQL inventories retain exact owned suppression rules/counts and query anchors; line offsets and whole-file pre-migration hashes are not current contracts. Nonce compatibility is exercised by `wporg-prefix-b4-nonces.php` and `nonce-input-normalization.php`; boundary tests retain current action resolution, failure and capability ordering. Legacy hook, field, metadata and physical asset names are retained where the runtime retains them.

Native Event Tickets creates updated product titles during completed-order fixture setup. The reschedule test now refreshes object caches before the first preview and checks persisted titles after forced rollback. The original fingerprint and no-automatic-mail assertions remain. No rollback runtime correction was required.

## Run safely

Start each task with `scripts/codex-preflight.sh` and follow `docs/wporg-remediation-workflow.md`. Use a clean isolated worktree under `/private/tmp/bvm-test-debt-*/packages/`, based on the Phase 5B closeout test commit (which descends from the checkpoint). Existing source-parity tests require owned sibling `vms` and `backstage-venue-manager` source copies from the same accepted runtime. Never point those fixtures at an installed site. The Phase 5B worktree and disposable source copies are retained for exact reproduction.

Standalone runner:

```text
python3 scripts/test-debt-regressions.py --evidence PRIVATE_EVIDENCE_DIR --php PHP_BINARY --label UNIQUE_LABEL --frozen-zip QUALIFIED_FROZEN_ZIP [test-stem ...]
```

No stems runs every top-level PHP entry. `--reverse --timezone Asia/Tokyo` repeats the inventory in a different order/timezone. Every failure produces a nonzero runner exit; the manifest preserves full logs, commands, exclusion reasons, per-test temporary residue and cleanup. Network and database sockets are denied by macOS `sandbox-exec`; writes are limited to the owned work area/evidence. Each test owns a separate process group, which is terminated on timeout or lingering descendants. The runner refuses normal-local paths and unknown test names.

Real WordPress runner, launched **only** by the existing resource supervisor:

```text
python3 scripts/lib/bvm-disposable-db.py --server MYSQLD_BINARY --receipt PRIVATE_EVIDENCE_DIR/supervisor.json -- python3 scripts/test-debt-wordpress.py --evidence PRIVATE_EVIDENCE_DIR --php PHP_BINARY --mysql MYSQL_CLIENT --wp-cli WP_CLI_PHAR --core WORDPRESS_CORE_SOURCE --addons DISPOSABLE_ADDON_SOURCE_DIR --label UNIQUE_LABEL event-plan-editor-vendor-preservation event-plan-staff-eligibility payables-canonical-wordpress-fixture staff-tasks/checklist-fixtures
```

The supervisor intentionally retains its audited fixed root/socket; it refuses an existing server/datadir/lock. This is an explicit containment constraint, not automatic WordPress discovery. Only WordPress core is copied: no source wp-content, configuration or operational database. Fresh installation creates the synthetic administrator with ID 1. WooCommerce and TEC are explicit prerequisites; use `--event-tickets` for native Event Tickets / Plus cases. Activate optional fixtures only through explicit `--companion SLUG` arguments. `--admin-acceptance` selects the skipped-plugin WP-CLI provider harness; `--private-storage` supplies an owned non-web storage root; `--http-fixture` owns a loopback PHP server for the one exact external-ticketing proof URL. The native test requires `--event-tickets`.

`helpers/current-wordpress-fixture.php` requires the supervisor and runs the canonical lifecycle migration **before** Event Plan date writes or slot creation. Legacy eligibility history is separately labelled in its test. Google integration uses `BVM_GOOGLE_FIXTURE_ROOT`, an explicit private directory outside WordPress, with synthetic credentials and intercepted transport; it no longer pins a previous phase's personal evidence path.

The runner blocks HTTP and mail, disables automatic cron, permits only its owned MySQL socket, and deletes its WordPress/private fixture directories. All synthetic posts, options, transients, Woo state, task/payable models and custom rows are inside that disposable database; the independent supervisor records process exit and datadir removal. If it cannot prove exit, it retains the datadir and reports failure. Three Phase 5B attempts (including closeout admin-02) hit its existing EPERM teardown limitation; independent process-group and MySQL shutdown checks proved exit before owned-residue cleanup, with original failure receipts preserved. A successful supervisor is not by itself a passing suite: inspect each result. Runner failures propagate to the supervisor.

Synthetic release-tool fixtures exercise temporary test archives; the qualified frozen ZIP is read-only. No current BVM release artifact is built, promoted or qualified. Do not use these commands for normal-local promotion or real Google/mail transport. Exact expanded commands and separate environment-qualified results are in the durable evidence.


## Explicit source fixtures and counting

The retained isolated worktree has authenticated copies of seventeen companion sources. `scripts/test-debt-source-fixtures.py --manifest PINNED_JSON --receipt NEW_RECEIPT` copies only explicitly supplied, hash-matched sources into absent owned sibling directories; `--verify` verifies retained copies without replacing them. The closeout `companion-source-fixtures.json` supplies the original file hashes and paths. Missing or mismatched sources fail setup. Only requested companions are activated in disposable WordPress.

Additional read-only fixtures are the qualified frozen ZIP (SHA `2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3`), the Investor candidate file authenticated by `docs/financial-authority-investor-baseline.json`, and the frozen prefix certificate Git export at `85a1a16`. Keep the candidate at the owned sibling `investor-financial-candidate.php` and the export at `prefix-manifest-certificate`. Runtime parity siblings `vms` and `backstage-venue-manager` contain accepted-runtime source copies; never substitute links to the normal plugin trees. Exact retained paths and commands are in closeout evidence.

The broad set is 238 top-level entries: 211 standalone executions, 26 WordPress executions and one support file. Eleven nested accepted database suites and the additional native Event Tickets reschedule variant yield 249 active execution entries across 248 unique executable files, plus the one catalog support file. The standard and native reschedule variants deliberately count separately. One evidence-only historical entry was moved verbatim to `history/g15-payables-provenance.php.txt` and is retired, not PASS or SKIP. New helper/fixture files are not added to the executable total.

Order checks use fresh PHP processes, changed full standalone order/timezone, repeated native reschedule in one database, and reversed shared-database fixture/admin suites. A shared PHP interpreter for unrelated scripts with colliding top-level test doubles is not a supported combined runner. Database before/after census and whole-fixture destruction distinguish per-test cleanup from plugin setup state retained until group teardown. No normal-local database is accessed and no fresh 246-table normal-site measurement is claimed.
