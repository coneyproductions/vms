# VMS Test Plan — 0.2.24.666 Cancelled Event Public Safety

## A. Version and syntax

1. Confirm plugin header, `VMS_VERSION`, and `vms-build.txt` report `0.2.24.666`.
2. Run PHP lint across plugin PHP files.
3. Run `node --check` across non-minified plugin JS files.
4. Confirm no activation/admin-load fatal.

## B. Regression: lightweight saves still safe

1. On a published ticketed Event Plan, perform a content-only WP Update.
2. Confirm save profile remains `core_wp_update` / Core only.
3. Confirm Ticket Integrity and staffing heavy work stay skipped.
4. Confirm no ticket hashes/product IDs/queue/audit rows drift.

## C. Front-page/photo shortcode cancelled exclusion

1. Find or create two upcoming Event Plans with public URLs/images: one published and one cancelled.
2. Render `[vms_events_photo limit="6"]`.
3. Confirm the cancelled event is not present by default.
4. Render `[vms_events_photo limit="6" include_cancelled="1"]`.
5. Confirm the cancelled event can still appear when explicitly requested, with the existing cancelled/rescheduled visual state.

## D. Public cancelled event page privacy and visible cancellation state

1. Use a cancelled Event Plan linked to a TEC event. Add a distinctive internal cancellation reason/note if safe in the fixture.
2. Visit the public TEC event page logged out/incognito.
3. Confirm a public cancellation/reschedule notice is visible.
4. Confirm the page body includes `vms-event-is-cancelled` or equivalent rendered cancellation markers.
5. Confirm internal cancellation reason code/note text is not visible in public HTML.
6. Confirm a Cancelled/Rescheduled image ribbon is visible where the theme renders the featured image; if the theme bypasses `post_thumbnail_html`, note the theme-specific limitation.

## E. Cancelled event ticket UI is fail-closed

1. On the same logged-out cancelled event page, confirm no active quantity controls/add-to-cart/Get Tickets controls remain for any ticket type.
2. Specifically check free/children/qualified tickets; they must not remain selectable.
3. Confirm reserved add-ons/fire pits/tables do not render.

## F. Direct Woo add/cart/checkout blocking

1. Identify all Woo products mapped to the cancelled Event Plan/TEC event, including paid, free/children, qualified, and entitlement/add-on products if present.
2. Attempt direct add-to-cart for each product.
3. Confirm each add fails with a cancellation sales-closed notice.
4. If a stale cancelled-event item is already in cart, confirm cart/checkout validation blocks checkout and shows the cancellation notice.

## G. Cancellation notification recipients

1. Use a fixture with assigned staff and vendor/primary vendor recipients.
2. Trigger or dry-run the cancellation notifications step.
3. Confirm staff recipients are included when staff are assigned.
4. Confirm duplicate recipient suppression still works by email.
5. Confirm no public customer-facing notice contains internal cancellation notes.

## H. Reduced staging smoke when available

The user generally installs each candidate build on staging as a fallback/real-world option. If local rendering cannot prove the public behavior, use staging for:

1. Logged-out/incognito cancelled event page rendering.
2. Public front-page/photo-card shortcode behavior with cache cleared if needed.
3. Direct add-to-cart/cart/checkout blocking for cancelled event products.
4. Staff notification/dry-run visibility if local email capture is insufficient.
