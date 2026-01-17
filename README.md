# vms

## EMOJIS ##
💡 idea
🙏 wishlist item
❓ question
🚧 in progress
✅ complete


## Development Notes

## INITIAL SETUP ##
✅ Create all public pages
    - Vendor Portal
    - Staff Portal
    - Vendor Application

*** ADMIN MENU ***
✅ Can we make the default event time UI editable? Each venue has different times & durations
🚧 Where are holidays entered?

## EVENT PLAN ##
- Add food trucks to UI
🚧 Month/Calendar view with featured image icons/avatars (with name)

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
✅ Add general labor contractors to portal
❓ AVAILABILITY: when a vendor agrees to a date and they put it in their calendar, does the current logic recognize that booking as being at the selected venue? What about cross-venue checking? If we lock a vendor in on a date, but don't want to inadvertently double book them at one of our own other venues.
✅ Should/can we have a bypass w9 data option? If we have a verbal agreement/understanding that a W9 will be supplied imminently, bypass the requirement, maybe for an established period of time so that it doesn't get forgotten?
💡 Agency management (multiple bands)

## VENDOR PORTAL ##
✅ Small visual tweak: format telephone #
🙏 Section for vendors/labor to see their events/terms
🙏 Autofill contract (manually approved by admin before submitting to vendor)
- Providing own sound tech
- Deposit required
- Weather delay 
- Ticket sales bonus

## STAFF PORTAL ##
- 

## VENUE ADMIN ##
✅ Default pay per day

## REPORTING - VENDORS ##
- Events completed
    - paid amount
    - paid date
    - paid method
    - paid confirmation


### GENERAL QUESTIONS ###
❓: Is this plugin "upload ready" to any wordpress website?
💬: ✅ Yes! With TEC as a requirement

❓: Is it possible to disable tickets? Should I bother? Other venues aren't ticketed
💬: 

❓: What is the best way to integrate VMS with
    Paying vendors/staff via square (square payroll or bill pay [if payroll, we can elimnate w9 storage])
💬:

cd public_html/booklivetalent.com/wp-content/plugins/vms
cd public_html/serenaderange.com/wp-content/plugins/vms

cd wp-content/plugins/vms

### Commit workflow
```bash
git status
git add .
git commit -m "updated the admin schedule UI"
git push origin main