# Preserve the November 16 Production source

Status for review · No source publication authorized by this documentation PR

## Verified identity

| Item | Value |
| --- | --- |
| Release | Backstage Venue Manager `1.3.19-vendor-portal.20261116.1411` |
| Production acceptance | November 16, 2026, with readback on November 17 |
| Exact local commit | `4b810e1dc9e1cc6ca9d439773d762cc13975b934` |
| Commit root tree | `a372505bf8f0fe1890ba81b1a613dbd95b912211` |
| BVM package subtree | `9105aed8efe23acb22a7d6fb18e35c934e007c1c` |
| Retained ZIP SHA-256 | `c4c01a71b5920126538df34d33a0ba7edeb2ea253bca94899a0cbf359dd66187` |
| Sorted path-tab-SHA-256 content-map identity | `63e165d8ea9fde898945ee5a5372c2079f740482298730c0f68bef2fbe05b69a` |
| Private BVM subtree export | `canonical-candidate-4b810e1.tar` in the restricted Phase 1 workspace, SHA-256 `84d9fcac320e2b3c797e85e69e8c272ba8b5ddad40646abc1caf6d12989461b9` |

The 432 committed plugin files match the retained ZIP and both hosted readbacks at the audit timestamp. The private audit Git mirror preserves the original commit object. The subtree tar is an independent file recovery copy. The commit identity and release record remain distinct from the choice of a future development branch. [Verified content manifest](evidence/bvm-1411-content-manifest.tsv) · [#140 acceptance](https://github.com/coneyproductions/vms/issues/140#issuecomment-6938910725)

## Proposed source publication after separate approval

1. Prepare an isolated recovery copy from the private Git mirror. Verify the full commit, root tree, plugin subtree, 432-file content map and retained ZIP. Record the remote repository and the absence of the proposed preservation ref. Save the current default branch identity and protection state for readback.
2. Review **every object that publication would make reachable**. The exact commit includes inherited workstation history and non-BVM trees. The local graph has 15 reachable commits, seven beyond the workstation legacy checkpoint. Compare that graph with current GitHub refs. Inspect the resulting commits, full trees, metadata, archives and binary content for secrets, customer information, environment details and publication rights. Review workflow files and push-triggered automation before choosing the ref type.
3. With approval for the exact SHA, destination and publication scope, create one archival ref such as `refs/tags/archive/bvm-production-20261116-1411`. Point it directly at the verified commit using a normal create-only push after confirming the name is unused. Verify the remote ref and re-export its BVM subtree in a fresh isolated clone to check the full manifest. Record the ref, resolved commit and content/package hashes in #6. Keep legacy `main`, existing refs and active worktrees intact.
4. If any reachable content cannot be approved for the public repository, keep the exact commit privately recoverable and stop the exact-SHA publication. A separately approved public export can preserve the BVM subtree under a **new** commit with a documented tree match and private provenance link. It does not preserve the original commit object on GitHub.
5. Bring any canonical development designation back for a separate decision after the bounded verification plan. Preserving an archival ref conveys provenance only.

The archive name, ref type and publication clearance are proposals. This PR contains the verification record only. [Phase 2 plan](PHASE-2-VERIFICATION-PLAN.md)
