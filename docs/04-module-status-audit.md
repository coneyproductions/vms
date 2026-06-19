---
title: 04 Module Status Audit
slug: module-status-audit
since: 0.2.24.455
---

# Module Status Audit

This file records the current read of what is actively bootstrapped in core versus what is staged, dormant, or residual.

## Purpose

The point of this audit is to reduce ambiguity before more major features land. A rich plugin can still become fragile when too many files look present but do not share one clear load path.

## Active in the canonical core bootstrap

### Core and shared foundation

- Core registry constants and schema registries
- Core load path (`includes/core/load.php`)
- Integrations load path (`includes/integrations/load.php`)
- REST load path (`includes/rest/load.php`)
- Public load path (`includes/public/load.php`)
- Social share load path (`includes/social-share/load.php`)
- Admin load path (`includes/admin/load.php`, admin only)
- Support load path (`includes/support/load.php`)
- Module load path (`includes/modules/load.php`)
- Docs registry / renderer / public docs / admin docs page

### Active feature groups seen in bootstrap

- Event Plans
- Vendors / vendor applications / vendor user links
- Venues
- Staff
- Cancellations
- Payables / ticket revenue / notifications
- Vendor booking onboarding
- Due dates / obligations
- Staffing core
- Ticketing core and Ticketing Phase B
- Ticket claims / verifications
- Ticket mutation audit / inventory forensics / integrity checks / cron / daily report
- Public venue calendar / vendor profiles / ICS
- Social sharing providers and queue system
- Admissions module
- Status notices module
- Staff Tasks module
- Availability & Date Dispatch module (toggle-aware)

## Active but structurally high-risk

These appear live and valuable, but large enough to deserve extra caution:

- `includes/cpt/event-plans.php`
- `includes/integrations/ticketing.php`
- `includes/integrations/ticketing-phase-b.php`
- `includes/integrations/ticketing-rules-v2.php`
- `includes/portal/vendor-portal.php`
- `includes/modules/staff-tasks/admin-ui.php`

## Staged / placeholder-backed

These exist in the zip but still read like staged screens rather than fully realized finished modules:

- Dashboard Operations view
- Dashboard Finance view
- Dashboard Onboarding & Health view
- Ops Console Teams page
- Ops Console Alert Presets page

## Dormant or intentionally not bootstrapped from core

### Safety

`includes/safety/` exists and contains real code, but it is not loaded from the canonical bootstrap in this build. Treat it as parked work, not a live core subsystem.

### Express Bar

Express Bar remnants still exist in comments and surrounding structure, but core loader comments say it moved to a standalone module. Treat it as out-of-core.

## Legacy residue / cleanup targets

These are the kinds of things still worth cleaning in future passes:

- duplicate or overly indirect loader references
- large files that mix rendering, business logic, and inline script output
- admin files still relying on path workarounds instead of normalized local includes
- comments that describe old ownership states without clearly parking the old path

## Current conclusion

The plugin is not a house of cards yet, but it is definitely at the stage where **clarity of ownership** matters as much as feature count.

The best immediate structural move after this audit is:

1. keep tightening loader clarity
2. split Event Plans into smaller owned files
3. keep ticketing on one canonical path
4. continue moving styling and scripting out of inline output as touched
