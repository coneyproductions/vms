<?php

defined('ABSPATH') || exit;

/**
 * Durable, operation-scoped customer communications for occurrence changes.
 *
 * Each occurrence operation owns one Event Plan metadata record. The audience
 * subtree is immutable after creation; recipient state and audit collections are
 * changed later through compare-and-swap writes.
 */

if (!function_exists('bvmgr_event_communication_operation_id_is_valid')) {
	function bvmgr_event_communication_operation_id_is_valid(string $operation_id): bool
	{
		return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim($operation_id));
	}
}

if (!function_exists('bvmgr_event_communication_meta_key')) {
	function bvmgr_event_communication_meta_key(string $operation_id): string
	{
		$operation_id = strtolower(trim($operation_id));
		return bvmgr_event_communication_operation_id_is_valid($operation_id)
			? '_vms_event_communication_v1_' . $operation_id
			: '';
	}
}

if (!function_exists('bvmgr_event_communication_now_utc')) {
	function bvmgr_event_communication_now_utc(): string
	{
		return gmdate('Y-m-d H:i:s');
	}
}

if (!function_exists('bvmgr_event_communication_normalize_fingerprint_value')) {
	function bvmgr_event_communication_normalize_fingerprint_value($value)
	{
		if (!is_array($value)) {
			return $value;
		}
		$is_list = $value === array() || array_keys($value) === range(0, count($value) - 1);
		if (!$is_list) {
			ksort($value, SORT_STRING);
		}
		foreach ($value as $key => $item) {
			$value[$key] = bvmgr_event_communication_normalize_fingerprint_value($item);
		}
		return $value;
	}
}

if (!function_exists('bvmgr_event_communication_hash')) {
	function bvmgr_event_communication_hash(array $value): string
	{
		return hash('sha256', (string) wp_json_encode(bvmgr_event_communication_normalize_fingerprint_value($value)));
	}
}

if (!function_exists('bvmgr_event_communication_custom_admission_rows')) {
	function bvmgr_event_communication_custom_admission_rows(int $plan_id): array
	{
		global $wpdb;
		$out = array('ok' => true, 'rows' => array(), 'error' => '');
		if ($plan_id <= 0 || !function_exists('bvmgr_admission_table_entries')) {
			return $out;
		}
		$table = (string) bvmgr_admission_table_entries();
		if ($table === '') {
			return $out;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Operation-time audience evidence must read every active record from the plugin-owned admissions repository.
		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if ((string) $exists !== $table) {
			return $out;
		}
		$wpdb->last_error = '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The bounded Event Plan query captures immutable communication evidence from the plugin-owned admissions repository.
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT id, admission_kind, source, guest_name, guest_email, party_size, phone, claim_reference FROM %i WHERE event_plan_id = %d AND status <> 'canceled' ORDER BY id ASC",
			$table,
			$plan_id
		), ARRAY_A);
		if ($wpdb->last_error !== '') {
			$out['ok'] = false;
			$out['error'] = 'Custom admission audience evidence could not be read.';
			return $out;
		}
		$out['rows'] = is_array($rows) ? array_values($rows) : array();
		return $out;
	}
}

if (!function_exists('bvmgr_event_communication_recipient_tokens')) {
	function bvmgr_event_communication_recipient_tokens(string $email, int $user_id, string $phone): array
	{
		$tokens = array();
		$valid_email = sanitize_email($email);
		if ($valid_email !== '' && is_email($valid_email)) {
			$tokens[] = 'email:' . strtolower($valid_email);
		}
		if ($user_id > 0) {
			$tokens[] = 'user:' . $user_id;
		}
		$phone_key = preg_replace('/[^0-9+]+/', '', $phone);
		if (is_string($phone_key) && strlen($phone_key) >= 7) {
			$tokens[] = 'phone:' . $phone_key;
		}
		return array_values(array_unique($tokens));
	}
}

if (!function_exists('bvmgr_event_communication_recipient_id')) {
	function bvmgr_event_communication_recipient_id(array $tokens, string $fallback): string
	{
		$identity = !empty($tokens) ? (string) $tokens[0] : $fallback;
		return 'recipient_' . substr(hash('sha256', $identity), 0, 24);
	}
}

if (!function_exists('bvmgr_event_communication_empty_recipient')) {
	function bvmgr_event_communication_empty_recipient(string $recipient_id): array
	{
		return array(
			'recipient_id' => $recipient_id,
			'customer_user_id' => 0,
			'customer_name' => '',
			'email_snapshot' => '',
			'email_valid' => false,
			'phone_snapshot' => '',
			'orders' => array(),
			'direct_entitlements' => array(),
		);
	}
}

if (!function_exists('bvmgr_event_communication_merge_identity')) {
	function bvmgr_event_communication_merge_identity(array &$recipient, string $name, string $email, int $user_id, string $phone): void
	{
		$name = sanitize_text_field($name);
		$email_snapshot = sanitize_text_field($email);
		$valid_email = sanitize_email($email_snapshot);
		$phone = sanitize_text_field($phone);
		if ((string) ($recipient['customer_name'] ?? '') === '' && $name !== '') {
			$recipient['customer_name'] = $name;
		}
		if ((int) ($recipient['customer_user_id'] ?? 0) <= 0 && $user_id > 0) {
			$recipient['customer_user_id'] = $user_id;
		}
		if ((string) ($recipient['email_snapshot'] ?? '') === '' && $email_snapshot !== '') {
			$recipient['email_snapshot'] = $email_snapshot;
		}
		if (empty($recipient['email_valid']) && $valid_email !== '' && is_email($valid_email)) {
			$recipient['email_snapshot'] = strtolower($valid_email);
			$recipient['email_valid'] = true;
		}
		if ((string) ($recipient['phone_snapshot'] ?? '') === '' && $phone !== '') {
			$recipient['phone_snapshot'] = $phone;
		}
	}
}

