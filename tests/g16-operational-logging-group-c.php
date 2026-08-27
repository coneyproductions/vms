<?php
declare(strict_types=1);

$g16c_root = dirname(__DIR__);
$g16c_shadow = dirname($g16c_root, 2) . '/vms';
$g16c_artifact = '/tmp/wporg-datezero-g15.0zTh76/plugin-check.strict.json';

function g16c_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g16c_same($expected, $actual, string $message): void
{
	g16c_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function g16c_read(string $path): string
{
	$value = file_get_contents($path);
	g16c_assert(is_string($value) && $value !== '', 'Unable to read ' . $path);
	return $value;
}

function g16c_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	g16c_assert($start !== false && $brace !== false, 'Missing function ' . $name);
	$depth = 1;
	for ($i = $brace + 1, $length = strlen($source); $i < $length; $i++) {
		$depth += $source[$i] === '{' ? 1 : 0;
		$depth -= $source[$i] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, (int) $start, $i - (int) $start + 1);
		}
	}
	throw new RuntimeException('Unclosed function ' . $name);
}

function g16c_replace_once(string $source, string $current, string $replacement, string $message): string
{
	g16c_same(1, substr_count($source, $current), $message . ' count');
	return str_replace($current, $replacement, $source);
}

function g16c_remove_function(string $source, string $name): string
{
	$function = g16c_extract_function($source, $name);
	return g16c_replace_once($source, $function, '', 'Owned function removal failed: ' . $name);
}

function g16c_token_hash(string $source): string
{
	$normalized = '';
	foreach (token_get_all($source) as $token) {
		if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			continue;
		}
		$normalized .= is_array($token) ? $token[1] : $token;
	}
	return hash('sha256', $normalized);
}

/** @param array<int,array{current:string,historical:string}> $specs */
function g16c_reverse_owned_changes(string $source, array $specs, string $label): string
{
	foreach ($specs as $index => $spec) {
		$source = g16c_replace_once($source, $spec['current'], $spec['historical'], $label . ' reverse fragment ' . $index);
	}
	return $source;
}

function g16c_swap_function(string $source, string $name, string $current_hash, string $historical_base64): string
{
	$current = g16c_extract_function($source, $name);
	g16c_same($current_hash, hash('sha256', $current), 'Current owned function contract changed: ' . $name);
	$historical = base64_decode($historical_base64, true);
	g16c_assert(is_string($historical) && $historical !== '', 'Historical function decode failed: ' . $name);
	return g16c_replace_once($source, $current, $historical, 'Historical function swap failed: ' . $name);
}

function g16c_remove_ticket_helpers(string $source): string
{
	$start = strpos($source, 'function vms_ticket_integrity_fatal_operation(');
	$last = g16c_extract_function($source, 'vms_ticket_integrity_fatal_operational_context');
	$last_start = strpos($source, $last, (int) $start);
	g16c_assert($start !== false && $last_start !== false, 'Ticket helper projection bounds changed.');
	$block = substr($source, (int) $start, (int) $last_start - (int) $start + strlen($last));
	g16c_same('136b427e6633803250e472bc8416a419dd19f3160906b5b049dd169312c146f6', hash('sha256', $block), 'Ticket helper block changed.');
	return g16c_replace_once($source, $block . "\n\n", '', 'Ticket helper block removal failed.');
}

$g16c_paths = array(
	'settings' => 'includes/admin/settings-page.php',
	'phase' => 'includes/integrations/ticketing-phase-b.php',
	'notifications' => 'includes/core/notifications.php',
	'ticket' => 'includes/ticketing/ticket-integrity-monitor.php',
);
$g16c_sources = array('mirror' => array(), 'shadow' => array());
foreach ($g16c_paths as $key => $relative) {
	$g16c_sources['mirror'][$key] = g16c_read($g16c_root . '/' . $relative);
	$g16c_sources['shadow'][$key] = g16c_read($g16c_shadow . '/' . $relative);
}

g16c_same('e0acd72b19d164c92958a99d9d1c58361fc90a8fcd1a0bf2c8d6f07b1ef9ef5a', hash_file('sha256', $g16c_artifact), 'Artifact SHA-256 changed.');
$g16c_findings = json_decode(g16c_read($g16c_artifact), true, 512, JSON_THROW_ON_ERROR);
g16c_same(167, count($g16c_findings), 'Artifact total changed.');
$g16c_types = array_count_values(array_column($g16c_findings, 'type'));
ksort($g16c_types);
g16c_same(array('ERROR' => 125, 'WARNING' => 42), $g16c_types, 'Artifact severity inventory changed.');

$g16c_logging_code = 'WordPress.PHP.DevelopmentFunctions.error_log_error_log';
$g16c_expected_owned = array(
	'includes/admin/settings-page.php:272:3:WARNING:' . $g16c_logging_code,
	'includes/core/notifications.php:381:4:WARNING:' . $g16c_logging_code,
	'includes/integrations/ticketing-phase-b.php:4711:5:WARNING:' . $g16c_logging_code,
	'includes/ticketing/ticket-integrity-monitor.php:482:4:WARNING:' . $g16c_logging_code,
);
$g16c_expected_neighbors = array(
	'includes/admin/settings-page.php:1788:8:ERROR:WordPress.Security.EscapeOutput.OutputNotEscaped',
	'includes/admin/settings-page.php:1982:10:ERROR:WordPress.Security.EscapeOutput.OutputNotEscaped',
	'includes/integrations/ticketing-phase-b.php:9764:14:ERROR:WordPress.Security.EscapeOutput.OutputNotEscaped',
);
$g16c_owned = array();
$g16c_neighbors = array();
$g16c_logging_total = 0;
foreach ($g16c_findings as $row) {
	$code = (string) ($row['code'] ?? '');
	$g16c_logging_total += str_starts_with($code, 'WordPress.PHP.DevelopmentFunctions.error_log_') ? 1 : 0;
	foreach ($g16c_paths as $relative) {
		if (!str_ends_with((string) ($row['file'] ?? ''), $relative)) {
			continue;
		}
		$signature = $relative . ':' . (int) ($row['line'] ?? 0) . ':' . (int) ($row['column'] ?? 0) . ':' . ($row['type'] ?? '') . ':' . $code;
		if ($code === $g16c_logging_code) {
			$g16c_owned[] = $signature;
		} elseif ($code === 'WordPress.Security.EscapeOutput.OutputNotEscaped') {
			$g16c_neighbors[] = $signature;
		}
	}
}
sort($g16c_expected_owned);
sort($g16c_expected_neighbors);
sort($g16c_owned);
sort($g16c_neighbors);
g16c_same(42, $g16c_logging_total, 'Authoritative logging total changed.');
g16c_same($g16c_expected_owned, $g16c_owned, 'Owned artifact rows changed.');
g16c_same($g16c_expected_neighbors, $g16c_neighbors, 'Accepted Output neighbors changed.');
g16c_same(38, $g16c_logging_total - count($g16c_owned), 'Exactly 38 logging findings must remain outside group C.');

