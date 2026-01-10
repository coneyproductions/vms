# vms

## Development Notes

*** ADMIN MENU ***
- Can we make the default event time UI editable? Each venue has different times & durations
- Where are holidays entered?

## EVENT PLAN ##
- Add food trucks to UI

## VENDOR ADMIN ##
✅ Collapse months
- Are we already collecting requested pay rate? I imagine that will eventually be admin managed.
- Display vendor entered information on admin side
✅ Track W9 details
- Flag W9 if pay over $600 and not already on file
✅ Check payable to...
- All W9 fields should be populated by the vendor in the portal (not SSN/EIN?)
- Should overhead variables be included?
 - FIXED: marketing, labor, utilities, [expandable]  in compensation package
 - VARIABLE: insurance, ASCAP, BMI, SESAC [expandable]
- Add labor (still uses portal, just not )
    - Bar
    - Sound
    - Cleanup
    - Gate

## VENDOR PORTAL ##
- Small visual tweak: format telephone #
- Section for vendors/labor to see their events/terms
- Autofill contract (manually approved by admin before submitting to vendor)

## REPORTING - VENDORS ##
- Events completed
- paid amount
- paid date
- paid method
- paid confirmation


### GENERAL QUESTIONS ###
Q: Is this plugin "upload ready" to any wordpress website?
A: Yes! With TEC as a requirement
Q: Is it possible to disable tickets? Should I bother? Other venues aren't ticketed
A: 

Q: Do holidays show up somewhere in the event plan?
A: 

Q: 

### Commit workflow
```bash
git status
git add .
git commit -m "Add tax input on vendor side"
git push origin main
 