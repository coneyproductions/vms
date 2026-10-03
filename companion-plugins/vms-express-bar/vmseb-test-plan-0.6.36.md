# VMS Express Bar 0.6.36 test plan

1. Run PHP and JavaScript syntax checks plus every existing Express Bar regression.
2. Run `tests/admin-operator-ux-0.6.36.php` and confirm simple, variable, variation, and Event Plan operator UI behavior.
3. Confirm an Event Plan save without `vmseb_auto_embed` preserves the legacy metadata value.
4. Confirm default ordering-window inheritance and explicit overrides still pass.
5. Confirm the ticket-flow offer hook and accepted frontend file hashes are unchanged.
6. Run Plugin Check for changed PHP and `git diff --check`.
7. On staging, inspect simple and variable Woo products, an Event Plan metabox, Bar Menu, and the public Express Bar page.
