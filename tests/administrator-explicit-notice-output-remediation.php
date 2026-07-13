<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		unset($hook, $callback, $priority, $accepted_args);
		return true;
	}
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		unset($domain);
		return $text;
	}
}

if (!function_exists('_n')) {
	function _n(string $single, string $plural, int $number, string $domain = ''): string
	{
		unset($domain);
		return $number === 1 ? $single : $plural;
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $value));
		return is_string($sanitized) ? trim($sanitized) : '';
	}
}

if (!function_exists('absint')) {
	function absint($value): int
	{
		return abs((int) $value);
	}
}

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('add_query_arg')) {
	function add_query_arg($args, string $url = ''): string
	{
		$base = $url !== '' ? $url : admin_url('admin.php');
		$parts = parse_url($base);
		$query = array();

		if (!empty($parts['query'])) {
			parse_str($parts['query'], $query);
		}

		foreach ((array) $args as $key => $value) {
			$query[(string) $key] = (string) $value;
		}

		$rebuilt = '';
		if (!empty($parts['scheme'])) {
			$rebuilt .= $parts['scheme'] . '://';
		}
		if (!empty($parts['host'])) {
			$rebuilt .= $parts['host'];
		}
		if (!empty($parts['path'])) {
			$rebuilt .= $parts['path'];
		}
		if ($query !== array()) {
			$rebuilt .= '?' . http_build_query($query);
		}

		return $rebuilt;
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_html__')) {
	function esc_html__(string $text, string $domain = ''): string
	{
		return esc_html(__($text, $domain));
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url): string
	{
		return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('sanitize_html_class')) {
	function sanitize_html_class($class): string
	{
		$sanitized = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $class);
		return is_string($sanitized) ? trim($sanitized, '-') : '';
	}
}

if (!function_exists('wp_timezone')) {
	function wp_timezone(): DateTimeZone
	{
		return new DateTimeZone('UTC');
	}
}

if (!function_exists('wp_date')) {
	function wp_date(string $format, int $timestamp, ?DateTimeZone $timezone = null): string
	{
		$date = new DateTimeImmutable('@' . $timestamp);
		if ($timezone instanceof DateTimeZone) {
			$date = $date->setTimezone($timezone);
		}

		return $date->format($format);
	}
}

if (!function_exists('vms_staffing_staff_qualification_review_url')) {
	function vms_staffing_staff_qualification_review_url(int $staff_id): string
	{
		return admin_url('post.php?post=' . $staff_id . '&action=edit');
	}
}

$GLOBALS['vms_test_staff_certifications_pending_items'] = array();
$GLOBALS['vms_test_staff_certifications_provider_calls'] = 0;
$GLOBALS['vms_test_staff_certifications_provider_statuses'] = array();

if (!function_exists('vms_staffing_get_staff_qualification_review_items')) {
	/**
	 * @return array<int|string,mixed>
	 */
	function vms_staffing_get_staff_qualification_review_items(string $status): array
	{
		$GLOBALS['vms_test_staff_certifications_provider_calls']++;
		$GLOBALS['vms_test_staff_certifications_provider_statuses'][] = $status;
		return $GLOBALS['vms_test_staff_certifications_pending_items'];
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses($html, $allowed_html): string
	{
		return (string) preg_replace_callback(
			'~<(/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>~',
			static function (array $matches) use ($allowed_html): string {
				$closing = $matches[1] === '/';
				$tag = strtolower((string) $matches[2]);
				if (!array_key_exists($tag, $allowed_html)) {
					return '';
				}

				if ($closing) {
					return '</' . $tag . '>';
				}

				$attrs = '';
				$allowed_attrs = is_array($allowed_html[$tag]) ? $allowed_html[$tag] : array();
				if ($allowed_attrs !== array()) {
					preg_match_all(
						'~\s+([a-zA-Z0-9:-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~',
						(string) ($matches[3] ?? ''),
						$attr_matches,
						PREG_SET_ORDER
					);

					foreach ($attr_matches as $attr_match) {
						$name = strtolower((string) $attr_match[1]);
						if (!array_key_exists($name, $allowed_attrs)) {
							continue;
						}

						$value = '';
						if (array_key_exists(2, $attr_match) && $attr_match[2] !== '') {
							$value = (string) $attr_match[2];
						} elseif (array_key_exists(3, $attr_match) && $attr_match[3] !== '') {
							$value = (string) $attr_match[3];
						} elseif (array_key_exists(4, $attr_match) && $attr_match[4] !== '') {
							$value = (string) $attr_match[4];
						}

						if ($name === 'href' && preg_match('~^\s*(?:javascript|data|vbscript):~i', html_entity_decode($value, ENT_QUOTES, 'UTF-8'))) {
							continue;
						}

						$attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
					}
				}

				return '<' . $tag . $attrs . '>';
			},
			(string) $html
		);
	}
}

require_once dirname(__DIR__) . '/includes/admin-ui/shell.php';
require_once dirname(__DIR__) . '/includes/modules/status-notices/admin-ui.php';
require_once dirname(__DIR__) . '/includes/admin/continuity-binder.php';
require_once dirname(__DIR__) . '/includes/admin/due-dates.php';
require_once dirname(__DIR__) . '/includes/admin/square-sync-protection.php';
require_once dirname(__DIR__) . '/includes/admin/staff-certifications.php';
require_once dirname(__DIR__) . '/includes/social-share/admin.php';

$pluginRoot = dirname(__DIR__);
$shellSource = file_get_contents($pluginRoot . '/includes/admin-ui/shell.php');
$statusSource = file_get_contents($pluginRoot . '/includes/modules/status-notices/admin-ui.php');
$continuitySource = file_get_contents($pluginRoot . '/includes/admin/continuity-binder.php');
$dueDatesSource = file_get_contents($pluginRoot . '/includes/admin/due-dates.php');
$squareSyncProtectionSource = file_get_contents($pluginRoot . '/includes/admin/square-sync-protection.php');
$staffCertificationsSource = file_get_contents($pluginRoot . '/includes/admin/staff-certifications.php');
$socialSource = file_get_contents($pluginRoot . '/includes/social-share/admin.php');
$emailFollowupsSource = file_get_contents($pluginRoot . '/includes/modules/email-followups/admin-ui.php');
$eventPlanImportSource = file_get_contents($pluginRoot . '/includes/admin/data-tools/page-event-plan-import.php');
$bootstrapSource = file_get_contents($pluginRoot . '/includes/bootstrap.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($shellSource) && $shellSource !== '', 'Admin shell source should be readable.');
$assert(is_string($statusSource) && $statusSource !== '', 'Status Notices admin UI source should be readable.');
$assert(is_string($continuitySource) && $continuitySource !== '', 'Continuity Binder source should be readable.');
$assert(is_string($dueDatesSource) && $dueDatesSource !== '', 'Due Dates source should be readable.');
$assert(is_string($squareSyncProtectionSource) && $squareSyncProtectionSource !== '', 'Square Sync Protection source should be readable.');
$assert(is_string($staffCertificationsSource) && $staffCertificationsSource !== '', 'Staff Certifications source should be readable.');
$assert(is_string($socialSource) && $socialSource !== '', 'Social Sharing source should be readable.');
$assert(is_string($emailFollowupsSource) && $emailFollowupsSource !== '', 'Email Follow-Ups source should be readable.');
$assert(is_string($eventPlanImportSource) && $eventPlanImportSource !== '', 'Event Plan Import source should be readable.');
$assert(is_string($bootstrapSource) && $bootstrapSource !== '', 'Bootstrap source should be readable.');

$normalizeAllowedHtml = static function (array $allowed_html): array {
	ksort($allowed_html);
	foreach ($allowed_html as $tag => $attrs) {
		if (is_array($attrs)) {
			ksort($attrs);
			$allowed_html[$tag] = $attrs;
		}
	}

	return $allowed_html;
};

$expectedAllowed = array(
	'div' => array(
		'class' => true,
	),
	'p' => array(),
);
$expectedHeaderActionsAllowed = array(
	'a' => array(
		'class' => true,
		'href' => true,
	),
	'button' => array(
		'class' => true,
		'data-vms-tour' => true,
		'data-vms-tour-start' => true,
		'type' => true,
	),
	'div' => array(
		'class' => true,
		'data-vms-tour' => true,
	),
);

$assert(function_exists('vms_admin_ui_explicit_notice_allowed_html'), 'Explicit notice allowlist helper should be defined.');
$assert(function_exists('vms_admin_ui_header_actions_allowed_html'), 'Header actions allowlist helper should be defined.');
$assert(
	$normalizeAllowedHtml(vms_admin_ui_explicit_notice_allowed_html()) === $normalizeAllowedHtml($expectedAllowed),
	'Explicit notice allowlist should contain only div[class] and p.'
);
$assert(
	$normalizeAllowedHtml(vms_admin_ui_header_actions_allowed_html()) === $normalizeAllowedHtml($expectedHeaderActionsAllowed),
	'Header actions allowlist should contain only the discovered action elements and attributes.'
);
$assert(
	preg_match('~echo\s+wp_kses\s*\(\s*\$explicit_notices_html\s*,\s*vms_admin_ui_explicit_notice_allowed_html\s*\(\s*\)\s*\)\s*;~s', $shellSource) === 1,
	'Admin shell should apply the dedicated allowlist at the final explicit notice sink.'
);
$assert(
	preg_match('~echo\s+[\'"]<div class="vms-admin-shell__actions">[\'"]\s*\.\s*wp_kses\s*\(\s*\$actions_html\s*,\s*vms_admin_ui_header_actions_allowed_html\s*\(\s*\)\s*\)\s*\.\s*[\'"]</div>[\'"]\s*;~s', $shellSource) === 1,
	'Admin shell should apply the dedicated allowlist at the final header-actions sink.'
);
$assert(strpos($shellSource, 'echo $explicit_notices_html;') === false, 'Admin shell should not leave a raw explicit notice echo sink.');
$assert(strpos($shellSource, 'esc_html($explicit_notices_html') === false, 'Admin shell should not text-escape the explicit notice fragment.');
$assert(strpos($shellSource, 'wp_kses_post($explicit_notices_html') === false, 'Admin shell should not use wp_kses_post() for the explicit notice sink.');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $shellSource), 'Admin shell should not use the post allowlist for the explicit notice sink.');
$assert(strpos($shellSource, 'echo \'<div class="vms-admin-shell__actions">\' . $actions_html . \'</div>\';') === false, 'Admin shell should not leave a raw header-actions echo sink.');
$assert(strpos($shellSource, 'esc_html($actions_html') === false, 'Admin shell should not text-escape the header-actions fragment.');
$assert(strpos($shellSource, 'wp_kses_post($actions_html') === false, 'Admin shell should not use wp_kses_post() for the header-actions sink.');
$assert(strpos($shellSource, 'echo $captured_notices_html;') !== false, 'Captured notice sink should remain untouched.');
$assert(strpos($shellSource, 'echo $content_html;') !== false, 'Shell content sink should remain untouched.');
$assert(strpos($shellSource, 'wp_kses($actions_html, vms_admin_ui_header_actions_allowed_html())') !== false, 'Dedicated header-actions allowlist should be applied only to actions.');
$assert(strpos($shellSource, 'wp_kses($captured_notices_html') === false, 'Dedicated explicit notice allowlist should not be applied to captured notices.');
$assert(strpos($shellSource, 'wp_kses($content_html') === false, 'Dedicated explicit notice allowlist should not be applied to shell content.');
$assert(strpos($bootstrapSource, "require_once __DIR__ . '/tours/tours.php';") !== false, 'Canonical bootstrap should load the shared tours helper file.');
$assert(strpos($bootstrapSource, 'class-vms-tours.php') === false, 'Canonical bootstrap should not directly load the legacy core tours help-button file.');

$allIncludeSource = '';
$actionCallerFiles = array();
$noticesCallbackFiles = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($pluginRoot . '/includes', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
	if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
		continue;
	}

	$source = file_get_contents($file->getPathname());
	$assert(is_string($source), 'Production include source should be readable: ' . $file->getPathname());
	$allIncludeSource .= "\n" . $source;
	if (preg_match('~[\'"]actions_html[\'"]\s*=>~', $source) === 1) {
		$actionCallerFiles[] = str_replace($pluginRoot . '/includes/', '', $file->getPathname());
	}
	if (preg_match('~[\'"]notices_callback[\'"]\s*=>~', $source) === 1) {
		$noticesCallbackFiles[] = str_replace($pluginRoot . '/includes/', '', $file->getPathname());
	}
}

$noticesCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>~', $allIncludeSource, $unusedMatches);
$statusNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_status_notice_notice_bar[\'"]~', $allIncludeSource, $unusedStatusMatches);
$continuityNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_continuity_binder_render_updated_notice[\'"]~', $allIncludeSource, $unusedContinuityMatches);
$dueDatesNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_due_render_admin_notices[\'"]~', $allIncludeSource, $unusedDueMatches);
$squareSyncNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_square_sync_protection_render_admin_notice[\'"]~', $allIncludeSource, $unusedSquareMatches);
$staffCertificationsNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*function\s*\(\)\s*use\s*\(\s*\$pending\s*\)\s*:\s*void~', $staffCertificationsSource, $unusedStaffMatches);
$socialNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_social_render_notices[\'"]~', $allIncludeSource, $unusedSocialMatches);
$expectedActionCallerFiles = array(
	'admin/event-command-center.php',
	'admin/integrity-calendar-reconcile.php',
	'admin/integrity-venue-reconcile.php',
	'admin/schedule.php',
	'admin/ticket-integrity-page.php',
	'admin/vendor-availability.php',
	'admin/vendor-command-center.php',
	'modules/availability-date-dispatch/admin-ui.php',
	'modules/status-notices/admin-ui.php',
	'safety/admin.php',
);
$expectedNoticesCallbackFiles = array(
	'admin/continuity-binder.php',
	'admin/due-dates.php',
	'admin/square-sync-protection.php',
	'admin/staff-certifications.php',
	'modules/status-notices/admin-ui.php',
	'social-share/admin.php',
);
sort($actionCallerFiles);
sort($expectedActionCallerFiles);
$noticesCallbackFiles = array_values(array_unique($noticesCallbackFiles));
sort($noticesCallbackFiles);
sort($expectedNoticesCallbackFiles);
$assert($noticesCallbackCount === 7, 'Only seven production notices_callback assignments should exist.');
$assert($statusNoticeCallbackCount === 2, 'Status Notices should still contribute exactly two production notices_callback callers.');
$assert($continuityNoticeCallbackCount === 1, 'Continuity Binder should contribute exactly one production notices_callback caller.');
$assert($dueDatesNoticeCallbackCount === 1, 'Due Dates should contribute exactly one production notices_callback caller.');
$assert($squareSyncNoticeCallbackCount === 1, 'Square Sync Protection should contribute exactly one production notices_callback caller.');
$assert($staffCertificationsNoticeCallbackCount === 1, 'Staff Certifications should contribute exactly one production notices_callback caller.');
$assert($socialNoticeCallbackCount === 1, 'Social Sharing should contribute exactly one production notices_callback caller.');
$assert($actionCallerFiles === $expectedActionCallerFiles, 'Header-actions caller inventory should stay limited to the inspected production files.');
$assert($noticesCallbackFiles === $expectedNoticesCallbackFiles, 'Explicit notice callbacks should remain limited to Status Notices, Continuity Binder, Due Dates, Square Sync Protection, Staff Certifications, and Social Sharing.');
$assert(strpos($statusSource, 'function vms_status_notice_notice_bar(): void') !== false, 'Status Notices explicit fragment owner should keep a void callback signature.');
$assert(substr_count($statusSource, "'notices_callback' => 'vms_status_notice_notice_bar'") === 2, 'Status Notices list and edit screens should both supply the explicit notice callback.');
$noticeBarStart = strpos($statusSource, 'function vms_status_notice_notice_bar(): void');
$noticeBarEnd = strpos($statusSource, "if (!function_exists('vms_status_notice_render_list_screen'))");
$assert($noticeBarStart !== false && $noticeBarEnd !== false && $noticeBarEnd > $noticeBarStart, 'Status Notice callback body should be locatable.');
$noticeBarSource = substr($statusSource, (int) $noticeBarStart, (int) $noticeBarEnd - (int) $noticeBarStart);
$assert(strpos($noticeBarSource, 'apply_filters(') === false && strpos($noticeBarSource, 'do_action(') === false, 'Status Notice callback should not hand off explicit notice markup through hooks or filters.');
$assert(strpos($noticeBarSource, 'esc_html($message)') !== false, 'Status Notice callback should keep contextual escaping for notice text.');
$assert(strpos($noticeBarSource, '<div class="notice notice-success is-dismissible"><p>') !== false, 'Status Notice callback should keep the fixed explicit notice fragment shape.');

