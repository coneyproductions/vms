# Phase 3C staffing notification delivery — local acceptance

Status: **ACTIVE / ACCEPTED LOCALLY**. Accepted locally on 2026-09-08 UTC. No real email was sent during implementation or acceptance.

Input authority: Phase 3A `6a8ffcd` → private-storage convergence `090e493a95a72552437af039e2dadd09bd35e185` → Phase 3B `0c4c99dd3854c53af6e648ddaba91779e8826124`. Phase 3C is a separate child on `work/staffing-notification-delivery-20260908`, isolated at `/private/tmp/bvm-staffing-notifications-20260908/packages/vms-github-reconcile`. The final commit is recorded in the adjacent durable `commit-receipt.json` and the durable acceptance report after commit.

Durable evidence root: `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3c`. Referenced paths below are relative to that root unless identified as repository source. Private full-row snapshots, tokens, intercepted synthetic mail, SQL traces and screenshots are outside webroot; none are committed to the plugin.

## A. Existing-path forensics

The accepted lifecycle already committed assignment state, staffing audit and rollup invalidation together, released its staffing lock, restored wpdb, and emitted post-commit events. Delivery stopped there: no lifecycle mail subscriber, queue, recipient decision, delivery result or retry path existed. Staff Tasks notifications/digest and document alerts were separate existing workflows, not disconnected staffing proposal delivery. Core notifications already supplied the append-only log, provider, template filter, preferences and hourly cron. The full call graph and authority inventory are in repository `architecture-and-matrix.md` and durable `existing-path-and-matrix.md`.

## B. Transition/notification matrix

New proposals, confirmed/declined receipts, proposed/confirmed cancellations, canceled/declined reproposals and audited shift-window revisions notify the canonical assigned person. Reassignment is the old assignment's cancellation plus the new person's proposal. A new proposal followed by a window revision in the same transaction creates two committed facts; the obsolete earlier revision is skipped, producing one useful message. The normal UI demonstrated this at audit 1395 (superseded) and 1396 (transport accepted).

Identical operations, failed/rolled-back transitions, cosmetic event metadata, pay, notes and headcount-only changes produce no new lifecycle communication. Date/time changes qualify when they revise the authoritative audited shift window. Generic title/venue changes alone do not trigger mail. No manager copy rule existed and none is invented. Distinct jobs remain distinct logical facts even at a shared mailbox; repeated identity lookup, request or worker cannot duplicate one fact. The complete matrix is in the architecture report.

## C. Delivery architecture

The explicit administrator POST enablement checkpoint stores the current audit maximum. Normal-local was enabled at `bvmgr_staffing_notify_after_audit=1342`; old changes are excluded. Absence of that option means disabled. No read, ordinary bootstrap or migration enables delivery.

A guarded `vms_staffing_committed` action runs after the accepted atomic boundary returns to committed, unlocked staffing authority. Work is recovered from committed transition/window audits into the existing `vms_notify_log` under `source=vms_staffing`, `event_key=staffing_audit_<ID>`, template `staffing.assignment_changed`. Queue insertion failure leaves the original audit discoverable. Both this post-commit action and the existing hourly `vms_notify_digest_tick_cron` process work. Recovery is independent of the optional Staff Tasks digest switch; no new cron schedule or external-cron configuration is introduced.

A separate delivery advisory lock serializes workers. The existing strict no-reconnect connection adapter is reused without starting a transaction, so mail never holds the staffing transaction or staffing lock. Before transport, current assignment/person/plan/revision/status must still match; obsolete work is skipped. A known email/user identity is pinned, while missing contact can be corrected for the same canonical person. Ambiguous, missing, malformed or opted-out recipients are visible failures. Email preference and template/provider infrastructure are reused.

The append-only ledger stores queued work, a durable pre-transport attempt marker, then sent/failed results. Safe failures wait 60 seconds and receive at most three automatic attempts. An authorized per-record manual retry after review can try again under the same identity. Successful or superseded records do not resend. A crash, thrown transport exception or lost terminal write leaves an uncertain outcome that neither automatic nor manual retry resends. Operator text distinguishes transport acceptance from unverified inbox delivery. Exactly-once inbox delivery is not claimed when an external transport outcome is unknowable.

