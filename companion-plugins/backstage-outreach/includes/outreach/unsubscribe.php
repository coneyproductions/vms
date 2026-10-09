<?php
defined('ABSPATH') || exit;

if (!function_exists('backstage_outreach_unsubscribe_table')) {
	function backstage_outreach_unsubscribe_table(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_outreach_unsubscribe_tokens';
	}
}

if (!function_exists('backstage_outreach_base64url_encode')) {
	function backstage_outreach_base64url_encode(string $value): string
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}

if (!function_exists('backstage_outreach_unsubscribe_signing_key')) {
	function backstage_outreach_unsubscribe_signing_key()
	{
		$option = 'backstage_outreach_unsubscribe_signing_key';
		$key = (string) get_option($option, '');
		if (preg_match('/^[A-Za-z0-9_-]{43}$/', $key)) {
			return $key;
		}

		try {
			$candidate = backstage_outreach_base64url_encode(random_bytes(32));
		} catch (Throwable $error) {
			return new WP_Error('unsubscribe_signing_key_unavailable', __('Secure unsubscribe signing is unavailable.', 'backstage-outreach'));
		}

		if (!add_option($option, $candidate, '', false)) {
			$key = (string) get_option($option, '');
			if (preg_match('/^[A-Za-z0-9_-]{43}$/', $key)) {
				return $key;
			}
			return new WP_Error('unsubscribe_signing_key_unavailable', __('Secure unsubscribe signing is unavailable.', 'backstage-outreach'));
		}
		return $candidate;
	}
}

if (!function_exists('backstage_outreach_unsubscribe_signature')) {
	function backstage_outreach_unsubscribe_signature(string $token)
	{
		$key = backstage_outreach_unsubscribe_signing_key();
		if (is_wp_error($key)) {
			return $key;
		}
		return backstage_outreach_base64url_encode(hash_hmac('sha256', 'v1|' . $token, (string) $key, true));
	}
}

if (!function_exists('backstage_outreach_unsubscribe_table_ready')) {
	function backstage_outreach_unsubscribe_table_ready(): bool
	{
		global $wpdb;
		$table = backstage_outreach_unsubscribe_table();
		$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		return is_string($found) && hash_equals($table, $found);
	}
}

if (!function_exists('backstage_outreach_issue_unsubscribe_token')) {
	function backstage_outreach_issue_unsubscribe_token(string $email, array $context = array())
	{
		$email = sanitize_email($email);
		$email_norm = function_exists('vms_outreach_normalize_email') ? vms_outreach_normalize_email($email) : strtolower($email);
		if ($email === '' || $email_norm === '') {
			return new WP_Error('unsubscribe_email_invalid', __('A valid recipient email is required.', 'backstage-outreach'));
		}
		if (!backstage_outreach_unsubscribe_table_ready()) {
			return new WP_Error('unsubscribe_storage_unavailable', __('Unsubscribe storage is unavailable. No email was submitted.', 'backstage-outreach'));
		}

		try {
			$token = backstage_outreach_base64url_encode(random_bytes(32));
		} catch (Throwable $error) {
			return new WP_Error('unsubscribe_token_unavailable', __('A secure unsubscribe link could not be created. No email was submitted.', 'backstage-outreach'));
		}
		$signature = backstage_outreach_unsubscribe_signature($token);
		if (is_wp_error($signature)) {
			return $signature;
		}

		global $wpdb;
		$inserted = $wpdb->insert(
			backstage_outreach_unsubscribe_table(),
			array(
				'token_hash' => hash('sha256', $token),
				'email' => $email,
				'email_norm' => $email_norm,
				'source_type' => sanitize_key((string) ($context['source_type'] ?? 'outreach')),
				'source_id' => absint($context['source_id'] ?? 0),
				'source_campaign_id' => absint($context['campaign_id'] ?? 0),
				'created_at' => function_exists('vms_outreach_now_mysql') ? vms_outreach_now_mysql() : current_time('mysql'),
			),
			array('%s', '%s', '%s', '%s', '%d', '%d', '%s')
		);
		if ($inserted === false) {
			return new WP_Error('unsubscribe_token_store_failed', __('The unsubscribe link could not be stored. No email was submitted.', 'backstage-outreach'));
		}

		$url = add_query_arg(
			array(
				'backstage-outreach-unsubscribe' => $token,
				'signature' => (string) $signature,
			),
			home_url('/')
		);
		if (!is_string($url) || !str_starts_with($url, 'https://') || wp_http_validate_url($url) === false) {
			return new WP_Error('unsubscribe_url_invalid', __('A secure unsubscribe URL is unavailable. No email was submitted.', 'backstage-outreach'));
		}

		return array(
			'token' => $token,
			'signature' => (string) $signature,
			'url' => $url,
		);
	}
}

