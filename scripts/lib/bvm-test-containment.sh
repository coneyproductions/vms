#!/bin/sh

# Test-only containment helpers for the BVM compatibility harnesses.
# The caller must define repo_root, wordpress_source_root, runtime_root,
# output_dir, php_bin, and the wp_source/wp_fixture shell functions.

bvm_test_containment_prepared=no
bvm_test_containment_normal_guard_installed=no
bvm_test_containment_runtime_guard_installed=no
bvm_test_containment_lock_acquired=no
bvm_test_containment_http=not-run
bvm_test_containment_process_boundary=not-run
bvm_test_containment_residue=not-run
bvm_test_containment_normal_state_result=not-run
bvm_test_containment_guard_cleanup=not-run
bvm_test_containment_lock_dir=
bvm_test_containment_token=
bvm_test_containment_guard_source=
bvm_test_containment_state_source=
bvm_test_containment_evidence_log=
bvm_test_containment_normal_guard=
bvm_test_containment_normal_state=
bvm_test_containment_runtime_guard=
bvm_test_containment_runtime_state=
bvm_test_containment_before=
bvm_test_containment_after=

bvm_test_containment_sha256_stream() {
	shasum -a 256 | awk '{print $1}'
}

bvm_test_containment_prepare() {
	for required_value in "$repo_root" "$wordpress_source_root" "$runtime_root" "$output_dir" "$php_bin"; do
		if [ -z "$required_value" ]; then
			printf 'Test-containment initialization received an empty required path.\n' >&2
			return 1
		fi
	done

	bvm_test_containment_guard_source=$repo_root/tests/addon-compatibility/test-containment-mu-plugin.php
	if [ ! -r "$bvm_test_containment_guard_source" ]; then
		printf 'Test-containment MU guard source is missing.\n' >&2
		return 1
	fi

	normal_mu_dir=$wordpress_source_root/wp-content/mu-plugins
	if [ ! -d "$normal_mu_dir" ]; then
		printf 'Normal Local mu-plugins directory is missing; refusing to create a persistent path.\n' >&2
		return 1
	fi

	bvm_test_containment_normal_guard=$normal_mu_dir/bvm-test-containment-runtime.php
	bvm_test_containment_normal_state=$normal_mu_dir/bvm-test-containment-state.json
	bvm_test_containment_runtime_guard=$runtime_root/wp-content/mu-plugins/bvm-test-containment-runtime.php
	bvm_test_containment_runtime_state=$runtime_root/wp-content/mu-plugins/bvm-test-containment-state.json

	if [ -e "$bvm_test_containment_normal_guard" ] || [ -e "$bvm_test_containment_normal_state" ]; then
		printf 'A normal-site test-containment guard already exists; refusing to overwrite it.\n' >&2
		return 1
	fi

	root_key=$(printf '%s' "$wordpress_source_root" | bvm_test_containment_sha256_stream)
	bvm_test_containment_lock_dir=${TMPDIR:-/tmp}/bvm-test-containment-${root_key}.lock
	if ! mkdir "$bvm_test_containment_lock_dir" 2>/dev/null; then
		printf 'Another compatibility containment run owns %s.\n' "$bvm_test_containment_lock_dir" >&2
		return 1
	fi
	bvm_test_containment_lock_acquired=yes

	bvm_test_containment_evidence_log=$output_dir/test-containment-events.jsonl
	bvm_test_containment_state_source=$output_dir/bvm-test-containment-state.json
	: > "$bvm_test_containment_evidence_log"
	token=$("$php_bin" -r 'echo bin2hex(random_bytes(16));')
	bvm_test_containment_token=$token
	export BVM_TEST_CONTAINMENT_TOKEN=$bvm_test_containment_token
	expires_utc=$(( $(date +%s) + 7200 ))
	"$php_bin" -r '
$state = array(
	"schema_version" => 1,
	"token" => $argv[2],
	"expires_utc" => (int) $argv[3],
	"evidence_log" => $argv[4],
	"normal_root" => $argv[5],
);
if (file_put_contents($argv[1], json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false) {
	exit(1);
}
' "$bvm_test_containment_state_source" "$token" "$expires_utc" "$bvm_test_containment_evidence_log" "$wordpress_source_root"
	chmod 0600 "$bvm_test_containment_state_source" "$bvm_test_containment_evidence_log"
	bvm_test_containment_prepared=yes
}

bvm_test_containment_install_normal_guard() {
	if [ "$bvm_test_containment_prepared" != yes ]; then
		printf 'Test containment was not prepared before normal guard installation.\n' >&2
		return 1
	fi
	cp "$bvm_test_containment_guard_source" "$bvm_test_containment_normal_guard"
	cp "$bvm_test_containment_state_source" "$bvm_test_containment_normal_state"
	chmod 0644 "$bvm_test_containment_normal_guard" "$bvm_test_containment_normal_state"
	bvm_test_containment_normal_guard_installed=yes
}

bvm_test_containment_install_runtime_guard() {
	if [ "$bvm_test_containment_prepared" != yes ] || [ ! -d "$(dirname "$bvm_test_containment_runtime_guard")" ]; then
		printf 'Disposable runtime is not ready for the test-containment guard.\n' >&2
		return 1
	fi
	cp "$bvm_test_containment_guard_source" "$bvm_test_containment_runtime_guard"
	cp "$bvm_test_containment_state_source" "$bvm_test_containment_runtime_state"
	chmod 0644 "$bvm_test_containment_runtime_guard" "$bvm_test_containment_runtime_state"
	bvm_test_containment_runtime_guard_installed=yes
}

bvm_test_containment_doing_cron_lock() {
	prefix=$(wp_source config get table_prefix --type=variable --quiet)
	wp_source db query "SELECT option_value FROM ${prefix}options WHERE option_name='_transient_doing_cron' LIMIT 1" \
		--skip-column-names --batch --raw --quiet --skip-plugins --skip-themes 2>/dev/null || true
}

bvm_test_containment_wait_for_normal_cron() {
	attempt=0
	while [ "$attempt" -lt 65 ]; do
		lock_value=$(bvm_test_containment_doing_cron_lock)
		if [ -z "$lock_value" ]; then
			return 0
		fi
		lock_is_stale=$("$php_bin" -r '$lock=(float)$argv[1]; echo ($lock + 60.0 <= microtime(true)) ? "yes" : "no";' "$lock_value")
		if [ "$lock_is_stale" = yes ]; then
			return 0
		fi
		attempt=$((attempt + 1))
		sleep 1
	done
	printf 'Normal Local cron did not become quiescent after the cross-process guard was installed.\n' >&2
	return 1
}

bvm_test_containment_option_hash() {
	option_name=$1
	wp_source option get "$option_name" --format=json --skip-plugins --skip-themes --quiet 2>/dev/null \
		| bvm_test_containment_sha256_stream
}

bvm_test_containment_capture_normal_state() {
	destination=$1
	prefix=$(wp_source config get table_prefix --type=variable --quiet)
	active_hash=$(bvm_test_containment_option_hash active_plugins)
	cron_hash=$(bvm_test_containment_option_hash cron)
	weather_settings_hash=$(bvm_test_containment_option_hash vmsx_weather_risk_settings)
	weather_log_hash=$(bvm_test_containment_option_hash vmsx_weather_risk_log)
	weather_snapshot_rows=$(wp_source db query "SELECT meta_id,post_id,meta_key,HEX(meta_value) FROM ${prefix}postmeta WHERE meta_key='vmsx_weather_risk_snapshot' ORDER BY meta_id" --skip-column-names --batch --raw --quiet --skip-plugins --skip-themes)
	weather_snapshot_count=$(printf '%s\n' "$weather_snapshot_rows" | awk 'NF{count++} END{print count+0}')
	weather_snapshot_hash=$(printf '%s' "$weather_snapshot_rows" | bvm_test_containment_sha256_stream)
	option_rows=$(wp_source db query "SELECT option_name,SHA2(CONCAT(LENGTH(option_value),':',option_value,':',autoload),256) FROM ${prefix}options ORDER BY option_name" --skip-column-names --batch --raw --quiet --skip-plugins --skip-themes)
	printf '%s\n' "$option_rows" > "${destination%.tsv}-options.tsv"
	option_count=$(printf '%s\n' "$option_rows" | awk 'NF{count++} END{print count+0}')
	option_hash=$(printf '%s' "$option_rows" | bvm_test_containment_sha256_stream)
	tables=$(wp_source db tables --all-tables-with-prefix --format=csv --quiet --skip-plugins --skip-themes | tr ',' '\n' | awk 'NF')
	checksum_sql=$(printf '%s\n' "$tables" | awk 'BEGIN{printf "CHECKSUM TABLE "}{if(count++)printf ","; printf "`%s`",$0}END{print ";"}')
	table_checksums=$(wp_source db query "$checksum_sql" --skip-column-names --batch --raw --quiet --skip-plugins --skip-themes | LC_ALL=C sort)
	printf '%s\n' "$table_checksums" > "${destination%.tsv}-tables.tsv"
	table_count=$(printf '%s\n' "$table_checksums" | awk 'NF{count++} END{print count+0}')
	table_hash=$(printf '%s' "$table_checksums" | bvm_test_containment_sha256_stream)
	config_hash=$("$php_bin" -r 'echo hash_file("sha256", $argv[1]);' "$wordpress_source_root/wp-config.php")
	{
		printf 'active_plugins_sha256\t%s\n' "$active_hash"
		printf 'cron_sha256\t%s\n' "$cron_hash"
		printf 'weather_settings_sha256\t%s\n' "$weather_settings_hash"
		printf 'weather_log_sha256\t%s\n' "$weather_log_hash"
		printf 'weather_snapshot_count\t%s\n' "$weather_snapshot_count"
		printf 'weather_snapshots_sha256\t%s\n' "$weather_snapshot_hash"
		printf 'normal_option_count\t%s\n' "$option_count"
		printf 'normal_options_sha256\t%s\n' "$option_hash"
		printf 'normal_table_count\t%s\n' "$table_count"
		printf 'normal_tables_sha256\t%s\n' "$table_hash"
		printf 'wp_config_sha256\t%s\n' "$config_hash"
	} > "$destination"
}

bvm_test_containment_capture_stable_normal_state() {
	destination=$1
	stability_candidate=$output_dir/normal-state-stability.tsv
	attempt=0
	while [ "$attempt" -lt 10 ]; do
		bvm_test_containment_capture_normal_state "$destination"
		sleep 1
		bvm_test_containment_capture_normal_state "$stability_candidate"
		if cmp -s "$destination" "$stability_candidate" \
			&& cmp -s "${destination%.tsv}-tables.tsv" "${stability_candidate%.tsv}-tables.tsv" \
			&& cmp -s "${destination%.tsv}-options.tsv" "${stability_candidate%.tsv}-options.tsv"; then
			return 0
		fi
		attempt=$((attempt + 1))
	done
	printf 'Normal Local state did not become stable after independently launched processes were contained.\n' >&2
	return 1
}

bvm_test_containment_assert_http_blocked() {
	before_count=$(awk -F'"' '$4=="http_blocked"{count++} END{print count+0}' "$bvm_test_containment_evidence_log")
	set +e
	fixture_http_result=$(wp_fixture eval '
$result = wp_remote_get("https://bvm-containment.invalid/must-not-leave-process", array("timeout" => 1));
if (!is_wp_error($result) || $result->get_error_code() !== "bvm_test_containment_http_blocked") {
	fwrite(STDERR, "The containment request reached a transport boundary.\n");
	exit(1);
}
echo "BVM_TEST_CONTAINMENT_HTTP_BLOCKED\n";
' --quiet 2>&1)
	fixture_http_exit=$?
	normal_http_result=$(wp_source eval '
$result = wp_remote_get("https://bvm-containment.invalid/must-not-leave-normal-process", array("timeout" => 1));
if (!is_wp_error($result) || $result->get_error_code() !== "bvm_test_containment_http_blocked") {
	fwrite(STDERR, "The normal-site containment request reached a transport boundary.\n");
	exit(1);
}
echo "BVM_TEST_CONTAINMENT_NORMAL_HTTP_BLOCKED\n";
' --skip-plugins --skip-themes --quiet 2>&1)
	normal_http_exit=$?
	set -e
	after_count=$(awk -F'"' '$4=="http_blocked"{count++} END{print count+0}' "$bvm_test_containment_evidence_log")
	if [ "$fixture_http_exit" -ne 0 ] || [ "$normal_http_exit" -ne 0 ] || [ "$after_count" -ne $((before_count + 2)) ]; then
		printf 'Deliberate outbound HTTP did not fail closed before transport.\n%s\n%s\n' "$fixture_http_result" "$normal_http_result" >&2
		return 1
	fi
	bvm_test_containment_http=pass

	normal_home=$(wp_source option get home --skip-plugins --skip-themes --quiet)
	normal_host=$("$php_bin" -r '$host=parse_url($argv[1], PHP_URL_HOST); echo is_string($host) ? strtolower($host) : "";' "$normal_home")
	case "$normal_host" in
		localhost|127.0.0.1|*.local) ;;
		*) printf 'Refusing the process-boundary probe because the normal URL is not local-only: %s\n' "$normal_host" >&2; return 1 ;;
	esac
	if ! command -v curl >/dev/null 2>&1; then
		printf 'curl is required for the Local FPM process-boundary containment proof.\n' >&2
		return 1
	fi
	normal_process_before=$(awk -F'"' '$4=="normal_web_process_blocked"{count++} END{print count+0}' "$bvm_test_containment_evidence_log")
	set +e
	normal_process_status=$(curl --noproxy '*' --insecure --silent --show-error --max-time 10 \
		--output "$output_dir/normal-process-boundary.body" \
		--write-out '%{http_code}' \
		"${normal_home%/}/?bvm_test_containment_process_boundary=1")
	normal_process_exit=$?
	set -e
	normal_process_after=$(awk -F'"' '$4=="normal_web_process_blocked"{count++} END{print count+0}' "$bvm_test_containment_evidence_log")
	if [ "$normal_process_exit" -ne 0 ] \
		|| [ "$normal_process_status" != 503 ] \
		|| ! grep -Fqx 'Local compatibility test containment is active.' "$output_dir/normal-process-boundary.body" \
		|| [ "$normal_process_after" -lt $((normal_process_before + 1)) ]; then
		printf 'The independently launched Local FPM process did not fail closed.\n' >&2
		return 1
	fi
	bvm_test_containment_process_boundary=pass
}

bvm_test_containment_create_residue_canary() {
	wp_fixture db query 'CREATE TABLE wp_bvm_test_containment_residue_canary (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, marker VARCHAR(64) NOT NULL) ENGINE=InnoDB; INSERT INTO wp_bvm_test_containment_residue_canary (id, marker) VALUES (1, "must-disappear-with-disposable-database");' --quiet
	canary_count=$(wp_fixture db query 'SELECT COUNT(*) FROM wp_bvm_test_containment_residue_canary WHERE id=1 AND marker="must-disappear-with-disposable-database"' --skip-column-names --batch --raw --quiet)
	if [ "$canary_count" != 1 ]; then
		printf 'Disposable residue canary could not be verified before teardown.\n' >&2
		return 1
	fi
	bvm_test_containment_residue=present-before-teardown
}

bvm_test_containment_database_absent() {
	database_name_to_check=$1
	database_count=$(wp_source db query "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='${database_name_to_check}'" --skip-column-names --batch --raw --quiet --skip-plugins --skip-themes 2>/dev/null || printf 'query-failed')
	[ "$database_count" = 0 ]
}

bvm_test_containment_remove_guards() {
	cleanup_failed=no
	if [ "$bvm_test_containment_normal_guard_installed" = yes ]; then
		if [ -f "$bvm_test_containment_normal_guard" ] && cmp -s "$bvm_test_containment_normal_guard" "$bvm_test_containment_guard_source"; then
			rm -f -- "$bvm_test_containment_normal_guard"
		else
			printf 'Normal test-containment guard changed unexpectedly; refusing blind removal.\n' >&2
			cleanup_failed=yes
		fi
		if [ -f "$bvm_test_containment_normal_state" ] && cmp -s "$bvm_test_containment_normal_state" "$bvm_test_containment_state_source"; then
			rm -f -- "$bvm_test_containment_normal_state"
		else
			printf 'Normal test-containment state changed unexpectedly; refusing blind removal.\n' >&2
			cleanup_failed=yes
		fi
	fi
	if [ "$cleanup_failed" = no ] && [ ! -e "$bvm_test_containment_normal_guard" ] && [ ! -e "$bvm_test_containment_normal_state" ]; then
		bvm_test_containment_normal_guard_installed=no
		bvm_test_containment_guard_cleanup=pass
		unset BVM_TEST_CONTAINMENT_TOKEN
		return 0
	fi
	bvm_test_containment_guard_cleanup=fail
	return 1
}

bvm_test_containment_release_lock() {
	if [ "$bvm_test_containment_lock_acquired" = yes ]; then
		if rmdir "$bvm_test_containment_lock_dir" 2>/dev/null; then
			bvm_test_containment_lock_acquired=no
			return 0
		fi
		printf 'Could not release the test-containment lock: %s\n' "$bvm_test_containment_lock_dir" >&2
		return 1
	fi
	return 0
}

bvm_test_containment_write_cleanup_report() {
	database_cleanup_value=$1
	runtime_cleanup_value=$2
	{
		printf 'database_cleanup\t%s\n' "$database_cleanup_value"
		printf 'runtime_cleanup\t%s\n' "$runtime_cleanup_value"
		printf 'http_containment\t%s\n' "$bvm_test_containment_http"
		printf 'process_boundary\t%s\n' "$bvm_test_containment_process_boundary"
		printf 'residue_canary\t%s\n' "$bvm_test_containment_residue"
		printf 'normal_state\t%s\n' "$bvm_test_containment_normal_state_result"
		printf 'guard_cleanup\t%s\n' "$bvm_test_containment_guard_cleanup"
		printf 'normal_guard_absent\t%s\n' "$([ ! -e "$bvm_test_containment_normal_guard" ] && [ ! -e "$bvm_test_containment_normal_state" ] && printf yes || printf no)"
		printf 'runtime_absent\t%s\n' "$([ ! -e "$runtime_root" ] && printf yes || printf no)"
		printf 'lock_released\t%s\n' "$([ "$bvm_test_containment_lock_acquired" = no ] && printf yes || printf no)"
	} > "$output_dir/test-containment-cleanup.tsv"
}
