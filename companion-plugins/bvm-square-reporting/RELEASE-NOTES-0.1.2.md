# BVM Square Reporting 0.1.2

- Restores each eligible Square order line to the exact historical Woo/BVM order-item name after `createOrder`.
- Preserves the stable Square reporting catalog variation and category by submitting only the existing line UID and corrected name.
- Leaves quantities, prices, taxes, discounts, refunds, and unrelated Square lines unchanged.
- Fails closed when the Woo reporting class, reporting variation, Square catalog object ID, line UID, or Woo order reference cannot be verified.
- Prevents request re-entry and logs Square update failures without interrupting WooCommerce checkout or payment.
