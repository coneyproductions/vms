<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_pass_invitation_default_signature')) {
	function bvmgr_pass_invitation_default_signature(): string
	{
		return 'Trey, Dalene & Henley Coney';
	}
}

if (!function_exists('bvmgr_pass_invitation_preview_ttl')) {
	function bvmgr_pass_invitation_preview_ttl(): int
	{
		return 10 * MINUTE_IN_SECONDS;
	}
}

if (!function_exists('bvmgr_pass_invitation_preview_key')) {
	function bvmgr_pass_invitation_preview_key(int $token_id, int $user_id): string
	{
		return 'bvmgr_pass_invite_' . $token_id . '_' . $user_id;
	}
}

if (!function_exists('bvmgr_pass_invitation_digest')) {
	function bvmgr_pass_invitation_digest(array $payload, int $user_id): string
	{
		$material = array(
			'token_id' => (int) ($payload['token_id'] ?? 0),
			'token_status' => sanitize_key((string) ($payload['token_status'] ?? '')),
			'batch_id' => (int) ($payload['batch_id'] ?? 0),
			'batch_status' => sanitize_key((string) ($payload['batch_status'] ?? '')),
			'batch_updated_at' => (string) ($payload['batch_updated_at'] ?? ''),
			'reviewed_previous_attempt_id' => (int) ($payload['reviewed_previous_attempt_id'] ?? 0),
			'recipient_name' => (string) ($payload['recipient_name'] ?? ''),
			'recipient_email' => strtolower((string) ($payload['recipient_email'] ?? '')),
			'recipient_party_id' => (int) ($payload['recipient_party_id'] ?? 0),
			'subject' => (string) ($payload['subject'] ?? ''),
			'message' => (string) ($payload['message'] ?? ''),
			'claim_url' => (string) ($payload['claim_url'] ?? ''),
			'from_name' => (string) ($payload['from_name'] ?? ''),
			'from_email' => strtolower((string) ($payload['from_email'] ?? '')),
			'reply_to' => strtolower((string) ($payload['reply_to'] ?? '')),
			'headers' => array_values(array_map('strval', (array) ($payload['headers'] ?? array()))),
			'user_id' => $user_id,
		);
		return hash_hmac('sha256', (string) wp_json_encode($material), wp_salt('auth'));
	}
}

if (!function_exists('bvmgr_pass_invitation_defaults')) {
	function bvmgr_pass_invitation_defaults(array $token, array $batch, string $recipient_name = ''): array
	{
		$claim_url = bvmgr_pass_claims_build_claim_url($token);
		$name = trim($recipient_name);
		$greeting = $name !== '' ? 'Hi ' . $name . ',' : 'Hello,';
		$scope = sanitize_key((string) ($batch['validity_type'] ?? ''));
		$scope_label = __('an eligible Serenade Range event', 'backstage-venue-manager');
		if ($scope === 'single_event') {
			$event_id = (int) ($batch['single_event_plan_id'] ?? 0);
			$event_title = $event_id > 0 ? trim((string) get_the_title($event_id)) : '';
			$scope_label = $event_title !== '' ? $event_title : __('the selected event', 'backstage-venue-manager');
		} elseif ($scope === 'date_range') {
			$scope_label = sprintf(__('an eligible event from %1$s through %2$s', 'backstage-venue-manager'), (string) ($batch['start_date'] ?? ''), (string) ($batch['end_date'] ?? ''));
		} elseif ($scope === 'season') {
			$scope_label = sprintf(__('an eligible event in %s', 'backstage-venue-manager'), (string) ($batch['season_label'] ?? __('the designated season', 'backstage-venue-manager')));
		}
		$admissions = max(1, (int) ($batch['admissions_per_link'] ?? 1));
		$message = $greeting . "\n\n";
		$message .= sprintf(__('You have been invited to claim a Guest Pass for %s.', 'backstage-venue-manager'), $scope_label) . "\n\n";
		$message .= sprintf(_n('This link can provide up to %d admission.', 'This link can provide up to %d admissions.', $admissions, 'backstage-venue-manager'), $admissions) . "\n";
		$expires_at = trim((string) ($batch['expires_at'] ?? ''));
		if ($expires_at !== '' && strpos($expires_at, '0000-00-00') !== 0) {
			try {
				$expiration = new DateTimeImmutable($expires_at, wp_timezone());
				$message .= sprintf(__('Claim before: %s', 'backstage-venue-manager'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expiration->getTimestamp(), wp_timezone())) . "\n";
			} catch (Exception $error) {
				// Eligibility rejects malformed expiration before preview.
			}
		}
		$message .= "\n";
		$message .= __('This is a bearer link. Anyone who has it can use it while the pass remains available, so please do not forward it.', 'backstage-venue-manager') . "\n\n";
		$message .= __('Claim your Guest Pass:', 'backstage-venue-manager') . "\n" . $claim_url . "\n\n";
		$message .= __('Availability and eligibility are checked again when the pass is claimed.', 'backstage-venue-manager') . "\n\n";
		$message .= bvmgr_pass_invitation_default_signature();
		return array(
			'subject' => __('Your Serenade Range Guest Pass invitation', 'backstage-venue-manager'),
			'message' => $message,
			'claim_url' => $claim_url,
		);
	}
}