foreach (array('mirror', 'shadow') as $tree) {
	$combined = implode("\n", $g16c_sources[$tree]);
	g16c_same(2, preg_match_all('/(?<![A-Za-z0-9_])error_log\s*\(/', $combined), $tree . ' must retain exactly two direct last-resort calls.');
	g16c_same(2, preg_match_all('/phpcs:ignore WordPress\.PHP\.DevelopmentFunctions\.error_log_error_log -- [^\n]+/', $combined), $tree . ' must have exactly two line-local logging suppressions.');
	g16c_same(0, preg_match_all('/phpcs:(?:disable|ignoreFile)[^\n]*DevelopmentFunctions|phpcs:ignore WordPress\.PHP\.DevelopmentFunctions(?:\s|$)|phpcs:ignore WordPress\.PHP\.DevelopmentFunctions\.error_log_error_log\s*,/i', $combined), $tree . ' must not add a broad logging suppression.');
	g16c_same(0, substr_count($g16c_sources[$tree]['settings'], 'error_log('), $tree . ' settings must project its owned row to zero.');
	g16c_same(0, substr_count($g16c_sources[$tree]['phase'], 'error_log('), $tree . ' PhaseB must project its owned row to zero.');
	g16c_same(1, substr_count($g16c_sources[$tree]['notifications'], 'error_log('), $tree . ' notification must retain one suppressed fallback.');
	g16c_same(1, substr_count($g16c_sources[$tree]['ticket'], 'error_log('), $tree . ' Ticket fatal must retain one suppressed fallback.');
}

g16c_same($g16c_sources['mirror']['notifications'], $g16c_sources['shadow']['notifications'], 'Notification file must retain full parity.');
foreach (array('vms_entitlements_sync_image_log', 'vms_entitlements_sync_product_image_with_result', 'vms_entitlements_sync_plan_image_changes') as $name) {
	g16c_same(g16c_extract_function($g16c_sources['mirror']['phase'], $name), g16c_extract_function($g16c_sources['shadow']['phase'], $name), 'PhaseB owned parity failed: ' . $name);
}
foreach (array('vms_ticket_integrity_fatal_operation', 'vms_ticket_integrity_fatal_source_scope', 'vms_ticket_integrity_fatal_operational_context', 'vms_ticket_integrity_fatal_guard_shutdown') as $name) {
	g16c_same(g16c_extract_function($g16c_sources['mirror']['ticket'], $name), g16c_extract_function($g16c_sources['shadow']['ticket'], $name), 'Ticket owned parity failed: ' . $name);
}
g16c_same(g16c_extract_function($g16c_sources['mirror']['settings'], 'vms_handle_sync_entitlement_images'), g16c_extract_function($g16c_sources['shadow']['settings'], 'vms_handle_sync_entitlement_images'), 'Settings owned handler parity failed.');

$g16c_phase = $g16c_sources['mirror']['phase'];
g16c_same(6, substr_count($g16c_phase, 'vms_entitlements_sync_image_log('), 'PhaseB must contain the wrapper definition and five internal producers.');
foreach (array(
	'entitlement_image_sync_legacy',
	'entitlement_image_sync_product_failed',
	'entitlement_image_sync_product_save_failed',
	'entitlement_image_sync_product_completed',
	'entitlement_image_sync_product_result',
	'entitlement_image_sync_plan_skipped',
) as $event_code) {
	g16c_same(1, substr_count($g16c_phase, "'{$event_code}'"), 'PhaseB event-code count changed: ' . $event_code);
}
g16c_same(1, preg_match('/\x27entitlement_image_sync_product_save_failed\x27.{0,700}\$e\s*\n\s*\);/s', $g16c_phase), 'Caught Throwable must travel only through the adapter error argument.');
g16c_same(0, substr_count(g16c_extract_function($g16c_phase, 'vms_entitlements_sync_image_log'), "'message'"), 'PhaseB wrapper must never put a raw message in context.');

$g16c_settings_handler = g16c_extract_function($g16c_sources['mirror']['settings'], 'vms_handle_sync_entitlement_images');
g16c_assert(strpos($g16c_settings_handler, "vms_entitlements_sync_image_log('entitlement_image_sync_backfill_completed'") !== false, 'Settings must prefer the PhaseB wrapper.');
g16c_assert(strpos($g16c_settings_handler, "bvmgr_record_operational_issue('entitlement_image_sync_backfill_completed'") !== false, 'Settings must fall back to the foundation adapter.');
g16c_assert(strpos($g16c_settings_handler, "'count' => (int) \$summary['errors']") !== false, 'Settings must retain only the bounded error count.');
g16c_same(0, substr_count($g16c_settings_handler, 'error_log('), 'Settings must not retain a direct fallback.');
$g16c_transient = strpos($g16c_settings_handler, "set_transient('vms_entitlement_image_sync_last'");
$g16c_record = strpos($g16c_settings_handler, "'entitlement_image_sync_backfill_completed'");
$g16c_redirect = strpos($g16c_settings_handler, 'wp_safe_redirect(');
g16c_assert($g16c_transient !== false && $g16c_record !== false && $g16c_redirect !== false && $g16c_transient < $g16c_record && $g16c_record < $g16c_redirect, 'Settings must preserve transient -> record -> redirect order.');

$g16c_notify = g16c_extract_function($g16c_sources['mirror']['notifications'], 'vms_notify_insert_log');
g16c_same(1, substr_count($g16c_notify, 'bvmgr_record_operational_issue('), 'Notification failure must try the adapter exactly once.');
g16c_same(2, substr_count($g16c_notify, 'notification_log_insert_failed'), 'Notification fixed event must appear only in adapter and fallback payloads.');
g16c_assert(strpos($g16c_notify, "if (!\$recorded && function_exists('error_log'))") !== false, 'Notification fallback must require adapter false and an available direct logger.');
g16c_same(0, substr_count($g16c_notify, 'last_error'), 'Notification fallback must not retain database errors.');
g16c_same(0, substr_count($g16c_notify, 'wp_json_encode($entry)'), 'Notification fallback must not retain entry fields.');

