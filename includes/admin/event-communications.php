<?php

defined('ABSPATH') || exit;

if (!function_exists('bvmgr_event_communication_admin_notice_key')) {
	function bvmgr_event_communication_admin_notice_key(int $plan_id, int $user_id = 0): string
	{
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		return 'bvmgr_event_comm_notice_' . absint($user_id) . '_' . absint($plan_id);
	}
}

if (!function_exists('bvmgr_event_communication_admin_bootstrap_key')) {
	function bvmgr_event_communication_admin_bootstrap_key(int $plan_id, string $operation_id, int $user_id = 0): string
	{
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		return 'bvmgr_event_comm_boot_' . absint($user_id) . '_' . absint($plan_id) . '_' . substr(hash('sha256', $operation_id), 0, 12);
	}
}

if (!function_exists('bvmgr_event_communication_admin_set_notice')) {
	function bvmgr_event_communication_admin_set_notice(int $plan_id, bool $ok, string $message): void
	{
		set_transient(bvmgr_event_communication_admin_notice_key($plan_id), array(
			'ok' => $ok,
			'message' => sanitize_text_field($message),
		), 15 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('bvmgr_event_communication_admin_url')) {
	function bvmgr_event_communication_admin_url(int $plan_id, string $operation_id = ''): string
	{
		$url = get_edit_post_link($plan_id, 'raw');
		if (!is_string($url) || $url === '') {
			$url = admin_url('post.php?post=' . absint($plan_id) . '&action=edit');
		}
		if ($operation_id !== '') {
			$url = add_query_arg('bvmgr_communication_operation', rawurlencode($operation_id), $url);
		}
		return $url . '#bvmgr-event-communications';
	}
}

if (!function_exists('bvmgr_event_communication_status_label')) {
	function bvmgr_event_communication_status_label(string $status): string
	{
		$labels = array(
			'pending' => __('Pending', 'backstage-venue-manager'),
			'sent_bvm' => __('Sent by BVM (accepted)', 'backstage-venue-manager'),
			'failed' => __('Failed', 'backstage-venue-manager'),
			'sent_manual' => __('Sent manually / outside BVM', 'backstage-venue-manager'),
			'excluded' => __('Intentionally excluded', 'backstage-venue-manager'),
		);
		$status = sanitize_key($status);
		return (string) ($labels[$status] ?? $labels['pending']);
	}
}

if (!function_exists('bvmgr_event_communication_summary_text')) {
	function bvmgr_event_communication_summary_text(array $summary): string
	{
		$total = (int) ($summary['recipient_count'] ?? 0);
		$resolved = (int) ($summary['resolved'] ?? 0);
		if ($total > 0 && $resolved === $total) {
			return sprintf(
				/* translators: 1: Resolved written notices, 2: Total written notices. */
				__('%1$d of %2$d written notices completed', 'backstage-venue-manager'),
				$resolved,
				$total
			);
		}
		return sprintf(
			/* translators: 1: Sent by BVM, 2: Sent manually, 3: Failed, 4: Pending, 5: Intentionally excluded. */
			__('%1$d sent by BVM / %2$d manual / %3$d failed / %4$d pending / %5$d excluded', 'backstage-venue-manager'),
			(int) ($summary['sent_bvm'] ?? 0),
			(int) ($summary['sent_manual'] ?? 0),
			(int) ($summary['failed'] ?? 0),
			(int) ($summary['pending'] ?? 0),
			(int) ($summary['excluded'] ?? 0)
		);
	}
}

if (!function_exists('bvmgr_event_communication_render_action_fields')) {
	function bvmgr_event_communication_render_action_fields(int $plan_id, string $operation_id, string $communication_action, string $recipient_id = ''): string
	{
		static $forms = array();
		$communication_action = sanitize_key($communication_action);
		if ($communication_action === 'render_registered_forms') {
			foreach ($forms as $form) {
				$form = is_array($form) ? $form : array();
				$form_id = (string) ($form['form_id'] ?? '');
				$registered_plan_id = absint($form['plan_id'] ?? 0);
				$registered_operation_id = (string) ($form['operation_id'] ?? '');
				$registered_action = sanitize_key((string) ($form['communication_action'] ?? ''));
				$registered_recipient_id = sanitize_key((string) ($form['recipient_id'] ?? ''));
				if ($form_id === '' || $registered_plan_id <= 0 || $registered_operation_id === '' || $registered_action === '') {
					continue;
				}

				echo '<form id="' . esc_attr($form_id) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
				if ($registered_action === 'export') {
					echo '<input type="hidden" name="action" value="bvmgr_event_communication_export">';
					echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $registered_plan_id) . '">';
					echo '<input type="hidden" name="operation_id" value="' . esc_attr($registered_operation_id) . '">';
					wp_nonce_field('bvmgr_event_communication_export_' . $registered_plan_id . '_' . $registered_operation_id, 'bvmgr_event_communication_export_nonce');
				} else {
					echo '<input type="hidden" name="action" value="bvmgr_event_communication_action">';
					echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $registered_plan_id) . '">';
					echo '<input type="hidden" name="operation_id" value="' . esc_attr($registered_operation_id) . '">';
					echo '<input type="hidden" name="communication_action" value="' . esc_attr($registered_action) . '">';
					if ($registered_recipient_id !== '') {
						echo '<input type="hidden" name="recipient_id" value="' . esc_attr($registered_recipient_id) . '">';
					}
					wp_nonce_field('bvmgr_event_communication_' . $registered_plan_id . '_' . $registered_operation_id, 'bvmgr_event_communication_nonce');
				}
				echo '</form>';
			}
			return '';
		}
		$form_id = 'bvmgr-event-communication-form-' . absint($plan_id) . '-' . substr(hash('sha256', $operation_id . '|' . $communication_action . '|' . $recipient_id), 0, 16);
		$forms[$form_id] = array(
			'form_id' => $form_id,
			'plan_id' => absint($plan_id),
			'operation_id' => $operation_id,
			'communication_action' => $communication_action,
			'recipient_id' => $recipient_id,
		);
		return $form_id;
	}
}