if (!function_exists('bvmgr_event_communication_audience_from_preview')) {
	function bvmgr_event_communication_audience_from_preview(array $preview): array
	{
		$recipients = array();
		$token_map = array();
		$item_ids = array();
		$admission_entry_ids = array();

		$resolve_recipient_id = static function (array $tokens, string $fallback) use (&$token_map): string {
			foreach ($tokens as $token) {
				if (isset($token_map[$token])) {
					return (string) $token_map[$token];
				}
			}
			return bvmgr_event_communication_recipient_id($tokens, $fallback);
		};
		$register_tokens = static function (string $recipient_id, array $tokens) use (&$token_map): void {
			foreach ($tokens as $token) {
				$token_map[$token] = $recipient_id;
			}
		};

		foreach ((array) ($preview['rows'] ?? array()) as $row) {
			$item_id = absint($row['order_item_id'] ?? 0);
			$order_id = absint($row['order_id'] ?? 0);
			$quantity = max(0, (int) ($row['effective_quantity'] ?? $row['qty'] ?? 0));
			if ($item_id <= 0 || $order_id <= 0 || $quantity <= 0) {
				continue;
			}
			$order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
			$name = sanitize_text_field((string) ($row['customer_name'] ?? ''));
			$email = sanitize_text_field((string) ($row['customer_email'] ?? ''));
			$user_id = 0;
			$phone = '';
			$order_number = (string) $order_id;
			$order_date = sanitize_text_field((string) ($row['order_date_local'] ?? ''));
			if (is_object($order)) {
				$user_id = method_exists($order, 'get_customer_id') ? absint($order->get_customer_id()) : 0;
				$phone = method_exists($order, 'get_billing_phone') ? (string) $order->get_billing_phone() : '';
				$order_number = method_exists($order, 'get_order_number') ? (string) $order->get_order_number() : $order_number;
			}
			$tokens = bvmgr_event_communication_recipient_tokens($email, $user_id, $phone);
			$recipient_id = $resolve_recipient_id($tokens, 'order:' . $order_id);
			if (!isset($recipients[$recipient_id])) {
				$recipients[$recipient_id] = bvmgr_event_communication_empty_recipient($recipient_id);
			}
			$register_tokens($recipient_id, $tokens);
			bvmgr_event_communication_merge_identity($recipients[$recipient_id], $name, $email, $user_id, $phone);
			if (!isset($recipients[$recipient_id]['orders'][$order_id])) {
				$recipients[$recipient_id]['orders'][$order_id] = array(
					'order_id' => $order_id,
					'order_number' => sanitize_text_field($order_number),
					'order_date_local' => $order_date,
					'entitlements' => array(),
				);
			}
			$kind = sanitize_key((string) ($row['line_kind'] ?? ''));
			$label = sanitize_text_field((string) ($row['product_name'] ?? ''));
			if (function_exists('bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match')) {
				$label = sanitize_text_field((string) bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match($label));
			}
			if ($label === '') {
				$label = $kind === 'addon' ? 'Reservation' : 'Admission';
			}
			$recipients[$recipient_id]['orders'][$order_id]['entitlements'][$item_id] = array(
				'order_item_id' => $item_id,
				'kind' => $kind,
				'label' => $label,
				'quantity' => $quantity,
				'reservation_identity' => $kind === 'addon' ? $label : '',
			);
			$item_ids[$item_id] = true;
		}

		$custom = bvmgr_event_communication_custom_admission_rows(absint($preview['plan_id'] ?? 0));
		foreach ((array) ($custom['rows'] ?? array()) as $row) {
			$entry_id = absint($row['id'] ?? 0);
			$quantity = max(0, (int) ($row['party_size'] ?? 0));
			if ($entry_id <= 0 || $quantity <= 0) {
				continue;
			}
			$name = sanitize_text_field((string) ($row['guest_name'] ?? ''));
			$email = sanitize_text_field((string) ($row['guest_email'] ?? ''));
			$phone = sanitize_text_field((string) ($row['phone'] ?? ''));
			$tokens = bvmgr_event_communication_recipient_tokens($email, 0, $phone);
			$recipient_id = $resolve_recipient_id($tokens, 'admission:' . $entry_id);
			if (!isset($recipients[$recipient_id])) {
				$recipients[$recipient_id] = bvmgr_event_communication_empty_recipient($recipient_id);
			}
			$register_tokens($recipient_id, $tokens);
			bvmgr_event_communication_merge_identity($recipients[$recipient_id], $name, $email, 0, $phone);
			$kind = sanitize_key((string) ($row['admission_kind'] ?? ''));
			$label = $kind !== '' ? ucwords(str_replace('_', ' ', $kind)) : 'Admission';
			$recipients[$recipient_id]['direct_entitlements'][$entry_id] = array(
				'admission_entry_id' => $entry_id,
				'kind' => 'custom_admission',
				'label' => sanitize_text_field($label),
				'quantity' => $quantity,
				'source' => sanitize_key((string) ($row['source'] ?? '')),
				'claim_reference' => sanitize_text_field((string) ($row['claim_reference'] ?? '')),
			);
			$admission_entry_ids[$entry_id] = true;
		}

		foreach ($recipients as &$recipient) {
			ksort($recipient['orders'], SORT_NUMERIC);
			foreach ($recipient['orders'] as &$order) {
				ksort($order['entitlements'], SORT_NUMERIC);
				$order['entitlements'] = array_values($order['entitlements']);
			}
			unset($order);
			$recipient['orders'] = array_values($recipient['orders']);
			ksort($recipient['direct_entitlements'], SORT_NUMERIC);
			$recipient['direct_entitlements'] = array_values($recipient['direct_entitlements']);
		}
		unset($recipient);
		ksort($recipients, SORT_STRING);
		$item_ids = array_values(array_map('absint', array_keys($item_ids)));
		$admission_entry_ids = array_values(array_map('absint', array_keys($admission_entry_ids)));
		sort($item_ids, SORT_NUMERIC);
		sort($admission_entry_ids, SORT_NUMERIC);

		return array(
			'ok' => !empty($custom['ok']),
			'error' => (string) ($custom['error'] ?? ''),
			'recipients' => $recipients,
			'order_item_ids' => $item_ids,
			'admission_entry_ids' => $admission_entry_ids,
		);
	}
}

if (!function_exists('bvmgr_event_communication_preview_rows')) {
	function bvmgr_event_communication_preview_rows(array $preview): array
	{
		$audience = bvmgr_event_communication_audience_from_preview($preview);
		$rows = array();
		foreach ((array) ($audience['recipients'] ?? array()) as $recipient) {
			$entitlements = array();
			$order_ids = array();
			foreach ((array) ($recipient['orders'] ?? array()) as $order) {
				$order_id = absint($order['order_id'] ?? 0);
				if ($order_id > 0) {
					$order_ids[] = $order_id;
				}
				foreach ((array) ($order['entitlements'] ?? array()) as $entitlement) {
					$entitlements[] = $entitlement + array('order_id' => $order_id);
				}
			}
			foreach ((array) ($recipient['direct_entitlements'] ?? array()) as $entitlement) {
				$entitlements[] = $entitlement;
			}
			$rows[] = array(
				'recipient_id' => (string) ($recipient['recipient_id'] ?? ''),
				'customer_name' => (string) ($recipient['customer_name'] ?? ''),
				'customer_email' => (string) ($recipient['email_snapshot'] ?? ''),
				'email_valid' => !empty($recipient['email_valid']),
				'order_id' => (int) ($order_ids[0] ?? 0),
				'order_ids' => array_values(array_unique($order_ids)),
				'entitlements' => $entitlements,
			);
		}
		return array(
			'ok' => !empty($audience['ok']),
			'error' => (string) ($audience['error'] ?? ''),
			'rows' => $rows,
			'contacts' => array_values(array_filter(array_map(static function (array $row): string {
				return !empty($row['email_valid']) ? strtolower((string) ($row['customer_email'] ?? '')) : '';
			}, $rows))),
		);
	}
}