$g16c_shutdown = g16c_extract_function($g16c_sources['mirror']['ticket'], 'vms_ticket_integrity_fatal_guard_shutdown');
$g16c_direct = strpos($g16c_shutdown, "'[BVM operational] event=ticket_integrity_fatal_shutdown");
$g16c_state = strpos($g16c_shutdown, 'vms_ticket_integrity_patch_daily_report_state(');
$g16c_option = strpos($g16c_shutdown, 'vms_ticket_integrity_log_event(');
$g16c_final = strpos($g16c_shutdown, "['finalized'] = true");
g16c_assert($g16c_direct !== false && $g16c_state !== false && $g16c_option !== false && $g16c_final !== false && $g16c_direct < $g16c_state && $g16c_state < $g16c_option && $g16c_option < $g16c_final, 'Ticket fatal order must remain direct -> state -> option -> finalized.');
g16c_assert(strpos($g16c_shutdown, "if (function_exists('error_log'))") !== false, 'Ticket fatal fallback must remain safe when error_log is disabled.');
g16c_same(0, substr_count($g16c_shutdown, 'bvmgr_record_operational_issue('), 'Ticket option log must remain the sole structured sink.');
foreach (array('fatal_message', 'fatal_file', 'context=%', 'message=%', 'file=%') as $forbidden) {
	g16c_same(0, substr_count($g16c_shutdown, $forbidden), 'Ticket direct boundary leaked forbidden field: ' . $forbidden);
}

$g16c_reverse_specs = array();
$g16c_reverse_specs['settings'] = array(array(
	'current' => <<<'PHP'
	$operational_context = array(
		'service' => 'ticketing',
		'operation' => 'sync_image_backfill',
		'stage' => 'complete',
		'status' => ((int) $summary['errors'] > 0) ? 'completed_with_errors' : 'completed',
		'count' => (int) $summary['errors'],
	);
	if (function_exists('vms_entitlements_sync_image_log')) {
		vms_entitlements_sync_image_log('entitlement_image_sync_backfill_completed', $operational_context);
	} elseif (function_exists('vms_record_operational_issue')) {
		vms_record_operational_issue('entitlement_image_sync_backfill_completed', $operational_context);
	}
PHP,
	'historical' => <<<'PHP'
	$summary_msg = sprintf(
		'Backfill complete: checked=%d updated=%d skipped=%d errors=%d',
		(int) $summary['checked'],
		(int) $summary['updated'],
		(int) $summary['skipped'],
		(int) $summary['errors']
	);
	if (function_exists('vms_entitlements_sync_image_log')) {
		vms_entitlements_sync_image_log($summary_msg);
	} else {
		error_log('[VMS Ticket Product Image Sync] ' . $summary_msg);
	}
PHP,
));
$g16c_reverse_specs['notifications'] = array(array(
	'current' => <<<'PHP'
		if ($ok !== 1) {
			$event_key = substr(sanitize_key((string) ($entry['event_key'] ?? 'unknown')), 0, 80);
			$recorded = function_exists('vms_record_operational_issue') && vms_record_operational_issue(
				'notification_log_insert_failed',
				array(
					'service' => 'notifications',
					'operation' => 'insert_log',
					'status' => 'failed',
					'event_key' => $event_key,
				)
			);
			if (!$recorded && function_exists('error_log')) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Preserve one minimal fallback when both the notification-table insert and the bounded option-backed operational adapter are unavailable; payload is limited to a fixed event and sanitized bounded event key.
				error_log('[BVM operational] event=notification_log_insert_failed event_key=' . $event_key);
			}
		}
PHP,
	'historical' => <<<'PHP'
		if ($ok !== 1) {
			error_log('[VMS Notify] Failed to insert notification log row for event_key=' . sanitize_key((string) ($entry['event_key'] ?? 'unknown')));
		}
PHP,
));
$g16c_reverse_specs['phase'] = array(
	array(
		'current' => <<<'PHP'
function vms_entitlements_sync_image_log(string $event_code, array $context = array(), $error = null): void {
    if (!function_exists('vms_record_operational_issue')) {
        return;
    }

    if (func_num_args() === 1) {
        vms_record_operational_issue(
            'entitlement_image_sync_legacy',
            array(
                'service' => 'ticketing',
                'operation' => 'sync_image',
                'status' => 'legacy',
            ),
            $event_code
        );
        return;
    }

    vms_record_operational_issue($event_code, $context, $error);
}
PHP,
		'historical' => <<<'PHP'
function vms_entitlements_sync_image_log(string $message): void {
    error_log('[VMS Entitlement Image Sync] ' . $message);
}
PHP,
	),
	array(
		'current' => <<<'PHP'
        vms_entitlements_sync_image_log(
            'entitlement_image_sync_product_failed',
            array(
                'service' => 'ticketing',
                'operation' => 'sync_image',
                'stage' => 'validate_product',
                'status' => $result['status'],
                'product_id' => $product_id,
            )
        );
PHP,
		'historical' => <<<'PHP'
        vms_entitlements_sync_image_log(
            sprintf('status=%s product_id=%d entitlement_id=%s', $result['status'], $product_id, $entitlement_id)
        );
PHP,
	),
	array(
		'current' => <<<'PHP'
                vms_entitlements_sync_image_log(
                    'entitlement_image_sync_product_save_failed',
                    array(
                        'service' => 'ticketing',
                        'operation' => 'sync_image',
                        'stage' => 'product_save',
                        'status' => 'warning_wc_save_failed',
                        'product_id' => $product_id,
                        'plan_id' => absint($result['plan_id']),
                        'post_id' => $img_id,
                    ),
                    $e
                );
PHP,
		'historical' => <<<'PHP'
                vms_entitlements_sync_image_log(
                    sprintf(
                        'status=warning_wc_save_failed product_id=%d entitlement_id=%s image_id=%d detail=%s',
                        $product_id,
                        $entitlement_id,
                        $img_id,
                        $e->getMessage()
                    )
                );
PHP,
	),
	array(
		'current' => <<<'PHP'
        vms_entitlements_sync_image_log(
            'entitlement_image_sync_product_completed',
            array(
                'service' => 'ticketing',
                'operation' => 'sync_image',
                'stage' => 'apply_image',
                'status' => $result['status'],
                'product_id' => $product_id,
                'plan_id' => absint($result['plan_id']),
                'post_id' => $img_id,
            )
        );
PHP,
		'historical' => <<<'PHP'
        vms_entitlements_sync_image_log(
            sprintf(
                'status=%s product_id=%d entitlement_id=%s plan_id=%d image_id=%d',
                $result['status'],
                $product_id,
                $entitlement_id,
                absint($result['plan_id']),
                $img_id
            )
        );
PHP,
	),
	array(
		'current' => <<<'PHP'
    vms_entitlements_sync_image_log(
        'entitlement_image_sync_product_result',
        array(
            'service' => 'ticketing',
            'operation' => 'sync_image',
            'stage' => 'resolve_image',
            'status' => (string) $result['status'],
            'product_id' => $product_id,
            'plan_id' => absint($result['plan_id']),
            'post_id' => $img_id,
        )
    );
PHP,
		'historical' => <<<'PHP'
    vms_entitlements_sync_image_log(
        sprintf(
            'status=%s product_id=%d entitlement_id=%s plan_id=%d image_id=%d detail=%s',
            (string) $result['status'],
            $product_id,
            $entitlement_id,
            absint($result['plan_id']),
            $img_id,
            (string) $result['message']
        )
    );
PHP,
	),
	array(
		'current' => <<<'PHP'
            vms_entitlements_sync_image_log(
                'entitlement_image_sync_plan_skipped',
                array(
                    'service' => 'ticketing',
                    'operation' => 'sync_image',
                    'stage' => 'resolve_product',
                    'status' => $res['status'],
                    'plan_id' => $plan_id,
                )
            );
PHP,
		'historical' => <<<'PHP'
            vms_entitlements_sync_image_log(
                sprintf(
                    'status=%s product_id=%d entitlement_id=%s plan_id=%d detail=%s',
                    $res['status'],
                    0,
                    $entitlement_id,
                    $plan_id,
                    $res['message']
                )
            );
PHP,
	),
);

