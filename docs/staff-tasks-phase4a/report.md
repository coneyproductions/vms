# Phase 4A — per-staff Google Calendar projection

Date: 2026-09-08. Parent authority: `f5a3df7c600ea1d4c7946ae1adc4072512f05ccc`. Candidate branch: `work/google-staff-tasks-phase4a-20260908`. Evidence root: `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-4a/`.

## Authority and scope

BVM Staff Tasks remains the sole operational task authority. This integration consumes `bvmgr_tasks_sync_record()` and never writes task instances, task logs, event occurrence state, staffing assignments or timing. The only change to an existing runtime file is loading the six Google components from `includes/modules/staff-tasks/staff-tasks.php`. No native day/week manager calendar, Google Tasks integration, companion implementation coupling or frozen-release change is included.

Development occurs in `/private/tmp/bvm-google-phase4a-20260908/packages/vms-github-reconcile`, with a synchronized isolated sibling `../../vms`. The normal installed plugin remains at accepted Phase 3D; this candidate is not promoted. The starting historical preflight warning was the expressly preserved dirty workspace, not an instruction to clean it. The isolated worktree passed clean preflight before edits.

## OAuth, identity and credentials

Each connection belongs to the accepted canonical **WordPress user ID**. Existing staff links must pass Phase 3D's active/reciprocal/unambiguous identity validation, and the user must have task-view/manager access. Staff records without a usable linked WordPress identity cannot currently own canonical Staff Tasks. They must receive an appropriate WordPress account/link through the existing administration process before connecting; this implementation does not create a second email-based identity or an anonymous portal callback.

The connect POST uses the current signed-in user and WordPress nonce. A random 256-bit OAuth state is stored as a hash, bound to that user's current WordPress session, with a ten-minute expiry. Callback validation consumes state once before exchanging a code. State from another session, a different user, an expired flow or a replay is rejected. Only the authenticated `admin_post` route can exchange authorization. A denial-only `nopriv` handler clears an expired-session callback URL and redirects to login without reading or exchanging its code.

The site-owned confidential client requests `openid` plus `calendar.app.created`, offline access and explicit consent/account selection. It rejects unexpected granted scopes. Google `sub`, not email, records the account relationship; one Google subject cannot bind to two BVM users. Reconnect must return the original subject. ID-token claims are accepted only from the direct TLS-validated token endpoint response, with issuer, audience/authorized party, time and nonce checks. This follows Google's documented direct-server-response exception; no browser-supplied ID token is accepted. No ID token is retained.

Client ID/secret and a separate random 32-byte encryption key are configured on the server, outside public plugin source. Refresh/access tokens use sodium authenticated encryption, following the accepted BVM encrypted-token approach with a separate deployment key. State is not autoloaded. Ordinary DB/plugin-file disclosure alone does not yield usable credentials without that key. Refresh-token replacements are encrypted and saved before use. Invalid/revoked grants stop in `authorization_required`; changing client ID or losing the encryption key fails closed. Application output contains safe state names and HTTP classes, never codes, token bodies or raw exceptions. Trusted server HTTP hooks remain a deployment logging boundary documented in [configuration.md](configuration.md).

## Calendar and event identity

The site gets a durable random namespace on its first connect action. Each connection stores its Google subject, client fingerprint, dedicated calendar ID, random calendar marker, calendar health and encrypted token envelope.

`calendars.insert` creates one secondary **BVM Tasks** calendar with the marker in its description. There is no primary-calendar write or calendar-list/personal-event query. An atomic local creation-intent save precedes the request. Because Google's insert method has no caller-assigned calendar ID, an uncertain outcome is deliberately **not retried automatically**. Recover by supplying the calendar ID from Google's UI; BVM checks the exact marker under narrow app-created access. Definitively rejected requests have an explicit retry. A proven deleted calendar requires a confirmed replacement action bound to the old calendar identity; replay cannot start another replacement. An uncertain replacement follows the same stop/recovery rule.

Event ID is `b` followed by SHA-256 of the durable site namespace and canonical task ID. Hex fits Google's base32hex alphabet. Revision, title, status and reconnect session never enter event identity; the same logical task has the same ID within each dedicated calendar. Private event properties record BVM site/task/user ownership, task revision/status, modification time, template ID and recurrence-root lineage. IDs do not depend on event title. Requests use no attendees and `sendUpdates=none`.

## Timing, lifecycle and privacy mapping

