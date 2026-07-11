<?php
declare(strict_types=1);

function vms_tests_resolve_wp_load(?string $startDir = null): ?string
{
	$candidates = array();
	foreach (array('VMS_TEST_WP_LOAD', 'VMS_TEST_WORDPRESS_ROOT', 'WP_LOAD_PATH', 'WP_ROOT') as $envKey) {
		$rawValue = getenv($envKey);
		if (!is_string($rawValue) || $rawValue === '') {
			continue;
		}

		if (basename($rawValue) === 'wp-load.php') {
			$candidates[] = $rawValue;
			continue;
		}

		$candidates[] = rtrim($rawValue, '/\\') . DIRECTORY_SEPARATOR . 'wp-load.php';
	}

	$current = $startDir !== null && $startDir !== '' ? $startDir : __DIR__;
	$current = realpath($current) ?: $current;
	$current = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $current), DIRECTORY_SEPARATOR);
	while ($current !== '') {
		$candidates[] = $current . DIRECTORY_SEPARATOR . 'wp-load.php';
		$parent = dirname($current);
		if ($parent === $current) {
			break;
		}
		$current = $parent;
	}

	foreach ($candidates as $candidate) {
		$resolved = realpath($candidate);
		if ($resolved !== false && is_readable($resolved)) {
			return $resolved;
		}
		if (is_readable($candidate)) {
			return $candidate;
		}
	}

	return null;
}

function vms_tests_require_wordpress(?string $startDir = null): void
{
	if (defined('ABSPATH')) {
		return;
	}

	$wpLoad = vms_tests_resolve_wp_load($startDir);
	if ($wpLoad === null) {
		fwrite(
			STDERR,
			"Could not locate wp-load.php. Set VMS_TEST_WP_LOAD to the file path or VMS_TEST_WORDPRESS_ROOT to the WordPress root.\n"
		);
		exit(1);
	}

	$bufferLevel = ob_get_level();
	ob_start();
	$bootstrapComplete = false;
	register_shutdown_function(static function () use (&$bootstrapComplete, $bufferLevel): void {
		if ($bootstrapComplete) {
			return;
		}

		$buffer = '';
		while (ob_get_level() > $bufferLevel) {
			$chunk = ob_get_contents();
			if (is_string($chunk) && $chunk !== '') {
				$buffer .= $chunk;
			}
			ob_end_clean();
		}

		if (strpos($buffer, 'Error establishing a database connection') !== false) {
			fwrite(STDERR, "WordPress bootstrap failed: Error establishing a database connection.\n");
			exit(1);
		}

		if ($buffer !== '') {
			fwrite(STDOUT, $buffer);
		}
	});

	require_once $wpLoad;
	$bootstrapComplete = true;
	while (ob_get_level() > $bufferLevel) {
		$chunk = ob_get_clean();
		if (is_string($chunk) && $chunk !== '') {
			fwrite(STDOUT, $chunk);
		}
	}
}