$g16c_ticket_shutdown_historical = 'ZnVuY3Rpb24gdm1zX3RpY2tldF9pbnRlZ3JpdHlfZmF0YWxfZ3VhcmRfc2h1dGRvd24oKTogdm9pZAp7CgkkZ3VhcmRzID0gJEdMT0JBTFNbJ3Ztc190aWNrZXRfaW50ZWdyaXR5X2ZhdGFsX2d1YXJkcyddID8/IGFycmF5KCk7CglpZiAoIWlzX2FycmF5KCRndWFyZHMpIHx8IGVtcHR5KCRndWFyZHMpKSB7CgkJcmV0dXJuOwoJfQoKCSRlcnJvciA9IGVycm9yX2dldF9sYXN0KCk7CglpZiAoIXZtc190aWNrZXRfaW50ZWdyaXR5X2lzX2ZhdGFsX2Vycm9yKCRlcnJvcikpIHsKCQlyZXR1cm47Cgl9CgoJdW5zZXQoJEdMT0JBTFNbJ3Ztc190aWNrZXRfaW50ZWdyaXR5X2ZhdGFsX2d1YXJkX3Jlc2VydmUnXSk7CgoJJGlzX21lbW9yeV9mYXRhbCA9IHZtc190aWNrZXRfaW50ZWdyaXR5X2lzX21lbW9yeV9mYXRhbCgkZXJyb3IpOwoJJGZhdGFsX21lc3NhZ2UgPSB0cmltKChzdHJpbmcpICgkZXJyb3JbJ21lc3NhZ2UnXSA/PyAnJykpOwoJJGZhdGFsX2ZpbGUgPSB0cmltKChzdHJpbmcpICgkZXJyb3JbJ2ZpbGUnXSA/PyAnJykpOwoJaWYgKCRmYXRhbF9maWxlICE9PSAnJyAmJiBkZWZpbmVkKCdBQlNQQVRIJykpIHsKCQkkZmF0YWxfZmlsZSA9IHN0cl9yZXBsYWNlKEFCU1BBVEgsICcnLCAkZmF0YWxfZmlsZSk7Cgl9CgkkcGVha19tZW1vcnlfbWIgPSBmdW5jdGlvbl9leGlzdHMoJ21lbW9yeV9nZXRfcGVha191c2FnZScpCgkJPyByb3VuZCgoKGludCkgbWVtb3J5X2dldF9wZWFrX3VzYWdlKHRydWUpKSAvIDEwNDg1NzYsIDEpCgkJOiAwLjA7CgoJZm9yZWFjaCAoJGd1YXJkcyBhcyAkZ3VhcmRfaWQgPT4gJGd1YXJkKSB7CgkJaWYgKCFpc19hcnJheSgkZ3VhcmQpIHx8ICFlbXB0eSgkZ3VhcmRbJ2ZpbmFsaXplZCddKSkgewoJCQljb250aW51ZTsKCQl9CgoJCSRvcGVyYXRpb24gPSBzYW5pdGl6ZV9rZXkoKHN0cmluZykgKCRndWFyZFsnb3BlcmF0aW9uJ10gPz8gJ3Vua25vd24nKSk7CgkJJGNvbnRleHQgPSBpc19hcnJheSgkZ3VhcmRbJ2NvbnRleHQnXSA/PyBudWxsKSA/ICRndWFyZFsnY29udGV4dCddIDogYXJyYXkoKTsKCQkkY29udGV4dFsnZmF0YWxfdHlwZSddID0gKGludCkgKCRlcnJvclsndHlwZSddID8/IDApOwoJCSRjb250ZXh0WydmYXRhbF9tZXNzYWdlJ10gPSAkZmF0YWxfbWVzc2FnZTsKCQkkY29udGV4dFsnZmF0YWxfZmlsZSddID0gJGZhdGFsX2ZpbGU7CgkJJGNvbnRleHRbJ2ZhdGFsX2xpbmUnXSA9IChpbnQpICgkZXJyb3JbJ2xpbmUnXSA/PyAwKTsKCQkkY29udGV4dFsncGVha19tZW1vcnlfbWInXSA9ICRwZWFrX21lbW9yeV9tYjsKCQkkY29udGV4dFsnbWVtb3J5X2V4aGF1c3RlZCddID0gJGlzX21lbW9yeV9mYXRhbCA/IDEgOiAwOwoKCQkkZXZlbnRfdHlwZSA9ICdzY2FuX2ZhaWxlZCc7CgkJJG1lc3NhZ2UgPSBfXygnVGlja2V0IGludGVncml0eSBzY2FuIGhpdCBhIGZhdGFsIGVycm9yLicsICdiYWNrc3RhZ2UtdmVudWUtbWFuYWdlcicpOwoJCWlmICgkb3BlcmF0aW9uID09PSAnc2NhbicpIHsKCQkJJGV2ZW50X3R5cGUgPSAkaXNfbWVtb3J5X2ZhdGFsID8gJ3NjYW5fZmFpbGVkX21lbW9yeScgOiAnc2Nhbl9mYWlsZWQnOwoJCQkkbWVzc2FnZSA9ICRpc19tZW1vcnlfZmF0YWwKCQkJCT8gX18oJ1RpY2tldCBpbnRlZ3JpdHkgc2NhbiBleGhhdXN0ZWQgUEhQIG1lbW9yeS4nLCAnYmFja3N0YWdlLXZlbnVlLW1hbmFnZXInKQoJCQkJOiBfXygnVGlja2V0IGludGVncml0eSBzY2FuIGhpdCBhIGZhdGFsIGVycm9yLicsICdiYWNrc3RhZ2UtdmVudWUtbWFuYWdlcicpOwoJCX0gZWxzZWlmICgkb3BlcmF0aW9uID09PSAnZGFpbHlfcmVwb3J0JykgewoJCQkkZXZlbnRfdHlwZSA9ICdkYWlseV9yZXBvcnRfZmFpbGVkJzsKCQkJJG1lc3NhZ2UgPSAkaXNfbWVtb3J5X2ZhdGFsCgkJCQk/IF9fKCdTdGF0ZSBvZiB0aGUgUmFuZ2UgZmFpbGVkIGR1cmluZyBhIFBIUCBtZW1vcnkgZXhoYXVzdGlvbi4nLCAnYmFja3N0YWdlLXZlbnVlLW1hbmFnZXInKQoJCQkJOiBfXygnU3RhdGUgb2YgdGhlIFJhbmdlIGhpdCBhIGZhdGFsIGVycm9yIGJlZm9yZSBzZW5kLicsICdiYWNrc3RhZ2UtdmVudWUtbWFuYWdlcicpOwoJCX0KCgkJaWYgKGZ1bmN0aW9uX2V4aXN0cygnZXJyb3JfbG9nJykpIHsKCQkJJGVuY29kZWRfY29udGV4dCA9IGZ1bmN0aW9uX2V4aXN0cygnd3BfanNvbl9lbmNvZGUnKSA/IHdwX2pzb25fZW5jb2RlKCRjb250ZXh0KSA6IGpzb25fZW5jb2RlKCRjb250ZXh0KTsKCQkJZXJyb3JfbG9nKAoJCQkJc3ByaW50ZigKCQkJCQknW1ZNUyBUSUNLRVQgSU5URUdSSVRZIEZBVEFMXSBvcGVyYXRpb249JTEkcyBtZW1vcnlfZXhoYXVzdGVkPSUyJGQgdHlwZT0lMyRkIGZpbGU9JTQkcyBsaW5lPSU1JGQgbWVzc2FnZT0lNiRzIGNvbnRleHQ9JTckcycsCgkJCQkJJG9wZXJhdGlvbiwKCQkJCQkkaXNfbWVtb3J5X2ZhdGFsID8gMSA6IDAsCgkJCQkJKGludCkgKCRlcnJvclsndHlwZSddID8/IDApLAoJCQkJCSRmYXRhbF9maWxlLAoJCQkJCShpbnQpICgkZXJyb3JbJ2xpbmUnXSA/PyAwKSwKCQkJCQkkZmF0YWxfbWVzc2FnZSwKCQkJCQlpc19zdHJpbmcoJGVuY29kZWRfY29udGV4dCkgPyAkZW5jb2RlZF9jb250ZXh0IDogJycKCQkJCSkKCQkJKTsKCQl9CgoJCWlmICgkb3BlcmF0aW9uID09PSAnZGFpbHlfcmVwb3J0JyAmJiBmdW5jdGlvbl9leGlzdHMoJ3Ztc190aWNrZXRfaW50ZWdyaXR5X3BhdGNoX2RhaWx5X3JlcG9ydF9zdGF0ZScpKSB7CgkJCSRzdGF0ZV9jaGFuZ2VzID0gYXJyYXkoCgkJCQknbGFzdF9zdGF0dXMnID0+ICdmYWlsZWQnLAoJCQkJJ2xhc3RfZXJyb3InID0+ICRpc19tZW1vcnlfZmF0YWwgPyAnZmF0YWxfbWVtb3J5X2V4aGF1c3RlZCcgOiAnZmF0YWxfZXJyb3InLAoJCQkpOwoJCQlpZiAoIWVtcHR5KCRjb250ZXh0Wyd0cmlnZ2VyJ10pKSB7CgkJCQkkc3RhdGVfY2hhbmdlc1snbGFzdF90cmlnZ2VyJ10gPSBzYW5pdGl6ZV9rZXkoKHN0cmluZykgJGNvbnRleHRbJ3RyaWdnZXInXSk7CgkJCX0KCQkJaWYgKCFlbXB0eSgkY29udGV4dFsnbW9kZSddKSkgewoJCQkJJHN0YXRlX2NoYW5nZXNbJ2xhc3RfbW9kZSddID0gc2FuaXRpemVfa2V5KChzdHJpbmcpICRjb250ZXh0Wydtb2RlJ10pOwoJCQl9CgkJCWlmICghZW1wdHkoJGNvbnRleHRbJ3JlY2lwaWVudCddKSkgewoJCQkJJHN0YXRlX2NoYW5nZXNbJ2xhc3RfcmVjaXBpZW50J10gPSBzYW5pdGl6ZV9lbWFpbCgoc3RyaW5nKSAkY29udGV4dFsncmVjaXBpZW50J10pOwoJCQl9CgkJCXZtc190aWNrZXRfaW50ZWdyaXR5X3BhdGNoX2RhaWx5X3JlcG9ydF9zdGF0ZSgkc3RhdGVfY2hhbmdlcyk7CgkJfQoKCQl2bXNfdGlja2V0X2ludGVncml0eV9sb2dfZXZlbnQoJGV2ZW50X3R5cGUsICRtZXNzYWdlLCAkY29udGV4dCk7CgkJJGd1YXJkc1skZ3VhcmRfaWRdWydmaW5hbGl6ZWQnXSA9IHRydWU7Cgl9CgoJJEdMT0JBTFNbJ3Ztc190aWNrZXRfaW50ZWdyaXR5X2ZhdGFsX2d1YXJkcyddID0gJGd1YXJkczsKfQ==';