| Canonical state | Google representation |
| --- | --- |
| Scheduled start and end | Exact recorded UTC instants projected with the recorded timezone. Open scheduled work is opaque/busy. |
| Scheduled start without end | Transparent one-minute **Start marker (end unspecified)**. No claimed work duration. |
| Date-only due | Transparent all-day **Due date**, ending on the following date exclusively. |
| Exact due time without schedule | Transparent **Deadline** marker beginning at the exact due instant and ending one minute later; description explicitly states this is not a duration block. |
| No calendar-relevant timing | BVM only; existing mirror is retired if timing is removed. |
| Timing/dependency review | No new event or timing change. Existing terminal tasks can receive status-only transparent patches while previous timing stays frozen; the local review block remains visible. |
| Open / scheduled | Active task title and canonical timing. Scheduled is derived by BVM, not a new Google task status. |
| Done | Same event, **[Completed]**, transparent; historical day/week context remains. |
| Skipped / canceled / superseded | Existing event retained with explicit terminal label and transparency; no newly connected historical terminal backlog. |
| Reopen | Same event ID; canonical open label and appropriate availability restored. |
| Reassignment / loss of eligibility | Former target's event becomes **[Retired]**, transparent, with a retirement explanation and metadata. New connected target receives its own operation. |

If former credentials are disconnected/revoked, BVM cannot perform remote retirement until reconnect; the unresolved former target remains visibly `not_connected`/`authorization_required`. This never prevents the authoritative reassignment. Disconnect itself preserves history and may therefore leave previously mirrored items visible in Google, as explicitly stated in the UI.

Event-relative tasks move only when Phase 3D has reconciled their canonical timing. Pending dependency reconciliation blocks Google timing; pinned tasks keep their canonical instants. Initial selection includes all open assigned work plus completed tasks with current/future timing or timing within the preceding thirty days. Previously known mirrors remain eligible for lifecycle/reassignment cleanup independently of that initial window.

The allowlist sends assignee-visible task title, task ID, status, timing/timezone/provenance, Event Plan numeric relationship, template/root identifiers and the protected BVM task-detail link. It omits free-form instructions, staff/customer notes, cancellation reasons, customer data, finance, document paths/attachments and unrelated Event Plan content. A task title is assumed to be intended for its assignee; operators should keep confidential material in the excluded instruction/private-document fields. Google receives no staff email address or guest invitations.

## Durable work, retries and concurrency

The existing committed task rows/audit are the durable recovery source. The worker reads current committed projections on a separate SQL connection and persists target delivery intent in the non-autoloaded `bvmgr_tasks_google_v1` option before any event request. Polling committed authority closes the task-commit/queue gap without adding a Google write to the five-table Staff Tasks transaction. Failed or rolled-back task mutations have no committed task to enqueue. Page reads do not scan into a queue or create state.

All Google connection/queue mutations share one connection-scoped MySQL advisory lock and the accepted no-reconnect database adapter. Task commands do not share that delivery lock; they can commit during slow Google transport. Workers re-read authority immediately before delivery and enqueue the latest projection again after an operation. If a process dies in the intervening window, the next scan recovers from committed authority. Former-assignee and current-assignee operations have separate durable results. The queue holds latest desired projection and independently applied projection, so an old revision cannot acknowledge a newer one.

Worker claims are serialized; database connection loss fails closed, rather than transparently reconnecting without the lock. Deterministic event IDs recover remote success/local acknowledgement loss. A repeated request, worker restart or manual sync first retrieves that ID and compares BVM-owned fields. Matching fields do not generate another insert/patch. Nonmatching fields use a targeted ETag-conditional patch; unrelated reminders/colors/private properties remain. At most twenty eligible attempts run per tick, oldest attempted targets first to avoid starvation, with a soft time budget. The existing fifteen-minute cron drives delivery; hourly reconciliation repairs external edits/deletions even without a BVM revision change.

Safe persistent states include `not_connected`, `not_eligible`, `blocked_timing_review`, `pending`, `update_pending`, `synced`, `failed_retryable`, `authorization_required`, `calendar_missing` and `permanent_failure`. Connection state separately distinguishes new, ready, rejected, uncertain and missing calendar creation/availability. Current task status is projected on reads so a newly committed revision does not misleadingly remain “synced” before the next worker. Each target retains its ten most recent safe operation results, counts, attempt and success timestamps; this bounded delivery history is not BVM's audit ledger.

| Failure | Handling |
| --- | --- |
| Expired access token / first 401 | Refresh offline credential, persist replacement, retry the API once. |
| Invalid/revoked refresh grant / repeated 401 | Erase unusable local token and require reconnect; no endless retry. |
| Rate-limit 403, 429, 5xx, timeout | Bounded exponential delay with jitter; maximum eight attempts before operator-visible permanent failure. |
| Ordinary permission 403 or malformed-event 400 | Permanent failure; current BVM task remains intact. |
| Event 404/410 | Check dedicated calendar first; reuse deterministic event identity if calendar exists. |
| Calendar 404/410 | Calendar missing state; no blind recreation. |
| Insert 409 | Retry through GET of the same deterministic identity. |
| ETag 412 | Refetch and conditionally reconcile current BVM-owned fields. |
| Foreign ownership metadata | Fail closed; do not overwrite an unrelated event. |
| Uncertain calendar insertion | Preserve creation intent for explicit marker-based recovery. |

## Validation and remaining real-account gate

