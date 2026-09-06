# Staffing lifecycle implementation — isolated validation

Date: 2026-09-06. Scope: source on `work/staffing-lifecycle` and a separate disposable WordPress/MariaDB installation. This is not a normal-local promotion or deployment receipt.

## A. Prior commit preserved

Implementation continues directly from `96309bdf5795a3944f002dee4c21811394362443`. That commit retains the original specification, eleven gap characterizations, and A–P forensic report. It is not squashed or rewritten. Worktree: `/private/tmp/bvm-staffing-lifecycle/packages/vms-github-reconcile`.

## B. Transaction and locking model

The consistency invariant is: assignment state/revision/window, its committed lifecycle audit, and affected rollup invalidation commit together, or none of those writes commits. Lookup, authorization, stale-revision checks, eligibility, and overlap validation happen inside the same boundary.

`bvmgr_staffing_atomic()` obtains one MySQL/MariaDB connection-scoped advisory lock per database and WordPress table prefix. It sets the next transaction to READ COMMITTED, verifies transactional tables and the exact lifecycle schema, starts the transaction, runs the operation, verifies lock ownership, commits, and releases the lock. Matrix creation, template application/seeding, timestamp synchronization, rollup recomputation, event-time metadata edits, and canonical rescheduling cooperate with this boundary. The broader prefix lock is intentional: it covers empty assignment sets, cross-event staff conflicts, multi-role matrix saves, and moves between windows without discovering an incomplete set of locks. It serializes staffing writes within one site; unrelated sites have separate locks. Lock acquisition waits at most five seconds.

A connection-sharing `wpdb` adapter disables WordPress reconnection/query replay during the boundary and turns failed queries and failed insert/update/delete operations into rollback failures. Nested operations join the owned boundary. Caller-owned transactions are rejected without implicitly committing them; accidental transaction-control SQL and DDL inside callbacks are rejected. Additional DML tables enlisted by rescheduling must also be InnoDB. Stock `wpdb` is supported; alternate database adapters fail closed pending separate validation.

READ COMMITTED was proved with an independent connection committing between two reads in one managed transaction. Separate worker connection IDs, an actual server lock barrier, and overlapping execution prove contention. This is not a process-local mutex or sequential model.

A connection lost during COMMIT has an inherently uncertain acknowledgment. The service returns `commit_outcome_unknown`, does not claim definite rollback, and emits no speculative event. A new request with a fresh host connection and the same operation ID safely checks the recorded result. A long-lived caller must restore its connection outside the service before explicit retry. The state/audit atomicity invariant still holds whether the server committed or rolled back. Definite failures return typed errors; the `rolled_back` flag is asserted only after acknowledged rollback.

## C. Alternatives evaluated

- Locking only the assignment row cannot protect a second overlapping assignment or a pair that has no row yet.
- Locking a discovered set of assignment/slot rows leaves empty-set and moving-window races unless a common staff/sentinel locking protocol is added. Deterministic per-staff locks could reduce contention later but add migration, discovery, ordering, and multi-staff operation complexity here.
- Event-only locks miss one person's shifts in different events; assignment-only advisory locks have the same problem as row locks. A site-level staffing lock is the smallest reliable common boundary across the existing batch writers without introducing sentinel rows.
- A pair uniqueness constraint cannot prevent interval conflicts. Unconditional `(slot_id, staff_id)` uniqueness would also require reconciling historical evidence before installation.
- PHP mutexes, transients, and ordinary WordPress options do not establish shared server transaction ownership or atomic audit persistence.

## D. Schema and migration

The additive, explicit `bvmgr_staffing_migrate_lifecycle()` adds assignment `revision BIGINT UNSIGNED NOT NULL DEFAULT 0`; nullable audit `assignment_id BIGINT UNSIGNED` and `operation_id VARCHAR(64)`; a unique audit operation index; and an assignment audit index. It validates actual index columns/uniqueness and column type/nullability, not merely their names. Existing status storage already accepts Declined.

