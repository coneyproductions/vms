# Phase 4B native Staff Tasks calendar — local acceptance

Status: **ACTIVE / ACCEPTED LOCALLY**. Accepted parent: `982d7f352d46ba4e346b30bb74eb36d1fbd82bf6`. Isolated branch: `work/staff-tasks-calendar-phase4b-20260908`. Acceptance performed September 8–9, 2026 (America/Chicago/UTC).

Durable evidence root: `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-4b/`. Normal-local evidence is in its `local-acceptance/` directory. No production/staging, packaging, ZIP creation, tag, push, WordPress.org action or real Google operation occurred.

## A. Calendar architecture

One server-rendered GET surface, `admin.php?page=vms-tasks-calendar`, consumes instantiated canonical Staff Tasks. There is no new table, schema change, template expansion, REST data store, browser task model, timer or write cache. `calendar/read.php` performs scoped reads; `calendar/ui.php` renders escaped HTML; one stylesheet supplies the layout. Reloads reconstruct from committed task authority.

The existing single-task projection now delegates to a shared row projection. A bounded batch adapter primes users/posts/meta and shares dependency checks within one read. Commands still use the original uncached identity validation. Google mirror status can consume an already projected task, avoiding a second task lookup; provider, OAuth, queue and normalization behavior are unchanged.

## B. Day/week UX specification

Navigation: **Backstage Venue Manager → Vendors & Staff → Tasks Calendar**. The existing Staff Tasks/My Tasks and Dashboard/Planning context receive links; ECC links to this same calendar with its Event Plan filter. There is no second ECC calendar engine.

Day is a chronological agenda. Week has seven day columns, honoring WordPress's first-day setting. Previous/Next/Today, view toggles and date selection preserve normalized filters. Week counts show distinct tasks per assignee/day, with names inside an expandable workload summary; they are counts, not capacity scores. A task with both scheduled timing and a due marker retains one canonical ID and one workload count.

The wide desktop layout can place attention alongside the board; laptop/tablet widths put it below with a prominent jump link. Week columns can scroll horizontally at intermediate widths. Mobile stacks days and constrains filter controls. Actual layouts were checked at 1440, 1024 and 390 pixels. Native links, details, labels and forms support keyboard operation; identity/status use text as well as color. Existing global WordPress/BVM notices remain visible above the operational surface.

## C. Timing-rendering specification

| Canonical timing | Display |
| --- | --- |
| Start and end | Scheduled card with the real interval |
| Exact due time only | Diamond deadline marker, no invented duration |
| Date only | Separate due-date area, retaining its recorded civil date |
| Start without end | Start marker explicitly saying the end is unspecified |
| No timing | Unplaced queue, no guessed position |
| Timing/dependency review | Prominent review queue; dated placement paused |

Timed instants use canonical UTC and display in the site zone; details retain the recorded task zone/timing and event-relative or pinned provenance. Date-only values do not shift across zones. DST tests cover 23- and 25-hour days, foreign-zone instants and overdue classification after a site-zone change. Overdue stays derived from canonical UTC, never a stored calendar status. Closed in-range tasks needing review remain visible with All statuses. Legacy tasks 7 and 8 retain null timing and `legacy_timing_requires_review`.

## D. Filter specification

Assignee, event, current Event Plan venue, timing type, lifecycle status and required/optional combine in prepared reads. Supported timing types are Scheduled, Deadline only, Date only and Unscheduled. My tasks, Unassigned, Overdue, Timing review and Sync attention are scoped shortcuts that compose with filters. Reset preserves the selected date/view. Malformed scalar/array/date/enum values normalize safely.

Facets contain task-linked identities within the viewer's authority, capped at 201 identities per facet. Selected filters remain represented; no unrelated user directory is exposed. Event-linked venue follows current Event Plan context rather than an old task snapshot.

## E. Attention/unscheduled queue specification

The queue surfaces undated work, unassigned tasks, overdue work, canonical review/dependency warnings and unhealthy Google mirrors across dates. Ordinary attention defaults to open work; explicitly selected closed statuses and closed in-range review work remain inspectable. Review takes precedence over inferred placement. Canonical dependency validation decides the actual warning; SQL selects candidates only.

Attention queries read 101 candidates and return up to 100, with explicit continuation controls. Range reads select 201 and return up to 200, with a conspicuous partial-calendar warning and pagination. Empty candidate pages do not imply that later pages have no work.

## F. Google-sync status integration

Safe textual states include synced, pending/update pending, retry needed, authorization required, calendar attention, failed, timing-review blocked, not connected and not eligible. Only this allowlisted status reaches cards; credential fields, raw mirror payloads and provider responses are not rendered. The Google state is read once per calendar request. No Google calendar enumeration, transport, queue insertion or OAuth is triggered by this surface.

Normal-local health indicators were exercised with a temporary read-only synthetic option response, containing no credentials and never written to the database. That response was removed; a browser check then proved that canonical tasks render with Google not configured. Normal-local has zero Google connections. Phase 4A's connected disposable test account and private configuration were not used or changed.

