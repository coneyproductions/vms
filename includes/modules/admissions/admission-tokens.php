<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_admission_generate_public_token')) {
	function bvmgr_admission_generate_public_token(): string
	{
		try {
			return strtolower(bin2hex(random_bytes(20)));
		} catch (Exception $e) {
			return strtolower(wp_generate_password(40, false, false));
		}
	}
}

if (!function_exists('bvmgr_admission_token_hash')) {
	function bvmgr_admission_token_hash(string $token): string
	{
		return hash_hmac('sha256', trim($token), wp_salt('auth'));
	}
}

if (!function_exists('bvmgr_admission_scan_url')) {
	function bvmgr_admission_scan_url(string $token): string
	{
		$token = trim($token);
		if ($token === '') {
			return '';
		}

		// Query-var URLs work immediately after plugin updates without requiring
		// a Permalinks save / rewrite flush.
		return add_query_arg('bvmgr_admission_scan_token', rawurlencode($token), home_url('/'));
	}
}

if (!function_exists('bvmgr_admission_public_pass_url')) {
	function bvmgr_admission_public_pass_url(string $token, bool $printable = true): string
	{
		$url = bvmgr_admission_scan_url($token);
		if ($url === '') {
			return '';
		}
		return $printable ? add_query_arg('vms_print_pass', '1', $url) : $url;
	}
}



if (!function_exists('bvmgr_admission_group_entries')) {
	function bvmgr_admission_group_entries(array $row): array
	{
		global $wpdb;
		$table = bvmgr_admission_table_entries();
		$entry_id = (int) ($row['id'] ?? 0);
		$claim_id = (int) ($row['pass_claim_id'] ?? 0);
		if ($claim_id <= 0) {
			return $entry_id > 0 ? array($row) : array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-group reads target the plugin-owned admissions table with a %i/%d-prepared identifier and filter, and pass rendering must observe request-fresh claim state.
		$rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE pass_claim_id = %d AND status <> \'canceled\' ORDER BY id ASC', $table, $claim_id), ARRAY_A);
		if (!is_array($rows) || empty($rows)) {
			return $entry_id > 0 ? array($row) : array();
		}
		return $rows;
	}
}

if (!function_exists('bvmgr_admission_format_public_date')) {
	function bvmgr_admission_format_public_date(string $date): string
	{
		$date = trim($date);
		if ($date === '') {
			return '';
		}
		try {
			$dt = new DateTimeImmutable($date, wp_timezone());
			return function_exists('wp_date') ? wp_date('F j, Y', $dt->getTimestamp(), wp_timezone()) : $dt->format('F j, Y');
		} catch (Exception $e) {
			return $date;
		}
	}
}

if (!function_exists('bvmgr_admission_qr_image_url')) {
	function bvmgr_admission_qr_image_url(string $data): string
	{
		$data = trim($data);
		if ($data === '') {
			return '';
		}
		return 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=16&data=' . rawurlencode($data);
	}
}

if (!function_exists('bvmgr_admission_extract_scan_token')) {
	function bvmgr_admission_extract_scan_token(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}
		if (preg_match('~/(?:admission|admissions)/scan/([^/?#]+)~i', $raw, $m)) {
			return sanitize_text_field(rawurldecode((string) $m[1]));
		}
		$parts = wp_parse_url($raw);
		if (is_array($parts) && !empty($parts['query'])) {
			parse_str((string) $parts['query'], $query);
			if (!empty($query['bvmgr_admission_scan_token'])) {
				return sanitize_text_field(rawurldecode((string) $query['bvmgr_admission_scan_token']));
			}
			if (!empty($query['vms_admission_scan_token'])) {
				return sanitize_text_field(rawurldecode((string) $query['vms_admission_scan_token']));
			}
		}
		if (stripos($raw, 'vms-admission:') === 0) {
			return sanitize_text_field(substr($raw, strlen('vms-admission:')));
		}
		if (preg_match('/^[a-f0-9]{32,80}$/i', $raw)) {
			return strtolower($raw);
		}
		return '';
	}
}