$_GET = array(
	'vms_status_notice_result' => 'saved',
);
ob_start();
vms_status_notice_notice_bar();
$savedNotice = (string) ob_get_clean();
$assert(
	$savedNotice === '<div class="notice notice-success is-dismissible"><p>Status Notice saved.</p></div>',
	'Status Notice callback should preserve the saved notice fragment.'
);
$assert(
	wp_kses($savedNotice, vms_admin_ui_explicit_notice_allowed_html()) === $savedNotice,
	'The explicit notice allowlist should admit the current saved notice fragment unchanged.'
);

$_GET = array(
	'vms_status_notice_result' => 'bulk_updated',
	'bulk_count' => '2',
);
ob_start();
vms_status_notice_notice_bar();
$bulkNotice = (string) ob_get_clean();
$assert(
	$bulkNotice === '<div class="notice notice-success is-dismissible"><p>2 notices updated.</p></div>',
	'Status Notice callback should preserve the bulk-updated notice fragment with inert dynamic text.'
);

$assert(strpos($continuitySource, 'function vms_continuity_binder_render_updated_notice(): void') !== false, 'Continuity Binder should expose a dedicated explicit notice callback.');
$assert(substr_count($continuitySource, "'notices_callback' => 'vms_continuity_binder_render_updated_notice'") === 1, 'Continuity Binder shell call should supply its explicit notice callback exactly once.');
$assert(substr_count($continuitySource, 'notice notice-success is-dismissible') === 1, 'Continuity Binder success notice should have exactly one production emission path.');
$assert(strpos($continuitySource, 'notice notice-warning') !== false, 'Continuity Binder warning notice should remain in the content callback.');
$assert(strpos($continuitySource, 'notice notice-warning"><p><strong>') !== false, 'Continuity Binder warning notice should remain a richer captured family outside the explicit contract.');
$assert(strpos($continuitySource, 'apply_filters(') === false && strpos($continuitySource, 'do_action(') === false, 'Continuity Binder notice paths should not hand off markup through hooks or filters.');
$assert(strpos($continuitySource, 'settings_errors(') === false && strpos($continuitySource, 'do_settings_sections(') === false && strpos($continuitySource, 'wp_editor(') === false, 'Continuity Binder should not route notice markup through Settings API or editor callbacks.');
$updatedNoticeStart = strpos($continuitySource, 'function vms_continuity_binder_render_updated_notice(): void');
$updatedNoticeEnd = strpos($continuitySource, 'function vms_render_continuity_binder_page()');
$assert($updatedNoticeStart !== false && $updatedNoticeEnd !== false && $updatedNoticeEnd > $updatedNoticeStart, 'Continuity Binder explicit notice callback body should be locatable.');
$updatedNoticeSource = substr($continuitySource, (int) $updatedNoticeStart, (int) $updatedNoticeEnd - (int) $updatedNoticeStart);
$contentStart = strpos($continuitySource, 'function vms_render_continuity_binder_page_content()');
$contentEnd = strpos($continuitySource, 'function vms_admin_post_save_continuity_binder()');
$assert($contentStart !== false && $contentEnd !== false && $contentEnd > $contentStart, 'Continuity Binder content callback body should be locatable.');
$continuityContentSource = substr($continuitySource, (int) $contentStart, (int) $contentEnd - (int) $contentStart);
$assert(strpos($updatedNoticeSource, '$_GET[\'updated\'] !== \'1\'') !== false, 'Continuity Binder explicit notice callback should preserve the existing exact display condition.');
$assert(strpos($updatedNoticeSource, 'esc_html__(\'Binder updated.\'') !== false, 'Continuity Binder explicit notice callback should keep contextual escaping for notice text.');
$assert(strpos($updatedNoticeSource, '<div class="notice notice-success is-dismissible"><p>') !== false, 'Continuity Binder explicit notice callback should keep the fixed simple notice fragment.');
$assert(strpos($updatedNoticeSource, '<strong>') === false && strpos($updatedNoticeSource, '<a ') === false && strpos($updatedNoticeSource, '<button') === false, 'Continuity Binder explicit notice callback should not introduce richer markup.');
$assert(strpos($continuityContentSource, 'notice notice-success is-dismissible') === false, 'Continuity Binder content callback should no longer emit the moved success notice.');
$assert(strpos($continuityContentSource, 'notice notice-warning') !== false, 'Continuity Binder content callback should still emit the remaining warning notice.');