if (!function_exists('bvmgr_event_communication_initial_state')) {
	function bvmgr_event_communication_initial_state(array $recipient, int $actor_user_id, string $timestamp): array
	{
		return array(
			'included' => true,
			'written_notice' => array(
				'status' => 'pending',
				'status_at_utc' => $timestamp,
				'actor_user_id' => $actor_user_id,
				'recipient_email_snapshot' => (string) ($recipient['email_snapshot'] ?? ''),
				'subject' => '',
				'body' => '',
				'send_attempt_result' => '',
				'error_information' => empty($recipient['email_valid']) ? 'missing_or_invalid_email' : '',
				'exclusion_reason' => '',
				'manual_channel' => '',
				'manual_note' => '',
				'address_used' => '',
			),
			'attempts' => array(),
			'contact_log' => array(),
			'audit_log' => array(
				array(
					'action' => 'audience_snapshot_created',
					'actor_user_id' => $actor_user_id,
					'created_at_utc' => $timestamp,
				),
			),
		);
	}
}

if (!function_exists('bvmgr_event_communication_build_ledger')) {
	function bvmgr_event_communication_build_ledger(array $preview, string $operation_id, int $actor_user_id, string $source = 'automatic'): array
	{
		$audience_result = bvmgr_event_communication_audience_from_preview($preview);
		if (empty($audience_result['ok'])) {
			throw new RuntimeException((string) ($audience_result['error'] ?? 'Communication audience could not be created.')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal plain-text audience diagnostic; admin/CLI callers escape or render it through their context-specific output APIs.
		}
		$audience = (array) ($audience_result['recipients'] ?? array());
		$timestamp = bvmgr_event_communication_now_utc();
		$states = array();
		foreach ($audience as $recipient_id => $recipient) {
			$states[$recipient_id] = bvmgr_event_communication_initial_state((array) $recipient, $actor_user_id, $timestamp);
		}
		$ledger = array(
			'schema_version' => 1,
			'operation_id' => strtolower($operation_id),
			'event_plan_id' => absint($preview['plan_id'] ?? 0),
			'event_name' => sanitize_text_field((string) ($preview['plan_title'] ?? get_the_title(absint($preview['plan_id'] ?? 0)))),
			'venue_name' => sanitize_text_field((string) get_bloginfo('name')),
			'reason' => sanitize_key((string) ($preview['reason'] ?? '')),
			'mode' => sanitize_key((string) ($preview['mode'] ?? '')),
			'old_occurrence' => (array) ($preview['old'] ?? array()),
			'new_occurrence' => (array) ($preview['new'] ?? array()),
			'snapshot_at_utc' => $timestamp,
			'snapshot_actor_user_id' => absint($actor_user_id),
			'snapshot_source' => sanitize_key($source),
			'audience' => $audience,
			'audience_order_item_ids' => (array) ($audience_result['order_item_ids'] ?? array()),
			'audience_admission_entry_ids' => (array) ($audience_result['admission_entry_ids'] ?? array()),
			'recipient_states' => $states,
			'test_attempts' => array(),
		);
		$ledger['audience_fingerprint'] = bvmgr_event_communication_hash(array(
			'operation_id' => $ledger['operation_id'],
			'event_plan_id' => $ledger['event_plan_id'],
			'reason' => $ledger['reason'],
			'old_occurrence' => $ledger['old_occurrence'],
			'new_occurrence' => $ledger['new_occurrence'],
			'audience' => $ledger['audience'],
			'audience_order_item_ids' => $ledger['audience_order_item_ids'],
			'audience_admission_entry_ids' => $ledger['audience_admission_entry_ids'],
		));
		return $ledger;
	}
}

if (!function_exists('bvmgr_event_communication_get_ledger')) {
	function bvmgr_event_communication_get_ledger(int $plan_id, string $operation_id): array
	{
		$key = bvmgr_event_communication_meta_key($operation_id);
		if ($plan_id <= 0 || $key === '') {
			return array();
		}
		$ledger = get_post_meta($plan_id, $key, true);
		if (!is_array($ledger)
			|| absint($ledger['event_plan_id'] ?? 0) !== $plan_id
			|| !hash_equals(strtolower($operation_id), strtolower((string) ($ledger['operation_id'] ?? '')))) {
			return array();
		}
		return $ledger;
	}
}

if (!function_exists('bvmgr_event_communication_verify_audience')) {
	function bvmgr_event_communication_verify_audience(array $ledger): bool
	{
		$expected = (string) ($ledger['audience_fingerprint'] ?? '');
		if ($expected === '') {
			return false;
		}
		$actual = bvmgr_event_communication_hash(array(
			'operation_id' => (string) ($ledger['operation_id'] ?? ''),
			'event_plan_id' => absint($ledger['event_plan_id'] ?? 0),
			'reason' => (string) ($ledger['reason'] ?? ''),
			'old_occurrence' => (array) ($ledger['old_occurrence'] ?? array()),
			'new_occurrence' => (array) ($ledger['new_occurrence'] ?? array()),
			'audience' => (array) ($ledger['audience'] ?? array()),
			'audience_order_item_ids' => (array) ($ledger['audience_order_item_ids'] ?? array()),
			'audience_admission_entry_ids' => (array) ($ledger['audience_admission_entry_ids'] ?? array()),
		));
		return hash_equals($expected, $actual);
	}
}

if (!function_exists('bvmgr_event_communication_persist_new_ledger')) {
	function bvmgr_event_communication_persist_new_ledger(int $plan_id, array $ledger): bool
	{
		$operation_id = (string) ($ledger['operation_id'] ?? '');
		$key = bvmgr_event_communication_meta_key($operation_id);
		if ($plan_id <= 0 || $key === '' || !bvmgr_event_communication_verify_audience($ledger)) {
			return false;
		}
		$existing = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		if (!empty($existing)) {
			return hash_equals((string) ($existing['audience_fingerprint'] ?? ''), (string) ($ledger['audience_fingerprint'] ?? ''));
		}
		if (!add_post_meta($plan_id, $key, $ledger, true)) {
			return false;
		}
		$stored = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		return !empty($stored)
			&& bvmgr_event_communication_verify_audience($stored)
			&& hash_equals((string) $ledger['audience_fingerprint'], (string) ($stored['audience_fingerprint'] ?? ''));
	}
}

if (!function_exists('bvmgr_event_communication_persist_for_operation')) {
	function bvmgr_event_communication_persist_for_operation(int $plan_id, string $operation_id, array $preview, int $actor_user_id): array
	{
		$expected_item_ids = array_values(array_unique(array_filter(array_map(static function (array $row): int {
			return absint($row['order_item_id'] ?? 0);
		}, (array) ($preview['rows'] ?? array())))));
		$custom_count = max(0, (int) ($preview['counts']['custom_admission_rows'] ?? 0));
		if (empty($expected_item_ids) && $custom_count === 0) {
			return array('created' => false, 'recipient_count' => 0, 'order_count' => 0, 'status' => 'not_required');
		}
		if (!bvmgr_event_communication_operation_id_is_valid($operation_id)) {
			throw new RuntimeException('Communication audience persistence requires a valid occurrence operation ID.');
		}
		$ledger = bvmgr_event_communication_build_ledger($preview, $operation_id, $actor_user_id, 'automatic');
		$stored_item_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($ledger['audience_order_item_ids'] ?? array())))));
		sort($expected_item_ids, SORT_NUMERIC);
		sort($stored_item_ids, SORT_NUMERIC);
		if ($expected_item_ids !== $stored_item_ids) {
			throw new RuntimeException('Communication audience did not preserve every affected Woo order item.');
		}
		if ($custom_count !== count((array) ($ledger['audience_admission_entry_ids'] ?? array()))) {
			throw new RuntimeException('Communication audience did not preserve every affected custom admission record.');
		}
		if (empty($ledger['audience'])) {
			throw new RuntimeException('Affected customer entitlements exist, but no communication recipients could be preserved.');
		}
		if (!bvmgr_event_communication_persist_new_ledger($plan_id, $ledger)) {
			throw new RuntimeException('Durable communication audience persistence failed; the occurrence operation cannot complete.');
		}
		return bvmgr_event_communication_summary($ledger) + array('created' => true, 'status' => 'pending');
	}
}

