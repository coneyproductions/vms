# VMS Data Tools 0.5.56 Test Plan

- Feed production-shaped BVM revenue rows through the canonical website classifier: 136 paid admissions, 29 genuine free admissions, 2 event-linked rentals, an add-on, and merchandise. Confirm only the 165 admission rows reach ticket rollup and only 136 are paid.
- Feed six production-shaped Square direct-ticket lines totaling quantity 8 through the provider-owned money reader with the admin-only Square helper absent. Confirm raw positive `total_money` establishes paid status even when legacy evidence totals are zero.
- Include a genuine explicit zero-value Square admission and a separate line with monetary fields absent. Confirm the former is free and the latter is unknown; neither becomes paid, and unknown does not enter complimentary categories.
- Confirm the combined Vendor Portal result is 136 presales, 8 paid door, 31 comp/guest, 144 final paid, $0 bonus, and $1,500 payout, with no `Ticket — 8` complimentary category.
- Re-run issue #9 Square line-name/classification regressions, provider/load-order coverage, financial authority, compatibility matrices, PHP lint, and `git diff --check`.
- Deploy only to staging, verify Data Tools 0.5.56/BVM 1.3.1 identity, and repeat Piano Man desktop/mobile Event History acceptance. Do not seed staging transaction data and do not deploy production.
