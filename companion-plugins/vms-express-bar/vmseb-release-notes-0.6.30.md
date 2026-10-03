# VMS Express Bar 0.6.30 — event safety, admission awareness, checkout tips

## Public event selection
- Dedicated `/express-bar/` auto-selection now considers only **published, Express Bar-enabled** Event Plans.
- Draft, pending, private, trashed, archived, and canceled Event Plans cannot become the navigation-driven public Express Bar context.
- A stale or hand-edited `?event_plan_id=` on the dedicated Express Bar page is validated; invalid/non-public plans fall back to the normal public resolver.
- Preserves the 0.6.29 unified-purchase handoff and event-scoped cart/order metadata.

## Event identity and switching
- Replaces the subtle one-line dedicated-page event context with a visual event card using the linked public event poster when available.
- Adds a **Change event** selector listing only current/future published Express Bar-enabled Event Plans.
- Switching events preserves already-carted Express Bar items under their original Event Plan.
- Warns before switching when the customer has unsaved quantities selected in the current builder.

## Admission awareness
- Detects matching admission products already in the WooCommerce cart.
- For logged-in customers, checks valid processing/completed/on-hold orders on the user/account email and accounts for refunded quantities.
- Shows a non-blocking warning when admission cannot be confirmed, with **Get Tickets** and **I already have tickets** actions.
- If an Event Plan has no locally verifiable ticket products (for example, external ticketing), the copy says admission cannot be verified automatically rather than claiming the customer has no ticket.

## Express Bar checkout tips
- Express Bar now owns the gratuity UI directly; the legacy VMS Commerce Discounts tip picker is suppressed while Express Bar is active.
- Checkout choices: **15% / 20% / 25% / Custom / No tip**.
- Percentage tips are calculated only from Express Bar line totals, not tickets, rentals, merchandise, taxes, or unrelated cart lines.
- After a tip is selected, the picker collapses to a clear **Tip added — … / Change** confirmation.
- Stores tip selection and compatible `_vms_discounts_*` order/fee metadata for reporting continuity.
- Default custom-tip safety cap: $100.

## Default ordering windows
- Restores site-level relative ordering-window defaults:
  - default open: 2880 minutes (48 hours) before showtime
  - default close: 0 minutes before showtime (at showtime)
- Explicit per-event Express Bar open/close times always override the defaults.
- Settings are editable on the Express Bar admin screen.

## Compatibility / unchanged behavior
- No change to Express Bar item registry, product eligibility, age-gate enforcement, pickup-name handling, quantity steppers, bucket discount snapshotting, fulfillment queue metadata, or the 0.6.29 post-ticket cart offer.
- Existing VMS Commerce Discounts tip settings are not deleted; they are simply prevented from rendering/charging while this Express Bar tip implementation is active.