No assignment rows are deleted, merged, backfilled, or silently canceled. No unique pair constraint is introduced. The shared server lock prevents concurrent creation; the repository normalizes requested staff IDs, preserves existing pairs, and rejects multiple active legacy rows for deliberate review. Additive migration with duplicate historical fixtures is idempotent and preserves every row. Incompatible existing indexes and nontransactional tables fail closed.

`bvmgr_staffing_lifecycle_preflight()` is a read-only receipt of engines, schema readiness, duplicate pairs/active counts, and orphan assignment IDs. Migration has no boot, page-read, or activation hook. DDL is not claimed to be rollbackable; partial additive installation must be inspected and completed forward.

## E. Canonical API

`bvmgr_staffing_transition_assignment($assignment_id, $target, $options)` is the single existing-assignment state writer. Options carry operator/staff context, canonical Event Plan ID, expected revision, unique operation ID, and reason. Actor identity always comes from the authenticated current user. The API resolves assignment → slot → plan itself, checks current ownership/capability, preserves identity/pay/actual-time fields, validates the transition and window, updates the revision, records the audit, and invalidates affected plans atomically. Known successful operation IDs return an authorized no-op and no extra audit or event. Old revisions cannot respond to a later reproposal cycle.

## F. State machine

| Current state | Allowed next state | Actor |
| --- | --- | --- |
| Proposed | Confirmed | Operator or owning staff |
| Proposed | Declined | Owning staff |
| Proposed | Canceled | Operator |
| Confirmed | Canceled | Operator; reason required |
| Declined or Canceled | Proposed | Operator; deliberate reason required |

Historical cancellation requires a reason. Unknown legacy states and Completed are not reinterpreted. Staff cannot cancel commitments or repropose. Confirm/repropose require an active slot, eligible staff, valid future-ending window, and a nonpast event date. Staff actions also require an existing portal-visible plan status. The existing local-date boundary is retained for overnight portal visibility. Same-state requests with a new operation ID fail as invalid transitions; retries use their original operation ID.

## G. Concurrency behavior

Independent worker processes prove that only one of two overlapping Proposed assignments can become Confirmed, including different slots in the same event. Concurrent creation leaves one pair. Whole matrix saves racing staff Accept, staff Decline, and operator Cancel preserve each decision. Competing Accept/Decline on one revision commits one outcome and returns a stale-revision error to the loser.

An actual InnoDB deadlock is induced with an independent external writer. The server deadlock counter increases; the lifecycle victim rolls back its state/audit; retrying the same logical operation succeeds exactly once. Automatic replay is deliberately avoided.

## H. Audit atomicity

The existing audit table receives `assignment_transition` records with immutable assignment/slot/plan/staff identity, previous/new status, revision, authenticated actor, context, reason, unique operation ID, and UTC creation time. Proposal creation receives its own committed transition record. Matrix batch audits remain distinguishable. Window changes receive `assignment_window_change`, not fabricated status transitions. Failed attempts are not mixed into committed lifecycle history.

Real SQL errors during assignment, audit, and rollup writes prove rollback. Connection loss during the audit write proves no reconnect/replay and no partial row/audit. An independent connection sees the committed row and audit before the transition hook runs. Successful retries generate neither duplicate history nor duplicate hooks.

## I. Overlap and shared projections

Proposed overlaps are warnings. Confirmed overlaps are hard failures. Declined/Canceled assignments and inactive slots do not create conflicts. Half-open intervals allow adjacent shifts. Both sides of conflict checks resolve the canonical slot/event window; same-event roles are included. Rollup conflict calculation uses this shared policy, and affected staff-related plans become dirty in the same transaction.

Event Plan and full/light ECC retain the accepted shared staffing snapshot; the staff portal reads the same committed normalized rows. Assigned remains Proposed + Confirmed. Inactive normalized history continues to suppress legacy fallback. Planned labor estimates retain their existing planned-headcount meaning.

A necessary parser correction initializes seconds to zero instead of inheriting the request's current seconds. Explicit durations support overnight work. DST fall-back duration is checked against UTC; nonexistent local times are rejected. Reversed explicit clock windows retain the existing invalid-window behavior; callers should use a duration for overnight shifts. Global site timezone changes and third-party direct SQL writers require separate maintenance/revalidation; they are not newly authorized or made into supported staffing write paths.

