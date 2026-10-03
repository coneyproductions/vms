# VMS Express Bar 0.6.5

## Server-efficiency pass

- Public menu rendering now uses the Express Bar registry first and only hydrates enabled menu products instead of building the entire Woo product catalog on every customer-facing render.
- Added per-request static caches for normalized Express Bar settings, registry rows, hydrated catalog candidates, and final catalog items.
- Add-to-cart validation now resolves selected item tokens directly instead of rebuilding the full menu catalog for each selected item.
- Front-end auto-embed now avoids shortcode parsing/resolution work on unrelated singular pages and only attempts event-plan resolution for VMS Event Plans / TEC event posts.
- Admin-only code is no longer loaded on normal public page views.
- Product thumbnails on the public menu now use lazy loading/async decoding.

## Dedicated-page foundation

- A shortcode without `event_plan_id` can now resolve the next published Express Bar-enabled Event Plan, making a permanent `/express-bar/` page possible with `[vms_express_bar_menu]`.
- If no Express Bar-enabled event/menu is available, the shortcode renders a simple “Express Bar Closed” state instead of an invalid Event Plan message.
- When ordering is closed but an upcoming/current Express Bar menu exists, the menu remains visible in browse-only mode and order controls stay disabled.
