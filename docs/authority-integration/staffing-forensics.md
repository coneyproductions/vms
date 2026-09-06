# Staffing integration forensics — 2026-09-06

Read-only comparison performed on clean `work/staffing-lifecycle`, HEAD `18146de3d652a269cb9b056da23efffda9141382`, against Wave 4 `1e53fa3e4afc4301ff9c5b912df1a4bfc83f7444`. History contains `96309bdf5795a3944f002dee4c21811394362443` followed by runtime implementation. No source/git/DB mutations or runtime tests were performed. Required preflight ran: clean status/diff; protected stash present; missing isolated sibling `/private/tmp/bvm-staffing-lifecycle/vms` warning. Current user task explicitly requires isolated source and prohibits normal-local synchronization; this missing deployment sibling is expected under that instruction. Workflow and pertinent recent ledger were read.

## Exact file inventory (33)
- `assets/css/vms-staffing-lifecycle.css`
- `assets/js/vms-event-plan-staff.js`
- `assets/js/vms-staffing-lifecycle.js`
- `docs/bvm-staffing-lifecycle-checkpoint-report.md`
- `docs/bvm-staffing-lifecycle-follow-up.md`
- `docs/bvm-staffing-lifecycle-implementation-report.md`
- `docs/bvm-staffing-lifecycle-validation.json`
- `includes/core/event-reschedule.php`
- `includes/core/load.php`
- `includes/core/staffing-lifecycle-ui.php`
- `includes/core/staffing-lifecycle.php`
- `includes/core/staffing.php`
- `includes/cpt/event-plans.php`
- `includes/cpt/event-plans/partials/staff.php`
- `includes/db/staffing-lifecycle.php`
- `includes/portal/staff-portal.php`
- `tests/event-plan-staff-eligibility.php`
- `tests/event-plan-staff-inline-js-remediation.php`
- `tests/p0-source-consistency-repair.php`
- `tests/staff-portal-inline-js-remediation.php`
- `tests/staff-portal-safe-html-output-remediation.php`
- `tests/staffing-final-repository-sql-remediation.php`
- `tests/staffing-lifecycle-checkpoint-forensics.php`
- `tests/staffing-lifecycle/README.md`
- `tests/staffing-lifecycle/bootstrap.php`
- `tests/staffing-lifecycle/concurrency.php`
- `tests/staffing-lifecycle/deadlock-worker.php`
- `tests/staffing-lifecycle/deadlock.php`
- `tests/staffing-lifecycle/integration.php`
- `tests/staffing-lifecycle/runtime.php`
- `tests/staffing-lifecycle/worker.php`
- `tests/staffing-matrix-rollup-reporting-repository-sql-remediation.php`
- `tests/staffing-repository-sql-remediation.php`

## Architecture and changed existing functions

- `includes/core/staffing.php`: `bvmgr_staffing_apply_template_to_event`, `bvmgr_staffing_seed_event_slots_from_template`, `bvmgr_staffing_save_event_roles_matrix`, `bvmgr_staffing_compute_rollup` now join the common transaction; template replacement preserves canceled slots/history and refuses implicit active cancellation. `bvmgr_staffing_sync_assignment_shift_timestamps_for_slot` delegates to lifecycle synchronization. `bvmgr_staffing_event_plan_datetime`, `bvmgr_staffing_resolve_anchor_local`, `bvmgr_staffing_resolve_slot_window` zero request-time seconds. `bvmgr_staffing_resolve_event_snapshot` attaches overlap warnings. `bvmgr_staffing_reconcile_existing_assignment_rows` documentation now reflects review rather than automatic cancellation.
- `includes/core/event-reschedule.php`: `bvmgr_event_occurrence_apply` joins staffing boundary, suppresses its own nested BEGIN/COMMIT, synchronizes windows before verify, propagates typed staffing failures.
- `includes/core/load.php` adds lifecycle, UI, and explicit migration modules immediately following staffing.php.
- Event Plan initial and deferred staff rendering, staff portal, and assets gain response controls. ECC itself is untouched on Staffing branch; both full/light ECC still consume the shared snapshot.
- New classes: `BVMGR_Staffing_Failure`; `BVMGR_Staffing_Transaction_DB extends wpdb` (connection adoption, no reconnect/replay, query and DML failure conversion, transaction/DDL/engine guards).

