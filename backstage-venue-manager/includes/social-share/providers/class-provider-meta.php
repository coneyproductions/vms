<?php
defined('ABSPATH') || exit;

class BVMGR_Social_Provider_Meta implements BVMGR_Social_Provider_Interface
{
	public function get_platform_key(): string
	{
		return 'meta';
	}

	public function get_display_name(): string
	{
		return 'Meta (Facebook + Instagram)';
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
			'client_id' => 'Client ID',
			'client_secret' => 'Client Secret',
			'redirect_uri' => 'Redirect URI',
		);
	}

	public function start_oauth(array $args = array())
	{
		return '';
	}

	public function handle_oauth_callback(array $request = array()): array
	{
		return array('ok' => false, 'message' => 'meta_oauth_not_implemented');
	}

	public function validate_connection(int $account_id): array
	{
		return array(
			'ok' => false,
			'auth_state' => 'error',
			'destinations' => array(),
			'permissions_ok' => false,
			'warnings' => array('Meta provider is stubbed in Phase 0/1.'),
		);
	}

	public function build_payload(array $queue_row, array $event_context): array
	{
		return array(
			'message' => (string) ($queue_row['rendered_caption'] ?? ''),
			'link' => (string) ($queue_row['final_url'] ?? ''),
			'platform' => (string) ($queue_row['platform'] ?? 'facebook'),
		);
	}

	public function publish(int $account_id, string $destination_id, array $rendered_payload): array
	{
		throw new RuntimeException('Meta provider is not implemented in Phase 0/1.');
	}

	public function classify_error($error): array
	{
		$message = $error instanceof Throwable ? $error->getMessage() : (string) $error;
		return array(
			'retryable' => false,
			'needs_review' => true,
			'auth_expired' => false,
			'error_code' => 'meta_stub',
			'message' => $message,
		);
	}
}