add_action('admin_footer-post.php', static function (): void {
	bvmgr_event_communication_render_action_fields(0, '', 'render_registered_forms');
});

if (!function_exists('bvmgr_event_communication_entitlement_text')) {
	function bvmgr_event_communication_entitlement_text(array $recipient): string
	{
		$parts = array();
		foreach ((array) ($recipient['orders'] ?? array()) as $order) {
			foreach ((array) ($order['entitlements'] ?? array()) as $entitlement) {
				/* translators: 1: Affected entitlement label, 2: Quantity. */
				$parts[] = sprintf('%1$s ×%2$d', (string) ($entitlement['label'] ?? __('Entitlement', 'backstage-venue-manager')), (int) ($entitlement['quantity'] ?? 0));
			}
		}
		foreach ((array) ($recipient['direct_entitlements'] ?? array()) as $entitlement) {
			/* translators: 1: Affected admission label, 2: Quantity. */
			$parts[] = sprintf('%1$s ×%2$d', (string) ($entitlement['label'] ?? __('Admission', 'backstage-venue-manager')), (int) ($entitlement['quantity'] ?? 0));
		}
		return implode('; ', $parts);
	}
}

if (!function_exists('bvmgr_event_communication_contact_text')) {
	function bvmgr_event_communication_contact_text(array $state): string
	{
		$contacts = (array) ($state['contact_log'] ?? array());
		if (empty($contacts)) {
			return __('No informal contact recorded', 'backstage-venue-manager');
		}
		$latest = (array) end($contacts);
		$method = sanitize_key((string) ($latest['method'] ?? 'other'));
		$labels = array(
			'phone' => __('Phone', 'backstage-venue-manager'),
			'in_person' => __('In person', 'backstage-venue-manager'),
			'other' => __('Other', 'backstage-venue-manager'),
		);
		return (string) ($labels[$method] ?? $labels['other']) . ' — ' . (string) ($latest['created_at_utc'] ?? '') . ' UTC';
	}
}

