# Phase 3A normal-local acceptance — 2026-09-07

**ACTIVE / ACCEPTED LOCALLY**

Phase 3A is installed in the current normal-local Backstage Venue Manager runtime. The real WordPress admin/WooCommerce flows passed with disposable local records and an isolated fake refund gateway. Production, staging, WordPress.org release authority, and companion source remain unchanged. Phase 3B was not started.

## A. Promotion preflight

Normal-local runtime: `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public/wp-content/plugins/backstage-venue-manager`, plugin header version **1.2.0**. WordPress **7.1**, WooCommerce/database **11.0.1**, HPOS enabled. The header version remains unchanged; the source commit and file hashes identify this post-release development runtime.

Accepted starting source: `89f75a55678fe57c19c9e4380b1f084873af3db2`. Candidate worktree: `/private/tmp/bvm-cancellation-phase3a-20260907/packages/vms-github-reconcile`; branch `work/cancellation-refund-completeness-20260907`. It began acceptance with only the expected uncommitted Phase 3A runtime, tests, and documentation; no staged changes. See [promotion-preflight.json](</Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3a/promotion-preflight.json>) for complete Git state, active companion versions, option/schema authorities, and original delta hashes.

The historical dirty mirror at `wp-content/plugins/packages/vms-github-reconcile`, branch `work/unreleased-2026-06-18`, HEAD `79da784`, was preserved. Its files, status, index hash, and protected stash match the preceding accepted preservation receipt. No reset, cleanup, stash operation, or historical-mirror edit occurred. All companion and legacy plugin trees match that receipt. Five intervening accepted ECC/staffing differences from that older receipt match baseline `89f75a` exactly; they were not Phase 3A edits. See `harness/source-preservation-comparison.json` and `harness/installed-versus-baseline.json`.

Normal-local has 246 tables and 824 options. Schema authorities include vendor core `vendor_core_v7`, staffing lifecycle preflight ready, claims `ticketing_claims_v1`, ticket mutation audit `ticket_mutation_audit_v1`, inventory audit `ticket_inventory_audit_v2`, private files `1`, tasks `1.2.0`, notifications `1.0.0`, and Square mirror `square_ticket_mirror_log_v1`. Complete values and companion versions are preserved in the preflight JSON.

## B. Exact promoted delta

Seven runtime files: five replacements and two additions. Every old and final SHA-256 is recorded in [promoted-delta.json](</Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3a/promoted-delta.json>).

| File | Change |
| --- | --- |
| `includes/core/cancellation-purchases.php` | New canonical discovery, preflight, audited exclusions, execution intent, and reconciliation |
| `includes/admin/cancellation-report.php` | New persistent report, protected acceptance POST, history, residual alert, final redirect |
| `includes/core/cancellation-adapters.php` | Delegate purchase discovery and refund execution |
| `includes/core/cancellation.php` | Preserve snapshots/history and truthful completion states |
| `includes/core/load.php` | Load report admin module |
| `includes/cpt/event-plans.php` | Report entry/redirect and eliminate view-time backfill |
| `assets/js/vms-event-plan-workflow.js` | Confirm the broader purchase scope and separate preflight approval |

All 390 other shared installed files match accepted baseline `89f75a` byte-for-byte. The seven runtime files match the candidate and its disposable sibling `../../vms`. No installed companion changes or structural normalization occurred.

Corrections found during real acceptance: tolerate the absent optional `wc_can_refund_order()` helper in Woo 11; ignore an unknown event metadata field when its numeric value demonstrably identifies a different Event Plan/TEC event; run the report redirect after the existing editor redirect; replace ticket-only confirmation wording; emphasize positive residuals in a separate red notice. These corrections are included in the final source and tests.

The original implementation report and original 56-check evidence are preserved unchanged under [original-implementation](</Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3a/original-implementation/implementation-report.md>). This acceptance report supersedes its readiness recommendation.

## C. Rollback package

`/Users/treyconey/Local Sites/serenade-range-local-test-site/app/bvm-local-rollbacks/cancellation-phase3a-20260907`

Contains backups of all five replaced files, original hashes, and the two initially absent paths. [rollback.md](</Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3a/rollback.md>) and the package's `RESTORE.md` give the exact restoration procedure. Verify current final hashes first, restore each backed-up file, remove only the two additions, then verify original hashes. No activation, database-wide restore, or unrelated source replacement is required.

## D. Migration/schema results

No schema migration, schema-version update, activation change, option installation, or historical-record migration is introduced. Explicit cancellation actions extend existing job-summary metadata and add Woo order intent metadata through Woo CRUD. Existing summaries without canonical coverage remain readable and visibly unverified.

