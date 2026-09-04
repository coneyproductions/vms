<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('MINUTE_IN_SECONDS', 60);
define('BVMGR_VERSION', '1.2.0');

final class WP_Error
{
	public function __construct(private string $code = '', private string $message = '') {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}

final class BackstageOutreachTestWpdb
{
	public string $prefix = 'wp_';

	public function prepare(string $query, ...$args): string
	{
		return $query;
	}

	public function get_results(string $query, $format = null): array
	{
		if (strpos($query, 'FROM wp_vms_pass_outreach_campaigns c') !== false) {
			return array(array(
				'id' => 7,
				'campaign_name' => 'Recovered Campaign',
				'campaign_purpose' => 'guest_pass_invitation',
				'email_subject' => 'You are invited',
				'message_template' => 'Hello {first_name}',
				'related_source_id' => 4,
				'related_batch_id' => 9,
				'admissions_per_recipient' => 2,
				'status' => 'active',
			));
		}
		if (strpos($query, 'FROM wp_vms_pass_outreach_recipients') !== false) {
			return array(array(
				'id' => 22,
				'campaign_id' => 7,
				'pass_token_id' => 101,
				'first_name' => 'Alex',
				'last_name' => 'Guest',
				'full_name' => 'Alex Guest',
				'email' => 'alex@example.invalid',
				'email_norm' => 'alex@example.invalid',
				'invite_token' => 'preserved-invite-token',
				'send_status' => 'not_sent',
				'send_method' => '',
				'status' => 'ready',
			));
		}
		return array();
	}
}

$GLOBALS['wpdb'] = new BackstageOutreachTestWpdb();
$GLOBALS['backstage_outreach_test_hooks'] = array();
$GLOBALS['backstage_outreach_test_modules'] = array();
$GLOBALS['backstage_outreach_test_pages'] = array();
$GLOBALS['backstage_outreach_test_schema_calls'] = 0;

function __($text, $domain = ''): string { return (string) $text; }
function esc_html__($text, $domain = ''): string { return (string) $text; }
function sanitize_key($value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_email($value): string { return filter_var((string) $value, FILTER_VALIDATE_EMAIL) ? strtolower((string) $value) : ''; }
function wp_check_invalid_utf8($value): string { return (string) $value; }
function wp_strip_all_tags($value): string { return strip_tags((string) $value); }
function absint($value): int { return abs((int) $value); }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
function plugin_dir_url(string $file): string { return 'https://example.invalid/wp-content/plugins/backstage-outreach/'; }
function plugin_basename(string $file): string { return basename(dirname($file)) . '/' . basename($file); }
function register_activation_hook(string $file, string $callback): void {}
function load_plugin_textdomain(string $domain, bool $deprecated = false, string $path = ''): bool { return true; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool { $GLOBALS['backstage_outreach_test_hooks'][] = array('action', $hook, $callback, $priority, $accepted_args); return true; }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool { $GLOBALS['backstage_outreach_test_hooks'][] = array('filter', $hook, $callback, $priority, $accepted_args); return true; }
function apply_filters($hook, $value) { return $value; }

function bvmgr_register_module(array $module): bool { $GLOBALS['backstage_outreach_test_modules'][] = $module; return true; }
function bvmgr_register_admin_page(array $page): bool { $GLOBALS['backstage_outreach_test_pages'][] = $page; return true; }
function bvmgr_admission_table_pass_sources(): string { return 'wp_vms_pass_sources'; }
function bvmgr_admission_table_pass_batches(): string { return 'wp_vms_pass_batches'; }
function bvmgr_admission_table_pass_tokens(): string { return 'wp_vms_pass_tokens'; }
function bvmgr_admission_table_pass_claims(): string { return 'wp_vms_pass_claims'; }
function bvmgr_admission_table_entries(): string { return 'wp_vms_admission_entries'; }
function bvmgr_pass_claims_get_batches(int $limit = 200): array { return array(array('id' => 9, 'batch_name' => 'Recovered Batch')); }
function bvmgr_pass_claims_get_token_by_id(int $token_id): ?array { return $token_id === 101 ? array('id' => 101, 'batch_id' => 9, 'status' => 'unclaimed') : null; }
function bvmgr_pass_claims_capability(): string { return 'manage_options'; }
function vms_outreach_maybe_upgrade_schema(): void { $GLOBALS['backstage_outreach_test_schema_calls']++; }

require_once dirname(__DIR__) . '/companion-plugins/backstage-outreach/backstage-outreach.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(!defined('VMS_PLUGIN_FILE'), 'The bootstrap fixture must represent BVM active with legacy VMS inactive.');
$assert(backstage_outreach_dependency_error() === '', 'BVM 1.2 with legacy VMS inactive must satisfy the companion dependency gate.');
$assert(function_exists('backstage_outreach_boot'), 'The companion bootstrap must register its loader.');

backstage_outreach_boot();
$assert(function_exists('vms_pass_outreach_get_campaigns') && function_exists('vms_pass_outreach_get_recipients_for_campaign'), 'Boot must load the recovered campaign and recipient APIs.');
$assert(count($GLOBALS['backstage_outreach_test_modules']) === 1 && $GLOBALS['backstage_outreach_test_modules'][0]['source'] === 'backstage-outreach', 'Boot must register the recovered module through BVM.');
$assert($GLOBALS['backstage_outreach_test_schema_calls'] === 1, 'Boot must invoke the companion schema gate exactly once.');

vms_outreach_register_admin_page_metadata();
$assert(count($GLOBALS['backstage_outreach_test_pages']) === 1 && $GLOBALS['backstage_outreach_test_pages'][0]['callback'] === 'vms_outreach_render_admin_page', 'The recovered admin page must register through the BVM registry.');

$campaigns = vms_pass_outreach_get_campaigns();
$assert(count($campaigns) === 1 && $campaigns[0]['id'] === 7 && $campaigns[0]['campaign_name'] === 'Recovered Campaign', 'Existing campaign rows must be read and normalized through the preserved table contract.');
$recipients = vms_pass_outreach_get_recipients_for_campaign(7);
$assert(count($recipients) === 1 && $recipients[0]['id'] === 22 && $recipients[0]['pass_token_id'] === 101, 'Existing recipient IDs and token reservations must be read through the preserved table contract.');
$assert(vms_pass_claims_get_batches()[0]['id'] === 9 && vms_pass_claims_get_token_by_id(101)['batch_id'] === 9, 'Recovered compatibility calls must recognize current BVM Guest Pass batches and tokens.');

echo 'Backstage Outreach bootstrap/data-read regression OK (' . $assertions . " assertions).\n";
