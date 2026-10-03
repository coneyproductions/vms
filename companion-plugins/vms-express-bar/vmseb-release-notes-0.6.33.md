# VMS Express Bar 0.6.33 — mobile sticky Review order CTA

## Mobile purchase-flow parity
- Adds a persistent mobile **Review order** CTA fixed to the bottom of the viewport, matching the ticket-purchase experience.
- Keeps the top sticky summary informational only (selected-item count and subtotal).
- Hides the ordinary inline footer actions on small screens so customers see one clear mobile next-step action instead of duplicate buttons.
- Keeps the existing inline **Review Order** and **Checkout** actions on desktop/tablet layouts above the mobile breakpoint.
- The mobile CTA is disabled until at least one Express Bar item is selected and remains disabled when the event ordering window is closed.
- Adds safe-area padding for iPhone-style bottom insets and extra page-bottom spacing so menu content is not obscured by the fixed action bar.

## Compatibility
- No changes to event resolution, event switching, admission detection, tips, ordering windows, age gate, cart/order metadata, pricing, discounts, or fulfillment behavior.
