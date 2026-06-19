# VMS Test Plan — 0.2.24.511

## Scope
Band application intro card presentation polish only. No form logic changes intended.

## Verify
1. Install the zip and open the public vendor application form.
2. Select **Band / Artist**.
3. Confirm the intro card shows a single title: **Performance details**.
4. Confirm the old eyebrow badge and the old pills/chips are gone.
5. Confirm the production support is shown as a simple list:
   - Full concert sound
   - Stage lighting
   - An experienced sound engineer
6. Confirm the follow-up sentence still explains that requests are reviewed based on fit, availability, expected turnout, promotion, and requested compensation.
7. Confirm **Typical turnout for your shows in this region** still appears for bands only.
8. Confirm **Requested compensation for a show like this** still appears for bands only and is still required when **Band / Artist** is selected.
9. Switch to non-band types and confirm the band-only card and fields remain hidden.
10. Submit one test band application and one non-band application to confirm no regressions.