if (!function_exists('bvmgr_pass_invitation_latest')) {
	function bvmgr_pass_invitation_latest(int $token_id): ?array
	{
		if ($token_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = bvmgr_admission_table_pass_invitations();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Invitation state is request-fresh operational data in a plugin-owned table.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE token_id = %d ORDER BY id DESC LIMIT 1', $table, $token_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('bvmgr_pass_invitation_history')) {
	function bvmgr_pass_invitation_history(int $token_id, int $limit = 20): array
	{
		global $wpdb;
		$limit = max(1, min(100, $limit));
		$table = bvmgr_admission_table_pass_invitations();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Invitation history is request-fresh operational data in a plugin-owned table.
		$rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE token_id = %d ORDER BY id DESC LIMIT %d', $table, $token_id, $limit), ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('bvmgr_pass_invitation_effective_status')) {
	function bvmgr_pass_invitation_effective_status(?array $attempt, array $token, array $batch): string
	{
		$token_status = sanitize_key((string) ($token['status'] ?? ''));
		if ($token_status === 'claimed') {
			return 'Claimed';
		}
		if ($token_status === 'void' || sanitize_key((string) ($batch['status'] ?? '')) !== 'active') {
			return 'Voided';
		}
		$expires_at = trim((string) ($batch['expires_at'] ?? ''));
		if ($expires_at !== '' && strpos($expires_at, '0000-00-00') !== 0) {
			try {
				if (time() >= (new DateTimeImmutable($expires_at, wp_timezone()))->getTimestamp()) {
					return 'Expired';
				}
			} catch (Exception $e) {
				return 'Expired';
			}
		}
		if (!is_array($attempt)) {
			return 'Not Invited';
		}
		$status = sanitize_key((string) ($attempt['status'] ?? ''));
		if ($status === 'handed_off') {
			return 'Handed Off';
		}
		if ($status === 'failed') {
			return 'Failed';
		}
		return 'In-Doubt';
	}
}

if (!function_exists('bvmgr_pass_invitation_eligibility')) {
	function bvmgr_pass_invitation_eligibility(array $token, array $batch)
	{
		if ((int) ($token['id'] ?? 0) <= 0 || (int) ($batch['id'] ?? 0) <= 0 || (int) ($token['batch_id'] ?? 0) !== (int) ($batch['id'] ?? 0)) {
			return new WP_Error('invalid_pass', __('The selected Guest Pass is invalid.', 'backstage-venue-manager'));
		}
		if (sanitize_key((string) ($token['status'] ?? '')) !== 'unclaimed') {
			return new WP_Error('pass_not_available', __('Only an available, unclaimed Guest Pass can be invited.', 'backstage-venue-manager'));
		}
		if (sanitize_key((string) ($batch['status'] ?? '')) !== 'active') {
			return new WP_Error('batch_not_active', __('This Guest Pass batch is not active.', 'backstage-venue-manager'));
		}
		$expires_at = trim((string) ($batch['expires_at'] ?? ''));
		if ($expires_at !== '' && strpos($expires_at, '0000-00-00') !== 0) {
			try {
				if (time() >= (new DateTimeImmutable($expires_at, wp_timezone()))->getTimestamp()) {
					return new WP_Error('pass_expired', __('This Guest Pass has expired.', 'backstage-venue-manager'));
				}
			} catch (Exception $e) {
				return new WP_Error('invalid_expiration', __('This Guest Pass has an invalid expiration value.', 'backstage-venue-manager'));
			}
		}
		$total_cap = max(0, (int) ($batch['total_admission_cap'] ?? 0));
		if ($total_cap > 0) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Capacity must be checked against current plugin-owned admission rows immediately before handoff.
			$used = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(party_size), 0) FROM %i WHERE pass_batch_id = %d AND status <> 'canceled'", bvmgr_admission_table_entries(), (int) $batch['id']));
			if ($used >= $total_cap) {
				return new WP_Error('batch_capacity_exhausted', __('This Guest Pass batch has no admission capacity remaining.', 'backstage-venue-manager'));
			}
		}
		if (bvmgr_pass_claims_build_claim_url($token) === '') {
			return new WP_Error('claim_link_unavailable', __('The signed claim link could not be generated.', 'backstage-venue-manager'));
		}
		return true;
	}
}

if (!function_exists('bvmgr_pass_invitation_validate_recipient')) {
	function bvmgr_pass_invitation_validate_recipient(string $name, string $email)
	{
		$name = trim(sanitize_text_field($name));
		$email = sanitize_email($email);
		if ($name === '' || strlen($name) > 190) {
			return new WP_Error('invalid_recipient_name', __('Enter the recipient name.', 'backstage-venue-manager'));
		}
		if ($email === '' || !is_email($email)) {
			return new WP_Error('invalid_recipient_email', __('Enter a valid recipient email address.', 'backstage-venue-manager'));
		}
		if (!function_exists('vms_outreach_email_is_suppressed')) {
			return new WP_Error('suppression_check_unavailable', __('The Outreach suppression check is unavailable; invitation sending is disabled.', 'backstage-venue-manager'));
		}
		if (vms_outreach_email_is_suppressed($email)) {
			return new WP_Error('recipient_suppressed', __('This address is suppressed from Outreach and cannot receive a Guest Pass invitation.', 'backstage-venue-manager'));
		}
		return array('name' => $name, 'email' => $email);
	}
}

if (!function_exists('bvmgr_pass_invitation_sanitize_content')) {
	function bvmgr_pass_invitation_sanitize_content(string $subject, string $message)
	{
		$subject = trim(sanitize_text_field($subject));
		$message = trim(sanitize_textarea_field($message));
		if ($subject === '' || strlen($subject) > 255) {
			return new WP_Error('invalid_invitation_subject', __('Enter an invitation subject no longer than 255 characters.', 'backstage-venue-manager'));
		}
		if ($message === '' || strlen($message) > 10000) {
			return new WP_Error('invalid_invitation_message', __('Enter an invitation message no longer than 10,000 characters.', 'backstage-venue-manager'));
		}
		return array('subject' => $subject, 'message' => $message);
	}
}

if (!function_exists('bvmgr_pass_invitation_recipient_party_id')) {
	function bvmgr_pass_invitation_recipient_party_id(string $name, string $email): int
	{
		if (!function_exists('backstage_outreach_party_suggestions') || !function_exists('backstage_outreach_party_get_contact_methods') || !function_exists('backstage_outreach_party_normalize_contact_value')) {
			return 0;
		}
		$normalized = backstage_outreach_party_normalize_contact_value('email', $email);
		if ($normalized === '') {
			return 0;
		}
		$matches = array();
		foreach (backstage_outreach_party_suggestions(array('display_name' => $name, 'email' => $email), 20) as $suggestion) {
			$party_id = (int) ($suggestion['party']['id'] ?? 0);
			if ($party_id <= 0) {
				continue;
			}
			foreach (backstage_outreach_party_get_contact_methods($party_id, false) as $method) {
				if ((string) ($method['method_type'] ?? '') === 'email' && (string) ($method['value_norm'] ?? '') === $normalized) {
					$matches[$party_id] = true;
				}
			}
		}
		return count($matches) === 1 ? (int) array_key_first($matches) : 0;
	}
}

if (!function_exists('bvmgr_pass_invitation_compliance')) {
	function bvmgr_pass_invitation_compliance(array $payload, array $raw)
	{
		$required = array(
			'backstage_outreach_issue_unsubscribe_token',
			'backstage_outreach_validate_unsubscribe_token',
			'backstage_outreach_unsubscribe_endpoint_readiness',
			'backstage_outreach_promotional_footer',
			'backstage_outreach_postal_address',
			'backstage_outreach_mail_transport_readiness',
		);
		foreach ($required as $function) {
			if (!function_exists($function)) {
				return new WP_Error('invitation_compliance_unavailable', __('Required Outreach email compliance services are unavailable; no invitation can be reviewed or sent.', 'backstage-venue-manager'));
			}
		}
		$endpoint = backstage_outreach_unsubscribe_endpoint_readiness();
		if (empty($endpoint['ready'])) {
			return new WP_Error('invitation_unsubscribe_unavailable', (string) ($endpoint['message'] ?? __('The required unsubscribe service is unavailable.', 'backstage-venue-manager')));
		}
		$postal_address = backstage_outreach_postal_address();
		if ($postal_address === '') {
			return new WP_Error('invitation_postal_address_unavailable', __('The required sender postal address is unavailable.', 'backstage-venue-manager'));
		}
		$from_email = sanitize_email((string) get_option('admin_email'));
		if ($from_email === '' || !is_email($from_email)) {
			return new WP_Error('invitation_sender_unavailable', __('The required sender email address is unavailable.', 'backstage-venue-manager'));
		}
		$from_name = trim(wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES));
		if ($from_name === '') {
			return new WP_Error('invitation_sender_unavailable', __('The required sender name is unavailable.', 'backstage-venue-manager'));
		}

		$unsubscribe_url = esc_url_raw((string) ($raw['unsubscribe_url'] ?? ''));
		if ($unsubscribe_url === '') {
			$issued = backstage_outreach_issue_unsubscribe_token((string) $payload['recipient_email'], array(
				'source_type' => 'guest_pass_invitation',
				'source_id' => (int) $payload['token_id'],
				'campaign_id' => 0,
			));
			if (is_wp_error($issued)) {
				return $issued;
			}
			$unsubscribe_url = esc_url_raw((string) ($issued['url'] ?? ''));
		}
		$query = (string) wp_parse_url($unsubscribe_url, PHP_URL_QUERY);
		$params = array();
		wp_parse_str($query, $params);
		$token = sanitize_text_field((string) ($params['backstage-outreach-unsubscribe'] ?? ''));
		$signature = sanitize_text_field((string) ($params['signature'] ?? ''));
		$row = backstage_outreach_validate_unsubscribe_token($token, $signature);
		$canonical_url = add_query_arg(array(
			'backstage-outreach-unsubscribe' => $token,
			'signature' => $signature,
		), home_url('/'));
		$email_norm = function_exists('vms_outreach_normalize_email') ? vms_outreach_normalize_email((string) $payload['recipient_email']) : strtolower((string) $payload['recipient_email']);
		if (is_wp_error($row)
			|| !is_string($canonical_url)
			|| !hash_equals($canonical_url, $unsubscribe_url)
			|| (string) ($row['email_norm'] ?? '') !== $email_norm
			|| sanitize_key((string) ($row['source_type'] ?? '')) !== 'guest_pass_invitation'
			|| (int) ($row['source_id'] ?? 0) !== (int) $payload['token_id']) {
			return new WP_Error('invitation_unsubscribe_invalid', __('The reviewed unsubscribe link is invalid or no longer belongs to this invitation.', 'backstage-venue-manager'));
		}

		$operator_message = (string) ($payload['operator_message'] ?? $payload['message'] ?? '');
		$message = rtrim($operator_message) . "\n\n" . backstage_outreach_promotional_footer($unsubscribe_url, $postal_address);
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
			'Reply-To: ' . $from_email,
		);
		$transport = backstage_outreach_mail_transport_readiness();
		$advertise_rfc8058 = !empty($transport['ready']);
		if ($advertise_rfc8058) {
			$headers[] = 'List-Unsubscribe: <' . $unsubscribe_url . '>';
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}
		$payload['operator_message'] = $operator_message;
		$payload['message'] = $message;
		$payload['unsubscribe_url'] = $unsubscribe_url;
		$payload['from_name'] = $from_name;
		$payload['from_email'] = $from_email;
		$payload['reply_to'] = $from_email;
		$payload['headers'] = $headers;
		$payload['advertise_rfc8058'] = $advertise_rfc8058;
		return $payload;
	}
}

