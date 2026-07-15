#!/bin/sh

set -eu

protected_stash='WPORG-16D preserve unrelated sidebar+doc work'
warnings=0

warn() {
	printf 'WARN: %s\n' "$1"
	warnings=$((warnings + 1))
}

status_block() {
	label=$1
	value=$2
	printf '\n%s\n' "$label"
	printf '%s\n' "$value"
}

repo_root=$(git rev-parse --show-toplevel 2>/dev/null || true)
if [ -z "$repo_root" ]; then
	printf 'ERROR: not inside a git repository.\n' >&2
	exit 1
fi

branch=$(git -C "$repo_root" branch --show-current)
head_sha=$(git -C "$repo_root" rev-parse HEAD)
head_subject=$(git -C "$repo_root" log -1 --pretty=%s)
status_short=$(git -C "$repo_root" status --short)
diff_check=$(git -C "$repo_root" diff --check || true)
remotes=$(git -C "$repo_root" remote -v)
stash_list=$(git -C "$repo_root" stash list)
protected_match=$(printf '%s\n' "$stash_list" | grep -F "$protected_stash" || true)

workspace_root=$(cd "$repo_root/../.." && pwd)
mirror_tree="$repo_root"
live_tree="$workspace_root/vms"

case "$repo_root" in
	*/packages/vms-github-reconcile) ;;
	*) warn "mirror repository root does not end with /packages/vms-github-reconcile: $repo_root" ;;
esac

for tree in "$mirror_tree" "$live_tree"; do
	if [ ! -e "$tree" ]; then
		warn "tree is missing: $tree"
		continue
	fi
	if [ ! -r "$tree" ]; then
		warn "tree is not readable: $tree"
	fi
	if [ ! -w "$tree" ]; then
		warn "tree is not writable: $tree"
	fi
done

if [ -n "$status_short" ]; then
	warn "git status --short is not clean"
fi

if [ -n "$diff_check" ]; then
	warn "git diff --check reported whitespace or merge-marker issues"
fi

if [ -z "$protected_match" ]; then
	warn "protected stash not found by message: $protected_stash"
fi

printf 'Repository root: %s\n' "$repo_root"
printf 'Current branch: %s\n' "${branch:-"(detached)"}"
printf 'HEAD: %s %s\n' "$head_sha" "$head_subject"
printf 'Mirror tree: %s\n' "$mirror_tree"
printf 'Live tree: %s\n' "$live_tree"

status_block 'git status --short' "${status_short:-"(clean)"}"
status_block 'git diff --check' "${diff_check:-"(clean)"}"
status_block 'git remote -v' "${remotes:-"(no remotes configured)"}"
status_block 'git stash list' "${stash_list:-"(no stashes)"}"
status_block 'protected stash match' "${protected_match:-"(missing)"}"

if [ "$warnings" -eq 0 ]; then
	printf '\nRESULT: PASS\n'
	exit 0
fi

printf '\nRESULT: WARN (%s issue(s))\n' "$warnings"
exit 1
