# Phase 3D — Staff Tasks reliability and operational authority

Date: 2026-09-08. Accepted parent: `411d86c3df7ff1fe0374102f4e065ed40940ca73`.

Evidence root: `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3d/`. Private SQL snapshots, synthetic request nonces, mail bodies and browser artifacts remain outside the repository/webroot. Public plugin name/version remain Backstage Venue Manager 1.2.0; the Staff Tasks database schema is 1.3.0.

## A. Canonical authority map

The existing Staff Tasks module is the only operational task authority. There is no new task model, parallel task ledger, REST service or Google integration.

| Existing storage | Authority |
| --- | --- |
| `vms_task_instances` | Immutable numeric task ID; Event Plan ID (`event_id`, not TEC ID); optional venue/type; title/instructions/priority/required snapshot; status; WP user assignee, assignment mode/lock; due compatibility field; completion actor/local timestamp; skip/cancel reasons; template/checklist and recurrence lineage. New revision, generation identity and versioned timing provenance. |
| `vms_task_logs` | Existing append-only application history, now committed atomically with task commands; actor, UTC audit time, before/after snapshots, operation fingerprint/identity. It is not a tamper-proof security ledger. Definition/membership audits use task ID 0 and explicit entity IDs. |
| `vms_task_templates` | Future task defaults: event/general context, title/instructions, priority, required flag, due and assignment rules; new timing rule JSON. |
| `vms_checklist_templates`, `vms_checklist_items` | Ordered applicability by default/venue/event type, membership and qualified overrides. |
| `vms_tasks_settings_v1`, schema option, shared cron | Settings and orchestration, not task state. Generation signature/queue/lock Event Plan metadata is likewise orchestration. |
| Existing `vms_notify_log` | Task delivery records under `source=vms_staff_tasks`; existing task message templates and core email provider. Phase 3C staffing mail remains separate. |

Person identity is the WordPress user ID. Where linked to staff, published `vms_staff`, reciprocal/unambiguous links and the accepted identity resolver must agree. Missing/deleted/inactive/ambiguous references fail validation. No free-text person authority was added. Event occurrence comes from the accepted Event Plan occurrence service and site timezone. TEC and old derived datetime fields cannot overwrite it.

Admin Tasks, My Tasks, templates, checklists, settings and the Event Plan Tasks panel remain. Existing admin-post/AJAX routes delegate to the command authority; timing/history adds one protected detail screen and one POST command. Template/checklist saves retain their admin-page POST/nonce/capability boundary. No task REST endpoint was found. The disconnected dashboard renderer and absent ECC task consumer are documented, not represented as working integrations. Staff Portal ICS/availability, Due Dates, Safety tasks and staffing assignments are distinct systems.

## B. Phase 2 reliability gaps and deferred work

The Phase 2 denominator was 24 equally weighted capabilities: 11 full, 10 partial, 3 absent (16/24, approximately 67%). This is not an effort estimate or a readiness score. No artificial 100% claim is made.

Implemented here: atomic task/audit writes; revision conflicts and request replay; stable generation across completion/reopen/reschedule; confirmed-only scheduled-role assignment and stale-owner clearing; explicit timing/timezone provenance; recurrence uniqueness, rollback and month-day anchoring; non-ticketed generation and truthful queued failure state; delivery callback repair, commit/retry/deduplication; explicit setup replacing read-time repair; timing/history visibility; Woo-compatible scoped staff access; default All open view including undated and scheduled-without-due work. These address partial rubric items 5, 6, 8, 10, 12, 20, 21 and 22 and strengthen existing lifecycle/permission/UI capabilities.

Deliberately deferred: bulk Preview → Commit (15), propagation Preview → Commit (16), full Event Plan AJAX/required-toggle parity (17), dashboard/ECC task display redesign (19), full checklist override/reordering editor (24), rich task content editing/series management UI, and broader guided-tour/navigation polish (23). Existing instance content remains a durable snapshot; timing/assignment/lifecycle are the supported operational edits. Checklist ordinary UI still supplies empty overrides. These limitations do not introduce another authority.

Obsolete expectations: automatic supersession/new IDs whenever an event date changes; cancel spawning another recurring occurrence; treating proposed staffing as committed task responsibility; ticket inventory as a prerequisite for task generation; one due field representing a scheduled block; page reads repairing canonical state. General scope supersedes the old `calendar` context label.

