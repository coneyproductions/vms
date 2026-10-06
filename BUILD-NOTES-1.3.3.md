# Build Notes 1.3.3

## Release identity

- Plugin: `Backstage Venue Manager`
- Public slug and text domain: `backstage-venue-manager`
- Version and build marker: `1.3.3`
- Companion release: `Backstage Outreach 1.2.3`, packaged and deployed separately

## Release scope

This patch makes the shared administrator shell declare UTF-8 when `DOMDocument` parses captured page HTML for notice extraction. It preserves existing notice placement, markup structure, escaping, permissions, output-buffer handling, and stored text. BVM schema targets and Backstage Outreach 1.2.3 remain unchanged.

## Build procedure

Build only from the clean committed release checkpoint with:

`php scripts/build-public-release.php --output-dir <task-owned-output>`

Build twice from the same commit. Require identical ZIP SHA-256 values and normalized extracted manifests. The artifact must contain one `backstage-venue-manager/` root and exclude `companion-plugins/backstage-outreach`.

## Staging gate

Back up the staging database and installed BVM runtime, block delivery, verify runtime drift, deploy only BVM, compare the deployed runtime tree to the accepted package, and rerun the disposable Outreach business-Source acceptance. Unicode text must survive preview, campaign creation, edit/save, input attributes, textareas, and extracted notices without rewriting historical records.

## Environment boundary

This release task authorizes staging certification only. It does not authorize production deployment, a default-branch merge, real business or campaign changes, message delivery, tagging, WordPress.org submission, or changes to Backstage Outreach 1.2.3.
