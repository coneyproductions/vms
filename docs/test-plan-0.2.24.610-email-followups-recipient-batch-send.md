# VMS Test Plan — 0.2.24.610 — Email Follow-Ups Recipient Selection + Batch Sends

🚨 **Codex/staging recommended before using this against a large live customer list.**

## Version checks

1. Confirm plugin header reports `0.2.24.610`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.610`.
3. Confirm `vms-build.txt` begins with `0.2.24.610`.

## Template duplicate regression

1. Go to `VMS → Marketing & Social → Email Follow-Ups → Templates`.
2. Count existing custom templates.
3. Edit an existing built-in template body.
4. Click the per-template **Save Template Changes** button.
5. Return to Templates and confirm no new blank/empty **Custom Follow-Up** template was created.
6. Repeat using the bottom **Save Templates** button.
7. Confirm no new blank/empty **Custom Follow-Up** template was created.

## Empty placeholder cleanup migration

1. If the previous 0.2.24.609 regression created empty placeholder custom templates, load any wp-admin page after installing 0.2.24.610.
2. Return to Email Follow-Ups → Templates.
3. Confirm empty placeholder custom templates named **Custom Follow-Up** with the default blank starter body are removed.
4. Confirm real custom templates with meaningful labels/subjects/bodies remain.

## Add Template behavior

1. Fill out the Add Template fields and click **Save New Template**.
2. Confirm exactly one custom template is created.
3. Edit that custom template and save.
4. Confirm saving the custom template does not create another custom template.

## Recipient selection manual send

1. Go to Email Follow-Ups → Preview & Test.
2. Select a recent event with multiple ticket buyers.
3. Confirm the manual-send section shows a recipient picker table with checkboxes.
4. Click **Select none** and attempt to submit.
5. Confirm the browser blocks the send with a “select at least one recipient” alert and no emails are sent.
6. Select one recipient, confirm send, and submit.
7. Confirm exactly that selected recipient receives the email.
8. Confirm the Logs tab shows one sent row for that recipient.

## Batch-send behavior

For staging/Codex, temporarily force a small batch size with the `vms_email_followups_manual_batch_size` filter, such as `2`, then test with an event that has at least 3 eligible recipients.

1. Select at least 3 recipients and confirm the manual send.
2. Confirm the first request sends only the batch-size amount.
3. Confirm the redirect notice says recipients remain.
4. Confirm the Preview & Test page shows **Manual send in progress** and a **Continue Sending Next Batch** button.
5. Click Continue Sending until no recipients remain.
6. Confirm logs show each selected recipient exactly once, with skipped duplicate rows only if a recipient had already received that same event/template before the test.

## Progress/safety UI

1. Start a manual send.
2. Confirm the button changes to **Sending...** and the page shows the keep-tab-open progress note while the request is running.
3. Confirm the page returns to Preview & Test with a success/warning notice.
