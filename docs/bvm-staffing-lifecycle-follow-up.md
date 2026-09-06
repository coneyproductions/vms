# BVM staffing assignment lifecycle follow-up

Status: specification only. This task does not add lifecycle UI, schema, notifications, or database migration.

## Smallest coherent workflow

1. An Event Plan operator with `edit_post` permission proposes a staff member for a normalized staffing slot. A proposal is tentative coverage and remains visible in the Event Plan, Command Center, and that staff member's portal.
2. The assigned staff member may accept or decline their own proposal in the staff portal. Accept changes `proposed` to `confirmed`; decline changes it to `canceled` with a decline reason. A staff member must never be able to change another person's assignment.
3. An authorized Event Plan operator may confirm or cancel an assignment. Operator confirmation is useful for assignments accepted outside the portal. Cancellation should require a short reason once the assignment was confirmed.
4. A canceled/declined association remains historical. Reassigning the same staff member may revive the canonical row as `proposed`; it must not insert a second active `(slot_id, staff_id)` association.

## Permissions and request boundaries

- Operator actions should use the existing Event Plan `edit_post` capability boundary, an assignment-specific nonce, and server-side validation that the slot belongs to that Event Plan.
- Staff portal actions should require the authenticated user-to-staff link, assignment ownership, an assignment-specific nonce, and an active slot. Portal actions should be limited to `proposed -> confirmed` and `proposed -> canceled`.
- Administrative override of a confirmed assignment should remain operator-only. A future dedicated staffing capability may replace `edit_post`, but introducing it is a separate authorization/migration decision.

## Audit requirements

Every lifecycle transition should append the existing staffing audit record with assignment ID, slot ID, staff ID, prior status, resulting status, actor user ID, actor type (`operator` or `staff`), UTC timestamp, and optional reason. The audit record should not store secrets or free-form portal request data. No-op saves and unrelated Event Plan saves must not emit a lifecycle transition.

## Coverage and overlap semantics

- `proposed` and `confirmed` both count as assigned operational coverage so planning screens do not show an already-contacted seat as wholly empty.
- The UI should display proposed and confirmed subtotals beside assigned coverage. A role with only proposed staff is tentatively covered, not committed/ready.
- `confirmed` assignments remain hard overlap conflicts because the person has committed to both shifts.
- Proposed overlaps should become a separate soft warning before confirmation. Confirmation should recheck availability and hard overlaps atomically and require an operator override reason if policy permits an overlap.
- `canceled`/declined assignments never count as coverage or overlap.

This preserves the current useful planning behavior while removing the ambiguity that previously arose when a single filled count hid lifecycle state. A later readiness policy may require confirmed coverage by a configurable deadline; that policy should consume the shared snapshot rather than redefining assignment truth in a UI.
