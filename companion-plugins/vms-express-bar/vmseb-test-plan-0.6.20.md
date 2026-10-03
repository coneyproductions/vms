## VMS Express Bar 0.6.20 Test Plan

1. Load `/express-bar/` on mobile and desktop and confirm the page leads with the Express Bar intro, not the event context.
2. Confirm the compact event reference reads `Ordering for: ...` and `View event page`, and both links resolve to the public event page.
3. Verify pickup name stacks above the selected-items summary on mobile and does not fight the summary layout on desktop.
4. Scroll deep into the menu on desktop and mobile and confirm the sticky summary remains visible without covering accordion headers or quantity controls.
5. In an open window, add and remove quantities and confirm the sticky summary updates live and `Review order` still routes to cart.
6. In a not-open-yet window, confirm pickup/stepper/review controls stay disabled and the compact status fields still render.
7. Confirm the old strings are absent:
   - `This page is for Express Bar drink pre-orders only.`
   - `Need admission tickets? Buy tickets on the event page.`
   - `before cart/checkout pricing`
   - `Eligible discounts are applied in cart/checkout.`
