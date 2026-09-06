#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
compatibility_phase=${BVM_COMPAT_PHASE:-mixed}
case "$compatibility_phase" in
	mixed) ;;
	*) printf 'Unknown additional compatibility phase: %s\n' "$compatibility_phase" >&2; exit 2 ;;
esac
export BVM_COMPAT_PHASE=$compatibility_phase
wordpress_source_root=${BVM_COMPAT_WP_ROOT:-$(CDPATH= cd -- "$repo_root/../../../.." && pwd)}
source_plugins_root=${BVM_COMPAT_ADDON_ROOT:-$wordpress_source_root/wp-content/plugins}
php_bin=${BVM_COMPAT_PHP_BIN:-$(command -v php)}
wp_cli_bin=${BVM_COMPAT_WP_CLI_BIN:-$(command -v wp)}
temp_base=${TMPDIR:-/tmp}
runtime_root=$(mktemp -d "$temp_base/bvm-addon-compat-runtime.XXXXXX")
if [ -n "${BVM_COMPAT_OUTPUT_DIR:-}" ]; then
	output_dir=$BVM_COMPAT_OUTPUT_DIR
	mkdir -p "$output_dir"
else
	output_dir=$(mktemp -d "$temp_base/bvm-addon-compat-report.XXXXXX")
fi

probe_file=$repo_root/tests/addon-compatibility/additional-runtime-probe.php
preload_file=$repo_root/tests/addon-compatibility/runtime-preload.php
report_builder=$repo_root/tests/addon-compatibility/additional-build-report.php
source_manifest_builder=$repo_root/tests/addon-compatibility/additional-source-manifest.php
containment_lib=$repo_root/scripts/lib/bvm-test-containment.sh
source_manifest=$output_dir/source-manifest.json
scenario_index=$output_dir/scenarios.tsv
debug_log=$runtime_root/wp-content/bvm-compat-debug.log
database_name=bvm_compat_$("$php_bin" -r 'echo bin2hex(random_bytes(6));')
database_created=no
database_cleanup=pending
runtime_cleanup=pending
report_basename=${BVM_COMPAT_REPORT_BASENAME:-bvm-additional-runtime-compatibility}

# shellcheck source=scripts/lib/bvm-test-containment.sh
. "$containment_lib"

validate_database_name() {
	case "$database_name" in
		bvm_compat_*) ;;
		*) printf 'Refusing unsafe disposable database name: %s\n' "$database_name" >&2; return 1 ;;
	esac
	case "$database_name" in
		*[!a-z0-9_]*) printf 'Refusing unsafe disposable database name: %s\n' "$database_name" >&2; return 1 ;;
		*) ;;
	esac
}

safe_remove_runtime() {
	case "$runtime_root" in
		"$temp_base"/bvm-addon-compat-runtime.*)
			if [ -n "$runtime_root" ] && [ "$runtime_root" != "/" ] && [ -e "$runtime_root" ]; then
				rm -rf -- "$runtime_root"
			fi
			;;
		*) printf 'Refusing unsafe runtime cleanup target: %s\n' "$runtime_root" >&2; return 1 ;;
	esac
}

wp_fixture() {
	"$php_bin" "$wp_cli_bin" --path="$runtime_root" "$@"
}

wp_source() {
	"$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" "$@"
}

cleanup_on_exit() {
	exit_code=$?
	trap - EXIT HUP INT TERM
	cleanup_failed=no
	set +e
	if [ "$database_created" = yes ]; then
		if validate_database_name \
			&& wp_fixture db drop --yes --quiet >/dev/null 2>&1 \
			&& bvm_test_containment_database_absent "$database_name"; then
			database_created=no
			database_cleanup=pass
			if [ "$bvm_test_containment_residue" = present-before-teardown ]; then
				bvm_test_containment_residue=removed-and-asserted
			fi
		else
			printf 'DISPOSABLE DATABASE CLEANUP FAILED: %s\n' "$database_name" >&2
			database_cleanup=fail
			cleanup_failed=yes
		fi
	fi
	if [ -e "$runtime_root" ]; then
		if safe_remove_runtime && [ ! -e "$runtime_root" ]; then
			runtime_cleanup=pass
		else
			runtime_cleanup=fail
			cleanup_failed=yes
		fi
	fi
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
	bvm_test_containment_write_cleanup_report "$database_cleanup" "$runtime_cleanup"
	if [ "$exit_code" -ne 0 ]; then
		printf 'Harness evidence preserved at: %s\n' "$output_dir" >&2
	fi
	if [ "$cleanup_failed" = yes ] && [ "$exit_code" -eq 0 ]; then
		exit_code=1
	fi
	exit "$exit_code"
}
trap cleanup_on_exit EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM

commerce_candidate=${BVM_COMPAT_COMMERCE_SOURCE_DIR:-$repo_root/companion-plugins/vms-commerce-discounts}
data_tools_candidate=${BVM_COMPAT_DATA_TOOLS_SOURCE_DIR:-$repo_root/companion-plugins/vms-data-tools}
calendar_feeds_candidate=${BVM_COMPAT_CALENDAR_FEEDS_SOURCE_DIR:-$repo_root/companion-plugins/backstage-calendar-feeds}
commerce_archive=${BVM_COMPAT_COMMERCE_ARCHIVE:-}
commerce_source_required=$commerce_candidate/vms-commerce-discounts.php
if [ -n "$commerce_archive" ]; then
	commerce_source_required=$commerce_archive
