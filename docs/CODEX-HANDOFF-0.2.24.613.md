# CODEX Handoff — VMS 0.2.24.613

## Goal

Patch the Event Feedback duplicate-submission issue observed after the private feedback MVP began receiving real customer responses.

## User report

- Event Feedback is working well and collecting candid private feedback.
- Some responses are duplicate submissions.
- One real customer generated a triplicate response.
- That customer bought 8 GA tickets and 2 comp tickets, so ticket quantity does not appear to be the direct cause.

## What changed

- Added front-end submit-button lockout on the public feedback form.
- Added a hidden one-time submission UID per rendered form.
- Added server-side idempotency checks for:
  - submission UID hash
  - feedback email-recipient hash
  - optional attendee email hash
  - same payload + same hashed request fingerprint within a short window
- Added short-lived submission locks around insert attempts to reduce race-window duplicates.
- Stored duplicate fingerprint/request/idempotency metadata on new responses.
- Added admin duplicate partitioning so likely duplicate stored responses are labeled and excluded from averages.
- Added Primary Vendor Details summary card.
- Kept the 0.2.24.612 debug-log cleanup baseline intact.

## Files changed

- `includes/core/event-feedback.php`
- `includes/public/event-feedback.php`
- `includes/admin/event-feedback.php`
- `assets/js/vms-event-feedback.js`
- `assets/css/vms-event-feedback.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/test-plan-0.2.24.613-event-feedback-duplicate-hardening.md`

## Important behavior

Existing survey URLs continue to work. The hidden one-time submission UID is generated when the public form renders, not in the URL.

Email-follow-up links using `recipient=` now become effectively one response per event+recipient. Manually entered optional attendee emails are also guarded one response per event+email.

Anonymous duplicate blocking is intentionally narrower: same event + same exact payload + same hashed request/browser fingerprint within the duplicate window.

## Test priority

⚠️ Focus Codex testing on double-click, browser retry, and repeated email-link cases. Also confirm existing response summaries continue to render and likely duplicates are excluded from averages.
