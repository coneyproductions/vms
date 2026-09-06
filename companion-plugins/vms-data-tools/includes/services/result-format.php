<?php
defined('ABSPATH') || exit;

function vms_dt_result_error(string $code, string $message): array {
	return [
		'ok'     => false,
		'errors' => [
			[
				'code'    => $code,
				'message' => $message,
			],
		],
	];
}

function vms_dt_err(string $code, string $message): array {
	return [
		'code'    => $code,
		'message' => $message,
	];
}

function vms_dt_warn(string $code, string $message): array {
	return [
		'code'    => $code,
		'message' => $message,
	];
}