if (!function_exists('backstage_outreach_validate_unsubscribe_token')) {
	function backstage_outreach_validate_unsubscribe_token(string $token, string $signature)
	{
		if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token) || !preg_match('/^[A-Za-z0-9_-]{43}$/', $signature)) {
			return new WP_Error('unsubscribe_link_invalid', __('This unsubscribe link is invalid.', 'backstage-outreach'));
		}
		$expected = backstage_outreach_unsubscribe_signature($token);
		if (is_wp_error($expected) || !hash_equals((string) $expected, $signature)) {
			return new WP_Error('unsubscribe_link_invalid', __('This unsubscribe link is invalid.', 'backstage-outreach'));
		}
		if (!backstage_outreach_unsubscribe_table_ready()) {
			return new WP_Error('unsubscribe_storage_unavailable', __('Unsubscribe service is temporarily unavailable.', 'backstage-outreach'));
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare('SELECT * FROM %i WHERE token_hash = %s LIMIT 1', backstage_outreach_unsubscribe_table(), hash('sha256', $token)),
			ARRAY_A
		);
		if (!is_array($row) || empty($row['email_norm'])) {
			return new WP_Error('unsubscribe_link_invalid', __('This unsubscribe link is invalid.', 'backstage-outreach'));
		}
		return $row;
	}
}

if (!function_exists('backstage_outreach_postal_address')) {
	function backstage_outreach_postal_address(): string
	{
		$location = (string) get_option('woocommerce_default_country', '');
		list($country, $state) = array_pad(explode(':', $location, 2), 2, '');
		$address_line = sanitize_text_field((string) get_option('woocommerce_store_address', ''));
		$city = sanitize_text_field((string) get_option('woocommerce_store_city', ''));
		$postcode = sanitize_text_field((string) get_option('woocommerce_store_postcode', ''));
		$locality = trim(implode(' ', array_filter(array(
			$city,
			sanitize_text_field($state),
			$postcode,
		))));
		$parts = $address_line !== '' && $city !== '' && $postcode !== '' && $country !== '' ? array_filter(array(
			sanitize_text_field(wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)),
			$address_line,
			sanitize_text_field((string) get_option('woocommerce_store_address_2', '')),
			$locality,
			sanitize_text_field($country),
		)) : array();
		$address = implode(', ', $parts);
		$address = (string) apply_filters('backstage_outreach_postal_address', $address);
		return sanitize_text_field($address);
	}
}

