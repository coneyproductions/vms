<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_ticketing_claims_account_dashboard_url')) {
	function bvmgr_ticketing_claims_account_dashboard_url(): string
	{
		if (function_exists('bvmgr_ticketing_verification_account_dashboard_url')) {
			return (string) bvmgr_ticketing_verification_account_dashboard_url();
		}
		if (function_exists('wc_get_account_endpoint_url')) {
			return (string) wc_get_account_endpoint_url('dashboard');
		}
		if (function_exists('wc_get_page_permalink')) {
			$url = wc_get_page_permalink('myaccount');
			if (is_string($url) && $url !== '') {
				return $url;
			}
		}
		return home_url('/my-account/');
	}
}

if (!function_exists('bvmgr_ticketing_claims_account_benefits_url')) {
	function bvmgr_ticketing_claims_account_benefits_url(): string
	{
		if (!is_user_logged_in()) {
			return '';
		}

		$dashboard_url = trim(bvmgr_ticketing_claims_account_dashboard_url());
		if ($dashboard_url === '') {
			return '';
		}

		if (function_exists('wc_get_page_id')) {
			$account_page_id = absint(wc_get_page_id('myaccount'));
			if ($account_page_id <= 0 || get_post_status($account_page_id) !== 'publish') {
				return '';
			}
		}

		return (string) add_query_arg('vms_benefits', '1', $dashboard_url) . '#vms-benefits-panel';
	}
}