Repeated initialization, editor/report refreshes, and historical selection leave every table and option unchanged. Before and after cleanup, all original row hashes match exactly across 246 tables; all 824 options and structural schemas match. Eleven auto-increment counters advanced through disposable inserts and were deliberately not reset. There is no migration failure path to execute; acceptance-persistence failure, stale scope, and uncertain refund recovery are covered by the focused suite.

## E. Browser acceptance

Chromium exercised the actual installed WordPress admin PHP and JavaScript, actual normal-local database, actual Woo HPOS objects/refunds, and actual Express Bar line-item snapshot helper. The browser used the prior acceptance discipline: private loopback `127.0.0.1:8798`, local administrator ID 1 with real capability/nonce checks, fixture-only POST routes, read-only SQL transactions for GET, and a gateway restricted to marked disposable orders. No static HTML substitute was used for normal-local acceptance. The harness and server were outside all plugin trees; the server is now stopped.

Native path: Event Plan cancellation controls → Mark Cancelled → sales stop/discovery → report preflight → reasoned exclusion if applicable → Accept preflight and execute refunds → reconciliation → persistent report → Event Plan report link → historical run. Preflight rendered order identity, ticket/preorder/add-on descriptions, quantities, original/prior/proposed/actual values, expected residual, exclusions and unresolved items.

| Synthetic case | Expected additional | Actual additional | Excluded | Unexplained residual | Final report |
| --- | ---: | ---: | ---: | ---: | --- |
| Tickets + six preorders + table add-on | $66 | $66 | $0 | $0 | Completed successfully |
| Deliberate preorder omission | $56 | $20 | $0 | $36 | Attention required; partial order |
| Reasoned preorder exclusion | $20 | $20 | $36 | $0 | Completed with exclusions |
| Prior $5 partial refund on $56 order | $51 | $51 | $0 | $0 | Completed successfully; cumulative Woo refund $56 |
| Two orders, one gateway failure | $112 | $56 | $0 | $56 | Attention required; one success and one failure |
| Gateway failure | $56 | $0 | $0 | $56 | Attention required; failed order identified |
| Unsupported $9 component, explicitly excluded | $56 | $56 | $9 | $0 | Completed with exclusions |

The unsupported-component submission without a reason was rejected; its subsequent reason was audited. Blank reasons on eligible components mean refund, not exclusion. The reason, actor, UTC time and job identity remain visible in the completed exclusion report.

Evidence: `harness/*-preflight.html`, `*-complete.html`, `*-persistent.html`, desktop/mobile PNGs, `runtime-results.json`, and `fake-gateway.jsonl`. Seven complete case flows plus **90 persistence/layout/read-only checks** and **7 legacy/communications checks** passed.

## F. Synthetic #7555 result

Synthetic order `14312`: two GA tickets ($20), six Corona preorders ($36), and a table add-on ($10). All three components were discovered together and reconciled to an actual $66 Woo refund with zero residual. Express Bar identity was written with the installed companion's native snapshot helper. This reproduces the ticket-plus-preorder/add-on architecture; it makes no claim about historical real order #7555.

## G. Residual detection

Synthetic order `14313` deliberately omitted the $36 preorder component. Final native Woo refund total and itemized additional refund were both $20; remaining balance was $36. The report prominently displays **Unexplained residual: USD 36.00**, highlights the preorder row, identifies the order, and cannot present clean success. See [desktop residual evidence](</Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3a/harness/omission-persistent-desktop.png>).

The omission harness initially triggered Woo's full-refund status follow-up because the requested and simulated amounts differed. That first synthetic attempt was archived, then only its disposable records were reset. The corrected mock preserves Woo's partial-refund classification. Final acceptance uses the clean $20 actual / $36 residual run.

## H. Persistence

Each case was reopened in a fresh browser context through its Event Plan's report link, refreshed, and reopened through its final historical run link. Job/run/event identity, actor/time, affected orders, expected/actual/excluded/residual totals and failure states remained stable. Both 1440px and 390px report views passed width checks. Two pre-existing cancellation records also rendered safely as coverage-unverified without backfill.

Reports existed in the normal-local database across independent requests before cleanup. Their complete serialized records, HTML and screenshots are now preserved in the durable audit; disposable Event Plans and orders have been removed, so their former admin URLs are not permanent audit links.

## I. Read-only, communications and write containment

