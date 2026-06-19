# VMS 0.2.24.676 Test Plan — Shared Ticket Ratio Allowance Groups

## A. Version markers

1. Install the build on staging.
2. Confirm plugin header, `VMS_VERSION`, and `vms-build.txt` all show `0.2.24.676`.

## B. Admin configuration smoke

1. Open a test Event Plan.
2. Add or edit three ticket rows:
   - Adult/GA, paid, `Counts toward add-on unlock` checked.
   - 8 & Under, limited/free, `Limit by qualifying tickets` checked, `Max per qualifying ticket = 3`, `Shared allowance group = youth`, `Counts toward add-on unlock` unchecked.
   - Youth 9-18, limited/paid-or-discounted as desired, `Limit by qualifying tickets` checked, `Max per qualifying ticket = 3`, `Shared allowance group = youth`, `Counts toward add-on unlock` unchecked.
3. Save config and reopen the Event Plan.
4. Confirm both limited rows still show group `youth`.

## C. Public/cart enforcement

Using a clean browser/cart:

1. Try 0 Adult + 1 limited youth/child ticket. Expected: blocked.
2. Try 1 Adult + 3 total limited tickets split across the two youth/child rows. Expected: allowed.
3. Try 1 Adult + 4 total limited tickets split across the two youth/child rows. Expected: blocked with a shared group limit notice.
4. Try 2 Adults + 6 total limited tickets split across the two youth/child rows. Expected: allowed.
5. Try 2 Adults + 7 total limited tickets split across the two youth/child rows. Expected: blocked.

## D. Regression checks

1. Confirm a single limited ticket row with no shared group still behaves like `0.2.24.675`.
2. Confirm fire pit/table add-on qualifying-ticket rules still work.
3. Confirm verified/free tickets still do not count toward add-on unlock when unchecked.
4. Confirm checkout validation blocks an already-invalid cart even if the customer bypasses the event page UI.

## E. Syntax/package validation

Run:

```bash
find vms -name '*.php' -print0 | xargs -0 -n1 php -l
node --check vms/assets/admin-ticketing.js
zip -T VMS_676_shared_ticket_ratio_groups.zip
```
