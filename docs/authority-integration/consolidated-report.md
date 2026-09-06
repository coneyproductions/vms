# BVM Financial Authority + Staffing Lifecycle integration

2026-09-06. Source and disposable testing only. This report concerns the isolated integration candidate; normal Local has not been promoted. Runtime validation and candidate commit receipts are recorded in the final section after checks complete.

## A–D. Authority and worktree inventory

Authoritative base: `checkpoint/bvm-accepted-local-2026-09-06` at `1e53fa3e4afc4301ff9c5b912df1a4bfc83f7444`, clean at its existing Wave4 checkpoint worktree. Financial v2 is clean at `02619f276ffde1a4fc04426c4404237d1bc2d8f6`. Staffing is clean at `18146de3d652a269cb9b056da23efffda9141382`, with `96309bdf5795a3944f002dee4c21811394362443` confirmed as ancestor. Both descend from Wave4. No fetch/pull/remote lookup was used.

The full local worktree/branch/HEAD inventory is [worktree-inventory.txt](worktree-inventory.txt). Existing readable worktrees were checked: original dirty branch at `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`; Outreach release clean at `27ee1bbac2ebc1a6122a886dd171605130393657`; Financial v2 and Staffing as above; Wave4 as above; detached g14-reconstruct clean at `2c8f790b9128d547b8bc0a27a714253fb6671bea`. Numerous old registered worktrees are missing/prunable; no prune or removal occurred. These are historical registrations, not the canonical mirror/live trees. Original preflight found the expected dirt described by the task; canonical mirror/live paths existed and protected stash was present. The new integration preflight passed clean before any implementation merge.

The contaminated `work/financial-authority` worktree was identified from Git worktree metadata at Wave4. Its working contents were not read, salvaged, merged, edited or cleaned. Its dirty status is accepted from the task rather than independently certified; this preserves the instruction not to touch it.

No existing ecosystem-audit ref/worktree was found. The internally delegated audit read the clean Wave4 checkpoint and local companion source, then wrote a separate temporary artifact. Its documentation is incorporated here as [ecosystem-audit.md](ecosystem-audit.md), rather than creating a redundant user-managed task or branch. No audit code changes or deletions occurred.

## E–H. Exact overlap and integration strategy

Independent file lists: [Financial files](financial-files.txt), [Staffing files across both commits](staffing-files.txt). Exact named PHP function/class changes, including new services/tests and top-level bootstrap changes, are in [branch-symbols-and-review.md](branch-symbols-and-review.md). The [Staffing forensics](staffing-forensics.md) cover schema, locking, handlers, UI and tests.

| Overlap | Classification | Resolution |
| --- | --- | --- |
| `includes/core/load.php` | CLEANLY COMPOSABLE | Financial registers shared ticket/snapshot services near reporting providers; Staffing registers lifecycle/UI/migration next to staffing. Preserve all registrations and load the new labor adapter after lifecycle. |
| `tests/p0-source-consistency-repair.php` | CLEANLY COMPOSABLE | Financial extracts ticket authority and updates financial assertions; Staffing asserts matrix lifecycle delegation at separate lines. Keep both sets. |
| ECC/Event Plan shared staffing + financial surfaces | SEMANTIC CONFLICT | Financial's saved aggregate labor must not masquerade as lifecycle-current or paid labor. Replace that read with a current read-only normalized labor projection and explicit planned/committed/unavailable-paid basis. |
| Profitability and goals/forecast | SEMANTIC CONFLICT at consumption boundary | Profitability remains a labeled mixed planning estimate using planned labor once. Forecast remains forecast. Commitments are separately shown; no extra subtraction and no paid-payroll inference. |
| Ticket/provider/current truth | CLEANLY COMPOSABLE | Preserve extracted accepted resolver/provider contract and ticket request cache. No direct Data Tools implementation loading added in BVM. |

No textual conflict or architectural conflict was found between the two implementation branches. No shared named function body was edited by both branches. File overlap did not cover staffing.php, Event Plan rendering or ECC itself: those are semantic producer/consumer overlaps.

The [strategy](integration-strategy.md) was saved before worktree creation. Financial first establishes the common consumer vocabulary/service; Staffing then supplies lifecycle authority. Neither branch depends on the other's obsolete function signature. The new worktree began clean directly from Wave4, then fast-forwarded to the original Financial commit and merged the original Staffing tip with `--no-ff --no-commit`. Git composed both overlaps automatically. The merge stayed uncommitted while semantic reconciliation and validation ran. No reconstruction from contaminated source and no cherry-pick rewriting were needed.