if (!function_exists('bvmgr_event_communication_status_is_resolved')) {
	function bvmgr_event_communication_status_is_resolved(string $status): bool
	{
		return in_array(sanitize_key($status), array('sent_bvm', 'sent_manual', 'excluded'), true);
	}
}

if (!function_exists('bvmgr_event_communication_summary')) {
	function bvmgr_event_communication_summary(array $ledger): array
	{
		$summary = array(
			'recipient_count' => count((array) ($ledger['audience'] ?? array())),
			'order_count' => 0,
			'pending' => 0,
			'sent_bvm' => 0,
			'failed' => 0,
			'sent_manual' => 0,
			'excluded' => 0,
			'resolved' => 0,
			'unresolved' => 0,
			'missing_email' => 0,
		);
		$order_ids = array();
		foreach ((array) ($ledger['audience'] ?? array()) as $recipient_id => $recipient) {
			foreach ((array) ($recipient['orders'] ?? array()) as $order) {
				$order_id = absint($order['order_id'] ?? 0);
				if ($order_id > 0) {
					$order_ids[$order_id] = true;
				}
			}
			if (empty($recipient['email_valid'])) {
				$summary['missing_email']++;
			}
			$state = (array) (($ledger['recipient_states'][$recipient_id] ?? array()));
			$status = sanitize_key((string) ($state['written_notice']['status'] ?? 'pending'));
			if (!array_key_exists($status, $summary)) {
				$status = 'pending';
			}
			$summary[$status]++;
			if (bvmgr_event_communication_status_is_resolved($status)) {
				$summary['resolved']++;
			} else {
				$summary['unresolved']++;
			}
		}
		$summary['order_count'] = count($order_ids);
		return $summary;
	}
}

if (!function_exists('bvmgr_event_communication_operation_summary')) {
	function bvmgr_event_communication_operation_summary(int $plan_id, string $operation_id): array
	{
		$ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		return empty($ledger) ? array() : bvmgr_event_communication_summary($ledger);
	}
}

if (!function_exists('bvmgr_event_communication_save_mutation')) {
	function bvmgr_event_communication_save_mutation(int $plan_id, string $operation_id, array $before, array $after): bool
	{
		$key = bvmgr_event_communication_meta_key($operation_id);
		if ($key === ''
			|| !bvmgr_event_communication_verify_audience($before)
			|| !bvmgr_event_communication_verify_audience($after)
			|| !hash_equals((string) $before['audience_fingerprint'], (string) $after['audience_fingerprint'])) {
			return false;
		}
		if (!update_post_meta($plan_id, $key, $after, $before)) {
			return false;
		}
		$stored = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		return !empty($stored)
			&& bvmgr_event_communication_verify_audience($stored)
			&& bvmgr_event_communication_hash($stored) === bvmgr_event_communication_hash($after);
	}
}

if (!function_exists('bvmgr_event_communication_actor_can_manage')) {
	function bvmgr_event_communication_actor_can_manage(int $plan_id, int $actor_user_id): bool
	{
		return $plan_id > 0 && $actor_user_id > 0 && user_can($actor_user_id, 'edit_post', $plan_id);
	}
}

if (!function_exists('bvmgr_event_communication_mutate_recipient')) {
	function bvmgr_event_communication_mutate_recipient(int $plan_id, string $operation_id, string $recipient_id, string $action, int $actor_user_id, array $data = array()): array
	{
		$result = array('ok' => false, 'message' => '', 'ledger' => array());
		if (!bvmgr_event_communication_actor_can_manage($plan_id, $actor_user_id)) {
			$result['message'] = 'An authorized Event Plan administrator is required.';
			return $result;
		}
		$before = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		if (empty($before) || !isset($before['audience'][$recipient_id], $before['recipient_states'][$recipient_id])) {
			$result['message'] = 'Communication recipient was not found.';
			return $result;
		}
		$after = $before;
		$state =& $after['recipient_states'][$recipient_id];
		$now = bvmgr_event_communication_now_utc();
		$action = sanitize_key($action);
		$current_status = sanitize_key((string) ($state['written_notice']['status'] ?? 'pending'));

		if ($action === 'exclude') {
			$reason = sanitize_textarea_field((string) ($data['reason'] ?? ''));
			if (empty($data['confirmed']) || $reason === '') {
				$result['message'] = 'Exclusion requires explicit confirmation and a reason.';
				return $result;
			}
			if (bvmgr_event_communication_status_is_resolved($current_status)) {
				$result['message'] = 'A resolved written notice cannot be replaced by an exclusion.';
				return $result;
			}
			$state['included'] = false;
			$state['written_notice'] = array_merge((array) $state['written_notice'], array(
				'status' => 'excluded',
				'status_at_utc' => $now,
				'actor_user_id' => $actor_user_id,
				'exclusion_reason' => $reason,
				'error_information' => '',
			));
		} elseif ($action === 'reinclude') {
			if ($current_status !== 'excluded' || !empty($state['included'])) {
				$result['message'] = 'Only an intentionally excluded recipient can be re-included.';
				return $result;
			}
			$state['included'] = true;
			$state['written_notice'] = array_merge((array) $state['written_notice'], array(
				'status' => 'pending',
				'status_at_utc' => $now,
				'actor_user_id' => $actor_user_id,
				'exclusion_reason' => '',
				'error_information' => empty($before['audience'][$recipient_id]['email_valid']) ? 'missing_or_invalid_email' : '',
			));
		} elseif ($action === 'contact') {
			$method = sanitize_key((string) ($data['method'] ?? ''));
			if (!in_array($method, array('phone', 'in_person', 'other'), true)) {
				$result['message'] = 'Contact method must be phone, in_person, or other.';
				return $result;
			}
			$state['contact_log'][] = array(
				'method' => $method,
				'note' => sanitize_textarea_field((string) ($data['note'] ?? '')),
				'actor_user_id' => $actor_user_id,
				'created_at_utc' => $now,
			);
		} elseif ($action === 'manual') {
			$channel = sanitize_key((string) ($data['channel'] ?? ''));
			if (!in_array($channel, array('email_outside_bvm', 'letter', 'other_written'), true)) {
				$result['message'] = 'A valid manual written-notice channel is required.';
				return $result;
			}
			if (bvmgr_event_communication_status_is_resolved($current_status)) {
				$result['message'] = 'This written notice is already resolved and was not overwritten.';
				return $result;
			}
			$state['written_notice'] = array_merge((array) $state['written_notice'], array(
				'status' => 'sent_manual',
				'status_at_utc' => $now,
				'actor_user_id' => $actor_user_id,
				'subject' => '',
				'body' => '',
				'send_attempt_result' => 'recorded_manual',
				'error_information' => '',
				'exclusion_reason' => '',
				'manual_channel' => $channel,
				'manual_note' => sanitize_textarea_field((string) ($data['note'] ?? '')),
				'address_used' => sanitize_text_field((string) ($data['address_used'] ?? '')),
			));
		} else {
			$result['message'] = 'Unknown communication recipient action.';
			return $result;
		}

		$state['audit_log'][] = array(
			'action' => $action,
			'actor_user_id' => $actor_user_id,
			'created_at_utc' => $now,
			'reason' => $action === 'exclude' ? sanitize_textarea_field((string) ($data['reason'] ?? '')) : '',
		);
		unset($state);
		if (!bvmgr_event_communication_save_mutation($plan_id, $operation_id, $before, $after)) {
			$result['message'] = 'Communication state changed concurrently or could not be persisted.';
			return $result;
		}
		$result['ok'] = true;
		$result['message'] = 'Communication recipient state updated.';
		$result['ledger'] = $after;
		return $result;
	}
}

