<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, $callback): bool
    {
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value)
    {
        return $value;
    }
}

if (!function_exists('wp_is_writable')) {
    function wp_is_writable(string $path): bool
    {
        return is_writable($path);
    }
}

if (!function_exists('wp_delete_file')) {
    function wp_delete_file(string $path): bool
    {
        return @unlink($path);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim($value);
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
        return trim((string) $value, '-');
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim($value);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $value): string
    {
        return trim($value);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $value): string
    {
        return $value;
    }
}

if (!function_exists('absint')) {
    function absint($value): int
    {
        return abs((int) $value);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string
    {
        return rtrim($value, '/\\') . '/';
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, int $decimals = 0): string
    {
        return number_format((float) $number, $decimals, '.', ',');
    }
}

if (!function_exists('get_option')) {
    function get_option(string $key, $default = false)
    {
        return array_key_exists($key, $GLOBALS['vms_test_options'])
            ? $GLOBALS['vms_test_options'][$key]
            : $default;
    }
}

if (!function_exists('wp_max_upload_size')) {
    function wp_max_upload_size(): int
    {
        return 25 * 1024 * 1024;
    }
}

if (!function_exists('wp_unique_filename')) {
    function wp_unique_filename(string $dir, string $filename): string
    {
        $dir = rtrim($dir, '/\\');
        $path = $dir . '/' . $filename;
        if (!file_exists($path)) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $suffix = 2;
        do {
            $candidate = $base . '-' . $suffix . ($extension !== '' ? '.' . $extension : '');
            $path = $dir . '/' . $candidate;
            $suffix++;
        } while (file_exists($path));

        return $candidate;
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool
    {
        return is_dir($target) || mkdir($target, 0777, true);
    }
}

if (!function_exists('_x')) {
    function _x(string $text, string $context = '', string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('_n_noop')) {
    function _n_noop(string $single, string $plural, string $domain = ''): array
    {
        return array($single, $plural);
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type)
    {
        return $type === 'mysql' ? gmdate('Y-m-d H:i:s') : time();
    }
}

class Vms_Test_Image_Editor
{
    /** @var resource|\GdImage */
    private $image;
    private int $width;
    private int $height;
    private int $quality = 82;

    public function __construct(string $path)
    {
        $binary = @file_get_contents($path);
        $resource = is_string($binary) ? @imagecreatefromstring($binary) : false;
        if ($resource === false) {
            throw new RuntimeException('Could not open source image.');
        }

        $this->image = $resource;
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    public function maybe_exif_rotate(): bool
    {
        return true;
    }

    public function get_size(): array
    {
        return array(
            'width' => $this->width,
            'height' => $this->height,
        );
    }

    public function resize(int $maxWidth, int $maxHeight, bool $crop): bool
    {
        $scale = min(1, min($maxWidth / max(1, $this->width), $maxHeight / max(1, $this->height)));
        $targetWidth = max(1, (int) round($this->width * $scale));
        $targetHeight = max(1, (int) round($this->height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            return false;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($canvas, $this->image, 0, 0, 0, 0, $targetWidth, $targetHeight, $this->width, $this->height);

        $this->image = $canvas;
        $this->width = $targetWidth;
        $this->height = $targetHeight;

        return true;
    }

    public function set_quality(int $quality): void
    {
        $this->quality = $quality;
    }

    public function save(string $target, string $mime)
    {
        if ($mime !== 'image/jpeg') {
            return new WP_Error('unsupported_mime', 'Only JPEG save is supported in this test harness.');
        }

        $saved = imagejpeg($this->image, $target, $this->quality);
        if (!$saved) {
            return new WP_Error('save_failed', 'Could not save image.');
        }

        return array(
            'path' => $target,
            'mime-type' => 'image/jpeg',
            'width' => $this->width,
            'height' => $this->height,
        );
    }

    public function __destruct()
    {
        $this->image = null;
    }
}

if (!function_exists('wp_get_image_editor')) {
    function wp_get_image_editor(string $path)
    {
        try {
            return new Vms_Test_Image_Editor($path);
        } catch (Throwable $error) {
            return new WP_Error('image_editor_failed', $error->getMessage());
        }
    }
}

$GLOBALS['vms_test_options'] = array(
    'vms_verification_upload_settings' => array(
        'max_upload_mb' => 20,
    ),
);

require_once dirname(__DIR__) . '/includes/helpers/image-normalization.php';
require_once dirname(__DIR__) . '/includes/integrations/ticketing-verifications.php';

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fail($message . ' expected [' . var_export($expected, true) . '] but got [' . var_export($actual, true) . ']');
    }
}

function createFixtureImage(string $path, string $type, int $width, int $height): void
{
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        fail('Could not create fixture canvas.');
    }

    if ($type === 'png') {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, $width, $height, $transparent);
    }

    $background = imagecolorallocate($image, 242, 244, 247);
    $accent = imagecolorallocate($image, 34, 76, 168);
    $ink = imagecolorallocate($image, 15, 24, 35);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);

    for ($y = 80; $y < $height; $y += 180) {
        imageline($image, 60, $y, $width - 60, $y, $accent);
    }
    imagefilledrectangle($image, 60, 60, min($width - 60, 780), 220, $accent);
    imagestring($image, 5, 90, 90, 'SERENADE RANGE VERIFIED TICKET PROOF', $background);
    imagestring($image, 5, 90, 280, 'Name: Test Customer', $ink);
    imagestring($image, 5, 90, 360, 'Credential: TEST-12345', $ink);
    imagestring($image, 5, 90, 440, 'Readable screenshot regression fixture', $ink);

    $saved = false;
    if ($type === 'jpg') {
        $saved = imagejpeg($image, $path, 92);
    } elseif ($type === 'png') {
        $saved = imagepng($image, $path, 3);
    } elseif ($type === 'webp') {
        if (!function_exists('imagewebp')) {
            fail('imagewebp() is unavailable for the requested fixture.');
        }
        $saved = imagewebp($image, $path, 92);
    }

    if (!$saved || !file_exists($path)) {
        fail('Could not save fixture image: ' . basename($path));
    }
}

if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg') || !function_exists('imagepng')) {
    fwrite(STDERR, "GD image functions are required for this smoke test.\n");
    exit(2);
}

