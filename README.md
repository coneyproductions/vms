# vms

## Development Notes

*** ADMIN MENU ***
❓ Can we make the default event time UI editable? Each venue has different times & durations
❓ Where are holidays entered?

## EVENT PLAN ##
- Add food trucks to UI

## VENDOR ADMIN ##
✅ Collapse months
❓ Are we already collecting requested pay rate? I imagine that will eventually be admin managed.
✅ Display vendor entered information on admin side
✅ Track W9 details
🙏 Flag W9 if pay over $600 and not already on file
✅ Check payable to...
✅ All W9 fields should be populated by the vendor in the portal (not SSN/EIN?)
❓ Should overhead variables be included?
 - FIXED: marketing, labor, utilities, [expandable]  in compensation package
 - VARIABLE: insurance, ASCAP, BMI, SESAC [expandable]
✅ "Staff" menu currently is NOT under "VMS" heading
🚧 Add general labor contractors to portal
    - Bar
    - Sound
    - Cleanup
    - Ticket checker
    - can these titles be editible in the UI for later expansion?
❓ AVAILABILITY: when a vendor agrees to a date and they put it in their calendar, does the current logic recognize that booking as being at the selected venue? What about cross-venue checking? If we lock a vendor in on a date, but don't want to inadvertently double book them at one of our own other venues.
🙏 Bypass w9 data option? If we have a verbal agreement/understanding that a W9 will be supplied imminently, bypass the requirement, maybe for an established period of time so that it doesn't get forgotten?

## VENDOR PORTAL ##
🙏 Small visual tweak: format telephone #
🙏 Section for vendors/labor to see their events/terms
🙏 Autofill contract (manually approved by admin before submitting to vendor)

## STAFF PORTAL ##
- 

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
git commit -m "added staff portal with tax logic"
git push origin main
 