## J. Operator handlers and UI

Authenticated AJAX action `bvmgr_staffing_operator_transition` supports Confirm, Cancel, and Repropose. Assignment/action/context/revision-specific nonces, scalar identifiers, actual plan capability, and the canonical service protect it. No handler writes a status directly.

Initial and deferred Event Plan staffing rendering include lifecycle labels and controls. The controller is loaded on the editor before deferred content arrives. Proposed/tentative and Confirmed/committed are explicit, and inactive rows remain visible. Planned, Assigned, Proposed, Confirmed, open planned positions, and open required-now positions remain distinct. Matrix copy uses Assigned instead of ambiguous Filled. Inline reason fields support cancellation/reproposal without native browser dialogs or nested forms. After a save, the row shows the returned state and asks for reload to refresh all editor summaries without discarding unsaved fields automatically.

## K. Staff handlers and UI

Authenticated AJAX action `bvmgr_staffing_staff_transition` accepts/declines only the user's own Proposed assignment. Unlinked users, cross-user/cross-plan attempts, invalid nonces, wrong request methods, stale revisions, and invalid time context fail without mutation. Responses expose only public identity/revision/status/label and messages; private pay overrides, notes, and other people's assignment IDs are omitted.

The portal shows Accept/Decline for eligible proposals, a committed label for Confirmed, and inactive history for Declined/Canceled. Inactive rows do not become an upcoming shift. Browser testing used synthetic records and the real authenticated AJAX handlers: Accept, required-reason rejection, Cancel, Repropose, Decline, and persistence after reload were verified visually.

## L. Hooks and delivery

`vms_staffing_assignment_transitioned` fires only after commit. The existing `vms_staffing_event_saved` hook is also deferred until commit. Subscriber failures are reported as post-commit warnings and cannot retroactively undo a committed assignment. No new email/SMS delivery, recipient lookup, queue, or outbox is implemented. No real notifications were sent. Disposable WordPress blocked outbound HTTP and mail before transport and disabled WP-Cron.

## M. Validation

See `bvm-staffing-lifecycle-validation.json` for exact final results. Validation uses WordPress 7.1, PHP 8.3.33, and isolated MariaDB 10.11.19/InnoDB through a private Unix socket with TCP networking disabled. Initial core-only checks also passed with PHP 8.5; final compatibility runs use PHP 8.3. Bundled Local MySQL binaries failed to initialize the isolated data directory, so this receipt does not claim MySQL 8 runtime coverage.

The suites include the eleven intentionally updated forensics; genuine concurrent confirmation/creation/matrix/response races; transition, nonce, ownership, audit/rollup failure and idempotence checks; READ COMMITTED visibility; nested transaction rejection; engine/index preconditions; connection loss; real deadlock/retry; actual deferred editor rendering; P0 consistency; staffing repository/matrix tests; portal tests; eligibility; published-date/reschedule safety; Event-Day rendering; reschedule communication-ledger tests; and both official-five add-on load orders. Source contract checks, PHP/JS syntax, and diff whitespace checks also pass. Repository SQL suites retain their exact inventories and negative controls; retired direct-write assertions now require delegation, backed by real database tests.

The ordinary add-on harness's normal-site guard installation was not run because this task prohibits any normal-local write. The existing official-five runtime probe was invoked directly with both accepted all-add-on load orders in the separate disposable installation. Additional add-on semantic contracts were checked; this receipt does not claim a rerun of unrelated normal-local promotion suites.

## N. Historical test classifications

**STALE TEST:** the portal safe-HTML pattern excluded the accepted `bvmgr_` function prefix. The portal inline-asset test expected an old script handle and old nonce spelling despite the accepted prefix/nonce compatibility layer. The Event Plan staff asset test likewise expected the retired `vmsEventPlanInitStaff` global and handle. Only test expectations were corrected for these naming failures; product names were not changed to appease them.

