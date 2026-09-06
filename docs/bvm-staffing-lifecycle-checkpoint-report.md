# Staffing lifecycle checkpoint forensics and implementation gate

Date: 2026-09-06. Result: **STOPPED BEFORE RUNTIME IMPLEMENTATION — transactional locking design required.**

The task explicitly requires: “If true transactional locking/schema changes are required, stop and specify them rather than pretending application code fully solves the race.” It also permits a coherent local commit of safe source/tests/spec portions when migration remains unresolved. This deliverable follows that exception; it is not a completed or deployable lifecycle feature.

## A. Authority, branch, and worktree

- Base branch: `checkpoint/bvm-accepted-local-2026-09-06`.
- Verified base SHA: `1e53fa3e4afc4301ff9c5b912df1a4bfc83f7444`.
- Task branch: `work/staffing-lifecycle`.
- Worktree: `/private/tmp/bvm-staffing-lifecycle/packages/vms-github-reconcile`.
- Original tree remains on `work/unreleased-2026-06-18` at `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`.
- Required preflight ran first in the original repository: expected dirty tree warning, clean whitespace check, correct mirror/live paths, protected stash present. The task explicitly identified that dirty tree and directed worktree isolation. No file edit or index change was made there.
- The normal-live synchronization rule is superseded by this task's explicit source/disposable-only instruction. No runtime file was changed in either tree.

The historical preflight script assumes a live sibling `../../vms`, which is not part of an isolated source checkout. It was not altered, pointed at normal Local for writes, or reported as passing an isolated live-tree check. Worktree branch/base, empty initial index, and source delta were verified separately. Tests needing `../../backstage-venue-manager` used a disposable copy of checkpoint `includes/` under `/private/tmp/bvm-staffing-lifecycle/backstage-venue-manager`; no WordPress configuration or database was created there.

## B. Files changed

1. `docs/bvm-staffing-lifecycle-follow-up.md`: replace the earlier incomplete proposal with explicit transitions, authorization, UI, atomic persistence, migration questions, audit/event contract, and acceptance criteria.
2. `docs/bvm-staffing-lifecycle-checkpoint-report.md`: this forensic report and A–P closeout.
3. `tests/staffing-lifecycle-checkpoint-forensics.php`: source-derived, in-memory characterization of the accepted repository and its concurrency/audit gaps.

No shared runtime, database migration, release manifest, packaging, or ledger file changed. This is lifecycle planning/forensics rather than a completed WordPress.org remediation batch; the dedicated report avoids adding a competing shared-ledger edit to the parallel branches.

## Source findings: existing versus absent

Line references below identify unchanged accepted-checkpoint source.

| Surface | Existing source | Missing or unsafe for the requested lifecycle |
| --- | --- | --- |
| Schema | `includes/db/migrations.php:255–275`: assignment ID, slot/staff IDs, `status VARCHAR(20)` default proposed, overrides/notes, shift timestamps, actual times, creation/update actor and time; primary ID plus nonunique slot/status and staff/status/window indexes | No unique association constraint, revision, or specified storage engine. Declined fits the existing column. |
| Audit schema | `includes/db/migrations.php:309–323`: audit ID, plan, actor, action, before/after JSON, UTC-compatible datetime | Suitable store; no new audit table needed. |
| Matrix writer | `includes/core/staffing.php:3343–3689`: creates Proposed, retains a sequentially observed Confirmed, revives other selected statuses as Proposed, cancels omitted/duplicate rows; signature no-op guard | No transition service, compare-and-set, transaction, shared staff lock, or per-transition audit. A Declined row omitted during another matrix change becomes Canceled. |
| Other writers | `includes/core/staffing.php:1683` template application can bulk cancel active assignments; `:1955` synchronizes active assignment times | Must join the same locking/audit protocol as confirmation. Event/slot time changes cannot bypass conflict validation. |
| Duplicate handling | `includes/core/staffing.php:3298`: confirmed > proposed > canceled, then lowest ID; extra rows canceled | Sequential application protection only; no protection between the read and insert. Declined has no explicit reconciliation priority. |
| Shared snapshot | `includes/core/staffing.php:2508,2768`: normalized authority, legacy-only provenance, proposed/confirmed/assigned/required/open/unique counts and rollup freshness | Declined is excluded from active counts but lacks explicit history subtotal; historical rows are not an operator lifecycle interface. |
| Event Plan UI | `includes/cpt/event-plans.php:6002–7120`, initial `includes/cpt/event-plans/partials/staff.php`: normalized matrix/role rendering, shared summary, candidate/qualification checks | No explicit Confirm/Cancel/Repropose lifecycle controls. Both render paths require implementation and tests. |
| Operator boundary | `includes/cpt/event-plans.php:4285,4407,8844`: `edit_post`, verified editor payload, authenticated AJAX with nonce helpers; dedicated admin-post actions also exist | No assignment-action endpoint or ownership chain/revision validation. |
| Portal | `includes/portal/staff-portal.php:1732–1751`: login and `_vms_staff_id` link to `vms_staff`; `:1192` selects own active Proposed/Confirmed rows and filters date/visible plan status; `:377` labels Proposed/Confirmed | No Accept/Decline controls or handlers; no Declined/Canceled history UI. Other statuses fall back to “Scheduled.” |
| Portal conventions | `includes/portal/staff-portal.php:551` permits ready/published/tentative/confirmed plans; `:2854` authenticated availability AJAX checks nonce and linked staff; assigned dates are locked | Reuse identity/nonce patterns, not availability mutation. Existing upcoming filter is local-date based, not a complete expired-shift mutation guard. |
| ECC | `includes/admin/event-command-center.php:1308` shared staffing snapshot; summary at `:2956–2962` shows coverage, required/open, proposed/confirmed, unique people | Needs shared overlap warnings and clear assigned/tentative/committed copy; no independent status policy. |
| Overlap | `includes/core/staffing.php:3947–3993`: rollup reports confirmed overlaps using stored timestamps | Detection after writes, not rejection. Ignores proposed warnings; excludes all assignments in the same event; does not explicitly filter the other slot active. No concurrency gate. |
| Labor | `includes/core/staffing.php:3739–3783`: estimate from planned slot headcount/rate/hours; `includes/admin/event-profitability-report.php:170` consumes shared snapshot rollup | This is planned labor, not confirmed/actual pay. Preserve it and coordinate any new metric with Financial Authority. |
| Staff tasks | `includes/modules/staff-tasks/store.php:1750`: scheduled-role resolver accepts proposed, confirmed, and checked_in | `checked_in` is an existing consumer exception; no assignment writer for it was found. Do not introduce Completed or silently reinterpret unknown history. |
| Notifications | `includes/core/staffing.php:814,860`: qualification email; `:3681`: `vms_staffing_event_saved`; staffing task/cancellation modules have separate notification infrastructure | No assignment-transition event or delivery contract. Existing mail paths must not be repurposed implicitly. |

