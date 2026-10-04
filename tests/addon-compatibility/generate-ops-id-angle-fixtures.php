<?php
declare(strict_types=1);

const OPS_ID_ANGLE_PAYLOAD = 'OPS14-SYNTHETIC-PDF417-ANGLE-TOLERANCE-20261004';
const OPS_ID_FRAME_WIDTH = 1920;
const OPS_ID_FRAME_HEIGHT = 1080;
const OPS_ID_BARCODE_WIDTH = 1400;
const OPS_ID_BARCODE_HEIGHT = 350;
const OPS_ID_BARCODE_CENTER_X = 960;
const OPS_ID_BARCODE_CENTER_Y = 702;

function ops_id_angle_fail(string $message): void
{
	fwrite(STDERR, $message . "\n");
	exit(1);
}

function ops_id_angle_usage(): void
{
	fwrite(STDERR, "Usage: php generate-ops-id-angle-fixtures.php <tcpdf-pdf417.php> <empty-output-directory>\n");
	exit(2);
}

if ($argc !== 3) {
	ops_id_angle_usage();
}

$pdf417_source = realpath($argv[1]);
$output_root = $argv[2];
if (!is_string($pdf417_source) || !is_file($pdf417_source)) {
	ops_id_angle_fail('The TCPDF PDF417 implementation could not be resolved.');
}
if (!extension_loaded('gd')) {
	ops_id_angle_fail('The GD extension is required.');
}
if (file_exists($output_root)) {
	$entries = array_values(array_diff(scandir($output_root) ?: array(), array('.', '..')));
	if ($entries !== array()) {
		ops_id_angle_fail('The output directory must be empty.');
	}
} elseif (!mkdir($output_root, 0777, true) && !is_dir($output_root)) {
	ops_id_angle_fail('The output directory could not be created.');
}

require_once $pdf417_source;
if (!class_exists('PDF417')) {
	ops_id_angle_fail('The selected source did not provide the PDF417 class.');
}

$barcode = new PDF417(OPS_ID_ANGLE_PAYLOAD, -1, 5);
$barcode_array = $barcode->getBarcodeArray();
$module_rows = (int) ($barcode_array['num_rows'] ?? 0);
$module_columns = (int) ($barcode_array['num_cols'] ?? 0);
$modules = $barcode_array['bcode'] ?? array();
if ($module_rows < 1 || $module_columns < 1 || count($modules) !== $module_rows) {
	ops_id_angle_fail('The PDF417 implementation returned an invalid module matrix.');
}

$module_image = imagecreatetruecolor($module_columns, $module_rows);
$white = imagecolorallocate($module_image, 255, 255, 255);
$black = imagecolorallocate($module_image, 0, 0, 0);
imagefill($module_image, 0, 0, $white);
foreach ($modules as $y => $row) {
	foreach ($row as $x => $value) {
		if ((int) $value === 1) {
			imagesetpixel($module_image, (int) $x, (int) $y, $black);
		}
	}
}

$barcode_image = imagecreatetruecolor(OPS_ID_BARCODE_WIDTH, OPS_ID_BARCODE_HEIGHT);
$barcode_white = imagecolorallocate($barcode_image, 255, 255, 255);
imagefill($barcode_image, 0, 0, $barcode_white);
imagecopyresized(
	$barcode_image,
	$module_image,
	0,
	0,
	0,
	0,
	OPS_ID_BARCODE_WIDTH,
	OPS_ID_BARCODE_HEIGHT,
	$module_columns,
	$module_rows
);

$fixture_sets = array(
	'full' => array('x' => 0, 'y' => 0, 'width' => 1920, 'height' => 1080),
	'roi-current' => array('x' => 183, 'y' => 489, 'width' => 1553, 'height' => 425),
	'roi-expanded' => array('x' => 183, 'y' => 303, 'width' => 1553, 'height' => 797),
);
$angles = array(0, 5, -5, 10, -10, 15, -15);
$receipt = array(
	'payload_sha256' => hash('sha256', OPS_ID_ANGLE_PAYLOAD),
	'geometry' => array(
		'frame' => array(OPS_ID_FRAME_WIDTH, OPS_ID_FRAME_HEIGHT),
		'barcode' => array(OPS_ID_BARCODE_WIDTH, OPS_ID_BARCODE_HEIGHT),
		'barcode_center' => array(OPS_ID_BARCODE_CENTER_X, OPS_ID_BARCODE_CENTER_Y),
		'fixture_sets' => $fixture_sets,
	),
	'files' => array(),
);

foreach (array_keys($fixture_sets) as $fixture_set) {
	$directory = $output_root . '/' . $fixture_set;
	if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
		ops_id_angle_fail('A fixture-set directory could not be created.');
	}
}

foreach ($angles as $angle) {
	$frame = imagecreatetruecolor(OPS_ID_FRAME_WIDTH, OPS_ID_FRAME_HEIGHT);
	$frame_white = imagecolorallocate($frame, 255, 255, 255);
	imagefill($frame, 0, 0, $frame_white);
	$rotated = $angle === 0 ? $barcode_image : imagerotate($barcode_image, -$angle, $frame_white);
	if ($rotated === false) {
		ops_id_angle_fail('A rotated fixture could not be created.');
	}
	$destination_x = OPS_ID_BARCODE_CENTER_X - (int) floor(imagesx($rotated) / 2);
	$destination_y = OPS_ID_BARCODE_CENTER_Y - (int) floor(imagesy($rotated) / 2);
	imagecopy($frame, $rotated, $destination_x, $destination_y, 0, 0, imagesx($rotated), imagesy($rotated));

	$angle_label = $angle < 0 ? 'minus' . abs($angle) : 'plus' . $angle;
	foreach ($fixture_sets as $fixture_set => $rect) {
		$image = $fixture_set === 'full' ? $frame : imagecrop($frame, $rect);
		if ($image === false) {
			ops_id_angle_fail('A fixture crop could not be created.');
		}
		$relative = $fixture_set . '/pdf417-' . $angle_label . '.png';
		$path = $output_root . '/' . $relative;
		if (!imagepng($image, $path, 9)) {
			ops_id_angle_fail('A fixture PNG could not be written.');
		}
		$receipt['files'][$relative] = hash_file('sha256', $path);
	}
}

ksort($receipt['files'], SORT_STRING);
file_put_contents($output_root . '/payload.sha256', $receipt['payload_sha256'] . "\n");
file_put_contents($output_root . '/receipt.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDOUT, "Generated 21 synthetic PDF417 fixtures.\n");
fwrite(STDOUT, 'Payload SHA-256: ' . $receipt['payload_sha256'] . "\n");