Future integration work: Google authorization, calendar selection, remote IDs, incremental delivery/reconciliation, external conflict policy and deletion mapping. None is implemented or represented as already synchronized.

## C. Lifecycle specification

Stored statuses remain `open`, `done`, `skipped`, `canceled`, `superseded`. Scheduled and overdue are derived; review/unavailable is a projection flag.

| Command | Permission / source state | Result and side effect |
| --- | --- | --- |
| Create | Manager | New open task, revision 1, creation audit; eligible assignment delivery after commit. |
| Complete | Manager or capable current assignee; open | done, completing user and local completion time, UTC before/after audit; general recurrence successor in the same transaction. |
| Skip | Same as complete; reason required | skipped, reason/audit; same recurrence rule. |
| Cancel | Manager; open; reason required | canceled, reason/audit; no successor spawned. |
| Reopen/restore | Manager; done/skipped/canceled | Same ID becomes open; current completion fields cleared, prior completion audit retained. |
| Assign / timing | Manager; open | Same ID; validated authority; revision and before/after audit only on material change. Terminal tasks must be reopened before editing. |
| Superseded history | All actors | Read-only historical record; no new superseding behavior introduced. |

The identical request UUID/fingerprint replays its recorded result without another audit or logical notification. Reusing an operation ID for different input fails. Competing stale revisions fail explicitly. A no-op leaves revision/audit unchanged. Reopening and recompleting a recurring parent cannot create another successor. Cancellation stops further spawning from that occurrence; it does not silently cancel an already existing descendant or provide an entire-series cancellation command.

## D. Timing specification

`timing_json` contract version 1 records timezone, due kind/source/value/offset/clock, schedule source/start/end/offset/duration, canonical UTC values, occurrence anchor and cancellation/review policy. The existing `due_at_local` is a site-local compatibility projection, not the source for new timing interpretation.

- Due-only: none/date/datetime with no scheduled block.
- Date-only: explicit local calendar date, due by 23:59:59 in its recorded timezone. It is not an all-day reserved calendar event.
- Scheduled: explicit start and optional end; absence of end is retained rather than inventing a duration.
- Event-relative: elapsed-minute offsets from canonical Event Plan start; event-date/local-clock due rules also remain supported. Event timezone follows accepted site authority.
- Manually pinned: recorded local clock plus timezone resolves to a fixed instant and does not follow event movement.

UTC is persisted alongside readable local provenance. America/Chicago is tested but not hard-coded; Asia/Tokyo is independently tested. Invalid clocks, DST gaps and repeated local clocks fail closed; the operator must choose an unambiguous time. Recurrence advances in the task's timezone, preserves local clock hours across DST, clamps short months and returns to the root day in later months. Impossible successor timing rolls back completion rather than silently losing the next task.

Legacy rows are not guessed/backfilled. The two normal historical instances retain null timing/generation provenance and revision 0. Their original values/history are unchanged. A manager must explicitly resolve legacy timing before a recurring completion requiring a successor. Read projections expose review state.

## E. Templates, generation and identity

An event/template pair is one logical generated task: unique generation key `event:<plan>:template:<template>`. Checklist order, date and status are not identity. The existing-instance lookup considers every status, including legacy rows. Historical duplicates suppress new generation and flag review; they are not merged or deleted automatically. A distinct copied/repeated Event Plan has its own identity.

Instances copy definitions. Ordinary template edits affect future instances only. Deleted/inactive templates do not rewrite or delete operational history. Checklist overrides are honored at generation. Definition saves and generation share the same transaction lock; checklist header and membership save together. Template forms use expected hashes to detect stale saves. “Make repeatable” creates its definition/membership/task atomically and links the first task so regeneration does not duplicate it.

General recurrence successor identity is `recurrence:<parent-task-ID>` with root lineage. Generation and recurrence uniqueness are enforced in SQL. Manual creates use the submitted operation UUID. Event save, queued/nightly/manual processing converge on the same generation function. Canceled/archived/ineligible events cannot create tasks. Effective tickets are not required. Queued errors remain failed rather than being falsely marked complete. Existing queue/job metadata and bounded locks remain orchestration; the database task key is the durable duplicate barrier.

