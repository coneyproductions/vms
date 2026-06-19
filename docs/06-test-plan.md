Latest targeted pass: `0.2.24.641` — see `docs/test-plan-0.2.24.641-ticket-quantity-checkout-hotfix.md`.

🚨 Codex/staging testing is recommended before installing live: focus on normal GA/public ticket quantities greater than 5, reducing a cart from 6 to 4, and confirming verified/free registered tickets still enforce their required registration/allowance behavior.

Latest targeted pass: `0.2.24.610` — see `docs/test-plan-0.2.24.610-email-followups-recipient-batch-send.md`.

## 0.2.24.610 — Email Follow-Ups selected recipients + batch-safe manual sends

🚨 Codex/staging testing is recommended before using this on a large live customer list. Focus on confirming empty custom template cleanup, no new custom template appears when saving existing templates, selected-recipient manual sends, and the Continue Sending flow on a forced small batch size.

# VMS Living Test Plan

Previous targeted pass: `0.2.24.609` — see `docs/test-plan-0.2.24.609-email-followups-template-save-character-cleanup.md`.

🚨 Email/feedback staging verification required: confirm the Post-Event Thank You template renders `{feedback_url}`, the test email opens the correct private survey, a submitted response appears in Event Feedback, and Email Follow-Ups logs a `feedback_submission` row. Keep automatic scheduled sends off until recipient preview/test sends are verified.

## 0.2.24.609 — Email Follow-Ups template save buttons + character cleanup

See `docs/test-plan-0.2.24.609-email-followups-template-save-character-cleanup.md`. Focus on confirming per-template save buttons submit successfully, saved template edits persist without scrolling to the bottom, and mojibake such as `Ã¢Â€Â¢` is repaired on existing and newly saved templates.

## 0.2.24.608 — Email Follow-Ups template timing + signatures

See `docs/test-plan-0.2.24.608-email-followups-template-timing-signatures.md`. Focus on confirming per-template timing, `{signature}` rendering, custom template add/delete, and post-event feedback URL regression behavior.

## 0.2.24.607 — Email Follow-Ups smart greeting tokens

See `docs/test-plan-0.2.24.607-email-followups-smart-greeting-tokens.md`. Focus on confirming `{customer_greeting}` renders as `Hi First,` for real eligible recipients with a billing/customer name and as `Hi there,` when no usable first name is available.

## 0.2.24.606 — Email Follow-Ups past-event preview repair

See `docs/test-plan-0.2.24.606-email-followups-past-event-preview.md`. Focus on confirming recently ended events appear in Email Follow-Ups → Preview & Test, especially with the Post-Event Thank You template and feedback URL token.

## 0.2.24.605 — Event Feedback + Email Follow-Ups integration

See `docs/test-plan-0.2.24.605-feedback-email-integration.md`. Focus on post-event email preview, feedback URL token rendering, test-send link behavior, feedback submission logging, and guardrails that prevent accidental customer blasts.

## 0.2.24.604 — Private Event Feedback MVP

See `docs/test-plan-0.2.24.604-event-feedback-mvp.md`. Focus on admin page loading, Event Plan survey link generation, logged-out survey submit, private response review, token rejection, and ticketing/checkout/Express Bar spot checks.

