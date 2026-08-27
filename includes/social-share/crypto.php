<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_social_crypto_secret')) {
	function bvmgr_social_crypto_secret(): string
	{
		$material = wp_salt('auth') . '|' . wp_salt('secure_auth') . '|vms_social_tokens';
		return hash('sha256', $material, true);
	}
}

if (!function_exists('bvmgr_social_encrypt_string')) {
	function bvmgr_social_encrypt_string(string $plaintext): string
	{
		$plaintext = (string) $plaintext;
		if ($plaintext === '') {
			return '';
		}

		$key = bvmgr_social_crypto_secret();

		if (function_exists('sodium_crypto_secretbox')) {
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$cipher = sodium_crypto_secretbox($plaintext, $nonce, substr($key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
			$payload = array(
				'v' => 1,
				'alg' => 'sodium_secretbox',
				'n' => base64_encode($nonce),
				'c' => base64_encode($cipher),
			);
			return base64_encode(wp_json_encode($payload));
		}

		if (function_exists('openssl_encrypt')) {
			$iv = random_bytes(12);
			$cipher_raw = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
			if (is_string($cipher_raw) && $cipher_raw !== '') {
				$payload = array(
					'v' => 1,
					'alg' => 'openssl_aes_256_gcm',
					'i' => base64_encode($iv),
					't' => base64_encode((string) $tag),
					'c' => base64_encode($cipher_raw),
				);
				return base64_encode(wp_json_encode($payload));
			}
		}

		return '';
	}
}

if (!function_exists('bvmgr_social_decrypt_string')) {
	function bvmgr_social_decrypt_string(string $encoded): string
	{
		$encoded = trim((string) $encoded);
		if ($encoded === '') {
			return '';
		}

		$decoded = base64_decode($encoded, true);
		if (!is_string($decoded) || $decoded === '') {
			return '';
		}

		$data = json_decode($decoded, true);
		if (!is_array($data)) {
			return '';
		}

		$key = bvmgr_social_crypto_secret();
		$alg = isset($data['alg']) ? (string) $data['alg'] : '';

		if ($alg === 'sodium_secretbox' && function_exists('sodium_crypto_secretbox_open')) {
			$nonce = base64_decode((string) ($data['n'] ?? ''), true);
			$cipher = base64_decode((string) ($data['c'] ?? ''), true);
			if (!is_string($nonce) || !is_string($cipher)) {
				return '';
			}
			$plain = sodium_crypto_secretbox_open($cipher, $nonce, substr($key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
			return is_string($plain) ? $plain : '';
		}

		if ($alg === 'openssl_aes_256_gcm' && function_exists('openssl_decrypt')) {
			$iv = base64_decode((string) ($data['i'] ?? ''), true);
			$tag = base64_decode((string) ($data['t'] ?? ''), true);
			$cipher = base64_decode((string) ($data['c'] ?? ''), true);
			if (!is_string($iv) || !is_string($tag) || !is_string($cipher)) {
				return '';
			}
			$plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
			return is_string($plain) ? $plain : '';
		}

		return '';
	}
}

if (!function_exists('bvmgr_social_encrypt_json')) {
	function bvmgr_social_encrypt_json(array $payload): string
	{
		$json = wp_json_encode($payload);
		if (!is_string($json) || $json === '') {
			return '';
		}
		return bvmgr_social_encrypt_string($json);
	}
}

if (!function_exists('bvmgr_social_decrypt_json')) {
	function bvmgr_social_decrypt_json(string $blob): array
	{
		$json = bvmgr_social_decrypt_string($blob);
		if ($json === '') {
			return array();
		}
		$decoded = json_decode($json, true);
		return is_array($decoded) ? $decoded : array();
	}
}
