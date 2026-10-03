# VMS Express Bar 0.6.35 — staging test plan

1. Confirm `vms-build.txt` and the active plugin metadata report `0.6.35`.
2. Open **BVM → Planning → Bar Menu** and confirm `admin.css` and `admin.js` load.
3. Search by title, Woo category, SKU, product ID, variation ID, and variation/source label.
4. Confirm the registry summary counts and **New — not configured** badges are accurate.
5. Enable an unconfigured item with **Show in Express Bar**, save the row, reload, then disable and save it again.
6. Confirm bucket, birthday gate, sort, online cap, and low-stock threshold values survive both membership changes.
7. On a simple Woo product, toggle **Show in Express Bar** and confirm the Bar Menu reflects the same state without changing Woo catalog visibility.
8. On a variable Woo product, repeat the check for one variation and confirm the parent product does not overwrite other variations.
9. Confirm the Woo category is displayed as the Express Bar section and that renaming the Woo category changes public grouping without editing the registry.
10. Confirm an Event Plan metabox says it uses the global Express Bar Menu and retains enable, auto-link, explicit times, headline, and pickup overrides.
11. Confirm explicit Event Plan ordering times beat global defaults; blank times inherit defaults when automatic defaults are enabled.
12. Confirm a Bar Menu change invalidates the dedicated public-page cache.
13. Compare the customer-facing Express Bar to 0.6.34 for unchanged layout, styling, interaction, and ordering behavior outside the corrected inherited default window.