Integration branch: `work/bvm-authority-integration`. Worktree: `/private/tmp/bvm-authority-integration-20260906/packages/vms-github-reconcile`. Evidence root: `/private/tmp/bvm-authority-integration-20260906`. Sibling `vms` and `backstage-venue-manager` paths under this evidence root are disposable source fixtures, not normal installed plugins. For the legacy Vendor Portal source test, the disposable `vms` portal file intentionally uses the read-only copied installed legacy source; canonical staffing source comparisons use the separate canonical replica. No normal tree was synchronized.

## I–L. Combined architecture and semantics

Staffing preserves canonical normalized slots/assignments and Proposed/Confirmed/Declined/Canceled semantics, revisions, explicit actions, reason requirements, permissions/nonces, transactional locking, concurrency protection and atomic audit. A site/prefix advisory lock serializes cooperating staffing writers. The connection adapter disables replay/reconnect; state/revision/window, audit and rollup invalidation commit together. Post-commit events preserve existing subscriber hooks; failed or uncertain commits are not falsely reported successful. Forensic/spec and runtime implementation history remain intact.

Financial contract version 2 retains transaction ticket receipts, separately reported manual values, modeled forecast and unavailable accounting totals. The separate reporting-provider contract is still version 1. Ticket revenue remains channel/refund/provider scoped; true zero is distinguishable from unavailable/stale/failure. Legacy actual-total metadata remains ambiguous observed evidence and cannot override current receipts or certify final profit. Investor's separate verified-hash source patch is tested in a disposable companion copy only; installed Investor's direct loading/other architectural debt remains on the backlog.

New `bvmgr_staffing_get_financial_labor()` uses a read-only REPEATABLE READ consistent SQL snapshot plus the existing staffing advisory lock, stock no-replay connection, explicit schema/engine/identity checks and guaranteed transaction/filter/lock cleanup. It reads current normalized slots and assignments, raw role rate metadata, canonical windows and plan metadata from that snapshot. It does not read/recompute stored labor rollups or persist financial data. Wrong/missing authority, legacy-only state, duplicate active pairs, unknown status, missing identities, read/lock failures and missing rates/windows fail unavailable as appropriate. Known empty normalized staffing is zero. Inactive normalized history suppresses legacy fallback.

| Financial observation | Value and basis |
| --- | --- |
| Planned labor | `PLANNED_LABOR`: active slot required headcount × configured slot/role rate/window, including open positions. Proposed, declined or canceled decisions do not erase an active planned position. |
| Committed labor | `COMMITTED_LABOR`: confirmed assignments only, using assignment override then slot then role compensation and canonical scheduled window. No inference from actual-time columns to paid payroll. |
| Tentative labor | The staffing adapter exposes proposed-assignment estimate separately; proposals are not commitments. Operational Assigned remains Proposed + Confirmed. |
| Actual paid labor | `UNAVAILABLE`: no verified paid payroll authority exists. |
| Forecast margin | Modeled revenue less configured direct/processing costs and planned labor once; unavailable operand means unavailable margin. |
| Provisional ticket contribution | Transaction receipts less known reported direct/processing costs; explicitly excludes labor and missing expenses. |
| Profitability scorecard | Transaction receipts, reported/configured direct costs, reported fees, planned labor and explicitly estimated bar contribution; labeled mixed planning estimate with omitted/unavailable event counts. |

Planned and committed figures overlap and must never be added. Planned costs round per slot; commitments round per person. A fractional-hour rate can yield a penny difference even at equal headcount; this is deterministic estimation, not settlement. Neither observation establishes payroll actuals or final accounting.

The combined financial snapshot is no longer request-cached, so same-request lifecycle changes update labor immediately. The accepted independent ticket request cache remains unchanged. Operator meta cache is invalidated before reading entries. ECC, Event Plan and profitability use the same financial contract/rendering vocabulary; full/light staffing still shares normalized truth. Narrow ECC changes say Assigned instead of Filled, expose Proposed/Confirmed, and add planned/committed labor to existing summaries. No ECC redesign was implemented.

Independent review corrections: role termmeta/term_taxonomy InnoDB gates and direct snapshot reads; operator metadata freshness; rejection of stale numeric labor fields when availability is unavailable; documented cent rounding. No authentication, public payment, nonce or delivery semantics were changed by reconciliation.

## M. Migration requirements and receipt

Migration remains explicit, additive and unhooked from normal bootstrap/activation. It adds assignment `revision` default 0, nullable audit `assignment_id` and `operation_id`, unique operation index and assignment audit index. It preserves all historical rows and introduces no pair-uniqueness cleanup or implicit lifecycle backfill. Existing incompatible indexes/engines fail closed.

[Fresh migration receipt](migration-receipt.json) reconstructs the representative Wave4 schema in a newly cloned prefix within the allowlisted disposable database by removing only the additive lifecycle fields from fresh copies. It confirms pre-migration schema unavailable, successful migration, unchanged duplicate/history inventory, default-zero revisions, null legacy audit identifiers, and identical schema on a second run. It preserved 36 assignments and 131 audit rows. Only those newly created fixture tables were dropped afterward. The original prior staffing database/artifacts were not reused or modified.

