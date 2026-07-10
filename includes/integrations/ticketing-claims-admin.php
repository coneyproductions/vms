<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_ticketing_claims_manage_capability')) {
	function vms_ticketing_claims_manage_capability(): string
	{
		if (function_exists('vms_ticketing_verification_manage_capability')) {
			return (string) vms_ticketing_verification_manage_capability();
		}
		return 'manage_options';
	}
}

if (!function_exists('vms_ticketing_claims_current_user_can_manage')) {
	function vms_ticketing_claims_current_user_can_manage(): bool
	{
		$cap = vms_ticketing_claims_manage_capability();
		return current_user_can($cap) || current_user_can('manage_options');
	}
}

if (!function_exists('vms_ticketing_claims_menu_slug')) {
	function vms_ticketing_claims_menu_slug(): string
	{
		return 'vms-credential-claims';
	}
}

if (!function_exists('vms_ticketing_claims_admin_page_url')) {
	function vms_ticketing_claims_admin_page_url(array $args = array()): string
	{
		return add_query_arg($args, admin_url('admin.php?page=' . vms_ticketing_claims_menu_slug()));
	}
}

if (!function_exists('vms_ticketing_claims_is_admin_page')) {
	function vms_ticketing_claims_is_admin_page(): bool
	{
		if (!is_admin()) {
			return false;
		}
		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		return $page === vms_ticketing_claims_menu_slug();
	}
}

if (!function_exists('vms_ticketing_claims_event_edit_url')) {
	function vms_ticketing_claims_event_edit_url(int $event_plan_id, array $args = array()): string
	{
		$event_plan_id = absint($event_plan_id);
		$base = admin_url('post.php?post=' . $event_plan_id . '&action=edit');
		return add_query_arg($args, $base);
	}
}


if (!function_exists('vms_ticketing_claims_event_metabox_form_id')) {
	function vms_ticketing_claims_event_metabox_form_id(int $event_plan_id, string $suffix): string
	{
		$event_plan_id = absint($event_plan_id);
		$suffix = sanitize_key($suffix);
		if ($suffix === '') {
			$suffix = 'action';
		}
		return 'vms-claims-ep-form-' . $event_plan_id . '-' . $suffix;
	}
}

if (!function_exists('vms_ticketing_claims_event_metabox_register_form')) {
	/**
	 * Register a detached footer form for the Event Plan editor.
	 * This keeps admin-post and filter actions out of the main WP post edit form.
	 *
	 * @param array<string,mixed> $hidden_fields
	 */
	function vms_ticketing_claims_event_metabox_register_form(string $form_id, string $method, string $action, array $hidden_fields = array()): void
	{
		global $vms_ticketing_claims_event_metabox_forms;
		if (!is_array($vms_ticketing_claims_event_metabox_forms)) {
			$vms_ticketing_claims_event_metabox_forms = array();
		}
		$vms_ticketing_claims_event_metabox_forms[$form_id] = array(
			'method' => (strtolower($method) === 'get') ? 'get' : 'post',
			'action' => esc_url_raw($action),
			'hidden_fields' => $hidden_fields,
		);
	}
}

if (!function_exists('vms_ticketing_claims_render_event_metabox_footer_forms')) {
	function vms_ticketing_claims_render_event_metabox_footer_forms(): void
	{
		if (!vms_ticketing_claims_is_event_plan_edit_screen()) {
			return;
		}

		global $vms_ticketing_claims_event_metabox_forms;
		if (!is_array($vms_ticketing_claims_event_metabox_forms) || empty($vms_ticketing_claims_event_metabox_forms)) {
			return;
		}

		foreach ($vms_ticketing_claims_event_metabox_forms as $form_id => $form) {
			$form_id = sanitize_html_class((string) $form_id);
			$method = (($form['method'] ?? 'post') === 'get') ? 'get' : 'post';
			$action = esc_url((string) ($form['action'] ?? ''));
			$hidden_fields = is_array($form['hidden_fields'] ?? null) ? (array) $form['hidden_fields'] : array();
			echo '<form id="' . esc_attr($form_id) . '" method="' . esc_attr($method) . '" action="' . $action . '" class="vms-claims-detached-form" style="display:none;">';
			foreach ($hidden_fields as $name => $value) {
				if ($value === null) {
					continue;
				}
				echo '<input type="hidden" name="' . esc_attr((string) $name) . '" value="' . esc_attr((string) $value) . '" />';
			}
			echo '</form>';
		}
	}
}
add_action('admin_footer', 'vms_ticketing_claims_render_event_metabox_footer_forms', 45);

if (!function_exists('vms_ticketing_claims_is_event_plan_edit_screen')) {
	function vms_ticketing_claims_is_event_plan_edit_screen(): bool
	{
		if (!is_admin()) {
			return false;
		}
		if (!function_exists('get_current_screen')) {
			return false;
		}
		$screen = get_current_screen();
		if (!is_object($screen)) {
			return false;
		}
		return ($screen->base ?? '') === 'post' && ($screen->post_type ?? '') === 'vms_event_plan';
	}
}

if (!function_exists('vms_ticketing_claims_admin_notice_messages')) {
	/**
	 * @return array<string,array{type:string,message:string}>
	 */
	function vms_ticketing_claims_admin_notice_messages(): array
	{
		return array(
			'grant_created' => array(
				'type' => 'success',
				'message' => __('Direct event grant created.', 'backstage-venue-manager'),
			),
			'grant_create_failed' => array(
				'type' => 'error',
				'message' => __('Could not create direct event grant.', 'backstage-venue-manager'),
			),
			'grant_note_saved' => array(
				'type' => 'success',
				'message' => __('Grant note updated.', 'backstage-venue-manager'),
			),
			'grant_note_failed' => array(
				'type' => 'error',
				'message' => __('Could not update grant note.', 'backstage-venue-manager'),
			),
			'grant_status_saved' => array(
				'type' => 'success',
				'message' => __('Grant status updated.', 'backstage-venue-manager'),
			),
			'grant_status_failed' => array(
				'type' => 'error',
				'message' => __('Could not update grant status.', 'backstage-venue-manager'),
			),
			'user_not_found' => array(
				'type' => 'error',
				'message' => __('No matching account was found for that search.', 'backstage-venue-manager'),
			),
			'event_missing' => array(
				'type' => 'warning',
				'message' => __('A linked calendar event is required before direct event grants can be created.', 'backstage-venue-manager'),
			),
			'invalid_request' => array(
				'type' => 'error',
				'message' => __('Invalid request. Please try again.', 'backstage-venue-manager'),
			),
			'confirm_used_required' => array(
				'type' => 'error',
				'message' => __('This grant already has usage history. Please confirm before disabling it.', 'backstage-venue-manager'),
			),
			'reservation_released' => array(
				'type' => 'success',
				'message' => __('Reservation released.', 'backstage-venue-manager'),
			),
			'reservation_release_failed' => array(
				'type' => 'error',
				'message' => __('Could not release reservation.', 'backstage-venue-manager'),
			),
		);
	}
}

if (!function_exists('vms_ticketing_claims_render_admin_notices')) {
	function vms_ticketing_claims_render_admin_notices(): void
	{
		if (!vms_ticketing_claims_is_event_plan_edit_screen() && !vms_ticketing_claims_is_admin_page()) {
			return;
		}
		$notice_key = isset($_GET['vms_claim_notice']) ? sanitize_key((string) $_GET['vms_claim_notice']) : '';
		if ($notice_key === '') {
			return;
		}

		$map = vms_ticketing_claims_admin_notice_messages();
		if (!isset($map[$notice_key])) {
			return;
		}

		$type = (string) ($map[$notice_key]['type'] ?? 'info');
		$message = (string) ($map[$notice_key]['message'] ?? '');
		$class = 'notice';
		if ($type === 'success') {
			$class .= ' notice-success';
		} elseif ($type === 'warning') {
			$class .= ' notice-warning';
		} elseif ($type === 'error') {
			$class .= ' notice-error';
		} else {
			$class .= ' notice-info';
		}

		echo '<div class="' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
	}
}
add_action('admin_notices', 'vms_ticketing_claims_render_admin_notices');

if (!function_exists('vms_ticketing_claims_event_context')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_ticketing_claims_event_context(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$tec_event_id = 0;
		if ($event_plan_id > 0 && function_exists('vms_ticketing_b_get_linked_tec_event_id')) {
			$tec_event_id = absint(vms_ticketing_b_get_linked_tec_event_id($event_plan_id));
		}

		return array(
			'event_plan_id' => $event_plan_id,
			'event_plan_title' => $event_plan_id > 0 ? (string) get_the_title($event_plan_id) : '',
			'event_id' => $tec_event_id,
			'event_title' => $tec_event_id > 0 ? (string) get_the_title($tec_event_id) : '',
		);
	}
}

if (!function_exists('vms_ticketing_claims_search_users')) {
	/**
	 * @return array<int,WP_User>
	 */
	function vms_ticketing_claims_search_users(string $query, int $limit = 20): array
	{
		$query = trim(sanitize_text_field($query));
		if ($query === '') {
			return array();
		}

		$limit = max(1, min(50, $limit));
		$args = array(
			'number' => $limit,
			'search' => '*' . esc_attr($query) . '*',
			'search_columns' => array('user_email', 'user_login', 'display_name'),
			'orderby' => 'display_name',
			'order' => 'ASC',
		);
		$results = get_users($args);
		$out = array();
		foreach ((array) $results as $item) {
			if ($item instanceof WP_User) {
				$out[] = $item;
			}
		}
		return $out;
	}
}