if (!function_exists('bvmgr_pass_invitation_build_payload')) {
	function bvmgr_pass_invitation_build_payload(int $token_id, array $raw)
	{
		$token = bvmgr_pass_claims_get_token_by_id($token_id);
		$batch = is_array($token) ? bvmgr_pass_claims_get_batch_by_id((int) ($token['batch_id'] ?? 0)) : null;
		if (!is_array($token) || !is_array($batch)) {
			return new WP_Error('pass_not_found', __('The selected Guest Pass could not be found.', 'backstage-venue-manager'));
		}
		$eligible = bvmgr_pass_invitation_eligibility($token, $batch);
		if (is_wp_error($eligible)) {
			return $eligible;
		}
		$recipient = bvmgr_pass_invitation_validate_recipient((string) ($raw['recipient_name'] ?? ''), (string) ($raw['recipient_email'] ?? ''));
		if (is_wp_error($recipient)) {
			return $recipient;
		}
		$operator_message = (string) ($raw['operator_message'] ?? $raw['message'] ?? '');
		$content = bvmgr_pass_invitation_sanitize_content((string) ($raw['subject'] ?? ''), $operator_message);
		if (is_wp_error($content)) {
			return $content;
		}
		$claim_url = bvmgr_pass_claims_build_claim_url($token);
		if (strpos((string) $content['message'], $claim_url) === false) {
			return new WP_Error('claim_link_missing', __('The invitation message must contain this Guest Pass claim link.', 'backstage-venue-manager'));
		}
		$payload = array(
			'token_id' => $token_id,
			'token_status' => (string) $token['status'],
			'batch_id' => (int) $batch['id'],
			'source_id' => (int) $token['source_id'],
			'batch_status' => (string) $batch['status'],
			'batch_updated_at' => (string) ($batch['updated_at'] ?? ''),
			'recipient_name' => (string) $recipient['name'],
			'recipient_email' => (string) $recipient['email'],
			'recipient_party_id' => bvmgr_pass_invitation_recipient_party_id((string) $recipient['name'], (string) $recipient['email']),
			'subject' => (string) $content['subject'],
			'operator_message' => (string) $content['message'],
			'message' => (string) $content['message'],
			'claim_url' => $claim_url,
		);
		return bvmgr_pass_invitation_compliance($payload, $raw);
	}
}