Final deterministic result and commit identity are recorded in the closeout below. The fake intercepts the production HTTP boundary; independent SQL readers prove committed task/attempt intent before writes. Tests cover connection/session identity, offline scopes/encryption, calendar idempotence/uncertainty/recovery/replacement, all timing classes, lifecycle/reopen/cancellation, reassignment, relative movement/pinning, external edits/preferences, ETags, rate limits/service errors, auth expiry/revocation/reconnect, event/calendar loss, request replay, duplicate workers, process kill after remote create, local persistence failure after remote success, and completion/reassignment during delivery. It uses ninety-five real disposable tables, not an invented 246-table fixture. Supervisor receipts prove process and database-residue cleanup.

Normal installed-runtime read acceptance uses the candidate Google components loaded explicitly beside accepted Phase 3D without promotion. The final window performs **33** repeated direct renderer/projection reads of Google status, task lists/history, Event Plan tasks and ECC. All **246** table row/schema manifests, options, private files and binlog remain identical. Legacy tasks **7 and 8** retain timing-review blocks. The temporary guard is removed. This is guarded real WordPress renderer acceptance, not an authenticated browser OAuth test. Connected-state/history reads are additionally tested in the disposable database. The first read harness attempt incorrectly re-required an already loaded function file and failed before rendering; its error and guard-cleanup evidence are retained. The corrected initial window passed 24 reads and the expanded final window passed 33.

Fresh accepted regression results: Phase 3D authority/concurrency/recovery **205**; staffing lifecycle **104**, concurrency **86**, deadlock/retry **41**, financial **33**, idempotent migration/history preservation; Phase 3C notification delivery **132** and deadlock/recovery **41**, with **44** unique committed audit identities. The fifteen selected Phase 3A/3B/private-document/Event Plan/ECC/reporting/security suites and three further task/ticket suites pass. Three older structural tests retain the documented Phase 3D baseline failures (override nonce-text expectation, task SQL line-number inventory, ticket helper hash). No passing claim is made for those tests. No release builder, package or Plugin Check run was performed.

No suitable Phase 4A Google configuration is present in the normal wp-config or process environment. No real Google account, consent exchange or API acceptance was attempted. Real acceptance must use an explicitly authorized test Google account and an allowed redirect origin, then exercise connect/calendar creation, scheduled/date-only/deadline tasks, same-event updates, complete/reopen, second-test-user reassignment where available, externally deleted event/tombstone recovery, revocation/disconnect/reconnect and confirmation that no unrelated calendar is accessed. Actual Google tombstone behavior, scope/consent issuance and calendar-client presentation remain unverified until that gate. See [configuration.md](configuration.md).

## Source, preservation and rollback

Runtime delta: one bootstrap file plus six new `google/{state,provider,mapping,sync,oauth,ui}.php` components. Tests: `tests/staff-tasks-google/{fake,integration,concurrency,worker}.php` and README. Documentation: this report, configuration guide, rollback guide and a focused ledger addendum. Existing task authority, notification implementations, private-document/ticket/financial/ECC source and companion trees are unchanged.

Private evidence contains exact original/candidate SHA-256 manifests, the original bootstrap backup and a candidate source patch. Normal plugin source was never promoted, so there is no normal runtime rollback to execute. [rollback.md](rollback.md) explains isolated rollback and the conditional later promotion procedure. No database schema change is required by Google; preserve the durable identity option on any later rollback rather than dropping identities or deleting Google history.

All fake task/user/calendar fixtures are confined to the destroyed disposable database/provider evidence. Normal tests create no fixtures or Google state. Frozen commit `04caf99ae7a98ac507c067a7535d2a672efa457b` and both existing qualified ZIPs retain SHA-256 `2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3`, **396 files** each. Protected stash `d08e726804712dc233f0e37b217abd6389963863`, the historical dirty workspace/index/HEAD and all normal/legacy/companion source manifests remain unchanged. No push, package/ZIP creation, tag, deployment, production/staging modification, WordPress.org action or external message occurred.

## Closeout

**READY FOR REAL GOOGLE ACCOUNT ACCEPTANCE**.

The final supervised provider run passes **369 Google-workflow assertions**, plus the **82** accepted Staff Tasks foundation assertions (**451 total**). These counts include fixture/commit-boundary assertions, not 369 distinct product capabilities. Final evidence is `final11-task-concurrency.stdout` and `disposable-final11.json`; supervisor cleanup succeeds. The large-backlog test's earlier two-forced-runs assumption was corrected: each forced pass also rechecks existing mirrors, so bounded pending work must be allowed to drain in ordinary subsequent ticks. The final test verifies all 23 new backlog tasks drain and repeated reconciliation creates no duplicate events. Earlier failed harness/fake-provider runs remain in evidence.

The final normal read window passes **33** views with exact **246-table**, options, private-file and binlog preservation. PHP lint, source review, whitespace checks, isolated sibling parity and rollback dry-run pass. Final commit identity, source manifest/patch and preservation receipts are retained in the private `acceptance-report.md` / `closeout.json`. This is an unpromoted candidate ready for the real test-account gate, not a claim of active local Google integration.
