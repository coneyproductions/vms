# VMS Express Bar 0.6.12 - order-level checkout tagging parity

## Summary

This pass fixes missing Express Bar order-level meta so orders created from Express Bar items are consistently classified, queued, and reported across classic Woo checkout and block checkout flows.

## Changes

- Added a shared order-item-driven sync routine for Express Bar order meta.
- New Express Bar orders now normalize `_vms_express_bar_queue_status` to `pending`.
- Order-level tagging now derives from order line-item Express Bar meta instead of relying on cart state only.
- Mixed orders with both ticket and Express Bar items are tagged correctly.
- Ticket-only orders are not tagged and stale Express Bar order-level meta is cleared if no bar items remain.
- Added Store API/block checkout hooks so checkout draft/final orders receive the same order-level Express Bar tagging as classic checkout.
- Queue UI now treats legacy `new` values as `pending` for display consistency.
- Bumped plugin header, `VMSEB_VERSION`, and `vms-build.txt` to `0.6.12`.

## Why

Orders containing Express Bar items were missing required order-level metadata in some checkout paths, which prevented them from showing in the Express Bar queue. The fix moves tagging to a single idempotent sync path based on saved order items so both Woo checkout implementations behave the same way.
