# VMS 0.2.24.571 Test Plan - Cancellation Notification Polish

🚨 **Repair/versioning protocol:** If Codex makes even a minimal code repair while testing this build, Codex must update all relevant version markers and packaging docs in the same pass before returning a replacement zip. At minimum this includes the plugin header version, `VMS_VERSION`, `vms-build.txt`, changelog/revision notes, this test plan or follow-up test notes, Codex handoff notes, and the package filename. Do not return a modified build with stale versioning/docs.

## Build Under Test

- Package: `vms-0.2.24.571-cancellation-notification-polish.zip`
- Baseline: `0.2.24.570-vendor-portal-pattern-preference-fix`
- Scope: public calendar cancellation ribbon removal plus Event Plan cancellation notification messaging.

## Install / Version Checks

1. Install/replace VMS Core with `vms-0.2.24.571-cancellation-notification-polish.zip`.
2. Confirm WordPress shows VMS version `0.2.24.571`.
3. Confirm `vms/vms-build.txt` reads `0.2.24.571`.
4. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.571`.

## Syntax / Smoke Checks

1. Run PHP lint on all VMS PHP files.
2. Activate VMS Core.
3. Open the Event Plan editor for an existing published Event Plan.
4. Confirm no fatal errors or editor save warnings unrelated to the active test.

## Public Calendar Ribbon Removal

1. Find or create a month with multiple cancelled Event Plans.
2. Open the public VMS venue calendar / month calendar view.
3. Confirm cancelled and rescheduled entries still appear in the calendar when expected.
4. Confirm event images in this calendar view no longer show the diagonal/surface image ribbon reading `Cancelled`, `Rescheduled`, or `Cancelled/Rescheduled`.
5. Confirm cancelled/rescheduled entries still retain appropriate non-ribbon state behavior, including cancelled styling/classes and safe CTA behavior such as `View Details` instead of purchase-oriented copy.
6. Confirm single-event cancelled/rescheduled banners and non-calendar public surfaces were not unintentionally removed unless intentionally covered by a separate pass.

## Cancellation Editor UI

1. Open an Event Plan that is not cancelled.
2. In the Cancellation section, confirm these fields are present:
   - Cancellation policy
   - Cancellation reason
   - Cancellation note
   - Primary vendor email message
3. Confirm the Cancellation note helper copy explains it is internal and not included in vendor/staff emails.
4. Enter a custom Primary vendor email message and save the Event Plan without cancelling.
5. Reopen the Event Plan and confirm the custom message persists.

## Status-Only Cancellation Notifications

1. Configure an Event Plan with:
   - A primary vendor with a valid email address.
   - At least one assigned staff member with a valid email address.
   - Cancellation policy set to `Status only`.
   - A custom Primary vendor email message.
2. Mark the Event Plan cancelled.
3. Confirm a cancellation job is created and auto-runs.
4. Confirm the provider sales stop, refund discovery, and refund execution steps are skipped by policy.
5. Confirm the Notifications step runs instead of being skipped.
6. Confirm the primary vendor receives a cancellation email containing the custom Primary vendor email message.
7. Confirm the staff recipient receives the standard staff cancellation notice and does not receive the primary vendor custom message.
8. Confirm the outgoing email does not expose internal cancellation policy, internal cancellation note, or internal reason note text.
9. Confirm the Cancellation Job panel shows notification recipient/sent counts.

## Refund-Capable Policy Regression Check

1. Repeat the cancellation notification check with a refund-capable cancellation policy on a safe/local fixture.
2. Confirm refund discovery/execution behavior still follows existing guardrails.
3. Confirm notifications still run after the upstream steps complete, fail, or block according to existing dependency rules.
4. Confirm retrying failed notification steps does not duplicate emails already logged as sent in the same cancellation job.

## Rescheduled Draft Regression Check

1. Cancel an Event Plan while entering a replacement date and a Primary vendor email message.
2. Confirm the linked replacement Draft Event Plan is created.
3. Open the replacement Draft and confirm cancellation state/job metadata and the Primary vendor cancellation message were cleared from the replacement draft.

## Notes

- This pass intentionally removes image ribbons only from the VMS public venue calendar view.
- The primary vendor custom message is event-specific and stored with the Event Plan.
- Staff, secondary vendors, and supporting/lineup vendors receive the standard assigned-person cancellation message.
