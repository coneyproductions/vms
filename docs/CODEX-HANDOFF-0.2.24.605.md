# CODEX HANDOFF — VMS 0.2.24.605 — Event Feedback + Email Follow-Ups Integration

## Package

- Build: `0.2.24.605`
- Base: `0.2.24.604-private-event-feedback-mvp`
- Purpose: connect post-event Email Follow-Ups with the private Event Feedback MVP.

## What changed

- Added `{feedback_url}` token support to Email Follow-Ups.
- Updated the Post-Event Thank You default template to include a private feedback link.
- Added a one-time settings migration so existing stored post-event templates get a feedback link if missing.
- Added recipient-aware invite/hash URL markers for emailed feedback links without exposing raw order IDs or raw email addresses.
- Feedback submissions now log a `feedback_submission` row into Email Follow-Ups logs when the logging module is available.
- Event Feedback admin and Event Plan sidebar metabox now link directly to the post-event email preview for the selected event.

## Critical safety notes

🚨 Do not enable automatic scheduled sends until staging confirms recipient discovery, rendered copy, and test email behavior.

Manual sends still require the confirmation checkbox. Test sends go only to the chosen test recipient.

## Required test plan

Run: `docs/test-plan-0.2.24.605-feedback-email-integration.md`

Key checks:

1. Email Follow-Ups page loads.
2. Event Feedback page loads.
3. Post-Event Thank You preview includes a feedback link.
4. Test email feedback link opens the correct event survey.
5. Test feedback submission appears privately in Event Feedback.
6. Email Follow-Ups logs the feedback submission.
7. Automatic scheduled sends remain off unless deliberately enabled.
