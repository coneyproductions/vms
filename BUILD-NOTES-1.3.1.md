# Build Notes 1.3.1

## Release identity

- Plugin: `Backstage Venue Manager`
- Public slug and text domain: `backstage-venue-manager`
- Version and build marker: `1.3.1`
- Issue #7 commit: `a698fdbfc7701fd0603e20000e31a592710a47f0`
- Issue #8 commit: `2a1e491d7a46a3122abc2d23cca7b1ccc0e1748d`
- Issue #8 tree: `660e22de40b2bfc087885efdcfb824f94e539cbe`

## Release scope

This release retains the certified 1.3.0 unified source and outside-webroot private-storage contract, then adds the Issue #7 ticket-surface ownership repair and Issue #8 rental placement/pointer-layout repair.

Safe Mode leaves native TEC ticket rendering authoritative. Progressive uses one BVM ticket controller. The complete server-rendered purchase region is inside the native ticket form and is adopted as one unit by Progressive. Compact entitlement rows size their action column to its contents so rental decrement and increment controls remain pointer-accessible.

Ticket products, stock, orders, pricing, qualification, checkout, add-on, and rental business semantics are unchanged.

All public ticket JavaScript and CSS handles use the canonical `BVMGR_VERSION` release marker. The ticket enqueue owner no longer replaces the 1.3.1 query version with per-file mtimes, so the production-facing cache-busting contract is consistent across the complete ticket bundle.

## Build procedure

Build only from the clean committed 1.3.1 release checkpoint with:

`php scripts/build-public-release.php --output-dir <task-owned-output>`

Build twice from the same commit. Require identical ZIP SHA-256 values and identical normalized extracted manifests. The public artifact filename must be `backstage-venue-manager-1.3.1-public-release.zip` and contain one `backstage-venue-manager/` root.

## Staging gate

Run Safe Mode and Progressive as visitor and administrator at desktop and mobile widths. Require stable computed visibility after at least 2.2 seconds, exactly one ticket controller, canonical sponsorship/ticket/purchase DOM order, no duplicate rental region, real `elementFromPoint()` coverage for both rental stepper buttons, intercepted cart payload verification, and clean console/network telemetry.

## Environment boundary

This candidate may be deployed to staging only for certification. It does not authorize production deployment, production maintenance-mode changes, production WPCode/settings changes, tagging, pushing, WordPress.org submission, or reviewer communication.