if (!function_exists('bvmgr_event_communication_render_missing_ledger')) {
	function bvmgr_event_communication_render_missing_ledger(int $plan_id, array $history_entry): void
	{
		$operation_id = (string) ($history_entry['operation_id'] ?? '');
		$impact = (array) ($history_entry['impact_counts'] ?? array());
		$customer_impact = (int) ($impact['customers'] ?? 0);
		if ($customer_impact <= 0 && (int) ($impact['custom_admission_rows'] ?? 0) <= 0) {
			return;
		}
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Communication ledger missing', 'backstage-venue-manager') . '</strong><br>';
		echo esc_html__('This historical occurrence operation has affected customers but no durable written-notice ledger. Preview an operation-specific bootstrap.', 'backstage-venue-manager') . '</p></div>';
		$form_id = bvmgr_event_communication_render_action_fields($plan_id, $operation_id, 'bootstrap_preview');
		echo '<button form="' . esc_attr($form_id) . '" type="submit" class="button button-secondary">' . esc_html__('Preview communication bootstrap', 'backstage-venue-manager') . '</button>';

		$stored = get_transient(bvmgr_event_communication_admin_bootstrap_key($plan_id, $operation_id));
		$preview = is_array($stored) ? $stored : array();
		if (empty($preview)) {
			return;
		}
		echo '<h5>' . esc_html__('Bootstrap preview', 'backstage-venue-manager') . '</h5>';
		echo '<p>' . esc_html(sprintf(
			/* translators: 1: Recipient count, 2: Order count, 3: Affected line-item count. */
			__('%1$d deduplicated recipients / %2$d orders / %3$d affected line items. No email will be sent.', 'backstage-venue-manager'),
			(int) ($preview['counts']['customers'] ?? 0),
			(int) ($preview['counts']['orders'] ?? 0),
			(int) ($preview['counts']['line_items'] ?? 0)
		)) . '</p>';
		foreach ((array) ($preview['ambiguities'] ?? array()) as $ambiguity) {
			echo '<div class="notice notice-error inline"><p>' . esc_html((string) $ambiguity) . '</p></div>';
		}
		if (!empty($preview['allowed'])) {
			$form_id = bvmgr_event_communication_render_action_fields($plan_id, $operation_id, 'bootstrap_apply');
			echo '<input form="' . esc_attr($form_id) . '" type="hidden" name="preview_fingerprint" value="' . esc_attr((string) ($preview['fingerprint'] ?? '')) . '">';
			echo '<p><label><input form="' . esc_attr($form_id) . '" type="checkbox" name="confirm_bootstrap" value="BOOTSTRAP-COMMUNICATIONS" required> ' . esc_html__('Create this durable audience ledger. Send no email.', 'backstage-venue-manager') . '</label></p>';
			echo '<button form="' . esc_attr($form_id) . '" type="submit" class="button button-primary">' . esc_html__('Apply communication bootstrap', 'backstage-venue-manager') . '</button>';
		}
	}
}