fi
weather_archive=$source_plugins_root/VMS\ WEATHER\ RISK\ ZIP\ ARCHIVES/vmsx-weather-risk-0.1.12-title-cleanup.zip
weather_candidate=$repo_root/companion-plugins/vmsx-weather-risk
sponsorships_candidate=${BVM_COMPAT_SPONSORSHIPS_SOURCE_DIR:-$source_plugins_root/packages/vms-sponsorships}
bridge_repo=${BVM_COMPAT_DRM_BRIDGE_REPO:-$source_plugins_root/drm-events-bridge}
router_repo=${BVM_COMPAT_DRM_ROUTER_REPO:-/Users/treyconey/Downloads/drm-event-router-source/drm-event-router}
bridge_commit=${BVM_COMPAT_BRIDGE_COMMIT:-b1efcc974233a3b43c2a9efa30533c6688f87320}
router_commit=${BVM_COMPAT_ROUTER_COMMIT:-21fadbd00e2ccebeb42ef8cb0334160af6e8b288}
bridge_expected_tree=3e2c1e49411c6811065f7f8eacca8fdaf736ed60
router_expected_tree=52b6e45421e0809526c46c34c7997ec024cc56df
bridge_expected_bootstrap=90ded5e059fe0974d465ae9627f8b12d36154b2b83a384b85238d6767028efed
router_expected_bootstrap=6758ce2f473493638778dc0fb45827672fd1f84663f41c20aea8f8e6431e6378

for required_path in \
	"$wordpress_source_root/wp-settings.php" \
	"$wordpress_source_root/wp-config.php" \
	"$repo_root/backstage-venue-manager.php" \
	"$source_plugins_root/drm-calendar-intake/drm-calendar-intake.php" \
	"$source_plugins_root/vms-investor-portal/vms-investor-portal.php" \
	"$source_plugins_root/vms-meta-ads/vms-meta-ads.php" \
	"$source_plugins_root/vms-ops-console-premium/vms-ops-console-premium.php" \
	"$source_plugins_root/vms-season-passes/vms-season-passes.php" \
	"$sponsorships_candidate/vms-sponsorships.php" \
	"$source_plugins_root/vmsx-checkout-policies/vmsx-checkout-policies.php" \
	"$source_plugins_root/vms-events-slider/vms-events-slider.php" \
	"$source_plugins_root/vms-fill-dates/vms-fill-dates.php" \
	"$data_tools_candidate/vms-data-tools.php" \
	"$calendar_feeds_candidate/backstage-calendar-feeds.php" \
	"$source_plugins_root/vms-express-bar/vms-express-bar.php" \
	"$source_plugins_root/vms-refer-a-friend/vms-refer-a-friend.php" \
	"$source_plugins_root/woocommerce/woocommerce.php" \
	"$source_plugins_root/woocommerce-square/woocommerce-square.php" \
	"$source_plugins_root/the-events-calendar/the-events-calendar.php" \
	"$source_plugins_root/event-tickets/event-tickets.php" \
	"$source_plugins_root/event-tickets-plus/event-tickets-plus.php" \
	"$commerce_source_required" \
	"$weather_archive" \
	"$weather_candidate/vmsx-weather-risk.php" \
	"$probe_file" \
	"$preload_file" \
	"$report_builder" \
	"$source_manifest_builder" \
	"$containment_lib" \
	"$repo_root/tests/addon-compatibility/test-containment-mu-plugin.php"
do
	if [ ! -r "$required_path" ]; then
		printf 'Required compatibility source is missing or unreadable: %s\n' "$required_path" >&2
		exit 2
	fi
done

if [ -n "$(git -C "$source_plugins_root/drm-calendar-intake" status --short)" ]; then
	printf 'DRM Calendar Intake changed or is dirty; refusing to freeze an ambiguous source snapshot.\n' >&2
	exit 2
fi
calendar_intake_head_before=$(git -C "$source_plugins_root/drm-calendar-intake" rev-parse HEAD)

# The Bridge source worktree is forensic/read-only in every phase.
if ! git -C "$bridge_repo" rev-parse --git-dir >/dev/null 2>&1; then
	printf 'Expected authoritative DRM Events Bridge Git worktree is missing.\n' >&2
	exit 2
fi
bridge_status_before=$(git -C "$bridge_repo" status --porcelain=v1 --untracked-files=all)
bridge_status_before_sha256=$(printf '%s\n' "$bridge_status_before" | shasum -a 256 | awk '{print $1}')

	if ! git -C "$router_repo" rev-parse --git-dir >/dev/null 2>&1; then
		printf 'Expected clean DRM Event Router Git repository is missing.\n' >&2
		exit 2
	fi
	if ! git -C "$bridge_repo" diff --cached --quiet; then
		printf 'DRM Events Bridge index is not clean; refusing to use it as a source object store.\n' >&2
		exit 2
	fi
	if [ -n "$(git -C "$router_repo" status --porcelain=v1 --untracked-files=all)" ]; then
		printf 'DRM Event Router source repository is dirty; refusing to freeze it.\n' >&2
		exit 2
	fi
	bridge_tree=$(git -C "$bridge_repo" rev-parse "$bridge_commit^{tree}")
	router_tree=$(git -C "$router_repo" rev-parse "$router_commit^{tree}")
	if [ "$bridge_tree" != "$bridge_expected_tree" ] || [ "$router_tree" != "$router_expected_tree" ]; then
		printf 'A frozen DRM Git object did not match its expected release tree.\n' >&2
		exit 2
	fi
	export BVM_COMPAT_BRIDGE_COMMIT=$bridge_commit
	export BVM_COMPAT_BRIDGE_TREE=$bridge_tree
	export BVM_COMPAT_BRIDGE_BOOTSTRAP_SHA256=$bridge_expected_bootstrap
	export BVM_COMPAT_BRIDGE_STATUS_BEFORE_SHA256=$bridge_status_before_sha256
	export BVM_COMPAT_ROUTER_COMMIT=$router_commit
	export BVM_COMPAT_ROUTER_TREE=$router_tree
	export BVM_COMPAT_ROUTER_BOOTSTRAP_SHA256=$router_expected_bootstrap

bvm_test_containment_prepare
bvm_test_containment_install_normal_guard
bvm_test_containment_wait_for_normal_cron
bvm_test_containment_before=$output_dir/normal-state-before.tsv
bvm_test_containment_after=$output_dir/normal-state-after.tsv
bvm_test_containment_capture_stable_normal_state "$bvm_test_containment_before"

