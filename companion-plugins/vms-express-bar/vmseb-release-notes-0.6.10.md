# VMS Express Bar 0.6.10 - catalog registry refresh and stale product guard

## Summary

This pass prevents deleted/trashed Woo products from continuing to render on the public Express Bar page when their old Bar Menu registry rows still exist.

## Changes

- Public/catalog item resolution now rejects Woo products and variations whose posts are not `publish` or `private`.
- Deleted/trashed products are suppressed from the public menu even if their old registry token remains saved.
- Added a **Catalog Refresh** card to **VMS → Bar Menu**.
- Added **Refresh Bar Menu Registry** action to prune deleted/unavailable Woo items from the saved Express Bar registry.
- Preserved current product truth direction: product names, prices, categories, images, and inventory remain sourced from Woo/Square; Express Bar only stores operational menu flags.

## Why

During Square/Woo import testing, old Woo items that had been deleted/trash-cleared could still appear in Express Bar because their saved registry entries referenced old Woo product IDs. Express Bar now treats unavailable Woo products as stale and hides/prunes them.
