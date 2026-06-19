# VMS Test Plan — 0.2.24.605 — Event Feedback + Email Follow-Ups Integration

🚨 **Codex/staging verification required before production customer sends.** This pass touches customer email copy, feedback survey links, and logging. Do not enable automatic scheduled sends until preview, test email, and one controlled manual test are verified.

## Version checks

1. Confirm plugin header version is `0.2.24.605`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.605`.
3. Confirm `vms-build.txt` contains `0.2.24.605`.

## Smoke checks

1. Activate/update VMS on staging.
2. Confirm there is no fatal error on plugin load.
3. Open wp-admin and confirm the normal VMS shell/top navigation renders.
4. Open `admin.php?page=vms-event-feedback` and confirm the Event Feedback page loads.
5. Open `admin.php?page=vms-email-followups` and confirm the Email Follow-Ups page loads.

## Event Feedback admin → Email Follow-Ups bridge

1. Open Event Feedback and choose a recent Event Plan.
2. Confirm the private survey URL still appears.
3. Click **Open Post-Event Email Preview**.
4. Confirm Email Follow-Ups opens on the Preview & Test tab with the same Event Plan selected and the `post_event` template selected.
5. From an Event Plan edit screen, confirm the Post-Event Feedback metabox still shows the survey URL and now includes **Preview Feedback Email**.

## Post-event email template token checks

1. Open Email Follow-Ups → Templates.
2. Confirm `{feedback_url}` appears in the Available Tokens list.
3. Confirm the Post-Event Thank You body contains a private feedback link line, either from defaults or the one-time migration.
4. Save templates and confirm global Overview settings are not reset.
5. Save Overview settings and confirm template enabled/disabled states are not reset.

## Rendered preview checks

1. Open Email Follow-Ups → Preview & Test.
2. Select a real/past Event Plan with ticket buyers.
3. Select **Post-Event Thank You**.
4. Confirm the recipient preview loads without errors.
5. Confirm the Rendered Email includes a clickable feedback URL.
6. Confirm the Recipient Preview card shows **Feedback link: Included**.
7. Confirm the feedback URL contains `vms_event_feedback=1`, `event_plan_id`, `key`, and recipient-aware invite markers when a ticket buyer recipient is available.

## Test email check

1. Keep automatic scheduled sends off.
2. Send a test Post-Event Thank You email to the admin/test recipient.
3. Confirm the email arrives.
4. Click the feedback link from the test email.
5. Confirm the private Event Feedback survey opens for the correct Event Plan.
6. Submit one test response.
7. Confirm the response appears privately under Event Feedback.
8. Confirm Email Follow-Ups → Logs includes a `feedback_submission` / `post_event` row.

## Manual-send guard check

1. On Email Follow-Ups → Preview & Test, attempt **Send to Eligible Recipients** without checking the confirmation checkbox.
2. Confirm no customer emails are sent and a warning appears.
3. Do not perform a real manual send on production until the recipient list is reviewed.

## Regression checks

1. Confirm normal Event Feedback public survey links still work without an email-invitation token.
2. Confirm an invalid feedback `key` still shows the unavailable-link state.
3. Confirm ticketing UI, checkout, qualified ticket helper copy, and Express Bar smoke paths are unaffected.
4. Confirm no duplicate post-event emails are sent to the same recipient when the duplicate-send log guard already has a sent row.

## Notes

- The integration intentionally keeps MailPoet as the sending/list layer where configured, while VMS owns event-date logic and feedback-link token generation.
- Automatic scheduled sends remain controlled by Email Follow-Ups settings and should stay disabled until site-specific recipient discovery is trusted.