### includes/core/staffing-lifecycle.php
- `bvmgr_staffing_transaction_active`
- `bvmgr_staffing_lock_name`
- `bvmgr_staffing_transaction_tables`
- `bvmgr_staffing_require_transaction_schema`
- `bvmgr_staffing_atomic`
- `bvmgr_staffing_defer_event_saved`
- `bvmgr_staffing_lifecycle_row`
- `bvmgr_staffing_lifecycle_window`
- `bvmgr_staffing_assignment_overlaps`
- `bvmgr_staffing_lifecycle_dirty`
- `bvmgr_staffing_lifecycle_record`
- `bvmgr_staffing_transition_assignment`
- `bvmgr_staffing_matrix_proposals`
- `bvmgr_staffing_sync_lifecycle_window`
- `bvmgr_staffing_event_overlap_warnings`
- `bvmgr_staffing_guard_time_metadata`
- `bvmgr_staffing_guard_time_add`
- `bvmgr_staffing_guard_time_delete`
- `bvmgr_staffing_guard_time_mutation`
- `bvmgr_staffing_guard_time_update_by_mid`
- `bvmgr_staffing_guard_time_delete_by_mid`

### includes/core/staffing-lifecycle-ui.php
- `bvmgr_staffing_status_label`
- `bvmgr_staffing_lifecycle_nonce`
- `bvmgr_staffing_lifecycle_message`
- `bvmgr_staffing_handle_lifecycle_request`
- `bvmgr_staffing_lifecycle_public_result`
- `bvmgr_staffing_lifecycle_ajax`
- `bvmgr_staffing_render_lifecycle_controls`
- `bvmgr_staffing_lifecycle_enqueue_assets`

### includes/db/staffing-lifecycle.php
- `bvmgr_staffing_migrate_lifecycle`
- `bvmgr_staffing_lifecycle_preflight`

## Lifecycle contract

Proposed → Confirmed by operator or owning staff; Proposed → Declined by owning staff; Proposed → Canceled by operator; Confirmed → Canceled by operator with reason; Declined/Canceled → Proposed by operator with deliberate reason. Past cancellations require reason. Unknown legacy states are preserved and cannot be transitioned. No Completed interpretation introduced. Matrix creates proposals only, preserves existing terminal rows, rejects implicit active removals and multiple active duplicate pairs. Eligibility/current plan ownership/capability and window checks happen within transaction.

Inputs to canonical transition include `context`, canonical `event_plan_id`, expected `revision`, unique 16–64 alphanumeric/hyphen `operation_id`, reason. Actor derives from current user. Same logical operation retries return an authorized no-op and existing audit; a reused ID with different action/actor/context fails. New operation with stale revision fails.

Single site-prefix/database advisory GET_LOCK (5-second timeout), READ COMMITTED, stock wpdb only, exact InnoDB/schema validation, shared adopted connection, START TRANSACTION → state+revision+audit+dirty rollups → COMMIT. All cooperating writers share this lock, including metadata/time/reschedule. External transactions reject without implicit commit. Commit acknowledgment loss produces `commit_outcome_unknown`, not a false rollback claim. No automatic deadlock/reconnect replay; explicit same-operation retry. Hook `vms_staffing_assignment_transitioned` and saved-event hook fire after commit; subscribers cannot undo state. No delivery/outbox implementation.

Proposed overlaps warn; Confirmed overlaps block, including same-event different-role work. Half-open adjacent shifts allowed. Canonical event/slot windows are re-resolved, not trusted denormalized assignment timestamps. Declined/Canceled/inactive slots excluded. Time edits invalidate old forms through revision increments and window audits.

AJAX authenticated operator/staff endpoints delegate to service, require POST and action/context/assignment/revision-bound nonce. Staff owns linked staff ID. Responses deliberately omit private assignment rates/notes and others’ IDs. Controls label Proposed tentative / Confirmed committed, retain terminal history, use inline reasons, and preserve unsaved editor changes by requesting reload after saved action.

## Financial integration conclusions

**Semantic integration work is required even though staffing does not edit ECC/profitability.** Shared staffing snapshot gives normalized authority/provenance, planned/assigned/proposed/confirmed counts, role assignment rows and history. Assigned means Proposed + Confirmed. Normalized inactive history suppresses legacy fallback. `bvmgr_staffing_estimate_slot_cost()` and rollup `est_labor_cost_total` remain *planned headcount* × slot/role pay × canonical duration, entirely independent of assignment statuses. They do not establish committed cost or actual labor. Existing assignment schema has `pay_type_override`, `pay_rate_override`, `actual_start_local`, `actual_end_local`; lifecycle leaves these untouched. No lifecycle-aware committed/actual labor API exists.

Recommended narrow bridge: retain slot-headcount planned labor forecast; derive tentative assignment estimate from Proposed rows and committed estimate from Confirmed rows on active normalized slots, using explicit rate precedence and canonical time; exclude terminal rows from both assignment estimates. Do not label either estimate actual incurred/paid labor. Keep actual labor unavailable absent a verified actual-work/payroll authority. Unknown rate/window/database failure must propagate unavailable, never zero. Empty confirmed assignment set can produce known zero committed estimate only after successful authoritative reads. Duplicate/unknown-state legacy evidence must produce an explicit limitation rather than double-counting or reinterpreting. Planning remains nonzero for unfilled slots and after cancellations if the slot need remains active.

