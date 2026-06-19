# CODEX HANDOFF — VMS 0.2.24.606 — Email Follow-Ups Past-Event Preview Repair

## Purpose

Repair the Email Follow-Ups Preview & Test screen so post-event feedback invitations can be previewed/tested against recently ended Event Plans. The previous selector only listed future events, which blocked the intended post-event workflow.

## Changed files

- `includes/modules/email-followups/recipients.php`
- `includes/modules/email-followups/admin-ui.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/01-project-handoff.md`
- `docs/05-revision-log.md`
- `docs/06-test-plan.md`
- `docs/test-plan-0.2.24.606-email-followups-past-event-preview.md`
- `docs/CODEX-HANDOFF-0.2.24.606.md`

## Key behavior

- Preview/Test event choices now include a one-year lookback and one-year lookahead window.
- The explicitly selected Event Plan remains in the dropdown even if it falls outside the default window.
- Event choices are sorted by nearest event date, so recent shows surface first.
- Past events and today's event receive simple labels in the selector.
- Cron/automatic send behavior was not broadened; this pass targets admin preview/test selection only.

## Required checks

1. Confirm version markers report `0.2.24.606`.
2. Open Email Follow-Ups → Preview & Test.
3. Confirm a recently ended Event Plan appears in the Event dropdown.
4. Select Post-Event Thank You and preview the past event.
5. Confirm `{feedback_url}` resolves and the test email link opens the selected event's private feedback survey.
6. Confirm manual sends remain guarded by the confirmation checkbox.

🚨 If Codex makes any repair after testing, update plugin header version, `VMS_VERSION`, `vms-build.txt`, revision log, handoff, test-plan/Codex handoff docs, and package filename before returning a replacement zip.
