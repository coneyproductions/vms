<?php
defined('ABSPATH') || exit;

class BVMGR_Social_Provider_Webhook implements BVMGR_Social_Provider_Interface
{
	public function get_platform_key(): string
	{
		return 'webhook';
	}

	public function get_display_name(): string
	{
		return 'Webhook';
	}

	public function get_capabilities(): array
	{
		return array(
			'text' => true,
			'link' => true,
			'image' => true,
			'video' => true,
		);
	}

	public function get_oauth_fields(): array
	{
		return array(
			'webhook_url' => 'Webhook URL',
			'signing_secret' => 'Signing secret',
		);
	}

	public function start_oauth(array $args = array())
	{
		return '';
	}

	public function handle_oauth_callback(array $request = array()): array
	{
		return array('ok' => true, 'message' => 'webhook_no_oauth');
	}

	public function validate_connection(int $account_id): array
	{
		$cfg = function_exists('bvmgr_social_account_token_json') ? bvmgr_social_account_token_json($account_id) : array();
		$url = esc_url_raw((string) ($cfg['webhook_url'] ?? ''));
		$ok = $url !== '';
		return array(
			'ok' => $ok,
			'auth_state' => $ok ? 'connected' : 'error',
			'destinations' => $ok ? array(array('id' => $url, 'name' => $url)) : array(),
			'permissions_ok' => $ok,
			'warnings' => $ok ? array() : array('Webhook URL is missing.'),
		);
	}

	public function build_payload(array $queue_row, array $event_context): array
	{
		return array(
			'event' => array(
				'event_plan_id' => (int) ($queue_row['event_plan_id'] ?? 0),
				'tec_event_id' => (int) ($queue_row['tec_event_id'] ?? 0),
				'title' => (string) ($event_context['event_title'] ?? ''),
				'date' => (string) ($event_context['event_date'] ?? ''),
				'start_time' => (string) ($event_context['start_time'] ?? ''),
				'end_time' => (string) ($event_context['end_time'] ?? ''),
			),
			'venue' => array(
				'id' => (int) ($queue_row['venue_id'] ?? 0),
				'name' => (string) ($event_context['venue_name'] ?? ''),
				'city' => (string) ($event_context['venue_city'] ?? ''),
				'state' => (string) ($event_context['venue_state'] ?? ''),
			),
			'rendered_caption' => (string) ($queue_row['rendered_caption'] ?? ''),
			'final_url' => (string) ($queue_row['final_url'] ?? ''),
			'featured_image_url' => (string) ($event_context['featured_image_url'] ?? ''),
			'queue_id' => (int) ($queue_row['id'] ?? 0),
			'platform' => 'webhook',
		);
	}

	public function publish(int $account_id, string $destination_id, array $rendered_payload): array
	{
		$cfg = function_exists('bvmgr_social_account_token_json') ? bvmgr_social_account_token_json($account_id) : array();
		$url = esc_url_raw((string) ($cfg['webhook_url'] ?? ''));
		if ($url === '') {
			throw new RuntimeException('Webhook URL is missing.');
		}

		$secret = (string) ($cfg['signing_secret'] ?? '');
		$json = wp_json_encode($rendered_payload);
		if (!is_string($json) || $json === '') {
			throw new RuntimeException('Unable to encode webhook payload.');
		}

		$headers = array(
			'Content-Type' => 'application/json',
		);
		if ($secret !== '') {
			$headers['X-VMS-Signature'] = hash_hmac('sha256', $json, $secret);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 12,
				'headers' => $headers,
				'body' => $json,
			)
		);

		if (is_wp_error($response)) {
			throw new RuntimeException((string) $response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal plain-text webhook transport diagnostic; the queue runner sanitizes it for storage and downstream sinks escape or JSON-encode it contextually.
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		if ($code < 200 || $code >= 300) {
			throw new RuntimeException('Webhook returned HTTP ' . $code); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal plain-text webhook status diagnostic; the queue runner sanitizes it for storage and downstream sinks escape or JSON-encode it contextually.
		}

		return array(
			'platform_post_id' => 'webhook-' . wp_generate_uuid4(),
			'http_code' => $code,
			'destination_id' => $destination_id,
		);
	}

	public function classify_error($error): array
	{
		$message = $error instanceof Throwable ? $error->getMessage() : (string) $error;
		$retryable = true;
		$needs_review = false;
		$auth_expired = false;
		$code = 'webhook_error';

		if (stripos($message, 'missing') !== false) {
			$retryable = false;
			$needs_review = true;
			$code = 'webhook_config';
		}
		if (stripos($message, '401') !== false || stripos($message, '403') !== false) {
			$auth_expired = true;
			$retryable = false;
			$code = 'webhook_auth';
		}

		return array(
			'retryable' => $retryable,
			'needs_review' => $needs_review,
			'auth_expired' => $auth_expired,
			'error_code' => $code,
			'message' => $message,
		);
	}
}
