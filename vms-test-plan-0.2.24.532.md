# VMS Test Plan — 0.2.24.532

## Scope
Validate the cancelled-plan **Run Live Refunds Now** path after rerouting it through the canonical Event Plan edit screen.

## Install
1. Install `vms-0.2.24.532-live-refund-edit-screen-routing.zip`.
2. Open a previously cancelled Event Plan that already shows a Cancellation Job with refund candidates.

## Checks
1. Click **Run Live Refunds Now**.
2. Confirm the browser confirmation prompt appears.
3. Confirm the request does **not** land on a blank `admin-post.php` screen.
4. Confirm you return to the same Event Plan edit screen near the **Cancellation Job** panel.
5. Confirm unrelated save notices do **not** appear (`Post updated`, staffing validation, etc.).
6. Confirm a refund result notice appears (success/warning/error).
7. Open at least one known eligible Woo order and verify whether a real refund note/record was created.
8. Confirm already-refunded lines are not refunded twice on rerun.
9. Confirm mixed/unsafe orders still remain queued/manual-review instead of being blindly refunded.

## Files touched
- `includes/cpt/event-plans.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `docs/05-revision-log.md`
- `docs/07-rollback-notes.md`
- `vms-build.txt`
