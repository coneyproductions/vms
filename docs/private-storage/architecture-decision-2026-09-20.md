# Private-storage architecture decision — 2026-09-20

## Decision

Backstage Venue Manager 1.3.0 uses host-provisioned storage outside every web document root and alias. `BVMGR_PRIVATE_STORAGE_ROOT` identifies the existing dedicated private directory. `BVMGR_PRIVATE_STORAGE_WEB_ROOTS` must completely declare the host's published filesystem roots. Invalid, missing, ambiguous, overlapping, linked, unreadable, or unwritable configuration fails closed. There is no automatic or operator-selectable uploads fallback.

The confidentiality invariant is structural: an unauthenticated HTTP request has no URL mapping to a BVM private object. Apache/IIS deny files and loopback HTTP probes may help contain historical public source copies, but they are not authority for the canonical destination.

## Context and authority

The accepted outside-webroot design was developed through commits `21f7367`, `7dd74c7`, `d3a5b1b`, `04caf99`, and `090e493`. Round 2 WordPress.org remediation later introduced a verified-uploads destination at `wp-content/uploads/backstage-venue-manager/private/site-N`, with HTTP-denial receipts and server protection files. That implementation entered the reconstructed source but was never deployed here as canonical 1.3 operational storage: Local remained on 1.2.0, and no corrected 1.3 package was installed on Local, staging, or production.

The unified 1.3.0 line therefore restores the accepted outside-webroot destination while retaining compatible later hardening: canonical path checks, traversal/wrapper/NUL rejection, ancestor-link and hardlink rejection, per-site isolation, bounded diagnostics, conflict-safe publication, durable recovery receipts, scoped WordPress upload handling, authorized PHP streaming, suppressed public attachment URLs, and zero-write reads.

Both historical uploads families remain migration sources: the older BVM/VMS buckets and the short-lived verified-uploads `site-N` tree. Neither may become a destination. Empty cleanup is limited to BVM-owned legacy directories; shared companion roots are retained.

## Release and topology disposition

The prior 1.3.0 ZIP with SHA-256 `963e664e0c095fc691d9e7d293550efa9ec79913af9a2f24817135f5de5eac2e` is superseded and **DO NOT DEPLOY**.

This source-only correction is performed in the certified reconstruction worktree on `reconcile/bvm-unified-20260920`. For this tranche only, the noncanonical worktree path and absent derived `/app/vms` sibling are authorized preflight exceptions. The installed Local BVM 1.2.0 and legacy VMS trees must remain untouched; sibling synchronization is intentionally deferred to a separately authorized environment-alignment step. Disposable test fixtures may reproduce the historical sibling topology outside the operational WordPress tree, but they do not create a competing source authority.

## Consequences

Hosts must provision and privately back up the outside directory before private uploads or migration can run. Each environment needs its own PHP permission and public-root review. Current source recognition and durable receipts permit interrupted migration to resume without overwriting conflicting bytes or deleting an unverified source. Real private objects are not migrated by this source-correction tranche.