if (!function_exists('vms_ticketing_claims_user_display')) {
	function vms_ticketing_claims_user_display(int $user_id, string $fallback = ''): string
	{
		$user_id = absint($user_id);
		if ($user_id > 0) {
			$user = get_user_by('id', $user_id);
			if ($user instanceof WP_User) {
				$display = trim((string) $user->display_name);
				$email = trim((string) $user->user_email);
				if ($display === '') {
					$display = (string) $user->user_login;
				}
				if ($email !== '') {
					return $display . ' <' . $email . '>';
				}
				return $display;
			}
		}
		return trim($fallback) !== '' ? $fallback : __('Unknown account', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_ticketing_claims_ticket_context_label')) {
	function vms_ticketing_claims_ticket_context_label(int $ticket_product_id, string $ticket_key = ''): string
	{
		$ticket_product_id = absint($ticket_product_id);
		$ticket_key = sanitize_key($ticket_key);
		$label = __('Any ticket', 'backstage-venue-manager');
		if ($ticket_product_id > 0) {
			$title = trim((string) get_the_title($ticket_product_id));
			$label = $title !== '' ? $title : ('#' . $ticket_product_id);
		}
		if ($ticket_key !== '') {
			$label .= ' [' . $ticket_key . ']';
		}
		return $label;
	}
}

if (!function_exists('vms_ticketing_claims_resolve_user')) {
	function vms_ticketing_claims_resolve_user(string $identity = '', int $user_id = 0): ?WP_User
	{
		$user_id = absint($user_id);
		if ($user_id > 0) {
			$user = get_user_by('id', $user_id);
			return ($user instanceof WP_User) ? $user : null;
		}

		$identity = trim(sanitize_text_field($identity));
		if ($identity === '') {
			return null;
		}

		if (is_email($identity)) {
			$user = get_user_by('email', sanitize_email($identity));
			return ($user instanceof WP_User) ? $user : null;
		}

		if (is_numeric($identity)) {
			$user = get_user_by('id', absint($identity));
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$user = get_user_by('login', sanitize_user($identity, true));
		return ($user instanceof WP_User) ? $user : null;
	}
}

if (!function_exists('vms_ticketing_claims_event_ticket_options')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_ticketing_claims_event_ticket_options(int $event_plan_id, int $tec_event_id): array
	{
		$out = array();
		$seen = array();

		if ($event_plan_id > 0 && function_exists('vms_ticketing_v2_get_config') && function_exists('vms_ticketing_v2_get_sync')) {
			$cfg = vms_ticketing_v2_get_config($event_plan_id);
			$sync = vms_ticketing_v2_get_sync($event_plan_id);
			$sync_map = (isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
			$sync_tickets = (isset($sync_map['tickets']) && is_array($sync_map['tickets'])) ? $sync_map['tickets'] : array();
			$rows = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();
			foreach ($rows as $ticket_row) {
				if (!is_array($ticket_row)) {
					continue;
				}
				if (array_key_exists('enabled', $ticket_row) && empty($ticket_row['enabled'])) {
					continue;
				}
				$ticket_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
				$product_id = 0;
				if ($ticket_key !== '' && isset($sync_tickets[$ticket_key]) && is_array($sync_tickets[$ticket_key])) {
					$product_id = absint($sync_tickets[$ticket_key]['woo_product_id'] ?? 0);
				}
				if ($product_id <= 0) {
					continue;
				}
				if (isset($seen[$product_id])) {
					continue;
				}
				$seen[$product_id] = true;
				$title = sanitize_text_field((string) ($ticket_row['title'] ?? get_the_title($product_id)));
				$out[] = array(
					'product_id' => $product_id,
					'ticket_key' => $ticket_key,
					'label' => $title !== '' ? $title : ('#' . $product_id),
				);
			}
		}

		if ($tec_event_id > 0) {
			$query = get_posts(array(
				'post_type' => 'product',
				'post_status' => array('publish', 'private', 'draft', 'pending'),
				'fields' => 'ids',
				'posts_per_page' => 200,
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key' => '_vms_ticket_event_id',
						'value' => $tec_event_id,
						'compare' => '=',
						'type' => 'NUMERIC',
					),
					array(
						'key' => '_tribe_wooticket_for_event',
						'value' => $tec_event_id,
						'compare' => '=',
						'type' => 'NUMERIC',
					),
				),
			));

			foreach ((array) $query as $product_id_raw) {
				$product_id = absint($product_id_raw);
				if ($product_id <= 0 || isset($seen[$product_id])) {
					continue;
				}
				$ticket_key = sanitize_key((string) get_post_meta($product_id, '_vms_ticket_key', true));
				if ($ticket_key === '' && function_exists('vms_ticketing_v2_product_meta_key')) {
					$ticket_key = sanitize_key((string) get_post_meta($product_id, vms_ticketing_v2_product_meta_key('ticketing_ticket_key'), true));
				}
				$seen[$product_id] = true;
				$out[] = array(
					'product_id' => $product_id,
					'ticket_key' => $ticket_key,
					'label' => sanitize_text_field((string) get_the_title($product_id)),
				);
			}
		}

		return $out;
	}
}

if (!function_exists('vms_ticketing_claims_get_program_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_ticketing_claims_get_program_options(): array
	{
		if (function_exists('vms_ticketing_verification_programs')) {
			return (array) vms_ticketing_verification_programs();
		}
		return array();
	}
}

if (!function_exists('vms_ticketing_claims_grant_type_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_ticketing_claims_grant_type_options(): array
	{
		if (function_exists('vms_ticketing_claims_grant_type_labels')) {
			return (array) vms_ticketing_claims_grant_type_labels();
		}

		return array(
			'event_ticket_eligibility' => __('Event Ticket Eligibility', 'backstage-venue-manager'),
			'event_free_admit' => __('Free Admission', 'backstage-venue-manager'),
			'credential_benefit_override' => __('Credential Benefit Override', 'backstage-venue-manager'),
			'event_grant' => __('Event Grant', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_ticketing_claims_grant_status_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_ticketing_claims_grant_status_options(): array
	{
		$out = array();
		$statuses = function_exists('vms_ticketing_claims_allowed_grant_statuses')
			? (array) vms_ticketing_claims_allowed_grant_statuses()
			: array('active', 'reserved', 'used', 'expired', 'revoked');
		foreach ($statuses as $status_key) {
			$status_key = sanitize_key((string) $status_key);
			if ($status_key === '') {
				continue;
			}
			$label = function_exists('vms_ticketing_claims_grant_status_label')
				? vms_ticketing_claims_grant_status_label($status_key)
				: ucwords(str_replace('_', ' ', $status_key));
			$out[$status_key] = $label;
		}
		return $out;
	}
}

if (!function_exists('vms_ticketing_claims_grant_next_status_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_ticketing_claims_grant_next_status_options(string $current_status): array
	{
		$current_status = sanitize_key($current_status);
		$allowed = array(
			'active' => array('active', 'reserved', 'used', 'revoked', 'expired'),
			'reserved' => array('reserved', 'active', 'used', 'revoked', 'expired'),
			'used' => array('used', 'active', 'revoked'),
			'expired' => array('expired', 'active', 'revoked'),
			'revoked' => array('revoked', 'active', 'expired'),
		);
		$allowed_for_current = $allowed[$current_status] ?? array('active', 'revoked', 'expired');
		$all = vms_ticketing_claims_grant_status_options();
		$out = array();
		foreach ($allowed_for_current as $status_key) {
			$status_key = sanitize_key((string) $status_key);
			if ($status_key === '' || !isset($all[$status_key])) {
				continue;
			}
			$out[$status_key] = $all[$status_key];
		}
		return $out;
	}
}

if (!function_exists('vms_ticketing_claims_grant_status_change_reason_code')) {
	function vms_ticketing_claims_grant_status_change_reason_code(string $status): string
	{
		$status = sanitize_key($status);
		$map = array(
			'active' => 'grant_repaired_restored',
			'reserved' => 'grant_reserved',
			'used' => 'grant_consumed',
			'expired' => 'grant_expired',
			'revoked' => 'grant_revoked',
		);
		return $map[$status] ?? 'grant_updated';
	}
}

if (!function_exists('vms_ticketing_claims_grant_status_change_message')) {
	function vms_ticketing_claims_grant_status_change_message(string $status): string
	{
		$status = sanitize_key($status);
		$map = array(
			'active' => __('Direct event grant restored to active status by operator.', 'backstage-venue-manager'),
			'reserved' => __('Direct event grant marked reserved by operator.', 'backstage-venue-manager'),
			'used' => __('Direct event grant marked used by operator.', 'backstage-venue-manager'),
			'expired' => __('Direct event grant marked expired by operator.', 'backstage-venue-manager'),
			'revoked' => __('Direct event grant revoked by operator.', 'backstage-venue-manager'),
		);
		return $map[$status] ?? __('Direct event grant updated by operator.', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_ticketing_claims_time_in_range')) {
	function vms_ticketing_claims_time_in_range(string $datetime, string $from = '', string $to = ''): bool
	{
		$datetime = trim($datetime);
		if ($datetime === '') {
			return false;
		}
		$point = strtotime($datetime);
		if (!$point) {
			return false;
		}

		$from = trim($from);
		if ($from !== '') {
			$from_ts = strtotime($from . ' 00:00:00');
			if ($from_ts && $point < $from_ts) {
				return false;
			}
		}

		$to = trim($to);
		if ($to !== '') {
			$to_ts = strtotime($to . ' 23:59:59');
			if ($to_ts && $point > $to_ts) {
				return false;
			}
		}

		return true;
	}
}

if (!function_exists('vms_ticketing_claims_reservation_usage_map')) {
	/**
	 * @return array<int,array<string,int>>
	 */
	function vms_ticketing_claims_reservation_usage_map(int $event_id): array
	{
		$event_id = absint($event_id);
		if ($event_id <= 0) {
			return array();
		}

		global $wpdb;
		$table = vms_ticketing_claims_table_reservations();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT direct_grant_id, status, COUNT(1) AS cnt
				 FROM {$table}
				 WHERE event_id = %d AND direct_grant_id > 0
				 GROUP BY direct_grant_id, status",
				$event_id
			),
			ARRAY_A
		);

		$map = array();
		foreach ((array) $rows as $row) {
			$grant_id = absint($row['direct_grant_id'] ?? 0);
			$status = sanitize_key((string) ($row['status'] ?? ''));
			$cnt = max(0, absint($row['cnt'] ?? 0));
			if ($grant_id <= 0 || $status === '') {
				continue;
			}
			if (!isset($map[$grant_id])) {
				$map[$grant_id] = array();
			}
			$map[$grant_id][$status] = $cnt;
		}
		return $map;
	}
}

if (!function_exists('vms_ticketing_claims_log_admin_action')) {
	/**
	 * @param array<string,mixed> $args
	 */
	function vms_ticketing_claims_log_admin_action(array $args): int
	{
		if (!function_exists('vms_ticketing_claims_log_result')) {
			return 0;
		}

		$actor_user_id = absint($args['actor_user_id'] ?? get_current_user_id());
		$target_user_id = absint($args['target_user_id'] ?? 0);
		$assignee_email = sanitize_email((string) ($args['assignee_email'] ?? ''));
		if ($assignee_email === '' && $target_user_id > 0) {
			$user = get_user_by('id', $target_user_id);
			if ($user instanceof WP_User) {
				$assignee_email = sanitize_email((string) $user->user_email);
			}
		}

		$context = isset($args['context']) && is_array($args['context']) ? $args['context'] : array();
		$context['source'] = 'admin_operator';
		$context['actor_user_id'] = $actor_user_id;

		return vms_ticketing_claims_log_result(array(
			'event_id' => absint($args['event_id'] ?? 0),
			'ticket_product_id' => absint($args['ticket_product_id'] ?? 0),
			'ticket_key' => sanitize_key((string) ($args['ticket_key'] ?? '')),
			'buyer_user_id' => $actor_user_id,
			'assignee_user_id' => $target_user_id,
			'assignee_email' => $assignee_email,
			'rule_path' => 'admin_action',
			'direct_grant_id' => absint($args['direct_grant_id'] ?? 0),
			'result' => sanitize_key((string) ($args['result'] ?? 'success')),
			'reason_code' => sanitize_key((string) ($args['reason_code'] ?? 'manual_repair')),
			'message' => sanitize_text_field((string) ($args['message'] ?? '')),
			'context' => $context,
		));
	}
}

if (!function_exists('vms_ticketing_claims_render_event_plan_metabox')) {
	function vms_ticketing_claims_render_event_plan_metabox(WP_Post $post): void
	{
		if (!vms_ticketing_claims_current_user_can_manage()) {
			echo '<p>' . esc_html__('You do not have permission to manage credential claims.', 'backstage-venue-manager') . '</p>';
			return;
		}

		$ctx = vms_ticketing_claims_event_context((int) $post->ID);
		$event_id = absint($ctx['event_id'] ?? 0);
		$event_title = sanitize_text_field((string) ($ctx['event_title'] ?? ''));
		$ticket_options = vms_ticketing_claims_event_ticket_options((int) $post->ID, $event_id);
		$program_options = vms_ticketing_claims_get_program_options();

		$search_q = isset($_GET['vms_claim_lookup']) ? sanitize_text_field((string) wp_unslash($_GET['vms_claim_lookup'])) : '';
		$selected_user_id = isset($_GET['vms_claim_user_id']) ? absint($_GET['vms_claim_user_id']) : 0;
		$selected_user = $selected_user_id > 0 ? get_user_by('id', $selected_user_id) : null;
		if (!($selected_user instanceof WP_User)) {
			$selected_user = null;
		}
		$search_results = ($search_q !== '') ? vms_ticketing_claims_search_users($search_q, 20) : array();

		$grants = array();
		if ($event_id > 0 && function_exists('vms_ticketing_claims_get_direct_grants')) {
			$grants = vms_ticketing_claims_get_direct_grants(array(
				'event_id' => $event_id,
				'status' => 'any',
				'limit' => 250,
			));
		}
		$reservation_usage = vms_ticketing_claims_reservation_usage_map($event_id);
		$reservation_filter_status = isset($_GET['vms_claim_res_status']) ? sanitize_key((string) wp_unslash($_GET['vms_claim_res_status'])) : 'reserved';
		if ($reservation_filter_status === '') {
			$reservation_filter_status = 'reserved';
		}
		$reservation_filter_email = isset($_GET['vms_claim_res_email']) ? sanitize_email((string) wp_unslash($_GET['vms_claim_res_email'])) : '';
		$reservations = array();
		if ($event_id > 0 && function_exists('vms_ticketing_claims_get_reservations')) {
			$reservations = vms_ticketing_claims_get_reservations(array(
				'event_id' => $event_id,
				'status' => $reservation_filter_status === 'any' ? '' : $reservation_filter_status,
				'assignee_email' => $reservation_filter_email,
				'limit' => 120,
			));
		}

		$log_filter_result = isset($_GET['vms_claim_log_result']) ? sanitize_key((string) wp_unslash($_GET['vms_claim_log_result'])) : '';
		$log_filter_email = isset($_GET['vms_claim_log_email']) ? sanitize_email((string) wp_unslash($_GET['vms_claim_log_email'])) : '';
		$log_filter_rule_path = isset($_GET['vms_claim_log_rule']) ? sanitize_key((string) wp_unslash($_GET['vms_claim_log_rule'])) : '';
		$logs = array();
		if ($event_id > 0 && function_exists('vms_ticketing_claims_get_logs')) {
			$logs = vms_ticketing_claims_get_logs(array(
				'event_id' => $event_id,
				'result' => $log_filter_result,
				'assignee_email' => $log_filter_email,
				'rule_path' => $log_filter_rule_path,
				'limit' => 120,
			));
		}

		$lookup_form_id = vms_ticketing_claims_event_metabox_form_id((int) $post->ID, 'lookup');
		vms_ticketing_claims_event_metabox_register_form(
			$lookup_form_id,
			'get',
			vms_ticketing_claims_event_edit_url((int) $post->ID)
		);
		$grant_form_id = vms_ticketing_claims_event_metabox_form_id((int) $post->ID, 'grant-create');
		$grant_hidden_fields = array(
			'action' => 'vms_ticketing_claims_create_grant',
			'event_plan_id' => (string) $post->ID,
			'event_id' => (string) $event_id,
			'_wpnonce' => wp_create_nonce('vms_ticketing_claims_create_grant'),
		);
		if ($selected_user instanceof WP_User) {
			$grant_hidden_fields['user_id'] = (string) $selected_user->ID;
		}
		vms_ticketing_claims_event_metabox_register_form(
			$grant_form_id,
			'post',
			admin_url('admin-post.php'),
			$grant_hidden_fields
		);
		$reservation_filter_form_id = vms_ticketing_claims_event_metabox_form_id((int) $post->ID, 'reservation-filter');
		vms_ticketing_claims_event_metabox_register_form(
			$reservation_filter_form_id,
			'get',
			vms_ticketing_claims_event_edit_url((int) $post->ID)
		);
		$log_filter_form_id = vms_ticketing_claims_event_metabox_form_id((int) $post->ID, 'log-filter');
		vms_ticketing_claims_event_metabox_register_form(
			$log_filter_form_id,
			'get',
			vms_ticketing_claims_event_edit_url((int) $post->ID)
		);

		echo '<div class="vms-claims-admin" data-vms-tour="claims.grants.help">';
		echo '<p class="description">' . esc_html__('Create and manage direct event grants for eligible accounts, with auditable status changes.', 'backstage-venue-manager') . '</p>';

		if ($event_id <= 0) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__('This Event Plan is not linked to a calendar event yet. Link or create the event before managing direct grants.', 'backstage-venue-manager') . '</p></div>';
		} else {
			$event_label = $event_title !== '' ? $event_title : ('#' . $event_id);
			echo '<p><strong>' . esc_html__('Linked Event:', 'backstage-venue-manager') . '</strong> ' . esc_html($event_label) . ' <span class="vms-claims-meta">(' . esc_html__('Event ID', 'backstage-venue-manager') . ': ' . esc_html((string) $event_id) . ')</span></p>';
		}

		echo '<hr />';
		echo '<h4 data-vms-tour="claims.grants.lookup">' . esc_html__('Find Eligible Account', 'backstage-venue-manager') . '</h4>';
		echo '<p>';
		echo '<input type="text" class="regular-text" name="vms_claim_lookup" value="' . esc_attr($search_q) . '" placeholder="' . esc_attr__('Search by email, login, or name', 'backstage-venue-manager') . '" form="' . esc_attr($lookup_form_id) . '" />';
		echo ' <button type="submit" class="button" form="' . esc_attr($lookup_form_id) . '">' . esc_html__('Search', 'backstage-venue-manager') . '</button>';
		if ($search_q !== '' || $selected_user) {
			$clear_url = vms_ticketing_claims_event_edit_url((int) $post->ID);
			echo ' <a class="button-link" href="' . esc_url($clear_url) . '">' . esc_html__('Clear', 'backstage-venue-manager') . '</a>';
		}
		echo '</p>';

		if ($search_q !== '') {
			if (!empty($search_results)) {
				echo '<table class="widefat striped vms-claims-search-table">';
				echo '<thead><tr><th>' . esc_html__('Account', 'backstage-venue-manager') . '</th><th>' . esc_html__('Email', 'backstage-venue-manager') . '</th><th>' . esc_html__('Action', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
				foreach ($search_results as $candidate) {
					$pick_url = vms_ticketing_claims_event_edit_url((int) $post->ID, array(
						'vms_claim_lookup' => $search_q,
						'vms_claim_user_id' => (int) $candidate->ID,
					));
					echo '<tr>';
					echo '<td>' . esc_html((string) $candidate->display_name) . ' <span class="vms-claims-meta">#' . esc_html((string) $candidate->ID) . '</span></td>';
					echo '<td>' . esc_html((string) $candidate->user_email) . '</td>';
					echo '<td><a class="button button-small" href="' . esc_url($pick_url) . '">' . esc_html__('Use Account', 'backstage-venue-manager') . '</a></td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			} else {
				echo '<p class="vms-claims-empty">' . esc_html__('No matching account was found.', 'backstage-venue-manager') . '</p>';
			}
		}

		echo '<hr />';
		echo '<h4 data-vms-tour="claims.grants.create">' . esc_html__('Create Event Benefit Grant', 'backstage-venue-manager') . '</h4>';
		if ($selected_user instanceof WP_User) {
			echo '<p><strong>' . esc_html__('Selected account:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $selected_user->display_name) . ' &lt;' . esc_html((string) $selected_user->user_email) . '&gt; <span class="vms-claims-meta">#' . esc_html((string) $selected_user->ID) . '</span></p>';
		} else {
			echo '<p class="description">' . esc_html__('Select an account above, or enter an exact account email/login below.', 'backstage-venue-manager') . '</p>';
		}

		echo '<div class="vms-claims-grant-form" data-vms-tour="claims.grants.form">';
		echo '<table class="form-table" role="presentation"><tbody>';
		if (!($selected_user instanceof WP_User)) {
			echo '<tr><th scope="row"><label for="vms-claim-user-identity">' . esc_html__('Account Email or Login', 'backstage-venue-manager') . '</label></th><td><input type="text" id="vms-claim-user-identity" name="user_identity" class="regular-text" required form="' . esc_attr($grant_form_id) . '"></td></tr>';
		}
		echo '<tr><th scope="row"><label for="vms-claim-grant-type">' . esc_html__('Grant Type', 'backstage-venue-manager') . '</label></th><td><select id="vms-claim-grant-type" name="grant_type" form="' . esc_attr($grant_form_id) . '">';
			foreach (vms_ticketing_claims_grant_type_options() as $grant_type_key => $grant_type_label) {
				$grant_type_key = sanitize_key((string) $grant_type_key);
				if ($grant_type_key === '') {
					continue;
				}
				echo '<option value="' . esc_attr($grant_type_key) . '"' . selected($grant_type_key, 'event_grant', false) . '>' . esc_html((string) $grant_type_label) . '</option>';
			}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="vms-claim-ticket-product">' . esc_html__('Ticket Restriction', 'backstage-venue-manager') . '</label></th><td><select id="vms-claim-ticket-product" name="ticket_product_id" form="' . esc_attr($grant_form_id) . '">';
		echo '<option value="0">' . esc_html__('Any qualifying ticket for this event', 'backstage-venue-manager') . '</option>';
		foreach ($ticket_options as $ticket) {
			$product_id = absint($ticket['product_id'] ?? 0);
			if ($product_id <= 0) {
				continue;
			}
			$label = sanitize_text_field((string) ($ticket['label'] ?? ('#' . $product_id)));
			$ticket_key = sanitize_key((string) ($ticket['ticket_key'] ?? ''));
			$suffix = $ticket_key !== '' ? (' [' . $ticket_key . ']') : '';
			echo '<option value="' . esc_attr((string) $product_id) . '">' . esc_html($label . $suffix) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="vms-claim-program">' . esc_html__('Credential Type Restriction', 'backstage-venue-manager') . '</label></th><td><select id="vms-claim-program" name="credential_program" form="' . esc_attr($grant_form_id) . '">';
		echo '<option value="">' . esc_html__('Any approved credential type', 'backstage-venue-manager') . '</option>';
		foreach ($program_options as $program_key => $program_label) {
			$key = sanitize_key((string) $program_key);
			if ($key === '') {
				continue;
			}
			echo '<option value="' . esc_attr($key) . '">' . esc_html((string) $program_label) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="vms-claim-initial-status">' . esc_html__('Initial Status', 'backstage-venue-manager') . '</label></th><td><select id="vms-claim-initial-status" name="initial_status" form="' . esc_attr($grant_form_id) . '">';
		foreach (vms_ticketing_claims_grant_status_options() as $status_key => $status_label) {
			$status_key = sanitize_key((string) $status_key);
			if ($status_key === '') {
				continue;
			}
			echo '<option value="' . esc_attr($status_key) . '"' . selected($status_key, 'active', false) . '>' . esc_html((string) $status_label) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="vms-claim-expiration-behavior">' . esc_html__('Expiration Behavior', 'backstage-venue-manager') . '</label></th><td><select id="vms-claim-expiration-behavior" name="expiration_behavior" form="' . esc_attr($grant_form_id) . '">';
		echo '<option value="none">' . esc_html__('No automatic expiration', 'backstage-venue-manager') . '</option>';
		echo '<option value="event_end">' . esc_html__('Expire after event end (operator reminder only)', 'backstage-venue-manager') . '</option>';
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="vms-claim-qty-limit">' . esc_html__('Usage Limit', 'backstage-venue-manager') . '</label></th><td><input type="number" min="0" step="1" id="vms-claim-qty-limit" name="qty_limit" value="1" class="small-text" form="' . esc_attr($grant_form_id) . '"> <span class="description">' . esc_html__('Use 0 for unlimited.', 'backstage-venue-manager') . '</span></td></tr>';
		echo '<tr><th scope="row"><label for="vms-claim-note">' . esc_html__('Operator Note / Reason', 'backstage-venue-manager') . '</label></th><td><input type="text" id="vms-claim-note" name="note" class="regular-text" maxlength="190" form="' . esc_attr($grant_form_id) . '"></td></tr>';
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary" form="' . esc_attr($grant_form_id) . '" ' . ($event_id <= 0 ? 'disabled="disabled"' : '') . '>' . esc_html__('Create Benefit Grant', 'backstage-venue-manager') . '</button></p>';
		echo '</div>';

		echo '<hr />';
		echo '<h4 data-vms-tour="claims.grants.browser">' . esc_html__('Current Event Grants', 'backstage-venue-manager') . '</h4>';

		if (empty($grants)) {
			echo '<p class="vms-claims-empty">' . esc_html__('No direct grants found for this event yet.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<table class="widefat striped vms-claims-grants-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__('Account', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Grant Type', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Ticket Context', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Quantity / Used', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Status', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Created', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Created By', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Internal Note', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
			echo '</tr></thead><tbody>';

			foreach ($grants as $grant) {
				$grant_id = absint($grant['id'] ?? 0);
				$user_id = absint($grant['user_id'] ?? 0);
				$user = $user_id > 0 ? get_user_by('id', $user_id) : null;
				$user_name = ($user instanceof WP_User) ? (string) $user->display_name : __('Unknown account', 'backstage-venue-manager');
				$user_email = ($user instanceof WP_User) ? (string) $user->user_email : '';
				$grant_type = sanitize_key((string) ($grant['grant_type'] ?? 'event_ticket_eligibility'));
				$grant_type_label = function_exists('vms_ticketing_claims_grant_type_label')
					? vms_ticketing_claims_grant_type_label($grant_type)
					: (($grant_type === 'event_free_admit') ? __('Free Admission', 'backstage-venue-manager') : __('Event Grant', 'backstage-venue-manager'));
				$ticket_product_id = absint($grant['ticket_product_id'] ?? 0);
				$ticket_key = sanitize_key((string) ($grant['ticket_key'] ?? ''));
				$ticket_label = $ticket_product_id > 0 ? sanitize_text_field((string) get_the_title($ticket_product_id)) : __('Any ticket', 'backstage-venue-manager');
				$ticket_suffix = $ticket_key !== '' ? (' [' . $ticket_key . ']') : '';
				$qty_limit = max(0, absint($grant['qty_limit'] ?? 0));
				$qty_used = max(0, absint($grant['qty_used'] ?? 0));
				$res_counts = $reservation_usage[$grant_id] ?? array();
				$status = function_exists('vms_ticketing_claims_resolve_grant_status')
					? vms_ticketing_claims_resolve_grant_status($grant, is_array($res_counts) ? $res_counts : null)
					: sanitize_key((string) ($grant['status'] ?? 'active'));
				$status_label = function_exists('vms_ticketing_claims_grant_status_label')
					? vms_ticketing_claims_grant_status_label($status)
					: ($status !== '' ? ucwords(str_replace('_', ' ', $status)) : __('Unknown', 'backstage-venue-manager'));
				$status_help = function_exists('vms_ticketing_claims_grant_status_explanation')
					? vms_ticketing_claims_grant_status_explanation($status)
					: '';
				$created_at = sanitize_text_field((string) ($grant['created_at'] ?? ''));
				$created_by = absint($grant['created_by'] ?? 0);
				$created_by_user = $created_by > 0 ? get_user_by('id', $created_by) : null;
				$created_by_label = ($created_by_user instanceof WP_User) ? (string) $created_by_user->display_name : ($created_by > 0 ? ('#' . $created_by) : __('System', 'backstage-venue-manager'));
				$note = sanitize_text_field((string) ($grant['note'] ?? ''));
				$res_state = array();
				foreach ($res_counts as $res_status => $cnt) {
					$res_state[] = ucwords(str_replace('_', ' ', sanitize_key((string) $res_status))) . ': ' . absint($cnt);
				}

				echo '<tr>';
				echo '<td><strong>' . esc_html($user_name) . '</strong>';
				if ($user_email !== '') {
					echo '<br><span class="vms-claims-meta">' . esc_html($user_email) . '</span>';
				}
				echo '</td>';
				echo '<td>' . esc_html($grant_type_label) . '</td>';
				echo '<td>' . esc_html($ticket_label . $ticket_suffix) . '</td>';
				echo '<td>' . esc_html((string) $qty_limit) . ' / ' . esc_html((string) $qty_used);
				if (!empty($res_state)) {
					echo '<br><span class="vms-claims-meta">' . esc_html(implode(' | ', $res_state)) . '</span>';
				}
				echo '</td>';
				echo '<td><span class="vms-claims-status vms-claims-status-' . esc_attr($status) . '">' . esc_html($status_label) . '</span>';
				if ($status_help !== '') {
					echo '<br><span class="vms-claims-meta">' . esc_html($status_help) . '</span>';
				}
				echo '</td>';
				echo '<td>' . esc_html($created_at !== '' ? $created_at : '—') . '</td>';
				echo '<td>' . esc_html($created_by_label) . '</td>';
				echo '<td>';
				$note_form_id = vms_ticketing_claims_event_metabox_form_id((int) $post->ID, 'grant-note-' . $grant_id);
				vms_ticketing_claims_event_metabox_register_form(
					$note_form_id,
					'post',
					admin_url('admin-post.php'),
					array(
						'action' => 'vms_ticketing_claims_update_grant_note',
						'event_plan_id' => (string) $post->ID,
						'grant_id' => (string) $grant_id,
						'_wpnonce' => wp_create_nonce('vms_ticketing_claims_update_grant_note_' . $grant_id),
					)
				);
				echo '<div class="vms-claims-note-form">';
				echo '<input type="text" name="note" value="' . esc_attr($note) . '" class="regular-text" maxlength="190" form="' . esc_attr($note_form_id) . '" />';
				echo '<button type="submit" class="button button-small" form="' . esc_attr($note_form_id) . '">' . esc_html__('Save Note', 'backstage-venue-manager') . '</button>';
				echo '</div>';
				echo '</td>';
				echo '<td>';
				$status_form_id = vms_ticketing_claims_event_metabox_form_id((int) $post->ID, 'grant-status-' . $grant_id);
				vms_ticketing_claims_event_metabox_register_form(
					$status_form_id,
					'post',
					admin_url('admin-post.php'),
					array(
						'action' => 'vms_ticketing_claims_set_grant_status',
						'event_plan_id' => (string) $post->ID,
						'grant_id' => (string) $grant_id,
						'_wpnonce' => wp_create_nonce('vms_ticketing_claims_set_grant_status_' . $grant_id),
					)
				);
				echo '<div class="vms-claims-action-form">';
				$row_status_options = vms_ticketing_claims_grant_next_status_options($status);
				echo '<select name="new_status" class="small-text" form="' . esc_attr($status_form_id) . '">';
				foreach ($row_status_options as $status_key => $status_option_label) {
					$status_key = sanitize_key((string) $status_key);
					if ($status_key === '') {
						continue;
					}
					echo '<option value="' . esc_attr($status_key) . '" ' . selected($status, $status_key, false) . '>' . esc_html((string) $status_option_label) . '</option>';
				}
				echo '</select> ';
				echo '<button type="submit" class="button button-small" form="' . esc_attr($status_form_id) . '">' . esc_html__('Update Status', 'backstage-venue-manager') . '</button>';
				echo '</div>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '<hr />';
		echo '<h4>' . esc_html__('Reservation Repair Queue', 'backstage-venue-manager') . '</h4>';
		echo '<div class="vms-claims-inline-filters">';
		echo '<label>' . esc_html__('Status', 'backstage-venue-manager') . ' ';
		echo '<select name="vms_claim_res_status" form="' . esc_attr($reservation_filter_form_id) . '">';
		$status_options = array(
			'reserved' => __('Reserved', 'backstage-venue-manager'),
			'released' => __('Released', 'backstage-venue-manager'),
			'consumed' => __('Consumed', 'backstage-venue-manager'),
			'any' => __('Any', 'backstage-venue-manager'),
		);
		foreach ($status_options as $status_key => $status_label) {
			echo '<option value="' . esc_attr($status_key) . '" ' . selected($reservation_filter_status, $status_key, false) . '>' . esc_html($status_label) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__('Assignee Email', 'backstage-venue-manager') . ' <input type="text" name="vms_claim_res_email" value="' . esc_attr($reservation_filter_email) . '" class="regular-text" form="' . esc_attr($reservation_filter_form_id) . '"></label> ';
		echo '<button type="submit" class="button" form="' . esc_attr($reservation_filter_form_id) . '">' . esc_html__('Filter', 'backstage-venue-manager') . '</button>';
		echo '</div>';

		if (empty($reservations)) {
			echo '<p class="vms-claims-empty">' . esc_html__('No reservations found for the current filter.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<table class="widefat striped vms-claims-reservations-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__('Created', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Assignee', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Buyer', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Ticket Context', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Status', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Order/Cart Context', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
			echo '</tr></thead><tbody>';

			foreach ($reservations as $reservation) {
				$reservation_id = absint($reservation['id'] ?? 0);
				$reservation_status = sanitize_key((string) ($reservation['status'] ?? ''));
				$assignee_user_id = absint($reservation['assignee_user_id'] ?? 0);
				$assignee_email = sanitize_email((string) ($reservation['assignee_email'] ?? ''));
				$buyer_user_id = absint($reservation['buyer_user_id'] ?? 0);
				$ticket_product_id = absint($reservation['ticket_product_id'] ?? 0);
				$ticket_key = sanitize_key((string) ($reservation['ticket_key'] ?? ''));
				$order_id = absint($reservation['order_id'] ?? 0);
				$cart_item_key = sanitize_text_field((string) ($reservation['cart_item_key'] ?? ''));
				$session_key = sanitize_text_field((string) ($reservation['session_key'] ?? ''));
				$token = sanitize_text_field((string) ($reservation['reservation_token'] ?? ''));

				echo '<tr>';
				echo '<td>' . esc_html((string) ($reservation['created_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_user_display($assignee_user_id, $assignee_email)) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_user_display($buyer_user_id, '—')) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label($ticket_product_id, $ticket_key)) . '</td>';
				echo '<td><span class="vms-claims-status vms-claims-status-' . esc_attr($reservation_status) . '">' . esc_html(ucwords(str_replace('_', ' ', $reservation_status))) . '</span></td>';
				echo '<td>';
				echo '<span class="vms-claims-meta">' . esc_html__('Order', 'backstage-venue-manager') . ':</span> ' . esc_html($order_id > 0 ? ('#' . $order_id) : '—');
				echo '<br><span class="vms-claims-meta">' . esc_html__('Cart', 'backstage-venue-manager') . ':</span> ' . esc_html($cart_item_key !== '' ? $cart_item_key : '—');
				echo '<br><span class="vms-claims-meta">' . esc_html__('Session', 'backstage-venue-manager') . ':</span> ' . esc_html($session_key !== '' ? $session_key : '—');
				echo '<br><span class="vms-claims-meta">' . esc_html__('Token', 'backstage-venue-manager') . ':</span> ' . esc_html($token !== '' ? $token : '—');
				echo '</td>';
				echo '<td>';
				if ($reservation_status === 'reserved') {
					$release_form_id = vms_ticketing_claims_event_metabox_form_id((int) $post->ID, 'reservation-release-' . $reservation_id);
					vms_ticketing_claims_event_metabox_register_form(
						$release_form_id,
						'post',
						admin_url('admin-post.php'),
						array(
							'action' => 'vms_ticketing_claims_release_reservation',
							'event_plan_id' => (string) $post->ID,
							'reservation_id' => (string) $reservation_id,
							'_wpnonce' => wp_create_nonce('vms_ticketing_claims_release_reservation_' . $reservation_id),
						)
					);
					echo '<div class="vms-claims-action-form">';
					echo '<button type="submit" class="button button-small" form="' . esc_attr($release_form_id) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Release this reservation? History will be preserved in the activity log.', 'backstage-venue-manager'))) . ');">' . esc_html__('Release Reservation', 'backstage-venue-manager') . '</button>';
					echo '</div>';
				} else {
					echo '<span class="vms-claims-meta">' . esc_html__('No repair action available.', 'backstage-venue-manager') . '</span>';
				}
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '<hr />';
		echo '<h4>' . esc_html__('Event Claim Activity', 'backstage-venue-manager') . '</h4>';
		echo '<div class="vms-claims-inline-filters">';
		echo '<label>' . esc_html__('Result', 'backstage-venue-manager') . ' ';
		echo '<select name="vms_claim_log_result" form="' . esc_attr($log_filter_form_id) . '">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		echo '<option value="success" ' . selected($log_filter_result, 'success', false) . '>' . esc_html__('Success', 'backstage-venue-manager') . '</option>';
		echo '<option value="failure" ' . selected($log_filter_result, 'failure', false) . '>' . esc_html__('Failure', 'backstage-venue-manager') . '</option>';
		echo '</select></label> ';
		echo '<label>' . esc_html__('Assignee Email', 'backstage-venue-manager') . ' <input type="text" name="vms_claim_log_email" value="' . esc_attr($log_filter_email) . '" class="regular-text" form="' . esc_attr($log_filter_form_id) . '"></label> ';
		echo '<label>' . esc_html__('Rule Path', 'backstage-venue-manager') . ' ';
		echo '<select name="vms_claim_log_rule" form="' . esc_attr($log_filter_form_id) . '">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		echo '<option value="credential_program" ' . selected($log_filter_rule_path, 'credential_program', false) . '>' . esc_html__('Credential Program', 'backstage-venue-manager') . '</option>';
		echo '<option value="event_direct_grant" ' . selected($log_filter_rule_path, 'event_direct_grant', false) . '>' . esc_html__('Direct Grant', 'backstage-venue-manager') . '</option>';
		echo '<option value="admin_action" ' . selected($log_filter_rule_path, 'admin_action', false) . '>' . esc_html__('Manual Operator Action', 'backstage-venue-manager') . '</option>';
		echo '</select></label> ';
		echo '<button type="submit" class="button" form="' . esc_attr($log_filter_form_id) . '">' . esc_html__('Filter', 'backstage-venue-manager') . '</button>';
		echo '</div>';

		if (empty($logs)) {
			echo '<p class="vms-claims-empty">' . esc_html__('No claim activity found for this event and filter.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<table class="widefat striped vms-claims-logs-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__('Timestamp (UTC)', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Assignee', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Buyer/Actor', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Ticket', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Rule Path', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Result', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Reason', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Source', 'backstage-venue-manager') . '</th>';
			echo '</tr></thead><tbody>';
			foreach ($logs as $log_row) {
				$assignee_user_id = absint($log_row['assignee_user_id'] ?? 0);
				$assignee_email = sanitize_email((string) ($log_row['assignee_email'] ?? ''));
				$buyer_user_id = absint($log_row['buyer_user_id'] ?? 0);
				$result = sanitize_key((string) ($log_row['result'] ?? ''));
				$reason_code = sanitize_key((string) ($log_row['reason_code'] ?? ''));
				$message = sanitize_text_field((string) ($log_row['message'] ?? ''));
				$rule_path = sanitize_key((string) ($log_row['rule_path'] ?? ''));
				$context = function_exists('vms_ticketing_claims_decode_context_json')
					? vms_ticketing_claims_decode_context_json((string) ($log_row['context_json'] ?? ''))
					: array();
				$source = sanitize_text_field((string) ($context['source'] ?? ''));
				$source_label = $source !== '' ? $source : '—';
				$reason_label = function_exists('vms_ticketing_claims_friendly_reason')
					? vms_ticketing_claims_friendly_reason($reason_code, $message)
					: ($message !== '' ? $message : $reason_code);

				echo '<tr>';
				echo '<td>' . esc_html((string) ($log_row['created_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_user_display($assignee_user_id, $assignee_email)) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_user_display($buyer_user_id, '—')) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label(absint($log_row['ticket_product_id'] ?? 0), sanitize_key((string) ($log_row['ticket_key'] ?? '')))) . '</td>';
				echo '<td>' . esc_html($rule_path !== '' ? $rule_path : '—') . '</td>';
				echo '<td><span class="vms-claims-status vms-claims-status-' . esc_attr($result) . '">' . esc_html($result !== '' ? ucwords(str_replace('_', ' ', $result)) : '—') . '</span></td>';
				echo '<td>' . esc_html($reason_label) . '</td>';
				echo '<td>' . esc_html($source_label) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<p><a class="button" href="' . esc_url(vms_ticketing_claims_admin_page_url(array('event_id' => $event_id))) . '">' . esc_html__('Open Full Claims Activity Screen', 'backstage-venue-manager') . '</a></p>';

		echo '</div>';
	}
}

if (!function_exists('vms_ticketing_claims_add_event_plan_metabox')) {
	function vms_ticketing_claims_add_event_plan_metabox(): void
	{
		add_meta_box(
			'vms_ticketing_claims_ops',
			__('Credential Grants', 'backstage-venue-manager'),
			'vms_ticketing_claims_render_event_plan_metabox',
			'vms_event_plan',
			'normal',
			'default'
		);
	}
}
add_action('add_meta_boxes_vms_event_plan', 'vms_ticketing_claims_add_event_plan_metabox', 26);

if (!function_exists('vms_ticketing_claims_enqueue_admin_assets')) {
	function vms_ticketing_claims_enqueue_admin_assets(): void
	{
		if (!vms_ticketing_claims_is_event_plan_edit_screen() && !vms_ticketing_claims_is_admin_page()) {
			return;
		}
		wp_enqueue_style(
			'vms-ticketing-claims-admin',
			VMS_PLUGIN_URL . 'assets/css/vms-ticketing-claims-admin.css',
			array('vms-admin'),
			function_exists('vms_asset_version') ? vms_asset_version() : (defined('VMS_VERSION') ? (string) VMS_VERSION : '')
		);
	}
}
add_action('admin_enqueue_scripts', 'vms_ticketing_claims_enqueue_admin_assets', 35);

if (!function_exists('vms_ticketing_claims_handle_create_grant')) {
	function vms_ticketing_claims_handle_create_grant(): void
	{
		if (!vms_ticketing_claims_current_user_can_manage()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_ticketing_claims_create_grant');

		$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
		$event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
		if ($event_id <= 0) {
			$ctx = vms_ticketing_claims_event_context($event_plan_id);
			$event_id = absint($ctx['event_id'] ?? 0);
		}
		if ($event_plan_id <= 0 || $event_id <= 0) {
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'event_missing')));
			exit;
		}

		$user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
		$user_identity = isset($_POST['user_identity']) ? sanitize_text_field((string) wp_unslash($_POST['user_identity'])) : '';
		$user = vms_ticketing_claims_resolve_user($user_identity, $user_id);
		if (!($user instanceof WP_User)) {
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'user_not_found')));
			exit;
		}

		$ticket_product_id = isset($_POST['ticket_product_id']) ? absint($_POST['ticket_product_id']) : 0;
		$ticket_key = '';
		if ($ticket_product_id > 0) {
			$ticket_key = sanitize_key((string) get_post_meta($ticket_product_id, '_vms_ticket_key', true));
			if ($ticket_key === '' && function_exists('vms_ticketing_v2_product_meta_key')) {
				$ticket_key = sanitize_key((string) get_post_meta($ticket_product_id, vms_ticketing_v2_product_meta_key('ticketing_ticket_key'), true));
			}
		}

		$grant_type = isset($_POST['grant_type']) ? sanitize_key((string) wp_unslash($_POST['grant_type'])) : 'event_grant';
		$allowed_grant_types = function_exists('vms_ticketing_claims_allowed_grant_types')
			? (array) vms_ticketing_claims_allowed_grant_types()
			: array('event_ticket_eligibility', 'event_free_admit', 'credential_benefit_override', 'event_grant');
		if (!in_array($grant_type, $allowed_grant_types, true)) {
			$grant_type = 'event_grant';
		}
		$credential_program = isset($_POST['credential_program']) ? sanitize_key((string) wp_unslash($_POST['credential_program'])) : '';
		$initial_status = isset($_POST['initial_status']) ? sanitize_key((string) wp_unslash($_POST['initial_status'])) : 'active';
		$allowed_statuses = function_exists('vms_ticketing_claims_allowed_grant_statuses')
			? (array) vms_ticketing_claims_allowed_grant_statuses()
			: array('active', 'reserved', 'used', 'expired', 'revoked');
		if (!in_array($initial_status, $allowed_statuses, true)) {
			$initial_status = 'active';
		}
		$expiration_behavior = isset($_POST['expiration_behavior']) ? sanitize_key((string) wp_unslash($_POST['expiration_behavior'])) : 'none';
		if (!in_array($expiration_behavior, array('none', 'event_end'), true)) {
			$expiration_behavior = 'none';
		}
		$qty_limit = isset($_POST['qty_limit']) ? max(0, absint($_POST['qty_limit'])) : 1;
		$note = isset($_POST['note']) ? sanitize_text_field((string) wp_unslash($_POST['note'])) : '';

		$grant_id = function_exists('vms_ticketing_claims_create_direct_grant')
			? vms_ticketing_claims_create_direct_grant(array(
				'event_id' => $event_id,
				'user_id' => (int) $user->ID,
				'grant_type' => $grant_type,
				'ticket_product_id' => $ticket_product_id,
				'ticket_key' => $ticket_key,
				'credential_program' => $credential_program,
				'qty_limit' => $qty_limit,
				'status' => $initial_status,
				'note' => $note,
				'actor_user_id' => get_current_user_id(),
			))
			: 0;

		if ($grant_id > 0) {
			vms_ticketing_claims_log_admin_action(array(
				'event_id' => $event_id,
				'ticket_product_id' => $ticket_product_id,
				'ticket_key' => $ticket_key,
				'direct_grant_id' => $grant_id,
				'target_user_id' => (int) $user->ID,
				'assignee_email' => (string) $user->user_email,
				'reason_code' => 'grant_created',
				'message' => __('Direct event grant created by operator.', 'backstage-venue-manager'),
				'context' => array(
					'event_plan_id' => $event_plan_id,
					'previous_status' => '',
					'new_status' => $initial_status,
					'note' => $note,
					'grant_type' => $grant_type,
					'credential_program' => $credential_program,
					'qty_limit' => $qty_limit,
					'expiration_behavior' => $expiration_behavior,
				),
			));
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'grant_created')));
			exit;
		}

		wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'grant_create_failed')));
		exit;
	}
}
add_action('admin_post_vms_ticketing_claims_create_grant', 'vms_ticketing_claims_handle_create_grant');

if (!function_exists('vms_ticketing_claims_handle_update_grant_note')) {
	function vms_ticketing_claims_handle_update_grant_note(): void
	{
		if (!vms_ticketing_claims_current_user_can_manage()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}

		$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
		$grant_id = isset($_POST['grant_id']) ? absint($_POST['grant_id']) : 0;
		check_admin_referer('vms_ticketing_claims_update_grant_note_' . $grant_id);
		if ($grant_id <= 0) {
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'invalid_request')));
			exit;
		}

		$grant = function_exists('vms_ticketing_claims_get_direct_grant') ? vms_ticketing_claims_get_direct_grant($grant_id) : null;
		if (!$grant) {
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'grant_note_failed')));
			exit;
		}

		$note = isset($_POST['note']) ? sanitize_text_field((string) wp_unslash($_POST['note'])) : '';
		$ok = function_exists('vms_ticketing_claims_update_direct_grant_note')
			? vms_ticketing_claims_update_direct_grant_note($grant_id, $note, get_current_user_id())
			: false;

		if ($ok) {
			vms_ticketing_claims_log_admin_action(array(
				'event_id' => absint($grant['event_id'] ?? 0),
				'ticket_product_id' => absint($grant['ticket_product_id'] ?? 0),
				'ticket_key' => sanitize_key((string) ($grant['ticket_key'] ?? '')),
				'direct_grant_id' => $grant_id,
				'target_user_id' => absint($grant['user_id'] ?? 0),
				'reason_code' => 'grant_note_updated',
				'message' => __('Grant note updated by operator.', 'backstage-venue-manager'),
				'context' => array(
					'event_plan_id' => $event_plan_id,
					'previous_status' => sanitize_key((string) ($grant['status'] ?? '')),
					'new_status' => sanitize_key((string) ($grant['status'] ?? '')),
					'previous_note' => sanitize_text_field((string) ($grant['note'] ?? '')),
					'note' => $note,
				),
			));
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'grant_note_saved')));
			exit;
		}

		wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'grant_note_failed')));
		exit;
	}
}
add_action('admin_post_vms_ticketing_claims_update_grant_note', 'vms_ticketing_claims_handle_update_grant_note');

if (!function_exists('vms_ticketing_claims_handle_set_grant_status')) {
	function vms_ticketing_claims_handle_set_grant_status(): void
	{
		if (!vms_ticketing_claims_current_user_can_manage()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}

		$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
		$grant_id = isset($_POST['grant_id']) ? absint($_POST['grant_id']) : 0;
		check_admin_referer('vms_ticketing_claims_set_grant_status_' . $grant_id);
		if ($grant_id <= 0) {
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'invalid_request')));
			exit;
		}

		$grant = function_exists('vms_ticketing_claims_get_direct_grant') ? vms_ticketing_claims_get_direct_grant($grant_id) : null;
		if (!$grant) {
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'grant_status_failed')));
			exit;
		}

		$new_status = isset($_POST['new_status']) ? sanitize_key((string) wp_unslash($_POST['new_status'])) : '';
		$allowed_statuses = function_exists('vms_ticketing_claims_allowed_grant_statuses')
			? (array) vms_ticketing_claims_allowed_grant_statuses()
			: array('active', 'reserved', 'used', 'expired', 'revoked');
		if (!in_array($new_status, $allowed_statuses, true)) {
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'invalid_request')));
			exit;
		}

		$old_status = sanitize_key((string) ($grant['status'] ?? ''));
		$qty_used = max(0, absint($grant['qty_used'] ?? 0));

		if ($old_status === $new_status) {
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'grant_status_saved')));
			exit;
		}

		$ok = function_exists('vms_ticketing_claims_set_direct_grant_status')
			? vms_ticketing_claims_set_direct_grant_status($grant_id, $new_status, get_current_user_id())
			: false;

		if ($ok) {
			$reason_code = vms_ticketing_claims_grant_status_change_reason_code($new_status);
			vms_ticketing_claims_log_admin_action(array(
				'event_id' => absint($grant['event_id'] ?? 0),
				'ticket_product_id' => absint($grant['ticket_product_id'] ?? 0),
				'ticket_key' => sanitize_key((string) ($grant['ticket_key'] ?? '')),
				'direct_grant_id' => $grant_id,
				'target_user_id' => absint($grant['user_id'] ?? 0),
				'reason_code' => $reason_code,
				'message' => vms_ticketing_claims_grant_status_change_message($new_status),
				'context' => array(
					'event_plan_id' => $event_plan_id,
					'previous_status' => $old_status,
					'new_status' => $new_status,
					'note' => sanitize_text_field((string) ($grant['note'] ?? '')),
					'qty_used' => $qty_used,
				),
			));
			wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'grant_status_saved')));
			exit;
		}

		wp_safe_redirect(vms_ticketing_claims_event_edit_url($event_plan_id, array('vms_claim_notice' => 'grant_status_failed')));
		exit;
	}
}
add_action('admin_post_vms_ticketing_claims_set_grant_status', 'vms_ticketing_claims_handle_set_grant_status');

