# Codex Handoff — VMS 0.2.24.646

## Issue
After saving Event Feedback notification settings, the admin feedback results view could show mojibake characters such as `Â·` and `â€”` in rating labels, response summary rows, and primary-vendor choice counts.

## Likely cause
The affected strings used typographic UTF-8 separators (`·`, `—`, and ellipsis). The settings save path performs an `admin-post.php` redirect back to the admin page, and that refresh exposed the bad character interpretation in the rendered admin output.

## Change
Replaced the Event Feedback admin separators with ASCII-safe text:

- Response summary separator: ` - ` instead of em dash / middle dot.
- Rating label separator: `5/5 - Excellent` instead of `5/5 · Excellent`.
- Choice counts: `Yes 2 / Maybe 0 / No 0` instead of middle-dot separators.
- Empty/fallback labels: `--` instead of em dash.
- Public feedback submit fallback label: `Submitting...` instead of typographic ellipsis.

## Safety boundaries
- No database migrations.
- No notification-recipient storage changes.
- No survey payload/storage changes.
- No deletion, duplicate-detection, averaging, or notification email behavior changed.
- Preserves 0.2.24.645 progressive add-on visibility timing guard.

## Test focus
Open Event Feedback for a submitted event, save notification settings, and verify all response/summary labels display clean ASCII separators with no `Â` or `â` artifacts.