Messages include event, role, status/action, shift, timezone, venue when present and an authenticated current-assignment landing. Contacts without an account receive operator-contact guidance and no response URL. Email GET links do not mutate state; the existing authenticated POST AJAX handler, context/revision-bound nonce, operation ID, actor checks and concurrency rules govern response. The history UI shows the latest 100 qualifying event audits; workers recover/process bounded batches of 100. Older rows remain durably in the existing ledger/audit tables.

## D. Source changes

| Repository path | Purpose |
|---|---|
| `includes/core/staffing-notifications.php` | Cutover, committed-audit recovery, recipient/template resolution, serialized delivery and safe retry |
| `includes/admin/staffing-notifications.php` | Event Plan status metabox, authorized history/enable/retry, authenticated response landing |
| `includes/core/load.php` | Load the two new files |
| `includes/core/staffing-lifecycle.php` | Add only the guarded post-commit notification hook and replace the obsolete delivery comment |
| `tests/staffing-lifecycle/notification-delivery.php` | Real SQL notification, failure, recovery, idempotence and concurrency cases |
| `tests/staffing-lifecycle/notification-worker.php` | Independent transition/recovery/crash worker |
| `tests/staffing-lifecycle/notification-deadlock.php` | Existing real deadlock suite with committed-notification identity assertions |

Documentation and the remediation ledger complete the focused commit. Four runtime paths have exact mirror/isolated-sibling/normal-local parity; 399 other normal plugin files are unchanged. Existing Phase 3A, private storage, Phase 3B, lifecycle validation and transaction implementation remain byte-identical except the explicitly listed lifecycle hook/comment.

## E. Deterministic tests and exact results

Final notification run: **132 real-database assertions passed**, with 18 intercepted transport calls in the main process, plus independently intercepted concurrent/crash workers. Coverage includes disabled/idempotent cutover/no backfill, proposal, confirmation, decline, cancellation, reproposal, canonical recipient/content, GET/nonce/stale/replay rejection, duplicate ticks, safe failures/cooldown/retry, missing contact repair, invalid email, preference opt-out, template failure, queue insertion failure/recovery, exhaustion/manual correction, shift change and rollback, read rendering, concurrent duplicate requests and recovery, process exit during transport and restart.

Existing real deadlock suite: **41 assertions passed** with delivery enabled; **44 unique committed audit notification identities**, no duplicate work or notification for rolled-back audit. Existing disposable integration **104**, concurrency **86**, real staffing/financial SQL **33**, no-mutation migration gate **176** passed. The additive migration rehearsal preserved history and was idempotent.

PHP 8.3.30 and supervised private-socket MySQL 8.0.35 were used. Tests are hard-bound by their existing bootstrap to disposable `bvm_integration_source`; destructive suites never ran against normal-local. Exact stdout/stderr is retained as `focused05-notification-*.stdout/.stderr`, `final04-*.stdout/.stderr`, and `regressions/`. `run-disposable.py` captures setup and exact test arguments. The supervisor is repository `scripts/lib/bvm-disposable-db.py`; it enforces free disk/log/time limits and removes owned processes/database state. `disposable-05.json` reports success and no residue.

One earlier `final04` supervisor teardown reported `[Errno 1] Operation not permitted` after all assertions passed. This was not silently accepted: process IDs/groups were verified absent, MySQL reported Shutdown complete, its socket was absent, and only the retained owned datadir was removed after that proof (`disposable-04-teardown-recovery.json`). The final focused run had clean supervisor teardown. Initial test-fixture duplicate email and two normal harness ownership restrictions were corrected; no runtime acceptance code correction was needed.

## F. Transaction/concurrency proof

