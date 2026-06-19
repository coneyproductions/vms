# CODEX HANDOFF — VMS 0.2.24.604 — Private Event Feedback MVP

## Summary

This build adds the first-pass private post-event feedback module so the operator can collect useful customer feedback early this week.

The module is intentionally small and event-focused:

- Event-specific private survey links.
- Public one-page survey endpoint for attendees.
- Venue/overall feedback plus bar and bathroom quick ratings with optional detail sections.
- Primary vendor / performance feedback when an Event Plan has a primary vendor.
- Secondary vendor feedback when an Event Plan has assigned secondary vendors, including wait-time diagnostics for food trucks or similar vendors.
- Private admin response summaries and detailed response review.
- Event Plan sidebar metabox with copyable survey URL and View Responses button.

## Important Boundaries

This pass should not change:

- TEC ticket UI or quantity controls.
- Qualified-ticket claim logic.
- Woo checkout.
- Square sync/protection.
- Express Bar.
- OPS scanner/camera flows.
- Vendor portal flows.

Response data is stored in a private internal CPT (`vms_feedback`) and should not be exposed publicly or to vendors by default.

## Technical Notes

- Shared helpers and CPT registration live in `includes/core/event-feedback.php`.
- Public survey routing/rendering and submit handling live in `includes/public/event-feedback.php`.
- Admin shell page, response summaries, and Event Plan metabox live in `includes/admin/event-feedback.php`.
- CSS lives in `assets/css/vms-event-feedback.css`; no inline style blocks were added.
- Public survey URLs use a deterministic event-specific HMAC token, not a stored option/table.
- No database migration is required.

## Test Priority

Run `docs/test-plan-0.2.24.604-event-feedback-mvp.md`.

Priority checks:

1. Admin page loads and appears in the VMS top navigation / All VMS Pages discovery.
2. Event Plan sidebar metabox shows survey URL without adding nested forms.
3. Logged-out survey link renders and submits successfully.
4. Admin summaries and response details update after submit.
5. Invalid survey token is rejected.
6. Ticketing/checkout/Express Bar spot checks show no unrelated regression.

## Rollback

Rollback to `0.2.24.603` if this introduces PHP fatals or global public/admin page breakage. If only survey copy/fields need adjustment, patch the feedback module in place and bump version markers again.
