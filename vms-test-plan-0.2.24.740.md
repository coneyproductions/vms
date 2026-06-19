# VMS 0.2.24.740 Test Plan — Public Event Vendor Sidebar Grouped Renderer

## Pre-checks

1. Activate VMS `0.2.24.740`.
2. Confirm version markers:
   - plugin header shows `0.2.24.740`
   - `VMS_VERSION` is `0.2.24.740`
   - `vms/vms-build.txt` begins with `0.2.24.740`

## Source-of-truth checks

1. Confirm the public event vendor sidebar renders from Event Plan-owned vendor assignment data.
2. Confirm public rendering does not depend on ADD review state, ADD response state, dispatch logs, or audit tables.
3. Confirm legacy Event Plan fallback fields still keep older Event Plans rendering when canonical lineup/assignment collections are missing.

## Public sidebar acceptance

1. Open a public event page with multiple assigned food vendors.
2. Confirm all assigned public food vendors appear under one `Food Vendors` heading.
3. Confirm the sidebar does not show `Primary`, `Secondary`, or `Internal` assignment labels.
4. Confirm empty vendor groups do not render.
5. Confirm vendors without logos show a neutral placeholder instead of a broken image.
6. Confirm vendors without cuisine/sub-category do not show empty meta rows or stray punctuation.

## Mixed vendor scenarios

1. Check an event/page state with one music vendor and one food vendor.
2. Confirm music and food render as separate grouped sections.
3. Check an event/page state with one music vendor and two food vendors.
4. Confirm the food section still uses the shared `Food Vendors` heading and shows both food cards.
5. Check an event/page state with a market vendor assignment.
6. Confirm `Market Vendor` renders as its own section below the food section.

## Single-vendor and empty-state behavior

1. Open a public event with exactly one assigned public vendor.
2. Confirm the sidebar still uses the grouped container/card layout rather than falling back to older teaser-only output.
3. Open a public event with no assigned public vendors.
4. Confirm no empty vendor sidebar block renders.

## Legacy shortcode compatibility

1. On an event page or fixture that still includes both:
   - `vms_vendor_teaser`
   - `vms_secondary_vendor_teaser`
2. Confirm only one grouped public vendor sidebar renders.

## Responsive check

1. Check the sidebar at desktop width.
2. Confirm two food vendors fit comfortably without oversized empty space.
3. Check the sidebar at a narrow/mobile width.
4. Confirm vendor cards remain readable, stack cleanly by section, and do not collapse into unreadably tiny tiles.
