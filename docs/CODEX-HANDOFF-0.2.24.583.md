# CODEX HANDOFF — VMS 0.2.24.583

## Focus
Add the first Email Follow-Ups foundation inside VMS so ticket buyers can receive event-aware reminders and follow-ups without creating a separate plugin yet.

## What changed
- New core module: `includes/modules/email-followups/`.
- New admin screen: **VMS > Email Follow-Ups**.
- New Marketing & Social hub card for Email Follow-Ups.
- New stylesheet: `assets/css/vms-email-followups-admin.css`.
- MailPoet API detection and optional subscriber/list/tag sync.
- Default templates for:
  - Know Before You Go
  - Day-of Reminder
  - Post-Event Thank You
  - Weather / Event Update
- Preview & Test screen that resolves Event Plan context, eligible Woo/TEC ticket-buyer recipients, rendered copy, and scheduled timing.
- Safe test-send tool for admin addresses.
- Guarded manual send to eligible recipients with confirmation checkbox.
- Duplicate-send guard and lightweight option-based logs.
- Hourly scheduler installed but automatic scheduled sends remain off by default.
- Guided tour registration for the new module.

## Highest-risk areas to test
1. Recipient discovery must match the correct Event Plan and not include unrelated events.
2. Refunded/cancelled/zero-net ticket rows must not be counted as eligible recipients.
3. Manual recipient send must not run unless the confirmation checkbox is checked.
4. Duplicate protection must prevent repeat sends for the same event/template/recipient.
5. MailPoet active/inactive states must not fatal.
6. Automatic scheduled sends must remain off by default.
7. Existing Marketing & Social, Social Sharing, staffing-template, and ticket-image-repair screens must still load.

## Important operational note
🚨 Do not enable automatic scheduled sends on production until staging confirms recipient discovery, token rendering, manual test sends, and duplicate protection. The module is intentionally useful in preview/test/manual mode first.

## Files changed / added
- `includes/modules/load.php`
- `includes/modules/email-followups/email-followups.php`
- `includes/modules/email-followups/settings.php`
- `includes/modules/email-followups/templates.php`
- `includes/modules/email-followups/mailpoet.php`
- `includes/modules/email-followups/logs.php`
- `includes/modules/email-followups/recipients.php`
- `includes/modules/email-followups/sender.php`
- `includes/modules/email-followups/scheduler.php`
- `includes/modules/email-followups/admin-ui.php`
- `assets/css/vms-email-followups-admin.css`
- `includes/admin/menu.php`
- `includes/admin-ui/nav.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/01-project-handoff.md`
- `docs/05-revision-log.md`
- `docs/06-test-plan.md`
- `docs/test-plan-0.2.24.583-email-followups-foundation.md`
- `vms-test-plan-0.2.24.583.md`
