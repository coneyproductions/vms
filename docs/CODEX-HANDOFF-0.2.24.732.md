# Codex Handoff — VMS 0.2.24.732

## What changed

- Fixed the duplicate VMS admin navigation on `VMS -> Marketing & Social -> Email Follow-Ups`.
- Registered `vms-email-followups` as a shared shell page so the global `all_admin_notices` nav does not render above the page shell anymore.
- Preserved the Marketing & Social primary active state and the Email Follow-Ups secondary active pill.

## Intentionally not changed

- No public Event Feedback changes.
- No Email Follow-Ups sending or recipient logic changes.
- No template, log, MailPoet sync, or scheduler changes.
- No deployment or staging push was performed.

## Local verification performed

- `php -l` passed for `vms/includes/modules/email-followups/admin-ui.php`.
- Local WordPress runtime render checks confirmed:
  - Email Follow-Ups `overview`, `templates`, `preview`, and `logs` each render exactly one VMS primary row and one Marketing & Social secondary row.
  - Social Sharing also renders a single VMS nav stack.
  - Meta Ads Builder, Promotable Events, Performance, Logs, and Settings each render a single VMS nav stack in the Marketing & Social cluster.
- Direct HTML inspection of the Email Follow-Ups nav confirmed the `Email Follow-Ups` secondary link remains current.

## Packaging note

- Package name: `vms-0.2.24.732-email-followups-admin-nav-dedupe.zip`