$_GET = array(
	'updated' => '1',
);
ob_start();
vms_continuity_binder_render_updated_notice();
$continuityUpdatedNotice = (string) ob_get_clean();
$assert(
	$continuityUpdatedNotice === '<div class="notice notice-success is-dismissible"><p>Binder updated.</p></div>',
	'Continuity Binder explicit notice callback should preserve the updated notice fragment.'
);
$assert(
	wp_kses($continuityUpdatedNotice, vms_admin_ui_explicit_notice_allowed_html()) === $continuityUpdatedNotice,
	'The explicit notice allowlist should admit the Continuity Binder updated notice unchanged.'
);

$_GET = array(
	'updated' => '0',
);
ob_start();
vms_continuity_binder_render_updated_notice();
$continuityNoNotice = (string) ob_get_clean();
$assert($continuityNoNotice === '', 'Continuity Binder explicit notice callback should stay silent when the exact updated flag is absent.');

$assert(strpos($dueDatesSource, 'function vms_due_render_admin_notices(): void') !== false, 'Due Dates should expose a dedicated explicit notice callback.');
$assert(substr_count($dueDatesSource, "'notices_callback' => 'vms_due_render_admin_notices'") === 1, 'Due Dates shell call should supply its explicit notice callback exactly once.');
$assert(strpos($dueDatesSource, 'apply_filters(') === false && strpos($dueDatesSource, 'do_action(') === false, 'Due Dates notice path should not hand off markup through hooks or filters.');
$assert(strpos($dueDatesSource, 'settings_errors(') === false && strpos($dueDatesSource, 'do_settings_sections(') === false && strpos($dueDatesSource, 'wp_editor(') === false, 'Due Dates should not route notice markup through Settings API or editor callbacks.');
$dueNoticeStart = strpos($dueDatesSource, 'function vms_due_render_admin_notices(): void');
$dueNoticeEnd = strpos($dueDatesSource, 'function vms_render_due_dates_admin_page(): void');
$assert($dueNoticeStart !== false && $dueNoticeEnd !== false && $dueNoticeEnd > $dueNoticeStart, 'Due Dates explicit notice callback body should be locatable.');
$dueNoticeSource = substr($dueDatesSource, (int) $dueNoticeStart, (int) $dueNoticeEnd - (int) $dueNoticeStart);
$dueContentStart = strpos($dueDatesSource, 'function vms_render_due_dates_admin_page_content(): void');
$assert($dueContentStart !== false, 'Due Dates content callback body should be locatable.');
$dueDatesContentSource = substr($dueDatesSource, (int) $dueContentStart);
$assert(strpos($dueNoticeSource, 'sanitize_key(vms_due_admin_query_arg(\'vms_due_msg\'))') !== false, 'Due Dates explicit notice callback should preserve the existing sanitized message source.');
$assert(strpos($dueNoticeSource, 'strpos($msg, \'error\') !== false') !== false, 'Due Dates explicit notice callback should preserve the severity mapping.');
$assert(strpos($dueNoticeSource, 'esc_html(str_replace(\'_\', \' \', $msg))') !== false, 'Due Dates explicit notice callback should preserve contextual escaping and wording normalization.');
$assert(strpos($dueNoticeSource, 'notice ' . "' . (\$is_error ? 'notice-error' : 'notice-success') . ' is-dismissible") !== false, 'Due Dates explicit notice callback should keep the exact class family.');
$assert(strpos($dueNoticeSource, '<strong>') === false && strpos($dueNoticeSource, '<a ') === false && strpos($dueNoticeSource, '<button') === false && strpos($dueNoticeSource, '<span') === false, 'Due Dates explicit notice callback should not introduce richer markup.');
$assert(strpos($dueDatesContentSource, 'notice-error') === false && strpos($dueDatesContentSource, 'notice-success') === false && strpos($dueDatesContentSource, 'is-dismissible') === false, 'Due Dates content callback should no longer emit the migrated simple notices.');

$_GET = array(
	'vms_due_msg' => 'payee_added',
);
ob_start();
vms_due_render_admin_notices();
$dueSuccessNotice = (string) ob_get_clean();
$assert(
	$dueSuccessNotice === '<div class="notice notice-success is-dismissible"><p>payee added</p></div>',
	'Due Dates explicit notice callback should preserve the success notice fragment and normalized message text.'
);
$assert(
	wp_kses($dueSuccessNotice, vms_admin_ui_explicit_notice_allowed_html()) === $dueSuccessNotice,
	'The explicit notice allowlist should admit the Due Dates success notice unchanged.'
);

$_GET = array(
	'vms_due_msg' => 'due_complete_error_unknown',
);
ob_start();
vms_due_render_admin_notices();
$dueErrorNotice = (string) ob_get_clean();
$assert(
	$dueErrorNotice === '<div class="notice notice-error is-dismissible"><p>due complete error unknown</p></div>',
	'Due Dates explicit notice callback should preserve the error notice fragment and severity mapping.'
);

$_GET = array(
	'vms_due_msg' => '',
);
ob_start();
vms_due_render_admin_notices();
$dueNoNotice = (string) ob_get_clean();
$assert($dueNoNotice === '', 'Due Dates explicit notice callback should stay silent when no message slug is present.');

