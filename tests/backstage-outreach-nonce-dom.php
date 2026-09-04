<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function sanitize_html_class($value): string
{
	return (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value);
}

function esc_attr($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function wp_create_nonce($action): string
{
	return 'nonce-' . substr(hash('sha256', (string) $action), 0, 16);
}

function wp_verify_nonce($nonce, $action): bool
{
	return is_string($nonce) && hash_equals(wp_create_nonce($action), $nonce);
}

function wp_referer_field($display = true): string
{
	$field = '<input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=vms-outreach" />';
	if ($display) {
		echo $field;
	}
	return $field;
}

function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true): string
{
	$GLOBALS['outreach_nonce_calls'][] = array(
		'action' => (string) $action,
		'name' => (string) $name,
		'referer' => (bool) $referer,
		'display' => (bool) $display,
	);

	$name = esc_attr($name);
	$field = '<input type="hidden" id="' . $name . '" name="' . $name . '" value="' . esc_attr(wp_create_nonce($action)) . '" />';
	if ($referer) {
		$field .= wp_referer_field(false);
	}
	if ($display) {
		echo $field;
	}
	return $field;
}

$GLOBALS['outreach_nonce_calls'] = array();

require_once dirname(__DIR__) . '/companion-plugins/backstage-outreach/includes/outreach/helpers.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$forms = array(
	array('campaign-save', 'vms_pass_outreach_campaign_save', 'vms-outreach-campaign-save-4-nonce'),
	array('contact-save', 'vms_outreach_contact_save', 'vms-outreach-contact-save-8-nonce'),
	array('contact-import', 'vms_outreach_contact_import', 'vms-outreach-contact-import-nonce'),
	array('contact-unsuppress', 'vms_outreach_suppression_remove', 'vms-outreach-contact-suppression-remove-8-nonce'),
	array('contact-delete', 'vms_outreach_contact_delete', 'vms-outreach-contact-delete-8-nonce'),
	array('suppression-save', 'vms_outreach_suppression_save', 'vms-outreach-suppression-save-9-nonce'),
	array('suppression-remove', 'vms_outreach_suppression_remove', 'vms-outreach-suppression-remove-9-nonce'),
	array('recipient-import', 'vms_pass_outreach_recipient_import', 'vms-outreach-recipient-import-4-nonce'),
	array('recipient-export', 'vms_pass_outreach_recipient_export', 'vms-outreach-recipient-export-4-nonce'),
	array('contact-audience', 'vms_pass_outreach_contact_audience', 'vms-outreach-contact-audience-4-nonce'),
	array('recipient-save', 'vms_pass_outreach_recipient_save', 'vms-outreach-recipient-save-4-8-nonce'),
	array('send-queued', 'vms_pass_outreach_send_queued_recipients', 'vms-outreach-send-queued-4-nonce'),
	array('bulk-queue-all', 'vms_pass_outreach_recipient_bulk', 'vms-outreach-recipient-bulk-queue-all-4-nonce'),
	array('bulk-queue-review', 'vms_pass_outreach_recipient_bulk', 'vms-outreach-recipient-bulk-queue-review-4-nonce'),
	array('bulk-list', 'vms_pass_outreach_recipient_bulk', 'vms-outreach-recipient-bulk-list-4-nonce'),
);

ob_start();
echo '<!doctype html><html><body>';
foreach ($forms as [$form_id, $action, $nonce_id]) {
	echo '<form id="' . esc_attr($form_id) . '" method="post">';
	vms_outreach_nonce_field($action, $nonce_id);
	echo '</form>';
}
echo '</body></html>';
$html = (string) ob_get_clean();

$document = new DOMDocument('1.0', 'UTF-8');
$previous_errors = libxml_use_internal_errors(true);
$loaded = $document->loadHTML($html);
libxml_clear_errors();
libxml_use_internal_errors($previous_errors);
$assert($loaded === true, 'The nonce fixture must produce parseable HTML.');

$xpath = new DOMXPath($document);
$id_counts = array();
foreach ($xpath->query('//*[@id]') ?: array() as $element) {
	$id = $element->getAttribute('id');
	$id_counts[$id] = ($id_counts[$id] ?? 0) + 1;
}
$duplicate_ids = array_filter($id_counts, static fn (int $count): bool => $count > 1);
$assert($duplicate_ids === array(), 'Every rendered Outreach element ID must be unique.');
$assert(!isset($id_counts['_wpnonce']), 'The default _wpnonce element ID must not remain.');
$assert(!isset($id_counts['_wp_http_referer']), 'The referer field must not gain a duplicate-prone element ID.');

