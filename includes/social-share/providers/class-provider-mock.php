<?php
defined('ABSPATH') || exit;

class BVMGR_Social_Provider_Mock implements BVMGR_Social_Provider_Interface
{
	public function get_platform_key(): string
	{
		return 'mock';
	}

	public function get_display_name(): string
	{
		return 'Mock Provider';
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
		return array();
	}

	public function start_oauth(array $args = array())
	{
		return '';
	}

	public function handle_oauth_callback(array $request = array()): array
	{
		return array('ok' => true, 'message' => 'mock_noop');
	}

	public function validate_connection(int $account_id): array
	{
		return array(
			'ok' => true,
			'auth_state' => 'connected',
			'destinations' => array(
				array('id' => 'mock-default', 'name' => 'Mock Destination'),
			),
			'permissions_ok' => true,
			'warnings' => array(),
		);
	}

	public function build_payload(array $queue_row, array $event_context): array
	{
		return array(
			'caption' => (string) ($queue_row['rendered_caption'] ?? ''),
			'url' => (string) ($queue_row['final_url'] ?? ''),
			'event_title' => (string) ($event_context['event_title'] ?? ''),
			'event_plan_id' => (int) ($queue_row['event_plan_id'] ?? 0),
		);
	}

	public function publish(int $account_id, string $destination_id, array $rendered_payload): array
	{
		return array(
			'platform_post_id' => 'mock-' . wp_generate_uuid4(),
			'destination_id' => $destination_id,
			'echo' => $rendered_payload,
		);
	}

	public function classify_error($error): array
	{
		$message = $error instanceof Throwable ? $error->getMessage() : (string) $error;
		return array(
			'retryable' => true,
			'needs_review' => false,
			'auth_expired' => false,
			'error_code' => 'mock_error',
			'message' => $message,
		);
	}
}
