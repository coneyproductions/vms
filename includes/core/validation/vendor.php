<?php
defined('ABSPATH') || exit;

/**
 * Validate and sanitize vendor input.
 * Returns: ['ok' => bool, 'data' => array, 'errors' => array]
 */
function bvmgr_validate_vendor_input(array $input): array {
	$schema = bvmgr_vendor_schema();

	$out = [];
	$errors = [];

	foreach ($schema as $field => $def) {
		$value = $input[$field] ?? null;

		// Required checks
		if (!empty($def['required'])) {
			if ($value === null || (is_string($value) && trim($value) === '')) {
				$errors[$field] = ($def['label'] ?? $field) . ' is required.';
				continue;
			}
		}

		// Empty optional values: normalize to empty string
		if ($value === null) {
			$out[$field] = '';
			continue;
		}

		// Type sanitize
		$type = $def['type'] ?? 'string';

		if ($type === 'email') {
			$san = sanitize_email((string) $value);
			if ($san !== '' && !is_email($san)) {
				$errors[$field] = ($def['label'] ?? $field) . ' must be a valid email.';
			}
			$out[$field] = $san;
			continue;
		}

		if ($type === 'url') {
			$san = esc_url_raw((string) $value);
			$out[$field] = $san;
			continue;
		}

		// default string
		$out[$field] = sanitize_text_field((string) $value);
	}

	return [
		'ok'     => empty($errors),
		'data'   => $out,
		'errors' => $errors,
	];
}