$assert(strpos($squareSyncProtectionSource, 'function vms_square_sync_protection_render_admin_notice(): void') !== false, 'Square Sync Protection should expose a dedicated explicit notice callback.');
$assert(substr_count($squareSyncProtectionSource, "'notices_callback' => 'vms_square_sync_protection_render_admin_notice'") === 1, 'Square Sync Protection shell call should supply its explicit notice callback exactly once.');
$assert(strpos($squareSyncProtectionSource, 'apply_filters(') === false && strpos($squareSyncProtectionSource, 'do_action(') === false, 'Square Sync Protection notice path should not hand off explicit notice markup through hooks or filters.');
$assert(strpos($squareSyncProtectionSource, 'settings_errors(') === false && strpos($squareSyncProtectionSource, 'do_settings_sections(') === false && strpos($squareSyncProtectionSource, 'wp_editor(') === false, 'Square Sync Protection should not route notice markup through Settings API or editor callbacks.');
$squareNoticeStart = strpos($squareSyncProtectionSource, 'function vms_square_sync_protection_render_admin_notice(): void');
$squareNoticeEnd = strpos($squareSyncProtectionSource, 'function vms_render_square_sync_protection_page_content(): void');
$assert($squareNoticeStart !== false && $squareNoticeEnd !== false && $squareNoticeEnd > $squareNoticeStart, 'Square Sync Protection explicit notice callback body should be locatable.');
$squareNoticeSource = substr($squareSyncProtectionSource, (int) $squareNoticeStart, (int) $squareNoticeEnd - (int) $squareNoticeStart);
$squareContentStart = strpos($squareSyncProtectionSource, 'function vms_render_square_sync_protection_page_content(): void');
$squareContentEnd = strpos($squareSyncProtectionSource, 'function vms_render_square_sync_protection_page(): void');
$assert($squareContentStart !== false && $squareContentEnd !== false && $squareContentEnd > $squareContentStart, 'Square Sync Protection content callback body should be locatable.');
$squareContentSource = substr($squareSyncProtectionSource, (int) $squareContentStart, (int) $squareContentEnd - (int) $squareContentStart);
$assert(strpos($squareNoticeSource, 'sanitize_key((string) $_GET[\'vms_square_notice\'])') !== false, 'Square Sync Protection explicit notice callback should preserve the existing sanitized notice source.');
$assert(strpos($squareNoticeSource, 'scan_done') !== false && strpos($squareNoticeSource, 'repair_done') !== false, 'Square Sync Protection explicit notice callback should preserve the existing notice conditions.');
$assert(strpos($squareNoticeSource, '<div class="notice notice-info"><p>') !== false, 'Square Sync Protection explicit notice callback should preserve the scan info notice fragment.');
$assert(strpos($squareNoticeSource, '<div class="notice notice-success"><p>') !== false, 'Square Sync Protection explicit notice callback should preserve the repair success notice fragment.');
$assert(strpos($squareNoticeSource, 'esc_html__(\'Square Sync Protection scan complete.\'') !== false, 'Square Sync Protection explicit notice callback should preserve the scan-complete message text.');
$assert(strpos($squareNoticeSource, 'esc_html__(\'Square Sync Protection repair complete.\'') !== false, 'Square Sync Protection explicit notice callback should preserve the repair-complete message text.');
$assert(strpos($squareNoticeSource, '<strong>') === false && strpos($squareNoticeSource, '<a ') === false && strpos($squareNoticeSource, '<button') === false && strpos($squareNoticeSource, '<span') === false, 'Square Sync Protection explicit notice callback should not introduce richer markup.');
$assert(strpos($squareContentSource, 'Square Sync Protection scan complete.') === false && strpos($squareContentSource, 'Square Sync Protection repair complete.') === false, 'Square Sync Protection content callback should no longer emit the migrated simple notices.');

$_GET = array(
	'vms_square_notice' => 'scan_done',
);
ob_start();
vms_square_sync_protection_render_admin_notice();
$squareScanNotice = (string) ob_get_clean();
$assert(
	$squareScanNotice === '<div class="notice notice-info"><p>Square Sync Protection scan complete.</p></div>',
	'Square Sync Protection explicit notice callback should preserve the scan-complete notice fragment.'
);
$assert(
	wp_kses($squareScanNotice, vms_admin_ui_explicit_notice_allowed_html()) === $squareScanNotice,
	'The explicit notice allowlist should admit the Square Sync Protection scan notice unchanged.'
);

$_GET = array(
	'vms_square_notice' => 'repair_done',
);
ob_start();
vms_square_sync_protection_render_admin_notice();
$squareRepairNotice = (string) ob_get_clean();
$assert(
	$squareRepairNotice === '<div class="notice notice-success"><p>Square Sync Protection repair complete.</p></div>',
	'Square Sync Protection explicit notice callback should preserve the repair-complete notice fragment.'
);

$_GET = array(
	'vms_square_notice' => '',
);
ob_start();
vms_square_sync_protection_render_admin_notice();
$squareNoNotice = (string) ob_get_clean();
$assert($squareNoNotice === '', 'Square Sync Protection explicit notice callback should stay silent when no notice slug is present.');