if (!function_exists('vms_ticketing_claims_redirect_after_post_action')) {
	function vms_ticketing_claims_redirect_after_post_action(int $event_plan_id, string $notice): void
	{
		$event_plan_id = absint($event_plan_id);
		$notice = sanitize_key($notice);

		$target = '';
		$referer = isset($_POST['_wp_http_referer']) ? wp_unslash((string) $_POST['_wp_http_referer']) : '';
		if (is_string($referer) && $referer !== '') {
			$target = $referer;
		}
		if ($target === '') {
			$target = $event_plan_id > 0 ? vms_ticketing_claims_event_edit_url($event_plan_id) : vms_ticketing_claims_admin_page_url();
		}
		$target = add_query_arg('vms_claim_notice', $notice, $target);
		wp_safe_redirect($target);
		exit;
	}
}

if (!function_exists('vms_ticketing_claims_handle_release_reservation')) {
	function vms_ticketing_claims_handle_release_reservation(): void
	{
		if (!vms_ticketing_claims_current_user_can_manage()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}

		$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
		$reservation_id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0;
		check_admin_referer('vms_ticketing_claims_release_reservation_' . $reservation_id);
		if ($reservation_id <= 0) {
			vms_ticketing_claims_redirect_after_post_action($event_plan_id, 'invalid_request');
		}

		$reservation = function_exists('vms_ticketing_claims_get_reservation') ? vms_ticketing_claims_get_reservation($reservation_id) : null;
		if (!$reservation) {
			vms_ticketing_claims_redirect_after_post_action($event_plan_id, 'reservation_release_failed');
		}

		$previous_status = sanitize_key((string) ($reservation['status'] ?? ''));
		$ok = function_exists('vms_ticketing_claims_release_reservation')
			? vms_ticketing_claims_release_reservation($reservation_id, get_current_user_id())
			: false;
		if (!$ok) {
			vms_ticketing_claims_redirect_after_post_action($event_plan_id, 'reservation_release_failed');
		}

		vms_ticketing_claims_log_admin_action(array(
			'event_id' => absint($reservation['event_id'] ?? 0),
			'ticket_product_id' => absint($reservation['ticket_product_id'] ?? 0),
			'ticket_key' => sanitize_key((string) ($reservation['ticket_key'] ?? '')),
			'direct_grant_id' => absint($reservation['direct_grant_id'] ?? 0),
			'target_user_id' => absint($reservation['assignee_user_id'] ?? 0),
			'assignee_email' => sanitize_email((string) ($reservation['assignee_email'] ?? '')),
			'reason_code' => 'reservation_released',
			'message' => __('Reservation released by operator.', 'backstage-venue-manager'),
			'context' => array(
				'event_plan_id' => $event_plan_id,
				'reservation_id' => $reservation_id,
				'reservation_token' => sanitize_text_field((string) ($reservation['reservation_token'] ?? '')),
				'previous_status' => $previous_status,
				'new_status' => 'released',
				'order_id' => absint($reservation['order_id'] ?? 0),
				'cart_item_key' => sanitize_text_field((string) ($reservation['cart_item_key'] ?? '')),
				'session_key' => sanitize_text_field((string) ($reservation['session_key'] ?? '')),
				'reservation_status' => 'released',
			),
		));

		vms_ticketing_claims_redirect_after_post_action($event_plan_id, 'reservation_released');
	}
}
add_action('admin_post_vms_ticketing_claims_release_reservation', 'vms_ticketing_claims_handle_release_reservation');