## F. Event movement, cancellation and staffing

Open event-relative tasks reconcile in place; pinned tasks and terminal history do not move. A stale or missing Event Plan occurrence flags review instead of trusting stale TEC. A pending relative or staffing reconciliation is visible on pure reads without performing a repair. There is no independently authoritative per-event timezone in the accepted occurrence architecture; Staff Tasks does not invent one.

Cancellation policy defaults to review. Explicit retain keeps cleanup/follow-up work; explicit cancel cancels open work with an audit. Cancellation hooks, canonical event saves and post-commit staffing events reconcile tasks. Both matrix saves and individual confirmed/canceled staffing transitions are covered. Exactly one eligible confirmed staff member resolves an unlocked scheduled-role task; none/multiple/invalid clears its old owner. Explicit manager locks remain pinned. Person mode may be unassigned; role-only responsibility does not imply assignment to every role-holder.

Normal browser movement proof is a Ready Event Plan date/time edit through the actual editor (2026-10-20 20:00 → 2026-10-21 21:00). Its relative task moved 18:00 → 19:00 on the new date; the manually pinned 2026-10-20 15:00–16:30 block remained fixed. This does not claim a new paid-ticket reschedule or replacement-plan workflow; accepted Phase 3A behavior is unchanged.

## G. Audit, concurrency and delivery

Five existing task tables require InnoDB. Commands adopt the accepted strict no-reconnect database adapter, acquire a connection-scoped task advisory lock, use READ COMMITTED and verify lock ownership before commit. Revision/CAS, command identity and audit are inside that boundary. DDL/nested transaction controls and writes outside the five task tables are rejected. External staffing transactions are not joined. A commit whose outcome cannot be determined is reported as unknown, with same-request replay available; no blind fresh operation is issued.

Transport runs only after task commit. An independent SQL connection verifies the task revision and durable attempt marker before intercepted mail. Existing notification storage records queued work, attempt and terminal outcome. Explicit failure permits bounded retry (three attempts, at least 60 seconds); an uncertain attempt is not automatically resent. Successful logical delivery is deduplicated. Current revision, ownership, email validity/preferences and review state are rechecked. History distinguishes transport acceptance from inbox delivery.

Existing reminders/digest use repaired scheduled callback aliases, stable task/revision or user/day identities and paginated task scans. Assignment queue recovery uses committed task audits after the explicit cutoff. Legacy revision-0 reminder backlog is excluded. Delivery failure/unknown states are visible; no broad manual delivery administration or new reminder platform was added. Historical staffing notification cutoff remains 1342. Task cutoff is 13. Mail settings and provider configuration are unchanged.

## H. Future external-sync contract

`bvmgr_tasks_sync_record(task_id)` is a pure projection containing contract version, task ID, revision, status, canonical user ID, title/instructions/priority, typed timing with UTC/timezone/source, Event Plan context, template/checklist/generation identity, recurrence root, authoritative modification UTC, review, and derived scheduled/overdue state.

Future adapters must treat nonempty review or unavailable references as a reconciliation requirement and evaluate the full projection, including dependent event/assignee validity; task revision alone does not version changes in other authorities. Time passing can change overdue without a task write. Due date-only must not become a reserved block. Same-ID reopen/reschedule is an update; a successor/new Event Plan is a new task. No Google event IDs, credentials, OAuth or outbound synchronization were introduced.

## I. Source changes

Eleven runtime paths: `assets/js/vms-tasks-event-plan-metabox.js`; existing Staff Tasks `admin-ui.php`, `db.php`, `generator.php`, `notifications.php`, `staff-tasks.php`, `store.php`; new module-local `authority.php`, `event-authority.php`, `delivery.php`, `authority-ui.php`. Old mutation implementations delegate to one command path; no unrelated core/companion refactoring. Existing detached Event Plan form ownership is retained. New tests live in `tests/staff-tasks/`.

Normal, isolated mirror and isolated sibling runtime hashes match. The historical dirty worktree, inactive sibling VMS and accepted Phase 3C worktree were not edited. Additive schema changes are exactly five columns across three existing tables and two unique indexes; there are still 246 tables. Setup is explicit administrator POST, not ordinary bootstrap.