**RETIRED CONTRACT:** implicit revival/cancellation in matrix saves, deletion of historical slots during template replacement, and direct timestamp/overlap SQL assertions. Their replacements assert lifecycle delegation and test the new behavior in real SQL. The eligibility fixture now inserts pre-existing invalid history explicitly because new repository proposals correctly enforce eligibility. Existing history remains visible and preserved.

Missing mirror-fixture assets and incomplete dependency tables/memory settings were disposable harness setup issues, corrected before final validation. No unresolved failure is represented as a pass.

## O. Files changed

Runtime: `includes/core/staffing-lifecycle.php`, `staffing-lifecycle-ui.php`, `staffing.php`, `load.php`, `event-reschedule.php`; `includes/db/staffing-lifecycle.php`; Event Plan class/initial partial; staff portal; new lifecycle CSS/JS; and the existing Event Plan staff controller's wording.

Tests: `tests/staffing-lifecycle/` (hard-bound bootstrap, transaction/integration/concurrent/deadlock suites and workers); eleven-forensic entry point; P0 source consistency; staffing repository/final/matrix suites; portal safe-HTML/inline-JS expectations; Event Plan staff asset/eligibility expectations. Documentation: this A–S report, the validation receipt, and disposable-test instructions. The commit's file list is the authoritative inventory.

## P. New local commit

The new implementation commit is the commit containing this report; its exact SHA is supplied in the task's final response. Its parent is the preserved forensic/spec commit. No push, tag, package, ZIP, or deployment is authorized by this receipt.

## Q. Future normal-local migration/promotion requirements

A separate authorization is required. Before promotion: record the exact source/target commit and paths; stop all staffing/time writers; back up the target database and files; confirm all listed/enlisted tables are InnoDB and stock `wpdb` is in use; save the read-only lifecycle preflight, full `SHOW CREATE TABLE` output, duplicate/active/orphan inventory, row counts, and audit hashes. Do not silently resolve active duplicates or fabricate missing identities.

Run the explicit additive migration under maintenance, save its result and resulting schema/index definitions, rerun it to prove no further change, and compare assignment/history rows with the preflight receipt. If an existing column/index has the wrong shape, stop for deliberate reconciliation; do not drop evidence to make the migration pass. Resume only after matching-source runtime tests and operator/staff smoke checks, including real concurrency, audit rollback, role/time changes, ECC/portal consistency, and notification containment. Synchronize the correct canonical runtime tree only under that future authorization, preserving legacy tree differences.

Forward recovery is preferred for partial additive DDL. Retain new columns/indexes and audit evidence during application rollback; never blindly drop them. Reverting to the old matrix writer would restore known lifecycle gaps, so keep staffing writes disabled until a compatible forward repair is verified. A promotion receipt must include before/after schema, duplicate review decisions, engines/versions, source/runtime hashes, test results, backup location, notification evidence, and the exact authorized target.

## R. Financial Authority merge considerations

Compared with `work/financial-authority-v2`, the direct shared edits are `includes/core/load.php` and `tests/p0-source-consistency-repair.php`. Expect a loader/test integration review. Its ECC/profitability/financial snapshot work also consumes staffing summaries, so confirm that planned labor remains planned labor and that Assigned/Proposed/Confirmed fields are not conflated with financial recognition. No financial calculations were changed here. The older `work/financial-authority` comparison had no additional delta from the common base. Neither financial worktree was modified.

## S. Environment preservation

The original dirty worktree remained byte-identical across 1,783 files; canonical normal runtime across 384 files; legacy normal runtime across 408 files. Original status, index, and HEAD are unchanged. Protected stash `WPORG-16D preserve unrelated sidebar+doc work` remains at `d08e726804712dc233f0e37b217abd6389963863`.

All SQL connections were explicitly bound to `staffing_test` at `localhost:/private/tmp/bvm-staffing-db/mysql.sock`. WordPress core and dependency code were copied read-only into `/private/tmp`; the normal configuration/database was not used for execution. No normal plugin activation/deactivation, normal guard installation, staging, production, SSH, outbound delivery, push, or deployment occurred. Temporary browser/server cleanup is recorded in the final receipt.