if (!function_exists('backstage_outreach_mail_transport_readiness')) {
	function backstage_outreach_mail_transport_readiness(): array
	{
		$result = array(
			'ready' => false,
			'signs_rfc8058_headers' => false,
			'method' => 'unverified',
			'message' => __('The configured mail transport does not verify DKIM signing of the RFC 8058 unsubscribe headers.', 'backstage-outreach'),
		);

		if (!class_exists('WP_PHPMailer')) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			require_once ABSPATH . WPINC . '/class-wp-phpmailer.php';
		}
		if (class_exists('WP_PHPMailer')) {
			try {
				$mailer = new WP_PHPMailer(true);
				do_action('phpmailer_init', $mailer);
				$private = (string) $mailer->DKIM_private_string;
				if ($private === '' && (string) $mailer->DKIM_private !== '' && is_readable((string) $mailer->DKIM_private)) {
					$private = (string) file_get_contents((string) $mailer->DKIM_private);
				}
				$key = $private !== '' && function_exists('openssl_pkey_get_private')
					? openssl_pkey_get_private($private, (string) $mailer->DKIM_passphrase)
					: false;
				if ((string) $mailer->DKIM_domain !== '' && (string) $mailer->DKIM_selector !== '' && $key !== false) {
					$result = array(
						'ready' => true,
						'signs_rfc8058_headers' => true,
						'method' => 'phpmailer_dkim',
						'message' => '',
					);
				}
				if (is_resource($key) || $key instanceof OpenSSLAsymmetricKey) {
					openssl_free_key($key);
				}
			} catch (Throwable $error) {
				$result['message'] = __('The configured mail transport could not be verified for RFC 8058 header signing.', 'backstage-outreach');
			}
		}

		$filtered = apply_filters('backstage_outreach_mail_transport_readiness', $result);
		if (!is_array($filtered)) {
			return $result;
		}
		$filtered = array_merge($result, $filtered);
		$filtered['ready'] = !empty($filtered['ready']) && !empty($filtered['signs_rfc8058_headers']);
		return $filtered;
	}
}

if (!function_exists('backstage_outreach_unsubscribe_endpoint_readiness')) {
	function backstage_outreach_unsubscribe_endpoint_readiness(): array
	{
		$home_url = home_url('/');
		$key = backstage_outreach_unsubscribe_signing_key();
		$result = array(
			'ready' => is_string($home_url)
				&& str_starts_with($home_url, 'https://')
				&& wp_http_validate_url($home_url) !== false
				&& backstage_outreach_unsubscribe_table_ready()
				&& !is_wp_error($key)
				&& has_action('template_redirect', 'backstage_outreach_render_unsubscribe_page') !== false,
			'message' => __('The secure public unsubscribe confirmation endpoint is unavailable. No email was submitted.', 'backstage-outreach'),
		);
		$filtered = apply_filters('backstage_outreach_unsubscribe_endpoint_readiness', $result);
		return is_array($filtered) ? array_merge($result, $filtered) : $result;
	}
}

if (!function_exists('backstage_outreach_require_dkim_unsubscribe_headers')) {
	function backstage_outreach_require_dkim_unsubscribe_headers($mailer): void
	{
		if (!is_object($mailer) || !property_exists($mailer, 'DKIM_extraHeaders')) {
			return;
		}
		$headers = array_merge((array) $mailer->DKIM_extraHeaders, array('List-Unsubscribe', 'List-Unsubscribe-Post'));
		$mailer->DKIM_extraHeaders = array_values(array_unique($headers));
	}
}

if (!function_exists('backstage_outreach_promotional_footer')) {
	function backstage_outreach_promotional_footer(string $unsubscribe_url, string $postal_address): string
	{
		return implode("\n", array(
			'--',
			sprintf(__('This promotional email was sent by %s.', 'backstage-outreach'), wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)),
			sprintf(__('Postal address: %s', 'backstage-outreach'), $postal_address),
			sprintf(__('Unsubscribe from all Backstage Outreach promotional email: %s', 'backstage-outreach'), $unsubscribe_url),
		));
	}
}

if (!function_exists('backstage_outreach_email_lock_name')) {
	function backstage_outreach_email_lock_name(string $email): string
	{
		$email_norm = function_exists('vms_outreach_normalize_email') ? vms_outreach_normalize_email($email) : strtolower($email);
		return 'outreach_send_' . substr(hash('sha256', $email_norm), 0, 40);
	}
}

