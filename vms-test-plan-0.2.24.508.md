# VMS Test Plan — 0.2.24.508

## Scope
Band vendor application booking-context refresh on top of 0.2.24.507.

## Verify
1. Open the public **Vendor Application** form.
2. Select **Band / Artist** and confirm the band-only booking note appears above the band questions, calling out full concert sound, stage lighting, and experienced sound engineering support.
3. Confirm **Typical turnout you bring in this region** is shown for bands only, uses the expected ranges, and becomes required when **Band / Artist** is selected.
4. Confirm **Requested compensation for a show like this** is shown for bands only, becomes required when **Band / Artist** is selected, and includes the softer helper copy about rough numbers being acceptable.
5. Confirm the optional **Compensation notes** and **Audience / following notes** textareas appear for bands only.
6. Submit a band application without turnout or compensation and confirm the form rejects it with the new validation message.
7. Submit a complete band application and confirm the saved application record + admin email include turnout, requested compensation, compensation notes, and audience/following notes.
8. Approve/sync the application and confirm the vendor record receives the new band metadata fields without breaking the existing application approval flow.
9. Select **Food Truck** (or another non-band type) and confirm the new band-only fields stay hidden and are not required.

## Guardrails
- No food-truck application regressions
- No changes to Turnstile behavior
- No changes to approval permissions or status flow
- No changes to the non-band application field requirements
