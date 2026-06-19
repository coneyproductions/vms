# Test Plan — 0.2.24.582 GA ticket image repair + staffing threshold preservation

🚨 **Codex/staging testing required before production confidence.** This touches Woo/TEC product image behavior and customer-facing order/email thumbnails.

## Install / version checks
- Install the zip over the current VMS build.
- Confirm WordPress shows VMS version `0.2.24.582`.
- Confirm the 0.2.24.581 staffing-template per-role threshold UI is still present.

## GA ticket image repair checks
- Pick an Event Plan / linked TEC event with a public event image and a GA ticket product that is currently missing its Woo product featured image.
- Go to **VMS > Settings > Ticketing Image Tools**.
- Click **Sync Ticket Images to Woo Products**.
- Confirm the GA Woo ticket product now has the event image.
- Confirm qualified ticket / entitlement products and add-on products still retain or receive their expected images.

## Checkout self-heal checks
- Create or identify a GA ticket product missing its featured image but linked to an Event Plan / TEC event.
- Add a GA ticket to cart and proceed through checkout far enough to create an order in staging.
- Confirm the GA ticket line in the order/customer email view shows the event image after checkout self-heal.
- Confirm the order line still includes normal Event / Event Date metadata.

## Image fallback checks
- Confirm image resolution priority:
  1. Event Plan featured image
  2. Linked TEC event featured image
  3. Primary vendor featured image fallback
- Confirm a ticket configured with image mode `none` does not receive an image.
- Confirm a ticket configured with a custom image still prefers that custom image.

## Legacy product checks
- Test an older VMS-linked TEC ticket product that has Event Plan + TEC event/product markers but no explicit `_vms_product_role`.
- Confirm it is treated as a GA ticket for image sync/self-heal purposes.

## Staffing regression checks from 0.2.24.581
- Create/edit a staffing template with different **Activate at attendance** values per role.
- Save and reopen the template; confirm each role kept its own threshold.
- Apply the template to an Event Plan; confirm role thresholds carry into the Event Plan.
- Use **Replace staffing from template** and confirm omitted old role thresholds are cleared.
- Confirm existing templates created before 0.2.24.581 still load without fatal errors.