$assert(strpos($staffCertificationsSource, 'function vms_staff_certifications_get_pending_review_items(): array') !== false, 'Staff Certifications should expose a dedicated pending-review loader helper.');
$assert(strpos($staffCertificationsSource, "vms_staffing_get_staff_qualification_review_items('pending_verification')") !== false, 'Staff Certifications should preserve the exact pending-review provider call and argument.');
$assert(substr_count($staffCertificationsSource, "vms_staffing_get_staff_qualification_review_items('pending_verification')") === 1, 'Staff Certifications pending-review provider should appear exactly once in production source.');
$assert(strpos($staffCertificationsSource, 'function vms_staff_certifications_render_empty_state_notice(array $pending): void') !== false, 'Staff Certifications should expose a dedicated empty-state explicit notice helper.');
$assert(strpos($staffCertificationsSource, "'notices_callback' => function () use (\$pending): void {") !== false, 'Staff Certifications shell call should supply a page-local explicit notice closure using the resolved pending dataset.');
$assert(strpos($staffCertificationsSource, "function () use (\$pending): void {\n                    vms_render_staff_certifications_admin_page_content(\$pending);") !== false, 'Staff Certifications shell content callback should use the same resolved pending dataset.');
$assert(strpos($staffCertificationsSource, 'vms_render_staff_certifications_admin_page_content(?array $pending = null): void') !== false, 'Staff Certifications content callback should accept an already-resolved pending dataset.');
$assert(strpos($staffCertificationsSource, 'vms_staff_certifications_render_empty_state_notice($pending);') !== false, 'Staff Certifications fallback renderer should reuse the same pending dataset for the empty-state notice.');
$assert(strpos($staffCertificationsSource, 'if (empty($pending)) {') !== false, 'Staff Certifications should preserve the exact empty-state condition.');
$assert(strpos($staffCertificationsSource, '<div class="notice notice-success inline"><p>') !== false, 'Staff Certifications explicit notice helper should preserve the exact inline success notice fragment.');
$assert(strpos($staffCertificationsSource, 'esc_html__(\'No staff certifications are waiting for review.\'') !== false, 'Staff Certifications explicit notice helper should preserve the exact message translation and escaping function.');
$assert(strpos($staffCertificationsSource, "add_action('admin_notices', function (): void {") !== false, 'Staff Certifications should keep the separate rich warning family on the admin_notices hook.');
$assert(strpos($staffCertificationsSource, '$screen && isset($screen->id) && $screen->id === \'vms_page_vms-staff-certifications\'') !== false, 'Staff Certifications rich warning family should keep its existing screen visibility guard.');
$assert(strpos($staffCertificationsSource, 'notice notice-warning is-dismissible vms-staff-certifications-admin-notice') !== false, 'Staff Certifications should retain the richer global warning notice family markup unchanged.');
$assert(strpos($staffCertificationsSource, '<p><strong>') !== false && strpos($staffCertificationsSource, '<a href="') !== false, 'Staff Certifications rich warning family should remain outside the explicit notice contract.');

ob_start();
vms_staff_certifications_render_empty_state_notice(array());
$staffEmptyNotice = (string) ob_get_clean();
$assert(
	$staffEmptyNotice === '<div class="notice notice-success inline"><p>No staff certifications are waiting for review.</p></div>',
	'Staff Certifications explicit notice helper should preserve the exact inline empty-state notice fragment.'
);
$assert(
	wp_kses($staffEmptyNotice, vms_admin_ui_explicit_notice_allowed_html()) === $staffEmptyNotice,
	'The explicit notice allowlist should admit the Staff Certifications empty-state notice unchanged.'
);

ob_start();
vms_staff_certifications_render_empty_state_notice(
	array(
		array(
			'staff_id' => 17,
		),
	)
);
$staffNonemptyNotice = (string) ob_get_clean();
$assert($staffNonemptyNotice === '', 'Staff Certifications explicit notice helper should stay silent when pending items exist.');

ob_start();
vms_render_staff_certifications_admin_page_content(array());
$staffEmptyContent = (string) ob_get_clean();
$assert(strpos($staffEmptyContent, 'No staff certifications are waiting for review.') === false, 'Staff Certifications content callback should no longer emit the moved empty-state notice.');
$assert(strpos($staffEmptyContent, 'Pending Review') !== false, 'Staff Certifications content callback should still render the summary content for the empty state.');

ob_start();
vms_render_staff_certifications_admin_page_content(
	array(
		array(
			'staff_id' => 29,
			'staff_name' => 'Alex Example',
			'row' => array(
				'name' => 'TABC',
				'submitted_at' => 1710000000,
				'expiration_date' => '2026-12-31',
				'proof_download_url' => 'https://example.test/proof.pdf',
			),
		),
	)
);
$staffNonemptyContent = (string) ob_get_clean();
$assert(strpos($staffNonemptyContent, 'No staff certifications are waiting for review.') === false, 'Staff Certifications content callback should not emit the empty-state notice when pending items exist.');
$assert(strpos($staffNonemptyContent, 'Alex Example') !== false && strpos($staffNonemptyContent, 'TABC') !== false, 'Staff Certifications content callback should keep rendering nonempty review rows from the resolved dataset.');

$GLOBALS['vms_test_staff_certifications_pending_items'] = array();
$GLOBALS['vms_test_staff_certifications_provider_calls'] = 0;
$GLOBALS['vms_test_staff_certifications_provider_statuses'] = array();
ob_start();
vms_render_staff_certifications_admin_page();
$staffEmptyPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_staff_certifications_provider_calls'] === 1, 'Staff Certifications page renderer should resolve the pending-review dataset exactly once for the empty state.');
$assert($GLOBALS['vms_test_staff_certifications_provider_statuses'] === array('pending_verification'), 'Staff Certifications page renderer should use the existing pending-review provider argument exactly once.');
$assert(substr_count($staffEmptyPage, 'No staff certifications are waiting for review.') === 1, 'Staff Certifications page renderer should emit the empty-state notice exactly once when the resolved dataset is empty.');
$assert(strpos($staffEmptyPage, 'notice notice-success inline') !== false, 'Staff Certifications rendered empty-state notice should preserve the inline class.');
$assert(strpos($staffEmptyPage, 'Alex Example') === false, 'Staff Certifications empty-state render should not leak nonempty-row content.');

$GLOBALS['vms_test_staff_certifications_pending_items'] = array(
	array(
		'staff_id' => 31,
		'staff_name' => 'Jamie Queue',
		'row' => array(
			'name' => 'Food Handler',
			'submitted_at' => 1710001234,
			'expiration_date' => '2027-01-15',
			'proof_download_url' => 'https://example.test/queue-proof.pdf',
		),
	),
);
$GLOBALS['vms_test_staff_certifications_provider_calls'] = 0;
$GLOBALS['vms_test_staff_certifications_provider_statuses'] = array();
ob_start();
vms_render_staff_certifications_admin_page();
$staffNonemptyPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_staff_certifications_provider_calls'] === 1, 'Staff Certifications page renderer should still resolve the pending-review dataset exactly once for the nonempty state.');
$assert($GLOBALS['vms_test_staff_certifications_provider_statuses'] === array('pending_verification'), 'Staff Certifications nonempty render should keep the existing provider argument.');
$assert(strpos($staffNonemptyPage, 'No staff certifications are waiting for review.') === false, 'Staff Certifications page renderer should emit no empty-state notice when pending items exist.');
$assert(strpos($staffNonemptyPage, 'Jamie Queue') !== false && strpos($staffNonemptyPage, 'Food Handler') !== false, 'Staff Certifications page renderer should keep using the resolved dataset for nonempty page content.');
$assert(strpos($staffNonemptyPage, '>1</strong> certification needs review') !== false, 'Staff Certifications nonempty summary should still use the same resolved dataset count.');