if (!function_exists('vms_ticketing_claims_register_admin_menu')) {
	function vms_ticketing_claims_register_admin_menu(): void
	{
		if (!is_admin()) {
			return;
		}
		add_submenu_page(
			'vms-dashboard',
			__('Credential Claims', 'backstage-venue-manager'),
			__('Credential Claims', 'backstage-venue-manager'),
			vms_ticketing_claims_manage_capability(),
			vms_ticketing_claims_menu_slug(),
			'vms_ticketing_claims_render_admin_page'
		);
	}
}
add_action('admin_menu', 'vms_ticketing_claims_register_admin_menu', 27);

if (!function_exists('vms_ticketing_claims_get_verified_programs_for_user')) {
	/**
	 * @return string[]
	 */
	function vms_ticketing_claims_get_verified_programs_for_user(int $user_id): array
	{
		$user_id = absint($user_id);
		if ($user_id <= 0) {
			return array();
		}
		if (function_exists('vms_ticketing_get_user_verified_programs')) {
			return array_values(array_unique(array_filter(array_map('sanitize_key', (array) vms_ticketing_get_user_verified_programs($user_id)))));
		}
		$raw = get_user_meta($user_id, 'vms_verified_programs', true);
		$list = is_array($raw) ? $raw : array();
		return array_values(array_unique(array_filter(array_map('sanitize_key', $list))));
	}
}

