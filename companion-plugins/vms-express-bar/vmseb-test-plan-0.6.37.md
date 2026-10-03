# VMS Express Bar 0.6.37 test plan

1. Confirm the plugin header, `VMSEB_VERSION`, and `vms-build.txt` report `0.6.37`.
2. Run PHP syntax checks for every PHP file and JavaScript syntax checks for every JavaScript file.
3. Run `tests/event-poster-responsive-image-0.6.37.php` and every existing Express Bar regression.
4. On staging, confirm the “Ordering for” card emits `srcset`/`sizes`, uses a source at least as large as the rendered poster, preserves the portrait poster without cover-cropping, and remains compact at desktop and mobile widths.
5. Confirm the selected Event Plan, open/close state, admission notice, products, quantities, review button, and existing cart stay unchanged.
6. Check the browser console, PHP error log, page weight, and relevant caches before production promotion.