No dedicated lifecycle REST/admin-post/AJAX handler was found in the traced assignment writers and portal. Existing transaction code in event-reschedule handles other workflows and does not establish a staffing-wide staff lock.

## C–F. Lifecycle, permissions, operator and staff UX

The [specification](bvm-staffing-lifecycle-follow-up.md) defines Proposed -> Confirmed/Declined/Canceled, Confirmed -> Canceled, and deliberate operator reproposal from Declined/Canceled. No Completed state. Operators use the existing plan-specific `edit_post` boundary; staff can only Accept/Decline their own current proposal. All actions require authenticated POST, a contextual nonce, server-resolved ownership, and fresh state/revision validation.

Operator workflow is a separate per-assignment state/action list beside the bulk proposal matrix. Staff see their event/role/time and Accept/Decline, with read-only committed/history states. These are specified only: no controls or endpoints were installed.

## G. Audit model

Reuse the existing staffing audit table, adding assignment/slot/staff/state/revision/operation/context/source/reason detail inside before/after JSON. Plan, actor, and UTC time already have columns. Every changed row requires a successful audit write in the same transaction. Current void helper ignores failed inserts; the characterization reproduces a successful matrix response with an unaudited proposal when the audit insert fails.

## H. Overlap and locking model

Proposed is tentative and may overlap with a visible soft warning. Confirmation rejects other confirmed active overlaps, including distinct roles in the same event. Declined/Canceled/inactive slots do not conflict; adjacent half-open windows do not overlap. No confirmed-overlap override is introduced.

A deterministic repository interleaving reproduces the stale matrix overwrite: the assignment read returns Proposed, another request changes the stored row to Confirmed/Declined/Canceled, then the actual checkpoint matrix function writes its stale Proposed decision by assignment ID. A separate interleaving reproduces duplicate proposal creation after a concurrent insert. These use the actual extracted matrix/reconciliation/audit functions with an in-memory wpdb double, not a live database.

The two-confirmation example is explicitly a model, since no confirmation service exists yet. Two requests both read no committed overlap and update different proposed rows; both per-row compare-and-set operations can succeed. This establishes why a new endpoint-only state guard would be insufficient, not a claim of tested MySQL locking.

Required next implementation unit: transactional engine verification, a shared parent-plan/staff locking protocol across every writer, versioned stale-request handling, atomic audit, and two-connection database tests. Full sequence, lock order, cache invalidation, error behavior, and migration alternatives are in the specification. True locking must be resolved before lifecycle UI is enabled.

## I. Notifications

No events were added and no notifications sent. A future versioned `vms_staffing_assignment_transitioned` post-commit action is specified with audit ID as event identity. Creation, confirmation, decline, cancellation, and reproposal are distinguishable by states/source. Delivery, outbox guarantees, subscriber permissions, and retry behavior are deferred. Existing qualification/cancellation email was not called.

## J. Tests and results

All executed tests ran as isolated PHP CLI processes with in-memory stubs, without WordPress bootstrap or a database connection. Interpreter: PHP 8.5.9 CLI; this is not PHP 8.3 runtime acceptance.

