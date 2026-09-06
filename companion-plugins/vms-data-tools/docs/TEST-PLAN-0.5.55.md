# VMS Data Tools 0.5.55 Test Plan

- Load with Backstage Venue Manager before and after Data Tools; confirm one `vms-data-tools` provider registration in both orders.
- Invoke the provider for Event Command Center and vendor-portal scopes; confirm valid, valid-empty, website-paid, free-ticket, Square/POS, revenue, warning/error, and provenance fields.
- Leave Data Tools inactive with its directory present, then remove it from the disposable plugin tree; confirm BVM neither includes Data Tools files nor registers Data Tools hooks in either state.
- Force provider exceptions and unavailable results; confirm BVM isolates the failure and uses its supported Woo/core fallback.
- Run the official and additional compatibility matrices under the hardened no-network/residue containment harness.
