# VMS Data Tools 0.5.56 Build Notes

- Classifies website attendance only from canonical BVM `ticket` rows; ancillary, unknown, and non-ticket product roles remain outside admission counts.
- Reads Square line-item money directly from `total_money`, tax, discount, gross, variation-total, and base-price objects in the provider runtime instead of depending on an admin-only helper.
- Carries an explicit paid/free/unknown Square admission status into rollup and complimentary detail. Missing monetary evidence is unknown and is not treated as complimentary.
- Preserves the configured-location fallback when an Event Plan has no saved Square location.
- Leaves Square Reporting, Square/Woo synchronization, payout math, persistence, activation, scheduling, and the approved Vendor Portal layout unchanged.