if (!function_exists('bvmgr_event_communication_default_message')) {
	function bvmgr_event_communication_default_message(array $ledger): array
	{
		$event_name = sanitize_text_field((string) ($ledger['event_name'] ?? 'Event'));
		$venue_name = sanitize_text_field((string) ($ledger['venue_name'] ?? ''));
		$old_start = function_exists('bvmgr_event_occurrence_parse_local')
			? bvmgr_event_occurrence_parse_local((string) ($ledger['old_occurrence']['start_local'] ?? ''))
			: null;
		$new_start = function_exists('bvmgr_event_occurrence_parse_local')
			? bvmgr_event_occurrence_parse_local((string) ($ledger['new_occurrence']['start_local'] ?? ''))
			: null;
		$timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
		$old_date = $old_start instanceof DateTimeImmutable ? wp_date('l, F j, Y', $old_start->getTimestamp(), $timezone) : (string) ($ledger['old_occurrence']['start_local'] ?? '');
		$new_date = $new_start instanceof DateTimeImmutable ? wp_date('l, F j, Y', $new_start->getTimestamp(), $timezone) : (string) ($ledger['new_occurrence']['start_local'] ?? '');
		$event_time = $new_start instanceof DateTimeImmutable ? wp_date('g:i a', $new_start->getTimestamp(), $timezone) : '';
		$reason = sanitize_key((string) ($ledger['reason'] ?? ''));
		if ($reason === 'date_correction') {
			/* translators: %s: Event name. */
			$subject = sprintf(__('Important date correction: %s', 'backstage-venue-manager'), $event_name);
			$explanation = sprintf(
				/* translators: 1: Old event date, 2: Correct event date. */
				__('When you purchased, this event was incorrectly listed as %1$s. The correct event date is %2$s.', 'backstage-venue-manager'),
				$old_date,
				$new_date
			);
		} else {
			/* translators: %s: Event name. */
			$subject = sprintf(__('Event rescheduled: %s', 'backstage-venue-manager'), $event_name);
			$explanation = sprintf(
				/* translators: 1: Old event date, 2: New event date. */
				__('This event has been moved from %1$s to %2$s.', 'backstage-venue-manager'),
				$old_date,
				$new_date
			);
		}
		$lines = array(
			__('Hello,', 'backstage-venue-manager'),
			'',
			$explanation,
			'',
			/* translators: %s: Event name. */
			sprintf(__('Event: %s', 'backstage-venue-manager'), $event_name),
			/* translators: %s: New event date. */
			sprintf(__('New date: %s', 'backstage-venue-manager'), $new_date),
		);
		if ($event_time !== '') {
			/* translators: %s: New event time. */
			$lines[] = sprintf(__('Event time: %s', 'backstage-venue-manager'), $event_time);
		}
		$lines[] = '';
		$lines[] = __('Your existing tickets or reservation have already been transferred. You do not need to repurchase anything.', 'backstage-venue-manager');
		$lines[] = __('Please contact the venue if the new date does not work for you.', 'backstage-venue-manager');
		if ($venue_name !== '') {
			$lines[] = '';
			$lines[] = $venue_name;
		}
		return array('subject' => sanitize_text_field($subject), 'body' => implode("\n", $lines));
	}
}

if (!function_exists('bvmgr_event_communication_mail_send')) {
	function bvmgr_event_communication_mail_send(string $to, string $subject, string $body): array
	{
		$message = array('to' => $to, 'subject' => $subject, 'body_text' => $body, 'body_html' => '');
		$filtered = apply_filters('bvmgr_event_communication_mail_transport', null, $message);
		if (is_array($filtered)) {
			return array(
				'success' => !empty($filtered['success']),
				'provider' => sanitize_key((string) ($filtered['provider'] ?? 'test_transport')),
				'provider_message_id' => sanitize_text_field((string) ($filtered['provider_message_id'] ?? '')),
				'error' => sanitize_textarea_field((string) ($filtered['error'] ?? $filtered['error_message'] ?? '')),
			);
		}
		if (function_exists('bvmgr_notify_provider_core_email_send')) {
			$provider = bvmgr_notify_provider_core_email_send($message);
			return array(
				'success' => !empty($provider['success']),
				'provider' => 'core_email',
				'provider_message_id' => sanitize_text_field((string) ($provider['provider_message_id'] ?? '')),
				'error' => sanitize_textarea_field((string) ($provider['error_message'] ?? '')),
			);
		}
		$sent = (bool) wp_mail($to, $subject, $body);
		return array('success' => $sent, 'provider' => 'wp_mail', 'provider_message_id' => '', 'error' => $sent ? '' : 'wp_mail_false');
	}
}

