# VMS 0.2.24.697

- Queues verification submission notification emails asynchronously so proof uploads no longer wait on synchronous `wp_mail()` delivery.
- Defers Woo/Event Tickets ticket-email sending out of the `/?wc-ajax=checkout` request when the order completes through classic Woo AJAX checkout.
- Preserves checkout validation, event/ticket line-item metadata, stock handling, and verification file restrictions.