if (!function_exists('bvmgr_event_communication_render_ledger')) {
	function bvmgr_event_communication_render_ledger(int $plan_id, string $operation_id, array $ledger): void
	{
		$summary = bvmgr_event_communication_summary($ledger);
		$message = bvmgr_event_communication_default_message($ledger);
		$included_emails = array();
		foreach ((array) ($ledger['audience'] ?? array()) as $recipient_id => $recipient) {
			$state = (array) ($ledger['recipient_states'][$recipient_id] ?? array());
			if (!empty($state['included']) && !empty($recipient['email_valid'])) {
				$included_emails[] = (string) ($recipient['email_snapshot'] ?? '');
			}
		}
		echo '<h4>' . esc_html__('Review & Send Notification', 'backstage-venue-manager') . '</h4>';
		echo '<p><strong>' . esc_html(bvmgr_event_communication_summary_text($summary)) . '</strong></p>';
		if ((int) ($summary['unresolved'] ?? 0) > 0) {
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Written notice remains unresolved.', 'backstage-venue-manager') . '</strong> ';
			echo esc_html__('Phone or in-person contact is recorded separately and does not clear this reminder.', 'backstage-venue-manager') . '</p></div>';
		} else {
			echo '<div class="notice notice-success inline"><p>' . esc_html__('Every affected recipient has a resolved written-notice state.', 'backstage-venue-manager') . '</p></div>';
		}

		$form_id = bvmgr_event_communication_render_action_fields($plan_id, $operation_id, 'send_bulk');
		echo '<p><label><strong>' . esc_html__('Subject', 'backstage-venue-manager') . '</strong><br><input form="' . esc_attr($form_id) . '" type="text" class="widefat" name="subject" value="' . esc_attr((string) $message['subject']) . '" required></label></p>';
		echo '<p><label><strong>' . esc_html__('Message', 'backstage-venue-manager') . '</strong><br><textarea form="' . esc_attr($form_id) . '" class="widefat" rows="10" name="body" required>' . esc_textarea((string) $message['body']) . '</textarea></label></p>';
		echo '<p><label><input form="' . esc_attr($form_id) . '" type="checkbox" name="confirm_send" value="1" required> ' . esc_html__('I reviewed the final subject and message.', 'backstage-venue-manager') . '</label></p>';
		echo '<p><button form="' . esc_attr($form_id) . '" type="submit" class="button button-primary" name="send_mode" value="pending">' . esc_html__('Send to Included Pending Customers', 'backstage-venue-manager') . '</button> ';
		echo '<button form="' . esc_attr($form_id) . '" type="submit" class="button button-secondary" name="send_mode" value="failed">' . esc_html__('Retry Failed', 'backstage-venue-manager') . '</button></p>';
		echo '<p><label>' . esc_html__('Administrator test address', 'backstage-venue-manager') . ' <input form="' . esc_attr($form_id) . '" type="email" name="test_email" value="' . esc_attr((string) wp_get_current_user()->user_email) . '"></label> ';
		echo '<button form="' . esc_attr($form_id) . '" type="submit" class="button" name="send_mode" value="test">' . esc_html__('Send Test to Administrator', 'backstage-venue-manager') . '</button></p>';

		echo '<div class="vms-ep-basic-grid">';
		echo '<p class="vms-ep-basic-item"><label><strong>' . esc_html__('Copy included email addresses', 'backstage-venue-manager') . '</strong><br><textarea readonly rows="4" class="widefat">' . esc_textarea(implode("\n", array_values(array_unique($included_emails)))) . '</textarea></label></p>';
		$export_form_id = bvmgr_event_communication_render_action_fields($plan_id, $operation_id, 'export');
		echo '<div class="vms-ep-basic-item"><p><button form="' . esc_attr($export_form_id) . '" type="submit" class="button button-secondary">' . esc_html__('Export recipient CSV', 'backstage-venue-manager') . '</button></p></div></div>';

		echo '<table class="widefat striped"><thead><tr>';
		foreach (array(__('Include', 'backstage-venue-manager'), __('Customer', 'backstage-venue-manager'), __('Email', 'backstage-venue-manager'), __('Orders', 'backstage-venue-manager'), __('Affected items', 'backstage-venue-manager'), __('Contact status', 'backstage-venue-manager'), __('Written notice', 'backstage-venue-manager'), __('Actions', 'backstage-venue-manager')) as $heading) {
			echo '<th>' . esc_html($heading) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ((array) ($ledger['audience'] ?? array()) as $recipient_id => $recipient) {
			$state = (array) ($ledger['recipient_states'][$recipient_id] ?? array());
			$written = (array) ($state['written_notice'] ?? array());
			$order_labels = array_map(static function (array $order): string {
				return '#' . (string) ($order['order_number'] ?? $order['order_id'] ?? '');
			}, (array) ($recipient['orders'] ?? array()));
			echo '<tr>';
			echo '<td>';
			if (!empty($state['included']) && !bvmgr_event_communication_status_is_resolved((string) ($written['status'] ?? 'pending'))) {
				$form_id = bvmgr_event_communication_render_action_fields($plan_id, $operation_id, 'exclude', (string) $recipient_id);
				echo '<label><input form="' . esc_attr($form_id) . '" type="checkbox" name="include_recipient" value="1" checked> ' . esc_html__('Included', 'backstage-venue-manager') . '</label>';
				echo '<p class="description">' . esc_html__('To exclude, uncheck Include and provide the required reason and confirmation.', 'backstage-venue-manager') . '</p>';
				echo '<textarea form="' . esc_attr($form_id) . '" name="reason" rows="2" required placeholder="' . esc_attr__('Reason required', 'backstage-venue-manager') . '"></textarea>';
				echo '<label><input form="' . esc_attr($form_id) . '" type="checkbox" name="confirm_exclusion" value="1" required> ' . esc_html__('Exclude this affected customer from written notification?', 'backstage-venue-manager') . '</label>';
				echo '<button form="' . esc_attr($form_id) . '" class="button" type="submit">' . esc_html__('Save exclusion', 'backstage-venue-manager') . '</button>';
			} elseif (empty($state['included'])) {
				echo '<input type="checkbox" disabled> ' . esc_html__('Excluded', 'backstage-venue-manager');
				$form_id = bvmgr_event_communication_render_action_fields($plan_id, $operation_id, 'reinclude', (string) $recipient_id);
				echo '<button form="' . esc_attr($form_id) . '" class="button" type="submit">' . esc_html__('Re-include', 'backstage-venue-manager') . '</button>';
			} else {
				echo '<input type="checkbox" checked disabled> ' . esc_html__('Included', 'backstage-venue-manager');
			}
			echo '</td>';
			echo '<td><strong>' . esc_html((string) (($recipient['customer_name'] ?? '') ?: __('Customer', 'backstage-venue-manager'))) . '</strong></td>';
			echo '<td>' . esc_html((string) (($recipient['email_snapshot'] ?? '') ?: '—'));
			if (empty($recipient['email_valid'])) {
				echo '<br><strong>' . esc_html__('Written notice requires manual contact', 'backstage-venue-manager') . '</strong>';
			}
			echo '</td>';
			echo '<td>' . esc_html(implode(', ', $order_labels)) . '</td>';
			echo '<td>' . esc_html(bvmgr_event_communication_entitlement_text($recipient)) . '</td>';
			echo '<td>' . esc_html(bvmgr_event_communication_contact_text($state)) . '</td>';
			echo '<td><strong>' . esc_html(bvmgr_event_communication_status_label((string) ($written['status'] ?? 'pending'))) . '</strong>';
			if ((string) ($written['exclusion_reason'] ?? '') !== '') {
				echo '<br>' . esc_html((string) $written['exclusion_reason']);
			}
			if ((string) ($written['error_information'] ?? '') !== '') {
				echo '<br><span class="description">' . esc_html((string) $written['error_information']) . '</span>';
			}
			echo '</td><td>';
			$form_id = bvmgr_event_communication_render_action_fields($plan_id, $operation_id, 'contact', (string) $recipient_id);
			echo '<details><summary>' . esc_html__('Record contact', 'backstage-venue-manager') . '</summary>';
			echo '<select form="' . esc_attr($form_id) . '" name="contact_method"><option value="phone">' . esc_html__('Phone', 'backstage-venue-manager') . '</option><option value="in_person">' . esc_html__('In person', 'backstage-venue-manager') . '</option><option value="other">' . esc_html__('Other', 'backstage-venue-manager') . '</option></select>';
			echo '<textarea form="' . esc_attr($form_id) . '" name="note" rows="2" placeholder="' . esc_attr__('Optional note', 'backstage-venue-manager') . '"></textarea><button form="' . esc_attr($form_id) . '" class="button" type="submit">' . esc_html__('Record', 'backstage-venue-manager') . '</button></details>';

			if (!bvmgr_event_communication_status_is_resolved((string) ($written['status'] ?? 'pending'))) {
				$form_id = bvmgr_event_communication_render_action_fields($plan_id, $operation_id, 'manual', (string) $recipient_id);
				echo '<details><summary>' . esc_html__('Manual written notice', 'backstage-venue-manager') . '</summary>';
				echo '<select form="' . esc_attr($form_id) . '" name="manual_channel"><option value="email_outside_bvm">' . esc_html__('Email outside BVM', 'backstage-venue-manager') . '</option><option value="letter">' . esc_html__('Letter', 'backstage-venue-manager') . '</option><option value="other_written">' . esc_html__('Other written channel', 'backstage-venue-manager') . '</option></select>';
				echo '<input form="' . esc_attr($form_id) . '" type="text" name="address_used" placeholder="' . esc_attr__('Address used (optional)', 'backstage-venue-manager') . '"><textarea form="' . esc_attr($form_id) . '" name="note" rows="2" placeholder="' . esc_attr__('Optional note', 'backstage-venue-manager') . '"></textarea><button form="' . esc_attr($form_id) . '" class="button" type="submit">' . esc_html__('Mark written notice sent manually', 'backstage-venue-manager') . '</button></details>';
			}

			if (bvmgr_event_communication_status_is_resolved((string) ($written['status'] ?? 'pending')) && !empty($state['included']) && !empty($recipient['email_valid'])) {
				$form_id = bvmgr_event_communication_render_action_fields($plan_id, $operation_id, 'resend', (string) $recipient_id);
				echo '<details><summary>' . esc_html__('Explicit resend', 'backstage-venue-manager') . '</summary>';
				echo '<input form="' . esc_attr($form_id) . '" type="text" name="subject" value="' . esc_attr((string) $message['subject']) . '" required><textarea form="' . esc_attr($form_id) . '" name="body" rows="5" required>' . esc_textarea((string) $message['body']) . '</textarea>';
				echo '<label><input form="' . esc_attr($form_id) . '" type="checkbox" name="confirm_resend" value="1" required> ' . esc_html__('Create a new audited send attempt.', 'backstage-venue-manager') . '</label><button form="' . esc_attr($form_id) . '" class="button" type="submit">' . esc_html__('Resend', 'backstage-venue-manager') . '</button></details>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
}

if (!function_exists('bvmgr_event_communication_render_admin_section')) {
	function bvmgr_event_communication_render_admin_section(int $plan_id, array $history, string $preferred_operation_id = ''): void
	{
		if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id) || empty($history)) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Event Plan operation selection; capability is checked above and every mutation/export has its own nonce.
		$requested = isset($_GET['bvmgr_communication_operation']) ? sanitize_text_field(wp_unslash((string) $_GET['bvmgr_communication_operation'])) : '';
		$selected = $requested !== '' ? $requested : $preferred_operation_id;
		if ($selected === '') {
			foreach (array_reverse($history) as $entry) {
				$operation_id = (string) ($entry['operation_id'] ?? '');
				$operation_ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
				$summary = empty($operation_ledger) ? array() : bvmgr_event_communication_summary($operation_ledger);
				$impact = (array) ($entry['impact_counts'] ?? array());
				$missing_affected_ledger = empty($summary) && ((int) ($impact['customers'] ?? 0) > 0 || (int) ($impact['custom_admission_rows'] ?? 0) > 0);
				$has_unfinished_attempt = false;
				foreach ((array) ($operation_ledger['recipient_states'] ?? array()) as $recipient_state) {
					if (bvmgr_event_communication_has_unfinished_attempt((array) $recipient_state)) {
						$has_unfinished_attempt = true;
						break;
					}
				}
				if ((!empty($summary) && (int) ($summary['unresolved'] ?? 0) > 0) || $missing_affected_ledger || $has_unfinished_attempt) {
					$selected = $operation_id;
					break;
				}
			}
		}
		if ($selected === '') {
			$latest = (array) end($history);
			$selected = (string) ($latest['operation_id'] ?? '');
		}
		$selected_entry = array();
		foreach ($history as $entry) {
			if (is_array($entry) && hash_equals($selected, (string) ($entry['operation_id'] ?? ''))) {
				$selected_entry = $entry;
				break;
			}
		}
		if (empty($selected_entry)) {
			return;
		}

		$ledger = bvmgr_event_communication_get_ledger($plan_id, $selected);
		$summary = empty($ledger) ? array() : bvmgr_event_communication_summary($ledger);
		$impact = (array) ($selected_entry['impact_counts'] ?? array());
		$missing_affected_ledger = empty($ledger) && ((int) ($impact['customers'] ?? 0) > 0 || (int) ($impact['custom_admission_rows'] ?? 0) > 0);
		$unfinished_attempts = 0;
		foreach ((array) ($ledger['recipient_states'] ?? array()) as $recipient_state) {
			if (bvmgr_event_communication_has_unfinished_attempt((array) $recipient_state)) {
				$unfinished_attempts++;
			}
		}
		$requires_attention = $missing_affected_ledger
			|| $unfinished_attempts > 0
			|| (int) ($summary['unresolved'] ?? 0) > 0
			|| (int) ($summary['failed'] ?? 0) > 0;
		if ($missing_affected_ledger) {
			$panel_summary = __('ledger review required', 'backstage-venue-manager');
		} elseif ($unfinished_attempts > 0) {
			$panel_summary = sprintf(
				/* translators: %d: Number of customer communication send attempts requiring review. */
				_n('%d send attempt needs review', '%d send attempts need review', $unfinished_attempts, 'backstage-venue-manager'),
				$unfinished_attempts
			);
		} elseif ((int) ($summary['failed'] ?? 0) > 0) {
			$failed = (int) $summary['failed'];
			$pending = (int) ($summary['pending'] ?? 0);
			if ($pending > 0) {
				$panel_summary = sprintf(
					/* translators: 1: Number of failed customer communications, 2: Number of pending customer communications. */
					__('%1$d failed / %2$d pending', 'backstage-venue-manager'),
					$failed,
					$pending
				);
			} else {
				$panel_summary = sprintf(
					/* translators: %d: Number of failed customer communications. */
					_n('%d failed', '%d failed', $failed, 'backstage-venue-manager'),
					$failed
				);
			}
		} elseif ((int) ($summary['unresolved'] ?? 0) > 0) {
			$unresolved = (int) $summary['unresolved'];
			$panel_summary = sprintf(
				/* translators: %d: Number of affected customer recipients requiring administrator review. */
				_n('%d recipient needs review', '%d recipients need review', $unresolved, 'backstage-venue-manager'),
				$unresolved
			);
		} elseif ((int) ($summary['recipient_count'] ?? 0) > 0) {
			$panel_summary = sprintf(
				/* translators: 1: Completed written notices, 2: Total written notices. */
				__('%1$d of %2$d complete', 'backstage-venue-manager'),
				(int) ($summary['resolved'] ?? 0),
				(int) ($summary['recipient_count'] ?? 0)
			);
		} else {
			$panel_summary = __('no affected customer notice required', 'backstage-venue-manager');
		}

		$notice = get_transient(bvmgr_event_communication_admin_notice_key($plan_id));
		if (is_array($notice)) {
			delete_transient(bvmgr_event_communication_admin_notice_key($plan_id));
			if (empty($notice['ok'])) {
				$requires_attention = true;
			}
		}
		echo '<details id="bvmgr-event-communications" class="vms-ep-card vms-mt-12"' . ($requires_attention ? ' open' : '') . '>';
		echo '<summary><strong>' . esc_html__('Customer communications', 'backstage-venue-manager') . '</strong> — ' . esc_html($panel_summary) . '</summary>';
		echo '<div class="vms-mt-12">';
		if (is_array($notice)) {
			echo '<div class="notice ' . esc_attr(!empty($notice['ok']) ? 'notice-success' : 'notice-error') . ' inline"><p>' . esc_html((string) ($notice['message'] ?? '')) . '</p></div>';
		}
		if (empty($ledger)) {
			bvmgr_event_communication_render_missing_ledger($plan_id, $selected_entry);
		} else {
			bvmgr_event_communication_render_ledger($plan_id, $selected, $ledger);
		}
		echo '</div></details>';
	}
}

if (!function_exists('bvmgr_event_communication_admin_redirect')) {
	function bvmgr_event_communication_admin_redirect(int $plan_id, string $operation_id): void
	{
		wp_safe_redirect(bvmgr_event_communication_admin_url($plan_id, $operation_id));
		exit;
	}
}

if (!function_exists('bvmgr_event_communication_admin_handle_action')) {
	function bvmgr_event_communication_admin_handle_action(): void
	{
		$plan_id = isset($_POST['event_plan_id']) ? absint(wp_unslash($_POST['event_plan_id'])) : 0;
		$operation_id = isset($_POST['operation_id']) ? sanitize_text_field(wp_unslash((string) $_POST['operation_id'])) : '';
		if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id) || !bvmgr_event_communication_operation_id_is_valid($operation_id)) {
			wp_die(esc_html__('You cannot manage this Event Plan communication ledger.', 'backstage-venue-manager'), '', array('response' => 403));
		}
		check_admin_referer('bvmgr_event_communication_' . $plan_id . '_' . $operation_id, 'bvmgr_event_communication_nonce');
		$action = isset($_POST['communication_action']) ? sanitize_key(wp_unslash((string) $_POST['communication_action'])) : '';
		$recipient_id = isset($_POST['recipient_id']) ? sanitize_key(wp_unslash((string) $_POST['recipient_id'])) : '';
		$actor_user_id = get_current_user_id();
		$result = array('ok' => false, 'message' => __('Unknown communication action.', 'backstage-venue-manager'));

		if ($action === 'bootstrap_preview') {
			$preview = bvmgr_event_communication_bootstrap_preview($plan_id, $operation_id);
			set_transient(bvmgr_event_communication_admin_bootstrap_key($plan_id, $operation_id), $preview, 15 * MINUTE_IN_SECONDS);
			$result = array('ok' => !empty($preview['allowed']), 'message' => !empty($preview['allowed']) ? __('Communication bootstrap preview is ready. No email was sent.', 'backstage-venue-manager') : __('Communication bootstrap is blocked by operation evidence ambiguity.', 'backstage-venue-manager'));
		} elseif ($action === 'bootstrap_apply') {
			$confirm = isset($_POST['confirm_bootstrap']) ? sanitize_text_field(wp_unslash((string) $_POST['confirm_bootstrap'])) : '';
			$fingerprint = isset($_POST['preview_fingerprint']) ? sanitize_text_field(wp_unslash((string) $_POST['preview_fingerprint'])) : '';
			$result = $confirm === 'BOOTSTRAP-COMMUNICATIONS'
				? bvmgr_event_communication_bootstrap_apply($plan_id, $operation_id, $actor_user_id, $fingerprint)
				: array('ok' => false, 'message' => __('Bootstrap confirmation is required.', 'backstage-venue-manager'));
		} elseif ($action === 'exclude') {
			$result = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $recipient_id, 'exclude', $actor_user_id, array(
				'confirmed' => !isset($_POST['include_recipient']) && isset($_POST['confirm_exclusion']) && sanitize_text_field(wp_unslash((string) $_POST['confirm_exclusion'])) === '1',
				'reason' => isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash((string) $_POST['reason'])) : '',
			));
		} elseif ($action === 'reinclude') {
			$result = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $recipient_id, 'reinclude', $actor_user_id);
		} elseif ($action === 'contact') {
			$result = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $recipient_id, 'contact', $actor_user_id, array(
				'method' => isset($_POST['contact_method']) ? sanitize_key(wp_unslash((string) $_POST['contact_method'])) : '',
				'note' => isset($_POST['note']) ? sanitize_textarea_field(wp_unslash((string) $_POST['note'])) : '',
			));
		} elseif ($action === 'manual') {
			$result = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $recipient_id, 'manual', $actor_user_id, array(
				'channel' => isset($_POST['manual_channel']) ? sanitize_key(wp_unslash((string) $_POST['manual_channel'])) : '',
				'note' => isset($_POST['note']) ? sanitize_textarea_field(wp_unslash((string) $_POST['note'])) : '',
				'address_used' => isset($_POST['address_used']) ? sanitize_text_field(wp_unslash((string) $_POST['address_used'])) : '',
			));
		} elseif ($action === 'send_bulk') {
			$confirmed = isset($_POST['confirm_send']) && sanitize_text_field(wp_unslash((string) $_POST['confirm_send'])) === '1';
			$mode = isset($_POST['send_mode']) ? sanitize_key(wp_unslash((string) $_POST['send_mode'])) : '';
			$subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash((string) $_POST['subject'])) : '';
			$body = isset($_POST['body']) ? sanitize_textarea_field(wp_unslash((string) $_POST['body'])) : '';
			if (!$confirmed) {
				$result = array('ok' => false, 'message' => __('Review confirmation is required before sending.', 'backstage-venue-manager'));
			} elseif ($mode === 'test') {
				$test_email = isset($_POST['test_email']) ? sanitize_email(wp_unslash((string) $_POST['test_email'])) : '';
				$result = bvmgr_event_communication_send_test($plan_id, $operation_id, $actor_user_id, $test_email, $subject, $body);
			} else {
				$bulk = bvmgr_event_communication_send_bulk($plan_id, $operation_id, $actor_user_id, $subject, $body, $mode);
				$result = array(
					'ok' => !empty($bulk['ok']),
					'message' => sprintf(
						/* translators: 1: Accepted messages, 2: Failed messages, 3: Skipped recipients. */
						__('%1$d accepted / %2$d failed / %3$d skipped. No status means delivery confirmation.', 'backstage-venue-manager'),
						(int) ($bulk['accepted'] ?? 0),
						(int) ($bulk['failed'] ?? 0),
						(int) ($bulk['skipped'] ?? 0)
					),
				);
			}
		} elseif ($action === 'resend') {
			$confirmed = isset($_POST['confirm_resend']) && sanitize_text_field(wp_unslash((string) $_POST['confirm_resend'])) === '1';
			$result = $confirmed
				? bvmgr_event_communication_send_recipient(
					$plan_id,
					$operation_id,
					$recipient_id,
					$actor_user_id,
					isset($_POST['subject']) ? sanitize_text_field(wp_unslash((string) $_POST['subject'])) : '',
					isset($_POST['body']) ? sanitize_textarea_field(wp_unslash((string) $_POST['body'])) : '',
					true
				)
				: array('ok' => false, 'message' => __('Explicit resend confirmation is required.', 'backstage-venue-manager'));
		}

		bvmgr_event_communication_admin_set_notice($plan_id, !empty($result['ok']), (string) ($result['message'] ?? ''));
		bvmgr_event_communication_admin_redirect($plan_id, $operation_id);
	}
}
add_action('admin_post_bvmgr_event_communication_action', 'bvmgr_event_communication_admin_handle_action');

