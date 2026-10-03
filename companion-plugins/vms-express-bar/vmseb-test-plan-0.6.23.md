# VMS Express Bar 0.6.23 Test Plan

1. Load the add-on with the public Backstage Venue Manager package and confirm dependency detection succeeds from `BVMGR_VERSION` / `BVMGR_PLUGIN_FILE`.
2. Open a linked TEC event whose Event Plan has Express Bar and auto-embed enabled.
3. Confirm the event page renders one event-specific Express Bar CTA linking to `/express-bar/?event_plan_id={PLAN_ID}`.
4. Confirm a disabled event or an event with auto-embed disabled does not render the CTA.
5. Confirm the dedicated Express Bar page still resolves the current/next Event Plan and retains its configured ordering window.
6. Add an available item, review the cart, complete a safe test order, and confirm event/item metadata and queue visibility.
7. Confirm legacy VMS resolver function names remain supported.
8. Run PHP lint across every plugin PHP file and validate the release ZIP with `unzip -t`.
