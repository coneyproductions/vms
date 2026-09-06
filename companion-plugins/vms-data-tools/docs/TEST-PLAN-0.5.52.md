# VMS Data Tools 0.5.52 Test Plan

## A. Version

1. Confirm `vms-data-tools/vms-data-tools.php` reports `Version: 0.5.52`.
2. Confirm `VMS_DT_VERSION` is `0.5.52`.
3. Confirm `vms-data-tools/vms-build.txt` reports `Build: 0.5.52`.

## B. Revenue Intelligence fatal regression

1. Open `/wp-admin/admin.php?page=vms-dt-revenue-intelligence`.
2. Confirm the page renders without a PHP fatal or HTTP `500`.
3. Confirm the overview cards load normally.
4. Confirm the processor-cash labels shipped in `0.5.51` are still present.
5. Confirm the composition and event-table sections render.

## C. Revenue Intelligence bucket-label helpers

1. Confirm the page can access bucket labels without fataling.
2. Confirm variation bucket override reads do not fatal.
3. Confirm no runtime path depends on any `.bak` file.

## D. Single Event regression smoke

1. Open one Single Event Report.
2. Confirm the page still renders without fatal/error.
3. Confirm the `Processor cash and attribution` card set still shows:
   - `Square total collected`
   - `Website-originated portion`
   - `Direct Square / POS portion`
   - `Current profitability basis`
4. Confirm the old `Gross Cashflow Seen` wording is still absent.

## E. CSV regression

1. From Revenue Intelligence, download the event CSV if data exists.
2. Confirm the CSV still uses the `0.5.51` Phase 1 headers:
   - `Website-Originated Total`
   - `Square Processor Collected`
   - `Direct Square/POS Portion`
   - `Current Profitability Basis`

## F. Technical verification

Run:

```bash
php -l vms-data-tools.php
php -l includes/admin/page-revenue-intelligence.php
```

Expected:

- No syntax errors.
- No undefined-function fatal for `vms_dt_rr_standard_bucket_labels()`.

## G. Staging checklist

1. Confirm the environment is staging before install.
2. Back up the current `wp-content/plugins/vms-data-tools/` folder.
3. Install only `vms-data-tools-0.5.52.zip`.
4. Confirm the active plugin version shows `0.5.52`.
5. Open Revenue Intelligence first and confirm the previous fatal is gone.
6. Open one Single Event Report and confirm the `0.5.51` Phase 1 labels still render.
7. Check browser console and PHP error logs.
8. Stop if Revenue Intelligence still returns `500` or if a new fatal appears.

## H. Known local constraint

If the local site database is unavailable, record the failure and treat syntax checks plus staging/browser verification as the release gate for this hotfix.
