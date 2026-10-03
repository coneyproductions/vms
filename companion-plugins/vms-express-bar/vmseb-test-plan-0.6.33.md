# VMS Express Bar 0.6.33 Test Plan

1. Confirm staging is running VMS Express Bar 0.6.33 with Express Bar prototype snippets disabled.
2. Mobile, zero selection: verify a bottom-fixed **Review order** CTA is visible but disabled.
3. Mobile, select one item: verify the sticky CTA enables immediately and remains visible while scrolling through categories.
4. Tap the sticky CTA: verify it submits to the cart/review-order destination and preserves pickup name, quantities, age-gate behavior, event-plan metadata, and totals.
5. Mobile: verify the old inline footer Review Order / Checkout buttons are not duplicated below the menu.
6. Mobile Safari/iPhone: verify the CTA clears the browser safe-area and does not cover the last menu item.
7. Desktop: verify the mobile sticky CTA is absent and the existing bottom Review Order / Checkout actions remain visible.
8. Closed/browse-only ordering window: verify the mobile CTA stays disabled.
9. Recheck event selector, admission-awareness, tips, mixed-cart totals, and ticket-only checkout for regressions.
