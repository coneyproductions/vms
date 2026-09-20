<?php
namespace BVMGR\Admissions;

defined('ABSPATH') || exit;

require_once __DIR__ . '/qr/QRCode.php';

/** Local, in-memory PNG rendering for the existing admission bearer payload. */
final class Local_QR extends \BVMGR\Vendor\PHPQRCode\QRCode
{
	public static function data_uri(string $payload): string
	{
		if (!preg_match('/\Avms-admission:[a-zA-Z0-9]{32,80}\z/', $payload) || !function_exists('gzcompress')) {
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
			// Keep the existing pass link/manual lookup fallback; never log the payload.
			return '';
		}
	}
}
