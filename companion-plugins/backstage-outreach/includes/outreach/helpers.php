<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_outreach_nonce_field')) {
	function vms_outreach_nonce_field(string $action, string $input_id): void
	{
		$input_id = sanitize_html_class($input_id);
		$markup = wp_nonce_field($action, '_wpnonce', true, false);
		$markup = str_replace('id="_wpnonce"', 'id="' . esc_attr($input_id) . '"', $markup);

		// WordPress generated and escaped the field markup; only the escaped id attribute changed.
		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if (!function_exists('vms_outreach_now_mysql')) {
	function vms_outreach_now_mysql(): string
	{
		return function_exists('current_time') ? (string) current_time('mysql') : gmdate('Y-m-d H:i:s');
	}
}

if (!function_exists('vms_outreach_normalize_email')) {
	function vms_outreach_normalize_email(string $email): string
	{
		$email = sanitize_email(trim($email));
		return $email !== '' ? strtolower($email) : '';
	}
}

if (!function_exists('vms_outreach_normalize_phone')) {
	function vms_outreach_normalize_phone(string $phone): string
	{
		$digits = preg_replace('/\D+/', '', $phone);
		if (!is_string($digits)) {
			return '';
		}
		if (strlen($digits) > 15) {
			$digits = substr($digits, 0, 15);
		}
		return $digits;
	}
}

if (!function_exists('vms_outreach_split_contact_name')) {
	function vms_outreach_split_contact_name(string $name): array
	{
		$name = trim(preg_replace('/\s+/u', ' ', $name));
		if ($name === '') {
			return array('', '');
		}

		$parts = preg_split('/\s+/u', $name, 2);
		return array(
			sanitize_text_field((string) ($parts[0] ?? '')),
			sanitize_text_field((string) ($parts[1] ?? '')),
		);
	}
}

if (!function_exists('vms_outreach_compose_contact_name')) {
	function vms_outreach_compose_contact_name(string $first_name, string $last_name, string $fallback = ''): string
	{
		$name = trim(sanitize_text_field($first_name) . ' ' . sanitize_text_field($last_name));
		if ($name !== '') {
			return $name;
		}
		return sanitize_text_field($fallback);
	}
}

if (!function_exists('vms_outreach_trim_to_null')) {
	function vms_outreach_trim_to_null(string $value): ?string
	{
		$value = trim($value);
		return $value === '' ? null : $value;
	}
}

if (!function_exists('vms_outreach_normalize_tags')) {
	function vms_outreach_normalize_tags($raw): string
	{
		if (is_array($raw)) {
			$parts = array();
			foreach ($raw as $value) {
				$parts = array_merge($parts, preg_split('/[\r\n,;|]+/', (string) $value) ?: array());
			}
		} else {
			$parts = preg_split('/[\r\n,;|]+/', (string) $raw);
		}

		$seen = array();
		$clean = array();
		foreach ((array) $parts as $part) {
			$tag = sanitize_text_field((string) $part);
			if ($tag === '') {
				continue;
			}
			$key = strtolower($tag);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$clean[] = $tag;
		}

		return implode(', ', $clean);
	}
}

if (!function_exists('vms_outreach_append_tag')) {
	function vms_outreach_append_tag(string $tags, string $tag): string
	{
		$values = $tags !== '' ? explode(',', $tags) : array();
		$values[] = $tag;
		return vms_outreach_normalize_tags($values);
	}
}

if (!function_exists('vms_outreach_normalize_url_field')) {
	function vms_outreach_normalize_url_field(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}

		if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $raw) && preg_match('/^[A-Z0-9._%\-]+\.[A-Z]{2,}(?:[\/?#].*)?$/i', $raw)) {
			$raw = 'https://' . $raw;
		}

		return esc_url_raw($raw);
	}
}

if (!function_exists('vms_outreach_value_present')) {
	function vms_outreach_value_present($value): bool
	{
		if (is_string($value)) {
			return trim($value) !== '';
		}
		return !empty($value);
	}
}
