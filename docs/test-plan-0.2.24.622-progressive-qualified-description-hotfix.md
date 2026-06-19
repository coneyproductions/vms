# VMS Test Plan — 0.2.24.622 Progressive Qualified Description Hotfix

## Scope

Validate the Progressive ticket UI hotfix that restores row-level descriptions for qualified ticket rows without reopening the deeper qualification helper panels before selection.

## Install / version checks

1. Install/activate the packaged zip on staging.
2. Confirm `vms-build.txt` reports `0.2.24.622`.
3. Confirm the WP plugin header and `VMS_VERSION` constant both report `0.2.24.622`.

## Regression target

On an event with General Admission, Veteran Admission, Police/Fire/EMT Admission, and Amenities/add-ons:

1. Confirm the public purchase UI is still running Progressive mode.
2. Confirm there is one visible `Tickets` heading and no duplicate `Admission` heading.
3. At quantity 0, confirm qualified ticket rows show their short row-level descriptions, including:
   - `Qualified ticket. Veteran Admission requires an approved account...`
   - `Qualified ticket. Police, Fire Fighter, EMT Admission requires an approved account...`
4. Inspect the qualified description nodes if needed and confirm they are not computed as `display: none`.
5. Confirm deeper helper/claim/account panels remain hidden at quantity 0.
6. Increase a qualified ticket quantity to 1 and confirm the relevant helper/claim/account panel appears.
7. Reset the qualified ticket quantity to 0 and confirm the helper/claim/account panel hides again while the short row description remains visible.

## Layout regression

Test desktop plus mobile/tablet widths around 390px, 430px, 768px, and 1024px.

1. Confirm ticket/add-on cards remain inside the viewport with no horizontal overflow.
2. Confirm the 0.2.24.621 mobile/tablet flattening still removes the excessive nested-box look.
3. Confirm Amenities remains collapsed by default, title is `Amenities`, and helper subtext remains visible.
4. Expand Amenities and confirm add-on cards, quantity controls, price, and gating status remain inside the card.

## Business-rule regression

1. Confirm add-on gating still requires the configured ticket count before add-ons can be selected.
2. Confirm adding tickets/add-ons to cart still works.
3. Confirm no changes to Woo product IDs, TEC ticket IDs, cart line items, prices, inventory, or event/add-on eligibility.

## Rollback

If the hotfix causes new layout issues, disable Progressive layout in VMS ticket UI settings or roll back to `0.2.24.621`.
