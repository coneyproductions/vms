# VMS 0.2.24.745 Test Plan — Sale Ticket Card Title Order

1. Confirm version markers show `0.2.24.745` in the plugin header, `VMS_VERSION`, and `vms-build.txt`.
2. Run `php -l vendor-management-system.php`, `php -l includes/core/registry/constants.php`, `php -l includes/integrations/ticketing-rules-v2.php`, and `node --check assets/vms-ticketing-front.js`.
3. Open the Taylor public event page and confirm the active sale ticket card renders in this order:
   `ON SALE`
   `General Admission`
   `Early Bird: 43 available at $15.00 • Ends Aug 12`
4. Confirm the General Admission sale row still shows the same sale price, regular price, and quantity controls.
5. Confirm Youth, Children, Veteran, Police/Fire/EMT, and Teacher ticket cards still render in their prior order with no sale meta injected above their titles.
6. Confirm the page shows no JavaScript errors and no PHP warnings/fatals introduced by this patch.
