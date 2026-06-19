# VMS 0.2.24.619 — Public Calendar 16:9 Card Media Test Plan

## Goal
Confirm the public calendar list/card view uses a true 16:9 media frame so event artwork displays larger and more naturally on phone and tablet layouts, while desktop behavior remains intact.

## Preconditions
- VMS 0.2.24.619 installed.
- Public calendar page available with upcoming events that have featured images/posters.
- Test on phone and tablet widths (or responsive emulator).

## Test Steps
1. Open the public calendar on a phone-sized viewport.
2. Confirm the calendar loads in List view automatically.
3. Confirm each event card image is displayed in a 16:9-style media area instead of the prior short strip.
4. Confirm the image fills the card width, crops cleanly, and does not distort.
5. Confirm vendor/title/date/time/excerpt/CTA layout remains intact below the image.
6. Repeat on an iPad/tablet-sized viewport in portrait and landscape.
7. Confirm the View dropdown is still hidden on phone/tablet layouts.
8. On desktop, confirm the calendar still behaves normally and list view cards continue rendering correctly.

## Expected Results
- Phone/tablet list cards show visibly taller media with a consistent 16:9 presentation.
- No broken image sizing, overflow, or layout regressions.
- Mobile/tablet forced List view behavior remains unchanged.
