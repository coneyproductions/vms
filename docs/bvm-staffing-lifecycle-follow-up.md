# BVM staffing assignment lifecycle specification

Date: 2026-09-06. Authority: accepted checkpoint `1e53fa3e4afc4301ff9c5b912df1a4bfc83f7444`.

**Specification only; runtime implementation stopped at the requested database-locking gate.**
See [forensic report and validation](bvm-staffing-lifecycle-checkpoint-report.md).
This supersedes the earlier suggestion to store staff declines as canceled: the requested lifecycle requires a distinct `declined` value. Existing canceled rows must not be relabeled by guessing intent.

## State machine

| Current state | Action | Result | Actor |
| --- | --- | --- | --- |
| No canonical association | Propose | proposed | Operator |
| proposed | Confirm / Accept | confirmed | Operator / owning staff member |
| proposed | Decline | declined | Owning staff member |
| proposed | Cancel | canceled | Operator |
| confirmed | Cancel | canceled | Operator |
| declined | Repropose | proposed | Operator, deliberate action |
| canceled | Repropose | proposed | Operator, deliberate action |

No other transition is allowed. No Completed or Checked In state is added. Event completion, attendance, and actual labor are separate concerns. `checked_in` is already recognized by one staff-task consumer, but no assignment writer for it was found; unknown stored states must fail closed pending review, not be silently mapped to proposed.

Staff cannot cancel a commitment, repropose, or confirm another person's assignment. Operators can record an off-portal acceptance by confirming; they cannot impersonate a staff decline. Require a bounded, sanitized reason for canceling a confirmed assignment and for reproposal; make the reason optional for canceling a proposal. Keep prior audit entries when reviving the canonical association.

## Request and permission contract

Use one service for every assignment mutation and one authenticated POST endpoint, exposed through the established admin-post or authenticated AJAX conventions. Do not register a nopriv route. UI controls must not mutate on GET. Creation through the proposal matrix retains its plan/slot-scoped nonce because no assignment ID exists yet.

- Operator: authenticated current user, `current_user_can('edit_post', event_plan_id)`, and a valid Event Plan post. Resolve assignment -> slot -> plan from the database and verify the submitted plan agrees; never authorize using only a submitted plan ID.
- Staff: authenticated current user, server-resolved `_vms_staff_id`, a valid `vms_staff` post, exact assignment ownership, and only Accept/Decline. Ignore a client-supplied staff ID or actor context. Operator context is selected by the endpoint and checked, not granted by an arbitrary form field.
- Both: assignment/action/revision-specific nonce, strict scalar positive identifiers, allowed action, server-side ownership/capability checks, and fresh locked state before writing. Invalid/missing/cross-assignment nonces fail without writes or events. Nonces are CSRF protection, not one-time request IDs.
- Staff action eligibility: active slot; Event Plan status in the existing portal list (`ready`, `published`, `tentative`, `confirmed`); valid event date no earlier than the current local day; valid resolved UTC shift with end after start and end later than now. Fail safely for missing/expired context. These are both render-time and mutation-time checks. Allow a shift already underway until its end. Existing portal date filtering can hide an overnight shift after its event date; preserve that date boundary initially and document it in UI acceptance.
- Operator confirm/repropose: active slot, valid future-ending shift, existing staff/role eligibility and hard qualification checks. No past-event confirmation or reproposal. Historical cancellation remains operator-only with a reason and audit; do not extend it into attendance/pay correction.
- Missing record: generic unavailable result. Forbidden: 403. Invalid action/state/window: 400/409 as appropriate. Stale revision, overlap, or lock contention: 409 with refresh/retry guidance. Database/audit failure: error and rollback; never a success notice.

Use a monotonically increasing assignment revision as the recommended stale-request token. Request includes expected state/revision and a unique operation ID. Repeating the same successful operation returns its recorded result with no new transition, audit, or event. A different stale operation fails. A prior Accept request must not accept a newly reproposed assignment after a proposed -> declined -> proposed cycle. Same-state requests that are not known retries return an explicit no-change result only after full authorization; they never rewrite audit timestamps.

## Coverage contract

Preserve the accepted shared resolver as the authority. Proposed is tentative assigned coverage; Confirmed is committed assigned coverage. `assigned = proposed + confirmed`; the compatibility `filled` count retains this assigned meaning. Declined and Canceled contribute zero to coverage and conflicts. Keep planned headcount, required-now headcount, open planned positions, open required-now positions, per-role assignments, and unique people distinct. Do not derive event totals by counting portal event cards or crew groups.

Display “Assigned X / Required Y; Tentative P; Confirmed C; Open O.” Tentative coverage must not be described as committed. Preserve existing operational readiness calculations; introducing a confirmed-only readiness deadline is deferred. Legacy-only records retain `legacy_status_unknown_headcount` and legacy provenance, without fabricated normalized IDs or Accept buttons. Normalized rows, including inactive-only history, suppress legacy fallback. Migration to normalized rows requires deliberate operator reconciliation.