if (!function_exists('bvmgr_event_communication_admin_export')) {
	function bvmgr_event_communication_admin_export(): void
	{
		$plan_id = isset($_POST['event_plan_id']) ? absint(wp_unslash($_POST['event_plan_id'])) : 0;
		$operation_id = isset($_POST['operation_id']) ? sanitize_text_field(wp_unslash((string) $_POST['operation_id'])) : '';
		if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
			wp_die(esc_html__('You cannot export this communication ledger.', 'backstage-venue-manager'), '', array('response' => 403));
		}
		check_admin_referer('bvmgr_event_communication_export_' . $plan_id . '_' . $operation_id, 'bvmgr_event_communication_export_nonce');
		$ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
		if (empty($ledger)) {
			wp_die(esc_html__('Communication ledger not found.', 'backstage-venue-manager'), '', array('response' => 404));
		}
		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="event-communications-' . absint($plan_id) . '-' . sanitize_file_name($operation_id) . '.csv"');
		$stream = fopen('php://output', 'wb');
		if (!is_resource($stream)) {
			wp_die(esc_html__('CSV output could not be opened.', 'backstage-venue-manager'));
		}
		fputcsv($stream, array('name', 'email', 'affected_orders', 'affected_entitlements', 'written_notice_status'));
		foreach (bvmgr_event_communication_export_rows($ledger) as $row) {
			fputcsv($stream, array($row['name'], $row['email'], $row['affected_orders'], $row['affected_entitlements'], $row['written_notice_status']));
		}
		fclose($stream); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies.
		exit;
	}
}
add_action('admin_post_bvmgr_event_communication_export', 'bvmgr_event_communication_admin_export');