if (!function_exists('vms_ticketing_claims_get_event_verified_ticket_contexts')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_ticketing_claims_get_event_verified_ticket_contexts(int $event_id): array
	{
		$event_id = absint($event_id);
		if ($event_id <= 0 || !function_exists('vms_ticketing_v2_resolve_verified_ticket_context')) {
			return array();
		}

		$product_ids = get_posts(array(
			'post_type' => 'product',
			'post_status' => array('publish', 'private', 'draft', 'pending'),
			'fields' => 'ids',
			'posts_per_page' => 300,
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => '_vms_ticket_event_id',
					'value' => $event_id,
					'compare' => '=',
					'type' => 'NUMERIC',
				),
				array(
					'key' => '_tribe_wooticket_for_event',
					'value' => $event_id,
					'compare' => '=',
					'type' => 'NUMERIC',
				),
			),
		));

		$out = array();
		foreach ((array) $product_ids as $product_id_raw) {
			$product_id = absint($product_id_raw);
			if ($product_id <= 0) {
				continue;
			}
			$ctx = (array) vms_ticketing_v2_resolve_verified_ticket_context($product_id);
			if (sanitize_key((string) ($ctx['visibility_mode'] ?? '')) !== 'verified') {
				continue;
			}
			$ctx['product_id'] = $product_id;
			$ctx['product_title'] = sanitize_text_field((string) get_the_title($product_id));
			$out[] = $ctx;
		}

		return $out;
	}
}

if (!function_exists('vms_ticketing_claims_build_inspector_payload')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_ticketing_claims_build_inspector_payload(string $email, int $event_id = 0): array
	{
		$email = sanitize_email($email);
		$event_id = absint($event_id);
		$user = ($email !== '') ? get_user_by('email', $email) : null;
		if (!($user instanceof WP_User)) {
			return array(
				'searched_email' => $email,
				'user' => null,
				'verified_programs' => array(),
				'direct_grants' => array(),
				'recent_logs' => array(),
				'reservations' => array(),
				'eligibility_checks' => array(),
			);
		}

		$user_id = (int) $user->ID;
		$verified_programs = vms_ticketing_claims_get_verified_programs_for_user($user_id);
		$direct_grants = function_exists('vms_ticketing_claims_get_direct_grants')
			? vms_ticketing_claims_get_direct_grants(array(
				'user_id' => $user_id,
				'event_id' => $event_id,
				'status' => 'any',
				'limit' => 100,
			))
			: array();
		$recent_logs = function_exists('vms_ticketing_claims_get_logs')
			? vms_ticketing_claims_get_logs(array(
				'assignee_user_id' => $user_id,
				'event_id' => $event_id,
				'limit' => 60,
			))
			: array();
		$reservations = function_exists('vms_ticketing_claims_get_reservations')
			? vms_ticketing_claims_get_reservations(array(
				'assignee_user_id' => $user_id,
				'event_id' => $event_id,
				'limit' => 100,
			))
			: array();

		$eligibility_checks = array();
		if ($event_id > 0 && function_exists('vms_ticketing_claims_resolve_eligibility')) {
			$ticket_contexts = vms_ticketing_claims_get_event_verified_ticket_contexts($event_id);
			foreach ($ticket_contexts as $ticket_ctx) {
				$product_id = absint($ticket_ctx['product_id'] ?? 0);
				$eligibility = vms_ticketing_claims_resolve_eligibility(array(
					'user_id' => $user_id,
					'event_id' => $event_id,
					'ticket_product_id' => $product_id,
					'ticket_key' => sanitize_key((string) ($ticket_ctx['ticket_key'] ?? '')),
					'legacy_program' => sanitize_key((string) ($ticket_ctx['program'] ?? '')),
					'allowed_programs' => (array) ($ticket_ctx['allowed_programs'] ?? array()),
					'allow_direct_grants' => !empty($ticket_ctx['allow_direct_grants']),
					'grant_type' => sanitize_key((string) ($ticket_ctx['claim_grant_type'] ?? 'event_ticket_eligibility')),
				));
				$eligibility_checks[] = array(
					'product_id' => $product_id,
					'product_title' => sanitize_text_field((string) ($ticket_ctx['product_title'] ?? ('#' . $product_id))),
					'ticket_key' => sanitize_key((string) ($ticket_ctx['ticket_key'] ?? '')),
					'eligibility' => is_array($eligibility) ? $eligibility : array(),
				);
			}
		}

		return array(
			'searched_email' => $email,
			'user' => $user,
			'verified_programs' => $verified_programs,
			'direct_grants' => $direct_grants,
			'recent_logs' => $recent_logs,
			'reservations' => $reservations,
			'eligibility_checks' => $eligibility_checks,
		);
	}
}

