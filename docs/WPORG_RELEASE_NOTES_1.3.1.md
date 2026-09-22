# WPORG Release Notes 1.3.1

## Identity

- Public plugin: `Backstage Venue Manager`
- Public slug and text domain: `backstage-venue-manager`
- Public version: `1.3.1`
- Package entry point: `backstage-venue-manager/backstage-venue-manager.php`

## Ticketing corrections

- Keeps native TEC tickets authoritative in Safe Mode.
- Uses exactly one ticket-surface controller in Progressive.
- Prevents valid ticket rows from disappearing after JavaScript and MutationObservers settle.
- Places the complete add-on/rental purchase region inside the ticket form in canonical order.
- Keeps rental decrement and increment controls pointer-accessible at desktop and mobile widths.
- Preserves ticket products, inventory, prices, orders, qualification, checkout, add-on, and rental semantics.

## Release-engineering boundary

Version `1.3.1` gives the repaired frontend JavaScript and CSS a consistent new public cache-busting identity after 1.3.0 was served; ticket JavaScript no longer substitutes per-file mtimes for that release marker. This note does not authorize production deployment, publication, WordPress.org submission, or external communication.
