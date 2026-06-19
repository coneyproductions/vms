# VMS 0.2.24.559 Test Plan — Individual Guest Pass QR Codes

## Install
1. Upload and activate the zip over the current VMS build.
2. Confirm the VMS build stamp shows `0.2.24.559`.

## Guest Pass Claim Quantity
1. Create a Guest Pass batch with `Number of Claim Links = 2` and `Admissions per Claimed Link = 4`.
2. Open one claim link.
3. Confirm the “How many people will use this pass?” control has +/- buttons and cannot exceed 4.
4. Try typing 5 and confirm it is clamped or rejected with a clear validation message.

## Individual QR Codes
1. Claim one link for 4 people.
2. Confirm the success page shows 4 individual QR codes.
3. Click View / Print Passes.
4. Confirm the printable pass page shows 4 individual QR codes.
5. Confirm each QR/reference is unique.

## Email
1. Claim a pass with an email address.
2. Confirm the email includes all individual QR codes and a View / Print Passes button.
3. Use Resend Email from admin and confirm the resent email includes the individual QR codes.

## Door Check-in
1. Scan/check in the first QR code only.
2. Confirm only that individual pass is checked in.
3. Scan/check in another QR from the same claim.
4. Confirm it checks in separately.

## Reporting Spot Check
1. Confirm Event Command Center Guest list / comps reflects the VMS admissions headcount for the selected event.
2. Confirm paid ticket counts/revenue do not increase from Guest Pass claims.