if (!function_exists('vms_ticketing_claims_render_admin_page')) {
	function vms_ticketing_claims_render_admin_page(): void
	{
		if (!vms_ticketing_claims_current_user_can_manage()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}

		$event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
		$result_filter = isset($_GET['result']) ? sanitize_key((string) wp_unslash($_GET['result'])) : '';
		$assignee_email = isset($_GET['assignee_email']) ? sanitize_email((string) wp_unslash($_GET['assignee_email'])) : '';
		$buyer_email = isset($_GET['buyer_email']) ? sanitize_email((string) wp_unslash($_GET['buyer_email'])) : '';
		$ticket_product_id = isset($_GET['ticket_product_id']) ? absint($_GET['ticket_product_id']) : 0;
		$ticket_key = isset($_GET['ticket_key']) ? sanitize_key((string) wp_unslash($_GET['ticket_key'])) : '';
		$rule_path = isset($_GET['rule_path']) ? sanitize_key((string) wp_unslash($_GET['rule_path'])) : '';
		$credential_program = isset($_GET['credential_program']) ? sanitize_key((string) wp_unslash($_GET['credential_program'])) : '';
		$direct_grant_only = isset($_GET['direct_grant_only']) ? absint($_GET['direct_grant_only']) : 0;
		$reservation_status = isset($_GET['reservation_status']) ? sanitize_key((string) wp_unslash($_GET['reservation_status'])) : '';
		$grant_account = isset($_GET['grant_account']) ? sanitize_text_field((string) wp_unslash($_GET['grant_account'])) : '';
		$grant_status_filter = isset($_GET['grant_status']) ? sanitize_key((string) wp_unslash($_GET['grant_status'])) : '';
		$grant_source_filter = isset($_GET['grant_source']) ? sanitize_key((string) wp_unslash($_GET['grant_source'])) : '';
		$grant_created_after = isset($_GET['grant_created_after']) ? sanitize_text_field((string) wp_unslash($_GET['grant_created_after'])) : '';
		$grant_created_before = isset($_GET['grant_created_before']) ? sanitize_text_field((string) wp_unslash($_GET['grant_created_before'])) : '';
		$grant_updated_after = isset($_GET['grant_updated_after']) ? sanitize_text_field((string) wp_unslash($_GET['grant_updated_after'])) : '';
		$grant_updated_before = isset($_GET['grant_updated_before']) ? sanitize_text_field((string) wp_unslash($_GET['grant_updated_before'])) : '';
		$inspector_email = isset($_GET['inspector_email']) ? sanitize_email((string) wp_unslash($_GET['inspector_email'])) : '';
		$inspector_event_id = isset($_GET['inspector_event_id']) ? absint($_GET['inspector_event_id']) : $event_id;
		$program_options = vms_ticketing_claims_get_program_options();

		$buyer_user_id = 0;
		$buyer_lookup_missing = false;
		if ($buyer_email !== '') {
			$buyer_user = get_user_by('email', $buyer_email);
			if ($buyer_user instanceof WP_User) {
				$buyer_user_id = (int) $buyer_user->ID;
			} else {
				$buyer_lookup_missing = true;
			}
		}

		$grant_user_id = 0;
		$grant_lookup_missing = false;
		if ($grant_account !== '') {
			$grant_user = vms_ticketing_claims_resolve_user($grant_account);
			if ($grant_user instanceof WP_User) {
				$grant_user_id = (int) $grant_user->ID;
			} else {
				$grant_lookup_missing = true;
			}
		}

		$logs = array();
		$reservations = array();
		$direct_grants = array();
		if (!$buyer_lookup_missing && function_exists('vms_ticketing_claims_get_logs')) {
			$logs = vms_ticketing_claims_get_logs(array(
				'event_id' => $event_id,
				'ticket_product_id' => $ticket_product_id,
				'ticket_key' => $ticket_key,
				'buyer_user_id' => $buyer_user_id,
				'assignee_email' => $assignee_email,
				'result' => $result_filter,
				'rule_path' => $rule_path,
				'credential_program' => $credential_program,
				'direct_grant_only' => $direct_grant_only === 1,
				'reservation_status' => $reservation_status,
				'limit' => 250,
			));
		}
		if (function_exists('vms_ticketing_claims_get_reservations')) {
			$reservations = vms_ticketing_claims_get_reservations(array(
				'event_id' => $event_id,
				'ticket_product_id' => $ticket_product_id,
				'ticket_key' => $ticket_key,
				'assignee_email' => $assignee_email,
				'status' => $reservation_status === '' || $reservation_status === 'any' ? '' : $reservation_status,
				'limit' => 250,
			));
		}
		if (!$grant_lookup_missing && function_exists('vms_ticketing_claims_get_direct_grants')) {
			$direct_grants = vms_ticketing_claims_get_direct_grants(array(
				'event_id' => $event_id,
				'user_id' => $grant_user_id,
				'status' => 'any',
				'grant_type' => $grant_source_filter,
				'credential_program' => $credential_program,
				'limit' => 300,
			));
			if ($grant_created_after !== '' || $grant_created_before !== '' || $grant_updated_after !== '' || $grant_updated_before !== '') {
				$direct_grants = array_values(array_filter($direct_grants, static function ($row) use ($grant_created_after, $grant_created_before, $grant_updated_after, $grant_updated_before): bool {
					if (!is_array($row)) {
						return false;
					}
					$created_ok = vms_ticketing_claims_time_in_range((string) ($row['created_at'] ?? ''), $grant_created_after, $grant_created_before);
					if (!$created_ok) {
						return false;
					}
					$updated_ok = vms_ticketing_claims_time_in_range((string) ($row['updated_at'] ?? ''), $grant_updated_after, $grant_updated_before);
					return $updated_ok;
				}));
			}
			if ($grant_status_filter !== '') {
				$direct_grants = array_values(array_filter($direct_grants, static function ($row) use ($grant_status_filter): bool {
					if (!is_array($row)) {
						return false;
					}
					$res_counts = function_exists('vms_ticketing_claims_grant_reservation_counts')
						? vms_ticketing_claims_grant_reservation_counts(absint($row['id'] ?? 0))
						: array();
					$status = function_exists('vms_ticketing_claims_resolve_grant_status')
						? vms_ticketing_claims_resolve_grant_status($row, $res_counts)
						: sanitize_key((string) ($row['status'] ?? ''));
					return $status === $grant_status_filter;
				}));
			}
		}
		$inspector_payload = ($inspector_email !== '') ? vms_ticketing_claims_build_inspector_payload($inspector_email, $inspector_event_id) : array();

		echo '<div class="wrap vms-claims-admin-page" data-vms-tour="claims.browser.help">';
		echo '<h1>' . esc_html__('Credential Claims Activity', 'backstage-venue-manager') . '</h1>';
		echo '<p class="description">' . esc_html__('Search claims, inspect results, and run safe repair actions without touching raw tables.', 'backstage-venue-manager') . '</p>';

		echo '<form method="get" action="" class="vms-claims-global-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr(vms_ticketing_claims_menu_slug()) . '" />';
		echo '<p>';
		echo '<label>' . esc_html__('Event ID', 'backstage-venue-manager') . ' <input type="number" min="0" step="1" name="event_id" value="' . esc_attr((string) $event_id) . '" class="small-text"></label> ';
		echo '<label>' . esc_html__('Result', 'backstage-venue-manager') . ' <select name="result">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		echo '<option value="success" ' . selected($result_filter, 'success', false) . '>' . esc_html__('Success', 'backstage-venue-manager') . '</option>';
		echo '<option value="failure" ' . selected($result_filter, 'failure', false) . '>' . esc_html__('Failure', 'backstage-venue-manager') . '</option>';
		echo '</select></label> ';
		echo '<label>' . esc_html__('Assignee Email', 'backstage-venue-manager') . ' <input type="text" name="assignee_email" value="' . esc_attr($assignee_email) . '" class="regular-text"></label> ';
		echo '<label>' . esc_html__('Buyer Email', 'backstage-venue-manager') . ' <input type="text" name="buyer_email" value="' . esc_attr($buyer_email) . '" class="regular-text"></label>';
		echo '</p><p>';
		echo '<label>' . esc_html__('Ticket Product ID', 'backstage-venue-manager') . ' <input type="number" min="0" step="1" name="ticket_product_id" value="' . esc_attr((string) $ticket_product_id) . '" class="small-text"></label> ';
		echo '<label>' . esc_html__('Ticket Key', 'backstage-venue-manager') . ' <input type="text" name="ticket_key" value="' . esc_attr($ticket_key) . '" class="small-text"></label> ';
		echo '<label>' . esc_html__('Rule Path', 'backstage-venue-manager') . ' <select name="rule_path">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		echo '<option value="credential_program" ' . selected($rule_path, 'credential_program', false) . '>' . esc_html__('Credential Program', 'backstage-venue-manager') . '</option>';
		echo '<option value="event_direct_grant" ' . selected($rule_path, 'event_direct_grant', false) . '>' . esc_html__('Direct Grant', 'backstage-venue-manager') . '</option>';
		echo '<option value="admin_action" ' . selected($rule_path, 'admin_action', false) . '>' . esc_html__('Manual Operator Action', 'backstage-venue-manager') . '</option>';
		echo '</select></label> ';
		echo '<label>' . esc_html__('Credential Type', 'backstage-venue-manager') . ' <select name="credential_program">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		foreach ($program_options as $program_key => $program_label) {
			$program_key = sanitize_key((string) $program_key);
			if ($program_key === '') {
				continue;
			}
			echo '<option value="' . esc_attr($program_key) . '" ' . selected($credential_program, $program_key, false) . '>' . esc_html((string) $program_label) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__('Reservation Status', 'backstage-venue-manager') . ' <select name="reservation_status">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		echo '<option value="reserved" ' . selected($reservation_status, 'reserved', false) . '>' . esc_html__('Reserved', 'backstage-venue-manager') . '</option>';
		echo '<option value="released" ' . selected($reservation_status, 'released', false) . '>' . esc_html__('Released', 'backstage-venue-manager') . '</option>';
		echo '<option value="consumed" ' . selected($reservation_status, 'consumed', false) . '>' . esc_html__('Consumed', 'backstage-venue-manager') . '</option>';
		echo '</select></label> ';
		echo '<label><input type="checkbox" name="direct_grant_only" value="1" ' . checked($direct_grant_only, 1, false) . '> ' . esc_html__('Direct grant results only', 'backstage-venue-manager') . '</label> ';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Apply Filters', 'backstage-venue-manager') . '</button> ';
		echo '<a class="button" href="' . esc_url(vms_ticketing_claims_admin_page_url()) . '">' . esc_html__('Reset', 'backstage-venue-manager') . '</a>';
		echo '</p></form>';

		if ($buyer_lookup_missing) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__('No buyer account matched the buyer email filter, so claim log results are empty.', 'backstage-venue-manager') . '</p></div>';
		}
		if ($grant_lookup_missing) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__('No account matched the benefit browser account filter, so benefit results are empty.', 'backstage-venue-manager') . '</p></div>';
		}

		echo '<h2 data-vms-tour="claims.browser">' . esc_html__('Benefit Browser', 'backstage-venue-manager') . '</h2>';
		echo '<p class="description">' . esc_html__('Search event-linked benefits by account, source, status, credential type, and created/updated dates.', 'backstage-venue-manager') . '</p>';
		echo '<form method="get" action="" class="vms-claims-global-filters" data-vms-tour="claims.browser.filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr(vms_ticketing_claims_menu_slug()) . '" />';
		echo '<label>' . esc_html__('Event ID', 'backstage-venue-manager') . ' <input type="number" min="0" step="1" name="event_id" value="' . esc_attr((string) $event_id) . '" class="small-text"></label> ';
		echo '<label>' . esc_html__('Account (email/login/id)', 'backstage-venue-manager') . ' <input type="text" name="grant_account" value="' . esc_attr($grant_account) . '" class="regular-text"></label> ';
		echo '<label>' . esc_html__('Status', 'backstage-venue-manager') . ' <select name="grant_status">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		foreach (vms_ticketing_claims_grant_status_options() as $status_key => $status_label) {
			$status_key = sanitize_key((string) $status_key);
			if ($status_key === '') {
				continue;
			}
			echo '<option value="' . esc_attr($status_key) . '" ' . selected($grant_status_filter, $status_key, false) . '>' . esc_html((string) $status_label) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__('Grant Source', 'backstage-venue-manager') . ' <select name="grant_source">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		foreach (vms_ticketing_claims_grant_type_options() as $grant_type_key => $grant_type_label) {
			$grant_type_key = sanitize_key((string) $grant_type_key);
			if ($grant_type_key === '') {
				continue;
			}
			echo '<option value="' . esc_attr($grant_type_key) . '" ' . selected($grant_source_filter, $grant_type_key, false) . '>' . esc_html((string) $grant_type_label) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__('Credential Type', 'backstage-venue-manager') . ' <select name="credential_program">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		foreach ($program_options as $program_key => $program_label) {
			$program_key = sanitize_key((string) $program_key);
			if ($program_key === '') {
				continue;
			}
			echo '<option value="' . esc_attr($program_key) . '" ' . selected($credential_program, $program_key, false) . '>' . esc_html((string) $program_label) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__('Created After', 'backstage-venue-manager') . ' <input type="date" name="grant_created_after" value="' . esc_attr($grant_created_after) . '"></label> ';
		echo '<label>' . esc_html__('Created Before', 'backstage-venue-manager') . ' <input type="date" name="grant_created_before" value="' . esc_attr($grant_created_before) . '"></label> ';
		echo '<label>' . esc_html__('Updated After', 'backstage-venue-manager') . ' <input type="date" name="grant_updated_after" value="' . esc_attr($grant_updated_after) . '"></label> ';
		echo '<label>' . esc_html__('Updated Before', 'backstage-venue-manager') . ' <input type="date" name="grant_updated_before" value="' . esc_attr($grant_updated_before) . '"></label> ';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Apply Benefit Filters', 'backstage-venue-manager') . '</button> ';
		echo '<a class="button" href="' . esc_url(vms_ticketing_claims_admin_page_url(array('event_id' => $event_id))) . '">' . esc_html__('Reset Benefits', 'backstage-venue-manager') . '</a>';
		echo '</form>';

		if (empty($direct_grants)) {
			echo '<p class="vms-claims-empty">' . esc_html__('No benefits matched the current browser filters.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<table class="widefat striped vms-claims-grants-table" data-vms-tour="claims.browser.table">';
			echo '<thead><tr><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Account', 'backstage-venue-manager') . '</th><th>' . esc_html__('Benefit Source', 'backstage-venue-manager') . '</th><th>' . esc_html__('Eligibility Basis', 'backstage-venue-manager') . '</th><th>' . esc_html__('Ticket Context', 'backstage-venue-manager') . '</th><th>' . esc_html__('Qty / Used', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th><th>' . esc_html__('Created', 'backstage-venue-manager') . '</th><th>' . esc_html__('Updated', 'backstage-venue-manager') . '</th><th>' . esc_html__('Notes', 'backstage-venue-manager') . '</th><th>' . esc_html__('Quick Actions', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
			foreach ($direct_grants as $grant_row) {
				$grant_id = absint($grant_row['id'] ?? 0);
				$grant_event_id = absint($grant_row['event_id'] ?? 0);
				$event_title = $grant_event_id > 0 ? sanitize_text_field((string) get_the_title($grant_event_id)) : '';
				$account_label = vms_ticketing_claims_user_display(absint($grant_row['user_id'] ?? 0), __('Unknown account', 'backstage-venue-manager'));
				$grant_type = sanitize_key((string) ($grant_row['grant_type'] ?? ''));
				$grant_type_label = function_exists('vms_ticketing_claims_grant_type_label')
					? vms_ticketing_claims_grant_type_label($grant_type)
					: ($grant_type !== '' ? ucwords(str_replace('_', ' ', $grant_type)) : __('Unknown', 'backstage-venue-manager'));
				$credential_basis = sanitize_key((string) ($grant_row['credential_program'] ?? ''));
				if ($credential_basis !== '' && function_exists('vms_ticketing_verification_program_label')) {
					$credential_basis = sanitize_text_field((string) vms_ticketing_verification_program_label($credential_basis));
				} elseif ($credential_basis !== '') {
					$credential_basis = ucwords(str_replace('_', ' ', $credential_basis));
				} else {
					$credential_basis = __('Any credential / direct benefit', 'backstage-venue-manager');
				}
				$res_counts = function_exists('vms_ticketing_claims_grant_reservation_counts')
					? vms_ticketing_claims_grant_reservation_counts($grant_id)
					: array();
				$status_key = function_exists('vms_ticketing_claims_resolve_grant_status')
					? vms_ticketing_claims_resolve_grant_status($grant_row, $res_counts)
					: sanitize_key((string) ($grant_row['status'] ?? 'active'));
				$status_label = function_exists('vms_ticketing_claims_grant_status_label')
					? vms_ticketing_claims_grant_status_label($status_key)
					: ucwords(str_replace('_', ' ', $status_key));
				$status_help = function_exists('vms_ticketing_claims_grant_status_explanation')
					? vms_ticketing_claims_grant_status_explanation($status_key)
					: '';
				echo '<tr>';
				echo '<td>' . esc_html($event_title !== '' ? $event_title : ('#' . $grant_event_id)) . '</td>';
				echo '<td>' . esc_html($account_label) . '</td>';
				echo '<td>' . esc_html($grant_type_label) . '</td>';
				echo '<td>' . esc_html($credential_basis) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label(absint($grant_row['ticket_product_id'] ?? 0), sanitize_key((string) ($grant_row['ticket_key'] ?? '')))) . '</td>';
				echo '<td>' . esc_html((string) absint($grant_row['qty_limit'] ?? 0)) . ' / ' . esc_html((string) absint($grant_row['qty_used'] ?? 0)) . '</td>';
				echo '<td><span class="vms-claims-status vms-claims-status-' . esc_attr($status_key) . '">' . esc_html($status_label) . '</span>';
				if ($status_help !== '') {
					echo '<br><span class="vms-claims-meta">' . esc_html($status_help) . '</span>';
				}
				echo '</td>';
				echo '<td>' . esc_html((string) ($grant_row['created_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($grant_row['updated_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html(sanitize_text_field((string) ($grant_row['note'] ?? ''))) . '</td>';
				echo '<td>';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-claims-action-form">';
				echo '<input type="hidden" name="action" value="vms_ticketing_claims_set_grant_status" />';
				echo '<input type="hidden" name="event_plan_id" value="0" />';
				echo '<input type="hidden" name="grant_id" value="' . esc_attr((string) $grant_id) . '" />';
				echo '<select name="new_status" class="small-text">';
				foreach (vms_ticketing_claims_grant_next_status_options($status_key) as $option_status => $option_label) {
					$option_status = sanitize_key((string) $option_status);
					if ($option_status === '') {
						continue;
					}
					echo '<option value="' . esc_attr($option_status) . '" ' . selected($status_key, $option_status, false) . '>' . esc_html((string) $option_label) . '</option>';
				}
				echo '</select> ';
				wp_nonce_field('vms_ticketing_claims_set_grant_status_' . $grant_id);
				echo '<button type="submit" class="button button-small">' . esc_html__('Apply', 'backstage-venue-manager') . '</button>';
				echo '</form>';
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2 data-vms-tour="claims.log">' . esc_html__('Claim Log', 'backstage-venue-manager') . '</h2>';
		if (empty($logs)) {
			echo '<p class="vms-claims-empty">' . esc_html__('No claim log entries matched the current filters.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<table class="widefat striped vms-claims-logs-table">';
			echo '<thead><tr><th>' . esc_html__('Timestamp (UTC)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Assignee', 'backstage-venue-manager') . '</th><th>' . esc_html__('Buyer/Actor', 'backstage-venue-manager') . '</th><th>' . esc_html__('Ticket', 'backstage-venue-manager') . '</th><th>' . esc_html__('Rule Path', 'backstage-venue-manager') . '</th><th>' . esc_html__('Result', 'backstage-venue-manager') . '</th><th>' . esc_html__('Reason', 'backstage-venue-manager') . '</th><th>' . esc_html__('Source', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
			foreach ($logs as $row) {
				$context = function_exists('vms_ticketing_claims_decode_context_json')
					? vms_ticketing_claims_decode_context_json((string) ($row['context_json'] ?? ''))
					: array();
				$event_post_id = absint($row['event_id'] ?? 0);
				$event_title = $event_post_id > 0 ? (string) get_the_title($event_post_id) : '';
				$source = sanitize_text_field((string) ($context['source'] ?? ''));
				$result = sanitize_key((string) ($row['result'] ?? ''));
				$reason_code = sanitize_key((string) ($row['reason_code'] ?? ''));
				$message = sanitize_text_field((string) ($row['message'] ?? ''));
				$reason_label = function_exists('vms_ticketing_claims_friendly_reason')
					? vms_ticketing_claims_friendly_reason($reason_code, $message)
					: ($message !== '' ? $message : $reason_code);

				echo '<tr>';
				echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html($event_title !== '' ? $event_title : ('#' . $event_post_id)) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_user_display(absint($row['assignee_user_id'] ?? 0), sanitize_email((string) ($row['assignee_email'] ?? '')))) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_user_display(absint($row['buyer_user_id'] ?? 0), '—')) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label(absint($row['ticket_product_id'] ?? 0), sanitize_key((string) ($row['ticket_key'] ?? '')))) . '</td>';
				echo '<td>' . esc_html(sanitize_key((string) ($row['rule_path'] ?? '')) ?: '—') . '</td>';
				echo '<td><span class="vms-claims-status vms-claims-status-' . esc_attr($result) . '">' . esc_html($result !== '' ? ucwords(str_replace('_', ' ', $result)) : '—') . '</span></td>';
				echo '<td>' . esc_html($reason_label) . '</td>';
				echo '<td>' . esc_html($source !== '' ? $source : '—') . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2 data-vms-tour="claims.repair">' . esc_html__('Reservation Repair', 'backstage-venue-manager') . '</h2>';
		if (empty($reservations)) {
			echo '<p class="vms-claims-empty">' . esc_html__('No reservations matched the current filters.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<table class="widefat striped vms-claims-reservations-table">';
			echo '<thead><tr><th>' . esc_html__('Created', 'backstage-venue-manager') . '</th><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Assignee', 'backstage-venue-manager') . '</th><th>' . esc_html__('Buyer', 'backstage-venue-manager') . '</th><th>' . esc_html__('Ticket Context', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th><th>' . esc_html__('Order/Cart Context', 'backstage-venue-manager') . '</th><th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
			foreach ($reservations as $row) {
				$reservation_id = absint($row['id'] ?? 0);
				$status = sanitize_key((string) ($row['status'] ?? ''));
				$event_post_id = absint($row['event_id'] ?? 0);
				$event_title = $event_post_id > 0 ? (string) get_the_title($event_post_id) : '';
				echo '<tr>';
				echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html($event_title !== '' ? $event_title : ('#' . $event_post_id)) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_user_display(absint($row['assignee_user_id'] ?? 0), sanitize_email((string) ($row['assignee_email'] ?? '')))) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_user_display(absint($row['buyer_user_id'] ?? 0), '—')) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label(absint($row['ticket_product_id'] ?? 0), sanitize_key((string) ($row['ticket_key'] ?? '')))) . '</td>';
				echo '<td><span class="vms-claims-status vms-claims-status-' . esc_attr($status) . '">' . esc_html($status !== '' ? ucwords(str_replace('_', ' ', $status)) : '—') . '</span></td>';
				echo '<td>';
				echo '<span class="vms-claims-meta">' . esc_html__('Order', 'backstage-venue-manager') . ':</span> ' . esc_html(absint($row['order_id'] ?? 0) > 0 ? ('#' . absint($row['order_id'] ?? 0)) : '—');
				echo '<br><span class="vms-claims-meta">' . esc_html__('Cart', 'backstage-venue-manager') . ':</span> ' . esc_html((string) ($row['cart_item_key'] ?? '') !== '' ? (string) $row['cart_item_key'] : '—');
				echo '<br><span class="vms-claims-meta">' . esc_html__('Session', 'backstage-venue-manager') . ':</span> ' . esc_html((string) ($row['session_key'] ?? '') !== '' ? (string) $row['session_key'] : '—');
				echo '</td>';
				echo '<td>';
				if ($status === 'reserved') {
					echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-claims-action-form">';
					echo '<input type="hidden" name="action" value="vms_ticketing_claims_release_reservation" />';
					echo '<input type="hidden" name="event_plan_id" value="0" />';
					echo '<input type="hidden" name="reservation_id" value="' . esc_attr((string) $reservation_id) . '" />';
					wp_nonce_field('vms_ticketing_claims_release_reservation_' . $reservation_id);
					echo '<button type="submit" class="button button-small" onclick="return confirm(' . esc_attr(wp_json_encode(__('Release this reservation? History will be preserved in the activity log.', 'backstage-venue-manager'))) . ');">' . esc_html__('Release Reservation', 'backstage-venue-manager') . '</button>';
					echo '</form>';
				} else {
					echo '<span class="vms-claims-meta">' . esc_html__('No repair action available.', 'backstage-venue-manager') . '</span>';
				}
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2 data-vms-tour="claims.inspector">' . esc_html__('Eligibility Inspector', 'backstage-venue-manager') . '</h2>';
		echo '<p class="description">' . esc_html__('Paste an email to inspect credential approvals, event grants, and recent claim usage in plain language.', 'backstage-venue-manager') . '</p>';
		echo '<form method="get" action="" class="vms-claims-global-filters" data-vms-tour="claims.inspector.form">';
		echo '<input type="hidden" name="page" value="' . esc_attr(vms_ticketing_claims_menu_slug()) . '" />';
		echo '<label>' . esc_html__('Account Email', 'backstage-venue-manager') . ' <input type="text" name="inspector_email" class="regular-text" value="' . esc_attr($inspector_email) . '" required></label> ';
		echo '<label>' . esc_html__('Event ID (optional)', 'backstage-venue-manager') . ' <input type="number" min="0" step="1" name="inspector_event_id" class="small-text" value="' . esc_attr((string) $inspector_event_id) . '"></label> ';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Inspect Account', 'backstage-venue-manager') . '</button>';
		echo '</form>';

		if ($inspector_email !== '') {
			$user = $inspector_payload['user'] ?? null;
			if (!($user instanceof WP_User)) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__('No registered account matched that email.', 'backstage-venue-manager') . '</p></div>';
			} else {
				$verified_programs = (array) ($inspector_payload['verified_programs'] ?? array());
				$direct_grants = (array) ($inspector_payload['direct_grants'] ?? array());
				$recent_logs = (array) ($inspector_payload['recent_logs'] ?? array());
				$user_reservations = (array) ($inspector_payload['reservations'] ?? array());
				$eligibility_checks = (array) ($inspector_payload['eligibility_checks'] ?? array());
				$program_labels = array();
				foreach ($verified_programs as $program_key) {
					$program_key = sanitize_key((string) $program_key);
					if ($program_key === '') {
						continue;
					}
					if (function_exists('vms_ticketing_verification_program_label')) {
						$program_labels[] = vms_ticketing_verification_program_label($program_key);
					} else {
						$program_labels[] = ucwords(str_replace('_', ' ', $program_key));
					}
				}
				$reservation_counts = array();
				foreach ($user_reservations as $reservation_row) {
					$status_key = sanitize_key((string) ($reservation_row['status'] ?? 'unknown'));
					if (!isset($reservation_counts[$status_key])) {
						$reservation_counts[$status_key] = 0;
					}
					$reservation_counts[$status_key]++;
				}

				echo '<div class="vms-claims-inspector-card">';
				echo '<p><strong>' . esc_html__('Account', 'backstage-venue-manager') . ':</strong> ' . esc_html((string) $user->display_name) . ' &lt;' . esc_html((string) $user->user_email) . '&gt; <span class="vms-claims-meta">#' . esc_html((string) $user->ID) . '</span></p>';
				echo '<p><strong>' . esc_html__('Credential types approved', 'backstage-venue-manager') . ':</strong> ' . (!empty($program_labels) ? esc_html(implode(', ', $program_labels)) : esc_html__('None', 'backstage-venue-manager')) . '</p>';
				if (!empty($reservation_counts)) {
					$chunks = array();
					foreach ($reservation_counts as $status_key => $count) {
						$chunks[] = ucwords(str_replace('_', ' ', $status_key)) . ': ' . absint($count);
					}
					echo '<p><strong>' . esc_html__('Event usage state', 'backstage-venue-manager') . ':</strong> ' . esc_html(implode(' | ', $chunks)) . '</p>';
				}
				echo '</div>';

					echo '<h3 data-vms-tour="claims.inspector.grants">' . esc_html__('Event-Specific Grants', 'backstage-venue-manager') . '</h3>';
				if (empty($direct_grants)) {
					echo '<p class="vms-claims-empty">' . esc_html__('No direct event grants found for this account and filter.', 'backstage-venue-manager') . '</p>';
				} else {
					echo '<table class="widefat striped vms-claims-grants-table">';
					echo '<thead><tr><th>' . esc_html__('Event ID', 'backstage-venue-manager') . '</th><th>' . esc_html__('Grant Type', 'backstage-venue-manager') . '</th><th>' . esc_html__('Ticket Context', 'backstage-venue-manager') . '</th><th>' . esc_html__('Qty / Used', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th><th>' . esc_html__('Created', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
					foreach ($direct_grants as $grant_row) {
						echo '<tr>';
						echo '<td>' . esc_html((string) absint($grant_row['event_id'] ?? 0)) . '</td>';
							$grant_type_key = sanitize_key((string) ($grant_row['grant_type'] ?? ''));
							$grant_type_label = function_exists('vms_ticketing_claims_grant_type_label')
								? vms_ticketing_claims_grant_type_label($grant_type_key)
								: ($grant_type_key !== '' ? ucwords(str_replace('_', ' ', $grant_type_key)) : __('Unknown', 'backstage-venue-manager'));
							echo '<td>' . esc_html($grant_type_label) . '</td>';
						echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label(absint($grant_row['ticket_product_id'] ?? 0), sanitize_key((string) ($grant_row['ticket_key'] ?? '')))) . '</td>';
						echo '<td>' . esc_html((string) absint($grant_row['qty_limit'] ?? 0)) . ' / ' . esc_html((string) absint($grant_row['qty_used'] ?? 0)) . '</td>';
							$grant_res_counts = function_exists('vms_ticketing_claims_grant_reservation_counts')
								? vms_ticketing_claims_grant_reservation_counts(absint($grant_row['id'] ?? 0))
								: array();
							$grant_status_key = function_exists('vms_ticketing_claims_resolve_grant_status')
								? vms_ticketing_claims_resolve_grant_status($grant_row, $grant_res_counts)
								: sanitize_key((string) ($grant_row['status'] ?? 'unknown'));
							$grant_status_label = function_exists('vms_ticketing_claims_grant_status_label')
								? vms_ticketing_claims_grant_status_label($grant_status_key)
								: ucwords(str_replace('_', ' ', $grant_status_key));
							echo '<td>' . esc_html($grant_status_label) . '</td>';
						echo '<td>' . esc_html((string) ($grant_row['created_at'] ?? '')) . '</td>';
						echo '</tr>';
					}
					echo '</tbody></table>';
				}

				if ($inspector_event_id > 0) {
					echo '<h3>' . esc_html__('Event Eligibility Checks', 'backstage-venue-manager') . '</h3>';
					if (empty($eligibility_checks)) {
						echo '<p class="vms-claims-empty">' . esc_html__('No verified ticket contexts were found for that event.', 'backstage-venue-manager') . '</p>';
					} else {
						echo '<table class="widefat striped vms-claims-eligibility-table">';
						echo '<thead><tr><th>' . esc_html__('Ticket', 'backstage-venue-manager') . '</th><th>' . esc_html__('Eligible', 'backstage-venue-manager') . '</th><th>' . esc_html__('Rule Path', 'backstage-venue-manager') . '</th><th>' . esc_html__('Reason', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
						foreach ($eligibility_checks as $check_row) {
							$eligibility = isset($check_row['eligibility']) && is_array($check_row['eligibility']) ? $check_row['eligibility'] : array();
							$is_eligible = !empty($eligibility['eligible']);
							$reason_code = sanitize_key((string) ($eligibility['reason_code'] ?? ''));
							$message = sanitize_text_field((string) ($eligibility['message'] ?? ''));
							$reason_label = function_exists('vms_ticketing_claims_friendly_reason')
								? vms_ticketing_claims_friendly_reason($reason_code, $message)
								: ($message !== '' ? $message : $reason_code);
							echo '<tr>';
							echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label(absint($check_row['product_id'] ?? 0), sanitize_key((string) ($check_row['ticket_key'] ?? '')))) . '</td>';
							echo '<td>' . esc_html($is_eligible ? __('Yes', 'backstage-venue-manager') : __('No', 'backstage-venue-manager')) . '</td>';
							echo '<td>' . esc_html(sanitize_key((string) ($eligibility['matched_rule_path'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html($reason_label) . '</td>';
							echo '</tr>';
						}
						echo '</tbody></table>';
					}
				}

				echo '<h3>' . esc_html__('Recent Claim History', 'backstage-venue-manager') . '</h3>';
				if (empty($recent_logs)) {
					echo '<p class="vms-claims-empty">' . esc_html__('No claim history found for this account and filter.', 'backstage-venue-manager') . '</p>';
				} else {
					echo '<table class="widefat striped vms-claims-logs-table">';
					echo '<thead><tr><th>' . esc_html__('Timestamp (UTC)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Ticket', 'backstage-venue-manager') . '</th><th>' . esc_html__('Result', 'backstage-venue-manager') . '</th><th>' . esc_html__('Reason', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
					foreach ($recent_logs as $log_row) {
						$result = sanitize_key((string) ($log_row['result'] ?? ''));
						$reason_code = sanitize_key((string) ($log_row['reason_code'] ?? ''));
						$message = sanitize_text_field((string) ($log_row['message'] ?? ''));
						$reason_label = function_exists('vms_ticketing_claims_friendly_reason')
							? vms_ticketing_claims_friendly_reason($reason_code, $message)
							: ($message !== '' ? $message : $reason_code);
						$event_post_id = absint($log_row['event_id'] ?? 0);
						$event_title = $event_post_id > 0 ? (string) get_the_title($event_post_id) : '';
						echo '<tr>';
						echo '<td>' . esc_html((string) ($log_row['created_at'] ?? '')) . '</td>';
						echo '<td>' . esc_html($event_title !== '' ? $event_title : ('#' . $event_post_id)) . '</td>';
						echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label(absint($log_row['ticket_product_id'] ?? 0), sanitize_key((string) ($log_row['ticket_key'] ?? '')))) . '</td>';
						echo '<td>' . esc_html($result !== '' ? ucwords(str_replace('_', ' ', $result)) : '—') . '</td>';
						echo '<td>' . esc_html($reason_label) . '</td>';
						echo '</tr>';
					}
					echo '</tbody></table>';
				}
			}
		}

		echo '</div>';
	}
}

if (!function_exists('vms_ticketing_claims_render_user_profile_summary')) {
	function vms_ticketing_claims_render_user_profile_summary(WP_User $user): void
	{
		if (!vms_ticketing_claims_current_user_can_manage() || !current_user_can('edit_user', (int) $user->ID)) {
			return;
		}

		$user_id = (int) $user->ID;
		$verified_programs = vms_ticketing_claims_get_verified_programs_for_user($user_id);
		$program_labels = array();
		foreach ($verified_programs as $program_key) {
			$program_key = sanitize_key((string) $program_key);
			if ($program_key === '') {
				continue;
			}
			if (function_exists('vms_ticketing_verification_program_label')) {
				$program_labels[] = vms_ticketing_verification_program_label($program_key);
			} else {
				$program_labels[] = ucwords(str_replace('_', ' ', $program_key));
			}
		}

		$grants = function_exists('vms_ticketing_claims_get_direct_grants')
			? vms_ticketing_claims_get_direct_grants(array(
				'user_id' => $user_id,
				'status' => 'any',
				'limit' => 25,
			))
			: array();
		$recent_logs = function_exists('vms_ticketing_claims_get_logs')
			? vms_ticketing_claims_get_logs(array(
				'assignee_user_id' => $user_id,
				'limit' => 25,
			))
			: array();
		$inspector_url = vms_ticketing_claims_admin_page_url(array(
			'inspector_email' => (string) $user->user_email,
		));

		echo '<h2>' . esc_html__('VMS Eligibility Summary', 'backstage-venue-manager') . '</h2>';
		echo '<p class="description">' . esc_html__('Credential approvals, event-specific grants, and recent claim activity for this account.', 'backstage-venue-manager') . '</p>';
		echo '<p><strong>' . esc_html__('Approved credential types', 'backstage-venue-manager') . ':</strong> ' . (!empty($program_labels) ? esc_html(implode(', ', $program_labels)) : esc_html__('None', 'backstage-venue-manager')) . '</p>';
		echo '<p><a class="button" href="' . esc_url($inspector_url) . '">' . esc_html__('Open Full Eligibility Inspector', 'backstage-venue-manager') . '</a></p>';

		echo '<h3>' . esc_html__('Event-Specific Grants', 'backstage-venue-manager') . '</h3>';
		if (empty($grants)) {
			echo '<p class="description">' . esc_html__('No direct event grants found.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Event ID', 'backstage-venue-manager') . '</th><th>' . esc_html__('Grant Type', 'backstage-venue-manager') . '</th><th>' . esc_html__('Ticket Context', 'backstage-venue-manager') . '</th><th>' . esc_html__('Qty / Used', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
			foreach ($grants as $grant_row) {
				$grant_type_key = sanitize_key((string) ($grant_row['grant_type'] ?? ''));
				$grant_type_label = function_exists('vms_ticketing_claims_grant_type_label')
					? vms_ticketing_claims_grant_type_label($grant_type_key)
					: ($grant_type_key !== '' ? ucwords(str_replace('_', ' ', $grant_type_key)) : __('Unknown', 'backstage-venue-manager'));
				$grant_res_counts = function_exists('vms_ticketing_claims_grant_reservation_counts')
					? vms_ticketing_claims_grant_reservation_counts(absint($grant_row['id'] ?? 0))
					: array();
				$grant_status_key = function_exists('vms_ticketing_claims_resolve_grant_status')
					? vms_ticketing_claims_resolve_grant_status($grant_row, $grant_res_counts)
					: sanitize_key((string) ($grant_row['status'] ?? 'unknown'));
				$grant_status_label = function_exists('vms_ticketing_claims_grant_status_label')
					? vms_ticketing_claims_grant_status_label($grant_status_key)
					: ucwords(str_replace('_', ' ', $grant_status_key));
				echo '<tr>';
				echo '<td>' . esc_html((string) absint($grant_row['event_id'] ?? 0)) . '</td>';
				echo '<td>' . esc_html($grant_type_label) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label(absint($grant_row['ticket_product_id'] ?? 0), sanitize_key((string) ($grant_row['ticket_key'] ?? '')))) . '</td>';
				echo '<td>' . esc_html((string) absint($grant_row['qty_limit'] ?? 0)) . ' / ' . esc_html((string) absint($grant_row['qty_used'] ?? 0)) . '</td>';
				echo '<td>' . esc_html($grant_status_label) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h3>' . esc_html__('Recent Claim Activity', 'backstage-venue-manager') . '</h3>';
		if (empty($recent_logs)) {
			echo '<p class="description">' . esc_html__('No recent claim activity found.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Timestamp (UTC)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Ticket', 'backstage-venue-manager') . '</th><th>' . esc_html__('Result', 'backstage-venue-manager') . '</th><th>' . esc_html__('Reason', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
			foreach ($recent_logs as $log_row) {
				$result = sanitize_key((string) ($log_row['result'] ?? ''));
				$reason_code = sanitize_key((string) ($log_row['reason_code'] ?? ''));
				$message = sanitize_text_field((string) ($log_row['message'] ?? ''));
				$reason_label = function_exists('vms_ticketing_claims_friendly_reason')
					? vms_ticketing_claims_friendly_reason($reason_code, $message)
					: ($message !== '' ? $message : $reason_code);
				$event_post_id = absint($log_row['event_id'] ?? 0);
				$event_title = $event_post_id > 0 ? (string) get_the_title($event_post_id) : '';
				echo '<tr>';
				echo '<td>' . esc_html((string) ($log_row['created_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html($event_title !== '' ? $event_title : ('#' . $event_post_id)) . '</td>';
				echo '<td>' . esc_html(vms_ticketing_claims_ticket_context_label(absint($log_row['ticket_product_id'] ?? 0), sanitize_key((string) ($log_row['ticket_key'] ?? '')))) . '</td>';
				echo '<td>' . esc_html($result !== '' ? ucwords(str_replace('_', ' ', $result)) : '—') . '</td>';
				echo '<td>' . esc_html($reason_label) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
	}
}
add_action('show_user_profile', 'vms_ticketing_claims_render_user_profile_summary');
add_action('edit_user_profile', 'vms_ticketing_claims_render_user_profile_summary');