if (!function_exists('bvmgr_pass_invitation_return_url')) {
	function bvmgr_pass_invitation_return_url(int $token_id): string
	{
		return bvmgr_pass_claims_admin_page_url(array('tab' => 'passes', 'invite_token_id' => $token_id));
	}
}

if (!function_exists('bvmgr_pass_invitation_require_operator')) {
	function bvmgr_pass_invitation_require_operator(): void
	{
		if (!current_user_can(bvmgr_pass_claims_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
	}
}

if (!function_exists('bvmgr_pass_invitation_handle_preview')) {
	function bvmgr_pass_invitation_handle_preview(): void
	{
		bvmgr_pass_invitation_require_operator();
		$token_id = absint((string) ($_POST['token_id'] ?? '0'));
		check_admin_referer('bvmgr_pass_invitation_preview_' . $token_id);
		$payload = bvmgr_pass_invitation_build_payload($token_id, (array) wp_unslash($_POST));
		if (is_wp_error($payload)) {
			bvmgr_pass_claims_set_user_message('error', $payload->get_error_message());
			wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
			exit;
		}
		$user_id = get_current_user_id();
		$previous = bvmgr_pass_invitation_latest($token_id);
		$payload['reviewed_previous_attempt_id'] = is_array($previous) ? (int) $previous['id'] : 0;
		$payload['review_digest'] = bvmgr_pass_invitation_digest($payload, $user_id);
		$payload['reviewed_at'] = time();
		set_transient(bvmgr_pass_invitation_preview_key($token_id, $user_id), $payload, bvmgr_pass_invitation_preview_ttl());
		wp_safe_redirect(add_query_arg('invitation_review', '1', bvmgr_pass_invitation_return_url($token_id)));
		exit;
	}
}
add_action('admin_post_vms_pass_invitation_preview', 'bvmgr_pass_invitation_handle_preview');

if (!function_exists('bvmgr_pass_invitation_acquire_lock')) {
	function bvmgr_pass_invitation_acquire_lock(int $token_id): bool
	{
		global $wpdb;
		$name = 'bvmgr-pass-invite-' . $token_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A short MySQL advisory lock serializes send-intent creation for one existing token.
		return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $name)) === 1;
	}
}