if (!function_exists('backstage_outreach_send_promotional_email')) {
	function backstage_outreach_send_promotional_email(string $email, string $subject, string $message, array $headers = array(), array $context = array())
	{
		$email = sanitize_email($email);
		if ($email === '' || !is_email($email)) {
			return new WP_Error('outreach_email_invalid', __('A valid recipient email is required. No email was submitted.', 'backstage-outreach'));
		}
		if (!function_exists('vms_outreach_email_is_suppressed') || !function_exists('vms_outreach_upsert_suppression')) {
			return new WP_Error('outreach_suppression_unavailable', __('Outreach suppression checks are unavailable. No email was submitted.', 'backstage-outreach'));
		}
		$postal_address = backstage_outreach_postal_address();
		if ($postal_address === '') {
			return new WP_Error('outreach_postal_address_unavailable', __('The required postal sender address is unavailable. No email was submitted.', 'backstage-outreach'));
		}
		$endpoint = backstage_outreach_unsubscribe_endpoint_readiness();
		if (empty($endpoint['ready'])) {
			return new WP_Error('outreach_unsubscribe_endpoint_unavailable', (string) ($endpoint['message'] ?? __('The secure public unsubscribe confirmation endpoint is unavailable. No email was submitted.', 'backstage-outreach')));
		}
		$transport = backstage_outreach_mail_transport_readiness();
		$require_rfc8058 = (bool) apply_filters('backstage_outreach_require_rfc8058', false, $email, $context);
		$advertise_rfc8058 = !empty($transport['ready']);
		if ($require_rfc8058 && !$advertise_rfc8058) {
			return new WP_Error('outreach_mail_transport_unverified', (string) ($transport['message'] ?? __('The mail transport is unverified. No email was submitted.', 'backstage-outreach')));
		}

		global $wpdb;
		$lock = backstage_outreach_email_lock_name($email);
		if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
			return new WP_Error('outreach_send_lock_unavailable', __('The recipient suppression gate could not be locked. No email was submitted.', 'backstage-outreach'));
		}

		try {
			if (vms_outreach_email_is_suppressed($email)) {
				return new WP_Error('outreach_suppressed', __('This recipient is globally suppressed from Outreach email. No email was submitted.', 'backstage-outreach'));
			}
			$link = backstage_outreach_issue_unsubscribe_token($email, $context);
			if (is_wp_error($link)) {
				return $link;
			}
			if (vms_outreach_email_is_suppressed($email)) {
				return new WP_Error('outreach_suppressed', __('This recipient is globally suppressed from Outreach email. No email was submitted.', 'backstage-outreach'));
			}

			$url = (string) $link['url'];
			$message = rtrim($message) . "\n\n" . backstage_outreach_promotional_footer($url, $postal_address);
			if ($advertise_rfc8058) {
				$headers[] = 'List-Unsubscribe: <' . $url . '>';
				$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
			}
			$headers = array_values(array_unique(array_map('strval', $headers)));

			$mail_error = '';
			$mail_capture = static function ($wp_error) use (&$mail_error): void {
				if (is_wp_error($wp_error)) {
					$mail_error = $wp_error->get_error_message();
				}
			};
			if ($advertise_rfc8058) {
				add_action('phpmailer_init', 'backstage_outreach_require_dkim_unsubscribe_headers', PHP_INT_MAX, 1);
			}
			add_action('wp_mail_failed', $mail_capture, 10, 1);
			try {
				$accepted = wp_mail($email, $subject, $message, $headers);
			} finally {
				remove_action('wp_mail_failed', $mail_capture, 10);
				if ($advertise_rfc8058) {
					remove_action('phpmailer_init', 'backstage_outreach_require_dkim_unsubscribe_headers', PHP_INT_MAX);
				}
			}
			if (!$accepted) {
				return new WP_Error('wp_mail_failed', $mail_error !== '' ? $mail_error : __('WordPress did not accept the Outreach email for delivery.', 'backstage-outreach'));
			}
			return true;
		} finally {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
		}
	}
}

