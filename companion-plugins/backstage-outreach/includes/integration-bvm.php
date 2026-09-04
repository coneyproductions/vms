<?php
/**
 * BVM 1.2 integration for the recovered Guest Pass Outreach workflow.
 */

defined('ABSPATH') || exit;

if (!function_exists('backstage_outreach_add_guest_pass_tab')) {
	function backstage_outreach_add_guest_pass_tab(array $tabs): array
	{
		$tabs['outreach'] = __('Outreach', 'backstage-outreach');
		return $tabs;
	}
}
add_filter('bvmgr_pass_claims_admin_tabs', 'backstage_outreach_add_guest_pass_tab');

if (!function_exists('backstage_outreach_guest_pass_tab_url')) {
	function backstage_outreach_guest_pass_tab_url(string $url, string $tab): string
	{
		return $tab === 'outreach' ? vms_outreach_admin_page_url() : $url;
	}
}
add_filter('bvmgr_pass_claims_admin_tab_url', 'backstage_outreach_guest_pass_tab_url', 10, 2);

if (!function_exists('backstage_outreach_add_top_nav_item')) {
	function backstage_outreach_add_top_nav_item(array $items, string $cluster_key): array
	{
		if ($cluster_key === 'marketing_social') {
			$url = vms_outreach_admin_page_url();
			foreach ($items as $item) {
				if (is_array($item) && (string) ($item['url'] ?? '') === $url) {
					return $items;
				}
			}
			$items[] = array('label' => __('Outreach', 'backstage-outreach'), 'url' => $url);
		}
		return $items;
	}
}
add_filter('vms_admin_ui_nav_cluster_items', 'backstage_outreach_add_top_nav_item', 20, 2);

if (!function_exists('backstage_outreach_enqueue_admin_assets')) {
	function backstage_outreach_enqueue_admin_assets(): void
	{
		if (!vms_outreach_is_admin_page()) {
			return;
		}
		$css_path = BACKSTAGE_OUTREACH_PLUGIN_PATH . 'assets/css/outreach-admin.css';
		$js_path = BACKSTAGE_OUTREACH_PLUGIN_PATH . 'assets/js/outreach-admin.js';
		$css_version = is_file($css_path) ? (string) filemtime($css_path) : BACKSTAGE_OUTREACH_VERSION;
		$js_version = is_file($js_path) ? (string) filemtime($js_path) : BACKSTAGE_OUTREACH_VERSION;
		wp_enqueue_style(
			'backstage-outreach-admin',
			BACKSTAGE_OUTREACH_PLUGIN_URL . 'assets/css/outreach-admin.css',
			array('bvmgr-admin', 'bvmgr-admin-ui'),
			$css_version
		);
		wp_enqueue_script(
			'backstage-outreach-admin',
			BACKSTAGE_OUTREACH_PLUGIN_URL . 'assets/js/outreach-admin.js',
			array(),
			$js_version,
			true
		);
	}
}
add_action('admin_enqueue_scripts', 'backstage_outreach_enqueue_admin_assets', 50);

if (!function_exists('backstage_outreach_register_public_route')) {
	function backstage_outreach_register_public_route(): void
	{
		add_rewrite_tag('%backstage_outreach_invite_token%', '([^&]+)');
		add_rewrite_rule('^pass/invite/([^/]+)/?$', 'index.php?backstage_outreach_invite_token=$matches[1]', 'top');

		if ((string) get_option('backstage_outreach_flush_rewrite', '') === '1') {
			flush_rewrite_rules(false);
			delete_option('backstage_outreach_flush_rewrite');
		}
	}
}
add_action('init', 'backstage_outreach_register_public_route', 31);

if (!function_exists('backstage_outreach_request_invite_token')) {
	function backstage_outreach_request_invite_token(): string
	{
		$value = get_query_var('backstage_outreach_invite_token', '');
		if (is_scalar($value) && (string) $value !== '') {
			return sanitize_text_field(rawurldecode((string) $value));
		}

		$uri = function_exists('bvmgr_request_current_uri') ? bvmgr_request_current_uri() : '';
		if ($uri !== '' && preg_match('~^/pass/invite/([^/?#]+)~', $uri, $matches)) {
			return sanitize_text_field(rawurldecode((string) $matches[1]));
		}
		return '';
	}
}