Both read-only windows produced exact whole-database equality. No browser GET attempted a refund, cancellation mutation, exclusion, financial write, communications send, or BVM audit insertion. Cancellation runs created no reschedule communications ledger. A separate deliberate synthetic communications-history fixture verified **19 controls** remained owned by their independent forms, with subject/body/send fields excluded from Event Plan save FormData; its ledger and history remained stable on refresh.

Mail was blocked at `pre_wp_mail`, PHP sendmail, and external HTTP boundaries. Seventeen local mail attempts were blocked: nine during CLI fixture setup (recorded as GET `/`) and eight during explicit synthetic refund POSTs. Browser editor/preflight/report GETs attempted zero mail sends. Gateway logs contain only marked synthetic orders and `network: false`.

The harness suppressed pre-existing Action Scheduler housekeeping, ticket-audit retention pruning, edit locks, term-count updates and option housekeeping. The query guard continued to reject unrelated writes. Early fixture/harness refinements are recorded in logs: incomplete ticket capacity metadata caused a legacy Event Tickets/forensics recursion; supplying capacity metadata resolved it without companion or unrelated BVM changes. Existing admin warnings and the baseline task due-date SQL warning were not treated as new Phase 3A defects.

Cleanup first verified every nonfixture row against the original baseline across all 246 tables before deleting anything. Failed Woo refund objects had left four synthetic line-item rows; their `_refunded_item_id` values proved ownership by the disposable orders. They were included in the audited cleanup. See `harness/failed-refund-item-ownership.json`, `synthetic-record-archive.json`, `cleanup-receipt.json`, and `state-integrity-results.json`.

Final state: **all original rows restored exactly**, all options unchanged, schemas unchanged except advanced counters. No staffing, existing Event Plan/order/customer/ticket, companion configuration, document, or communications record changed.

## J. Regression results

Tests ran against an external test directory linked to the installed normal-local runtime; no tests were installed in the plugin. **14 of 16 selected commands passed**, with two confirmed baseline structural/historical exceptions. Numbered suites total **557 assertions**:

| Suite | Result |
| --- | --- |
| Cancellation purchase completeness | 58 passing |
| Financial authority | 234 passing |
| ECC optional context | 35 passing |
| ECC 2.0 fixture semantics | 103 passing |
| Staffing financial/value/provenance | 127 passing |
| Reporting provider contract; Data Tools provider decoupling and reporting | Pass |
| Ticket integrity scan-lock recovery | Pass |
| Staffing repository, final repository, matrix/rollup SQL contracts | Pass |
| Private-file upload API boundary | Pass |
| Event Plan workflow confirmations | Pass |
| Original report fixture browser coverage | 4 passing viewport/fixture combinations |
| Runtime PHP lint, workflow JS syntax, diff checks and sibling parity | Pass |

Baseline exceptions: the older private-file operations test requires the mirror-only `includes/safety/private-files.php` shim, absent from the accepted installed layout; it passes in the full candidate mirror, and the installed private-file implementation is unchanged. The historical G16 ticket-integrity projection test pins a superseded monitor block hash and fails on the unchanged accepted monitor. Neither failure is caused by Phase 3A, and neither was hidden by modifying runtime layout or weakening assertions. Complete results are in `harness/regression-results.json` and `harness/regressions/`.

The separate destructive standalone staffing/concurrency and full reschedule-apply suites require their dedicated disposable database, which was unavailable; they were not redirected at normal-local. Relevant staffing SQL/value contracts, real editor/ECC reads, native cancellation/refund writes, and communications form/history isolation passed. This is local acceptance with a fake gateway, not production payment-provider certification.

## K. Frozen authority

Verified again after testing; see [frozen-authority.json](</Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3a/frozen-authority.json>):

- Release branch remains `release/wporg-readiness-2026-09-07` at `04caf99ae7a98ac507c067a7535d2a672efa457b`.
- Qualified ZIP remains 396 files, SHA-256 `2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3`.
- Protected stash remains `d08e726804712dc233f0e37b217abd6389963863`, `WPORG-16D preserve unrelated sidebar+doc work`.
- No ZIP rebuild, release change, push, tag, submission, reviewer reply, production/staging change, or companion-source edit occurred.

## L. Final acceptance status and source authority

**ACTIVE / ACCEPTED LOCALLY**

Normal-local retains only the accepted seven-file Phase 3A runtime delta. Its authority is the focused commit containing the Phase 3A implementation and this receipt on `work/cancellation-refund-completeness-20260907`. The final commit, clean/staged state, exact paths, and runtime hashes are recorded in [source-authority.json](</Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3a/source-authority.json>). The historical dirty repository and frozen WordPress.org authority remain separate.
