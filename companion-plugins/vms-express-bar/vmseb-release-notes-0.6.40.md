# VMS Express Bar 0.6.40

- Replaces the undifferentiated admin order list with Current Show, Completed, Past / Unclaimed, and Future Orders fulfillment views.
- Defaults the working queue to the earliest current or upcoming show with a paid Pending or Ready Express Bar order.
- Uses canonical Event Plan dates and times to separate past and future work and makes the selected show identity unmistakable.
- Uses WooCommerce's paid-status policy for actionable fulfillment, preserving paid `processing` and `completed` orders while excluding failed, cancelled, refunded, pending-payment, on-hold, draft, and trash orders from working queues.
- Keeps Picked Up orders recoverable in Completed and retains the existing Pending, Ready, Completed, and ID Verified metadata.
- Returns operators to the same queue view and Event Plan after every fulfillment action.
- Limits item movement totals to the valid orders and event items displayed in the active working view.
- Adds mobile queue cards and clearer responsive event/view controls without changing the customer-facing Express Bar, checkout, Square, tips, discounts, products, tickets, pricing, or public ordering flow.
