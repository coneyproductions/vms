#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
wordpress_source_root=${WAVE3B2_WP_ROOT:-$(CDPATH= cd -- "$repo_root/../../../.." && pwd)}
php_bin=${WAVE3B2_PHP_BIN:-/opt/homebrew/opt/php@8.3/bin/php}
wp_cli_bin=${WAVE3B2_WP_CLI_BIN:-/opt/homebrew/bin/wp}
rollback_root=${WAVE3B2_ROLLBACK_ROOT:-$(dirname "$wordpress_source_root")/bvm-local-rollbacks}
mode=${1:-}
output_dir=${2:-}
source_entry=${3:-}
runner=$repo_root/tests/data-tools-normal-local-acceptance.php
preload_file=$repo_root/tests/addon-compatibility/runtime-preload.php
containment_lib=$repo_root/scripts/lib/bvm-test-containment.sh

case "$mode" in
	baseline)
		expected_version=0.5.54
		expected_tree=7f997b79f675b2534188d44f750afb4bd4eec9d03db9f75b6020b0afe02e9039
		;;
	candidate-diagnostic|acceptance)
		expected_version=0.5.55
		expected_tree=8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b
		;;
	*)
		printf 'Usage: %s baseline|candidate-diagnostic|acceptance OUTPUT_DIR [CANDIDATE_ENTRY]\n' "$0" >&2
		exit 2
		;;
esac

if [ -z "$output_dir" ]; then
	printf 'An explicit evidence output directory is required.\n' >&2
	exit 2
fi
case "$output_dir" in
	"$rollback_root"/*) ;;
	*) printf 'Evidence output must remain below the local rollback root: %s\n' "$rollback_root" >&2; exit 2 ;;
esac
if [ "$mode" = candidate-diagnostic ] && [ ! -r "$source_entry" ]; then
	printf 'Candidate diagnostic requires a readable candidate entry.\n' >&2
	exit 2
fi
for required in "$wordpress_source_root/wp-settings.php" "$php_bin" "$wp_cli_bin" "$runner" "$preload_file" "$containment_lib"; do
	if [ ! -r "$required" ]; then
		printf 'Required acceptance path is unreadable: %s\n' "$required" >&2
		exit 2
	fi
done

mkdir -p "$output_dir"
chmod 0700 "$output_dir"
temp_base=${TMPDIR:-/tmp}
runtime_root=$(mktemp -d "$temp_base/wave3b2-normal-acceptance.XXXXXX")
database_cleanup=not-applicable
runtime_cleanup=pending

wp_source() {
	"$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" "$@"
}

wp_fixture() {
	printf 'No disposable fixture command is available in the normal-local acceptance wrapper.\n' >&2
	return 1
}

# shellcheck source=scripts/lib/bvm-test-containment.sh
. "$containment_lib"

cleanup_on_exit() {
	exit_code=$?
	trap - EXIT HUP INT TERM
	cleanup_failed=no
	set +e
	if [ "$bvm_test_containment_normal_guard_installed" = yes ] && [ -r "$bvm_test_containment_before" ]; then
		bvm_test_containment_capture_normal_state "$bvm_test_containment_after"
		if cmp -s "$bvm_test_containment_before" "$bvm_test_containment_after" \
			&& cmp -s "${bvm_test_containment_before%.tsv}-tables.tsv" "${bvm_test_containment_after%.tsv}-tables.tsv" \
			&& cmp -s "${bvm_test_containment_before%.tsv}-options.tsv" "${bvm_test_containment_after%.tsv}-options.tsv"; then
			bvm_test_containment_normal_state_result=pass
		else
			bvm_test_containment_normal_state_result=fail
			cleanup_failed=yes
		fi
	fi
	if ! bvm_test_containment_remove_guards; then
		cleanup_failed=yes
	fi
	if ! bvm_test_containment_release_lock; then
		cleanup_failed=yes
	fi
	if rmdir "$runtime_root" 2>/dev/null && [ ! -e "$runtime_root" ]; then
		runtime_cleanup=pass
	else
		runtime_cleanup=fail
		cleanup_failed=yes
	fi
	bvm_test_containment_write_cleanup_report "$database_cleanup" "$runtime_cleanup"
	if [ "$cleanup_failed" = yes ] && [ "$exit_code" -eq 0 ]; then
		exit_code=1
	fi
	exit "$exit_code"
}
trap cleanup_on_exit EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM

bvm_test_containment_prepare
bvm_test_containment_install_normal_guard
bvm_test_containment_wait_for_normal_cron
bvm_test_containment_before=$output_dir/normal-state-before.tsv
bvm_test_containment_after=$output_dir/normal-state-after.tsv
bvm_test_containment_capture_stable_normal_state "$bvm_test_containment_before"

export WAVE3B2_DATA_TOOLS_MODE=$mode
export WAVE3B2_DATA_TOOLS_EXPECTED_VERSION=$expected_version
export WAVE3B2_DATA_TOOLS_EXPECTED_TREE_SHA256=$expected_tree
if [ "$mode" = candidate-diagnostic ]; then
	export WAVE3B2_DATA_TOOLS_SOURCE_ENTRY=$source_entry
else
	unset WAVE3B2_DATA_TOOLS_SOURCE_ENTRY 2>/dev/null || true
fi

"$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" \
	--require="$preload_file" eval-file "$runner" \
	--skip-plugins --skip-themes --quiet \
	> "$output_dir/normal-local-acceptance.json" \
	2> "$output_dir/runner.stderr.log"

if [ -s "$output_dir/runner.stderr.log" ]; then
	printf 'The normal-local acceptance runner wrote unexpected stderr.\n' >&2
	exit 1
fi

"$php_bin" -r '
$path = $argv[1];
$decoded = json_decode((string) file_get_contents($path), true);
if (!is_array($decoded) || ($decoded["status"] ?? "") !== "PASS") {
	fwrite(STDERR, "Normal-local acceptance did not produce a PASS result.\n");
	exit(1);
}
' "$output_dir/normal-local-acceptance.json"

printf 'PASS: Data Tools %s normal-local acceptance runner\n' "$mode"
