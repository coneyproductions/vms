# VMS Express Bar 0.6.35

- Loads Express Bar admin assets from the exact submenu hooks returned by WordPress under both Backstage Venue Manager and the historical parent.
- Makes the Bar Menu registry the clear primary management surface with useful counts, searchable product metadata, unconfigured-item badges, Woo-category guidance, and truthful cleanup wording.
- Keeps current WooCommerce product categories canonical for public grouping while retaining an old stored category only as a fallback when Woo has no category.
- Adds simple-product and per-variation **Show in Express Bar** controls to WooCommerce product editing. Both controls update the existing `vmseb_catalog_registry` row and preserve every operational field.
- Makes Event Plan global-menu inheritance explicit and preserves global default ordering-window inheritance with explicit event times taking precedence.
- Leaves the accepted public PHP template, public JavaScript, and public CSS unchanged from 0.6.34.
