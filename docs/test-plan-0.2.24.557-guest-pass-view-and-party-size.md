# VMS 0.2.24.557 Test Plan — Guest Pass View + Party Size Hotfix

## Must test

1. In VMS > Guest Passes > Guest Passes, click View Pass on a claimed pass. It should open a printable pass page and must not 404.
2. Claim a new Guest Pass with an email address. Confirm the email sends and includes a QR code plus a View / Print Pass button.
3. Claim a new Guest Pass with party size 4. Confirm the printable pass says Admits: 4 people and the admission record has party size 4.
4. Scan the QR / paste the token in the door UI. Confirm check-in handles the party size correctly.
5. Confirm old pretty URLs still fail gracefully if rewrite rules are not flushed; new generated links should use query-var URLs.
