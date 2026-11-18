<?php
defined('ABSPATH') || exit;

interface BVMGR_Social_Provider_Interface
{
	public function get_platform_key(): string;

	public function get_display_name(): string;

	/**
	 * @return array<string,bool>
	 */
	public function get_capabilities(): array;

	/**
	 * @return array<string,string>
	 */
	public function get_oauth_fields(): array;

	/**
	 * @param array<string,mixed> $args
	 * @return string|array<string,mixed>
	 */
	public function start_oauth(array $args = array());

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	public function handle_oauth_callback(array $request = array()): array;

	/**
	 * @return array<string,mixed>
	 */
	public function validate_connection(int $account_id): array;

	/**
	 * @param array<string,mixed> $queue_row
	 * @param array<string,mixed> $event_context
	 * @return array<string,mixed>
	 */
	public function build_payload(array $queue_row, array $event_context): array;

	/**
	 * @param array<string,mixed> $rendered_payload
	 * @return array<string,mixed>
	 */
	public function publish(int $account_id, string $destination_id, array $rendered_payload): array;

	/**
	 * @param mixed $error
	 * @return array<string,mixed>
	 */
	public function classify_error($error): array;
}