## G. Permission model

The data function requires an authenticated user with existing manage-all or view-self capability. Non-managers are forcibly scoped to their own WP user ID, including forged assignee filters and facets. Managers retain the existing broader scope. Details and all writes remain behind accepted Staff Tasks authority.

Complete/reopen/cancel forms submit the existing `vms_tasks_transition` action with its accepted nonce, revision and operation identity. Edit/reassign links open the canonical filtered Staff Tasks editor; timing/history uses its existing detail page. Return navigation is allowlisted and normalized. No mutation handler is duplicated. Drag/drop rescheduling is explicitly deferred as a future UX enhancement.

Browser tests verify both staff identities, denied/anonymous calendar endpoints, cross-assignee detail denial, valid-nonce permission denial, missing nonce, stale revision, forbidden self-cancel and GET mutation refusal. Existing task-detail denial can produce an HTTP 200 admin shell containing “Task unavailable” after headers have rendered; no task/history data is returned. This pre-existing response-status behavior was recorded, not changed by Phase 4B.

## H. Query/performance characterization

Final supervised measurements: 1,200 completed historical fixtures; 200 visible task cap; 100 attention-candidate cap; 26 staff identities; **15 queries** at 200 visible tasks; exactly two reciprocal-link validation queries and no per-task record lookups. The history-filtered read measured approximately 0.039 seconds in this disposable environment, not a production benchmark.

Users, Event Plans, staff posts and metadata are primed in batches. Repeated event/person/role/legacy-identity checks share a request-local context. There is no persistent projection cache. JSON timing predicates bound returned work but do not add a new indexed timing schema; unusual dependency combinations can require distinct canonical validation work. Existing accepted authority remains decisive.

## I. Source changes

Nine runtime paths are byte-identical across isolated mirror, its sibling and installed normal-local:

- `includes/modules/staff-tasks/calendar/read.php` (new)
- `includes/modules/staff-tasks/calendar/ui.php` (new)
- `assets/css/staff-tasks-calendar.css` (new)
- `includes/modules/staff-tasks/authority.php`
- `includes/modules/staff-tasks/google/sync.php`
- `includes/modules/staff-tasks/staff-tasks.php`
- `includes/modules/staff-tasks/admin-ui.php`
- `includes/admin-ui/nav.php`
- `includes/admin-ui/context.php`

Repo-only additions are the calendar integration test/README and this report, plus an append-only remediation-ledger entry. No existing historical dirty-workspace file or index was edited. Final lint, diff checks, source review, runtime parity and hash-guarded rollback verification passed.

## J. Deterministic test results

`calendar14`: **238 assertions**, 95 tables unchanged, zero calendar-read SQL mutation/HTTP attempts. Coverage includes every requested timing class, lifecycle, unassigned/multiple people, combined filters, self/denied scope, malformed requests, rescheduling, pinning, review dependency changes, Google status, single/batch projection parity, DST and volume/pagination.

Receipts: `integration-result.json`, `calendar14-calendar.stdout`, `calendar14-supervisor.json`. The supervisor confirms no owned process or database residue remains. During development, a mobile flex-sizing overflow and a closed-review visibility edge case were corrected and reverified before acceptance.

## K. Browser acceptance

The installed normal-local WordPress/BVM runtime was exercised through a private loopback harness with synthetic actors and real accepted handlers. Final receipts: `browser-calendar-result.json` (58 checks), `browser-reschedule-result.json` (19), `browser-actions-result.json` (17), `browser-denials-result.json` (13), `browser-read-result.json` (35 views), and `browser-no-google-result.json`; three additional operational screenshots bring the successful read window to 39 views.

Relative gates work moved from October 18 at 16:00 to October 25 at 17:00 after the Event Plan save. Pinned briefing stayed October 18 at 15:00. Completed/canceled history and timing review remained coherent. Complete/reopen/cancel and reassign/back reflected canonical data after reload. Desktop/week, day, tablet, mobile, self views, filtered views and task detail screenshots are retained.

Harness qualifications: installed Chrome stalled during sequential navigation/screenshots; bundled Chromium passed the navigation/actions. Later, a single-worker PHP loopback server stalled after accepting a browser connection. The owned server was replaced with four guarded workers; repeated reads then passed. Earlier interrupted GETs remain covered by the same containment window. Synthetic venues initially used the wrong CPT and were corrected to `vms_venue`; Event Plan validation also required a synthetic claim-identity field to submit the existing form. A pre-existing Staff Tasks tour was dismissed through its close control. These harness/fixture issues did not require Event Plan or tour source changes. Screenshot/locator expectations were corrected for the existing 12-hour display and task-detail denial semantics.

## L. 246-table read-containment result

**Pass**: all 246 table row hashes and schemas, every option, private-file hashes/mtime/modes and binlog position match across the read window. Repeated calendar day/week/date/filter/attention reads, task history, ECC and Event Plan reads, no-Google rendering and viewport captures are included. Denied commands also have a separate identical 246-table/options/files/binlog before/after proof.

