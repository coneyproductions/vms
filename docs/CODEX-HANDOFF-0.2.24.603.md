# CODEX HANDOFF — VMS 0.2.24.603 — Qualified Ticket Self-Allowance Fix

## Summary

This is a targeted follow-up to `0.2.24.602`. The prior patch relaxed duplicate-email validation so one approved assignee could claim multiple seats up to their effective allowance. This pass fixes the remaining self-allowance calculation problem where the logged-in buyer could still be treated as covering only one ticket, causing the public UI to prompt for an extra guest email even when the buyer's verified allowance or direct grant allowed more.

## Technical Notes

- `vms_ticketing_v2_assignee_claims_per_event_limit()` now resolves verified/profile allowance without capping it by the public ticket `max_qty_per_order` value.
- The ticket/product max-qty setting remains separate from a credentialed person's eligible-pass allowance.
- Active event/direct grants can raise the effective per-assignee cap even when the credential-program path also matches first.
- Ticket context now carries `ticket_product_id` so the direct-grant lookup can be scoped correctly when available.

## Test Priority

Run `docs/test-plan-0.2.24.603-qualified-ticket-self-allowance-fix.md`.

Priority cases:

1. Approved Veteran account with default allowance `2` selects quantity `2` with no second guest-email prompt.
2. Same account with override `4` selects quantity `4` with no guest-email prompt for self-covered seats.
3. Quantity above allowance still requires a separate approved guest or fails gracefully.
4. Prior completed/on-hold/processing consumption still reduces availability.
5. Mixed GA + qualified + add-on checkout still reaches checkout correctly.

## Rollback

Rollback to `0.2.24.600` if broad checkout behavior breaks or PHP fatals occur. A legitimate over-allowance rejection is not a regression.