weather_archive_sha=$(shasum -a 256 "$weather_archive" | awk '{print $1}')
if [ "$weather_archive_sha" != '5d57006c4ef190b5ac7786abce15f2585b4ffb302831451399224b0ef03ade28' ]; then
	printf 'Frozen Weather 0.1.12 archive SHA-256 did not match the approved baseline.\n' >&2
	exit 2
fi

normal_active_plugins_hash() {
	wp_source option get active_plugins --format=json --skip-plugins --skip-themes --quiet \
		| "$php_bin" -r '$value = stream_get_contents(STDIN); echo hash("sha256", trim((string) $value));'
}

normal_before=$(normal_active_plugins_hash)
normal_config_before=$("$php_bin" -r 'echo hash_file("sha256", $argv[1]);' "$wordpress_source_root/wp-config.php")

db_name_source=$(wp_source config get DB_NAME --type=constant --quiet)
db_user=$(wp_source config get DB_USER --type=constant --quiet)
db_password=$(wp_source config get DB_PASSWORD --type=constant --quiet)
db_host=$(wp_source config get DB_HOST --type=constant --quiet)
db_charset=$(wp_source config get DB_CHARSET --type=constant --quiet)

if [ "$database_name" = "$db_name_source" ]; then
	printf 'Disposable database name unexpectedly matches the normal site database.\n' >&2
	exit 2
fi
validate_database_name

rsync -a --exclude='wp-content/' --exclude='wp-config.php' "$wordpress_source_root/" "$runtime_root/"
mkdir -p "$runtime_root/wp-content/plugins" "$runtime_root/wp-content/themes" "$runtime_root/wp-content/mu-plugins"
bvm_test_containment_install_runtime_guard
if [ -d "$wordpress_source_root/wp-content/themes" ]; then
	rsync -a "$wordpress_source_root/wp-content/themes/" "$runtime_root/wp-content/themes/"
fi

# This is the same public BVM fixture boundary as Phase 3. In particular, the
# dormant source-only Safety prototype remains excluded.
rsync -a \
	--exclude='/.git/' \
	--exclude='/.github/' \
	--exclude='/.gitignore' \
	--exclude='/.gitattributes' \
	--exclude='/AGENTS.md' \
	--exclude='/release-public-excludes.txt' \
	--exclude='/.codex/' \
	--exclude='/.codex-temp/' \
	--exclude='/tests/' \
	--exclude='/test-results/' \
	--exclude='/docs/' \
	--exclude='/scripts/' \
	--exclude='/dist/' \
	--exclude='/_attic/' \
	--exclude='/includes/safety/' \
	--exclude='/BUILD-NOTES-*.md' \
	--exclude='/local-event-plan-perf-*.md' \
	--exclude='/vms-test-plan-*.md' \
	--exclude='*.zip' \
	--exclude='*.log' \
	"$repo_root/" \
	"$runtime_root/wp-content/plugins/backstage-venue-manager/"

additional_copy_slugs='drm-calendar-intake vms-investor-portal vms-meta-ads vms-ops-console-premium vms-season-passes vmsx-checkout-policies'
for plugin_slug in \
	vms-events-slider vms-fill-dates vms-express-bar vms-refer-a-friend \
	woocommerce woocommerce-square the-events-calendar event-tickets event-tickets-plus \
	$additional_copy_slugs
do
	rsync -a \
		--exclude='/.git/' \
		--exclude='/AGENTS.md' \
		--exclude='/tests/' \
		"$source_plugins_root/$plugin_slug/" \
		"$runtime_root/wp-content/plugins/$plugin_slug/"
done

mkdir -p "$runtime_root/wp-content/plugins/vms-data-tools" "$runtime_root/wp-content/plugins/backstage-calendar-feeds"
rsync -a --exclude='/.git/' --exclude='/AGENTS.md' --exclude='/tests/' "$data_tools_candidate/" "$runtime_root/wp-content/plugins/vms-data-tools/"
rsync -a --exclude='/.git/' --exclude='/AGENTS.md' --exclude='/tests/' "$calendar_feeds_candidate/" "$runtime_root/wp-content/plugins/backstage-calendar-feeds/"

mkdir -p "$runtime_root/wp-content/plugins/vms-sponsorships"
rsync -a \
	--exclude='/.git/' \
	--exclude='/AGENTS.md' \
	--exclude='/tests/' \
	"$sponsorships_candidate/" \
	"$runtime_root/wp-content/plugins/vms-sponsorships/"

	mkdir -p "$runtime_root/wp-content/plugins/drm-events-bridge" "$runtime_root/wp-content/plugins/drm-event-router"
	git -C "$bridge_repo" archive --format=tar "$bridge_commit" | tar -xf - -C "$runtime_root/wp-content/plugins/drm-events-bridge"
	git -C "$router_repo" archive --format=tar "$router_commit" | tar -xf - -C "$runtime_root/wp-content/plugins/drm-event-router"
	bridge_staged_bootstrap=$(shasum -a 256 "$runtime_root/wp-content/plugins/drm-events-bridge/drm-events-bridge.php" | awk '{print $1}')
	router_staged_bootstrap=$(shasum -a 256 "$runtime_root/wp-content/plugins/drm-event-router/drm-event-router.php" | awk '{print $1}')
	if [ "$bridge_staged_bootstrap" != "$bridge_expected_bootstrap" ] || [ "$router_staged_bootstrap" != "$router_expected_bootstrap" ]; then
		printf 'A staged DRM bootstrap did not match its immutable Git object.\n' >&2
		exit 2
	fi
	bridge_status_after_freeze=$(git -C "$bridge_repo" status --porcelain=v1 --untracked-files=all)
	if [ "$bridge_status_before" != "$bridge_status_after_freeze" ]; then
		printf 'DRM Events Bridge worktree changed while the isolated snapshot was frozen.\n' >&2
		exit 2
	fi

if [ -n "$commerce_archive" ]; then
	unzip -q "$commerce_archive" -d "$runtime_root/wp-content/plugins"
