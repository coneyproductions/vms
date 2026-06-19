<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_admission_normalize_name')) {
	function vms_admission_normalize_name(string $name): string
	{
		$name = trim($name);
		if ($name === '') {
			return '';
		}

		$name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
		$name = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name);
		$name = preg_replace('/\s+/u', ' ', (string) $name);
		$name = trim((string) $name);

		if (function_exists('mb_substr')) {
			$name = mb_substr($name, 0, 220, 'UTF-8');
		} else {
			$name = substr($name, 0, 220);
		}

		return $name;
	}
}

if (!function_exists('vms_admission_mask_phone')) {
	function vms_admission_mask_phone(string $phone): string
	{
		$digits = preg_replace('/\D+/', '', $phone);
		if (!is_string($digits) || $digits === '') {
			return '';
		}
		$tail = substr($digits, -4);
		return '***-***-' . $tail;
	}
}


if (!function_exists('vms_admission_normalize_phone')) {
	function vms_admission_normalize_phone(string $phone): string
	{
		$digits = preg_replace('/\D+/', '', $phone);
		if (!is_string($digits)) {
			return '';
		}
		$digits = trim($digits);
		if ($digits === '') {
			return '';
		}
		if (strlen($digits) > 10 && strpos($digits, '1') === 0) {
			$digits = substr($digits, -10);
		}
		if (strlen($digits) > 40) {
			$digits = substr($digits, -40);
		}
		return $digits;
	}
}

if (!function_exists('vms_admission_normalize_email')) {
	function vms_admission_normalize_email(string $email): string
	{
		$email = sanitize_email($email);
		if ($email === '') {
			return '';
		}
		return function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
	}
}
