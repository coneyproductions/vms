<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('BACKSTAGE_OUTREACH_PLUGIN_URL', 'https://example.invalid/wp-content/plugins/backstage-outreach/');
define('BACKSTAGE_OUTREACH_VERSION', '1.0.0');

final class WP_Error
{
	public function __construct(private string $code = '', private string $message = '') {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}

$GLOBALS['outreach_test_hooks'] = array('actions' => array(), 'filters' => array());
$GLOBALS['outreach_test_claim_success'] = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool { $GLOBALS['outreach_test_hooks']['actions'][] = array($hook, $callback, $priority, $accepted_args); return true; }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool { $GLOBALS['outreach_test_hooks']['filters'][] = array($hook, $callback, $priority, $accepted_args); return true; }
function __($text, $domain = ''): string { return (string) $text; }
function sanitize_key($value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_email($value): string { return filter_var((string) $value, FILTER_VALIDATE_EMAIL) ? strtolower((string) $value) : ''; }
function absint($value): int { return abs((int) $value); }

function vms_outreach_admin_page_url(array $args = array()): string { return 'admin.php?page=vms-outreach'; }
function vms_pass_outreach_claim_context_for_token(array $token, array $batch): array { return !empty($token['outreach']) ? array('recipient' => array('id' => 22, 'first_name' => 'Alex', 'last_name' => 'Guest', 'email' => 'alex@example.invalid', 'phone' => '555-0100'), 'campaign' => array('id' => 7), 'batch' => $batch, 'token_row' => $token) : array(); }
function vms_pass_outreach_effective_recipient_cap(array $batch, ?array $campaign = null): int { return is_array($campaign) ? 2 : max(1, (int) ($batch['admissions_per_link'] ?? 1)); }
function vms_pass_outreach_filter_events_for_campaign(array $campaign, array $events): array { return array_values(array_filter($events, static fn(array $event): bool => (int) ($event['id'] ?? 0) !== 2)); }
function vms_pass_outreach_record_recipient_claim_success(array $recipient, array $campaign, int $claim_id, int $entry_id, int $party_size, int $token_id): void { $GLOBALS['outreach_test_claim_success'][] = func_get_args(); }
function vms_pass_outreach_log_claim_denial(array $context): void {}
function vms_pass_outreach_public_failure_message(): string { return 'This invitation cannot be claimed.'; }
function vms_pass_outreach_recipient_preflight(array $recipient, array $campaign, array $batch, array $token): array { return array('ok' => true); }
function vms_pass_outreach_campaign_preflight(array $campaign): array { return array('ok' => true); }

require_once dirname(__DIR__) . '/companion-plugins/backstage-outreach/includes/integration-bvm.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$filterHooks = array_column($GLOBALS['outreach_test_hooks']['filters'], 0);
$actionHooks = array_column($GLOBALS['outreach_test_hooks']['actions'], 0);
foreach (array('bvmgr_pass_claims_admin_tabs', 'bvmgr_pass_claims_admin_tab_url', 'bvmgr_pass_claims_claim_context', 'bvmgr_pass_claims_claim_preflight_error', 'bvmgr_pass_claims_eligible_events', 'bvmgr_pass_claims_default_posted', 'bvmgr_pass_claims_max_party_size', 'bvmgr_pass_claims_claim_validation_error', 'bvmgr_pass_claims_claim_insert_payload', 'bvmgr_pass_claims_claim_meta', 'bvmgr_pass_claims_public_claim_error') as $hook) {
	$assert(in_array($hook, $filterHooks, true), 'Companion must register BVM filter ' . $hook . '.');
}
$assert(in_array('bvmgr_pass_claims_claim_created', $actionHooks, true), 'Companion must register the BVM claim-completion action.');
$assert(in_array('template_redirect', $actionHooks, true) && in_array('init', $actionHooks, true), 'Companion must register its public invite route and router.');

$tabs = backstage_outreach_add_guest_pass_tab(array('sources' => 'Sources', 'batches' => 'Batches', 'passes' => 'Guest Passes', 'reports' => 'Reports'));
$assert(array_keys($tabs) === array('sources', 'batches', 'passes', 'reports', 'outreach'), 'Guest Pass navigation must add Outreach without replacing the four core tabs.');
$assert(backstage_outreach_guest_pass_tab_url('core-url', 'outreach') === 'admin.php?page=vms-outreach', 'The Outreach tab must route to the companion page.');
$assert(backstage_outreach_guest_pass_tab_url('core-url', 'passes') === 'core-url', 'Existing Guest Pass tab URLs must remain unchanged.');
$assert(count(backstage_outreach_add_top_nav_item(array(), 'marketing_social')) === 1, 'Marketing & Social navigation must expose Outreach.');
$existingTopNav = array(array('label' => 'Outreach', 'url' => 'admin.php?page=vms-outreach'));
$assert(backstage_outreach_add_top_nav_item($existingTopNav, 'marketing_social') === $existingTopNav, 'Registry-provided Outreach navigation must not be duplicated.');
$assert(backstage_outreach_add_top_nav_item(array(), 'planning') === array(), 'Unrelated top-navigation clusters must remain unchanged.');

$normalContext = backstage_outreach_claim_context(array('keep' => true), array('id' => 1), array('id' => 9));
$assert($normalContext === array('keep' => true), 'Ordinary Guest Pass tokens must retain their existing claim context.');
$outreachContext = backstage_outreach_claim_context(array(), array('id' => 101, 'outreach' => true), array('id' => 9, 'admissions_per_link' => 4));
$assert((int) $outreachContext['recipient']['id'] === 22 && (int) $outreachContext['campaign']['id'] === 7, 'Reserved Outreach tokens must resolve their recipient and campaign context.');
$assert(backstage_outreach_claim_preflight_error(null, array('id' => 1), array('id' => 9), array()) === null, 'Ordinary Guest Pass tokens must bypass Outreach preflight.');
$missingCampaignError = backstage_outreach_claim_preflight_error(null, array('id' => 101), array('id' => 9), array('recipient' => array('id' => 22), 'campaign' => null));
$assert($missingCampaignError instanceof WP_Error && $missingCampaignError->get_error_code() === 'campaign_missing', 'A reserved Outreach token must fail closed if its campaign record is missing.');
$assert(backstage_outreach_claim_preflight_error(null, array('id' => 101), array('id' => 9), $outreachContext) === null, 'A valid reserved Outreach token must pass recovered campaign preflight.');

$events = backstage_outreach_filter_claim_events(array(array('id' => 1), array('id' => 2)), array(), array(), $outreachContext);
$assert(array_column($events, 'id') === array(1), 'Outreach campaigns must be able to narrow current BVM eligible events.');
$normalEvents = backstage_outreach_filter_claim_events(array(array('id' => 1), array('id' => 2)), array(), array(), array());
$assert(array_column($normalEvents, 'id') === array(1, 2), 'Ordinary Guest Pass event eligibility must remain unchanged.');

$posted = backstage_outreach_prefill_claim(array('first_name' => '', 'last_name' => '', 'phone' => '', 'email' => ''), $outreachContext);
$assert($posted['first_name'] === 'Alex' && $posted['email'] === 'alex@example.invalid', 'Recipient identity must prefill only its reserved claim form.');
$assert(backstage_outreach_max_party_size(4, array('admissions_per_link' => 4), $outreachContext) === 2, 'Campaign recipient limits must cap the current BVM party-size control.');
$assert(backstage_outreach_max_party_size(4, array('admissions_per_link' => 4), array()) === 4, 'Ordinary Guest Pass party-size limits must remain unchanged.');

$basePayload = array('data' => array('token_id' => 101), 'formats' => array('%d'));
$normalPayload = backstage_outreach_claim_insert_payload($basePayload, array());
$assert($normalPayload === $basePayload, 'Ordinary claims must not receive Outreach columns.');
$attributedPayload = backstage_outreach_claim_insert_payload($basePayload, $outreachContext);
$assert($attributedPayload['data']['outreach_campaign_id'] === 7 && $attributedPayload['data']['outreach_recipient_id'] === 22, 'Outreach claims must preserve campaign and recipient attribution IDs.');
$meta = backstage_outreach_claim_meta(array('group_size' => 1), $outreachContext);
$assert($meta['outreach_campaign_id'] === 7 && $meta['outreach_recipient_id'] === 22, 'Admission claim metadata must preserve Outreach attribution for scanner/check-in records.');

backstage_outreach_record_claim_success(array('claim_id' => 31, 'entry_id' => 41, 'party_size' => 2), array('id' => 101), array());
$assert($GLOBALS['outreach_test_claim_success'] === array(), 'Ordinary Guest Pass claims must not mutate Outreach recipients.');
backstage_outreach_record_claim_success(array('claim_id' => 31, 'entry_id' => 41, 'party_size' => 2), array('id' => 101), $outreachContext);
$assert($GLOBALS['outreach_test_claim_success'][0] === array($outreachContext['recipient'], $outreachContext['campaign'], 31, 41, 2, 101), 'Successful Outreach claims must update only the linked recipient using current BVM IDs.');

echo 'Backstage Outreach BVM integration OK (' . $assertions . " assertions).\n";
