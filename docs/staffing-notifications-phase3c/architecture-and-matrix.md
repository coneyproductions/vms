# Phase 3C existing-path forensics and delivery decisions

Accepted input: `0c4c99dd3854c53af6e648ddaba91779e8826124`. New isolated authority: `work/staffing-notification-delivery-20260908`.

## Existing path

- Event Plan Staff matrix saves call `bvmgr_staffing_save_event_roles_matrix` in core/staffing.php, then `bvmgr_staffing_matrix_proposals` inside the shared atomic boundary. Existing assignments cannot be revived by stale matrix checkbox saves.
- Existing operator/staff buttons use assets/js/vms-staffing-lifecycle.js, authenticated AJAX, `bvmgr_staffing_handle_lifecycle_request`, nonce bound to context/assignment/target/revision, and operation ID. The transition service verifies actor, plan, staff, role eligibility, revision, overlap, expiration and allowed transition.
- `bvmgr_staffing_atomic` owns the connection-scoped staffing lock and strict no-reconnect transaction adapter. Assignment, audit, rollup invalidation and reschedule writes commit together. On failure, buffered events are cleared. On success it releases the staffing lock, restores wpdb, clears caches and emits `vms_staffing_assignment_transitioned` / `vms_staffing_event_saved`.
- Delivery stopped exactly here. The accepted comment explicitly said delivery was not implemented; there was no assignment-transition notification subscriber. The lifecycle implementation report explicitly records that omission.
- Existing `vms_staffing_event_saved` also feeds Staff Tasks assignment resolution. Staff Tasks has separate notification events/templates, preferences and digest delivery; it is not a staffing assignment proposal outbox. Staff qualification review/submission and cancellation mail are also separate existing paths and remain untouched.
- Core notifications already supplies `vms_notify_log`, `bvmgr_notify_insert_log`, `bvmgr_notify_provider_core_email_send`, template-filter infrastructure and the hourly `vms_notify_digest_tick_cron`. Phase 3B already supplies unambiguous staff/contact identity resolution. There was no staffing lifecycle delivery queue, retry, or recipient/template subscriber to reconnect.

## Transition/notification matrix

| Committed fact | Recipient | Communication |
|---|---|---|
| New proposal / canceled or declined reproposal | Canonical assigned person | Proposed/tentative role, shift and authenticated response landing |
| Proposal confirmed by operator or staff | Same canonical person | Confirmation receipt; current assignment landing |
| Proposal declined by staff | Same canonical person | Decline receipt; no resurrection action |
| Proposed or Confirmed assignment canceled | Same canonical person | Cancellation receipt; no stale response action |
| Reassignment | Former person for committed cancellation; new person for committed proposal | Separate authoritative facts; old retries suppressed after superseding changes |
| Audited date/time/shift-window revision | Person on current matching assignment revision | Status plus shift-time update; current authoritative time/role/context |
| Identical save / duplicate HTTP operation / failed transition / rolled-back reschedule | None | No new logical delivery |
| Cosmetic title, venue text, pay, staffing notes, headcount-only changes | None | No new lifecycle communication is inferred from generic metadata writes; current event/venue is included with the next qualifying committed change |
| Previously captured historical audit before explicit cutover | None | Not backfilled |

No manager/operator email copies are configured for the lifecycle path; operators receive status/history in the Event Plan. This avoids substituting generic review/admin recipients or creating an unrequested copy policy. Multiple distinct jobs remain distinct notification facts, even when they share a mailbox; each logical audit notification has one canonical person/address and cannot duplicate because of duplicated identity lookup rows, repeated requests or workers. Ambiguous reverse user identity and malformed/missing contact are visible failures. Existing email-channel preferences are respected.

## Completion architecture

The committed staffing audit is the recovery authority. One explicit administrator option stores the maximum audit ID at enablement; no old staffing messages are emailed. New `vms_staffing_committed` notification processing runs after successful COMMIT and lock release, not inside the transaction. Existing transaction validation and writes remain unchanged. Audited window changes, including reschedules, are recovered by the same path.

The existing notification ledger stores one stable `staffing_audit_<ID>` identity with queued work and append-only attempts/results. A dedicated delivery advisory lock serializes post-commit and hourly workers. The accepted strict connection adapter is reused without starting a transaction; it prevents silent reconnect from losing the delivery lock and replaying delivery queries. Queue insertion failure leaves the committed audit eligible for recovery. Recovery runs immediately after successful staffing commands and on the existing hourly notification cron event, independently of the optional Staff Tasks digest switch. External cron need only continue executing the existing WordPress cron schedule.

Before transport, the worker verifies current staff, assignment revision/status and event ownership. Superseded work is skipped, not rewritten as misleading current-state mail. Recipient identity is pinned once known; unresolved contact may be corrected for that same person. A durable attempt marker precedes mail. `false` transport results can retry after 60 seconds, up to three automatic attempts; an authorized operator can explicitly retry that same record after review/correction. Previously successful or superseded records cannot resend. Crash/exception or missing terminal persistence leaves an uncertain marker that neither automatic nor manual retry resends: inbox/provider investigation is required. Exactly-once inbox delivery is not claimed across an unknowable external transport outcome.

Email links only display current authenticated assignment controls. Existing POST-only AJAX responses, revision-bound nonces, user ownership, operation replay and concurrency protection remain authoritative. No state-changing GET link or new bearer action token exists.

Source scope: two added notification files; core/load.php loads them; core/staffing-lifecycle.php adds only the guarded post-commit event and updates its obsolete comment. No schema, new cron event, mail configuration, staffing redesign, Staff Tasks stabilization or Google Calendar integration.