The resolver should expose all historical rows separately from active counts, and shared status/overlap helpers should drive Event Plan, ECC, portal, crew lists, and staff tasks. Current consumers must not independently invent a meaning for `declined`, `canceled`, or `checked_in`.

## Operator UI

Keep the checkbox matrix for new bulk proposals. Add an assignment list inside each role card, with staff name, role/shift, text state badge (color is supplementary), and explicit Confirm, Cancel, or Repropose buttons. Show declined/canceled history separately, with no automatic revival when a checkbox is present in a stale form.

An unrelated matrix save must preserve every existing lifecycle state. Checking a never-assigned person creates a proposal. A historical association directs the operator to Repropose; it cannot be revived implicitly. Removing active staff or a whole role requires explicit cancellation intent and the current revision, including a reason for confirmed rows. An old checked/unchecked membership snapshot cannot override a subsequent acceptance or decline. Confirmed cancellation cannot be concealed inside a headcount or template save.

The editor's deferred/AJAX renderer and initial partial must show the same controls and counts. Avoid nested forms in the WordPress post editor; use authenticated action buttons with equivalent accessible status/error feedback. Refresh the role and summary from the shared resolver after success and preserve unrelated unsaved editor fields. Stale actions prompt a refresh without resubmitting a matrix snapshot.

## Staff UI

Show only the authenticated staff member's actionable assignments, with event title, local date/time, role, and state. Proposed cards offer Accept and Decline. Confirmed cards say Confirmed and have no self-cancel action. Declined/Canceled appear as read-only history and do not become “next shift,” working dates, or assigned totals. Existing crew visibility remains as established, but another person's row never receives an actionable nonce. Do not widen event-document permissions.

A stale form must display an unavailable/changed message and leave the latest lifecycle state intact. A missing or expired window disables response controls and the service independently rejects the request. No scheduling, availability rewrite, attendance collection, or pay editing is added.

## Required atomic persistence and concurrency gate

The accepted source has neither staffing transactions nor a lock shared by all assignment writers. Per-assignment compare-and-set alone cannot prevent two overlapping rows for the same staff member from being confirmed. Two requests can both observe no committed overlap, then each successfully update a different proposed row. A lock scoped only to the Event Plan fails across plans. A PHP static flag, transient, or ordinary option check is not sufficient.

Recommended design to validate before implementation:

1. Verify transactional storage for every table participating in the atomic boundary. Use InnoDB for assignments, slots, staffing audit, and the chosen stable lock rows; rollup dirty state must either participate or have a reliable post-commit invalidation mechanism. Current migration SQL does not specify ENGINE. Do not assume deployed table engines from the source or issue silent conversions.
2. Serialize all staffing operations for a plan using a stable parent Event Plan row, then all affected staff using their stable `vms_staff` post rows in ascending staff-ID order. Lock the parent before discovering slot membership; all matrix/template writers must follow that order. Require existing, valid lock rows and transactional `posts`, or use a dedicated lock table if that prerequisite cannot be established. Never discover an empty assignment set and assume it is locked.
3. Re-read slot, assignment, revision, staff linkage, eligibility, and resolved UTC shift under those locks. Acquire fresh locking/current reads for conflict checks rather than an older transaction snapshot. Cross-plan conflict reads must avoid introducing an inverted parent-lock order. Specify retry/timeout/deadlock behavior and transaction ownership before coding, including invocation inside existing Event Plan/reschedule transactions.
4. Check every other active assignment for that staff member using half-open intervals: `other_start < target_end AND other_end > target_start`. Exclude the target assignment, not the entire target event. Different roles in the same event can overlap. Reject another confirmed overlap when confirming; proposed overlaps are returned as soft warnings. Inactive slots and declined/canceled assignments do not conflict. Missing or invalid target windows block confirmation instead of skipping checks.
5. Persist the transition using expected state/revision, append the existing staffing audit entry, and mark affected event rollups dirty in the same atomic boundary. Check every result and roll back on any failure. Invalidate all impacted event snapshots, not just the clicked event, because cross-event warnings/conflicts change. Rebuild cached rollups after commit from canonical rows; do not send messages during the transaction.
6. Serialize proposal creation and canonical-row selection on the same plan/staff locks. Existing duplicate groups require deterministic reconciliation and auditable handling; never drop historical rows merely to make a unique index succeed. Existing confirmed duplicates/conflicts require operator review. No new duplicate pair may be inserted by concurrent matrix saves.
7. Apply the same protocol to matrix saves, template replacement/cancellation, and shift-timestamp synchronization. Shift or event-time changes for a confirmed assignment must recheck hard conflicts before becoming effective. Locking only the new Accept/Confirm endpoint leaves a correctness hole.

### Schema decisions still unresolved

