# Backstage Venue Manager source reconciliation

The Production-verified cumulative BVM source is proposed at
[`backstage-venue-manager/`](backstage-venue-manager/) for
`1.3.21-cumulative.20261117.61021431`.

[Source scope and authority](docs/BVM-SOURCE-BASELINE.md) ·
[Release identity and acceptance limits](docs/audits/issue-6/releases/1.3.21-cumulative.20261117.61021431/RELEASE.md)

This source-only integration is pending owner review. Publication alone does not
merge it or designate a canonical branch. Future BVM work should use the cumulative
baseline only after explicit owner-approved integration and source designation.
The legacy repository-root plugin files remain preserved; do not combine them with
the cumulative plugin when packaging.

The public export contains 430 files. The complete approved release contains 432;
two private release assets are deliberately excluded from Git. Keep the frozen
complete package and verify its full manifest for installation or promotion.
The existing manual Local → Staging → Production protocol and separate environment
approvals remain required. No website changes are made by this source integration.
