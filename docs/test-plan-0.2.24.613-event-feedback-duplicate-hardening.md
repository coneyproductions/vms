# VMS 0.2.24.613 Test Plan — Event Feedback Duplicate Hardening

## Scope

This patch is based on the 0.2.24.612 debug-log cleanup package and only targets Event Feedback duplicate handling and summary clarity.

## Files touched

- `includes/core/event-feedback.php`
- `includes/public/event-feedback.php`
- `includes/admin/event-feedback.php`
- `assets/js/vms-event-feedback.js`
- `assets/css/vms-event-feedback.css`
- version/build metadata

## Smoke checks

1. Confirm version markers show `0.2.24.613`:
   - `vendor-management-system.php`
   - `includes/core/registry/constants.php`
   - `vms-build.txt`
2. Confirm the site loads without a PHP fatal error.
3. Confirm VMS → Event Feedback opens.
4. Confirm an event-specific feedback survey link still renders.
5. Confirm the public survey page loads logged out/incognito.

## Duplicate-prevention checks

### Same loaded form double-click

1. Open a feedback survey in incognito.
2. Fill the required overall rating and any test comments.
3. Double-click the submit button or click it repeatedly.
4. Expected:
   - The button changes to `Submitting…` and disables.
   - Only one response is stored.

### Browser POST retry / back-submit

1. Submit a test response.
2. Use browser back/reload/resubmit behavior if prompted.
3. Expected:
   - The second POST redirects to the thank-you state.
   - No extra stored response is created.

### Same email invite repeated

1. Use a survey link containing `recipient=` or enter the same optional email twice on separate attempts.
2. Expected:
   - First submission is stored.
   - Later submission from the same event+recipient/email is treated as an existing response and redirects to thank-you without adding a duplicate.

### Anonymous exact retry

1. Submit anonymous feedback.
2. Immediately resubmit the same form values from the same browser/device.
3. Expected:
   - The duplicate is blocked by hashed request+payload detection.

## Admin summary checks

1. Open VMS → Event Feedback for the tested Event Plan.
2. Confirm duplicate-looking existing responses are labeled `Likely duplicate` when the stored payload matches.
3. Confirm summary averages use only unique responses.
4. Confirm the sidebar explains stored responses vs likely duplicates when duplicates exist.
5. Confirm `Primary vendor details` appears above secondary vendor details when the Event Plan has a primary vendor.

## Regression checks

1. Confirm secondary vendor details still show wait time/speed, friendliness, selection, price/value, quality, and accuracy averages.
2. Confirm Email Follow-Ups preview still opens from the Event Feedback page.
3. Confirm existing feedback links continue to work; no new link format is required.
4. Confirm no raw IP address or user-agent text is stored; only hashed request metadata is saved.

## Codex notes

⚠️ Please specifically test rapid double-submit / browser retry behavior. The issue reported in production included duplicate and triplicate stored responses, including one customer with 8 GA tickets and 2 comp tickets. Ticket quantity should not be assumed as the cause.
