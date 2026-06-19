# VMS 0.2.24.677 Test Plan — Vendor Application Holding Queue + Response Notes

## A. Version markers
1. Upload/activate the plugin.
2. Confirm the active plugin reports `0.2.24.677`.
3. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.677`.

Expected: all markers match `0.2.24.677`.

## B. Existing pending applications remain visible
1. Open VMS → Vendor Applications.
2. Confirm existing pending applications still show **Pending**.
3. Confirm the Pending badge/count still only represents Pending applications.

Expected: existing pending records are not silently approved/rejected/hidden.

## C. Move an application to Holding / Keep on File
1. Open a Pending vendor application.
2. Review the new **Operator response** card.
3. Confirm the default message explains that the application is being kept on file and that booking depends on confidence in paid ticket draw/overhead coverage.
4. Leave **Email this message** checked for a test applicant email.
5. Click **Move to Holding**.

Expected:
- Application status changes to **Holding / Keep on File**.
- Application no longer counts as Pending in the approval badge.
- Latest response metadata appears in the application sidebar.
- Applicant receives the response email, if site mail is configured.
- No VMS vendor post is created by the Holding action.

## D. Filter the Holding queue
1. Return to the Vendor Applications list.
2. Use the status dropdown and select **Holding / Keep on File**.
3. Click Filter.

Expected: the held application appears in the Holding filtered view.

## E. Approve with note
1. Open a Pending or Holding application.
2. Enter a short applicant-facing approval note.
3. Click **Approve**.

Expected:
- Status changes to **Approved**.
- A VMS vendor profile is created or synced.
- Submitting website user is linked when possible.
- Latest response is recorded.
- Applicant email sends when checked.

## F. Reject with note
1. Open a Pending or Holding application.
2. Enter a short applicant-facing rejection note.
3. Click **Reject**.

Expected:
- Status changes to **Rejected**.
- Latest response is recorded.
- Applicant email sends when checked.
- No new VMS vendor profile is created by the rejection action.

## G. Save private note only
1. Open any vendor application.
2. Enter an internal note.
3. Click **Save Note Only**.

Expected:
- Internal note is saved.
- Status does not change.
- No applicant email is sent.

## H. Regression checks
1. Submit a new public vendor application through `[vms_vendor_apply]`.
2. Confirm the new application is created as Pending.
3. Confirm existing admin notification email for new submissions still works.
4. Confirm row action for Pending/Holding applications says **Review / Respond** and does not bypass the message UI.
5. Run `php -l includes/vendor-applications.php`.

Expected: no fatal errors, no PHP syntax errors, and no one-click approval/rejection path that skips applicant messaging from the list row.