$assert(strpos($socialSource, 'function vms_social_render_notices(): void') !== false, 'Social Sharing should expose a dedicated explicit notice callback.');
$assert(substr_count($socialSource, "'notices_callback' => 'vms_social_render_notices'") === 1, 'Social Sharing shell call should supply its explicit notice callback exactly once.');
$socialNoticeStart = strpos($socialSource, 'function vms_social_render_notices(): void');
$socialNoticeEnd = strpos($socialSource, "if (!function_exists('vms_social_render_admin_page'))");
$assert($socialNoticeStart !== false && $socialNoticeEnd !== false && $socialNoticeEnd > $socialNoticeStart, 'Social Sharing explicit notice callback body should be locatable.');
$socialNoticeSource = substr($socialSource, (int) $socialNoticeStart, (int) $socialNoticeEnd - (int) $socialNoticeStart);
$socialPageStart = strpos($socialSource, 'function vms_social_render_admin_page(): void');
$socialPageEnd = strpos($socialSource, "if (!function_exists('vms_social_render_admin_page_content'))");
$assert($socialPageStart !== false && $socialPageEnd !== false && $socialPageEnd > $socialPageStart, 'Social Sharing page renderer body should be locatable.');
$socialPageSource = substr($socialSource, (int) $socialPageStart, (int) $socialPageEnd - (int) $socialPageStart);
$socialContentStart = strpos($socialSource, 'function vms_social_render_admin_page_content(): void');
$socialContentEnd = strpos($socialSource, "if (!function_exists('vms_social_render_overview_tab'))");
$assert($socialContentStart !== false && $socialContentEnd !== false && $socialContentEnd > $socialContentStart, 'Social Sharing content callback body should be locatable.');
$socialContentSource = substr($socialSource, (int) $socialContentStart, (int) $socialContentEnd - (int) $socialContentStart);
$assert(strpos($socialNoticeSource, 'sanitize_text_field(vms_social_admin_query_arg(\'vms_social_notice\'))') !== false, 'Social Sharing explicit notice callback should preserve the sanitized notice message source.');
$assert(strpos($socialNoticeSource, 'sanitize_key(vms_social_admin_query_arg(\'vms_social_notice_type\'))') !== false, 'Social Sharing explicit notice callback should preserve the sanitized notice type source.');
$assert(strpos($socialNoticeSource, "array('error', 'warning', 'success', 'info')") !== false, 'Social Sharing explicit notice callback should preserve the existing notice type allowlist.');
$assert(strpos($socialNoticeSource, '<div class="notice notice-') !== false && strpos($socialNoticeSource, 'is-dismissible') !== false, 'Social Sharing explicit notice callback should preserve the dismissible notice class family.');
$assert(strpos($socialNoticeSource, 'esc_attr($class)') !== false && strpos($socialNoticeSource, 'esc_html($notice)') !== false, 'Social Sharing explicit notice callback should preserve contextual escaping.');
$assert(strpos($socialNoticeSource, '<strong>') === false && strpos($socialNoticeSource, '<a ') === false && strpos($socialNoticeSource, '<button') === false && strpos($socialNoticeSource, '<span') === false, 'Social Sharing explicit notice callback should stay within the simple fragment contract.');
$assert(strpos($socialPageSource, "'notices_callback' => 'vms_social_render_notices'") !== false, 'Social Sharing page renderer should pass the explicit notice callback through the Administrator shell.');
$assert(strpos($socialPageSource, "echo '<h1>'") !== false && strpos($socialPageSource, 'vms_social_render_notices();') !== false, 'Social Sharing no-shell fallback should preserve the pre-content notice ordering.');
$assert(strpos($socialContentSource, 'vms_social_render_notices();') === false, 'Social Sharing content callback should no longer emit the moved page-local notice family.');

$_GET = array(
	'vms_social_notice' => 'Accounts synced.',
	'vms_social_notice_type' => 'warning',
);
ob_start();
vms_social_render_notices();
$socialWarningNotice = (string) ob_get_clean();
$assert(
	$socialWarningNotice === '<div class="notice notice-warning is-dismissible"><p>Accounts synced.</p></div>',
	'Social Sharing explicit notice callback should preserve the warning notice fragment.'
);
$assert(
	wp_kses($socialWarningNotice, vms_admin_ui_explicit_notice_allowed_html()) === $socialWarningNotice,
	'The explicit notice allowlist should admit the Social Sharing warning notice unchanged.'
);

$_GET = array(
	'vms_social_notice' => '<strong>Queue</strong> run complete.',
	'vms_social_notice_type' => 'danger',
);
ob_start();
vms_social_render_notices();
$socialFallbackNotice = (string) ob_get_clean();
$assert(
	$socialFallbackNotice === '<div class="notice notice-success is-dismissible"><p>Queue run complete.</p></div>',
	'Social Sharing explicit notice callback should sanitize the notice text and fall back unknown types to success.'
);

$_GET = array(
	'vms_social_notice' => '',
	'vms_social_notice_type' => 'info',
);
ob_start();
vms_social_render_notices();
$socialNoNotice = (string) ob_get_clean();
$assert($socialNoNotice === '', 'Social Sharing explicit notice callback should stay silent when no notice text is present.');

$assert(strpos($emailFollowupsSource, 'function vms_email_followups_render_notices(): void') !== false, 'Email Follow-Ups should remain an inspected but unmigrated notice helper source.');
$assert(strpos($emailFollowupsSource, "'notices_callback' =>") === false, 'Email Follow-Ups should remain unmigrated in this pass.');
$assert(strpos($emailFollowupsSource, 'vms_email_followups_render_notices();') !== false, 'Email Follow-Ups render flow should still emit its notice helper from the page-render closure.');
$assert(strpos($emailFollowupsSource, 'No Event Plans found for preview/testing.') !== false, 'Email Follow-Ups should still retain its separate inline preview warning family.');

$assert(strpos($eventPlanImportSource, "'notices_callback' =>") === false, 'Event Plan Import should remain unmigrated in this pass.');
$assert(strpos($eventPlanImportSource, 'vms_event_plan_import_pop_notice();') !== false, 'Event Plan Import should still resolve its page-local notice payload inside the content-render path.');
$assert(strpos($eventPlanImportSource, 'vms_event_plan_import_notice_class($type)') !== false, 'Event Plan Import should still keep its inline notice-class mapper path.');
$assert(strpos($eventPlanImportSource, 'notice notice-error inline') !== false, 'Event Plan Import should still retain the separate preview rows payload error notice family.');

$allowedHeaderActions = '<a class="button button-primary" href="https://example.test/wp-admin/post-new.php?post_type=vms_event_plan">New Event Plan</a><a class="button" href="https://example.test/wp-admin/edit.php?post_type=vms_event_plan">Event Plans</a><div class="vms-ticket-integrity__header-actions" data-vms-tour="ticket-integrity.help"><button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.ticket_integrity.monitor" data-vms-tour="ticket-integrity.help">Start Guided Tour</button></div>';
$assert(
	wp_kses($allowedHeaderActions, vms_admin_ui_header_actions_allowed_html()) === $allowedHeaderActions,
	'Header actions allowlist should preserve the current anchor, wrapper, and guided-tour button fragments.'
);