## J. Deterministic validation

Final supervised real-MySQL suite: **200 assertions**, including 82 foundation assertions. Coverage includes manual/replayed create; template generation across completed state; time movement/pinning; cancellation review/cancel/retain; canonical/missing/inactive/ambiguous staff; one/multiple confirmed role owners and locks; completion/reopen/cancel; same/competing operations; generation races; audit failure rollback; process interruption/restart; queue persistence failure; competing delivery workers; uncertain transport restart; retry/cooldown; reminders/digest replay; DST/timezones/month root anchoring; copied Event Plan identity; deleted template snapshot preservation; non-ticketed queue and truthful failure state; My Tasks without due dates; and full disposable-table read containment.

Evidence: `final18-task-concurrency.stdout`, `disposable-18.json`, `run-disposable.py`, and tracked test sources. Disposable WordPress/Woo/TEC runs are hard-bound to the supervised private socket/database, never normal Local. The 176-assertion no-mutation preflight gate passes. Expected injected queue failure emits a recorded diagnostic. Earlier fixture/selector failures and supervisor runs remain in evidence. Runs 03 and 17 encountered an OS teardown EPERM after tests; process/group absence, socket absence and MySQL shutdown were proved before owned residue removal. Recovery receipts/logs are retained. Final run 18 reports success, no remaining process and no database residue.

## K. Browser acceptance

Actual normal installed WordPress/BVM/Woo/companion source and normal Local MySQL were exercised on an authenticated loopback server. The harness provides synthetic actors, blocks other traffic/cron/external HTTP, intercepts all mail and restricts mutations to fixtures. It does not claim real login, inbox delivery or unguarded platform behavior.

Passed: manual create; actual template/checklist forms; Event Plan generation; reassignment; date-only due; scheduled pinning; completion/replay/reopen/cancel; self-completion and manager restore; canonical editor date/time change; relative/pinned movement; three regeneration reruns with zero creation; timing/history inspection; 14 actual endpoint denials; and repeated read surfaces. A follow-up verifies the All open view shows undated and scheduled-without-due assignments; nine more reads preserve all 246 tables, options/private files and binlog with zero write/mail attempts. Relevant screenshots were visually inspected.

Four initial synthetic mail calls were intercepted with independent commit proof; the follow-up intercepted two more calls, for six total and zero real mail. Initial guided-tour overlay/ambiguous selector and out-of-horizon fixture assumptions were corrected without unauthorized writes. Woo subscriber redirection was an actual integration defect repaired only for the two capability-checked task screens. Unauthorized history can return HTTP 200 after the admin shell has started, but denies content; this status-code limitation is recorded explicitly.

## L. Read containment

Initial acceptance: **27 repeated browser views**, all **246 tables** identical in rows/schema; options, private files and binlog identical; **zero mutation attempts and zero mail attempts**. Surfaces include Tasks/filtering, detail/history, Event Plan, ECC, templates/checklists and staff My Tasks/history. ECC currently has no task consumer; this verifies the existing ECC surface, not a newly invented task dashboard. Existing filters were exercised; no new free-text search UI is claimed.

The 14 denied endpoint cases likewise preserve all 246 tables. Separate final All open read verification is retained under `local-acceptance/my-tasks-followup/`. Known platform/companion bookkeeping, edit locks and navigation preferences are separately suppressed/logged. This is the established guarded read-containment standard, not a claim that arbitrary unguarded WordPress requests have no platform side effects.

## M. Regression results

All 15 Phase 3C selected regression suites pass: Phase 3B technician-document notifications **64**, private-storage configurations **16**, upload/private operations/import, authorization/nonce, Phase 3A cancellation **58**, staffing/financial **127**, financial authority **234**, ECC context **35**, ECC UI fixtures **103**, Event Plan confirmation behavior and reporting-provider contract. Additional task-signature and ticket scan-lock/CSS tests pass.

Real SQL accepted staffing suites pass: lifecycle **104**, concurrency **86**, deadlock **41**, financial **33**, idempotent migration, Phase 3C delivery **132**, notification deadlock/recovery **41** with **44 unique committed identities**. Receipts/output: `regression13-*`, `disposable-13.json`. Staff Tasks owns no ticket, private-document, reporting or companion source changes.