| Command | Result |
| --- | --- |
| `php tests/staffing-lifecycle-checkpoint-forensics.php` | PASS, 11 assertions: creation/batch-audit/no-op/sequential-retention controls plus stale overwrite, Declined history overwrite, duplicate insertion, ignored audit failure, and the explicitly modeled cross-assignment confirmation race |
| `php tests/p0-source-consistency-repair.php` | PASS: accepted source/count/provenance/freshness controls |
| `php tests/staffing-repository-sql-remediation.php` | PASS |
| `php tests/staffing-final-repository-sql-remediation.php` | PASS |
| `php tests/staffing-matrix-rollup-reporting-repository-sql-remediation.php` | PASS |
| `php tests/staff-portal-safe-html-output-remediation.php` | Existing checkpoint failure at line 238: source regex requires `vms_staff_portal_`, but accepted calls are `bvmgr_staff_portal_`. Test and portal source have zero diff from the base. Left unchanged. |

PHP lint for the new test and `git diff --check` passed. The complete documentation delta and new test were reviewed before staging.

Characterization PASS means the documented checkpoint gaps were reproduced; it is not a lifecycle feature acceptance result. Staff accept/decline, lifecycle nonce/ownership failure, transitions, true transactional races, and UI counts remain unimplemented and untested as a feature. The specification lists the full remaining acceptance matrix.

WordPress-backed Event Plan eligibility and the official/additional ecosystem harnesses were not run. No runtime changed, and their normal-Local containment/bootstrap behavior is unnecessary for this spec-only deliverable. No claim is made of an all-green ecosystem or end-to-end lifecycle.

## K. Compatibility regressions

No runtime delta, so no new runtime regression is introduced by this commit. The existing portal source-pattern failure is recorded rather than changing unrelated tests to manufacture a green result. The newly documented races and history overwrites are checkpoint behavior, not changes made here. Future implementation must retain P0 assigned/required/open/legacy semantics and planned labor estimates.

## L. Schema/migration requirements

No schema or migration was executed. Declined needs no status-column change; audit content fits current JSON columns. Recommended assignment revision and optional indexed idempotency support need a migration decision. Transactional engines are required for atomic assignment-and-audit writes, but source alone cannot establish installed engines. Conditional engine conversion, stable parent-row versus new lock-table strategy, and retained duplicate handling remain unresolved. Do not add a unique pair index or claim application code closes the race without this work.

## M. Local commit

One local documentation/forensics commit is intended after final lint, diff, source-preservation checks, and review. Its SHA is returned in the task closeout; it cannot be embedded in its own committed contents. No push is authorized or performed.

## N–O. Merge and Financial Authority hotspots

This deliverable changes only two documentation files and one new isolated test. It has no runtime conflict with the accepted checkpoint. The pre-existing lifecycle follow-up document is the only edited existing file.

Future implementation is likely to overlap Financial Authority at `includes/core/staffing.php` (shared snapshot/rollup/labor, matrix write path) and `includes/admin/event-profitability-report.php` (labor interpretation). `includes/cpt/event-plans.php` and `includes/admin/event-command-center.php` are broad shared UI/summary hotspots. Coordinate `includes/db/migrations.php` if either task adds schema versions. The specific Financial Authority branch was not inspected, so these are anticipated hotspots, not an assertion of actual merge conflicts.

## P. Environment preservation evidence

No normal WordPress bootstrap, database client, WP-CLI invocation, plugin activation/deactivation, browser mutation, network, SSH, staging/production access, packaging/ZIP/tag/deploy, message delivery, or remote Git operation occurred. All new source/test/spec writes are in the isolated `/private/tmp` worktree or its disposable evidence/fixture directories. Git worktree/branch creation and the local commit use shared repository metadata as required, while preserving the original worktree/index and protected stash.

Read-only file fingerprints were captured after source forensics and before characterization/test/spec work at `/private/tmp/bvm-staffing-lifecycle/evidence/preservation-before.json`. This is a bounded before/after check, not a claim of a database snapshot or a fingerprint preceding the first read.

| Tree | Files | Fingerprint (SHA-256 of sorted path/hash JSON) |
| --- | --- | --- |
| Original dirty mirror, excluding `.git` | 1783 | `77b3e2f6e3f91b460591f7c6b72de473a61b2bbce3e5b2b17a5fcdcaab3bc353` |
| Normal Local canonical BVM | 384 | `a00ab0d2e74ad3a971ab2d882fec568ba919c948297811ead87b7d3c78030ac5` |
| Normal Local legacy VMS | 408 | `7955978c49e3d3d1b588d1c3821595af06863cead1b4bf4d7d69f200969a741e` |

Final rechecks are saved beside that receipt as `preservation-after.json` and `preservation-result.json`. All seven comparisons passed: original/canonical/legacy file manifests, original HEAD, porcelain status, index diff, and protected stash ref. All three file fingerprints above remained exact. Database preservation evidence is the absence of any database connection/bootstrap in this execution; no unchanged database hash is claimed. Other concurrent tasks/background activity are outside this task's evidence boundary.
