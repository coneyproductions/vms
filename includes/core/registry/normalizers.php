<?php
defined('ABSPATH') || exit;

/**
 * Normalize/salvage an email value from a messy CSV cell.
 *
 * Contract:
 * - Return non-empty email ONLY if is_email(email) is true.
 * - Otherwise return email='' with a non-empty warning.
 */
function vms_normalize_email_cell(string $raw): array
{
	$raw = trim($raw);

	$is_valid = static function (string $email): bool {
		$email = trim($email);
		return $email !== '' && (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
	};

	$out = ['email' => '', 'warning' => ''];

	if ($raw === '') return $out;

	if ($is_valid($raw)) {
		return ['email' => $raw, 'warning' => ''];
	}

	if (strpos($raw, '@@') !== false) {
		$fixed = str_replace('@@', '@', $raw);
		if ($is_valid($fixed)) {
			return [
				'email'   => $fixed,
				'warning' => 'Email contained "@@"; corrected to a single "@": ' . $fixed,
			];
		}
	}

	$parts = preg_split('/[,\s;]+/', $raw) ?: [];
	$parts = array_values(array_filter(array_map('trim', $parts), static fn($v) => $v !== ''));

	foreach ($parts as $p) {
		$p = trim($p, " \t\n\r\0\x0B<>()[]{}\"'");
		if ($is_valid($p)) {
			return [
				'email'   => $p,
				'warning' => 'Multiple emails or extra text found; using first valid email: ' . $p,
			];
		}
	}

	// Smashed emails: local@ + truncate rest at first plausible TLD boundary
	$at = strpos($raw, '@');
	if ($at !== false) {
		$local = substr($raw, 0, $at);
		$rest  = trim(substr($raw, $at + 1), " \t\n\r\0\x0B<>()[]{}\"'");

		$tlds = ['.com', '.net', '.org', '.edu', '.gov', '.me', '.co', '.io', '.biz', '.info'];
		foreach ($tlds as $tld) {
			$pos = stripos($rest, $tld);
			if ($pos === false) continue;

			$cand = trim($local . '@' . substr($rest, 0, $pos + strlen($tld)));
			if ($is_valid($cand)) {
				return [
					'email'   => $cand,
					'warning' => 'Malformed email cell; extracted valid email: ' . $cand,
				];
			}
		}
	}

	return [
		'email'   => '',
		'warning' => 'Primary Email cell is invalid and no valid email could be extracted.',
	];
}