$nonce_ids = array();
foreach ($forms as [$form_id, $action, $nonce_id]) {
	$form_nodes = $xpath->query('//form[@id="' . $form_id . '"]');
	$assert($form_nodes !== false && $form_nodes->length === 1, $form_id . ' must render once.');
	$form = $form_nodes->item(0);
	$nonce_nodes = $xpath->query('.//input[@name="_wpnonce"]', $form);
	$referer_nodes = $xpath->query('.//input[@name="_wp_http_referer"]', $form);
	$assert($nonce_nodes !== false && $nonce_nodes->length === 1, $form_id . ' must contain one default-name nonce field.');
	$assert($referer_nodes !== false && $referer_nodes->length === 1, $form_id . ' must retain one referer field.');

	$nonce_input = $nonce_nodes->item(0);
	$referer_input = $referer_nodes->item(0);
	$assert($nonce_input->getAttribute('id') === $nonce_id, $form_id . ' must use its assigned unique nonce ID.');
	$assert($nonce_input->getAttribute('name') === '_wpnonce', $form_id . ' must preserve the submitted nonce field name.');
	$assert($referer_input->getAttribute('value') === '/wp-admin/admin.php?page=vms-outreach', $form_id . ' must preserve referer-field behavior.');
	$assert(!$referer_input->hasAttribute('id'), $form_id . ' referer must retain WordPress core name-only markup.');
	$assert(wp_verify_nonce($nonce_input->getAttribute('value'), $action), $form_id . ' nonce must validate only for its existing action.');
	$assert(!wp_verify_nonce('wrong-nonce', $action), $form_id . ' must reject a wrong nonce.');
	$assert(!wp_verify_nonce('', $action), $form_id . ' must reject a missing nonce.');
	$nonce_ids[] = $nonce_id;
}

$assert(count($nonce_ids) === count(array_unique($nonce_ids)), 'Assigned nonce IDs must be unique across every Outreach form variant.');
$assert(!wp_verify_nonce(wp_create_nonce($forms[0][1]), $forms[1][1]), 'A campaign nonce must not authorize the contact form.');
$assert(!wp_verify_nonce(wp_create_nonce($forms[7][1]), $forms[8][1]), 'A recipient-import nonce must not authorize an export.');
$assert(!wp_verify_nonce(wp_create_nonce($forms[10][1]), $forms[11][1]), 'A recipient-save nonce must not authorize delivery.');

$assert(count($GLOBALS['outreach_nonce_calls']) === count($forms), 'Every form must delegate nonce creation to WordPress core.');
foreach ($GLOBALS['outreach_nonce_calls'] as $call) {
	$assert($call['name'] === '_wpnonce', 'The helper must preserve the default submitted nonce field name.');
	$assert($call['referer'] === true, 'The helper must retain WordPress referer generation.');
	$assert($call['display'] === false, 'The helper must capture core markup only to replace its nonce element ID.');
}

$root = dirname(__DIR__);
$helper_source = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/helpers.php');
$campaign_source = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach.php');
$recipient_source = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach-recipients.php');
$admin_source = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/admin-ui.php');
$renderer_source = $campaign_source . $recipient_source . $admin_source;

$assert(substr_count($helper_source, "wp_nonce_field(\$action, '_wpnonce', true, false)") === 1, 'The helper must preserve WordPress nonce/referer generation and the _wpnonce field name.');
$assert(substr_count($renderer_source, 'wp_nonce_field(') === 0, 'Outreach form renderers must not emit duplicate-prone default IDs directly.');
$assert(substr_count($renderer_source, 'vms_outreach_nonce_field(') === 15, 'All 15 Outreach nonce-field render sites must use the unique-ID helper.');

foreach (array(
	'vms_pass_outreach_campaign_save',
	'vms_outreach_contact_save',
	'vms_outreach_contact_import',
	'vms_outreach_contact_delete',
	'vms_outreach_suppression_save',
	'vms_outreach_suppression_remove',
	'vms_pass_outreach_recipient_import',
	'vms_pass_outreach_recipient_export',
	'vms_pass_outreach_contact_audience',
	'vms_pass_outreach_recipient_save',
	'vms_pass_outreach_send_queued_recipients',
	'vms_pass_outreach_recipient_bulk',
) as $action) {
	$assert(substr_count($renderer_source, "check_admin_referer('" . $action . "')") === 1, $action . ' must retain its existing server-side verification action.');
}

$handler_count = substr_count($campaign_source . $recipient_source . $admin_source, "add_action('admin_post_");
$capability_count = substr_count($campaign_source . $recipient_source . $admin_source, 'current_user_can(');
$assert($handler_count === 23 && $capability_count >= $handler_count, 'All existing Outreach handlers must retain capability enforcement.');

echo 'Backstage Outreach nonce DOM/security OK (' . $assertions . " assertions).\n";
