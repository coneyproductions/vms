<?php
declare(strict_types=1);

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	vms_test_fail($message);
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	vms_test_fail(
		$message
		. "\nExpected: " . var_export($expected, true)
		. "\nActual: " . var_export($actual, true)
	);
}

function vms_test_read_file(string $path): string
{
	$contents = file_get_contents($path);
	if (!is_string($contents) || $contents === '') {
		vms_test_fail('Failed to read source file: ' . $path);
	}

	return $contents;
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name . '(';
	$start = strpos($source, $needle);
	if ($start === false) {
		vms_test_fail('Unable to locate function ' . $name . '.');
	}

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		vms_test_fail('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	$in_single_quote = false;
	$in_double_quote = false;
	$in_line_comment = false;
	$in_block_comment = false;

	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		$next_char = ($i + 1 < $length) ? $source[$i + 1] : '';
		$prev_char = ($i > 0) ? $source[$i - 1] : '';

		if ($in_line_comment) {
			if ($char === "\n") {
				$in_line_comment = false;
			}
			continue;
		}
		if ($in_block_comment) {
			if ($char === '*' && $next_char === '/') {
				$in_block_comment = false;
				$i++;
			}
			continue;
		}
		if ($in_single_quote) {
			if ($char === "'" && $prev_char !== '\\') {
				$in_single_quote = false;
			}
			continue;
		}
		if ($in_double_quote) {
			if ($char === '"' && $prev_char !== '\\') {
				$in_double_quote = false;
			}
			continue;
		}

		if ($char === '/' && $next_char === '/') {
			$in_line_comment = true;
			$i++;
			continue;
		}
		if ($char === '/' && $next_char === '*') {
			$in_block_comment = true;
			$i++;
			continue;
		}
		if ($char === "'") {
			$in_single_quote = true;
			continue;
		}
		if ($char === '"') {
			$in_double_quote = true;
			continue;
		}

		if ($char === '{') {
			$depth++;
			continue;
		}
		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	vms_test_fail('Unable to locate closing brace for ' . $name . '.');
}

function __(string $text): string
{
	return $text;
}

function wp_trigger_error(string $function_name, string $message, int $error_level): void
{
	unset($function_name);
	trigger_error($message, $error_level);
}

function wp_strip_all_tags($text, bool $remove_breaks = false): string
{
	if (is_null($text)) {
		return '';
	}

	if (!is_scalar($text)) {
		wp_trigger_error(
			'',
			sprintf(
				'Warning: %1$s expects parameter %2$s (%3$s) to be a %4$s, %5$s given.',
				__FUNCTION__,
				'#1',
				'$text',
				'string',
				gettype($text)
			),
			E_USER_WARNING
		);

		return '';
	}

	$text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text);
	$text = strip_tags((string) $text);

	if ($remove_breaks) {
		$text = preg_replace('/[\r\n\t ]+/', ' ', (string) $text);
	}

	return trim((string) $text);
}

function current_user_can(string $capability): bool
{
	return $capability === 'manage_options' && !empty($GLOBALS['vms_test_manage_options']);
}

function vms_test_native_strip_tags_noise(string $value): string
{
	return trim(strip_tags((string) $value));
}

/**
 * @return array{data:array<string,mixed>,flag:mixed,ob_level_before:int,ob_level_after:int}
 */
function vms_test_run_attach_noise(string $noise, bool $owns_buffer, bool $is_admin): array
{
	$GLOBALS['vms_test_manage_options'] = $is_admin;
	$GLOBALS['vms_ajax_ob_started'] = $owns_buffer;

	$start_level = ob_get_level();
	if ($owns_buffer) {
		ob_start();
		echo $noise;
	}

	try {
		$data = vms_ticketing_ajax_attach_noise(array('ok' => true));
		$end_level = ob_get_level();
	} finally {
		while (ob_get_level() > $start_level) {
			ob_end_clean();
		}
	}

	return array(
		'data' => $data,
		'flag' => $GLOBALS['vms_ajax_ob_started'] ?? null,
		'ob_level_before' => $start_level,
		'ob_level_after' => $end_level,
	);
}