if (!function_exists('bvmgr_event_communication_has_unfinished_attempt')) {
	function bvmgr_event_communication_has_unfinished_attempt(array $state): bool
	{
		foreach (array_reverse((array) ($state['attempts'] ?? array())) as $attempt) {
			if ((string) ($attempt['completed_at_utc'] ?? '') !== '') {
				return false;
			}
			if ((string) ($attempt['started_at_utc'] ?? '') !== '') {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('bvmgr_event_communication_send_recipient')) {
	function bvmgr_event_communication_send_recipient(int $plan_id, string $operation_id, string $recipient_id, int $actor_user_id, string $subject, string $body, bool $allow_resend = false): array
	{
		$result = array('ok' => false, 'accepted' => false, 'message' => '', 'attempt_id' => '');
		if (!bvmgr_event_communication_actor_can_manage($plan_id, $actor_user_id)) {
			$result['message'] = 'An authorized Event Plan administrator is required.';
			return $result;
		}
		$subject = sanitize_text_field($subject);
		$body = sanitize_textarea_field($body);
		if ($subject === '' || $body === '') {
			$result['message'] = 'Subject and message body are required.';
			return $result;
		}
		$before = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		$recipient = (array) ($before['audience'][$recipient_id] ?? array());
		$state = (array) ($before['recipient_states'][$recipient_id] ?? array());
		if (empty($before) || empty($recipient) || empty($state)) {
			$result['message'] = 'Communication recipient was not found.';
			return $result;
		}
		$status = sanitize_key((string) ($state['written_notice']['status'] ?? 'pending'));
		if (empty($state['included'])) {
			$result['message'] = 'Excluded recipients cannot be sent by BVM.';
			return $result;
		}
		if (bvmgr_event_communication_has_unfinished_attempt($state)) {
			$result['message'] = 'A prior send attempt has no durable completion result and requires review.';
			return $result;
		}
		if ($allow_resend && !bvmgr_event_communication_status_is_resolved($status)) {
			$result['message'] = 'Explicit resend is available only after a resolved written notice.';
			return $result;
		}
		if (!$allow_resend && !in_array($status, array('pending', 'failed'), true)) {
			$result['message'] = 'This recipient is already resolved and normal sending will not duplicate the notice.';
			return $result;
		}
		$email = sanitize_email((string) ($recipient['email_snapshot'] ?? ''));
		if ($email === '' || !is_email($email)) {
			$result['message'] = 'Written notice requires manual contact because the stored email is missing or invalid.';
			return $result;
		}

		$attempt_id = wp_generate_uuid4();
		$result['attempt_id'] = $attempt_id;
		$started = bvmgr_event_communication_now_utc();
		$prepared = $before;
		$prepared['recipient_states'][$recipient_id]['attempts'][] = array(
			'attempt_id' => $attempt_id,
			'type' => $allow_resend ? 'explicit_resend' : 'send',
			'started_at_utc' => $started,
			'completed_at_utc' => '',
			'actor_user_id' => $actor_user_id,
			'recipient_email_snapshot' => $email,
			'subject' => $subject,
			'body' => $body,
			'result' => 'initiated',
			'provider' => '',
			'provider_message_id' => '',
			'error_information' => '',
		);
		if (!bvmgr_event_communication_save_mutation($plan_id, $operation_id, $before, $prepared)) {
			$result['message'] = 'The send attempt could not be recorded before transmission; no email was sent.';
			return $result;
		}

		$mail = bvmgr_event_communication_mail_send($email, $subject, $body);
		$current = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		$final = $current;
		$attempt_index = null;
		foreach ((array) ($final['recipient_states'][$recipient_id]['attempts'] ?? array()) as $index => $attempt) {
			if (hash_equals($attempt_id, (string) ($attempt['attempt_id'] ?? ''))) {
				$attempt_index = $index;
				break;
			}
		}
		if ($attempt_index === null) {
			$result['message'] = 'Email transport returned, but the durable attempt record could not be found. Review before retrying.';
			return $result;
		}
		$accepted = !empty($mail['success']);
		$completed = bvmgr_event_communication_now_utc();
		$final['recipient_states'][$recipient_id]['attempts'][$attempt_index] = array_merge(
			(array) $final['recipient_states'][$recipient_id]['attempts'][$attempt_index],
			array(
				'completed_at_utc' => $completed,
				'result' => $accepted ? 'accepted' : 'failed',
				'provider' => sanitize_key((string) ($mail['provider'] ?? '')),
				'provider_message_id' => sanitize_text_field((string) ($mail['provider_message_id'] ?? '')),
				'error_information' => sanitize_textarea_field((string) ($mail['error'] ?? '')),
			)
		);
		$final['recipient_states'][$recipient_id]['written_notice'] = array_merge(
			(array) $final['recipient_states'][$recipient_id]['written_notice'],
			array(
				'status' => $accepted ? 'sent_bvm' : 'failed',
				'status_at_utc' => $completed,
				'actor_user_id' => $actor_user_id,
				'recipient_email_snapshot' => $email,
				'subject' => $subject,
				'body' => $body,
				'send_attempt_result' => $accepted ? 'accepted' : 'failed',
				'error_information' => sanitize_textarea_field((string) ($mail['error'] ?? '')),
				'exclusion_reason' => '',
				'manual_channel' => '',
				'manual_note' => '',
			)
		);
		$final['recipient_states'][$recipient_id]['audit_log'][] = array(
			'action' => $allow_resend ? 'explicit_resend_completed' : 'send_completed',
			'actor_user_id' => $actor_user_id,
			'created_at_utc' => $completed,
			'attempt_id' => $attempt_id,
			'result' => $accepted ? 'accepted' : 'failed',
		);
		if (!bvmgr_event_communication_save_mutation($plan_id, $operation_id, $current, $final)) {
			$result['accepted'] = $accepted;
			$result['message'] = 'Email transport returned, but its final status could not be persisted. Review the initiated attempt before any resend.';
			return $result;
		}
		$result['ok'] = $accepted;
		$result['accepted'] = $accepted;
		$result['message'] = $accepted ? 'WordPress accepted the customer notice for sending.' : 'Customer notice was not accepted; the recipient remains retryable.';
		return $result;
	}
}

if (!function_exists('bvmgr_event_communication_send_bulk')) {
	function bvmgr_event_communication_send_bulk(int $plan_id, string $operation_id, int $actor_user_id, string $subject, string $body, string $mode = 'pending'): array
	{
		$mode = sanitize_key($mode);
		$eligible_statuses = $mode === 'failed' ? array('failed') : array('pending');
		$result = array('ok' => true, 'attempted' => 0, 'accepted' => 0, 'failed' => 0, 'skipped' => 0, 'results' => array());
		$ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		foreach ((array) ($ledger['audience'] ?? array()) as $recipient_id => $recipient) {
			$state = (array) ($ledger['recipient_states'][$recipient_id] ?? array());
			$status = sanitize_key((string) ($state['written_notice']['status'] ?? 'pending'));
			if (empty($state['included']) || !in_array($status, $eligible_statuses, true) || empty($recipient['email_valid'])) {
				$result['skipped']++;
				continue;
			}
			$result['attempted']++;
			$sent = bvmgr_event_communication_send_recipient($plan_id, $operation_id, (string) $recipient_id, $actor_user_id, $subject, $body, false);
			$result['results'][$recipient_id] = $sent;
			if (empty($sent['ok'])) {
				$result['ok'] = false;
			}
			if (!empty($sent['accepted'])) {
				$result['accepted']++;
			} else {
				$result['failed']++;
			}
		}
		return $result;
	}
}

if (!function_exists('bvmgr_event_communication_send_test')) {
	function bvmgr_event_communication_send_test(int $plan_id, string $operation_id, int $actor_user_id, string $test_email, string $subject, string $body): array
	{
		$result = array('ok' => false, 'message' => '');
		if (!bvmgr_event_communication_actor_can_manage($plan_id, $actor_user_id)) {
			$result['message'] = 'An authorized Event Plan administrator is required.';
			return $result;
		}
		$email = sanitize_email($test_email);
		if ($email === '' || !is_email($email)) {
			$result['message'] = 'A valid administrator test email is required.';
			return $result;
		}
		$before = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		if (empty($before)) {
			$result['message'] = 'Communication ledger was not found.';
			return $result;
		}
		$mail = bvmgr_event_communication_mail_send($email, '[TEST] ' . sanitize_text_field($subject), sanitize_textarea_field($body));
		$after = $before;
		$after['test_attempts'][] = array(
			'attempt_id' => wp_generate_uuid4(),
			'created_at_utc' => bvmgr_event_communication_now_utc(),
			'actor_user_id' => $actor_user_id,
			'recipient_email_snapshot' => $email,
			'subject' => '[TEST] ' . sanitize_text_field($subject),
			'body' => sanitize_textarea_field($body),
			'result' => !empty($mail['success']) ? 'accepted' : 'failed',
			'provider' => sanitize_key((string) ($mail['provider'] ?? '')),
			'error_information' => sanitize_textarea_field((string) ($mail['error'] ?? '')),
		);
		if (!bvmgr_event_communication_save_mutation($plan_id, $operation_id, $before, $after)) {
			$result['message'] = 'Test email returned, but its audit record could not be persisted.';
			return $result;
		}
		$result['ok'] = !empty($mail['success']);
		$result['message'] = $result['ok'] ? 'WordPress accepted the test notice.' : 'The test notice failed.';
		return $result;
	}
}

if (!function_exists('bvmgr_event_communication_mark_manual_bulk')) {
	function bvmgr_event_communication_mark_manual_bulk(int $plan_id, string $operation_id, int $actor_user_id, string $channel, string $note = '', string $recipient_id = ''): array
	{
		$result = array('ok' => true, 'updated' => 0, 'skipped' => 0, 'results' => array());
		$ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		foreach ((array) ($ledger['audience'] ?? array()) as $candidate_id => $recipient) {
			if ($recipient_id !== '' && !hash_equals($recipient_id, (string) $candidate_id)) {
				continue;
			}
			$state = (array) ($ledger['recipient_states'][$candidate_id] ?? array());
			$status = sanitize_key((string) ($state['written_notice']['status'] ?? 'pending'));
			if (empty($state['included']) || bvmgr_event_communication_status_is_resolved($status)) {
				$result['skipped']++;
				continue;
			}
			$changed = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, (string) $candidate_id, 'manual', $actor_user_id, array(
				'channel' => $channel,
				'note' => $note,
				'address_used' => (string) ($recipient['email_snapshot'] ?? ''),
			));
			$result['results'][$candidate_id] = $changed;
			if (!empty($changed['ok'])) {
				$result['updated']++;
			} else {
				$result['ok'] = false;
			}
		}
		return $result;
	}
}

if (!function_exists('bvmgr_event_communication_history_entry')) {
	function bvmgr_event_communication_history_entry(int $plan_id, string $operation_id): array
	{
		foreach (array_reverse(bvmgr_event_occurrence_history($plan_id)) as $entry) {
			if (is_array($entry) && hash_equals(strtolower($operation_id), strtolower((string) ($entry['operation_id'] ?? '')))) {
				return $entry;
			}
		}
		return array();
	}
}

if (!function_exists('bvmgr_event_communication_bootstrap_preview')) {
	function bvmgr_event_communication_bootstrap_preview(int $plan_id, string $operation_id): array
	{
		$operation_id = strtolower(trim($operation_id));
		$preview = array(
			'allowed' => false,
			'plan_id' => absint($plan_id),
			'plan_title' => $plan_id > 0 ? (string) get_the_title($plan_id) : '',
			'operation_id' => $operation_id,
			'reason' => '',
			'mode' => 'bootstrap',
			'old' => array(),
			'new' => array(),
			'rows' => array(),
			'counts' => array('orders' => 0, 'line_items' => 0, 'customers' => 0, 'custom_admission_rows' => 0),
			'notification_rows' => array(),
			'ambiguities' => array(),
			'warnings' => array(),
			'fingerprint' => '',
			'already_exists' => false,
		);
		if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
			$preview['ambiguities'][] = 'Event Plan not found.';
			return $preview;
		}
		if (!bvmgr_event_communication_operation_id_is_valid($operation_id)) {
			$preview['ambiguities'][] = 'A valid occurrence operation ID is required.';
			return $preview;
		}
		$history = bvmgr_event_communication_history_entry($plan_id, $operation_id);
		if (empty($history)) {
			$preview['ambiguities'][] = 'The requested operation is not recorded for this Event Plan.';
			return $preview;
		}
		$preview['reason'] = sanitize_key((string) ($history['reason'] ?? ''));
		$preview['old'] = array(
			'start_local' => (string) ($history['old_start_local'] ?? ''),
			'end_local' => (string) ($history['old_end_local'] ?? ''),
			'start_utc' => (string) ($history['old_start_utc'] ?? ''),
			'end_utc' => (string) ($history['old_end_utc'] ?? ''),
			'timezone' => (string) ($history['timezone'] ?? ''),
		);
		$preview['new'] = array(
			'start_local' => (string) ($history['new_start_local'] ?? ''),
			'end_local' => (string) ($history['new_end_local'] ?? ''),
			'start_utc' => (string) ($history['new_start_utc'] ?? ''),
			'end_utc' => (string) ($history['new_end_utc'] ?? ''),
			'timezone' => (string) ($history['timezone'] ?? ''),
		);
		$preview['already_exists'] = !empty(bvmgr_event_communication_get_ledger($plan_id, $operation_id));
		$impact = (array) ($history['impact_counts'] ?? array());
		if ((int) ($impact['custom_admission_rows'] ?? 0) > 0) {
			$preview['ambiguities'][] = 'This historical operation included custom admissions that were not operation-stamped; deterministic bootstrap is unavailable.';
		}

		$order_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($history['order_ids'] ?? array())))));
		$result = class_exists('BVMGR_Ticket_Revenue_Service')
			? BVMGR_Ticket_Revenue_Service::get_sales_result(array(
				'event_plan_ids' => array($plan_id),
				'order_ids' => $order_ids,
				'order_statuses' => array('pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'),
				'include_unresolved' => true,
				'include_refunded_lines' => true,
			))
			: array('rows' => array());
		$stamped_order_ids = array();
		foreach ((array) ($result['rows'] ?? array()) as $row) {
			$item_id = absint($row['order_item_id'] ?? 0);
			if ($item_id <= 0 || !hash_equals($operation_id, strtolower(trim((string) wc_get_order_item_meta($item_id, '_vms_occurrence_operation_id', true))))) {
				continue;
			}
			if ((int) ($row['refunded_qty'] ?? 0) > 0 || !empty($row['is_refunded'])) {
				$preview['ambiguities'][] = sprintf('Order item %d has refund state that prevents deterministic operation-time quantity reconstruction.', $item_id);
				continue;
			}
			$effective_start = (string) wc_get_order_item_meta($item_id, '_vms_effective_event_start_local', true);
			if ((string) ($history['new_start_local'] ?? '') !== $effective_start) {
				$preview['ambiguities'][] = sprintf('Order item %d no longer carries the operation target occurrence.', $item_id);
				continue;
			}
			$row['effective_quantity'] = max(0, (int) ($row['qty'] ?? 0));
			$preview['rows'][] = $row;
			$stamped_order_ids[absint($row['order_id'] ?? 0)] = true;
		}
		$expected_line_items = max(0, (int) ($impact['line_items'] ?? 0));
		if ($expected_line_items !== count($preview['rows'])) {
			$preview['ambiguities'][] = sprintf('Operation audit expects %1$d affected line items, but %2$d deterministic operation-stamped items were found.', $expected_line_items, count($preview['rows']));
		}
		$found_order_ids = array_values(array_filter(array_map('absint', array_keys($stamped_order_ids))));
		sort($order_ids, SORT_NUMERIC);
		sort($found_order_ids, SORT_NUMERIC);
		if ($order_ids !== $found_order_ids) {
			$preview['ambiguities'][] = 'Operation-stamped item orders do not exactly match the occurrence audit order IDs.';
		}
		$audience = bvmgr_event_communication_preview_rows($preview);
		$preview['notification_rows'] = (array) ($audience['rows'] ?? array());
		$preview['counts']['orders'] = count($found_order_ids);
		$preview['counts']['line_items'] = count($preview['rows']);
		$preview['counts']['customers'] = count($preview['notification_rows']);
		$preview['allowed'] = empty($preview['ambiguities']);
		$preview['fingerprint'] = bvmgr_event_communication_hash(array(
			'plan_id' => $plan_id,
			'operation_id' => $operation_id,
			'history' => $history,
			'rows' => $preview['rows'],
			'notification_rows' => $preview['notification_rows'],
			'counts' => $preview['counts'],
			'ambiguities' => $preview['ambiguities'],
		));
		return $preview;
	}
}