$root = sys_get_temp_dir() . '/vms-proof-normalization-tests';
wp_mkdir_p($root);
assertTrue(is_dir($root), 'Smoke-test directory could not be created.');

$cleanup = array();
try {
    $jpgSource = trailingslashit($root) . 'source-large.jpg';
    createFixtureImage($jpgSource, 'jpg', 3200, 2100);
    $cleanup[] = $jpgSource;

    $jpgResult = vms_ticketing_verification_optimize_image_upload($jpgSource, $root, 'jpg-large');
    assertTrue(is_array($jpgResult), 'Large JPG did not normalize successfully.');
    $jpgPath = (string) ($jpgResult['path'] ?? '');
    $cleanup[] = $jpgPath;
    assertTrue($jpgPath !== '' && file_exists($jpgPath), 'Large JPG output file was not created.');
    assertSame('image/jpeg', (string) ($jpgResult['mime'] ?? ''), 'Large JPG output mime');
    $jpgSize = getimagesize($jpgPath);
    assertTrue(is_array($jpgSize), 'Large JPG output dimensions were unreadable.');
    assertTrue(max((int) $jpgSize[0], (int) $jpgSize[1]) <= (int) VMS_TICKETING_VERIFICATION_IMAGE_MAX_DIMENSION, 'Large JPG output was not resized to the configured long edge.');

    $pngSource = trailingslashit($root) . 'source-proof.png';
    createFixtureImage($pngSource, 'png', 2400, 1800);
    $cleanup[] = $pngSource;

    $pngResult = vms_ticketing_verification_optimize_image_upload($pngSource, $root, 'png-proof');
    assertTrue(is_array($pngResult), 'PNG proof did not normalize successfully.');
    $pngPath = (string) ($pngResult['path'] ?? '');
    $cleanup[] = $pngPath;
    assertTrue(substr($pngPath, -4) === '.jpg', 'PNG proof was not converted to JPG.');
    assertSame('image/jpeg', (string) ($pngResult['mime'] ?? ''), 'PNG output mime');

    if (function_exists('imagewebp')) {
        $webpSource = trailingslashit($root) . 'source-proof.webp';
        createFixtureImage($webpSource, 'webp', 2600, 1700);
        $cleanup[] = $webpSource;

        $webpResult = vms_ticketing_verification_optimize_image_upload($webpSource, $root, 'webp-proof');
        assertTrue(is_array($webpResult), 'WEBP proof did not normalize successfully.');
        $webpPath = (string) ($webpResult['path'] ?? '');
        $cleanup[] = $webpPath;
        assertTrue(substr($webpPath, -4) === '.jpg', 'WEBP proof was not converted to JPG.');
        assertSame('image/jpeg', (string) ($webpResult['mime'] ?? ''), 'WEBP output mime');
    }

    $tooLarge = vms_normalize_uploaded_image_to_jpeg($jpgSource, $root, 'too-large', array(
        'max_dimension' => (int) VMS_TICKETING_VERIFICATION_IMAGE_MAX_DIMENSION,
        'quality' => 92,
        'max_output_bytes' => 1024,
    ));
    assertTrue(is_wp_error($tooLarge), 'Oversized normalized output should return a WP_Error.');
    assertSame('file_too_large', $tooLarge->get_error_code(), 'Oversized output error code');

    assertSame('pdf', vms_ticketing_verification_guess_upload_kind('fixture.pdf', 'application/pdf'), 'PDF upload kind');
    assertSame('heic', vms_ticketing_verification_guess_upload_kind('fixture.heic', 'image/heic'), 'HEIC upload kind');
    assertTrue(stripos(vms_ticketing_verification_notice_message('pdf_too_large'), 'PDF') !== false, 'PDF too large notice should mention PDF.');
    assertTrue(stripos(vms_ticketing_verification_notice_message('heic_not_supported'), 'HEIC') !== false, 'HEIC notice should mention HEIC.');

    fwrite(STDOUT, "verification proof normalization smoke: PASS\n");
    exit(0);
} finally {
    foreach ($cleanup as $path) {
        if (is_string($path) && $path !== '' && file_exists($path) && is_file($path)) {
            @unlink($path);
        }
    }
}
