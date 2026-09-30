<?php
namespace BVMGR\Admissions;

defined('ABSPATH') || exit;

require_once __DIR__ . '/qr/QRCode.php';

/** Local, in-memory PNG rendering for the existing admission bearer payload. */
final class Local_QR extends \BVMGR\Vendor\PHPQRCode\QRCode
{
	public static function data_uri(string $payload): string
	{
		if (!preg_match('/\Avms-admission:[a-zA-Z0-9]{32,80}\z/', $payload)) {
			return '';
		}
		return self::render_data_uri($payload);
	}

	/** Render a same-site Guest Pass claim URL without broadening admission QR inputs. */
	public static function claim_url_data_uri(string $payload, string $site_url): string
	{
		if (!self::is_safe_claim_url($payload, $site_url)) {
			return '';
		}
		return self::render_data_uri($payload);
	}

	private static function is_safe_claim_url(string $payload, string $site_url): bool
	{
		if ($payload === '' || $site_url === '' || strlen($payload) > 2048 || preg_match('/[\x00-\x20\x7f]/', $payload)) {
			return false;
		}

		$payload_parts = parse_url($payload);
		$site_parts = parse_url($site_url);
		if (!is_array($payload_parts) || !is_array($site_parts)) {
			return false;
		}

		$scheme = strtolower((string) ($payload_parts['scheme'] ?? ''));
		$site_scheme = strtolower((string) ($site_parts['scheme'] ?? ''));
		$host = strtolower((string) ($payload_parts['host'] ?? ''));
		$site_host = strtolower((string) ($site_parts['host'] ?? ''));
		if (!in_array($scheme, array('http', 'https'), true) || $scheme !== $site_scheme || $host === '' || $host !== $site_host) {
			return false;
		}
		if (isset($payload_parts['user']) || isset($payload_parts['pass']) || isset($payload_parts['query']) || isset($payload_parts['fragment'])) {
			return false;
		}

		$default_port = $scheme === 'https' ? 443 : 80;
		$payload_port = isset($payload_parts['port']) ? (int) $payload_parts['port'] : $default_port;
		$site_port = isset($site_parts['port']) ? (int) $site_parts['port'] : $default_port;
		if ($payload_port !== $site_port) {
			return false;
		}

		$site_path = '/' . trim((string) ($site_parts['path'] ?? ''), '/');
		if ($site_path === '/') {
			$site_path = '';
		}
		$claim_prefix = $site_path . '/pass/claim/';
		$path = (string) ($payload_parts['path'] ?? '');
		if (!str_starts_with($path, $claim_prefix)) {
			return false;
		}

		$token = substr($path, strlen($claim_prefix));
		return preg_match('/\A[a-z0-9]{24,64}\.[a-f0-9]{24}\/?\z/', $token) === 1;
	}

	private static function render_data_uri(string $payload): string
	{
		if (!function_exists('gzcompress')) {
			return '';
		}
		try {
			$encoder = new self($payload);
			$code = $encoder->qr_encode($payload, 1);
			$size = $code['s'][0];
			$scale = 6;
			$quiet = 4;
			$width = ($size + 2 * $quiet) * $scale;
			$white = str_repeat("\xff", $width);
			$pixels = str_repeat("\0" . $white, $quiet * $scale);
			foreach ($code['b'] as $row) {
				$line = str_repeat("\xff", $quiet * $scale);
				foreach ($row as $dark) {
					$line .= str_repeat($dark ? "\0" : "\xff", $scale);
				}
				$line .= str_repeat("\xff", $quiet * $scale);
				$pixels .= str_repeat("\0" . $line, $scale);
			}
			$pixels .= str_repeat("\0" . $white, $quiet * $scale);
			$compressed = gzcompress($pixels);
			if ($compressed === false) {
				return '';
			}
			$chunk = static function (string $type, string $bytes): string {
				return pack('N', strlen($bytes)) . $type . $bytes . pack('N', crc32($type . $bytes));
			};
			$png = "\x89PNG\r\n\x1a\n";
			$png .= $chunk('IHDR', pack('NNCCCCC', $width, $width, 8, 0, 0, 0, 0));
			$png .= $chunk('IDAT', $compressed);
			$png .= $chunk('IEND', '');
			return 'data:image/png;base64,' . base64_encode($png);
		} catch (\Throwable $error) {
			// Keep the existing link/manual lookup fallbacks; never log the payload.
			return '';
		}
	}
}