else
	mkdir -p "$runtime_root/wp-content/plugins/vms-commerce-discounts"
	rsync -a "$commerce_candidate/" "$runtime_root/wp-content/plugins/vms-commerce-discounts/"
fi
rsync -a --exclude='/docs/' "$weather_candidate/" "$runtime_root/wp-content/plugins/vmsx-weather-risk/"

calendar_intake_head_after=$(git -C "$source_plugins_root/drm-calendar-intake" rev-parse HEAD)
if [ "$calendar_intake_head_before" != "$calendar_intake_head_after" ] || [ -n "$(git -C "$source_plugins_root/drm-calendar-intake" status --short)" ]; then
	printf 'DRM Calendar Intake moved while its fixture was being frozen.\n' >&2
	exit 2
fi

if [ -e "$runtime_root/wp-content/plugins/vms/vendor-management-system.php" ] \
	|| [ -e "$runtime_root/wp-content/plugins/vms.php" ] \
	|| [ -e "$runtime_root/wp-content/plugins/vms/vms.php" ] \
	|| [ -e "$runtime_root/wp-content/plugins/backstage-venue-manager.php" ]; then
	printf 'A prohibited historical or nonexistent BVM bootstrap identity entered the fixture.\n' >&2
	exit 2
fi

"$php_bin" "$wp_cli_bin" config create \
	--path="$runtime_root" \
	--dbname="$database_name" \
	--dbuser="$db_user" \
	--dbpass="$db_password" \
	--dbhost="$db_host" \
	--dbcharset="$db_charset" \
	--skip-salts \
	--force \
	--quiet

wp_fixture config set WP_DEBUG true --raw --quiet
wp_fixture config set WP_DEBUG_LOG "$debug_log" --quiet
wp_fixture config set WP_DEBUG_DISPLAY false --raw --quiet
wp_fixture config set SCRIPT_DEBUG true --raw --quiet
wp_fixture config set WP_ENVIRONMENT_TYPE local --quiet
wp_fixture config set WP_MEMORY_LIMIT 512M --quiet
wp_fixture config set WP_MAX_MEMORY_LIMIT 512M --quiet
wp_fixture config set DISABLE_WP_CRON true --raw --quiet
wp_fixture config set AUTOMATIC_UPDATER_DISABLED true --raw --quiet
wp_fixture config set WP_HTTP_BLOCK_EXTERNAL true --raw --quiet
wp_fixture db create --quiet
database_created=yes
wp_fixture core install \
	--url='http://bvm-compat.test' \
	--title='BVM Compatibility Fixture' \
	--admin_user='bvm_compat_admin' \
	--admin_password="$("$php_bin" -r 'echo bin2hex(random_bytes(16));')" \
	--admin_email='bvm-compat@example.test' \
	--skip-email \
	--quiet

bvm_test_containment_create_residue_canary
bvm_test_containment_assert_http_blocked
if [ "${BVM_COMPAT_CONTAINMENT_ABORT_AFTER_CANARY:-no}" = yes ]; then
	printf 'Injected containment failure after the HTTP and residue canaries.\n' >&2
	exit 86
fi

fixture_noncore_plugins='woocommerce the-events-calendar event-tickets event-tickets-plus vms-events-slider vms-fill-dates vms-data-tools vms-express-bar vms-refer-a-friend'
fixture_foundation_plugins="backstage-venue-manager $fixture_noncore_plugins"
fixture_additional_plugins='drm-calendar-intake drm-event-router drm-events-bridge backstage-calendar-feeds vms-investor-portal vms-meta-ads vms-ops-console-premium vms-season-passes vms-sponsorships vmsx-checkout-policies vmsx-weather-risk'
all_fixture_plugins="$fixture_foundation_plugins woocommerce-square $fixture_additional_plugins vms-commerce-discounts"

: > "$scenario_index"
activation_setup_log=$output_dir/activation-setup.log
: > "$activation_setup_log"
append_activation_result() {
	activation_id=$1
	activation_addon=$2
	activation_core=$3
	activation_woocommerce=$4
	activation_companion=$5
	activation_exit=$6
	activation_state=$7
	activation_raw=$output_dir/$activation_id.raw.log
	activation_debug=$output_dir/$activation_id.debug.log
	activation_state_base64=$("$php_bin" -r 'echo base64_encode($argv[1]);' "$activation_state")
	printf 'BVM_COMPAT_ACTIVATION_STATE_JSON=%s\n' "$activation_state_base64" >> "$activation_raw"
	: > "$activation_debug"
	printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
		"$activation_id" "$activation_addon" "$activation_core" "$activation_woocommerce" "$activation_companion" 'activation' "$activation_exit" "$activation_raw" "$activation_debug" \
		>> "$scenario_index"
}

# Required-core activation safety is observed before BVM has ever been active
# in the fresh disposable database.
# shellcheck disable=SC2086 -- the fixed list intentionally expands to WP-CLI arguments.
wp_fixture plugin activate $fixture_noncore_plugins --quiet >>"$activation_setup_log" 2>&1
season_absent_log=$output_dir/activation-core-absent-vms-season-passes.raw.log
set +e
wp_fixture plugin activate vms-season-passes --quiet >"$season_absent_log" 2>&1
season_absent_exit=$?
set -e
season_absent_state=$(wp_fixture eval 'global $wpdb; $role=get_role("administrator"); $tables=array($wpdb->prefix."vms_season_pass_types",$wpdb->prefix."vms_season_passholders",$wpdb->prefix."vms_season_pass_checkins"); $present=array_values(array_filter($tables,static function($table)use($wpdb){return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$table))===$table;})); $active=in_array("vms-season-passes/vms-season-passes.php",(array)get_option("active_plugins",array()),true); $mutated=get_option("vms_season_passes_db_version",null)!==null || ($role && $role->has_cap("vms_season_pass_manage")) || $present!==array(); echo wp_json_encode(array("passed"=>$active && !$mutated,"active"=>$active,"mutated"=>$mutated,"tables"=>$present));' --quiet)
append_activation_result activation-core-absent-vms-season-passes vms-season-passes no yes core-absent "$season_absent_exit" "$season_absent_state"
wp_fixture plugin deactivate vms-season-passes --quiet >>"$activation_setup_log" 2>&1