At every intercepted main-process mail call, a separate SQL connection could read both the committed staffing audit and durable attempt marker, and the staffing transaction-active flag was false. The normal-local browser repeated that proof for all **10 transport attempts** (`local-acceptance/postcommit-mail-proof.jsonl`). Queue writes inside a staffing transaction are rejected by the deterministic test.

Two independent workers submitting the same transition produce one committed result, one replay/noop and one message. Two recovery workers create/send once. A worker deliberately exits with code 23 during transport; restart retains the uncertain attempt and sends nothing. Existing genuine deadlock/explicit-retry tests pass with unique notification identities. Failed validation, failed transactions and rolled-back reschedules produce no false notification. Mail failure does not roll back staffing.

## G. Normal-local browser acceptance

Real installed WordPress 7.1/BVM/admin/AJAX source, normal Local MySQL and normal companion plugins were used through a loopback PHP server at port 8797 under the established containment guard. Browser scripts operate actual controls and handlers. The harness provides deterministic synthetic actor authentication, blocks external HTTP, intercepts all mail, suppresses cron dispatch/platform bookkeeping, and prevents nonfixture writes. Ordinary login-screen/redirection and real inbox delivery are not claimed; production `auth_redirect()` remains unchanged, while the harness returns sign-in-required 403 for anonymous actors.

The Event Plan Staff matrix created a new proposal. Staff D accepted through the notification landing, then replayed the identical POST without another send. Operator cancellation followed by an old staff response returned 409/stale_assignment. Reproposal and staff decline worked; decline replay was a noop. Another staff member received 403. Missing and invalid email proposals remained committed and displayed their failure. Simulated mail failure remained committed, was visible in history, and immediate retry respected cooldown. Later per-record UI retry succeeded once; replay did not resend. A subsequent failed proposal was canceled; retry and scheduled processing sent nothing, and the history authority marked it superseded. Anonymous link access was blocked.

Evidence: `local-acceptance/browser-results.jsonl`, `browser-cases.py`, `browser-retry.py`, captured response JSON, history text and screenshots. Visual inspection confirmed the real staff response and operator failure/history surfaces. Initial staff rendering hid controls because full Event Plan save gave the synthetic plan Draft status; correcting only its fixture workflow status to Ready exercised the accepted staff visibility policy. Two earlier guarded UI saves rolled back staffing when the test allowlist omitted a synthetic slot/dynamic assignment update. Their trace and diagnostic evidence are retained; neither created a false notification.

## H. Failure/retry proof

Normal-local intercepted **10** staffing mail calls: **8 accepted**, **2 simulated failures**, all to `tech-d@example.invalid`. No real transport ran. Missing/invalid address cases performed no mail call. Audit 1404 failed, remained proposed, then manual retry succeeded under the same key; retry replay sent zero mail. Audit 1406 failed and was superseded by cancellation; after cooldown the existing cron marked it skipped, and duplicate cron sent zero messages. Cron ran with optional digest disabled. An early cron observation before cooldown correctly retained the failure; the final post-cooldown receipt confirms supersession (`local-acceptance/cron-result.json`). Template/queue/crash/exhaustion paths are additionally covered in the disposable suite.

## I. Read containment

Opening and refreshing Event Plan, full staffing, ECC/readiness, notification history, Phase 3B document history, operator assignment landing and staff assignment landing, plus direct canonical recipient previews/resolvers/renderers, preserved **all 246 table contents and structural schemas**. Options, private-file hashes/modes/timestamps and binlog position matched. **Zero SQL mutations, zero mail, zero new staffing/notification/rollup rows** during this read window.

Thirty existing platform bookkeeping attempts were suppressed and logged separately during the window. Existing BVM navigation preference and editor locks were likewise suppressed, not misreported as new Phase 3C writes. This is the established guarded containment standard, not a claim that arbitrary unguarded WordPress/companion requests perform no platform bookkeeping. Evidence: `local-acceptance/read-containment-result.json`, before/after full snapshots and SQL traces.

## J. Regression results