if (!function_exists('bvmgr_pass_invitation_release_lock')) {
	function bvmgr_pass_invitation_release_lock(int $token_id): void
	{
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Releases the matching short-lived invitation advisory lock.
		$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'bvmgr-pass-invite-' . $token_id));
	}
}

if (!function_exists('bvmgr_pass_invitation_transport')) {
	function bvmgr_pass_invitation_transport(array $payload): array
	{
		if (!function_exists('backstage_outreach_email_lock_name') || !function_exists('vms_outreach_email_is_suppressed')) {
			return array('status' => 'failed', 'failure_code' => 'outreach_compliance_unavailable');
		}
		global $wpdb;
		$lock = backstage_outreach_email_lock_name((string) ($payload['recipient_email'] ?? ''));
		if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
			return array('status' => 'failed', 'failure_code' => 'outreach_send_lock_unavailable');
		}
		$dkim_hook = !empty($payload['advertise_rfc8058']) && function_exists('backstage_outreach_require_dkim_unsubscribe_headers');
		try {
			if (vms_outreach_email_is_suppressed((string) ($payload['recipient_email'] ?? ''))) {
				return array('status' => 'failed', 'failure_code' => 'recipient_suppressed');
			}
			if ($dkim_hook) {
				add_action('phpmailer_init', 'backstage_outreach_require_dkim_unsubscribe_headers', PHP_INT_MAX, 1);
			}
			$accepted = wp_mail(
				(string) ($payload['recipient_email'] ?? ''),
				(string) ($payload['subject'] ?? ''),
				(string) ($payload['message'] ?? ''),
				array_values(array_map('strval', (array) ($payload['headers'] ?? array())))
			);
			return array(
				'status' => $accepted ? 'handed_off' : 'failed',
				'failure_code' => $accepted ? '' : 'wp_mail_rejected',
			);
		} catch (Throwable $error) {
			return array('status' => 'in_doubt', 'failure_code' => 'transport_exception');
		} finally {
			if ($dkim_hook) {
				remove_action('phpmailer_init', 'backstage_outreach_require_dkim_unsubscribe_headers', PHP_INT_MAX);
			}
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
		}
	}
}

if (!function_exists('bvmgr_pass_invitation_attempt_is_active')) {
	function bvmgr_pass_invitation_attempt_is_active(?array $attempt): bool
	{
		if (!is_array($attempt) || sanitize_key((string) ($attempt['status'] ?? '')) !== 'sending') {
			return false;
		}
		try {
			$created = (new DateTimeImmutable((string) ($attempt['created_at'] ?? ''), wp_timezone()))->getTimestamp();
		} catch (Exception $error) {
			return true;
		}
		return $created > (time() - (5 * MINUTE_IN_SECONDS));
	}
}

