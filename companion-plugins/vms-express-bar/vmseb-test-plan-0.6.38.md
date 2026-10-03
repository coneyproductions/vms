# VMS Express Bar 0.6.38 test plan

1. Confirm the plugin header, `VMSEB_VERSION`, and `vms-build.txt` report `0.6.38`.
2. Run PHP syntax checks for every PHP file and JavaScript syntax checks for every JavaScript file.
3. Run `tests/event-poster-responsive-image-0.6.37.php` and every existing Express Bar regression.
4. Confirm on the live “Ordering for” card that `srcset`/`sizes` remain present after output sanitization and that the frame has a 3:4 aspect ratio with contained artwork.
5. Confirm the selected Event Plan, open/close state, admission notice, products, quantities, review button, and existing cart remain unchanged.
6. Check desktop/mobile UI, browser console, PHP error log, page weight, and caches before sign-off.
