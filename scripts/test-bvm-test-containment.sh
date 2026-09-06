#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
php_bin=${BVM_COMPAT_PHP_BIN:-$(command -v php)}
temp_base=${TMPDIR:-/tmp}
output_dir=$(mktemp -d "$temp_base/bvm-test-containment-self-test.XXXXXX")

set +e
BVM_COMPAT_PHP_BIN="$php_bin" \
BVM_COMPAT_OUTPUT_DIR="$output_dir" \
BVM_COMPAT_CONTAINMENT_ABORT_AFTER_CANARY=yes \
"$repo_root/scripts/test-bvm-addon-runtime-compatibility.sh" >"$output_dir/worker.log" 2>&1
worker_exit=$?
set -e

if [ "$worker_exit" -ne 86 ]; then
	printf 'Containment failure-path worker exited %s instead of 86. Evidence: %s\n' "$worker_exit" "$output_dir" >&2
	exit 1
fi

cleanup_report=$output_dir/test-containment-cleanup.tsv
if [ ! -r "$cleanup_report" ]; then
	printf 'Containment failure-path cleanup report is missing. Evidence: %s\n' "$output_dir" >&2
	exit 1
fi

tab=$(printf '\t')
for expected in \
	"database_cleanup${tab}pass" \
	"runtime_cleanup${tab}pass" \
	"http_containment${tab}pass" \
	"process_boundary${tab}pass" \
	"residue_canary${tab}removed-and-asserted" \
	"normal_state${tab}pass" \
	"guard_cleanup${tab}pass" \
	"normal_guard_absent${tab}yes" \
	"runtime_absent${tab}yes" \
	"lock_released${tab}yes"
do
	if ! grep -Fqx "$expected" "$cleanup_report"; then
		printf 'Containment cleanup assertion is missing: %s. Evidence: %s\n' "$expected" "$output_dir" >&2
		exit 1
	fi
done

normal_mu_dir=${BVM_COMPAT_WP_ROOT:-$(CDPATH= cd -- "$repo_root/../../../.." && pwd)}/wp-content/mu-plugins
if [ -e "$normal_mu_dir/bvm-test-containment-runtime.php" ] || [ -e "$normal_mu_dir/bvm-test-containment-state.json" ]; then
	printf 'The injected failure left a normal-site containment file behind. Evidence: %s\n' "$output_dir" >&2
	exit 1
fi

printf 'BVM test-containment failure-path self-test passed.\n'
printf 'Evidence: %s\n' "$output_dir"
