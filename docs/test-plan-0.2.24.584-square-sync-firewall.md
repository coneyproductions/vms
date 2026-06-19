# Test Plan — 0.2.24.584 Square Sync Protection Firewall

## Version checks
1. Confirm WordPress shows VMS version `0.2.24.584`.
2. Confirm `vms/vms-build.txt` contains `0.2.24.584`.
3. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.584`.

## Square settings sanity check
1. Open WooCommerce > Settings > Square.
2. Confirm Square payments remain connected as expected.
3. Confirm product/catalog sync remains disabled unless deliberately testing a staging-only sync scenario.
4. Confirm Square fulfillment sync and Square discount-code processing are not required for ticket/admission workflows.

## Protection page smoke test
1. Open **VMS > Square Sync Protection**.
2. Confirm the screen loads without PHP warnings or fatal errors.
3. Click **Scan protected products**.
4. Confirm the report identifies VMS/TEC products such as Online Ticket / Online Addon / `VMS-*` products and skips normal catalog items.
5. Download the CSV report and confirm it includes Product ID, Name, SKU, reason, sync value, Square link status, and cleared-meta count.

## Repair test
1. On staging, pick one known VMS/TEC ticket or event add-on product that has stale Square metadata or `Sync with Square = yes`.
2. Click **Repair protected products**.
3. Confirm the product is now `Sync with Square = no`.
4. Confirm stale Square link metadata (`_square_item_id`, `_square_item_variation_id`, versions/images) is removed from that protected product.
5. Confirm the product has `_vms_square_sync_protected` and `_vms_square_sync_protection_reason` meta.

## Regression checks
1. Create or update an Event Plan ticket/add-on config and commit.
2. Confirm VMS/TEC-generated products remain `Sync with Square = no` after save/commit.
3. Confirm GA ticket inventory, add-on inventory, qualified-ticket behavior, subtotal math, and checkout still behave normally.
4. Confirm normal Square-owned products such as shirts, eggs, or express-menu items are not protected merely because they appear in an event page/menu context.
5. Confirm existing Email Follow-Ups, Staffing Templates, Ticketing Image Tools, and Ticket Integrity pages still load.

## Production caution
🚨 Run this on staging first if available. The repair tool is intentionally targeted, but it does remove stale Square catalog IDs from protected VMS/TEC products so Square cannot later treat those admission/event-control products as Square-managed catalog inventory.
