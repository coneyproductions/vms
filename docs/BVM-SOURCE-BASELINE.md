# Proposed cumulative BVM source baseline

This proposal preserves the exact Production-verified
`1.3.21-cumulative.20261117.61021431` runtime source under
`backstage-venue-manager/`. It introduces no plugin-code correction. The 430 public
file bodies and Git modes are exact exports of the private approved source.
Two private PNG release assets remain outside public history; the complete
432-file manifest pins their identity.

## Authority and history

The existing `main` branch and legacy repository-root plugin source are preserved.
This PR proposes a single cumulative source path rather than a replacement branch.
It does not import the private source commit's ancestors or designate a canonical
branch merely by being published. After separate owner-approved merge and source
designation, use the recorded cumulative merge/export commit as the BVM development
base. Do not restart subsequent BVM releases from the stale root plugin or overlay
standalone historic fixes without reconciliation. This authority remains proposed
until that explicit approval; repository defaults and current deployments are unchanged.

## Complete release preservation

The public source tree is not the complete approved distributable. Preserve the
original 432-file ZIP, its SHA-256, private source history and the two omitted blobs
in the existing private release archive. To reconstruct complete file content,
export only `backstage-venue-manager/`, add the two exact privately retained assets
at their manifest paths and verify all 432 paths, sizes and SHA-256 hashes against
`content-manifest.tsv`. Do not regenerate assets. Tests, repository-root legacy
source and audit documentation do not belong inside the plugin package.

File-content reconstruction does not establish byte-identical ZIP reconstruction:
archive ordering, timestamps and ZIP metadata may differ. The original frozen ZIP
is the authoritative artifact for the approved release. Never rebuild it for an
already-approved promotion. Give later changed packages unique versions and retain
new source/package identities.

## Tests and closure evidence

Nine existing #102/#143 regression files are preserved byte-for-byte under `tests/`.
They use synthetic substitutes and do not certify live financial workflows. Reuse
accepted test evidence for this unchanged source import; do not infer operational
success from code presence or closed issues.

Keep the established manual Local → Staging → Production workflow. Before a later
release, reconcile against installed Production, preserve rollback, and obtain
separate artifact-specific Staging and Production approvals. No new registry,
release platform or mandatory automated grant is introduced here.

A core BVM issue may be closed as completed only when its approved code is in the
designated authoritative source with its permanent commit recorded, its changes
are in a uniquely versioned complete release, appropriate regression tests and
complete-package verification pass, required environment acceptance is documented,
and the issue records permanent source/release evidence. Multiple issues may share
a release. Integration/deployment-pending issues stay open; superseded or not-planned
work needs an explicit nonimplementation disposition. Deployment verification alone
does not close #6/#102/#143 or imply outstanding owner acceptance.