if (!function_exists('backstage_outreach_confirm_unsubscribe')) {
	function backstage_outreach_confirm_unsubscribe(array $token_row): array
	{
		$email = sanitize_email((string) ($token_row['email'] ?? ''));
		if ($email === '' || !function_exists('vms_outreach_upsert_suppression')) {
			return array('status' => 'error', 'message' => __('Unsubscribe service is temporarily unavailable.', 'backstage-outreach'));
		}
		global $wpdb;
		$lock = backstage_outreach_email_lock_name($email);
		if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
			return array('status' => 'error', 'message' => __('Unsubscribe service is temporarily busy. Please try again.', 'backstage-outreach'));
		}
		try {
			$existing = function_exists('vms_outreach_get_suppression_by_email') ? vms_outreach_get_suppression_by_email($email) : null;
			if (is_array($existing)) {
				return array('status' => 'already', 'message' => __('You were already unsubscribed from Backstage Outreach promotional email.', 'backstage-outreach'));
			}
			$suppression = vms_outreach_upsert_suppression(array(
				'email' => $email,
				'reason' => 'unsubscribe_request',
				'scope' => function_exists('vms_outreach_default_suppression_scope') ? vms_outreach_default_suppression_scope() : 'global_outreach',
				'source_campaign_id' => absint($token_row['source_campaign_id'] ?? 0),
				'source_label' => sanitize_text_field((string) ($token_row['source_type'] ?? 'outreach')),
				'notes' => __('Created from a confirmed self-service Outreach unsubscribe request.', 'backstage-outreach'),
			), 0);
			if (is_wp_error($suppression) || !is_array($suppression)) {
				return array('status' => 'error', 'message' => is_wp_error($suppression) ? $suppression->get_error_message() : __('Unsubscribe could not be saved.', 'backstage-outreach'));
			}
			$wpdb->update(
				backstage_outreach_unsubscribe_table(),
				array('confirmed_at' => function_exists('vms_outreach_now_mysql') ? vms_outreach_now_mysql() : current_time('mysql')),
				array('id' => absint($token_row['id'] ?? 0)),
				array('%s'),
				array('%d')
			);
			return array('status' => 'success', 'message' => __('You have been unsubscribed from all Backstage Outreach promotional email.', 'backstage-outreach'));
		} finally {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
		}
	}
}

if (!function_exists('backstage_outreach_unsubscribe_page')) {
	function backstage_outreach_unsubscribe_page(string $method, string $token, string $signature, array $post = array()): array
	{
		$row = backstage_outreach_validate_unsubscribe_token($token, $signature);
		if (is_wp_error($row)) {
			return array('http_status' => 400, 'status' => 'error', 'message' => $row->get_error_message(), 'show_form' => false);
		}
		$existing = function_exists('vms_outreach_get_suppression_by_email') ? vms_outreach_get_suppression_by_email((string) $row['email']) : null;
		if ($method === 'GET') {
			if (is_array($existing)) {
				return array('http_status' => 200, 'status' => 'already', 'message' => __('You were already unsubscribed from Backstage Outreach promotional email.', 'backstage-outreach'), 'show_form' => false);
			}
			return array('http_status' => 200, 'status' => 'confirm', 'message' => __('Confirm that you want to stop all Backstage Outreach promotional email.', 'backstage-outreach'), 'show_form' => true);
		}
		if ($method !== 'POST') {
			return array('http_status' => 405, 'status' => 'error', 'message' => __('This request method is not supported.', 'backstage-outreach'), 'show_form' => false);
		}
		$one_click = isset($post['List-Unsubscribe']) && hash_equals('One-Click', (string) $post['List-Unsubscribe']);
		$browser_confirm = isset($post['backstage_outreach_confirm']) && hash_equals('1', (string) $post['backstage_outreach_confirm']);
		if (!$one_click && !$browser_confirm) {
			return array('http_status' => 400, 'status' => 'error', 'message' => __('Unsubscribe confirmation was missing.', 'backstage-outreach'), 'show_form' => false);
		}
		$result = backstage_outreach_confirm_unsubscribe($row);
		$result['http_status'] = $result['status'] === 'error' ? 503 : 200;
		$result['show_form'] = false;
		return $result;
	}
}

