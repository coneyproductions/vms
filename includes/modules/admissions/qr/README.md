# Local admissions QR encoder

Source: splitbrain/php-qrcode, commit `d622f56003571caca078ec4fc57d8260179b990f`.
https://github.com/splitbrain/php-qrcode/blob/d622f56003571caca078ec4fc57d8260179b990f/src/QRCode.php

MIT license and original copyright notices are retained in QRCode.php.
The only upstream change is namespace isolation from `splitbrain\phpQRCode`
to `BVMGR\Vendor\PHPQRCode` to avoid collisions with other plugins.
No build/minification step or Composer runtime is required.

BVM uses only its QR matrix encoder through ../local-qr.php, with medium error
correction and a four-module quiet zone. The adapter validates the bounded
admission payload, creates PNG bytes in memory using zlib, and supplies inline
browser images or embedded email images. It never requests a URL or writes a
QR image to disk. No remote fallback exists.