if (!function_exists('bvmgr_admission_ensure_entry_token')) {
	function bvmgr_admission_ensure_entry_token(int $entry_id): string
	{
		if ($entry_id <= 0) {
			return '';
		}
		global $wpdb;
		$table = bvmgr_admission_table_entries();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Token issuance reads one admissions row from the plugin-owned table with a %i/%d-prepared identifier and ID before conditional mutation.
		$row = $wpdb->get_row($wpdb->prepare('SELECT id, admission_token FROM %i WHERE id = %d', $table, $entry_id), ARRAY_A);
		if (!is_array($row)) {
			return '';
		}
		$existing = trim((string) ($row['admission_token'] ?? ''));
		if ($existing !== '') {
			return $existing;
		}
		for ($i = 0; $i < 5; $i += 1) {
			$token = bvmgr_admission_generate_public_token();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Token issuance writes directly to the plugin-owned admissions table because no core API exposes this repository and the retry loop must persist immediately.
			$updated = $wpdb->update(
				$table,
				array(
					'admission_token' => $token,
					'admission_token_hash' => bvmgr_admission_token_hash($token),
					'updated_at' => bvmgr_admission_now_mysql(),
				),
				array('id' => $entry_id, 'admission_token' => ''),
				array('%s', '%s', '%s'),
				array('%d', '%s')
			);
			if ($updated !== false) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Token issuance re-reads the freshly written admissions token from the plugin-owned table with a %i/%d-prepared identifier and ID.
				$fresh = (string) $wpdb->get_var($wpdb->prepare('SELECT admission_token FROM %i WHERE id = %d', $table, $entry_id));
				if ($fresh !== '') {
					return $fresh;
				}
			}
		}
		return '';
	}
}


if (!function_exists('bvmgr_admission_event_comp_headcount')) {
	function bvmgr_admission_event_comp_headcount(int $event_plan_id): int
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0 || !function_exists('bvmgr_admission_table_entries')) {
			return 0;
		}
		global $wpdb;
		$table = bvmgr_admission_table_entries();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions headcount reads aggregate the plugin-owned entries table with a %i/%d-prepared identifier and event filter so staffing and admission flows see fresh counts.
		return max(0, (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(party_size), 0) FROM %i WHERE event_plan_id = %d AND status <> 'canceled'", $table, $event_plan_id)));
	}
}

if (!function_exists('bvmgr_admission_email_last_result')) {
	function bvmgr_admission_email_last_result(): array
	{
		$result = $GLOBALS['bvmgr_admission_last_email_result'] ?? array();
		return is_array($result) ? $result : array();
	}
}

if (!function_exists('bvmgr_admission_email_set_result')) {
	function bvmgr_admission_email_set_result(array $result): void
	{
		$GLOBALS['bvmgr_admission_last_email_result'] = $result;
	}
}