if (!function_exists('backstage_outreach_render_unsubscribe_page')) {
	function backstage_outreach_render_unsubscribe_page(): void
	{
		if (!isset($_GET['backstage-outreach-unsubscribe'])) {
			return;
		}
		$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field((string) wp_unslash($_SERVER['REQUEST_METHOD']))) : 'GET';
		$token = sanitize_text_field((string) wp_unslash($_GET['backstage-outreach-unsubscribe']));
		$signature = isset($_GET['signature']) ? sanitize_text_field((string) wp_unslash($_GET['signature'])) : '';
		$post = array();
		foreach (array('List-Unsubscribe', 'backstage_outreach_confirm') as $key) {
			if (isset($_POST[$key]) && !is_array($_POST[$key])) {
				$post[$key] = sanitize_text_field((string) wp_unslash($_POST[$key]));
			}
		}
		$state = backstage_outreach_unsubscribe_page($method, $token, $signature, $post);
		status_header((int) $state['http_status']);
		if ((int) $state['http_status'] === 405) {
			header('Allow: GET, POST');
		}
		nocache_headers();
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Referrer-Policy: no-referrer');
		header('X-Robots-Tag: noindex, nofollow, noarchive', true);
		header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
		$title = __('Outreach email preferences', 'backstage-outreach');
		echo '<!doctype html><html lang="' . esc_attr(get_bloginfo('language')) . '"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html($title) . '</title><style>body{margin:0;background:#f4f4f5;color:#18181b;font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{box-sizing:border-box;min-height:100vh;padding:clamp(24px,8vw,72px) 16px}.card{box-sizing:border-box;max-width:640px;margin:0 auto;padding:clamp(24px,6vw,48px);background:#fff;border:1px solid #d4d4d8;border-radius:16px;box-shadow:0 12px 36px rgba(0,0,0,.08)}h1{margin:0 0 16px;font-size:clamp(1.65rem,7vw,2.25rem);line-height:1.15}p{margin:0 0 20px;overflow-wrap:anywhere}form{margin:0 0 20px}.button{appearance:none;border:0;border-radius:8px;background:#18181b;color:#fff;cursor:pointer;font:inherit;font-weight:700;padding:12px 18px}.button:hover,.button:focus{background:#3f3f46;outline:3px solid #a1a1aa;outline-offset:3px}.meta{color:#52525b;font-size:.925rem}@media(max-width:390px){.wrap{padding:16px 12px}.card{padding:24px 18px;border-radius:12px}}</style></head><body><main class="wrap"><section class="card"><h1>' . esc_html($title) . '</h1><p>' . esc_html((string) $state['message']) . '</p>';
		if (!empty($state['show_form'])) {
			echo '<form method="post" action="' . esc_url(add_query_arg(array('backstage-outreach-unsubscribe' => $token, 'signature' => $signature), home_url('/'))) . '"><input type="hidden" name="backstage_outreach_confirm" value="1"><button class="button" type="submit">' . esc_html__('Confirm unsubscribe', 'backstage-outreach') . '</button></form>';
		}
		echo '<p class="meta">' . esc_html__('This changes only Backstage Outreach promotional email. It does not change separate newsletter subscriptions.', 'backstage-outreach') . '</p></section></main></body></html>';
		exit;
	}
}
add_action('template_redirect', 'backstage_outreach_render_unsubscribe_page', 0);
