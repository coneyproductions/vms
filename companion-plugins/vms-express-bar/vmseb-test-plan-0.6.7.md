# VMS Express Bar 0.6.7 Test Plan

🚨 **Codex / browser testing recommended** because this pass changes public status wording and ordering-window behavior.

## 1. Version checks

- Confirm plugin header shows `0.6.7`.
- Confirm `VMSEB_VERSION` is `0.6.7`.
- Confirm `vms-build.txt` contains `0.6.7`.

## 2. Dedicated page — browse-only without explicit opening time

1. Open an Express Bar-enabled Event Plan with no `Ordering opens` value.
2. Visit `/express-bar/`.
3. Confirm the menu renders.
4. Confirm the status card says `Browse Only` or equivalent browse-only language.
5. Confirm it does **not** say `12:00 am` / midnight.
6. Confirm quantity steppers and checkout buttons are disabled.

## 3. Dedicated page — explicit future opening time

1. Set `Ordering opens` to a future time.
2. Visit `/express-bar/`.
3. Confirm the menu renders in browse-only mode.
4. Confirm the status says the configured opening time.
5. Confirm add-to-cart controls remain disabled.

## 4. Dedicated page — active ordering window

1. Set `Ordering opens` to a past time and `Ordering closes` to a future time.
2. Visit `/express-bar/`.
3. Confirm the status says ordering is open.
4. Confirm quantity steppers are enabled.
5. Add one or more items and continue to cart or checkout.
6. Confirm the Woo cart/order retains Express Bar event and pickup metadata.

## 5. Closed after event/window

1. Set `Ordering closes` to a past time.
2. Visit `/express-bar/`.
3. Confirm the message does not say customers can come back before ordering opens.
4. Confirm checkout remains blocked.

## 6. Event-page CTA regression check

1. Visit the public event page.
2. Confirm the full menu is no longer embedded in the event body.
3. Confirm the compact Express Bar CTA links to `/express-bar/?event_plan_id=...`.

## 7. Performance smoke

- Confirm the dedicated page still avoids hydrating the entire Woo catalog on public render.
- Confirm unrelated public pages do not enqueue Express Bar assets unless the shortcode/CTA is present.