$g16c_pre_edit_hashes = array(
	'mirror' => array(
		'settings' => '63f8655693b3e34b2e64ddd60e386710f9c9e0c38c9d47da8aaa79b660e4ea9e',
		'notifications' => 'c384536b996923ec05267f298cc7d4f5d8e2b41a9a9bd384ccd70401dada3c8b',
		'phase' => '9db8bc9b9a5963d4daa1f7fcd6f4c0f863d25f74d92524460111dedcbd2b83da',
		'ticket' => '832c4cf7e2eedaf4b7c9a621d27f28149073906e198227981a1e7673fc560bed',
	),
	'shadow' => array(
		'settings' => '650c6628ff5957b8eb565668c760703610cc0c4886842a61ac2e4f94566c247b',
		'notifications' => 'c384536b996923ec05267f298cc7d4f5d8e2b41a9a9bd384ccd70401dada3c8b',
		'phase' => 'cf6794339901625bfb2afadd5978af78de664017ce6d3e42558fdf65f90c40e6',
		'ticket' => '4b86e1eda7daba85726886cdb19c6d823733e96f9dae20646bddd74b72b0c02c',
	),
);
$g16c_project_full = static function (string $source, string $key) use ($g16c_reverse_specs, $g16c_ticket_shutdown_historical): string {
	if ($key === 'ticket') {
		$source = g16c_remove_ticket_helpers($source);
		return g16c_swap_function($source, 'vms_ticket_integrity_fatal_guard_shutdown', '3080ee643e6b24b893d7d212b6ea001c5d2bc95940e45522f7064e2470e94f8f', $g16c_ticket_shutdown_historical);
	}
	return g16c_reverse_owned_changes($source, $g16c_reverse_specs[$key], $key);
};
foreach (array('mirror', 'shadow') as $tree) {
	foreach ($g16c_paths as $key => $_relative) {
		$projection = $g16c_project_full($g16c_sources[$tree][$key], $key);
		g16c_same($g16c_pre_edit_hashes[$tree][$key], hash('sha256', $projection), 'Exact pre-G16 full-source projection changed for ' . $tree . ' ' . $key);
	}
}

