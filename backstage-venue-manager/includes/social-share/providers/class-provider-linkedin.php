<?php
defined('ABSPATH') || exit;

class BVMGR_Social_Provider_LinkedIn implements BVMGR_Social_Provider_Interface
{
	public function get_platform_key(): string
	{
		return 'linkedin';
	}

	public function get_display_name(): string
	{
		return 'LinkedIn';
	}

	public function get_capabilities(): array
	{
		return array(
			'text' => true,
			'link' => true,
			'image' => true,
			'video' => false,
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
		return array('ok' => false, 'message' => 'linkedin_oauth_not_implemented');
	}

	public function validate_connection(int $account_id): array
	{
		return array(
			'ok' => false,
			'auth_state' => 'error',
			'destinations' => array(),
			'permissions_ok' => false,
			'warnings' => array('LinkedIn provider is stubbed in Phase 0/1.'),
		);
	}

	public function build_payload(array $queue_row, array $event_context): array
	{
		return array(
			'commentary' => (string) ($queue_row['rendered_caption'] ?? ''),
			'content_url' => (string) ($queue_row['final_url'] ?? ''),
		);
	}

	public function publish(int $account_id, string $destination_id, array $rendered_payload): array
	{
		throw new RuntimeException('LinkedIn provider is not implemented in Phase 0/1.');
	}

	public function classify_error($error): array
	{
		$message = $error instanceof Throwable ? $error->getMessage() : (string) $error;
		return array(
			'retryable' => false,
			'needs_review' => true,
			'auth_expired' => false,
			'error_code' => 'linkedin_stub',
			'message' => $message,
		);
	}
}
