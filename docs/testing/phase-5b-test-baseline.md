# Phase 5B test baseline

Runtime authority: `3f4049014fcb151b0bb2113f4f1a688642c0855a`. Phase 5B changes tests, helpers, runners and documentation only. The full baseline is **PARTIALLY RECONCILED**; do not replace remaining failures with a blanket legacy allowance.

Durable evidence: `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-5b/`. Start with `phase-5b-reconciliation-report.md`, `historical-exception-inventory.json`, `current-reproduction-matrix.json`, and `payables-forensic-report.md` there. Historical Phase 1–4B reports remain unchanged.

## Current contracts and historical proof

| Original test | Current coverage |
| --- | --- |
| `g15-payables-tax-credit-dates.php` | `payables-tax-credit-date-contract.php` retains runtime date/W-9/Event Credit assertions; `payables-canonical-wordpress-fixture.php` adds real WordPress relationships and export-read containment. |
| Original payables strict-JSON prerequisite | `historical/g15-payables-provenance.php` retains the original SHA-256 and finding/row checks. The artifact remains unavailable; this gate is **not PASS**. |
| `g15-staff-notification-date-windows.php` | `staff-notification-date-contract.php` retains floating UTC and digest-window helpers. Committed delivery, reminders, digest replay and recovery are tested by `staff-tasks/concurrency.php` and `staffing-lifecycle/notification-delivery.php`. |
| `g15-ticketing-date-windows.php` | `ticketing-date-window-contract.php` retains fixed-clock date formatting, exact-second horizons, DST, sales defaults and clamps. Historical reverse-migration hashes are not current runtime invariants. Tracked G14/G15 provenance-v2 evidence is unchanged. |
| `vendor-portal-bonus-progress-paid-basis.php` | `companion-data-tools-vendor-bonus-contract.php` explicitly tests the tracked Data Tools provider with isolated doubles. It does not boot an inactive companion in BVM. |
| Old mocked checklist replacement/generation | `staff-tasks/checklist-fixtures.php` uses the definition authority and real SQL, then explicitly corrupts one legacy JSON row to prove fail-closed generation. Authority/concurrency covers revisions, audit, replay, permissions and recovery. |

The obsolete whole-source reverse projections remain recoverable from the accepted Git parent. Removing those historical gates from current behavior tests does not certify the original missing scanner artifact, requalify a package, or claim historical scanner success.

SQL annotation inventories use the nearest named declaration plus occurrence and rule codes. They still reject additional, removed, relocated or changed suppressions; physical line drift is irrelevant. Twelve deleted pre-Phase-3D query sites are recorded individually in `sql-inventory-reconciliation.json`; their replacement authority runs in the real-SQL task suites. Row-primitive unit tests explicitly distinguish their mock transaction boundary from real transactions.

Nonce and asset tests use the accepted B3/B4 names. Internal action hooks, request controls, physical asset filenames, metadata and slugs retain their actual runtime identities. Handler/renderer doubles select the current nonce action while preserving native-verifier failure and capability-order assertions; `wporg-prefix-b4-nonces.php` and `nonce-input-normalization.php` own compatibility/input normalization coverage.

## Run safely

Start each task with `scripts/codex-preflight.sh` and follow `docs/wporg-remediation-workflow.md`. Use a clean isolated worktree under `/private/tmp/bvm-test-debt-*/packages/`, based on the accepted runtime or the Phase 5B test commit. Existing source-parity tests require owned sibling `vms` and `backstage-venue-manager` source copies from the same accepted runtime. Never point those fixtures at an installed site. The Phase 5B worktree and disposable source copies are retained for exact reproduction.

Standalone runner:

```text
python3 scripts/test-debt-regressions.py --evidence PRIVATE_EVIDENCE_DIR --php PHP_BINARY --label UNIQUE_LABEL [test-stem ...]
```

No stems runs every top-level PHP entry. `--reverse --timezone Asia/Tokyo` repeats the inventory in a different order/timezone. Every failure produces a nonzero runner exit; the manifest preserves full logs, commands, exclusion reasons, per-test temporary residue and cleanup. Network and database sockets are denied by macOS `sandbox-exec`; writes are limited to the owned work area/evidence. Each test owns a separate process group, which is terminated on timeout or lingering descendants. The runner refuses normal-local paths and unknown test names.

Real WordPress runner, launched **only** by the existing resource supervisor:

```text
python3 scripts/lib/bvm-disposable-db.py --server MYSQLD_BINARY --receipt PRIVATE_EVIDENCE_DIR/supervisor.json -- python3 scripts/test-debt-wordpress.py --evidence PRIVATE_EVIDENCE_DIR --php PHP_BINARY --mysql MYSQL_CLIENT --wp-cli WP_CLI_PHAR --core WORDPRESS_CORE_SOURCE --addons DISPOSABLE_ADDON_SOURCE_DIR --label UNIQUE_LABEL event-plan-editor-vendor-preservation event-plan-staff-eligibility payables-canonical-wordpress-fixture staff-tasks/checklist-fixtures
```

The supervisor intentionally retains its audited fixed root/socket; it refuses an existing server/datadir/lock. This is an explicit containment constraint, not automatic WordPress discovery. Only WordPress core is copied: no source wp-content, configuration or operational database. Fresh installation creates the synthetic administrator with ID 1. WooCommerce and TEC are explicit prerequisites; use `--event-tickets` for native Event Tickets / Plus cases. Optional BVM companions are not activated by this runner.

`helpers/current-wordpress-fixture.php` requires the supervisor and runs the canonical lifecycle migration **before** Event Plan date writes or slot creation. Legacy eligibility history is separately labelled in its test. Google integration uses `BVM_GOOGLE_FIXTURE_ROOT`, an explicit private directory outside WordPress, with synthetic credentials and intercepted transport; it no longer pins a previous phase's personal evidence path.

The runner blocks HTTP and mail, disables automatic cron, permits only its owned MySQL socket, and deletes its WordPress/private fixture directories. All synthetic posts, options, transients, Woo state, task/payable models and custom rows are inside that disposable database; the independent supervisor records process exit and datadir removal. If it cannot prove exit, it retains the datadir and reports failure. Two Phase 5B attempts hit its existing EPERM limitation; independent process-group and MySQL shutdown checks proved exit before owned-residue cleanup, with original failure receipts preserved. A successful supervisor is not by itself a passing suite: inspect each result. Runner failures propagate to the supervisor.

Do not use these commands for packaging, Plugin Check, ZIP creation, normal-local promotion, or real Google/mail transport. Exact expanded commands and separate environment-qualified results are in the durable evidence.

## Recorded baseline

The two broad standalone runs each report 153 PASS, 53 FAIL and 32 routed entries; 42 previously failing tests now pass, with no prior PASS regression. The combined executable catalog reports 186 PASS, 56 FAIL, eight NOT_RUN and one support file. Of eight NOT_RUN entries, five are intentionally excluded release/package qualification commands, two require installed companion acceptance fixtures, and one requires a dedicated private-storage configuration fixture. No blanket expected-failure or exit-success allowance is installed.

The remaining failures include 53 explicitly listed standalone gates, one missing Sponsorships integration prerequisite, the missing original payables proof, and a separate native Event Tickets/Plus reschedule APPLY fingerprint mismatch. Standard Woo/TEC reschedule passes; it does not erase that native-provider failure. See the durable `remaining-failures.json` for the exact first failing gate and evidence per test.