weather_absent_log=$output_dir/activation-core-absent-vmsx-weather-risk.raw.log
set +e
wp_fixture plugin activate vmsx-weather-risk --quiet >"$weather_absent_log" 2>&1
weather_absent_exit=$?
set -e
weather_absent_state=$(wp_fixture eval '$active=in_array("vmsx-weather-risk/vmsx-weather-risk.php",(array)get_option("active_plugins",array()),true); $settings=get_option("vmsx_weather_risk_settings",null); $scheduled=wp_next_scheduled("vmsx_weather_risk_refresh_cron"); echo wp_json_encode(array("passed"=>$active && $settings===null && $scheduled===false,"active"=>$active,"settings_created"=>$settings!==null,"scheduled"=>$scheduled!==false));' --quiet)
append_activation_result activation-core-absent-vmsx-weather-risk vmsx-weather-risk no yes core-absent "$weather_absent_exit" "$weather_absent_state"
wp_fixture plugin deactivate vmsx-weather-risk --quiet >>"$activation_setup_log" 2>&1

wp_fixture plugin activate backstage-venue-manager --quiet >>"$activation_setup_log" 2>&1

season_present_log=$output_dir/activation-canonical-bvm-vms-season-passes.raw.log
set +e
wp_fixture plugin activate vms-season-passes --quiet >"$season_present_log" 2>&1
season_present_exit=$?
set -e
season_present_state=$(wp_fixture eval 'global $wpdb; $role=get_role("administrator"); $tables=array($wpdb->prefix."vms_season_pass_types",$wpdb->prefix."vms_season_passholders",$wpdb->prefix."vms_season_pass_checkins"); $present=array_values(array_filter($tables,static function($table)use($wpdb){return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$table))===$table;})); $active=in_array("vms-season-passes/vms-season-passes.php",(array)get_option("active_plugins",array()),true); $version=(string)get_option("vms_season_passes_db_version",""); $cap=(bool)($role && $role->has_cap("vms_season_pass_manage")); echo wp_json_encode(array("passed"=>$active && $version!=="" && $cap && count($present)===3,"active"=>$active,"db_version"=>$version,"capability"=>$cap,"tables"=>$present));' --quiet)
append_activation_result activation-canonical-bvm-vms-season-passes vms-season-passes yes yes canonical-bvm "$season_present_exit" "$season_present_state"
wp_fixture plugin deactivate vms-season-passes --quiet >>"$activation_setup_log" 2>&1

weather_present_log=$output_dir/activation-canonical-bvm-vmsx-weather-risk.raw.log
set +e
wp_fixture plugin activate vmsx-weather-risk --quiet >"$weather_present_log" 2>&1
weather_present_exit=$?
set -e
weather_present_state=$(wp_fixture eval '$active=in_array("vmsx-weather-risk/vmsx-weather-risk.php",(array)get_option("active_plugins",array()),true); $settings=get_option("vmsx_weather_risk_settings",null); $scheduled=wp_next_scheduled("vmsx_weather_risk_refresh_cron"); echo wp_json_encode(array("passed"=>$active && is_array($settings) && $scheduled!==false,"active"=>$active,"settings_created"=>is_array($settings),"scheduled"=>$scheduled!==false));' --quiet)
append_activation_result activation-canonical-bvm-vmsx-weather-risk vmsx-weather-risk yes yes canonical-bvm "$weather_present_exit" "$weather_present_state"
weather_deactivate_log=$output_dir/deactivation-canonical-bvm-vmsx-weather-risk.raw.log
set +e
wp_fixture plugin deactivate vmsx-weather-risk --quiet >"$weather_deactivate_log" 2>&1
weather_deactivate_exit=$?
set -e
weather_deactivate_state=$(wp_fixture eval '$active=in_array("vmsx-weather-risk/vmsx-weather-risk.php",(array)get_option("active_plugins",array()),true); $settings=get_option("vmsx_weather_risk_settings",null); $scheduled=wp_next_scheduled("vmsx_weather_risk_refresh_cron"); echo wp_json_encode(array("passed"=>!$active && is_array($settings) && $scheduled===false,"active"=>$active,"settings_preserved"=>is_array($settings),"scheduled"=>$scheduled!==false));' --quiet)
append_activation_result deactivation-canonical-bvm-vmsx-weather-risk vmsx-weather-risk yes yes canonical-bvm "$weather_deactivate_exit" "$weather_deactivate_state"

