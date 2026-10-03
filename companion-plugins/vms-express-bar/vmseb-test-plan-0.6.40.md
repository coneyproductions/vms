# VMS Express Bar 0.6.40 test plan

1. Confirm the plugin header, `VMSEB_VERSION`, and `vms-build.txt` report `0.6.40`.
2. Run PHP syntax checks for every PHP file, JavaScript syntax checks for every JavaScript file, and every standalone Express Bar regression test.
3. Confirm the unchanged customer-facing PHP, JavaScript, and CSS files retain their accepted 0.6.39 hashes.
4. Current Show: select Super Trouper Event Plan 7248 and confirm paid order #8345 is Pending while failed order #8359 is not rendered as fulfillment work.
5. Confirm old unfinished orders are absent from Current Show and appear in Past / Unclaimed; completed past orders must remain in Completed rather than Past / Unclaimed.
6. Confirm future-event orders appear only in Future Orders relative to the selected operational show.
7. Mark a staging test order Ready, toggle ID Verified, and mark it Picked Up. Confirm each value persists independently, the view/event query stays intact, Picked Up immediately leaves Current Show, and the order appears in Completed.
8. Recover the staging test order with Mark Ready from Completed, confirm it returns to the correct working bucket, then restore all staging test metadata to its captured baseline.
9. Confirm item movement totals include only the valid orders/items displayed in the active view and never include failed, cancelled, or refunded orders.
10. Verify desktop and mobile admin layouts: obvious event/view controls, prominent event title/date/time/ID, readable queue rows/cards, usable actions, and no horizontal page overflow.
11. Run a public Express Bar smoke for event selection, item controls, age gate, Review Order, cart routing, and ordering-window behavior; no public mutation or checkout/payment submission is required.
12. Capture staging and production rollback snapshots, deploy only the standalone 0.6.40 changed files after all gates pass, clear relevant caches, and re-run version, hash, HTTP, admin, and error-log checks.
