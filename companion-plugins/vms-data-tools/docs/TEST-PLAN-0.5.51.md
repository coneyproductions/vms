# VMS Data Tools 0.5.51 Test Plan

## A. Version

1. Confirm `vms-data-tools/vms-data-tools.php` reports `Version: 0.5.51`.
2. Confirm `VMS_DT_VERSION` is `0.5.51`.
3. Confirm `vms-data-tools/vms-build.txt` reports `Build: 0.5.51`.

## B. Single Event summary

1. Open `/wp-admin/admin.php?page=vms-dt-report-single-event&event_plan_id=<event_id>`.
2. Confirm the `Processor cash and attribution` section renders without PHP fatal or browser error.
3. Confirm the card set includes:
   - `Website-originated portion`
   - `Square total collected`
   - `Direct Square / POS portion`
   - `Current profitability basis`
   - `Tax collected`
   - `Tips`
   - `Refunds / overlap held`
4. Confirm `Gross Cashflow Seen` no longer appears as a headline card.

## C. Single Event supporting data

1. Click at least these support links from the summary cards:
   - `Website-originated portion`
   - `Square total collected`
   - `Direct Square / POS portion`
   - `Current profitability basis`
2. Expected:
   - The URL gains `vms_dt_detail=single_event_supporting` when supporting detail is deferred.
   - The intended anchor is preserved.
   - The page lands on the supporting section instead of appearing to do nothing.

3. In supporting data, confirm:
   - `Processor cash and attribution breakdown` appears.
   - `Processor cash and profitability snapshot` appears.
   - `Held ticket-like Square gross`, `tax`, and `net` are visible.
   - `Future-event Square ticket rows in this time window` appears only when applicable.

## D. Match check and side-by-side summary

1. Open the Single Event `Match check`.
2. Confirm `Square total collected` is described as processor total collected.
3. Confirm `Website-originated portion` is described as attribution already inside Square when Square processes website orders.

4. Open `/wp-admin/admin.php?page=vms-dt-report-compare-events`.
5. Confirm the side-by-side summary shows:
   - `Square total collected`
   - `Website-originated portion`
   - `Direct Square / POS portion`
6. Confirm it does not show an additive combined collected-cash headline.

## E. Revenue Intelligence

1. Open `/wp-admin/admin.php?page=vms-dt-revenue-intelligence`.
2. Confirm overview cards render without fatal/error.
3. Confirm the KPI cards include:
   - `Current profitability basis`
   - `Square total collected`
   - `Website-originated attribution`
   - `Direct Square / POS portion`
4. Confirm the old additive framing does not appear in the headline cards.
5. Confirm the composition section is labeled `Profitability basis composition`.
6. Confirm the event table is labeled `Event-by-event profitability table`.

## F. CSV export

1. From Revenue Intelligence, download the event CSV.
2. Confirm the header row includes:
   - `Website-Originated Total`
   - `Square Processor Collected`
   - `Direct Square/POS Portion`
   - `Current Profitability Basis`
3. Confirm the CSV does not label website-originated totals as standalone collected cash.

## G. Regression checks

1. Confirm Ticket Pace / Quick Read math and labels are unchanged unless directly touched by the new cash-vs-attribution wording.
2. Confirm no duplicate-id anchor behavior regressed in Single Event supporting links.
3. Confirm Revenue Intelligence compare cards and top-event ranking still render normally.

## H. Technical verification

Run:

```bash
php -l vms-data-tools.php
php -l includes/admin/menu.php
php -l includes/admin/page-reporting-module.php
php -l includes/admin/page-revenue-intelligence.php
```

Expected:

- No syntax errors.
- Pre-existing PHP deprecation notices may appear for implicitly nullable parameters in `page-revenue-intelligence.php`, but lint should still pass.

## I. Staging smoke checklist

1. Confirm the environment is staging before install.
2. Confirm the canonical install path is `wp-content/plugins/vms-data-tools/`.
3. Install only `vms-data-tools-0.5.51.zip`.
4. Confirm the active plugin version shows `0.5.51`.
5. Open one Single Event Report with Square data.
6. Confirm the summary renders with no fatal/error.
7. Confirm support links add `vms_dt_detail=single_event_supporting` and preserve anchors.
8. Confirm the new processor-cash labels appear.
9. Open Revenue Intelligence and confirm the new KPI labels appear.
10. Download the CSV and confirm the new headers appear.
11. Check browser console and PHP error log.
12. Stop if repeated `admin-ajax.php` or `wp-json` loops, fatal errors, or abnormal resource spikes appear.

## J. Known local constraint

If the local site database is unavailable, record the failure and treat syntax checks plus staging/browser verification as the required release gate for this Phase 1 build.
