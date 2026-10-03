# VMS Express Bar 0.6.31 Test Plan

## Preflight
1. Confirm staging is running VMS Express Bar 0.6.31.
2. Confirm temporary WPCode snippets **#5981 Express Bar Tips — STAGING** and **#5982 Express Bar Event Safety — STAGING** are disabled.
3. Purge WP Super Cache and object cache.

## A. Event selector scaling
1. With one eligible public Express Bar event, confirm **Change event** is not shown.
2. With two or more eligible events, open **Change event**.
3. Confirm the current event is shown only in the main event card and is not duplicated in the selector.
4. Confirm alternative events retain poster/thumbnail, title, and local date/time.
5. With enough events to exceed the selector height, confirm only the selector list scrolls; the page does not expand to show every card at once.
6. Confirm the Close button/header remain visible while the card list scrolls.
7. On mobile, confirm a one-column scrollable selector with a bounded height.
8. Select an event near the bottom of a long list and confirm routing uses that Event Plan.

## B. Regression
1. Admission-in-cart, admission-on-account, and unconfirmed-admission states still behave as in 0.6.30.
2. Ticket-only checkout has no tip prompt.
3. Express-Bar and mixed checkout show exactly one plugin-native tip prompt after prototype snippets are disabled.
4. Percentage/custom/no-tip calculations and collapse/Change behavior remain correct.
5. Public resolver still excludes unpublished/canceled Event Plans.
6. Age gate, ordering windows, quantity steppers, cart metadata, and fulfillment metadata remain unchanged.