$g16c_mutations = array(
	'settings' => array("'operation' => 'sync_image_backfill'", "'operation' => 'sync_image_mutated'"),
	'notifications' => array("'operation' => 'insert_log'", "'operation' => 'insert_mutated'"),
	'phase' => array('entitlement_image_sync_product_failed', 'entitlement_image_sync_product_mutated'),
	'ticket' => array('event=ticket_integrity_fatal_shutdown', 'event=ticket_integrity_fatal_mutated'),
);
foreach ($g16c_mutations as $key => $mutation) {
	$mutated = g16c_replace_once($g16c_sources['mirror'][$key], $mutation[0], $mutation[1], 'Owned mutation setup failed for ' . $key);
	$rejected = false;
	try {
		$g16c_project_full($mutated, $key);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	g16c_assert($rejected, 'Owned-function mutation must invalidate exact pre-edit reconstruction: ' . $key);
}

if (!defined('BVMGR_PLUGIN_PATH')) {
	define('BVMGR_PLUGIN_PATH', $g16c_root);
}
if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		return trim((string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)), '_-');
	}
}
if (!function_exists('sanitize_email')) {
	function sanitize_email($value): string
	{
		return filter_var((string) $value, FILTER_SANITIZE_EMAIL);
	}
}
if (!function_exists('__')) {
	function __($message, $domain = null): string
	{
		return (string) $message;
	}
}

foreach (array(
	'vms_ticket_integrity_is_fatal_error',
	'vms_ticket_integrity_is_memory_fatal',
	'vms_ticket_integrity_fatal_operation',
	'vms_ticket_integrity_fatal_source_scope',
	'vms_ticket_integrity_fatal_operational_context',
) as $function) {
	eval(g16c_extract_function($g16c_sources['mirror']['ticket'], $function));
}
g16c_same('scan', vms_ticket_integrity_fatal_operation('SCAN'), 'Ticket operation scan normalization failed.');
g16c_same('daily_report', vms_ticket_integrity_fatal_operation('Daily_Report'), 'Ticket operation daily-report normalization failed.');
g16c_same('unknown', vms_ticket_integrity_fatal_operation('send_email'), 'Ticket operation allowlist failed.');
g16c_same('includes_ticketing_workerphp', vms_ticket_integrity_fatal_source_scope($g16c_root . '/includes/ticketing/worker.php'), 'Plugin-relative source token changed.');
g16c_same('external', vms_ticket_integrity_fatal_source_scope($g16c_root . '-collision/secret.php'), 'Plugin-root prefix collision must be external.');
g16c_same('external', vms_ticket_integrity_fatal_source_scope($g16c_root . '/includes/../secret.php'), 'Traversal-like source must be external.');

$g16c_sentinel = 'recipient@example.test token=TOPSECRET uri=/private/path sql=SELECT-all';
$g16c_fatal_error = array(
	'type' => E_ERROR,
	'message' => 'Allowed memory size exhausted ' . $g16c_sentinel,
	'file' => $g16c_root . '/includes/ticketing/worker.php',
	'line' => 812,
);
$g16c_contexts = vms_ticket_integrity_fatal_operational_context(
	'guard-secret',
	'Daily_Report',
	array('trigger' => 'CRON Daily', 'mode' => 'Email Now', 'recipient' => 'recipient@example.test', 'arbitrary' => $g16c_sentinel),
	$g16c_fatal_error,
	123.45
);
g16c_same(array('operation', 'memory_exhausted', 'fatal_type', 'line', 'source_scope', 'correlation'), array_keys($g16c_contexts['direct']), 'Ticket direct allowlist changed.');
g16c_same('daily_report', $g16c_contexts['direct']['operation'], 'Ticket direct operation changed.');
g16c_same(1, $g16c_contexts['direct']['memory_exhausted'], 'Ticket memory characterization failed.');
g16c_same(E_ERROR, $g16c_contexts['direct']['fatal_type'], 'Ticket fatal type changed.');
g16c_same(812, $g16c_contexts['direct']['line'], 'Ticket fatal line changed.');
g16c_assert(preg_match('/^[a-f0-9]{24}$/', $g16c_contexts['direct']['correlation']) === 1, 'Ticket correlation must be 24 lowercase hex.');
g16c_same($g16c_contexts['direct']['correlation'], $g16c_contexts['option']['correlation'], 'Ticket direct/option correlation must match.');
g16c_same('crondaily', $g16c_contexts['option']['trigger'], 'Ticket option trigger changed.');
g16c_same('emailnow', $g16c_contexts['option']['mode'], 'Ticket option mode changed.');
g16c_same(123.5, $g16c_contexts['option']['peak_memory_mb'], 'Ticket option peak-memory bound changed.');
g16c_same(0, substr_count(json_encode($g16c_contexts), $g16c_sentinel), 'Ticket operational contexts leaked sentinel data.');

