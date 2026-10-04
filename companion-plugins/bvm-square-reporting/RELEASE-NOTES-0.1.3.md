# BVM Square Reporting 0.1.3

- Registers the existing fail-closed line-name restorer for WooCommerce Square Cash App Pay create-order responses.
- Detects Square orders pre-created by checkout integrations such as the native-discount bridge and restores their names before payment.
- Retrieves only the already-created Square order, then submits the same uid/name-only update used by 0.1.2.
- Leaves reporting SKUs, catalog objects, quantities, prices, taxes, discounts, refunds, and unrelated Square lines unchanged.
- Repeated invocation is idempotent: an already-correct Square order produces no update and no duplicate order.
