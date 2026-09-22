# BVM Square Reporting 0.1.1

- Keeps the 0.1.0 four-class reporting model unchanged.
- Makes the `woocommerce_checkout_order_created` reconciliation idempotent.
- Persisted late-added lines are updated through Woo order-item metadata APIs rather than re-saving a stale in-memory item, preventing duplicate reporting meta rows.
- No Square category names, SKUs, object IDs, checkout totals, or product-sync behavior changed.
