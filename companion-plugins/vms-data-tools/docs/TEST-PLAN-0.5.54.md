# VMS Data Tools 0.5.54 Test Plan

- Load with public Backstage Venue Manager 1.2.0 and no active historical VMS plugin.
- Confirm dependency recognition succeeds and the missing-core notice is absent.
- Confirm Data Tools menus, reporting helpers, vendor import schema adapter, REST routes, and guarded runtime modules register.
- Repeat with legacy VMS runtime stubs to confirm fallback resolution.
- Confirm production option hashes and relevant table counts remain unchanged across the in-place update.