if (!function_exists('bvmgr_ticketing_claims_account_query_absint')) {
	function bvmgr_ticketing_claims_account_query_absint(string $key): int
	{
		return bvmgr_request_read_absint($_GET, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Customer benefits query state only controls read-only account panel expansion.
	}
}

if (!function_exists('bvmgr_ticketing_claims_account_should_expand')) {
	function bvmgr_ticketing_claims_account_should_expand(): bool
	{
		return bvmgr_ticketing_claims_account_query_absint('vms_benefits') === 1;
	}
}

if (!function_exists('bvmgr_ticketing_claims_parse_existing_counts_payload')) {
	function bvmgr_ticketing_claims_parse_existing_counts_payload($raw): array
	{
		if (is_array($raw)) {
			$decoded = $raw;
		} elseif (is_string($raw)) {
			$raw = trim($raw);
			if ($raw === '' || strlen($raw) > 16384) {
				return array();
			}

			$json = bvmgr_json_decode_associative($raw, 8);
			if (
				empty($json['ok'])
				|| !is_array($json['value'])
				|| !bvmgr_json_decoded_is_object($json['value'], (string) ($json['top_level_token'] ?? ''))
			) {
				return array();
			}

			$decoded = $json['value'];
		} else {
			return array();
		}

		$existing_counts = array();
		foreach ($decoded as $email_key => $count) {
			$email_key = strtolower(trim(sanitize_email((string) $email_key)));
			if ($email_key === '') {
				continue;
			}
			if (is_array($count) || is_object($count)) {
				continue;
			}

			$existing_counts[$email_key] = max(0, min(1000, absint($count)));
		}

		return $existing_counts;
	}
}

if (!function_exists('bvmgr_ticketing_claims_post_existing_counts')) {
	function bvmgr_ticketing_claims_post_existing_counts(): array
	{
		if (!isset($_POST['existing_counts']) || !is_array($_POST['existing_counts'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- existing_counts is consumed only after the assignee-validation AJAX nonce and logged-in gates pass.
			return array();
		}

		$raw_existing_counts = wp_unslash($_POST['existing_counts']); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- existing_counts accepts only a nonce-gated assignee=>count map and is normalized by the existing helper below.
		if (!is_array($raw_existing_counts)) {
			return array();
		}

		return bvmgr_ticketing_claims_parse_existing_counts_payload($raw_existing_counts);
	}
}

if (!function_exists('bvmgr_ticketing_claims_account_event_date_text')) {
	function bvmgr_ticketing_claims_account_event_date_text(int $event_id): string
	{
		$event_id = absint($event_id);
		if ($event_id <= 0) {
			return '';
		}

		$raw = '';
		if (function_exists('tribe_get_start_date')) {
			$raw = (string) tribe_get_start_date($event_id, false, 'Y-m-d H:i:s');
		}
		if ($raw === '') {
			$raw = (string) get_post_meta($event_id, '_EventStartDate', true);
		}
		if ($raw === '') {
			$raw = (string) get_post_meta($event_id, '_EventStartDateUTC', true);
		}
		if ($raw === '') {
			return '';
		}

		$timestamp = strtotime($raw);
		if (!$timestamp) {
			return '';
		}

		return (string) wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
	}
}

if (!function_exists('bvmgr_ticketing_claims_account_user_grants')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_ticketing_claims_account_user_grants(int $user_id, int $limit = 40): array
	{
		$user_id = absint($user_id);
		if ($user_id <= 0 || !function_exists('bvmgr_ticketing_claims_get_direct_grants')) {
			return array();
		}

		$rows = bvmgr_ticketing_claims_get_direct_grants(array(
			'user_id' => $user_id,
			'status' => 'any',
			'limit' => max(1, min(120, absint($limit))),
		));

		$out = array();
		foreach ((array) $rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$event_id = absint($row['event_id'] ?? 0);
			$type_key = sanitize_key((string) ($row['grant_type'] ?? ''));
			$type_label = function_exists('bvmgr_ticketing_claims_grant_type_label')
				? bvmgr_ticketing_claims_grant_type_label($type_key)
				: ucwords(str_replace('_', ' ', $type_key));

			$res_counts = function_exists('bvmgr_ticketing_claims_grant_reservation_counts')
				? bvmgr_ticketing_claims_grant_reservation_counts(absint($row['id'] ?? 0))
				: null;
			$status_key = function_exists('bvmgr_ticketing_claims_resolve_grant_status')
				? bvmgr_ticketing_claims_resolve_grant_status($row, is_array($res_counts) ? $res_counts : null)
				: sanitize_key((string) ($row['status'] ?? 'active'));
			$status_label = function_exists('bvmgr_ticketing_claims_grant_status_label')
				? bvmgr_ticketing_claims_grant_status_label($status_key)
				: ucwords(str_replace('_', ' ', $status_key));
			$status_help = function_exists('bvmgr_ticketing_claims_grant_status_explanation')
				? bvmgr_ticketing_claims_grant_status_explanation($status_key)
				: '';

			$event_title = $event_id > 0 ? (string) get_the_title($event_id) : '';
			$event_date = bvmgr_ticketing_claims_account_event_date_text($event_id);
			$qty_limit = max(0, absint($row['qty_limit'] ?? 0));
			$qty_used = max(0, absint($row['qty_used'] ?? 0));

			$out[] = array(
				'event_title' => $event_title !== '' ? $event_title : __('Event benefit', 'backstage-venue-manager'),
				'event_date' => $event_date,
				'type_label' => $type_label,
				'status_key' => $status_key,
				'status_label' => $status_label,
				'status_help' => $status_help,
				'note' => sanitize_text_field((string) ($row['note'] ?? '')),
				'qty_limit' => $qty_limit,
				'qty_used' => $qty_used,
				'updated_at' => (string) ($row['updated_at'] ?? ''),
				'created_at' => (string) ($row['created_at'] ?? ''),
			);
		}

		return $out;
	}
}

if (!function_exists('bvmgr_ticketing_claims_render_account_benefits_entry')) {
	function bvmgr_ticketing_claims_render_account_benefits_entry(): void
	{
		if (!is_user_logged_in()) {
			return;
		}

		$user_id = (int) get_current_user_id();
		$expanded = bvmgr_ticketing_claims_account_should_expand();
		$panel_url = bvmgr_ticketing_claims_account_benefits_url();

		$verified_programs = function_exists('bvmgr_ticketing_get_user_verified_programs')
			? (array) bvmgr_ticketing_get_user_verified_programs($user_id)
			: array();
		$grants = bvmgr_ticketing_claims_account_user_grants($user_id, 50);
		$recent_claim_emails = function_exists('bvmgr_ticketing_claims_recent_assignee_emails_for_buyer')
			? (array) bvmgr_ticketing_claims_recent_assignee_emails_for_buyer($user_id, 8)
			: array();

		echo '<section id="vms-benefits-panel" class="vms-benefits-account-entry" data-vms-tour="claims.account.help">';
		echo '<h3 data-vms-tour="claims.account.summary">' . esc_html__('My Eligibility & Benefits', 'backstage-venue-manager') . '</h3>';
		echo '<p class="vms-verify-copy">' . esc_html__('See which eligibility types are approved on your account and which event benefits are currently available, reserved, used, expired, or revoked.', 'backstage-venue-manager') . '</p>';

		if (!$expanded) {
			if ($panel_url !== '') {
				echo '<p><a class="button" href="' . esc_url($panel_url) . '">' . esc_html__('Open My Benefits', 'backstage-venue-manager') . '</a></p>';
			} else {
				echo '<p class="vms-verify-copy">' . esc_html__('Benefits are shown here once the My Account dashboard page is available.', 'backstage-venue-manager') . '</p>';
			}
			echo '</section>';
			return;
		}

		echo '<div class="vms-benefits-how" data-vms-tour="claims.account.how">';
		echo '<p class="vms-verify-copy"><strong>' . esc_html__('How this works', 'backstage-venue-manager') . ':</strong> ' . esc_html__('Credential eligibility is approval tied to your account. Event benefits are additional rights that can be assigned for a specific event. Reserved means a temporary hold during checkout.', 'backstage-venue-manager') . '</p>';
		echo '</div>';

		echo '<div class="vms-benefits-block" data-vms-tour="claims.account.verified">';
		echo '<h4>' . esc_html__('Approved Eligibility', 'backstage-venue-manager') . '</h4>';
		if (empty($verified_programs)) {
			echo '<p class="vms-verify-copy">' . esc_html__('No approved credential eligibility is attached to this account yet.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<ul class="vms-benefits-list">';
			foreach ($verified_programs as $program_key) {
				$program_key = sanitize_key((string) $program_key);
				if ($program_key === '') {
					continue;
				}
				$program_label = function_exists('bvmgr_ticketing_verification_program_label')
					? bvmgr_ticketing_verification_program_label($program_key)
					: ucwords(str_replace('_', ' ', $program_key));
				/* translators: %s: human-readable value used in this message. */
				echo '<li>' . esc_html(sprintf(__('Verified for %s eligibility', 'backstage-venue-manager'), $program_label)) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		echo '<div class="vms-benefits-block" data-vms-tour="claims.account.grants">';
		echo '<h4>' . esc_html__('Event Benefits', 'backstage-venue-manager') . '</h4>';
		if (empty($grants)) {
			echo '<p class="vms-verify-copy">' . esc_html__('No event-specific benefits are currently attached to this account.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<div class="vms-benefits-cards">';
			foreach ($grants as $grant) {
				$status_key = sanitize_html_class((string) ($grant['status_key'] ?? 'active'));
				$status_label = (string) ($grant['status_label'] ?? '');
				$status_help = (string) ($grant['status_help'] ?? '');
				$event_title = (string) ($grant['event_title'] ?? '');
				$event_date = (string) ($grant['event_date'] ?? '');
				$type_label = (string) ($grant['type_label'] ?? '');
				$qty_limit = max(0, absint($grant['qty_limit'] ?? 0));
				$qty_used = max(0, absint($grant['qty_used'] ?? 0));
				$note = (string) ($grant['note'] ?? '');

				echo '<article class="vms-benefits-card">';
				echo '<p class="vms-benefits-card-event"><strong>' . esc_html($event_title) . '</strong>';
				if ($event_date !== '') {
					echo '<br /><span>' . esc_html($event_date) . '</span>';
				}
				echo '</p>';
				echo '<p class="vms-benefits-card-type">' . esc_html($type_label) . '</p>';
				echo '<p><span class="vms-claims-account-status vms-claims-account-status-' . esc_attr($status_key) . '">' . esc_html($status_label) . '</span></p>';
				if ($status_help !== '') {
					echo '<p class="vms-benefits-card-help">' . esc_html($status_help) . '</p>';
				}
				if ($qty_limit > 0) {
					/* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
					echo '<p class="vms-benefits-card-help">' . esc_html(sprintf(__('Used %1$d of %2$d units', 'backstage-venue-manager'), $qty_used, $qty_limit)) . '</p>';
				}
				if ($note !== '') {
					echo '<p class="vms-benefits-card-note">' . esc_html($note) . '</p>';
				}
				echo '</article>';
			}
			echo '</div>';
		}
		echo '</div>';

		if (!empty($recent_claim_emails)) {
			echo '<div class="vms-benefits-block" data-vms-tour="claims.account.recent">';
			echo '<h4>' . esc_html__('Recently Claimed For', 'backstage-venue-manager') . '</h4>';
			echo '<p class="vms-verify-copy">' . esc_html__('Quick helper for repeat group purchases. These addresses are only shown to you.', 'backstage-venue-manager') . '</p>';
			echo '<div class="vms-benefits-recent-list">';
			foreach ($recent_claim_emails as $email) {
				$email = sanitize_email((string) $email);
				if ($email === '') {
					continue;
				}
				echo '<button type="button" class="button vms-benefits-recent-email" data-vms-recent-claim-email="' . esc_attr($email) . '">' . esc_html($email) . '</button> ';
			}
			echo '</div>';
			echo '</div>';
		}

		echo '</section>';
	}
}
add_action('woocommerce_account_dashboard', 'bvmgr_ticketing_claims_render_account_benefits_entry', 27);

if (!function_exists('bvmgr_ticketing_claims_enqueue_account_styles')) {
	function bvmgr_ticketing_claims_enqueue_account_styles(): void
	{
		if (is_admin() || !is_user_logged_in()) {
			return;
		}
		if (!function_exists('is_account_page') || !is_account_page()) {
			return;
		}

		$deps = array();
		if (function_exists('wp_style_is')) {
			foreach (array('kadence-tribe-css', 'sr-tec-custom-css-css') as $maybe_dep) {
				if (wp_style_is($maybe_dep, 'registered') || wp_style_is($maybe_dep, 'enqueued')) {
					$deps[] = $maybe_dep;
				}
			}
		}

		wp_enqueue_style(
			'bvmgr-ticketing-front',
			plugins_url('assets/css/vms-ticketing-front.css', BVMGR_PLUGIN_FILE),
			$deps,
			function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '')
		);
	}
}
add_action('wp_enqueue_scripts', 'bvmgr_ticketing_claims_enqueue_account_styles', 1002);

if (!function_exists('bvmgr_ticketing_claims_handle_client_log_action')) {
	function bvmgr_ticketing_claims_handle_client_log_action(): void
	{
		if (!is_user_logged_in()) {
			bvmgr_ticketing_v2_ajax_send_error(array('message' => 'login_required'), 401);
		}

		if (!bvmgr_check_ajax_referer_compat('bvmgr_ticketing_claims_log_client_action', 'nonce', false)) {
			bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
		}

		$reason_code = isset($_POST['reason_code']) ? sanitize_key((string) wp_unslash($_POST['reason_code'])) : '';
		$allowed = array('self_apply_attempt', 'recent_claim_helper_used');
		if (!in_array($reason_code, $allowed, true)) {
			bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_reason'), 400);
		}

		$buyer_user_id = (int) get_current_user_id();
		$event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
		$assignee_email = isset($_POST['assignee_email']) ? sanitize_email((string) wp_unslash($_POST['assignee_email'])) : '';
		$message = ($reason_code === 'self_apply_attempt')
			? __('Customer used "Use my benefit" helper on event page.', 'backstage-venue-manager')
			: __('Customer reused a recent claimant email helper.', 'backstage-venue-manager');

		if (function_exists('bvmgr_ticketing_claims_log_result')) {
			bvmgr_ticketing_claims_log_result(array(
				'event_id' => $event_id,
				'buyer_user_id' => $buyer_user_id,
				'assignee_user_id' => $buyer_user_id,
				'assignee_email' => $assignee_email,
				'rule_path' => 'frontend_helper',
				'direct_grant_id' => 0,
				'result' => 'success',
				'reason_code' => $reason_code,
				'message' => $message,
				'context' => array(
					'source' => 'frontend_helper',
					'event_id' => $event_id,
					'assignee_email' => $assignee_email,
				),
			));
		}

		bvmgr_ticketing_v2_ajax_send_success(array('ok' => true));
	}
}
add_action('wp_ajax_vms_ticketing_claims_log_client_action', 'bvmgr_ticketing_claims_handle_client_log_action');

if (!function_exists('bvmgr_ticketing_claims_handle_validate_assignee')) {
	function bvmgr_ticketing_claims_handle_validate_assignee(): void
	{
		if (!bvmgr_check_ajax_referer_compat('bvmgr_ticketing_claims_validate_assignee', 'nonce', false)) {
			bvmgr_ticketing_v2_ajax_send_error(array(
				'ok' => false,
				'message' => __('Session expired. Please refresh and try again.', 'backstage-venue-manager'),
				'reason_code' => 'bad_nonce',
			), 403);
		}

		if (!is_user_logged_in()) {
			bvmgr_ticketing_v2_ajax_send_error(array(
				'ok' => false,
				'message' => __('Log in before checking approved guest emails for this ticket.', 'backstage-venue-manager'),
				'reason_code' => 'login_required',
			), 401);
		}

		$product_id = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
		$event_id = isset($_POST['event_id']) ? absint(wp_unslash($_POST['event_id'])) : 0;
		$ticket_key = isset($_POST['ticket_key']) ? sanitize_key((string) wp_unslash($_POST['ticket_key'])) : '';
		$assignee_email = isset($_POST['assignee_email']) ? sanitize_email((string) wp_unslash($_POST['assignee_email'])) : '';
			$existing_counts = bvmgr_ticketing_claims_post_existing_counts();

		if ($product_id <= 0 || $assignee_email === '') {
			bvmgr_ticketing_v2_ajax_send_error(array(
				'ok' => false,
				'message' => __('Enter a valid registered email address first.', 'backstage-venue-manager'),
				'reason_code' => 'invalid_request',
			), 400);
		}

		if (!function_exists('bvmgr_ticketing_v2_resolve_verified_ticket_context')) {
			bvmgr_ticketing_v2_ajax_send_error(array(
				'ok' => false,
				'message' => __('Ticket validation is temporarily unavailable.', 'backstage-venue-manager'),
				'reason_code' => 'context_unavailable',
			), 400);
		}

		$ctx = (array) bvmgr_ticketing_v2_resolve_verified_ticket_context($product_id);
		$visibility_mode = sanitize_key((string) ($ctx['visibility_mode'] ?? 'public'));
		if ($visibility_mode !== 'verified') {
			bvmgr_ticketing_v2_ajax_send_error(array(
				'ok' => false,
				'message' => __('This ticket does not support claim-ticket assignment.', 'backstage-venue-manager'),
				'reason_code' => 'ticket_not_verified',
			), 400);
		}

		$legacy_program = sanitize_key((string) ($ctx['program'] ?? ''));
		$allowed_programs = function_exists('bvmgr_ticketing_claims_normalize_allowed_programs')
			? bvmgr_ticketing_claims_normalize_allowed_programs($ctx['allowed_programs'] ?? array(), $legacy_program)
			: ($legacy_program !== '' ? array($legacy_program) : array());
		$allow_direct_grants = function_exists('bvmgr_ticketing_claims_truthy')
			? bvmgr_ticketing_claims_truthy($ctx['allow_direct_grants'] ?? false, false)
			: !empty($ctx['allow_direct_grants']);
		$grant_type = sanitize_key((string) ($ctx['claim_grant_type'] ?? 'event_ticket_eligibility'));
		if ($grant_type === '') {
			$grant_type = 'event_ticket_eligibility';
		}

		$ctx_event_id = absint($ctx['event_id'] ?? 0);
		if ($event_id <= 0) {
			$event_id = $ctx_event_id;
		}
		if ($event_id <= 0) {
			bvmgr_ticketing_v2_ajax_send_error(array(
				'ok' => false,
				'message' => __('Could not determine the event for this ticket.', 'backstage-venue-manager'),
				'reason_code' => 'event_missing',
			), 400);
		}

		$ctx_ticket_key = sanitize_key((string) ($ctx['ticket_key'] ?? ''));
		if ($ticket_key === '') {
			$ticket_key = $ctx_ticket_key;
		}
		$claims_per_assignee = max(1, absint($ctx['claims_per_assignee'] ?? 1));
		$group_product_ids = function_exists('bvmgr_ticketing_v2_ticket_group_product_ids_from_context')
			? bvmgr_ticketing_v2_ticket_group_product_ids_from_context($ctx, $product_id)
			: array($product_id);
		if (empty($group_product_ids)) {
			$group_product_ids = array($product_id);
		}
		$ticket_label = '';
		if (function_exists('wc_get_product')) {
			$product = wc_get_product($product_id);
			if ($product) {
				$ticket_label = sanitize_text_field((string) $product->get_name());
			}
		}

		$user = get_user_by('email', $assignee_email);
		if (!($user instanceof WP_User)) {
			bvmgr_ticketing_v2_ajax_send_error(array(
				'ok' => false,
				'message' => function_exists('bvmgr_ticketing_v2_claim_assignment_unknown_guest_message')
					? bvmgr_ticketing_v2_claim_assignment_unknown_guest_message()
					: __("We couldn't find an approved qualified guest account for this email. The guest needs to register and be approved before this ticket can be claimed.", 'backstage-venue-manager'),
				'reason_code' => 'account_not_found',
				'ticket_label' => $ticket_label,
			), 200);
		}

		$resolved = array(
			'eligible' => false,
			'reason_code' => 'not_eligible',
			'message' => '',
			'matched_rule_path' => '',
			'matched_grant_id' => 0,
		);
		if (function_exists('bvmgr_ticketing_claims_resolve_eligibility')) {
			$resolved = (array) bvmgr_ticketing_claims_resolve_eligibility(array(
				'user_id' => (int) $user->ID,
				'event_id' => (int) $event_id,
				'ticket_product_id' => (int) $product_id,
				'ticket_key' => (string) $ticket_key,
				'legacy_program' => (string) $legacy_program,
				'allowed_programs' => $allowed_programs,
				'allow_direct_grants' => $allow_direct_grants,
				'grant_type' => (string) $grant_type,
			));
		}

		$eligible = !empty($resolved['eligible']);
		$reason_code = sanitize_key((string) ($resolved['reason_code'] ?? ($eligible ? 'ok' : 'not_eligible')));
		$message = sanitize_text_field((string) ($resolved['message'] ?? ''));
		if ($message === '' && !$eligible) {
			$message = function_exists('bvmgr_ticketing_v2_claim_assignment_unapproved_guest_message')
				? bvmgr_ticketing_v2_claim_assignment_unapproved_guest_message()
				: __('This email is not approved for this ticket yet. The guest needs to register and be approved before this ticket can be claimed.', 'backstage-venue-manager');
		}
		if ($message === '' && $eligible) {
			$message = __('Eligible account confirmed for this ticket.', 'backstage-venue-manager');
		}

		$claims_per_assignee = function_exists('bvmgr_ticketing_v2_assignee_claims_per_event_limit')
			? max(1, absint(bvmgr_ticketing_v2_assignee_claims_per_event_limit($ctx, $user, $resolved)))
			: max(1, absint($claims_per_assignee));

		$consumed_qty = function_exists('bvmgr_ticketing_v2_assignee_consumed_qty_for_event')
			? absint(bvmgr_ticketing_v2_assignee_consumed_qty_for_event($event_id, $assignee_email, $group_product_ids))
			: 0;
		$cart_counts = function_exists('bvmgr_ticketing_v2_cart_assignee_usage_for_event')
			? (array) bvmgr_ticketing_v2_cart_assignee_usage_for_event($event_id, $ticket_key)
			: array();
		if (!empty($existing_counts)) {
			foreach ($existing_counts as $existing_email_key => $existing_count) {
				$cart_counts[$existing_email_key] = max(0, absint($cart_counts[$existing_email_key] ?? 0)) + max(0, absint($existing_count));
			}
		}
		$email_key = strtolower($assignee_email);
		$in_cart_qty = max(0, absint($cart_counts[$email_key] ?? 0));
		$remaining_before_assignment = max(0, $claims_per_assignee - $consumed_qty - $in_cart_qty);

		// A qualified account may be allowed to claim more than one ticket for the same
		// event. Do not reject the same assignee email merely because it already
		// appears in the current cart/session; only reject it once the effective
		// event limit for that assignee has been consumed by prior purchases plus
		// current cart assignments.
		if ($eligible && $remaining_before_assignment <= 0) {
			$eligible = false;
			$reason_code = 'assignee_limit_reached';
			/* translators: %d: number used in this message. */
			$message = sprintf(__('This guest has already used the %d-ticket limit for this event.', 'backstage-venue-manager'), $claims_per_assignee);
		}

		$remaining_after_assignment = max(0, $remaining_before_assignment - 1);
		if ($eligible) {
			$message = sprintf(
				/* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
				__('Eligible account confirmed. This account is eligible for %1$d ticket(s) for this event (%2$d remaining after this ticket).', 'backstage-venue-manager'),
				$claims_per_assignee,
				$remaining_after_assignment
			);
		}

		if (!$eligible) {
			bvmgr_ticketing_v2_ajax_send_error(array(
				'ok' => false,
				'message' => $message,
				'reason_code' => $reason_code,
				'assignee_email' => sanitize_email((string) $user->user_email),
				'ticket_label' => $ticket_label,
			), 200);
		}

		bvmgr_ticketing_v2_ajax_send_success(array(
			'ok' => true,
			'message' => $message,
			'reason_code' => $reason_code,
			'assignee_email' => sanitize_email((string) $user->user_email),
			'ticket_label' => $ticket_label,
		));
	}
}
add_action('wp_ajax_vms_ticketing_claims_validate_assignee', 'bvmgr_ticketing_claims_handle_validate_assignee');
add_action('wp_ajax_nopriv_vms_ticketing_claims_validate_assignee', 'bvmgr_ticketing_claims_handle_validate_assignee');

if (!function_exists('bvmgr_ticketing_claims_frontend_tour_screen_key')) {
	function bvmgr_ticketing_claims_frontend_tour_screen_key(string $screen_key): string
	{
		if (is_admin()) {
			return $screen_key;
		}

		if (function_exists('is_account_page') && is_account_page() && is_user_logged_in()) {
			return 'frontend:vms-account-benefits';
		}

		if (is_singular('tribe_events') && is_user_logged_in()) {
			$event_id = (int) get_queried_object_id();
			if ($event_id > 0 && function_exists('bvmgr_ticketing_verification_event_has_verified_tickets') && bvmgr_ticketing_verification_event_has_verified_tickets($event_id)) {
				return 'frontend:vms-event-benefits';
			}
		}

		return $screen_key;
	}
}
add_filter('vms_tours_frontend_screen_key', 'bvmgr_ticketing_claims_frontend_tour_screen_key', 40);

if (!function_exists('bvmgr_ticketing_claims_register_phase3_tours')) {
	/**
	 * @param array<int,array<string,mixed>> $tours
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_ticketing_claims_register_phase3_tours(array $tours): array
	{
		$tours[] = array(
			'id' => 'vms.claims.operator.grants',
			'title' => __('Event Benefit Grant Management', 'backstage-venue-manager'),
			'screen' => 'admin:vms_event_plan',
			'version' => '3.0.0',
			'level' => 'beginner',
			'description' => __('Assign and manage event-specific benefits for individual accounts.', 'backstage-venue-manager'),
			'audience' => array(
				'capabilities_any' => array('manage_options', 'edit_posts'),
				'capabilities_all' => array(),
				'roles_any' => array(),
				'roles_all' => array(),
			),
			'auto_run' => false,
			'auto_run_delay_ms' => 500,
			'priority' => 14,
			'steps' => array(
				array(
					'id' => 'claims_grants_help',
					'selector' => '[data-vms-tour="claims.grants.help"]',
					'title' => __('What A Benefit Grant Is', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Use this area to assign event-specific admission rights directly to an account. This exists so support teams can safely handle make-good, promo, and manual exception scenarios without editing raw data.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'claims_grants_lookup',
					'selector' => '[data-vms-tour="claims.grants.lookup"]',
					'title' => __('Find The Correct Account', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Search by email, login, or name first so the benefit is tied to the right user. Incorrect account targeting can block legitimate purchasers later.', 'backstage-venue-manager')),
					'placement' => 'bottom',
				),
				array(
					'id' => 'claims_grants_form',
					'selector' => '[data-vms-tour="claims.grants.form"]',
					'title' => __('Create A Benefit Safely', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Choose benefit source, quantity, status, and reason. Status exists to control availability without deleting history, which protects support and audit workflows.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'claims_grants_browser',
					'selector' => '[data-vms-tour="claims.grants.browser"]',
					'title' => __('Monitor Existing Grants', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Review active, reserved, used, expired, and revoked grants here. Reserved means checkout is in progress; repair tools can release stuck reservations when needed.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
			),
		);

		$tours[] = array(
			'id' => 'vms.claims.operator.browser',
			'title' => __('Benefit Browser & Repair Tools', 'backstage-venue-manager'),
			'screen' => 'admin:vms-credential-claims',
			'version' => '3.0.0',
			'level' => 'beginner',
			'description' => __('Inspect benefit state, claim history, and safe repair actions.', 'backstage-venue-manager'),
			'audience' => array(
				'capabilities_any' => array('manage_options'),
				'capabilities_all' => array(),
				'roles_any' => array(),
				'roles_all' => array(),
			),
			'auto_run' => true,
			'auto_run_delay_ms' => 500,
			'priority' => 15,
			'steps' => array(
				array(
					'id' => 'claims_browser_help',
					'selector' => '[data-vms-tour="claims.browser.help"]',
					'title' => __('Purpose Of This Screen', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('This screen shows how eligibility decisions were made and where each benefit came from, so operators can explain outcomes clearly and avoid guessing.', 'backstage-venue-manager')),
					'placement' => 'bottom',
				),
				array(
					'id' => 'claims_browser_filters',
					'selector' => '[data-vms-tour="claims.browser.filters"]',
					'title' => __('Filter Before Acting', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Filter by account, event, source, status, and dates to isolate the exact case. Precise filtering reduces accidental edits on the wrong record.', 'backstage-venue-manager')),
					'placement' => 'bottom',
				),
				array(
					'id' => 'claims_browser_table',
					'selector' => '[data-vms-tour="claims.browser.table"]',
					'title' => __('Read Benefit Status Correctly', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Status cards explain availability, reserved holds, and used history. Use revoke for forward-looking blocks; use repair/release when correcting stuck state.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'claims_log',
					'selector' => '[data-vms-tour="claims.log"]',
					'title' => __('Audit History', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Audit logs protect operators by recording who acted, what changed, and why. This exists so support actions remain traceable during escalations.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'claims_repair',
					'selector' => '[data-vms-tour="claims.repair"]',
					'title' => __('When To Repair Vs Revoke', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Revoke removes future availability. Repair is for correcting bad reservation state so legitimate benefits become usable again without deleting history.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
			),
		);

		$tours[] = array(
			'id' => 'vms.claims.customer.account',
			'title' => __('My Eligibility & Benefits', 'backstage-venue-manager'),
			'screen' => 'frontend:vms-account-benefits',
			'version' => '3.0.0',
			'level' => 'beginner',
			'description' => __('Understand credential eligibility and event-specific account benefits.', 'backstage-venue-manager'),
			'audience' => array(
				'capabilities_any' => array('read'),
				'capabilities_all' => array(),
				'roles_any' => array(),
				'roles_all' => array(),
			),
			'auto_run' => true,
			'auto_run_delay_ms' => 700,
			'priority' => 10,
			'steps' => array(
				array(
					'id' => 'claims_account_help',
					'selector' => '[data-vms-tour="claims.account.help"]',
					'title' => __('What You See Here', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('This page shows benefits attached to your account. Some come from approved credentials, and others may be assigned for specific events.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'claims_account_how',
					'selector' => '[data-vms-tour="claims.account.how"]',
					'title' => __('Why Status Matters', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Reserved means a temporary checkout hold. Used means already consumed. Revoked or expired means the benefit is no longer available.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'claims_account_grants',
					'selector' => '[data-vms-tour="claims.account.grants"]',
					'title' => __('Event Benefits', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Use this list to confirm what is available before purchasing. If something looks wrong, support can review audit history instead of asking you to retry blindly.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
			),
		);

		$tours[] = array(
			'id' => 'vms.claims.customer.event',
			'title' => __('Special-Access Ticket Assignment', 'backstage-venue-manager'),
			'screen' => 'frontend:vms-event-benefits',
			'version' => '4.0.0',
			'level' => 'beginner',
			'description' => __('Assign one eligible registered account per special-access ticket before adding tickets to your cart.', 'backstage-venue-manager'),
				'audience' => array(
					'capabilities_any' => array('read'),
					'capabilities_all' => array(),
				'roles_any' => array(),
				'roles_all' => array(),
			),
			'auto_run' => true,
			'auto_run_delay_ms' => 900,
			'priority' => 11,
			'steps' => array(
				array(
					'id' => 'claims_event_panel',
					'selector' => '[data-vms-tour="claims.seats.panel"]',
					'title' => __('Assign One Email Per Ticket', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Each special-access ticket needs a registered eligible account email. This protects fairness, prevents duplicate claims, and keeps support decisions clear later.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'claims_event_rows',
					'selector' => '[data-vms-tour="claims.seats.rows"]',
					'title' => __('Registered Accounts Matter', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('If someone has not registered yet, have them create an account first so their ticket can be validated and assigned correctly.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'claims_event_actions',
					'selector' => '[data-vms-tour="claims.seats.actions"]',
					'title' => __('Use My Benefit And Recent Emails', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Quick actions reduce typing, but every ticket still re-validates before tickets are added to your cart. Duplicates are blocked so each ticket maps to the right eligible account.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
			),
		);

		return $tours;
	}
}
add_filter('vms_tours_register', 'bvmgr_ticketing_claims_register_phase3_tours', 35);