The established containment harness freezes independent normal-local requests, uses read-only database sessions, disables background dispatch and blocks outbound mail/HTTP. Calendar-owned mutation and mail attempts: zero. Google HTTP attempts: zero. Pre-existing platform hooks were contained: 668 blocked HTTP probes (WordPress/TEC/LiquidWeb/Square), 1,140 option-update attempts, 120 Action Scheduler SQL attempts, four navigation preferences and four editor locks. Counts include interrupted harness reads. These are disclosed suppressions, not a claim that unrelated plugins are naturally free of read-side activity. The isolated direct calendar test separately proves zero mutation and HTTP attempts without those normal-local platform suppressions.

Receipts: `local-acceptance/read-containment-result.json`, `denials-containment.json`, private before/after snapshots and bounded diagnostic logs.

## M. Regression results

The complete relevant accepted regression matrix was rerun:

- Phase 4A supervised Google workflow: **451 assertions**; remote representation: **16**.
- Phase 3D real-SQL task/concurrency: **205**.
- Staffing lifecycle **104**, concurrency **86**, deadlock **41**, financial **33**; migration/history replay idempotent.
- Phase 3C delivery **132**, deadlock **41** with **44** unique identities.
- **18** selected prior suites pass: Phase 3B tech documents (64), private storage/upload boundaries, Phase 3A cancellation/financial authority, ECC context/fixtures, Event Plan workflow/import boundaries, reporting-provider contracts, authorization/nonce, task-signature and ticket-integrity checks.

Three historical structural tests fail identically on accepted parent and Phase 4B: `staff-tasks-overrides-json-remediation` (old checklist nonce text), `staff-tasks-repository-sql-remediation` (old DirectQuery inventory), `ticket-integrity-query-filter-boundary-remediation` (old G16 helper projection). Fresh parent/final logs and `regressions/parent-baseline-failures.json` establish these are not new failures. They were not rewritten to hide the baseline.

Final `google11`, `regression12` and `calendar14` supervisors all prove process/residue cleanup. Earlier fake-provider path and supervisor-teardown failures are retained. The `google04` EPERM teardown was recovered only after independently proving owned process/group exit and MySQL shutdown; its recovery receipt is retained and later clean supervised runs supersede it.

## N. Fixture cleanup/state integrity

**207 synthetic rows removed**: 13 tasks, 26 task logs, 80 notification rows, seven posts, 72 postmeta rows, three users and six usermeta rows. Cleanup verifies ownership and every original-row hash before deletion, then verifies all 246 tables against the original baseline. Original options/schema/private files match. Auto-increment counters were intentionally not reset. No templates or real Google fixtures were created by Phase 4B.

The first cleanup emitted missing-key warnings while classifying unchanged non-auto-increment staffing rollups; it still completed all ownership/original-row/final-table checks. The retained harness now skips unchanged tables before ownership-key classification. Independent post-cleanup snapshots verify exact restoration. Legacy tasks 7/8 remain protected. No Google status fixture is installed.

All owned PHP workers/process groups exited. Temporary MU guard/state, private session key and fixture nonce were removed; normal-local resumed. Evidence snapshots remain private. See `cleanup-result.json`, `cleanup-integrity.json`, `final-site-check.json` and `runtime-teardown.json`.

## O. Rollback procedure

Hash-bound backups and instructions: `local-acceptance/rollback/RESTORE.md`. `restore.py` first verifies all nine installed after-hashes and every original backup; `--restore` restores the Phase 4A loader first, restores six existing paths and removes the three new files. Run only within a contained normal-local maintenance window. It refuses source drift and performs no database restoration. Fixture cleanup is already complete; old database snapshots must not be replayed over current business data. Dry-run passed after cleanup.

## P. Frozen-authority verification

Historical dirty worktree/HEAD/branch/index, inactive legacy VMS and all 13 companion trees match baseline. Protected stash `d08e726804712dc233f0e37b217abd6389963863` (`WPORG-16D preserve unrelated sidebar+doc work`) is unchanged. Only the nine authorized installed normal-local runtime paths differ.

Frozen source remains clean at `04caf99ae7a98ac507c067a7535d2a672efa457b`. Both release archives still contain 396 files and SHA-256 `2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3`. Original Phase 4A candidate `77805caf5494481ac01323b3f68f97a16a2475fe` and accepted Phase 4A worktree remain unchanged. See `preservation-result.json`, `runtime-parity.json`, `frozen-verification.json` and final `closeout.json`.

## Q. Final accepted commit

The focused commit containing this report is the Phase 4B accepted authority. Its exact SHA, parent, source delta and clean post-commit verification are recorded in durable `phase-4b/closeout.json` and the external `acceptance-report.md`, avoiding a self-referential commit hash.

## R. Final status

**ACTIVE / ACCEPTED LOCALLY**. The normal-local native calendar is accepted with Staff Tasks as sole authority. Google remains supplemental; drag/drop is deferred. No broader ECC redesign or subsequent backlog feature is included.
