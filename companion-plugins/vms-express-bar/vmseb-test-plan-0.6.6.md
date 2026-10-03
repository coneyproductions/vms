# VMS Express Bar 0.6.6 Test Plan

🚨 **Codex / browser testing recommended.** This pass changes public event-page rendering, dedicated-page routing, and admin page detection.

## 1. Version checks

- Confirm plugin header shows `0.6.6`.
- Confirm `VMSEB_VERSION` is `0.6.6`.
- Confirm `vms-build.txt` contains `0.6.6`.

## 2. Admin dedicated page detection

Go to **VMS → Bar Menu**.

Expected:

- A **Public Express Bar Page** card appears near the top.
- If a page at `/express-bar/` exists, it is detected and linked.
- If the page contains `[vms_express_bar_menu]`, the admin card says the shortcode is detected.
- The **Dedicated Page** selector includes the Express Bar page.
- Saving Bar Menu preserves the selected page.

## 3. One-click page setup

Only test this on a fixture/staging site where the page does not already exist.

Expected:

- If no Express Bar page is detected, Bar Menu shows **Create Express Bar Page**.
- Clicking it creates a published `Express Bar` page with slug `express-bar` and content `[vms_express_bar_menu]`.
- It does not overwrite an existing page.

## 4. Event page CTA behavior

On an Event Plan or TEC event with Express Bar enabled and the event-page link option enabled:

Expected:

- The event page no longer appends the full Express Bar menu.
- A compact **Skip the line at the bar** CTA appears.
- CTA button points to `/express-bar/?event_plan_id={event_plan_id}`.
- CTA text reflects open vs closed/browse state.
- No nested forms are introduced on the event page.

## 5. Dedicated Express Bar page behavior

Open `/express-bar/`.

Expected:

- The full menu renders from `[vms_express_bar_menu]`.
- If `?event_plan_id={event_plan_id}` is present, that event is used.
- Without a query parameter, the page resolves the next published Express Bar-enabled Event Plan.
- If ordering is closed, products remain visible in browse-only mode and quantity controls/checkout buttons are disabled.
- If ordering is open, customers can add items and proceed to cart/checkout.

## 6. Cart/order regression

With ordering open:

- Add a non-age-gated item and continue to cart.
- Add an age-gated item and confirm birthday gate behavior.
- Confirm pickup name is required.
- Confirm order metadata still includes Express Bar event/pickup/item data.

## 7. Performance sanity check

- Compare event-page render before/after this pass.
- The event page should be lighter because it renders only the CTA instead of hydrating the full menu catalog.
- Dedicated Express Bar page still hydrates only enabled registry items, not the full Woo catalog.