if (!function_exists('backstage_outreach_public_invite_router')) {
	function backstage_outreach_public_invite_router(): void
	{
		if (is_admin()) {
			return;
		}
		$invite_token = backstage_outreach_request_invite_token();
		if ($invite_token === '') {
			return;
		}

		$context = vms_pass_outreach_resolve_public_invite_context($invite_token);
		if (is_wp_error($context)) {
			vms_pass_outreach_render_public_unavailable(array(
				'reason_code' => sanitize_key((string) $context->get_error_code()),
				'admin_reasons' => array(sanitize_text_field($context->get_error_message())),
			));
		}

		vms_pass_outreach_set_public_context((array) $context);
		$raw_token = bvmgr_pass_claims_build_raw_token((array) ($context['token_row'] ?? array()));
		if ($raw_token === '') {
			vms_pass_outreach_render_public_unavailable(array(
				'reason_code' => 'invalid_invite_token',
				'admin_reasons' => array('Reserved Guest Pass token could not be resolved'),
			));
		}
		bvmgr_pass_claims_render_public_claim($raw_token);
	}
}
add_action('template_redirect', 'backstage_outreach_public_invite_router', -1);

if (!function_exists('backstage_outreach_claim_context')) {
	function backstage_outreach_claim_context(array $context, array $token_row, array $batch): array
	{
		$recovered = vms_pass_outreach_claim_context_for_token($token_row, $batch);
		return !empty($recovered) ? $recovered : $context;
	}
}
add_filter('bvmgr_pass_claims_claim_context', 'backstage_outreach_claim_context', 10, 3);

if (!function_exists('backstage_outreach_claim_preflight_error')) {
	function backstage_outreach_claim_preflight_error($error, array $token_row, array $batch, array $context)
	{
		$recipient = is_array($context['recipient'] ?? null) ? (array) $context['recipient'] : array();
		$campaign = is_array($context['campaign'] ?? null) ? (array) $context['campaign'] : array();
		if (empty($recipient)) {
			return $error;
		}
		if (empty($campaign)) {
			vms_pass_outreach_log_claim_denial(array(
				'reason_code' => 'campaign_missing',
				'admin_reasons' => array('Outreach campaign not found'),
				'batch' => $batch,
				'recipient' => $recipient,
				'token_id' => absint($token_row['id'] ?? 0),
			));
			return new WP_Error('campaign_missing', vms_pass_outreach_public_failure_message());
		}

		$preflight = vms_pass_outreach_recipient_preflight($recipient, $campaign, $batch, $token_row);
		if (!empty($preflight['ok'])) {
			$preflight = vms_pass_outreach_campaign_preflight($campaign);
		}
		if (!empty($preflight['ok'])) {
			return $error;
		}

		vms_pass_outreach_log_claim_denial(array(
			'reason_code' => (string) ($preflight['reason_code'] ?? 'campaign_not_active'),
			'admin_reasons' => (array) ($preflight['admin_reasons'] ?? array()),
			'batch' => $batch,
			'campaign' => $campaign,
			'recipient' => $recipient,
			'token_id' => absint($token_row['id'] ?? 0),
			'details' => (array) ($preflight['details'] ?? array()),
		));
		return new WP_Error((string) ($preflight['reason_code'] ?? 'campaign_not_active'), vms_pass_outreach_public_failure_message());
	}
}
add_filter('bvmgr_pass_claims_claim_preflight_error', 'backstage_outreach_claim_preflight_error', 10, 4);

if (!function_exists('backstage_outreach_filter_claim_events')) {
	function backstage_outreach_filter_claim_events(array $events, array $batch, array $token_row, array $context): array
	{
		$campaign = is_array($context['campaign'] ?? null) ? (array) $context['campaign'] : array();
		return empty($campaign) ? $events : vms_pass_outreach_filter_events_for_campaign($campaign, $events);
	}
}
add_filter('bvmgr_pass_claims_eligible_events', 'backstage_outreach_filter_claim_events', 10, 4);

if (!function_exists('backstage_outreach_prefill_claim')) {
	function backstage_outreach_prefill_claim(array $posted, array $context): array
	{
		$recipient = is_array($context['recipient'] ?? null) ? (array) $context['recipient'] : array();
		if (!empty($recipient)) {
			foreach (array('first_name', 'last_name', 'phone', 'email') as $key) {
				$posted[$key] = sanitize_text_field((string) ($recipient[$key] ?? ''));
			}
			$posted['email'] = sanitize_email((string) ($recipient['email'] ?? ''));
		}
		return $posted;
	}
}
add_filter('bvmgr_pass_claims_default_posted', 'backstage_outreach_prefill_claim', 10, 2);

if (!function_exists('backstage_outreach_max_party_size')) {
	function backstage_outreach_max_party_size(int $maximum, array $batch, array $context): int
	{
		$campaign = is_array($context['campaign'] ?? null) ? (array) $context['campaign'] : null;
		return min($maximum, vms_pass_outreach_effective_recipient_cap($batch, $campaign));
	}
}
add_filter('bvmgr_pass_claims_max_party_size', 'backstage_outreach_max_party_size', 10, 3);