`bvmgr_staffing_resolve_event_snapshot()` may recompute/persist dirty rollups; financial read path can pass `skip_rollup_recompute` and calculate current normalized basis. Slot retrieval failure currently risks empty arrays; financial bridge should check actual query error/exception before claiming known empty. Current snapshot totals alone cannot price heterogeneous per-assignment overrides; original slot/assignment records are needed.

Direct file overlaps reported by prior branch receipt are `includes/core/load.php` and `tests/p0-source-consistency-repair.php`; classify as cleanly composable/bootstrap textual only after inspecting Financial diff. Shared staffing labor use is SEMANTIC CONFLICT: the integration must explicitly separate planned/committed/unavailable actual recognition. No direct Data Tools dependency should be introduced.

## Migration review and future receipt

Explicit `bvmgr_staffing_migrate_lifecycle()` has no boot/page/activation hook and requires manage_options, stock wpdb, no owned/external transaction, advisory lock and InnoDB. Adds assignment revision BIGINT UNSIGNED NOT NULL DEFAULT 0; audit assignment_id BIGINT UNSIGNED NULL; operation_id VARCHAR(64) NULL; UNIQUE lifecycle_operation(operation_id); nonunique lifecycle_assignment(assignment_id). Validates actual field type/nullability and complete exact index shape, not only names. No status rewrite, row deletion, duplicate merge, backfill or assignment-pair unique constraint. Existing storage accepts declined. Multiple NULL operation IDs preserve historical audit.

DDL is not transactional: partial additions require inspect-and-complete-forward, not claimed automatic rollback. No engine conversion occurs. Wrong existing index/column shapes fail closed. Runtime metadata/time editing and rollup recomputation now require ready migration even before assignment lifecycle use; promotion therefore needs a controlled schema gate before enabling writes. Read-only preflight emits engines, schema readiness, duplicate pairs with active counts, orphan assignment IDs. Preflight error-handling deserves care: its final `last_error` can miss an earlier overwritten read failure; receipt runner should assert each read and expected tables.

Future controlled receipt must capture full SHOW CREATE TABLE and engines for assignments/event_slots/audit/rollups/posts/postmeta (plus reschedule-enlisted tables), row counts/content hashes and duplicates/orphans before migration, run explicit migration, rerun idempotently, compare business/history rows, verify revision default and exact indexes. Preserve history and new fields on source rollback; old implicit writer restores known gaps, so keep staffing writes disabled pending forward repair. Full DB+source rollback backups, maintenance/no concurrent writers, installed-source hash and post-migration concurrency/rollback/operator+staff/ECC financial checks are required only on future authorized promotion.

## Tests / disposable execution safety

Existing committed suites: `staffing-lifecycle/runtime.php` real SQL lifecycle/idempotence/actor/revision/atomic failures/engine and duplicate preservation; `integration.php` independent visibility, committed audit hooks, rendering agreement, nonce/ownership, lock timeout, nested COMMIT rejection, connection kill, metadata/by-mid and canonical reschedule rollback, historical/invalid contexts, DST/overnight, index conditions; `concurrency.php` real barrier workers for overlapping confirmations, pair creation, matrix races, Accept/Decline revision races; `deadlock.php` real server deadlock counter and same-operation retry; forensic entry calls real transaction/concurrency then eleven regression checks. Existing SQL-remediation suites now assert delegated writers backed by runtime behavior. Browser action coverage was performed historically, not a repeatable checked-in browser runner.

Bootstrap hard-binds exactly BVM_STAFFING_TEST_ROOT=/private/tmp/bvm-staffing-db/wordpress, DB_NAME=staffing_test, DB_HOST=localhost:/private/tmp/bvm-staffing-db/mysql.sock. Verify wp-config values and active source before boot: DB assertions occur after wp-load. BVM_STAFFING_INSTALL=1 explicitly installs old v7 schema; lifecycle migration invoked by runtime.php. Tests deliberately ALTER tables, KILL their DB connection, create posts/users and inject failures: disposable only. Existing DB fixture must not be treated as current clean migration evidence; a representative old-schema fixture is needed to prove first migration.

The old implementation report records PHP 8.3.33 / WP 7.1 / MariaDB 10.11.19, no MySQL8 coverage. Tests need isolated WC/TEC schema for canonical rescheduling. HTTP and mail must be blocked in disposable MU plugin before transport and cron disabled; MariaDB TCP disabled/private socket. Sibling source comparisons require a disposable ../../backstage-venue-manager copy; never normal live sync.

**Hazard:** official/additional compatibility wrappers install guard files into their configured WordPress source root and capture its DB state. Do NOT use normal-local source root even if the actual scenarios create a disposable DB. Set BVM_COMPAT_WP_ROOT to a full independent fixture and inspect every derived path / DB connection first. No wrapper or runtime test ran during these forensics.
