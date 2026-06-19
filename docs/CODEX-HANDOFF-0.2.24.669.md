# Codex Handoff — VMS 0.2.24.669

## Focus

Diagnose slow DT/ECC admin report loads on the shared cPanel/LVE PHP worker pool. This build adds threshold-based request fingerprints, queue visibility, timing markers, and a few low-risk reuse/overlap guardrails so the next staging pass can show where the time is actually going.

## High-priority assertions

1. VMS Resource Fingerprints should record only slow/heavy or explicitly flagged work, not every request.
2. DT root and DT single-event report loads should leave marker-rich fingerprint entries that show whether ticket report, dataset, event model, evidence, labor, or render phases dominate.
3. A repeated DT single-event load should reuse request-level cached work where possible and avoid the duplicate summary/cost pass removed in this build.
4. Heavy ECC/DT admin screens should block Action Scheduler async-runner fan-out and reduce extra shared-pool PHP worker overlap.
5. ECC should continue to prefer DT combined reporting when available; TEC Orders remains a symptom reference, not the final source of truth.

## Known scope

This is a diagnostic/reduction pass, not a full report-query rewrite. If staging is still slow after this build, use the fingerprint markers and queue counts to target the next deeper optimization rather than guessing from cPanel worker counts alone.