if (!function_exists('bvmgr_event_communication_bootstrap_apply')) {
	function bvmgr_event_communication_bootstrap_apply(int $plan_id, string $operation_id, int $actor_user_id, string $expected_fingerprint): array
	{
		global $wpdb;
		$result = array('ok' => false, 'noop' => false, 'rolled_back' => false, 'message' => '', 'summary' => array());
		if (!bvmgr_event_communication_actor_can_manage($plan_id, $actor_user_id)) {
			$result['message'] = 'An authorized Event Plan administrator is required.';
			return $result;
		}
		$preview = bvmgr_event_communication_bootstrap_preview($plan_id, $operation_id);
		if (!empty($preview['already_exists'])) {
			$result['ok'] = true;
			$result['noop'] = true;
			$result['message'] = 'Communication ledger already exists; no recipients were duplicated.';
			$result['summary'] = bvmgr_event_communication_operation_summary($plan_id, $operation_id);
			return $result;
		}
		if (empty($preview['allowed']) || $expected_fingerprint === '' || !hash_equals($expected_fingerprint, (string) ($preview['fingerprint'] ?? ''))) {
			$result['message'] = 'Communication bootstrap is blocked or its approved preview is stale.';
			return $result;
		}
		$transaction_started = false;
		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bootstrap audience persistence must be atomic and uses explicit transaction control.
			if ($wpdb->query('START TRANSACTION') === false) {
				throw new RuntimeException('Database transaction could not be started.');
			}
			$transaction_started = true;
			$revalidated = bvmgr_event_communication_bootstrap_preview($plan_id, $operation_id);
			if (empty($revalidated['allowed']) || !hash_equals($expected_fingerprint, (string) ($revalidated['fingerprint'] ?? ''))) {
				throw new RuntimeException('Operation-specific audience evidence changed after preview.');
			}
			$ledger = bvmgr_event_communication_build_ledger($revalidated, $operation_id, $actor_user_id, 'retroactive_bootstrap');
			if (empty($ledger['audience']) || !bvmgr_event_communication_persist_new_ledger($plan_id, $ledger)) {
				throw new RuntimeException('Retroactive communication ledger persistence failed.');
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit follows exact durable audience verification.
			if ($wpdb->query('COMMIT') === false) {
				throw new RuntimeException('Database commit failed.');
			}
			$transaction_started = false;
			$result['ok'] = true;
			$result['message'] = 'Operation communication ledger bootstrapped without sending email.';
			$result['summary'] = bvmgr_event_communication_summary($ledger);
			return $result;
		} catch (Throwable $throwable) {
			if ($transaction_started) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Every failed bootstrap must roll back the audience write.
				$wpdb->query('ROLLBACK');
				$result['rolled_back'] = true;
			}
			$result['message'] = $throwable->getMessage();
			return $result;
		}
	}
}

