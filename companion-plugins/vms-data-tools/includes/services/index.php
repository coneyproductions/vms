<?php
defined('ABSPATH') || exit;

$dir = __DIR__;

$files = [
	'result-format.php',
	'csv-parse.php',
	'normalize.php',
	'validate.php',
	'match.php',
	'preview.php',
	'upsert.php',
	'meta.php',
	'contact.php',
	'commit.php',
];

foreach ($files as $f) {
	$path = $dir . '/' . $f;

	if (!file_exists($path)) {
		error_log('VMS DT missing service file: ' . $path);
		wp_die('VMS Data Tools missing required service file: ' . esc_html($path));
	}
}