Three older structural tests fail on both accepted 411d86c and this candidate: task override test expects obsolete nonce text; task repository suppression inventory is pinned to obsolete source line numbers; ticket query-filter test expects a prior helper hash. Logs from both authorities are retained in `regressions/`. They are baseline exceptions, not passing tests. PHP lint, JavaScript syntax and diff checks are required at commit. Packaging/Plugin Check was not run because no release/package task is authorized.

## N. Cleanup and state integrity

Initial window: **102 synthetic rows removed** after ownership and original full-row verification. Original two tasks and seven history rows remain unchanged. All 242 non-schema/non-option tables exactly match baseline; the remaining four contain only the accepted additive task schemas, schema version 1.3.0 and delivery floor 13. Existing cron and capability options are identical. Private files are unchanged. Auto-increment counters are not rewound.

Mutation classifications are recorded per table/trace: intended fixture setup/cleanup, expected WP editor writes, expected BVM task/audit/delivery/schema and synthetic Event Plan metadata, suppressed platform activity and archived baseline logging noise. No unresolved material database mutation remains. The first window suppressed 308 platform attempts and archived 151 expected Square-warning suffix entries while preserving the original 272-entry log prefix; filesystem modification time necessarily changed. Follow-up cleanup is independently recorded. Temporary guards/servers are removed after each window.

Between guards (12:58:20–13:01:58 UTC), normal background activity changed five table endpoints. ROW binlog positions 726341799–726985523 in binlog.000056 attribute all 75 row events across six tables: 60 option events, one post-76 editor lock, six transient scheduler-claim events, four scheduler-action events, three scheduler logs and one skipped/zero-processed social runner audit. Woo action 15321 completed and scheduled 15322; no order table or Staff Tasks authority changed. Thirteen option endpoint values changed (cron/locks/transients/resource/gateway/calendar-intake bookkeeping). This baseline/platform/BVM noise was preserved, not restored over. It precedes the follow-up baseline, whose full 246-table state was restored after fixture cleanup. Evidence: `between-windows-classification.json`, bounded decoded binlog and both snapshots. Continued ordinary activity after guard removal is outside the accepted read windows.

## O. Rollback

`local-acceptance/rollback/manifest.json` records every original/candidate SHA-256 and original file backup. `restore.py --check-only` verifies both; `restore.py` restores only the eleven paths and refuses later drift. Full instructions: `local-acceptance/rollback/RESTORE.md`.

Retain additive schema fields/indexes and operational history on rollback. Restore schema option to 1.2.0 only under controlled local rollback to 411d86c. Keep the task cutoff inertly and never lower the Phase 3C staffing cutoff. Do not blindly restore database snapshots or erase subsequent real work. Original dirty tree/stash, frozen release and companions are outside rollback scope.

## P. Frozen and historical authority

Frozen release commit `04caf99ae7a98ac507c067a7535d2a672efa457b` is unchanged. Both existing release ZIPs were reverified during preflight and final verification: SHA-256 `2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3`, **396 files each**. No ZIP was created/rebuilt.

Protected stash `d08e726804712dc233f0e37b217abd6389963863`, historical `work/unreleased-2026-06-18` at `79da784`, its index/37 modified tracked paths/36 untracked entries, inactive legacy plugin and 13 companions remain unchanged. Preservation manifests show only the eleven authorized normal runtime paths changed. Accepted prior development/security commits remain separate and preserved. No push, production/staging modification, release submission, external message, real mail or Google integration occurred.

## Q. Accepted commit

The focused Phase 3D commit containing this report is based on 411d86c. Its exact SHA is recorded after commit creation in the durable `acceptance-report.md` and `accepted-authority.json`; no self-referential commit hash is embedded in its own tracked content.

## R. Final status

**ACTIVE / ACCEPTED LOCALLY**.

Staff Tasks is the canonical operational task authority with explicit review for unresolved legacy timing. The final normal view follow-up preserves all 246 tables across nine additional reads; its 31 final fixtures and 20 earlier setup-only rows were removed. Across both windows: 36 repeated read views, 153 synthetic rows removed, six intercepted synthetic mail calls and zero real mail. Final tracked/source/rollback verification and the exact accepted SHA are recorded in the durable acceptance receipt.
