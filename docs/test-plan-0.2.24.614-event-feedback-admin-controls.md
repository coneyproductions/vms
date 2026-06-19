# VMS 0.2.24.614 Test Plan — Event Feedback Notifications + Delete

## Purpose

Validate the Event Feedback admin controls added after the MVP proved useful in production:

- optional operator notification emails for new feedback submissions
- admin deletion of individual feedback responses, mainly to clean up early duplicate submissions

## Pre-check

1. Install/activate VMS `0.2.24.614`.
2. Confirm version markers:
   - plugin header: `0.2.24.614`
   - `VMS_VERSION`: `0.2.24.614`
   - `vms-build.txt`: `0.2.24.614`
3. Go to `wp-admin/admin.php?page=vms-event-feedback`.
4. Select an Event Plan that already has feedback responses if available.

## Test 1 — Notification settings render

1. Open **VMS → Event Feedback**.
2. Select an Event Plan.
3. Confirm the sidebar shows **New submission notifications**.
4. Confirm fields exist:
   - `Email me new submissions` checkbox
   - recipient emails textarea
   - `Save Notification Settings` button

Expected:

- Settings form appears in the sidebar.
- It does not hide the response count/private-warning copy.
- Page layout remains readable at normal desktop width.

## Test 2 — Save notification settings

1. Check **Email me new submissions**.
2. Enter one test recipient email.
3. Save.
4. Confirm the page redirects back to the same selected Event Plan.
5. Confirm the success notice appears.
6. Reload the page.

Expected:

- Checkbox remains checked.
- Recipient email remains saved.
- No PHP warnings/notices appear.

## Test 3 — Multiple recipient parsing

1. Enter multiple emails separated by commas or line breaks.
2. Save.
3. Reload.

Expected:

- Valid recipient emails remain saved.
- Invalid entries are ignored.
- Settings form does not break.

## Test 4 — New submission notification

1. With notifications enabled, copy the private survey link.
2. Open the survey in an incognito/logged-out browser.
3. Submit one test feedback response.
4. Check the configured mailbox.

Expected:

- Exactly one new notification email is sent.
- Subject includes `New event feedback:` and the event title.
- Email body includes:
  - event title
  - submitter name/email if provided
  - overall rating if provided
  - final comment if provided
  - private VMS review URL
  - privacy reminder

## Test 5 — Disabled notifications

1. Uncheck **Email me new submissions**.
2. Save.
3. Submit another test feedback response.

Expected:

- Response saves normally.
- No Event Feedback notification email is sent.

## Test 6 — Delete one response

1. Open the selected Event Feedback admin page.
2. Expand/locate a test response.
3. Click **Delete response**.
4. Confirm the browser prompt.

Expected:

- Response is removed from the results list.
- Page redirects back to the same selected Event Plan.
- Success notice appears.
- Response count and averages update.
- Other responses remain intact.

## Test 7 — Delete duplicate cleanup

1. Find a response marked **Likely duplicate**.
2. Delete that duplicate response.
3. Reload the page.

Expected:

- Duplicate no longer appears.
- Duplicate count/sidebar note updates.
- Summary averages remain based on unique responses.

## Test 8 — Security checks

1. Try the delete action as a non-admin user.
2. Try the settings save action as a non-admin user.
3. Try deleting with an invalid/missing nonce.

Expected:

- Non-admin requests are blocked.
- Invalid nonce requests are blocked.
- No response is deleted without capability + nonce validation.

## Regression smoke checks

- Public feedback survey still renders.
- Survey submit still redirects to the thank-you state.
- Duplicate-submit hardening still blocks fast repeat submissions.
- Primary Vendor Details still appears.
- Secondary Vendor Details still appears.
- Email Follow-Ups post-event preview still opens from the Event Feedback page.
- Event Plan sidebar feedback metabox still displays survey/admin links.
- No duplicate VMS top navigation appears on the Event Feedback page.