$GLOBALS['g16c_order'] = array();
$GLOBALS['g16c_direct_logs'] = array();
$GLOBALS['g16c_state_patches'] = array();
$GLOBALS['g16c_option_logs'] = array();
function g16c_capture_error_log(string $message): void
{
	$GLOBALS['g16c_order'][] = 'direct';
	$GLOBALS['g16c_direct_logs'][] = $message;
}
function vms_ticket_integrity_patch_daily_report_state(array $changes): void
{
	$GLOBALS['g16c_order'][] = 'state';
	$GLOBALS['g16c_state_patches'][] = $changes;
}
function vms_ticket_integrity_log_event(string $event, string $message, array $context): void
{
	$GLOBALS['g16c_order'][] = 'option';
	$GLOBALS['g16c_option_logs'][] = array($event, $message, $context);
}

$g16c_shutdown_eval = $g16c_shutdown;
$g16c_shutdown_eval = g16c_replace_once($g16c_shutdown_eval, 'function vms_ticket_integrity_fatal_guard_shutdown(', 'function g16c_ticket_integrity_fatal_guard_shutdown(', 'Ticket runtime rename failed.');
$g16c_shutdown_eval = g16c_replace_once($g16c_shutdown_eval, 'error_get_last()', '$GLOBALS[\'g16c_fatal_error\']', 'Ticket runtime fatal injection failed.');
$g16c_shutdown_eval = g16c_replace_once($g16c_shutdown_eval, 'error_log(', 'g16c_capture_error_log(', 'Ticket runtime logger capture failed.');
eval($g16c_shutdown_eval);

$GLOBALS['g16c_fatal_error'] = $g16c_fatal_error;
$GLOBALS['bvmgr_ticket_integrity_fatal_guard_reserve'] = 'reserve';
$GLOBALS['bvmgr_ticket_integrity_fatal_guards'] = array(
	'guard-secret' => array(
		'operation' => 'daily_report',
		'context' => array(
			'trigger' => 'CRON Daily',
			'mode' => 'Email Now',
			'recipient' => 'recipient@example.test',
			'arbitrary' => $g16c_sentinel,
		),
		'finalized' => false,
	),
);
g16c_ticket_integrity_fatal_guard_shutdown();
g16c_same(array('direct', 'state', 'option'), $GLOBALS['g16c_order'], 'Ticket fatal runtime order changed.');
g16c_same(true, $GLOBALS['bvmgr_ticket_integrity_fatal_guards']['guard-secret']['finalized'], 'Ticket fatal guard must finalize after sinks.');
g16c_same('recipient@example.test', $GLOBALS['g16c_state_patches'][0]['last_recipient'], 'Ticket business-state recipient must be preserved.');
g16c_same('crondaily', $GLOBALS['g16c_state_patches'][0]['last_trigger'], 'Ticket business-state trigger changed.');
g16c_same('emailnow', $GLOBALS['g16c_state_patches'][0]['last_mode'], 'Ticket business-state mode changed.');
g16c_same('daily_report_failed', $GLOBALS['g16c_option_logs'][0][0], 'Ticket option event changed.');
g16c_same(0, substr_count(json_encode($GLOBALS['g16c_option_logs']), $g16c_sentinel), 'Ticket option log leaked sentinel data.');
g16c_same(0, substr_count($GLOBALS['g16c_direct_logs'][0], $g16c_sentinel), 'Ticket direct fallback leaked sentinel data.');
g16c_assert(preg_match('/^\[BVM operational\] event=ticket_integrity_fatal_shutdown operation=daily_report memory_exhausted=1 fatal_type=1 line=812 source_scope=includes_ticketing_workerphp correlation=[a-f0-9]{24}$/', $GLOBALS['g16c_direct_logs'][0]) === 1, 'Ticket direct payload allowlist changed.');

$g16c_shutdown_no_direct = g16c_replace_once($g16c_shutdown, 'function vms_ticket_integrity_fatal_guard_shutdown(', 'function g16c_ticket_integrity_shutdown_no_direct(', 'Ticket no-direct rename failed.');
$g16c_shutdown_no_direct = g16c_replace_once($g16c_shutdown_no_direct, 'error_get_last()', '$GLOBALS[\'g16c_fatal_error\']', 'Ticket no-direct fatal injection failed.');
$g16c_shutdown_no_direct = g16c_replace_once($g16c_shutdown_no_direct, "function_exists('error_log')", 'false', 'Ticket disabled logger injection failed.');
eval($g16c_shutdown_no_direct);
$GLOBALS['g16c_order'] = array();
$GLOBALS['bvmgr_ticket_integrity_fatal_guards']['guard-secret']['finalized'] = false;
g16c_ticket_integrity_shutdown_no_direct();
g16c_same(array('state', 'option'), $GLOBALS['g16c_order'], 'Disabled error_log must not interrupt state/option processing.');

$GLOBALS['g16c_adapter_result'] = true;
$GLOBALS['g16c_adapter_calls'] = array();
function bvmgr_record_operational_issue(string $event_code, array $context = array(), $error = null): bool
{
	$GLOBALS['g16c_adapter_calls'][] = array($event_code, $context, $error);
	return (bool) $GLOBALS['g16c_adapter_result'];
}
if (!function_exists('absint')) {
	function absint($value): int { return abs((int) $value); }
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string { return trim((string) $value); }
}
if (!function_exists('sanitize_textarea_field')) {
	function sanitize_textarea_field($value): string { return trim((string) $value); }
}
if (!function_exists('current_time')) {
	function current_time($type, $gmt = false): string { return '2026-08-08 12:00:00'; }
}
if (!function_exists('wp_json_encode')) {
	function wp_json_encode($value): string { return (string) json_encode($value); }
}
function vms_notify_log_table_name(): string { return 'wp_vms_notification_log'; }
function vms_notify_sanitize_template_key(string $value): string { return sanitize_key($value); }
function vms_notify_redact_payload_for_log($value): array { return array(); }