DDL is not rollbackable; partial additive installation requires inspection/forward completion. Future source rollback should retain additive history/schema and keep staffing writers stopped until a compatible forward repair. See [promotion plan](promotion-plan.md).

## N–Q. Validation and limitations

Financial authority tests pass 234 assertions with Woo available and 234 with Woo absent (468 total, replacing the original 396-equivalent coverage with explicit staffing basis assertions). The tested wrapper resolves actual Event Plan metabox/report/ECC financial output. Provider/P0/goals/staffing/ticketing/portal/source suites pass with correct disposable source fixtures. Investor patch baseline and candidate hashes match the carried manifest; source semantics tests pass.

Cross-domain tests pass 127 assertions covering all 15 requested cases: proposed + forecast, confirmed commitment, declined/canceled exclusion, deterministic change, empty staffing, unavailable/failure, paid receipts, zero receipts, forecast revenue, transaction + commitment, refunds, manual reported values, stale provider + current staffing, and ECC/Event Plan agreement. Assertions include value, basis, provenance, availability and no double subtraction. A separate real SQL suite passes 33 assertions for current same-request state, canonical estimator agreement, overrides/metadata snapshot, read-only DML guards, cancellation/decline, SQL failure/recovery and cleanup.

Staffing runtime: 11 updated forensic regressions; real concurrency suite reaches 86 database assertions including overlapping confirmations, duplicate creation and matrix/response races; lifecycle integration reaches 102 assertions including authorization/nonces, audit/rollup/connection loss rollback, external transactions, rescheduling and schema gates; genuine InnoDB deadlock and explicit retry reaches 39 assertions. Counts are per-suite cumulative and overlap; they must not be summed as unique cases.

[Browser receipt](browser-receipt.md) proves Accept, confirmed persistence, required-reason rejection, Cancel, Repropose, Decline and reload using actual integrated controls/controller/service on synthetic loopback fixtures. This is component/handler browser integration with a test-only actor adapter, not a full normal-site login/navigation certification. The test helper was removed from the webroot and the tab closed.

Initial source-test setup failures were resolved without changing runtime: copied accepted Data Tools/Calendar source fixtures and the legacy portal fixture; both Fill Dates tests now load the actual compatibility helper their companion bootstrap requires. Dirty-original-only Fill Dates/Outreach test filenames absent from Wave4 were not imported. Accepted source suites cover Calendar (109 isolated assertions), Data Tools, Commerce contract/Square activation, Weather provider/ECC and DRM Router/Bridge/Intake. No unrelated normal-local promotion tests were run.

Historical payables classification **C: original archived byte fixture genuinely unavailable in searched local locations; stale historical fixture dependency remains**. `g15-payables-tax-credit-dates.php` requires the exact missing strict JSON and SHA-256 `c5fe4d23b3cdf632f239632a23f2c58f9ccf7b8e293ff4b9e71f65101527aa17`. It stops before runtime assertions. The committed provenance-v2 documentary evidence is not the missing artifact and was not substituted. This test is explicitly not passed, and no historical data was fabricated. Details/search scope are in [runtime-plan-and-payables.md](runtime-plan-and-payables.md).

Full hardened matrix results, syntax counts and containment are recorded in the final receipt below. The initial official run exposed fixture setup debt: lifecycle intentionally rejects date writes before explicit migration, yielding empty calendar fixtures. Both probes now require their guarded random disposable root/database and run explicit migration before product fixtures; date persistence is asserted. Initial failure evidence is retained, not relabeled a pass. Source-bootstrap WordPress theme notices are documented fixture notices, distinct from product failures.

## R–S. Ecosystem backlog and ECC brief

The complete [ecosystem audit/backlog](ecosystem-audit.md) covers every requested component and additional first-party sources. Classification is based on source connectivity, not an unverified assertion that every installed plugin is active or configured. Leading work: Investor supported-contract adoption; lifecycle consumer policy in Staff Tasks/portals/Ops/communications; pass/referral/checkout/current-event boundaries; Agreements/Outreach/follow-up continuity; then workflow and incomplete-product decisions. Safety is disconnected/incomplete, native Social providers are stubs, payouts are not a settlement implementation. Nothing was deleted or retired by inference.

The [ECC design brief](ecc-design-brief.md) organizes show-day operations, financial, people, marketing/communications, documents/agreements and quick actions/reports. Event Plan owns configuration/intention, ECC owns current state/actions, dedicated reports own dense provenance/comparisons, portals own authenticated personal work. It deliberately avoids duplicating dense configuration into ECC. This is a later implementation brief only.

## T–W. Provenance, commit and promotion

The final integration merge preserves the exact original Financial commit and both exact Staffing commits as ancestors. No squash/rewrite/rebase occurred. Candidate SHA and graph are supplied in the final local commit receipt; the original dirty development branch is not a merge target. No push was performed.

