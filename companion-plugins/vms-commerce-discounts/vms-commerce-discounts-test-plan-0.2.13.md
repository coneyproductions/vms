# VMS Commerce Discounts 0.2.13 Test Plan

## Goal

Confirm that Woo cart transaction data distinguishes an explicitly recorded zero subtotal from absent subtotal data without changing existing paid-discount, display, order, or activation contracts.

## Cart qualification and allocation scenarios

1. Four paid tickets qualify for the existing four-ticket rule and receive the expected discount.
2. Three paid tickets remain below the four-ticket threshold.
3. Three paid tickets plus one explicitly zero-dollar ticket do not qualify.
4. Four paid tickets plus one explicitly zero-dollar ticket qualify using only the four paid tickets; the zero-dollar line receives no allocation.
5. Two paid tickets plus two explicitly zero-dollar tickets do not qualify.
6. An all-comp/free cart does not qualify and receives no allocation.
7. An explicitly recorded `line_subtotal` or `line_total` of zero remains zero even when the live product price is positive.
8. When both recorded subtotal and total are absent, a positive live product price provides the paid-line fallback and can qualify.
9. When transaction data is absent and the live product price is zero, the line remains free and does not qualify.
10. Discounted-but-positive recorded subtotals remain paid, including quantity greater than one, and nonpositive removed/canceled quantities do not count.

## Preserved contracts

1. Repeated cart calculation restores the stored original price before applying the same deterministic adjustment.
2. Classic Cart/Checkout presentation and Store API display data retain their current output contracts.
3. Checkout summaries, order item metadata, order discount totals, ledgers, mapping, rule persistence, settings assets, and admin hooks remain intact.
4. Missing-Square and Square-present activation regression suites remain green with the 0.2.13 source candidate.
5. The 0.2.13 source tree differs from the exact frozen 0.2.12 artifact only in the cart boundary, version/build/readme metadata, and this test plan.

## Dependencies and safety boundary

Run in isolated or disposable local fixtures with WooCommerce and Backstage Venue Manager contracts available. Do not dispatch a payment, Square API request, order mutation, email, webhook, provider request, remote synchronization, or normal-local business-data write during these tests.
