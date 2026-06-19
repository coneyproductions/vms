# Codex Handoff — VMS 0.2.24.694

## Focus

Ship a follow-up State of the Range accuracy fix so report recipients see coherent ticket counts/revenue labels instead of a row that mixes Woo analytics, `total_sales`, stock, and public-only ticket filtering.

## High-priority assertions

1. State of the Range must count completed ticket quantity across all active mapped ticket rows, including verified/qualified zero-dollar rows.
2. `Paid sold`, `Free/qualified sold`, and `Gross` must be derived from the same completed-order basis as `Sold`.
3. `Available inventory` must reflect current ticket availability, not a naive `capacity - sold` arithmetic label.
4. `Ticket capacity` must remain the sum of active mapped ticket-row inventory totals and be labeled accordingly.
5. The plain-text email body must no longer leak HTML entities such as `&#36;`, `&#8211;`, or `&amp;`.

## Known scope

This build changes only State of the Range rendering/metric logic. It does not move cron schedules, alter the Ticket Integrity scan window, or change the core memory-fix behavior delivered in `0.2.24.693`.

## Release package

- Versioned zip filename: `vms-0.2.24.694.zip`
- Canonical plugin folder inside the zip: `vms/`