if (!function_exists('bvmgr_admission_email_pass_result')) {
	function bvmgr_admission_email_pass_result(int $entry_id, string $context = 'guest_pass'): array
	{
		global $wpdb;
		$table = bvmgr_admission_table_entries();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-email composition reads one admissions row from the plugin-owned table with a %i/%d-prepared identifier and ID before building the outbound message.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $entry_id), ARRAY_A);
		if (!is_array($row)) {
			$result = array('sent' => false, 'code' => 'entry_not_found', 'message' => __('Admission entry was not found.', 'backstage-venue-manager'));
			bvmgr_admission_email_set_result($result);
			return $result;
		}
		$email = sanitize_email((string) ($row['guest_email'] ?? ''));
		if ($email === '') {
			$result = array('sent' => false, 'code' => 'missing_email', 'message' => __('No email address is saved on this pass.', 'backstage-venue-manager'));
			bvmgr_admission_email_set_result($result);
			return $result;
		}
		$group_rows = function_exists('bvmgr_admission_group_entries') ? bvmgr_admission_group_entries($row) : array($row);
		$event_plan_id = (int) ($row['event_plan_id'] ?? 0);
		$title = $event_plan_id > 0 ? (string) get_the_title($event_plan_id) : __('Event', 'backstage-venue-manager');
		$date = $event_plan_id > 0 ? (string) get_post_meta($event_plan_id, '_vms_event_date', true) : '';
		$venue_id = (int) ($row['venue_id'] ?? 0);
		$venue = $venue_id > 0 ? (string) get_the_title($venue_id) : '';
		$primary_token = bvmgr_admission_ensure_entry_token($entry_id);
		if ($primary_token === '') {
			$result = array('sent' => false, 'code' => 'missing_token', 'message' => __('Could not generate a pass token for this admission.', 'backstage-venue-manager'));
			bvmgr_admission_email_set_result($result);
			return $result;
		}
		$url = bvmgr_admission_public_pass_url($primary_token, true);
		/* translators: %s: human-readable value used in this message. */
		$subject = sprintf(__('Your admission pass for %s', 'backstage-venue-manager'), $title);
		$count = count($group_rows);
		$body = '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#162033;max-width:720px;margin:0 auto;">';
		$body .= '<h1 style="font-size:24px;margin:0 0 12px;">' . esc_html__('Your admission pass is confirmed', 'backstage-venue-manager') . '</h1>';
		$body .= '<p>' . esc_html($count > 1 ? __('Each person has their own QR code. Show the matching QR code at the gate for entry.', 'backstage-venue-manager') : __('Show this QR code at the gate for entry.', 'backstage-venue-manager')) . '</p>';
		$body .= '<p><strong>' . esc_html__('Name:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) ($row['guest_name'] ?? '')) . '</p>';
		$body .= '<p><strong>' . esc_html__('Event:', 'backstage-venue-manager') . '</strong> ' . esc_html($title) . '</p>';
		if ($date !== '') {
			$body .= '<p><strong>' . esc_html__('Date:', 'backstage-venue-manager') . '</strong> ' . esc_html(bvmgr_admission_format_public_date($date)) . '</p>';
		}
		if ($venue !== '') {
			$body .= '<p><strong>' . esc_html__('Venue:', 'backstage-venue-manager') . '</strong> ' . esc_html($venue) . '</p>';
		}
		$body .= '<div style="display:flex;flex-wrap:wrap;gap:14px;margin:18px 0;">';
		$slot = 1;
		foreach ($group_rows as $group_row) {
			$group_entry_id = (int) ($group_row['id'] ?? 0);
			$group_token = $group_entry_id > 0 ? bvmgr_admission_ensure_entry_token($group_entry_id) : '';
			if ($group_token === '') {
				continue;
			}
			$qr_url = bvmgr_admission_qr_image_url('vms-admission:' . $group_token);
			/* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
			$label = $count > 1 ? sprintf(__('Pass %1$d of %2$d', 'backstage-venue-manager'), $slot, $count) : __('Gate QR code', 'backstage-venue-manager');
			$body .= '<div style="border:1px solid #d9e2ef;border-radius:12px;padding:12px;background:#fff;text-align:center;min-width:190px;">';
			$body .= '<strong style="display:block;margin-bottom:8px;">' . esc_html($label) . '</strong>';
			if ($qr_url !== '') {
				$body .= '<img src="' . esc_url($qr_url) . '" alt="' . esc_attr__('Gate QR code', 'backstage-venue-manager') . '" width="170" height="170" style="display:block;max-width:170px;height:auto;margin:0 auto;">';
			}
			$body .= '<span style="display:block;font-size:12px;color:#526174;margin-top:6px;">' . esc_html('GL-' . $group_entry_id) . '</span>';
			$body .= '</div>';
			$slot++;
		}
		$body .= '</div>';
		$body .= '<p><a href="' . esc_url($url) . '" style="display:inline-block;background:#145dcc;color:#fff;text-decoration:none;border-radius:8px;padding:12px 16px;font-weight:700;">' . esc_html__('View / Print Passes', 'backstage-venue-manager') . '</a></p>';
		$body .= '<p style="font-size:13px;color:#526174;">' . esc_html__('If a QR code will not scan, door staff can search your name or phone number.', 'backstage-venue-manager') . '</p>';
		$body .= '</div>';
		$from_email = sanitize_email((string) get_option('admin_email'));
		$site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
		$headers = array('Content-Type: text/html; charset=UTF-8');
		if ($from_email !== '') {
			$headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
			$headers[] = 'Reply-To: ' . $from_email;
		}
		$mail_error = '';
		$mail_capture = static function ($wp_error) use (&$mail_error): void {
			if (is_wp_error($wp_error)) {
				$mail_error = $wp_error->get_error_message();
			}
		};
		add_action('wp_mail_failed', $mail_capture, 10, 1);
		$sent = wp_mail($email, $subject, $body, $headers);
		remove_action('wp_mail_failed', $mail_capture, 10);
		$now = bvmgr_admission_now_mysql();
		$result = array(
			'sent' => (bool) $sent,
			'code' => $sent ? 'sent' : 'wp_mail_failed',
			'message' => $sent ? __('Pass email sent.', 'backstage-venue-manager') : ($mail_error !== '' ? $mail_error : __('WordPress did not accept the pass email for delivery.', 'backstage-venue-manager')),
			'email' => $email,
			'pass_count' => $count,
			'checked_at' => $now,
			'context' => $context,
		);
		foreach ($group_rows as $group_row) {
			$gid = (int) ($group_row['id'] ?? 0);
				if ($gid > 0) {
					$current_meta = (string) ($group_row['claim_meta'] ?? '');
					$meta = array();
					if ($current_meta !== '') {
						$decoded = bvmgr_json_decode_associative($current_meta, 16);
						if (
							!empty($decoded['ok'])
							&& is_array($decoded['value'])
							&& bvmgr_json_decoded_is_object($decoded['value'], (string) ($decoded['top_level_token'] ?? ''))
						) {
							$meta = $decoded['value'];
						}
					}
					$meta['last_email_status'] = $result;
				$updates = array('claim_meta' => wp_json_encode($meta), 'updated_at' => $now);
				$formats = array('%s', '%s');
				if ($sent) {
					$updates['admission_emailed_at'] = $now;
					$formats[] = '%s';
				}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-email bookkeeping writes the plugin-owned admissions table directly so resend and audit flows observe the persisted email state immediately.
					$wpdb->update($table, $updates, array('id' => $gid), $formats, array('%d'));
			}
		}
		if (function_exists('bvmgr_admission_audit_log')) {
			bvmgr_admission_audit_log($event_plan_id, $entry_id, $sent ? 'admission_email_sent' : 'admission_email_failed', get_current_user_id(), $context, $result);
		}
		bvmgr_admission_email_set_result($result);
		return $result;
	}
}

if (!function_exists('bvmgr_admission_email_pass')) {
	function bvmgr_admission_email_pass(int $entry_id, string $context = 'guest_pass'): bool
	{
		$result = function_exists('bvmgr_admission_email_pass_result') ? bvmgr_admission_email_pass_result($entry_id, $context) : array('sent' => false);
		return !empty($result['sent']);
	}
}

if (!function_exists('bvmgr_admission_scan_rewrite')) {
	function bvmgr_admission_scan_rewrite(): void
	{
		add_rewrite_tag('%bvmgr_admission_scan_token%', '([^&]+)');
		add_rewrite_tag('%vms_admission_scan_token%', '([^&]+)');
		add_rewrite_rule('^admission/scan/([^/]+)/?$', 'index.php?bvmgr_admission_scan_token=$matches[1]', 'top');
	}
}
add_action('init', 'bvmgr_admission_scan_rewrite', 31);

if (!function_exists('bvmgr_admission_scan_template_router')) {
	function bvmgr_admission_scan_template_router(): void
	{
		if (is_admin()) {
			return;
		}
		$token = bvmgr_get_query_var_compat('bvmgr_admission_scan_token');
		$token = sanitize_text_field(rawurldecode($token));
		if ($token === '') {
			return;
		}

		global $wpdb;
		$table = bvmgr_admission_table_entries();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Public scan rendering reads one admissions row from the plugin-owned table with a %i/%s-prepared identifier and token, and the ticket surface must reflect request-fresh status.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE admission_token = %s LIMIT 1', $table, $token), ARRAY_A);
		$status = is_array($row) ? (string) ($row['status'] ?? '') : '';
		$name = is_array($row) ? (string) ($row['guest_name'] ?? '') : '';
		$event_plan_id = is_array($row) ? (int) ($row['event_plan_id'] ?? 0) : 0;
		$title = $event_plan_id > 0 ? (string) get_the_title($event_plan_id) : '';
		$date = $event_plan_id > 0 ? (string) get_post_meta($event_plan_id, '_vms_event_date', true) : '';
		$event_status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
		if ($event_status_key === '') {
			$event_status_key = '_vms_event_plan_status';
		}
		$event_status = $event_plan_id > 0 ? sanitize_key((string) get_post_meta($event_plan_id, $event_status_key, true)) : '';
		$event_is_cancelled = ($event_status === 'cancelled' || $event_status === 'canceled');
		$venue_id = is_array($row) ? (int) ($row['venue_id'] ?? 0) : 0;
		$venue = $venue_id > 0 ? (string) get_the_title($venue_id) : '';
		$ref = is_array($row) ? 'gl:' . (int) ($row['id'] ?? 0) : '';

		$status_label = $status !== '' ? ucfirst($status) : '';
		$scan_url = bvmgr_admission_scan_url($token);
		$qr_url = bvmgr_admission_qr_image_url('vms-admission:' . $token);
		$ref = is_array($row) ? 'GL-' . (int) ($row['id'] ?? 0) : '';
		$group_rows = is_array($row) && function_exists('bvmgr_admission_group_entries') ? bvmgr_admission_group_entries($row) : (is_array($row) ? array($row) : array());

		status_header(is_array($row) ? 200 : 404);
		nocache_headers();
		add_filter('document_title_parts', static function (array $parts): array {
			$parts['title'] = __('Admission Pass', 'backstage-venue-manager');
			return $parts;
		}, 20);
		if (function_exists('wp_enqueue_style') && defined('BVMGR_PLUGIN_URL')) {
			wp_enqueue_style('bvmgr-pass-claims-public', BVMGR_PLUGIN_URL . 'assets/css/vms-pass-claims-public.css', array(), defined('BVMGR_VERSION') ? BVMGR_VERSION : null);
		}
		if (function_exists('get_header')) {
			get_header();
		} else {
			echo '<!doctype html><html><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
		}
		$print_class = bvmgr_request_read_bool_flag($_GET, 'vms_print_pass') ? ' vms-pass-public-page--print' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive read-only print-mode state stays nonce-free while rejecting malformed array/object input.
		echo '<main id="primary" class="site-main vms-pass-public-page' . esc_attr($print_class) . '" role="main"><div class="vms-pass-wrap"><div class="vms-pass-card">';
		if (!is_array($row)) {
			echo '<h1>' . esc_html__('Pass Not Found', 'backstage-venue-manager') . '</h1><p class="vms-pass-error">' . esc_html__('This admission pass was not found.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<h1>' . esc_html__('Admission Pass', 'backstage-venue-manager') . '</h1>';
			if ($event_is_cancelled) {
				echo '<div class="vms-pass-error">' . esc_html__('This event has been cancelled. Please see gate staff for assistance.', 'backstage-venue-manager') . '</div>';
			} else {
				echo '<div class="vms-pass-success">' . esc_html__('Show this QR code at the gate for entry.', 'backstage-venue-manager') . '</div>';
			}
			echo '<div class="vms-pass-ticket">';
			$group_count = count($group_rows);
			if ($group_count > 1) {
				echo '<h2>' . esc_html__('Individual QR Codes', 'backstage-venue-manager') . '</h2>';
				echo '<p class="vms-pass-hint">' . esc_html__('Each person can arrive separately. Scan one QR code per person.', 'backstage-venue-manager') . '</p>';
				echo '<div class="vms-pass-qr-grid">';
				$slot = 1;
				foreach ($group_rows as $group_row) {
					$group_entry_id = (int) ($group_row['id'] ?? 0);
					$group_token = $group_entry_id > 0 ? bvmgr_admission_ensure_entry_token($group_entry_id) : '';
					$group_qr_url = $group_token !== '' ? bvmgr_admission_qr_image_url('vms-admission:' . $group_token) : '';
					if ($group_qr_url === '') {
						continue;
					}
					/* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
					echo '<div class="vms-pass-qr-item"><strong>' . esc_html(sprintf(__('Pass %1$d of %2$d', 'backstage-venue-manager'), $slot, $group_count)) . '</strong><img class="vms-pass-qr" src="' . esc_url($group_qr_url) . '" alt="' . esc_attr__('Gate QR code', 'backstage-venue-manager') . '"><span>' . esc_html('GL-' . $group_entry_id) . '</span></div>';
					$slot++;
				}
				echo '</div>';
			} elseif ($qr_url !== '') {
				echo '<div class="vms-pass-qr-wrap"><img class="vms-pass-qr" src="' . esc_url($qr_url) . '" alt="' . esc_attr__('Gate QR code', 'backstage-venue-manager') . '"></div>';
			}
			echo '<p class="vms-pass-meta"><strong>' . esc_html__('Name:', 'backstage-venue-manager') . '</strong> ' . esc_html($name) . '</p>';
			if ($title !== '') {
				echo '<p class="vms-pass-meta"><strong>' . esc_html__('Event:', 'backstage-venue-manager') . '</strong> ' . esc_html($title) . '</p>';
			}
			if ($date !== '') {
				echo '<p class="vms-pass-meta"><strong>' . esc_html__('Date:', 'backstage-venue-manager') . '</strong> ' . esc_html(bvmgr_admission_format_public_date($date)) . '</p>';
			}
			if ($venue !== '') {
				echo '<p class="vms-pass-meta"><strong>' . esc_html__('Venue:', 'backstage-venue-manager') . '</strong> ' . esc_html($venue) . '</p>';
			}
			$party_size = $group_count > 1 ? $group_count : (is_array($row) ? max(1, (int) ($row['party_size'] ?? 1)) : 1);
			if ($party_size > 1) {
				/* translators: %d: number of items described in this message. */
				echo '<p class="vms-pass-meta"><strong>' . esc_html__('Admits:', 'backstage-venue-manager') . '</strong> ' . esc_html(sprintf(_n('%d person', '%d people', $party_size, 'backstage-venue-manager'), $party_size)) . '</p>';
			}
			if ($event_is_cancelled) {
				echo '<p class="vms-pass-meta"><strong>' . esc_html__('Event Status:', 'backstage-venue-manager') . '</strong> ' . esc_html__('Cancelled', 'backstage-venue-manager') . '</p>';
			}
			if ($status_label !== '') {
				echo '<p class="vms-pass-meta"><strong>' . esc_html__('Status:', 'backstage-venue-manager') . '</strong> ' . esc_html($status_label) . '</p>';
			}
			echo '<p class="vms-pass-meta"><strong>' . esc_html__('Reference:', 'backstage-venue-manager') . '</strong> ' . esc_html($ref) . '</p>';
			echo '<p class="vms-pass-hint">' . esc_html__('Screenshot this page if cell service is weak. Door staff can also search your name or phone number.', 'backstage-venue-manager') . '</p>';
			echo '</div>';
		}
		echo '</div></div></main>';
		if (function_exists('get_footer')) {
			get_footer();
		} else {
			echo '</body></html>';
		}
		exit;
	}
}
add_action('template_redirect', 'bvmgr_admission_scan_template_router', 0);