[Promotion plan](promotion-plan.md) covers explicit target authorization, disposable acceptance first, source manifest/sync, full and targeted database backups, schema preflight, normal-local read-only acceptance, controlled explicit migration, idempotence/history comparison, staffing/financial checks, notifications and rollback requirements. It is a plan, not execution.

The baseline branch file lists and PHP symbol map above are exact. The final integration manifest lists runtime adapter/snapshot/load/ECC/profitability changes, cross-domain and migration tests, guarded runtime-probe setup, test fixture corrections and integration documentation. It contains no unrelated refactor or broad formatting pass.

## X–Z. Containment, preservation and next task

Runtime uses a fresh MariaDB 10.11 server with TCP networking disabled, dedicated Unix socket/database and copied WordPress core/dependencies. PHP 8.3 is explicit. Hardened harnesses point their so-called normal/source root at the separate disposable source WordPress; their guards and locks never enter normal Local. HTTP/process canaries and random-schema cleanup must pass in final receipts. Mail transport is blocked; cron disabled. No payment, external communication, SSH, remote WP-CLI, staging, production, deployment, upload, ZIP creation, tag, submission, Git remote operation or protected-stash mutation occurred.

Before/after independent content fingerprints cover the original 1,783-file dirty mirror, canonical 384-file installed BVM, legacy 408-file vms, Data Tools and Investor. Original HEAD/status/index and protected stash `d08e726804712dc233f0e37b217abd6389963863` are separately compared. Git's shared repository metadata necessarily gains the authorized new worktree/local branch/merge commit; this is not an original working-content change. Normal Local was not booted or queried by this task, so preservation does not claim to freeze unrelated normal database activity. The task's actions never connect to it.

Recommended next task, after green integrated evidence: controlled normal-local acceptance/promotion of this exact source candidate under the migration plan, with explicit authorization and writers held until schema/source checks pass. Investor companion modernization and Staff Tasks lifecycle policy are high-priority follow-ups; the full ECC redesign should follow accepted integrated architecture and the documented design brief. Historical payables fixture modernization remains separate.

## Final current validation receipt

- Hardened official-five: **19/19 PASS**. [Full report](bvm-addon-runtime-compatibility.report.json), [containment cleanup](official-containment-cleanup.tsv).
- Hardened additional ecosystem: **52/52 PASS**. [Full report](bvm-additional-runtime-compatibility.report.json), [containment cleanup](additional-containment-cleanup.tsv). Includes accepted companions, provider/third-party absence, coexistence and both load orders.
- New real Financial/provider acceptance: **4/4 modes, 78 assertions each, 312 total PASS**. [Summary](financial-provider/summary.json). Core-first, add-on-first, inactive-but-installed and physically absent each exercise real plugin loading, actual empty transaction records, proposal → confirmation → cancellation, planned $50 with committed $0 → $25 → $0, unavailable paid payroll, same-request ECC/snapshot agreement, actual Event Plan goals metabox and profitability row/render output. No stubbed financial totals enter these runtime tests.
- Focused final source runs: **27 PASS, zero unresolved current failures**; two mistakenly requested filenames are absent from accepted Wave4 and were not imported from original dirt. [Exact final classifications](focused-final-results.json). Independent companion source receipt retains initial Fill Dates bootstrap failures; final two tests now explicitly load the real core compatibility helper and pass directly. [Final Fill Dates receipt](fill-dates-final-results.json).
- Syntax: **54 checks PASS** across changed PHP under 8.3.33, changed JavaScript and repository shell scripts. Whitespace and staged checks are recorded before commit. [Syntax receipt](syntax-results.json).
- Runtime-source replica parity: **19 changed runtime/asset files match both disposable replicas**, with the separately documented legacy portal fixture exception outside those changed files. [Parity](replica-parity.json).
- Original working source and installed-source hashes, original status/HEAD/index and protected stash match. [Preservation receipt](preservation-after.json). [Input branches remain clean](input-branches-final.json).

All requested current integration gates are green. The historical payables strict-JSON audit remains explicitly unrun because its exact archive is unavailable; it is not represented as a passed gate. Initial matrix/source-fixture failures are retained as setup evidence. Normal Local remains unpromoted. Final teardown and exact commit graph follow in the external local commit receipt accompanying this report.

Final standalone teardown PASS: source and compatibility databases absent; owned PHP/MariaDB processes and Unix socket absent; disposable source tree/datadir and compatibility runtime roots absent; guards and containment locks absent. Staged source/evidence and original historical rollback fixtures remain preserved. [Teardown receipt](final-cleanup.json). Targeted provider core-first first boot emitted one early translation-load notice; three other mode stderr logs were empty. This notice did not bypass any assertion and is retained in evidence.