if (!function_exists('backstage_outreach_validate_claim')) {
	function backstage_outreach_validate_claim($error, array $token_row, array $batch, array $event_plan, array $input, array $context)
	{
		$recipient = is_array($context['recipient'] ?? null) ? (array) $context['recipient'] : array();
		$campaign = is_array($context['campaign'] ?? null) ? (array) $context['campaign'] : array();
		if (empty($recipient) || empty($campaign)) {
			return $error;
		}

		$claimant = array(
			'guest_name' => trim((string) ($input['first_name'] ?? '') . ' ' . (string) ($input['last_name'] ?? '')),
			'email' => sanitize_email((string) ($input['email'] ?? '')),
			'phone' => sanitize_text_field((string) ($input['phone'] ?? '')),
			'party_size' => max(1, absint($input['party_size'] ?? 1)),
		);
		$evaluation = vms_pass_outreach_evaluate_recipient_claim($recipient, $campaign, $batch, $token_row, $event_plan, $claimant);
		if (!empty($evaluation['ok'])) {
			return $error;
		}

		vms_pass_outreach_log_claim_denial(array(
			'reason_code' => (string) ($evaluation['reason_code'] ?? 'campaign_eligibility_failed'),
			'admin_reasons' => (array) ($evaluation['admin_reasons'] ?? array()),
			'batch' => $batch,
			'campaign' => $campaign,
			'recipient' => $recipient,
			'token_id' => absint($token_row['id'] ?? 0),
			'event_plan_id' => absint($event_plan['id'] ?? 0),
			'details' => (array) ($evaluation['details'] ?? array()),
		));
		return new WP_Error((string) ($evaluation['reason_code'] ?? 'campaign_eligibility_failed'), vms_pass_outreach_public_failure_message());
	}
}
add_filter('bvmgr_pass_claims_claim_validation_error', 'backstage_outreach_validate_claim', 10, 6);

if (!function_exists('backstage_outreach_claim_insert_payload')) {
	function backstage_outreach_claim_insert_payload(array $payload, array $context): array
	{
		$campaign_id = absint($context['campaign']['id'] ?? 0);
		$recipient_id = absint($context['recipient']['id'] ?? 0);
		if ($campaign_id > 0) {
			$payload['data']['outreach_campaign_id'] = $campaign_id;
			$payload['formats'][] = '%d';
		}
		if ($recipient_id > 0) {
			$payload['data']['outreach_recipient_id'] = $recipient_id;
			$payload['formats'][] = '%d';
		}
		return $payload;
	}
}
add_filter('bvmgr_pass_claims_claim_insert_payload', 'backstage_outreach_claim_insert_payload', 10, 2);

if (!function_exists('backstage_outreach_claim_meta')) {
	function backstage_outreach_claim_meta(array $meta, array $context): array
	{
		$campaign_id = absint($context['campaign']['id'] ?? 0);
		$recipient_id = absint($context['recipient']['id'] ?? 0);
		if ($campaign_id > 0) {
			$meta['outreach_campaign_id'] = $campaign_id;
		}
		if ($recipient_id > 0) {
			$meta['outreach_recipient_id'] = $recipient_id;
		}
		return $meta;
	}
}
add_filter('bvmgr_pass_claims_claim_meta', 'backstage_outreach_claim_meta', 10, 2);

if (!function_exists('backstage_outreach_record_claim_success')) {
	function backstage_outreach_record_claim_success(array $result, array $token_row, array $context): void
	{
		$recipient = is_array($context['recipient'] ?? null) ? (array) $context['recipient'] : array();
		$campaign = is_array($context['campaign'] ?? null) ? (array) $context['campaign'] : array();
		if (empty($recipient) || empty($campaign)) {
			return;
		}
		vms_pass_outreach_record_recipient_claim_success(
			$recipient,
			$campaign,
			absint($result['claim_id'] ?? 0),
			absint($result['entry_id'] ?? 0),
			max(1, absint($result['party_size'] ?? 1)),
			absint($token_row['id'] ?? 0)
		);
	}
}
add_action('bvmgr_pass_claims_claim_created', 'backstage_outreach_record_claim_success', 10, 3);

if (!function_exists('backstage_outreach_public_claim_error')) {
	function backstage_outreach_public_claim_error(string $message, WP_Error $error, array $context): string
	{
		return empty($context['campaign']) ? $message : vms_pass_outreach_public_error_message_for_claim_error($error);
	}
}
add_filter('bvmgr_pass_claims_public_claim_error', 'backstage_outreach_public_claim_error', 10, 3);
