# VMS Express Bar 0.6.30 Test Plan

## Preflight
1. Confirm staging is running VMS Express Bar 0.6.30.
2. Disable temporary WPCode snippets **Express Bar Tips — STAGING (#5981)** and **Express Bar Event Safety — STAGING (#5982)** so the plugin is the only implementation under test.
3. Purge WP Super Cache and object cache.
4. Confirm production remains untouched.

## A. Public resolver safety
1. Keep a future Draft Event Plan with an earlier date than the next published Express Bar event.
2. Load `/express-bar/` with no query parameter.
3. Confirm the Draft plan is never selected.
4. Confirm the page resolves to the current/next **published + Express Bar-enabled** Event Plan.
5. Load `/express-bar/?event_plan_id=<draft-plan-id>` and confirm it falls back to a valid public Express Bar plan.
6. Load an explicit valid published+enabled Event Plan and confirm it is honored.

## B. Event identity / selector
1. Confirm the selected event card shows title, local date/time, poster (or a neutral fallback), and View Event action.
2. With two or more published Express Bar-enabled future events, confirm **Change event** appears.
3. Open the selector and confirm only eligible public Express Bar events are listed.
4. Select another event and confirm the URL carries the selected `event_plan_id` and the menu/order metadata uses that plan.
5. Select a drink quantity but do not add it to cart; switch events and confirm a discard warning appears.
6. Put drinks for Event A in cart, switch to Event B, and confirm Event A items remain associated with Event A.

## C. Admission awareness
1. With a matching ticket in cart, confirm the event card reports admission found and the correct quantity.
2. Logged in with a prior valid matching ticket order, confirm admission is found on account.
3. With neither signal, confirm the warning is non-blocking and offers **Get Tickets** and **I already have tickets**.
4. Click **I already have tickets**; confirm the warning dismisses for that event for the browser session.
5. Use an externally ticketed/no-local-ticket-product event and confirm copy says admission cannot be verified automatically.

## D. Checkout tips
1. Ticket-only cart: no tip prompt.
2. Rental/merchandise-only cart: no tip prompt.
3. Express-Bar-only cart: tip prompt appears.
4. Mixed ticket + Express Bar cart: tip prompt appears; verify the displayed dollar amounts use only the Express Bar line subtotal.
5. Choose 15%, 20%, 25%; confirm fee amount and checkout total update correctly.
6. Confirm the picker collapses after a real tip is selected and **Change** reopens it.
7. Choose Custom; verify amount, maximum cap, and recalculation.
8. Choose No tip; confirm the fee is removed and the chooser remains available.
9. Change Express Bar quantities after selecting a percentage; confirm the percentage tip recalculates.
10. Place a staging test order and confirm `Bar Staff Tip` is a separate fee and compatible tip metadata is stored.

## E. Ordering-window defaults
1. Event with explicit open/close times: confirm explicit values still win.
2. Event with blank open/close times: confirm defaults open 48 hours before showtime and close at showtime.
3. Change default values in Express Bar settings and confirm a blank-window event reflects them.
4. Confirm post-showtime default behavior is closed, not browse/open.

## F. Regression
1. Age-gated item DOB flow still blocks underage/unconfirmed alcohol orders.
2. Pickup name remains required for open windows.
3. Quantity steppers and sticky summary remain stable on mobile and desktop.
4. Review Order and Checkout routes still add event-scoped Express Bar metadata.
5. 0.6.29 post-ticket **Order Drinks** offer still routes to the correct Event Plan.
6. Cart/order/fulfillment metadata and existing bucket-discount behavior remain intact.
