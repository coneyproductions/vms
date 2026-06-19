# VMS Test Plan — 0.2.24.510

## Scope
Visual polish pass for the band-only vendor application booking context card. No intended logic changes beyond swapping touched band-only field sizing from inline styles to shared CSS classes.

## Test Steps
1. Install `vms-0.2.24.510-band-application-intro-polish.zip` and confirm the plugin reports **0.2.24.510** in the Plugins screen and `vms-build.txt`.
2. Visit the public **Vendor Application** page while logged out.
3. Choose a non-band vendor type and confirm the new band booking context card does **not** appear.
4. Choose **Band / Artist** and confirm the band booking context now renders as a polished card rather than a plain alert-style box.
5. Confirm the card shows:
   - `Produced show support included` eyebrow
   - `Performance details` heading
   - copy mentioning full concert sound, stage lighting, and an experienced sound engineer
   - three chips for concert sound, stage lighting, and experienced sound engineer
6. Confirm the following still appear and function for bands only:
   - **Typical turnout for your shows in this region**
   - **Requested compensation for a show like this**
   - **Compensation notes (optional)**
   - **Audience / following notes (optional)**
   - **EPK Link (optional)**
7. Attempt to submit a band application without turnout and compensation. Confirm the existing required validation still blocks submission.
8. Submit a complete band application and confirm admin application details, email output, and saved application data still include turnout, requested compensation, compensation notes, and audience/following notes.
9. Check the band card on mobile width and confirm spacing, chips, and text remain clean and readable.

## Expected Result
The band-only booking context feels more premium and intentional, while all previously added application mechanics continue to work exactly as before.
