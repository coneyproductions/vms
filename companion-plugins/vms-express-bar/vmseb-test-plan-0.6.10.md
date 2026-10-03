# VMS Express Bar 0.6.10 Test Plan

🚨 Codex / browser testing recommended. This pass changes catalog item resolution and the Bar Menu registry cleanup action.

## Setup

- Install VMS Express Bar 0.6.10.
- Confirm WooCommerce and VMS are active.
- Use a staging event plan with Express Bar enabled.

## Tests

1. **Deleted product suppression**
   - Enable a Woo product in VMS → Bar Menu.
   - Confirm it appears on `/express-bar/`.
   - Trash or permanently delete that Woo product.
   - Reload `/express-bar/` after clearing page cache.
   - Expected: the deleted product no longer appears.

2. **Registry refresh action**
   - Go to VMS → Bar Menu.
   - Confirm the Catalog Refresh card shows registry/deleted-unavailable counts.
   - Click **Refresh Bar Menu Registry**.
   - Expected: success notice appears and stale/deleted rows are removed from the registry.

3. **Existing live products still render**
   - Confirm enabled published/private Woo products still appear in Express Bar.
   - Confirm names/prices/categories still come from Woo.

4. **Square import flow follow-up**
   - Import Express Bar Square products into Woo.
   - Confirm new Woo products appear in VMS → Bar Menu.
   - Enable one non-alcohol product and one alcohol product.
   - Confirm `/express-bar/` renders only enabled current Woo products.

5. **Checkout guard**
   - If a product is deleted after a customer loads the page, attempting to submit should fail gracefully with the existing invalid-items notice.
