# CODEX HANDOFF — VMS 0.2.24.624

🚨 **Please run this on staging before live deployment.**

## Goal

Polish the selected qualified-ticket mobile/customer instructions after 0.2.24.622 restored row descriptions but the expanded claim area still felt cramped and confusing for logged-out customers.

## What changed

- Simplified logged-out selected qualified-ticket copy to: `Please log in to redeem your [ticket name]. New here? Register first.`
- Kept Log In and Register actions visible.
- Separated the guest-email section from the login/register prompt.
- Kept the simplified logged-out copy from 0.2.24.623, removed the extra guest-row outlines, and stacked the guest email input above the button on mobile.
- Updated guest-email helper copy to explain that the customer should enter registered emails for guests already approved for that specific ticket.
- Hid the redundant `Need more than one qualified ticket?` disclosure when the guest-email panel is already visible.
- Removed the extra outlined guest-row box styling in the qualified-ticket guest-email section.
- Updated the mobile qualified-guest row layout so the email input sits above the `Add Qualified Guest` button and both controls span the available width.

## Files changed

- `assets/css/ticketing-front/40-ticket-locking.css`
- `assets/css/ticketing-front/80-ticket-ui-rewrite.css`
- `assets/css/vms-ticketing-front.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/test-plan-0.2.24.624-qualified-guest-mobile-layout.md`
- `vms-test-plan-0.2.24.624.md`

## Test plan

Run:

- `docs/test-plan-0.2.24.624-qualified-guest-mobile-layout.md`
- `vms-test-plan-0.2.24.624.md`

## Non-goals

- No checkout/payment behavior changes.
- No add-on eligibility logic changes.
- No ticket inventory/capacity logic changes.
- No desktop visual redesign beyond the selected qualified-ticket copy.
