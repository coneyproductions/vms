<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['outreach_help_filters'] = array();
$GLOBALS['outreach_help_actions'] = array();

function add_filter(string $hook, $callback, int $priority = 10): void
{
	$GLOBALS['outreach_help_filters'][$hook][$priority][] = $callback;
}

function add_action(string $hook, $callback, int $priority = 10): void
{
	$GLOBALS['outreach_help_actions'][$hook][$priority][] = $callback;
}

function __($text, $domain = null): string { return (string) $text; }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function esc_attr($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html__($text, $domain = null): string { return esc_html((string) $text); }
function wp_unslash($value) { return $value; }
function is_admin(): bool { return true; }
function current_user_can(string $capability): bool { return $capability === 'manage_options'; }
function bvmgr_pass_claims_capability(): string { return 'manage_options'; }
function bvmgr_render_help_button(array $args = array()): string
{
	return '<button type="button" data-vms-tour-start="' . esc_attr((string) ($args['tour_id'] ?? '')) . '" data-vms-tour="' . esc_attr((string) ($args['anchor'] ?? '')) . '">' . esc_html((string) ($args['label'] ?? 'Help')) . '</button>';
}

function outreach_help_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

require dirname(__DIR__) . '/companion-plugins/backstage-outreach/includes/contextual-help.php';

outreach_help_assert(
	isset($GLOBALS['outreach_help_filters']['vms_tours_register'][40][0]),
	'Contextual help must register through the existing vms_tours_register filter.'
);
outreach_help_assert(
	isset($GLOBALS['outreach_help_actions']['admin_notices'][20][0]),
	'The batch launcher must use an existing admin rendering hook.'
);

$tours = backstage_outreach_register_contextual_help_tours(array());
outreach_help_assert(count($tours) === 2, 'Expected one batch tour and one Outreach tour.');
$indexed = array();
foreach ($tours as $tour) {
	$indexed[(string) ($tour['id'] ?? '')] = $tour;
	outreach_help_assert(($tour['auto_run'] ?? null) === false, 'Contextual tours must never auto-launch.');
	outreach_help_assert(!empty($tour['allow_restart']), 'Contextual tours must remain manually restartable.');
	outreach_help_assert(!empty($tour['steps']), 'Each contextual tour needs runnable steps.');
	foreach ((array) $tour['steps'] as $step) {
		outreach_help_assert(empty($step['on_show']), 'Tour steps must not click, set values, or mutate forms.');
		outreach_help_assert(empty($step['allow_click_through']), 'Tour steps must not allow accidental form submission.');
	}
}

$batch = $indexed['backstage-outreach.guest-pass-batch-setup'] ?? array();
$outreach = $indexed['backstage-outreach.business-qr-setup'] ?? array();
outreach_help_assert(($batch['screen'] ?? '') === 'admin:vms-passes', 'Batch tour screen changed.');
outreach_help_assert(($outreach['screen'] ?? '') === 'admin:vms-outreach', 'Outreach tour screen changed.');

$copy = '';
foreach ($tours as $tour) {
	foreach ((array) ($tour['steps'] ?? array()) as $step) {
		$copy .= ' ' . strip_tags((string) ($step['body'] ?? ''));
	}
}
foreach (array(
	'does not create a WooCommerce coupon',
	'link its Source and batch to an Outreach campaign',
	'reusable business referral QR',
	'complete normal checkout',
	'Complimentary Guest Pass issues free admissions',
	'up to 2 people',
	'paid-order cap per business',
	'Campaign and batch caps',
	'Historical coupon usage can differ from currently paid orders',
	'not by directly editing its managed WooCommerce coupon',
	'Copy Link, QR Download, or Printable QR',
	'Previously purchased tickets',
) as $required_copy) {
	outreach_help_assert(strpos($copy, $required_copy) !== false, 'Missing required operator guidance: ' . $required_copy);
}

$_GET = array('page' => 'vms-passes', 'tab' => 'batches');
ob_start();
backstage_outreach_render_batch_help_launcher();
$launcher = (string) ob_get_clean();
outreach_help_assert(strpos($launcher, 'data-vms-tour-start="backstage-outreach.guest-pass-batch-setup"') !== false, 'Batch launcher did not target the registered tour.');
outreach_help_assert(strpos($launcher, 'type="button"') !== false, 'Batch launcher must be a non-submitting button.');

$distribution_source = (string) file_get_contents(dirname(__DIR__) . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
$outreach_source = (string) file_get_contents(dirname(__DIR__) . '/companion-plugins/backstage-outreach/includes/outreach/outreach.php');
$plugin_source = (string) file_get_contents(dirname(__DIR__) . '/companion-plugins/backstage-outreach/backstage-outreach.php');
foreach (array(
	'outreach-business-selection',
	'outreach-distribution-type',
	'outreach-business-limits',
	'outreach-review-selection',
	'outreach-reviewed-preview',
	'outreach-qr-actions',
	'outreach-business-results',
	'outreach-offer-lifecycle',
) as $anchor) {
	outreach_help_assert(strpos($distribution_source, $anchor) !== false, 'Missing business-distribution tour anchor: ' . $anchor);
}
outreach_help_assert(strpos($outreach_source, "'actions_html' => \$actions_html") !== false, 'Outreach shell must expose the manual tour launcher.');
outreach_help_assert(strpos($plugin_source, "includes/contextual-help.php") !== false, 'Contextual help module is not loaded by Outreach.');

fwrite(STDOUT, "outreach contextual help: PASS\n");