# Commerce is activated separately so the 0.2.13 no-Square behavior
# remains explicit evidence.
commerce_missing_square_activation_log=$output_dir/third-party-activation-absent-square-vms-commerce-discounts.raw.log
set +e
wp_fixture plugin activate vms-commerce-discounts --quiet >"$commerce_missing_square_activation_log" 2>&1
commerce_missing_square_activation_exit=$?
set -e
commerce_activation_active_plugins=$(wp_fixture option get active_plugins --format=json --skip-plugins --skip-themes --quiet)
commerce_activation_migration_marker=$(wp_fixture option get _vms_discounts_migrated --skip-plugins --skip-themes --quiet 2>/dev/null || printf '__missing__')
commerce_activation_state_json=$("$php_bin" -r '
$active = json_decode($argv[1], true);
$plugin = "vms-commerce-discounts/vms-commerce-discounts.php";
echo json_encode([
	"active_plugins" => is_array($active) ? array_values($active) : [],
	"commerce_active" => is_array($active) && in_array($plugin, $active, true),
	"migration_marker" => $argv[2] === "__missing__" ? null : $argv[2],
], JSON_UNESCAPED_SLASHES);
' "$commerce_activation_active_plugins" "$commerce_activation_migration_marker")
commerce_activation_state_base64=$("$php_bin" -r 'echo base64_encode($argv[1]);' "$commerce_activation_state_json")
printf '\nBVM_COMPAT_ACTIVATION_STATE_JSON=%s\n' "$commerce_activation_state_base64" >> "$commerce_missing_square_activation_log"
wp_fixture plugin activate woocommerce-square --quiet >>"$activation_setup_log" 2>&1
# shellcheck disable=SC2086 -- the fixed lists intentionally expand to WP-CLI arguments.
wp_fixture plugin activate $fixture_additional_plugins --quiet >>"$activation_setup_log" 2>&1
wp_fixture plugin activate vms-commerce-discounts --quiet >>"$activation_setup_log" 2>&1
# shellcheck disable=SC2086 -- the fixed list intentionally expands to WP-CLI arguments.
wp_fixture plugin deactivate $all_fixture_plugins --quiet >>"$activation_setup_log" 2>&1
wp_fixture transient delete _wc_activation_redirect --skip-plugins --skip-themes --quiet >/dev/null 2>&1 || true
wp_fixture transient delete _tribe_events_activation_redirect --skip-plugins --skip-themes --quiet >/dev/null 2>&1 || true
wp_fixture option update wc_square_show_wizard_on_activation 1 --skip-plugins --skip-themes --quiet >/dev/null

for capability in manage_woocommerce vms_manage_data_tools vms_import_vendors vms_manage_vendors vms_manage_investor_financials vms_view_investor_portal
do
	wp_fixture cap add administrator "$capability" --skip-plugins --skip-themes --quiet
done

"$php_bin" "$source_manifest_builder" "$runtime_root" "$source_manifest" >/dev/null
commerce_missing_square_debug_log=$output_dir/third-party-activation-absent-square-vms-commerce-discounts.debug.log
: > "$commerce_missing_square_debug_log"
printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
	'third-party-activation-absent-square-vms-commerce-discounts' 'vms-commerce-discounts' 'yes' 'yes' 'missing-woocommerce-square' 'activation' "$commerce_missing_square_activation_exit" "$commerce_missing_square_activation_log" "$commerce_missing_square_debug_log" \
	>> "$scenario_index"

bvm_plugin=backstage-venue-manager/backstage-venue-manager.php
woocommerce_plugin=woocommerce/woocommerce.php
square_plugin=woocommerce-square/woocommerce-square.php
tec_plugin=the-events-calendar/the-events-calendar.php
tickets_plugin=event-tickets/event-tickets.php
tickets_plus_plugin=event-tickets-plus/event-tickets-plus.php
events_plugin=vms-events-slider/vms-events-slider.php
fill_dates_plugin=vms-fill-dates/vms-fill-dates.php
data_tools_plugin=vms-data-tools/vms-data-tools.php
calendar_feeds_plugin=backstage-calendar-feeds/backstage-calendar-feeds.php
express_bar_plugin=vms-express-bar/vms-express-bar.php
raf_plugin=vms-refer-a-friend/vms-refer-a-friend.php
calendar_plugin=drm-calendar-intake/drm-calendar-intake.php
router_plugin=drm-event-router/drm-event-router.php
bridge_plugin=drm-events-bridge/drm-events-bridge.php
commerce_plugin=vms-commerce-discounts/vms-commerce-discounts.php
investor_plugin=vms-investor-portal/vms-investor-portal.php
meta_ads_plugin=vms-meta-ads/vms-meta-ads.php
ops_plugin=vms-ops-console-premium/vms-ops-console-premium.php
season_plugin=vms-season-passes/vms-season-passes.php
sponsorships_plugin=vms-sponsorships/vms-sponsorships.php
checkout_plugin=vmsx-checkout-policies/vmsx-checkout-policies.php
weather_plugin=vmsx-weather-risk/vmsx-weather-risk.php

run_scenario() {
	scenario_id=$1
	scenario_addon=$2
	core_expected=$3
	woocommerce_expected=$4
	companion_state=$5
	load_order=$6
	request_page=$7
	shift 7

	plugins_json=$("$php_bin" -r 'echo json_encode(array_slice($argv, 1), JSON_UNESCAPED_SLASHES);' -- "$@")
	wp_fixture option update active_plugins "$plugins_json" --format=json --skip-plugins --skip-themes --quiet >/dev/null

	raw_path=$output_dir/$scenario_id.raw.log
	scenario_debug_path=$output_dir/$scenario_id.debug.log
	: > "$debug_log"
	set +e
	BVM_COMPAT_REQUEST_PAGE=$request_page \
		"$php_bin" "$wp_cli_bin" \
		--path="$runtime_root" \
		--require="$preload_file" \
		eval-file "$probe_file" \
		"$scenario_id" "$scenario_addon" "$core_expected" "$woocommerce_expected" "$companion_state" "$load_order" \
		>"$raw_path" 2>&1
	scenario_exit=$?
	set -e
	if [ -f "$debug_log" ]; then
		cp "$debug_log" "$scenario_debug_path"
	else
		: > "$scenario_debug_path"
	fi
	printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
		"$scenario_id" "$scenario_addon" "$core_expected" "$woocommerce_expected" "$companion_state" "$load_order" "$scenario_exit" "$raw_path" "$scenario_debug_path" \
		>> "$scenario_index"
}

run_scenario additional-drm-calendar-intake-core-first drm-calendar-intake yes no normal core-first drm-calendar-intake-settings "$bvm_plugin" "$calendar_plugin"
run_scenario additional-drm-calendar-intake-addon-first drm-calendar-intake yes no normal addon-first drm-calendar-intake-settings "$calendar_plugin" "$bvm_plugin"
run_scenario additional-vms-commerce-discounts-core-first vms-commerce-discounts yes yes normal core-first vms-commerce-discounts "$bvm_plugin" "$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" "$commerce_plugin"
run_scenario additional-vms-commerce-discounts-addon-first vms-commerce-discounts yes yes normal addon-first vms-commerce-discounts "$commerce_plugin" "$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" "$bvm_plugin"
run_scenario additional-vms-investor-portal-core-first vms-investor-portal yes no normal core-first vms-investor-portal "$bvm_plugin" "$investor_plugin"
run_scenario additional-vms-investor-portal-addon-first vms-investor-portal yes no normal addon-first vms-investor-portal "$investor_plugin" "$bvm_plugin"
run_scenario additional-vms-meta-ads-core-first vms-meta-ads yes no normal core-first vms-ma-ads-builder "$bvm_plugin" "$meta_ads_plugin"
run_scenario additional-vms-meta-ads-addon-first vms-meta-ads yes no normal addon-first vms-ma-ads-builder "$meta_ads_plugin" "$bvm_plugin"
run_scenario additional-vms-ops-console-premium-core-first vms-ops-console-premium yes no normal core-first vms-ops-console-members "$bvm_plugin" "$ops_plugin"
run_scenario additional-vms-ops-console-premium-addon-first vms-ops-console-premium yes no normal addon-first vms-ops-console-members "$ops_plugin" "$bvm_plugin"
run_scenario additional-vms-season-passes-core-first vms-season-passes yes no normal core-first vms-season-passes "$bvm_plugin" "$season_plugin"
run_scenario additional-vms-season-passes-addon-first vms-season-passes yes no normal addon-first vms-season-passes "$season_plugin" "$bvm_plugin"
run_scenario additional-vms-sponsorships-core-first vms-sponsorships yes no normal core-first vms-sponsorships "$bvm_plugin" "$sponsorships_plugin"
run_scenario additional-vms-sponsorships-addon-first vms-sponsorships yes no normal addon-first vms-sponsorships "$sponsorships_plugin" "$bvm_plugin"
run_scenario additional-vmsx-checkout-policies-core-first vmsx-checkout-policies yes yes normal core-first vms-settings "$bvm_plugin" "$woocommerce_plugin" "$checkout_plugin"
run_scenario additional-vmsx-checkout-policies-addon-first vmsx-checkout-policies yes yes normal addon-first vms-settings "$checkout_plugin" "$woocommerce_plugin" "$bvm_plugin"
run_scenario additional-vmsx-weather-risk-core-first vmsx-weather-risk yes no normal core-first vms-weather-risk "$bvm_plugin" "$weather_plugin"
run_scenario additional-vmsx-weather-risk-addon-first vmsx-weather-risk yes no normal addon-first vms-weather-risk "$weather_plugin" "$bvm_plugin"
run_scenario additional-drm-events-bridge-core-first drm-events-bridge yes no normal core-first drm-events-bridge "$bvm_plugin" "$calendar_plugin" "$router_plugin" "$bridge_plugin"
run_scenario additional-drm-events-bridge-addon-first drm-events-bridge yes no normal addon-first drm-events-bridge "$calendar_plugin" "$router_plugin" "$bridge_plugin" "$bvm_plugin"
run_scenario additional-calendar-feeds-core-first drm-calendar-intake yes no normal core-first backstage "$bvm_plugin" "$calendar_plugin" "$calendar_feeds_plugin"
run_scenario additional-calendar-feeds-addon-first drm-calendar-intake yes no normal addon-first backstage "$calendar_feeds_plugin" "$calendar_plugin" "$bvm_plugin"

run_scenario additional-core-absent-drm-calendar-intake drm-calendar-intake no no normal n/a drm-calendar-intake-settings "$calendar_plugin"
run_scenario additional-calendar-feeds-standalone-provider-absent all no no missing-intake n/a backstage "$calendar_feeds_plugin"
run_scenario additional-core-absent-vms-commerce-discounts vms-commerce-discounts no yes normal n/a vms-commerce-discounts "$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" "$commerce_plugin"
run_scenario additional-core-absent-vms-investor-portal vms-investor-portal no no normal n/a vms-investor-portal "$investor_plugin"
run_scenario additional-core-absent-vms-meta-ads vms-meta-ads no no normal n/a vms-ma-ads-builder "$meta_ads_plugin"
run_scenario additional-core-absent-vms-ops-console-premium vms-ops-console-premium no no normal n/a vms-ops-console-members "$ops_plugin"
run_scenario additional-core-absent-vms-season-passes vms-season-passes no no normal n/a vms-season-passes "$season_plugin"
run_scenario additional-core-absent-vms-sponsorships vms-sponsorships no no normal n/a vms-sponsorships "$sponsorships_plugin"
run_scenario additional-core-absent-vmsx-checkout-policies vmsx-checkout-policies no yes normal n/a vmsx-checkout-policies "$woocommerce_plugin" "$checkout_plugin"
run_scenario additional-core-absent-vmsx-weather-risk vmsx-weather-risk no no normal n/a vms-weather-risk "$weather_plugin"
run_scenario additional-core-absent-drm-events-bridge drm-events-bridge no no normal n/a drm-events-bridge "$calendar_plugin" "$router_plugin" "$bridge_plugin"

run_scenario third-party-absent-vms-commerce-discounts vms-commerce-discounts yes no missing-woocommerce core-first vms-commerce-discounts "$bvm_plugin" "$commerce_plugin"
run_scenario third-party-absent-square-vms-commerce-discounts vms-commerce-discounts yes yes missing-woocommerce-square core-first vms-commerce-discounts "$bvm_plugin" "$woocommerce_plugin" "$commerce_plugin"
run_scenario third-party-absent-vmsx-checkout-policies vmsx-checkout-policies yes no missing-woocommerce core-first vms-settings "$bvm_plugin" "$checkout_plugin"
run_scenario third-party-absent-vms-season-passes vms-season-passes yes no missing-woocommerce core-first vms-season-passes "$bvm_plugin" "$season_plugin"
run_scenario third-party-present-vms-season-passes-woocommerce vms-season-passes yes yes woocommerce-present core-first vms-season-passes "$bvm_plugin" "$woocommerce_plugin" "$season_plugin"
run_scenario third-party-absent-vms-season-passes-ops vms-season-passes yes no missing-ops core-first vms-season-passes "$bvm_plugin" "$season_plugin"
run_scenario third-party-absent-vms-sponsorships vms-sponsorships yes no missing-tec core-first vms-sponsorships "$bvm_plugin" "$sponsorships_plugin"
run_scenario additional-provider-absent-drm-events-bridge drm-events-bridge no no missing-router missing-provider drm-events-bridge "$calendar_plugin" "$bridge_plugin"
run_scenario third-party-absent-vmsx-weather-risk-data-tools vmsx-weather-risk yes no missing-data-tools core-first vms-weather-risk "$bvm_plugin" "$weather_plugin"
run_scenario third-party-present-vmsx-weather-risk-data-tools vmsx-weather-risk yes no data-tools-present core-first vms-weather-risk "$bvm_plugin" "$data_tools_plugin" "$weather_plugin"
run_scenario third-party-absent-vmsx-weather-risk-tec vmsx-weather-risk yes no missing-tec core-first vms-weather-risk "$bvm_plugin" "$weather_plugin"

run_scenario additional-coexistence-core-first all yes yes full core-first vms-dashboard \
	"$bvm_plugin" "$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" \
	"$events_plugin" "$fill_dates_plugin" "$data_tools_plugin" "$express_bar_plugin" "$raf_plugin" \
	"$calendar_plugin" "$router_plugin" "$bridge_plugin" "$calendar_feeds_plugin" "$commerce_plugin" "$investor_plugin" "$meta_ads_plugin" "$ops_plugin" \
	"$season_plugin" "$sponsorships_plugin" "$checkout_plugin" "$weather_plugin"
run_scenario additional-coexistence-addons-first all yes yes full addons-first vms-dashboard \
	"$calendar_feeds_plugin" "$calendar_plugin" "$router_plugin" "$bridge_plugin" "$commerce_plugin" "$investor_plugin" "$meta_ads_plugin" "$ops_plugin" \
	"$season_plugin" "$sponsorships_plugin" "$checkout_plugin" "$weather_plugin" \
	"$events_plugin" "$fill_dates_plugin" "$data_tools_plugin" "$express_bar_plugin" "$raf_plugin" \
	"$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" "$bvm_plugin"

wp_fixture option update active_plugins '[]' --format=json --skip-plugins --skip-themes --quiet >/dev/null
if validate_database_name \
	&& wp_fixture db drop --yes --quiet >/dev/null \
	&& bvm_test_containment_database_absent "$database_name"; then
	database_created=no
	database_cleanup=pass
	bvm_test_containment_residue=removed-and-asserted
else
	database_cleanup=fail
	printf 'DISPOSABLE DATABASE CLEANUP FAILED: %s\n' "$database_name" >&2
fi

if safe_remove_runtime && [ ! -e "$runtime_root" ]; then
	runtime_cleanup=pass
else
	runtime_cleanup=fail
fi

normal_after=$(normal_active_plugins_hash)
normal_config_after=$("$php_bin" -r 'echo hash_file("sha256", $argv[1]);' "$wordpress_source_root/wp-config.php")
if [ "$normal_config_before" != "$normal_config_after" ]; then
	printf 'Normal wp-config.php changed during the isolated harness.\n' >&2
	exit 1
fi
bvm_test_containment_capture_normal_state "$bvm_test_containment_after"
if ! cmp -s "$bvm_test_containment_before" "$bvm_test_containment_after"; then
	printf 'Normal Local cron, Weather, activation, configuration, or database state changed during the isolated harness.\n' >&2
	diff -u "$bvm_test_containment_before" "$bvm_test_containment_after" >&2 || true
	diff -u "${bvm_test_containment_before%.tsv}-tables.tsv" "${bvm_test_containment_after%.tsv}-tables.tsv" >&2 || true
	diff -u "${bvm_test_containment_before%.tsv}-options.tsv" "${bvm_test_containment_after%.tsv}-options.tsv" >&2 || true
	exit 1
fi
bvm_test_containment_normal_state_result=pass
if ! bvm_test_containment_remove_guards; then
	exit 1
fi
if ! bvm_test_containment_release_lock; then
	exit 1
fi
bvm_test_containment_write_cleanup_report "$database_cleanup" "$runtime_cleanup"
export BVM_COMPAT_TEST_CONTAINMENT_HTTP=$bvm_test_containment_http
export BVM_COMPAT_TEST_CONTAINMENT_PROCESS_BOUNDARY=$bvm_test_containment_process_boundary
export BVM_COMPAT_TEST_CONTAINMENT_RESIDUE=$bvm_test_containment_residue
export BVM_COMPAT_TEST_CONTAINMENT_NORMAL_STATE=$bvm_test_containment_normal_state_result
export BVM_COMPAT_TEST_CONTAINMENT_GUARD_CLEANUP=$bvm_test_containment_guard_cleanup
bridge_status_after=$(git -C "$bridge_repo" status --porcelain=v1 --untracked-files=all)
bridge_status_after_sha256=$(printf '%s\n' "$bridge_status_after" | shasum -a 256 | awk '{print $1}')
if [ "$bridge_status_before" != "$bridge_status_after" ]; then
	printf 'DRM Events Bridge forensic worktree changed during the isolated harness.\n' >&2
	exit 1
fi
export BVM_COMPAT_BRIDGE_STATUS_AFTER_SHA256=$bridge_status_after_sha256

report_json=$output_dir/$report_basename.report.json
report_text=$output_dir/$report_basename.report.txt
set +e
"$php_bin" "$report_builder" \
	"$scenario_index" \
	"$source_manifest" \
	"$normal_before" \
	"$normal_after" \
	"$database_cleanup" \
	"$runtime_cleanup" \
	"$report_json" \
	"$report_text"
report_exit=$?
set -e

printf 'Harness evidence: %s\n' "$output_dir"
printf 'JSON report: %s\n' "$report_json"
printf 'Text report: %s\n' "$report_text"
exit "$report_exit"