if (!function_exists('bvmgr_pass_invitation_handle_send')) {
	function bvmgr_pass_invitation_handle_send(): void
	{
		bvmgr_pass_invitation_require_operator();
		$token_id = absint((string) ($_POST['token_id'] ?? '0'));
		check_admin_referer('bvmgr_pass_invitation_send_' . $token_id);
		$user_id = get_current_user_id();
		$stored = get_transient(bvmgr_pass_invitation_preview_key($token_id, $user_id));
		$posted_digest = isset($_POST['review_digest']) && is_scalar($_POST['review_digest']) ? sanitize_text_field(wp_unslash((string) $_POST['review_digest'])) : '';
		if (!is_array($stored) || $posted_digest === '' || !hash_equals((string) ($stored['review_digest'] ?? ''), $posted_digest)) {
			bvmgr_pass_claims_set_user_message('error', __('The invitation review is stale or expired. Preview it again before sending.', 'backstage-venue-manager'));
			wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
			exit;
		}
		$fresh = bvmgr_pass_invitation_build_payload($token_id, $stored);
		if (is_array($fresh)) {
			$fresh['reviewed_previous_attempt_id'] = (int) ($stored['reviewed_previous_attempt_id'] ?? 0);
		}
		if (is_wp_error($fresh) || !hash_equals($posted_digest, is_array($fresh) ? bvmgr_pass_invitation_digest($fresh, $user_id) : '')) {
			bvmgr_pass_claims_set_user_message('error', is_wp_error($fresh) ? $fresh->get_error_message() : __('The invitation changed after review. Preview it again.', 'backstage-venue-manager'));
			delete_transient(bvmgr_pass_invitation_preview_key($token_id, $user_id));
			wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
			exit;
		}
		if (!bvmgr_pass_invitation_acquire_lock($token_id)) {
			bvmgr_pass_claims_set_user_message('error', __('Another operator is working with this invitation. No email was sent.', 'backstage-venue-manager'));
			wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
			exit;
		}
		global $wpdb;
		$table = bvmgr_admission_table_pass_invitations();
		$latest = bvmgr_pass_invitation_latest($token_id);
		$reviewed_previous_attempt_id = (int) ($stored['reviewed_previous_attempt_id'] ?? 0);
		$latest_attempt_id = is_array($latest) ? (int) ($latest['id'] ?? 0) : 0;
		if ($latest_attempt_id !== $reviewed_previous_attempt_id) {
			bvmgr_pass_invitation_release_lock($token_id);
			bvmgr_pass_claims_set_user_message('error', __('Invitation history changed after preview. No email was sent; preview the current state again.', 'backstage-venue-manager'));
			wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
			exit;
		}
		$resend_confirmed = !empty($_POST['resend_confirm']);
		$reassign_confirmed = !empty($_POST['reassign_confirm']);
		$latest_status = sanitize_key((string) ($latest['status'] ?? ''));
		if ($latest_status === 'sending' && bvmgr_pass_invitation_attempt_is_active($latest)) {
			bvmgr_pass_invitation_release_lock($token_id);
			bvmgr_pass_claims_set_user_message('error', __('A prior invitation attempt has an uncertain outcome. Review its history before retrying.', 'backstage-venue-manager'));
			wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
			exit;
		}
		if (is_array($latest) && !$resend_confirmed) {
			bvmgr_pass_invitation_release_lock($token_id);
			bvmgr_pass_claims_set_user_message('error', __('This link already has an invitation attempt. Explicitly confirm the reviewed resend.', 'backstage-venue-manager'));
			wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
			exit;
		}
		$recipient_changed = is_array($latest) && strtolower((string) $latest['recipient_email']) !== strtolower((string) $fresh['recipient_email']);
		if ($recipient_changed && !$reassign_confirmed) {
			bvmgr_pass_invitation_release_lock($token_id);
			bvmgr_pass_claims_set_user_message('error', __('The recipient differs from the prior attempt. Explicitly confirm reassignment before sending.', 'backstage-venue-manager'));
			wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
			exit;
		}
		$now = bvmgr_admission_now_mysql();
		$attempt_key = hash('sha256', $token_id . '|' . $posted_digest . '|' . $user_id . '|' . wp_generate_uuid4());
		$inserted = $wpdb->insert($table, array(
			'token_id' => $token_id,
			'batch_id' => (int) $fresh['batch_id'],
			'source_id' => (int) $fresh['source_id'],
			'attempt_key' => $attempt_key,
			'review_digest' => $posted_digest,
			'recipient_name' => (string) $fresh['recipient_name'],
			'recipient_email' => (string) $fresh['recipient_email'],
			'recipient_email_hash' => hash('sha256', strtolower((string) $fresh['recipient_email'])),
			'recipient_party_id' => (int) ($fresh['recipient_party_id'] ?? 0) ?: null,
			'subject' => (string) $fresh['subject'],
			'message_hash' => hash('sha256', (string) $fresh['message']),
			'claim_link_hash' => hash('sha256', (string) $fresh['claim_url']),
			'status' => 'sending',
			'attempt_type' => is_array($latest) ? ($recipient_changed ? 'reassignment' : 'resend') : 'initial',
			'previous_attempt_id' => is_array($latest) ? (int) $latest['id'] : null,
			'operator_user_id' => $user_id,
			'created_at' => $now,
		), array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'));
		$attempt_id = (int) $wpdb->insert_id;
		delete_transient(bvmgr_pass_invitation_preview_key($token_id, $user_id));
		bvmgr_pass_invitation_release_lock($token_id);
		if ($inserted === false || $attempt_id <= 0) {
			bvmgr_pass_claims_set_user_message('error', __('The invitation send intent could not be recorded. No email was sent.', 'backstage-venue-manager'));
			wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
			exit;
		}

		$transport = bvmgr_pass_invitation_transport($fresh);
		$status = (string) $transport['status'];
		$failure_code = (string) $transport['failure_code'];
		$updated = $wpdb->update($table, array('status' => $status, 'failure_code' => $failure_code, 'completed_at' => bvmgr_admission_now_mysql()), array('id' => $attempt_id, 'status' => 'sending'), array('%s', '%s', '%s'), array('%d', '%s'));
		if ($updated !== 1) {
			$status = 'in_doubt';
		}
		if (function_exists('bvmgr_admission_audit_log')) {
			bvmgr_admission_audit_log(0, null, 'pass_invitation_attempt', $user_id, 'admin', array(
				'attempt_id' => $attempt_id,
				'token_id' => $token_id,
				'batch_id' => (int) $fresh['batch_id'],
				'source_id' => (int) $fresh['source_id'],
				'status' => $status,
				'attempt_type' => is_array($latest) ? ($recipient_changed ? 'reassignment' : 'resend') : 'initial',
				'recipient_email_hash' => hash('sha256', strtolower((string) $fresh['recipient_email'])),
			));
		}
		if ($status === 'handed_off') {
			bvmgr_pass_claims_set_user_message('success', __('WordPress accepted the invitation for mail handoff. This does not confirm inbox delivery.', 'backstage-venue-manager'));
		} elseif ($status === 'failed') {
			bvmgr_pass_claims_set_user_message('error', __('WordPress rejected the invitation handoff. No successful handoff was recorded.', 'backstage-venue-manager'));
		} else {
			bvmgr_pass_claims_set_user_message('error', __('The invitation transport outcome is uncertain. Do not retry until the attempt is reviewed.', 'backstage-venue-manager'));
		}
		wp_safe_redirect(bvmgr_pass_invitation_return_url($token_id));
		exit;
	}
}
add_action('admin_post_vms_pass_invitation_send', 'bvmgr_pass_invitation_handle_send');

if (!function_exists('bvmgr_pass_invitation_render_panel')) {
	function bvmgr_pass_invitation_render_panel(int $token_id): void
	{
		if ($token_id <= 0) {
			return;
		}
		$token = bvmgr_pass_claims_get_token_by_id($token_id);
		$batch = is_array($token) ? bvmgr_pass_claims_get_batch_by_id((int) ($token['batch_id'] ?? 0)) : null;
		if (!is_array($token) || !is_array($batch)) {
			echo '<section class="vms-pass-card"><p>' . esc_html__('The selected Guest Pass could not be found.', 'backstage-venue-manager') . '</p></section>';
			return;
		}
		$latest = bvmgr_pass_invitation_latest($token_id);
		$history = bvmgr_pass_invitation_history($token_id);
		$status = bvmgr_pass_invitation_effective_status($latest, $token, $batch);
		$review = get_transient(bvmgr_pass_invitation_preview_key($token_id, get_current_user_id()));
		$defaults = bvmgr_pass_invitation_defaults($token, $batch, is_array($latest) ? (string) $latest['recipient_name'] : '');
		$recipient_name = is_array($review) ? (string) $review['recipient_name'] : (is_array($latest) ? (string) $latest['recipient_name'] : '');
		$recipient_email = is_array($review) ? (string) $review['recipient_email'] : (is_array($latest) ? (string) $latest['recipient_email'] : '');
		$subject = is_array($review) ? (string) $review['subject'] : (string) $defaults['subject'];
		$message = is_array($review) ? (string) ($review['operator_message'] ?? $review['message']) : (string) $defaults['message'];
		echo '<section class="vms-pass-card vms-pass-invitation"><h2>' . esc_html__('Send Guest Pass Invitation', 'backstage-venue-manager') . '</h2>';
		echo '<p><strong>' . esc_html__('Invitation status:', 'backstage-venue-manager') . '</strong> ' . esc_html($status) . ' &middot; <strong>' . esc_html__('Batch:', 'backstage-venue-manager') . '</strong> #' . esc_html((string) $batch['id']) . ' ' . esc_html((string) $batch['batch_name']) . '</p>';
		echo '<p class="description">' . esc_html__('This sends the existing bearer claim link. It does not create a pass, reserve admission capacity, subscribe the recipient, or prove inbox delivery.', 'backstage-venue-manager') . '</p>';
		$eligible = bvmgr_pass_invitation_eligibility($token, $batch);
		if (is_wp_error($eligible)) {
			echo '<div class="notice notice-error inline"><p>' . esc_html($eligible->get_error_message()) . '</p></div>';
		} else {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form" data-vms-pass-invitation-form>';
			echo '<input type="hidden" name="action" value="vms_pass_invitation_preview"><input type="hidden" name="token_id" value="' . esc_attr((string) $token_id) . '">';
			wp_nonce_field('bvmgr_pass_invitation_preview_' . $token_id);
			echo '<div class="vms-pass-grid"><label>' . esc_html__('Recipient name', 'backstage-venue-manager') . '<input required maxlength="190" name="recipient_name" value="' . esc_attr($recipient_name) . '"></label>';
			echo '<label>' . esc_html__('Recipient email', 'backstage-venue-manager') . '<input required type="email" maxlength="190" name="recipient_email" value="' . esc_attr($recipient_email) . '"></label>';
			echo '<label class="vms-pass-span-2">' . esc_html__('Subject', 'backstage-venue-manager') . '<input required maxlength="255" name="subject" value="' . esc_attr($subject) . '"></label>';
			echo '<label class="vms-pass-span-2">' . esc_html__('Message', 'backstage-venue-manager') . '<textarea required rows="13" maxlength="10000" name="message">' . esc_textarea($message) . '</textarea><span class="description">' . esc_html__('The exact review adds the required promotional identity, postal address, and personalized unsubscribe link.', 'backstage-venue-manager') . '</span></label></div>';
			echo '<p class="vms-pass-actions"><button class="button button-primary">' . esc_html__('Preview Exact Invitation', 'backstage-venue-manager') . '</button> <span class="description">' . esc_html__('Preview never sends email.', 'backstage-venue-manager') . '</span></p></form>';
		}
		if (is_array($review) && isset($_GET['invitation_review'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only rendering of a server-side, user-bound preview transient.
			echo '<div class="vms-pass-invitation-review" data-vms-pass-invitation-review><h3>' . esc_html__('Exact outgoing message', 'backstage-venue-manager') . '</h3>';
			echo '<p><strong>' . esc_html__('From:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $review['from_name']) . ' &lt;' . esc_html((string) $review['from_email']) . '&gt;<br><strong>' . esc_html__('Reply-To:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $review['reply_to']) . '<br><strong>' . esc_html__('To:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $review['recipient_name']) . ' &lt;' . esc_html((string) $review['recipient_email']) . '&gt;<br><strong>' . esc_html__('Subject:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $review['subject']) . '</p>';
			echo '<pre>' . esc_html((string) $review['message']) . '</pre>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-vms-pass-invitation-send><input type="hidden" name="action" value="vms_pass_invitation_send"><input type="hidden" name="token_id" value="' . esc_attr((string) $token_id) . '"><input type="hidden" name="review_digest" value="' . esc_attr((string) $review['review_digest']) . '">';
			wp_nonce_field('bvmgr_pass_invitation_send_' . $token_id);
			if (is_array($latest)) {
				echo '<label class="vms-pass-confirm"><input type="checkbox" required name="resend_confirm" value="1"> ' . esc_html__('I reviewed the prior attempt and explicitly authorize this resend.', 'backstage-venue-manager') . '</label>';
				if (strtolower((string) $latest['recipient_email']) !== strtolower((string) $review['recipient_email'])) {
					echo '<label class="vms-pass-confirm"><input type="checkbox" required name="reassign_confirm" value="1"> ' . esc_html__('I confirm reassignment of this bearer link to the new recipient.', 'backstage-venue-manager') . '</label>';
				}
			}
			echo '<p><button class="button button-primary" data-vms-pass-send-confirm>' . esc_html__('Confirm Send', 'backstage-venue-manager') . '</button> <span class="vms-pass-stale-warning" data-vms-pass-invitation-stale hidden>' . esc_html__('Fields changed after preview. Preview again before sending.', 'backstage-venue-manager') . '</span></p></form></div>';
		}
		if (!empty($history)) {
			echo '<h3>' . esc_html__('Invitation history', 'backstage-venue-manager') . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__('Time', 'backstage-venue-manager') . '</th><th>' . esc_html__('Recipient', 'backstage-venue-manager') . '</th><th>' . esc_html__('Outcome', 'backstage-venue-manager') . '</th><th>' . esc_html__('Attempt', 'backstage-venue-manager') . '</th><th>' . esc_html__('Operator', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
			foreach ($history as $attempt) {
				$attempt_status = sanitize_key((string) $attempt['status']) === 'sending' ? 'In-Doubt' : ucwords(str_replace('_', ' ', (string) $attempt['status']));
				$user = get_userdata((int) $attempt['operator_user_id']);
				echo '<tr><td>' . esc_html((string) $attempt['created_at']) . '</td><td>' . esc_html((string) $attempt['recipient_name']) . '<div class="description">' . esc_html((string) $attempt['recipient_email']) . '</div></td><td>' . esc_html($attempt_status) . '</td><td>' . esc_html(ucfirst((string) $attempt['attempt_type'])) . '</td><td>' . esc_html($user instanceof WP_User ? $user->display_name : ('#' . (int) $attempt['operator_user_id'])) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p><a href="' . esc_url(bvmgr_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => (int) $batch['id']))) . '">' . esc_html__('Back to batch passes', 'backstage-venue-manager') . '</a></p></section>';
	}
}