$unsafeHeaderActions = '<div class="vms-help-menu" style="display:inline-block" data-vms-tour="ticket-integrity.help" data-vms-help-action="quick_tips"><details class="vms-help-menu" style="display:inline-block"><summary class="button button-secondary">Help</summary></details><button type="button" class="button" data-vms-tour-start="vms.ticket_integrity.monitor" data-vms-tour="ticket-integrity.help" data-vms-help-action="quick_tips" data-vms-help-open="1" onclick="alert(1)">Quick Tips</button><a class="button" href="javascript:alert(1)" target="_blank">Bad</a><script>alert(1)</script></div>';
$sanitizedHeaderActions = wp_kses($unsafeHeaderActions, vms_admin_ui_header_actions_allowed_html());
$assert(strpos($sanitizedHeaderActions, '<div class="vms-help-menu" data-vms-tour="ticket-integrity.help">') !== false, 'Header actions allowlist should preserve approved wrapper attributes.');
$assert(strpos($sanitizedHeaderActions, '<button type="button" class="button" data-vms-tour-start="vms.ticket_integrity.monitor" data-vms-tour="ticket-integrity.help">Quick Tips</button>') !== false, 'Header actions allowlist should preserve approved button hooks.');
$assert(strpos($sanitizedHeaderActions, '<a class="button">Bad</a>') !== false, 'Header actions allowlist should strip unsafe href protocols while preserving approved anchor markup.');
$assert(strpos($sanitizedHeaderActions, '<details') === false && strpos($sanitizedHeaderActions, '</details>') === false, 'Legacy details markup should not survive the canonical header-actions contract.');
$assert(strpos($sanitizedHeaderActions, '<summary') === false && strpos($sanitizedHeaderActions, '</summary>') === false, 'Legacy summary markup should not survive the canonical header-actions contract.');
$assert(strpos($sanitizedHeaderActions, '<script') === false && strpos($sanitizedHeaderActions, '</script>') === false, 'Header actions contract should reject script tags.');
$assert(stripos($sanitizedHeaderActions, 'style=') === false, 'Header actions contract should reject style attributes.');
$assert(stripos($sanitizedHeaderActions, 'target=') === false, 'Header actions contract should reject unapproved anchor attributes.');
$assert(stripos($sanitizedHeaderActions, 'data-vms-help-action=') === false, 'Header actions contract should reject unapproved data attributes.');
$assert(stripos($sanitizedHeaderActions, 'data-vms-help-open=') === false, 'Header actions contract should reject undiscovered helper attributes.');
$assert(preg_match('~<[^>]+\son[a-z]+\s*=~i', $sanitizedHeaderActions) === 0, 'Header actions contract should reject inline event-handler attributes.');

$unsafeHtml = '<div class="notice notice-success is-dismissible" style="color:red" data-track="1" role="alert" onclick="alert(1)"><p class="bad" aria-live="assertive" style="font-weight:bold">Saved<script>alert(1)</script><iframe src="https://example.test"></iframe><object data="bad"></object><embed src="bad"><form action="#"><input type="text" value="x"></form><a href="https://example.test">link</a><button type="button">button</button></p></div>';
$sanitizedHtml = wp_kses($unsafeHtml, vms_admin_ui_explicit_notice_allowed_html());
$assert(strpos($sanitizedHtml, '<div class="notice notice-success is-dismissible">') !== false, 'Allowed div tag and class attribute should survive.');
$assert(strpos($sanitizedHtml, '<p>') !== false, 'Allowed p tag should survive.');
$assert(strpos($sanitizedHtml, 'Saved') !== false, 'Notice text should survive sanitization.');
$assert(strpos($sanitizedHtml, '<script') === false && strpos($sanitizedHtml, '</script>') === false, 'Script tags should not survive.');
$assert(strpos($sanitizedHtml, '<iframe') === false && strpos($sanitizedHtml, '</iframe>') === false, 'Iframe tags should not survive.');
$assert(strpos($sanitizedHtml, '<object') === false && strpos($sanitizedHtml, '</object>') === false, 'Object tags should not survive.');
$assert(strpos($sanitizedHtml, '<embed') === false, 'Embed tags should not survive.');
$assert(strpos($sanitizedHtml, '<form') === false && strpos($sanitizedHtml, '</form>') === false, 'Form tags should not survive.');
$assert(strpos($sanitizedHtml, '<input') === false, 'Input tags should not survive.');
$assert(strpos($sanitizedHtml, '<a ') === false && strpos($sanitizedHtml, '</a>') === false, 'Anchor tags should not survive.');
$assert(strpos($sanitizedHtml, '<button') === false && strpos($sanitizedHtml, '</button>') === false, 'Button tags should not survive.');
$assert(preg_match('~<[^>]+\son[a-z]+\s*=~i', $sanitizedHtml) === 0, 'Inline event-handler attributes should not survive.');
$assert(stripos($sanitizedHtml, 'style=') === false, 'Style attributes should not survive.');
$assert(stripos($sanitizedHtml, 'data-track=') === false, 'Data attributes should not survive.');
$assert(stripos($sanitizedHtml, 'role=') === false, 'Role attributes should not survive.');
$assert(stripos($sanitizedHtml, 'aria-live=') === false, 'ARIA attributes should not survive.');
$assert(preg_match('~<p[^>]+\s(?:class|id|role|aria-[a-z-]+)=~i', $sanitizedHtml) === 0, 'Unapproved p attributes should not survive.');

$malformedHtml = '<div class="notice notice-success is-dismissible" data-bad="1"><p title="&quot; onmouseover=&quot;alert(1)">Broken</p></div>';
$sanitizedMalformedHtml = wp_kses($malformedHtml, vms_admin_ui_explicit_notice_allowed_html());
$assert(strpos($sanitizedMalformedHtml, 'onmouseover=') === false, 'Malformed attributes should not escape into executable attributes.');
$assert(strpos($sanitizedMalformedHtml, 'title=') === false, 'Malformed p attributes should not survive.');
$assert(strpos($sanitizedMalformedHtml, 'data-bad=') === false, 'Malformed div data attributes should not survive.');
$assert(strpos($sanitizedMalformedHtml, '<div class="notice notice-success is-dismissible"><p>Broken</p></div>') !== false, 'Malformed markup should remain inside the intended fragment contract.');

fwrite(STDOUT, "Administrator shell output remediation OK.\n");