All **15 selected standalone suites passed**: Phase 3B **64**, private-storage boundary **16**, Phase 3A cancellation **58**, staffing/financial **127**, financial authority **234**, ECC context **35**, ECC semantics **103**, upload validation, private upload API, private operations boundary, import operations boundary, authorization boundary, nonce normalization, Event Plan workflow confirmations and reporting-provider contract. Exact results are in `regressions/results.json`. Real lifecycle/atomic/concurrency/deadlock/migration/financial regression is listed in E–F. PHP syntax and `git diff --check` pass; own source/test diff review completed. No broad unrelated backlog suite is represented as newly run.

## K. Fixture cleanup and state integrity

The synthetic event/vendor, four staff records, five users, role/link metadata, slot, four assignments, staffing audits, rollup and notification ledger rows were archived and deleted with ownership checks. **156 rows across 14 tables were removed**. Before cleanup every original full-row hash remained present across all 246 tables. After cleanup **245 tables exactly match baseline**, and `wp_options` differs only by the accepted enablement row (`bvmgr_staffing_notify_after_audit=1342`, ID 64183, autoload off). Every original option and other original row is unchanged; structural schemas and private files are identical. Auto-increment gaps and the expected binlog history are retained, never rewound.

All material added rows are classified in `local-acceptance/mutation-classification.json`: fixture setup is intended test write; canonical transition/audit/rollup/notification and explicit enablement are expected BVM writes. Platform attempts were suppressed; two test guard omissions caused safe rollback. No unresolved material mutation or implementation defect remains. The expected companion Square-warning log suffix (78 entries) was archived outside webroot and removed while preserving the 170-entry original prefix; its filesystem modification time necessarily changed. No private document was altered.

The temporary MU containment files were removed, the owned PHP process stopped and port 8797 verified closed. The normal installed source remains promoted and enabled for future committed staffing changes. Evidence: `cleanup-database-result.json`, `cleanup-integrity.json`, `runtime-log-cleanup.json`, `guard-cleanup.json` under `local-acceptance/`.

## L. Rollback

Before promotion, two original files were copied and four original/candidate SHA-256 pairs recorded in `local-acceptance/rollback/manifest.json`. The hash-guarded restore script restores only those originals and removes the two added files; it refuses later source drift. Its check-only mode passed. Full instructions: `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3c/local-acceptance/rollback/RESTORE.md`.

No broad database restore is needed. The cutover option can remain inert under Phase 3B, preserving its historical boundary; optional exact-row removal is documented separately. Later business staffing or notification data must be preserved. Rollback was prepared/validated, not executed because acceptance passed.

## M. Frozen and preserved authority

Frozen release commit `04caf99ae7a98ac507c067a7535d2a672efa457b` remains clean and unchanged. Both existing qualified ZIP copies verify **SHA-256 `2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3`**, **396 files**. They were read, not rebuilt.

Protected stash `d08e726804712dc233f0e37b217abd6389963863` is identical. Historical `work/unreleased-2026-06-18` remains at `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`; its entire file manifest, index hash, status (37 modified tracked paths / 36 untracked entries), branch and protected stash match preflight. Legacy VMS and all 13 companion trees match their complete baseline hashes. Only the four declared installed BVM runtime files differ. Evidence: `final-preservation-verification.json`, `preservation-before.json`, `preservation-after.json`.

## N. Final commit

A focused Phase 3C commit is authorized only after these local acceptance checks. This report and the source/tests form that separate child of accepted Phase 3B; the resulting exact SHA is recorded in durable `commit-receipt.json` and appended to the durable copy of this report. No push, package, tag, deployment, submission or reviewer reply occurs.

## O. Final status

**ACTIVE / ACCEPTED LOCALLY**.

Staffing remains authoritative before delivery; failures are visible and safe to retry when outcome is known; uncertain outcomes cannot duplicate through automatic/manual retry. Accepted Phase 3A, security, Phase 3B, staffing transactions/reads, ECC and reporting behavior remains intact within the explicitly recorded regression coverage. Staff Tasks stabilization, Google Calendar, production/staging and unrelated development remain outside this completed change.
