# VMS 0.2.24.646 Test Plan — Event Feedback Admin Encoding Polish

## Purpose
Verify the Event Feedback admin page no longer displays mojibake characters after notification settings are saved.

## Setup
- Deploy VMS 0.2.24.646 to staging.
- Use an Event Plan with at least one Event Feedback response.
- Use the Event Feedback admin page with response summaries and detailed responses visible.

## Tests
1. Confirm version markers:
   - `vms/vendor-management-system.php` reports `Version: 0.2.24.646`.
   - `vms/includes/core/registry/constants.php` reports `VMS_VERSION` as `0.2.24.646`.
   - `/wp-content/plugins/vms/vms-build.txt` reports `0.2.24.646`.
2. Open `VMS -> Event Feedback` for an event with responses.
   - Expected: rating labels use clean text like `5/5 - Excellent`.
   - Expected: primary vendor counts use clean text like `Yes 2 / Maybe 0 / No 0`.
   - Expected: response summary line uses plain separators between submitted date, name, and email.
3. Save the New Submission Notification settings.
   - Expected: redirect returns to the same event feedback page.
   - Expected: no `Â`, `â`, `â€`, or similar mojibake appears anywhere in the Event Feedback response view.
4. Toggle notification settings off/on and save again.
   - Expected: recipients are preserved/sanitized as before.
   - Expected: no encoding artifacts return.
5. Open the public feedback form and start a submission.
   - Expected: the fallback button label, if shown, uses `Submitting...` rather than a typographic ellipsis.

## Regression checks
- Feedback response deletion still requires confirmation and works as before.
- New feedback averages and duplicate-excluded summaries are unchanged aside from separator text.
- 0.2.24.645 add-on visibility behavior remains intact on a known event with mapped add-ons.