try {
	$plugin_root = dirname(__DIR__);
	$source_path = $plugin_root . '/includes/integrations/ticketing.php';
	$source = vms_test_read_file($source_path);
	$function_body = vms_test_extract_function($source, 'vms_ticketing_ajax_attach_noise');

	vms_test_assert_true(
		strpos($function_body, "wp_strip_all_tags((string) \$noise, false)") !== false,
		'Ticketing AJAX noise cleanup should use wp_strip_all_tags() with $remove_breaks=false.'
	);
	vms_test_assert_true(
		preg_match('/(?<!wp_)strip_tags\s*\(/', $function_body) !== 1,
		'Ticketing AJAX noise cleanup should no longer contain native strip_tags().'
	);

	eval($function_body);

	$comparison_cases = array(
		'plain_text' => 'Ticket Alpha',
		'simple_tags' => '<strong>Ticket</strong> Alpha',
		'nested_tags' => '<div><span>Ticket <em>Alpha</em></span></div>',
		'script_style' => '<script>alert(1)</script><style>body{color:red}</style><div>Shown</div>',
		'line_breaks' => "<p>Line 1</p>\n<p>Line 2</p>",
		'entities' => 'Tom &amp; Jerry <strong>Live</strong>',
		'malformed_tags' => 'broken <tag text',
		'ticket_title' => ' VIP <em>Meet &amp; Greet</em> Pass ',
	);

	foreach ($comparison_cases as $label => $value) {
		$native = vms_test_native_strip_tags_noise($value);
		$wordpress_false = wp_strip_all_tags($value, false);
		$wordpress_true = wp_strip_all_tags($value, true);

		if ($label === 'script_style') {
			vms_test_assert_same('Shown', $wordpress_false, 'Script/style content should be removed from admin-only AJAX noise.');
			vms_test_assert_same('Shown', $wordpress_true, 'Script/style content should be removed even when breaks are collapsed.');
			vms_test_assert_true($native !== $wordpress_false, 'Native strip_tags() should differ when script/style content is present.');
			continue;
		}

		if ($label === 'line_breaks') {
			vms_test_assert_same($native, $wordpress_false, 'Line-break noise should preserve the existing non-collapsed behavior.');
			vms_test_assert_same('Line 1 Line 2', $wordpress_true, 'Collapsed-break mode should not be selected for AJAX noise.');
			vms_test_assert_true($wordpress_false !== $wordpress_true, 'Choosing $remove_breaks=false should preserve line breaks.');
			continue;
		}

		vms_test_assert_same($native, $wordpress_false, $label . ' should preserve the existing plain-text cleanup result.');
	}

	$markup_result = vms_test_run_attach_noise('<div>Ticket <strong>Alpha</strong></div>', true, true);
	vms_test_assert_same('Ticket Alpha', $markup_result['data']['_vms_ajax_noise'], 'Admin AJAX noise should preserve visible text after tag removal.');
	vms_test_assert_same(false, $markup_result['flag'], 'The AJAX buffer ownership flag should reset after cleanup.');
	vms_test_assert_same($markup_result['ob_level_before'], $markup_result['ob_level_after'], 'The owned AJAX buffer should be closed before returning.');

	$script_result = vms_test_run_attach_noise('<script>alert(1)</script><style>body{color:red}</style><div>Shown</div>', true, true);
	vms_test_assert_same('Shown', $script_result['data']['_vms_ajax_noise'], 'Script/style content should not leak into admin AJAX noise.');

	$line_break_result = vms_test_run_attach_noise("<p>Line 1</p>\n<p>Line 2</p>", true, true);
	vms_test_assert_same("Line 1\nLine 2", $line_break_result['data']['_vms_ajax_noise'], 'Admin AJAX noise should preserve line breaks when visible text spans multiple lines.');

	$entity_result = vms_test_run_attach_noise('Tom &amp; Jerry <strong>Live</strong>', true, true);
	vms_test_assert_same('Tom &amp; Jerry Live', $entity_result['data']['_vms_ajax_noise'], 'HTML entities should remain encoded in admin AJAX noise.');

	$non_admin_result = vms_test_run_attach_noise('<div>Private</div>', true, false);
	vms_test_assert_true(!array_key_exists('_vms_ajax_noise', $non_admin_result['data']), 'Non-admin responses should not expose AJAX noise.');
	vms_test_assert_same(false, $non_admin_result['flag'], 'The AJAX buffer ownership flag should still reset for non-admin responses.');

	$no_buffer_result = vms_test_run_attach_noise('unused', false, true);
	vms_test_assert_true(!array_key_exists('_vms_ajax_noise', $no_buffer_result['data']), 'Responses without an owned buffer should not attach AJAX noise.');
	vms_test_assert_same(false, $no_buffer_result['flag'], 'Responses without an owned buffer should leave the ownership flag false.');

	echo "ticketing text helper remediation: PASS\n";
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