final class G16CNotificationWPDB
{
	public int $insert_result = 0;
	public array $calls = array();
	public function insert($table, $data, $format): int
	{
		$this->calls[] = array($table, $data, $format);
		return $this->insert_result;
	}
}
$g16c_notify_eval = g16c_replace_once($g16c_notify, 'function vms_notify_insert_log(', 'function g16c_notify_insert_log(', 'Notification runtime rename failed.');
$g16c_notify_eval = g16c_replace_once($g16c_notify_eval, 'error_log(', 'g16c_capture_error_log(', 'Notification runtime logger capture failed.');
eval($g16c_notify_eval);
$wpdb = new G16CNotificationWPDB();
$g16c_notify_entry = array(
	'event_key' => 'Event Key ' . str_repeat('x', 100),
	'recipient_address' => 'recipient@example.test',
	'error_message' => $g16c_sentinel,
	'payload' => array('secret' => $g16c_sentinel),
);
$GLOBALS['g16c_adapter_calls'] = array();
$GLOBALS['g16c_direct_logs'] = array();
$GLOBALS['g16c_adapter_result'] = true;
g16c_notify_insert_log($g16c_notify_entry);
g16c_same(1, count($GLOBALS['g16c_adapter_calls']), 'Notification insert failure must call adapter once.');
g16c_same('notification_log_insert_failed', $GLOBALS['g16c_adapter_calls'][0][0], 'Notification adapter event changed.');
g16c_same(80, strlen($GLOBALS['g16c_adapter_calls'][0][1]['event_key']), 'Notification event key must be bounded.');
g16c_same(array(), $GLOBALS['g16c_direct_logs'], 'Successful notification adapter must suppress direct fallback.');
$GLOBALS['g16c_adapter_calls'] = array();
$GLOBALS['g16c_direct_logs'] = array();
$GLOBALS['g16c_adapter_result'] = false;
g16c_notify_insert_log($g16c_notify_entry);
g16c_same(1, count($GLOBALS['g16c_adapter_calls']), 'False notification adapter must still be called once.');
g16c_same(1, count($GLOBALS['g16c_direct_logs']), 'False notification adapter must use one fallback.');
g16c_same(0, substr_count($GLOBALS['g16c_direct_logs'][0], $g16c_sentinel), 'Notification fallback leaked sentinel data.');
g16c_assert(preg_match('/^\[BVM operational\] event=notification_log_insert_failed event_key=[a-z0-9_-]{1,80}$/', $GLOBALS['g16c_direct_logs'][0]) === 1, 'Notification fallback payload changed.');

$g16c_phase_logger = g16c_extract_function($g16c_sources['mirror']['phase'], 'vms_entitlements_sync_image_log');
eval($g16c_phase_logger);
$GLOBALS['g16c_adapter_calls'] = array();
$GLOBALS['g16c_adapter_result'] = false;
vms_entitlements_sync_image_log('legacy detail ' . $g16c_sentinel);
g16c_same('entitlement_image_sync_legacy', $GLOBALS['g16c_adapter_calls'][0][0], 'PhaseB legacy event changed.');
g16c_same(array('service' => 'ticketing', 'operation' => 'sync_image', 'status' => 'legacy'), $GLOBALS['g16c_adapter_calls'][0][1], 'PhaseB legacy context changed.');
g16c_same('legacy detail ' . $g16c_sentinel, $GLOBALS['g16c_adapter_calls'][0][2], 'PhaseB legacy string must travel only as adapter error identity input.');
$phase_error = new RuntimeException($g16c_sentinel, 77);
vms_entitlements_sync_image_log('entitlement_image_sync_product_save_failed', array('service' => 'ticketing', 'operation' => 'sync_image', 'stage' => 'product_save', 'status' => 'warning_wc_save_failed', 'product_id' => 12, 'plan_id' => 34, 'post_id' => 56), $phase_error);
g16c_same($phase_error, $GLOBALS['g16c_adapter_calls'][1][2], 'PhaseB Throwable must travel only as adapter error identity input.');
g16c_same(array('product_id' => 12, 'plan_id' => 34, 'post_id' => 56), array_intersect_key($GLOBALS['g16c_adapter_calls'][1][1], array_flip(array('product_id', 'plan_id', 'post_id'))), 'PhaseB safe IDs changed.');

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}
function current_user_can($capability): bool { return true; }
function wp_die($message): void { throw new RuntimeException((string) $message); }
function check_admin_referer($action): void { $GLOBALS['g16c_nonce_checked'] = $action; }
function get_posts($args): array { return array(); }
function set_transient($key, $value, $expiration): bool
{
	$GLOBALS['g16c_settings_order'][] = 'transient';
	$GLOBALS['g16c_transient'] = array($key, $value, $expiration);
	return true;
}
function admin_url($path = ''): string { return 'https://example.test/wp-admin/' . ltrim((string) $path, '/'); }
function add_query_arg($args, $url): string { return $url . '?' . http_build_query($args); }
function wp_safe_redirect($url): bool
{
	$GLOBALS['g16c_settings_order'][] = 'redirect';
	$GLOBALS['g16c_redirect'] = $url;
	return true;
}
final class G16CSettingsExit extends RuntimeException {}

$g16c_settings_eval = $g16c_settings_handler;
$g16c_settings_eval = g16c_replace_once($g16c_settings_eval, 'function vms_handle_sync_entitlement_images(', 'function g16c_handle_sync_entitlement_images(', 'Settings runtime rename failed.');
$g16c_settings_eval = g16c_replace_once($g16c_settings_eval, "function_exists('vms_entitlements_sync_image_log')", 'false', 'Settings PhaseB-unavailable injection failed.');
$g16c_settings_eval = g16c_replace_once($g16c_settings_eval, 'exit;', 'throw new G16CSettingsExit();', 'Settings exit capture failed.');
eval($g16c_settings_eval);
$GLOBALS['g16c_adapter_calls'] = array();
$GLOBALS['g16c_adapter_result'] = false;
$GLOBALS['g16c_settings_order'] = array();
$GLOBALS['g16c_transient'] = null;
$GLOBALS['g16c_redirect'] = null;
try {
	g16c_handle_sync_entitlement_images();
	throw new RuntimeException('Settings handler must terminate after redirect.');
} catch (G16CSettingsExit $exception) {
	// Expected control-flow sentinel.
}
g16c_same('vms_sync_entitlement_images', $GLOBALS['g16c_nonce_checked'], 'Settings nonce contract changed.');
g16c_same(array('transient', 'redirect'), $GLOBALS['g16c_settings_order'], 'Settings adapter false must preserve transient/redirect behavior.');
g16c_same('vms_entitlement_image_sync_last', $GLOBALS['g16c_transient'][0], 'Settings transient key changed.');
g16c_same(0, $GLOBALS['g16c_transient'][1]['errors'], 'Settings empty backfill summary changed.');
g16c_assert(strpos((string) $GLOBALS['g16c_redirect'], 'vms_entitlement_image_sync_done=1') !== false, 'Settings redirect contract changed.');
g16c_same(1, count($GLOBALS['g16c_adapter_calls']), 'Settings must call foundation adapter exactly once when PhaseB wrapper is unavailable.');
g16c_same('entitlement_image_sync_backfill_completed', $GLOBALS['g16c_adapter_calls'][0][0], 'Settings adapter event changed.');
g16c_same('completed', $GLOBALS['g16c_adapter_calls'][0][1]['status'], 'Settings success status changed.');
g16c_same(0, $GLOBALS['g16c_adapter_calls'][0][1]['count'], 'Settings bounded error count changed.');

echo "G16 operational logging group C regression checks passed.\n";
