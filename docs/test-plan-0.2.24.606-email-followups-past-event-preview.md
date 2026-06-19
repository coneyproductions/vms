# VMS Test Plan — 0.2.24.606 — Email Follow-Ups Past-Event Preview Repair

🚨 Staging/live verification recommended before sending feedback invitations to real customers. This pass changes the admin event selector used by Email Follow-Ups preview/test/manual-send tooling.

## Version checks

1. Confirm the plugin header reports `0.2.24.606`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.606`.
3. Confirm `vms-build.txt` contains `0.2.24.606`.

## Preview & Test selector

1. Open `wp-admin/admin.php?page=vms-email-followups&tab=preview`.
2. Confirm the Event dropdown includes the most recent past Event Plan, not only upcoming events.
3. Confirm past events display with a `past event` label.
4. Confirm today's event, if one exists, displays with a `today` label.
5. Confirm upcoming events still appear and can be selected.

## Feedback workflow regression

1. Select a recently ended Event Plan.
2. Select the `Post-Event Thank You` template.
3. Click **Preview**.
4. Confirm the rendered email references the selected past Event Plan.
5. Confirm the Recipient Preview shows the eligible recipient count for that past Event Plan.
6. Confirm the feedback link reports as included when the Event Feedback module is active.
7. Send a test email to the admin address and confirm the feedback button/link opens the selected event's private feedback survey.

## Safety checks

1. Confirm **Send to Eligible Recipients** still requires the confirmation checkbox.
2. Confirm automatic scheduled sends remain disabled unless explicitly enabled in Email Follow-Ups settings.
3. Confirm selecting an upcoming event with a pre-event template still works as before.

## Rollback point

If the selector fails to show past events or preview/test sends target the wrong Event Plan, roll back to `0.2.24.605` and document the selected Event Plan ID, event date, template key, and URL query string used during testing.
