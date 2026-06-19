# VMS Test Plan — 0.2.24.608 — Email Follow-Ups Template Timing + Signatures

🚨 **Staging/Codex testing recommended before production automatic sends.** This pass changes template configuration and scheduler timing behavior. Keep automatic scheduled sends off until preview/test results are confirmed.

## Version checks

1. Confirm plugin header reports `0.2.24.608`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.608`.
3. Confirm `vms-build.txt` contains `0.2.24.608`.

## Admin smoke checks

1. Open **VMS → Marketing & Social → Email Follow-Ups**.
2. Confirm the Overview tab loads with no fatal error or duplicate settings reset behavior.
3. Confirm the Overview tab still has the global safety/delivery fields:
   - Enable module
   - Enable automatic scheduled sends
   - MailPoet sync/list
   - From/reply/test recipient
   - Due-window hours
   - Default signature
4. Confirm the old global reminder/post-event send-hour fields no longer dominate the Overview UI.

## Signature checks

1. Confirm the Default signature field is populated for a previously configured site, or can be saved manually.
2. Open the Templates tab.
3. Confirm `{signature}` appears in the token list.
4. Confirm built-in template bodies include `{signature}` after the one-time migration.
5. Preview a template and confirm `{signature}` renders into the configured signature text.
6. Clear the signature field, save, preview again, and confirm `{signature}` renders blank without breaking the email body.

## Per-template timing checks

1. On the Templates tab, confirm every template card has:
   - Enable checkbox
   - Send timing select
   - Days field
   - Send hour field
   - Subject
   - Body
2. Confirm default timing labels:
   - Know Before You Go: 3 days before event at 9:00am
   - Day-of Reminder: Day of event at 9:00am
   - Post-Event Thank You: 1 day after event at 10:00am
   - Weather / Event Update: Manual only
3. Change Know Before You Go to 2 days before at 8, save, and confirm the Preview & Test scheduled timing reflects the new timing.
4. Change Weather / Event Update to Manual only, save, and confirm Preview & Test shows no scheduled timing for that template.

## Custom template checks

1. Add a custom template named `Food Truck Follow-Up`.
2. Set timing to `After event`, days `1`, hour `11`.
3. Add a subject/body using `{customer_greeting}`, `{event_name}`, and `{signature}`.
4. Save templates.
5. Confirm the new template appears in the Templates tab and Preview & Test template dropdown.
6. Preview the custom template against a recent event and confirm tokens render.
7. Delete the custom template using the delete checkbox and save.
8. Confirm it disappears from the Templates tab and Preview & Test dropdown.

## Sending safety checks

1. Keep automatic scheduled sends off.
2. Send a test email for a built-in template and a custom template.
3. Confirm both arrive at the configured test address.
4. Confirm manual send still requires the confirmation checkbox before real recipient emails are sent.
5. Confirm logs record test sends and do not create duplicate real recipient sends from preview/test actions.

## Regression checks

1. Confirm the Preview & Test event selector still includes recent past events for post-event feedback.
2. Confirm the Post-Event Thank You template still includes and renders `{feedback_url}` when Event Feedback is available.
3. Submit a feedback form from a test link and confirm Email Follow-Ups logs the feedback submission.
4. Confirm no duplicate VMS top navigation stacks appear on the Email Follow-Ups page.