`status VARCHAR(20)` already accommodates `declined`; no ENUM alteration or data relabeling is needed. Existing audit JSON fields accommodate lifecycle details. No new audit table is justified.

Recommended additive change: `revision BIGINT UNSIGNED NOT NULL DEFAULT 0` on assignments, incremented on status and relevant shift-context changes; persist operation IDs/results in the existing audit JSON or design an indexed idempotency field if needed. `updated_at` is only second precision and is not a safe revision across repeated lifecycle cycles. Decide migration/version ownership and verification before adding the field. A latest-audit-ID token is a possible alternative, but requires reliable atomic audit and indexed assignment lookup, neither currently provided as a repository contract.

Engine conversion is conditional on actual storage engines, not proven necessary on normal Local (which was not queried). A new lock table is conditional on the stable parent-row approach being unsuitable. An unconditional unique `(slot_id, staff_id)` index conflicts with retained duplicate history; do not add it without a non-destructive migration policy. A unique index alone would still not prevent overlap between different slots.

These requirements trigger the task's instruction to stop and specify true transactional locking/schema requirements. No locking, schema, migration, endpoint, or UI implementation is claimed by this specification commit.

## Audit and future internal events

Reuse `vms_staffing_audit_log`: action `assignment_transition`; Event Plan and actor columns; UTC `created_at`; before/after JSON containing assignment ID, slot ID, staff ID, old/new status, old/new revision, operation ID, actor context, bounded reason, and controlled source (`matrix_proposal`, `operator_confirm`, `staff_accept`, `staff_decline`, `operator_cancel`, `operator_reproposal`, or an explicit template/system cancellation source). Proposal creation records a null prior state. Every changed row in a batch gets a transition record, in addition to any retained batch summary. Failed/no-op requests emit none.

Change the audit write contract to surface failures and participate in the mutation transaction. Do not reuse the current void helper as proof of persistence. A failed audit insert must prevent the assignment change from committing.

After commit, a proposed versioned internal action `vms_staffing_assignment_transitioned` may carry a schema-version-1 payload with audit ID, identifiers, states, revision, actor context, timestamp, and source. Audit ID can be the event identity. Creation to proposed, confirmation, decline, cancellation, and reproposal are distinguishable in that one contract. Emit once for a new successful operation and never on rollback/no-op. This action is specified, not registered by this work.

Do not connect it to existing qualification email, staff-task reminders, or cancellation delivery. Delivery needs a separate subscriber policy, consent/channel configuration, retry/idempotency behavior, and an outbox if durable delivery becomes required. A post-commit PHP action alone is not a durable notification queue. No real notification is sent or scheduled in this task.

## Financial and compatibility boundaries

The accepted labor estimator prices planned slot headcount/rate/hours; it does not price only confirmed people. Profitability consumes that shared rollup. Preserve planned labor estimates during a decline/cancellation. A future committed/actual labor metric must be separate and coordinated with Financial Authority work, not introduced by changing `filled` or silently lowering the planned budget.

Cross-event overlap policy will now include same-event distinct roles, unlike the current rollup SQL which excludes the entire event. Cover this explicit change in tests and UI copy. Preserve qualification, availability, task/document permissions, legacy provenance, rollup freshness, and cancellation notification recipient behavior. Do not use legacy fallback to resurrect an inactive normalized assignment.

## Acceptance required before enabling lifecycle controls

Disposable WordPress/MySQL or MariaDB tests must exercise real requests and two independently coordinated database connections. In-memory characterization is insufficient to prove transactional locking.

- Creation, operator confirmation, staff acceptance/decline, operator cancellation, deliberate reproposal, every invalid transition, and no Completed state.
- Cross-user/cross-plan ownership, missing/wrong/cross-action nonces, unlinked staff, invalid role, capability denial, past/inactive/missing-window behavior.
- Same request retry, competing Accept/Decline, stale canceled/reproposed cycles, distinct operation IDs, revision mismatch, rollback on every write/audit failure.
- Proposed soft warning; confirmed hard rejection; proposed vs confirmed; same-event distinct roles; adjacent windows; overnight/DST windows; inactive/declined/canceled exclusion.
- Two concurrent confirmations for one staff member; concurrent creation of one pair; matrix vs Accept/Decline/Cancel; template/shift/event-time changes vs confirmation; lock timeout, rollback, and deadlock retry.
- Per-row audit completeness and UTC actor provenance; no audit/event duplication; zero delivery; no event before commit; affected-event rollup invalidation.
- Matrix preservation of all lifecycle states, duplicate reconciliation, normalized-only and inactive-normalized-only authority, legacy-only provenance, and unchanged planned labor cost semantics.
- Exact shared Event Plan role/summary, ECC, and portal active/history totals; both Event Plan render paths; staff tasks and crew/document permissions.
- Accepted P0, staffing repository/final/matrix, Event Plan staff eligibility/rendering, portal, cancellation recipient, and applicable contained ecosystem suites.
