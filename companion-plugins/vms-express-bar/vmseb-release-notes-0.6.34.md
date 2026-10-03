# VMS Express Bar 0.6.34

- Converts the final persisted Express Bar gratuity from a Woo fee line to a hidden virtual product order line after order creation.
- Checkout UX, tip calculations, and order totals remain unchanged.
- Adds a protected internal carrier product (`VMS-ONLINE-TIP-CARRIER`) with role `online_tip`, allowing the BVM Square Reporting add-on to attach the stable `ONLINE TIPS` Square reporting variation.
- Preserves existing tip order metadata and labels.