if (!function_exists('bvmgr_event_communication_export_rows')) {
	function bvmgr_event_communication_export_rows(array $ledger): array
	{
		$rows = array();
		foreach ((array) ($ledger['audience'] ?? array()) as $recipient_id => $recipient) {
			$state = (array) ($ledger['recipient_states'][$recipient_id] ?? array());
			$order_numbers = array();
			$entitlements = array();
			foreach ((array) ($recipient['orders'] ?? array()) as $order) {
				$order_numbers[] = '#' . sanitize_text_field((string) ($order['order_number'] ?? $order['order_id'] ?? ''));
				foreach ((array) ($order['entitlements'] ?? array()) as $entitlement) {
					$entitlements[] = sanitize_text_field((string) ($entitlement['label'] ?? 'Entitlement')) . ' ×' . max(0, (int) ($entitlement['quantity'] ?? 0));
				}
			}
			foreach ((array) ($recipient['direct_entitlements'] ?? array()) as $entitlement) {
				$entitlements[] = sanitize_text_field((string) ($entitlement['label'] ?? 'Admission')) . ' ×' . max(0, (int) ($entitlement['quantity'] ?? 0));
			}
			$rows[] = array(
				'name' => (string) ($recipient['customer_name'] ?? ''),
				'email' => (string) ($recipient['email_snapshot'] ?? ''),
				'affected_orders' => implode(', ', array_values(array_unique($order_numbers))),
				'affected_entitlements' => implode('; ', $entitlements),
				'written_notice_status' => sanitize_key((string) ($state['written_notice']['status'] ?? 'pending')),
			);
		}
		return $rows;
	}
}
