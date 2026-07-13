<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$vendorPortalSource = file_get_contents($pluginRoot . '/includes/portal/vendor-portal.php');
$taxProfileSource = file_get_contents($pluginRoot . '/includes/portal/vendor-tax-profile.php');
$guestPortalSource = file_get_contents($pluginRoot . '/includes/modules/admissions/vendor-guest-portal.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($vendorPortalSource) && $vendorPortalSource !== '', 'Vendor Portal source should be readable.');
$assert(is_string($taxProfileSource) && $taxProfileSource !== '', 'Vendor tax profile source should be readable.');
$assert(is_string($guestPortalSource) && $guestPortalSource !== '', 'Vendor guest portal source should be readable.');

$assert(strpos($vendorPortalSource, 'function vms_portal_notice(string $type, string $msg): string') !== false, 'Portal notice helper signature should remain string-only.');
$assert(strpos($vendorPortalSource, '$type = ($type === \'success\' || $type === \'warning\') ? $type : \'error\';') !== false, 'Portal notice helper should keep the existing success/warning/error type contract.');
$assert(strpos($vendorPortalSource, 'esc_attr($type)') !== false, 'Portal notice helper should keep escaping the notice type for the class attribute.');
$assert(strpos($vendorPortalSource, 'esc_html($msg)') !== false, 'Portal notice helper should keep escaping notice text.');
$assert(strpos($vendorPortalSource, 'wp_kses_post(vms_portal_notice(') !== false, 'Main Vendor Portal should continue to show the established portal notice sink pattern.');

$targetSources = array(
	'includes/portal/vendor-tax-profile.php' => $taxProfileSource,
	'includes/modules/admissions/vendor-guest-portal.php' => $guestPortalSource,
);

$expectedSinkCounts = array(
	'includes/portal/vendor-tax-profile.php' => 3,
	'includes/modules/admissions/vendor-guest-portal.php' => 3,
);

foreach ($targetSources as $relativePath => $source) {
	$assert(strpos($source, 'echo vms_portal_notice(') === false, $relativePath . ' should not directly echo portal notice fragments.');
	$assert(substr_count($source, 'echo wp_kses_post(vms_portal_notice(') === $expectedSinkCounts[$relativePath], $relativePath . ' should wrap each direct portal notice fragment with the established sink.');
	$assert(strpos($source, 'esc_html(vms_portal_notice(') === false, $relativePath . ' should not text-escape the full helper-generated fragment.');
	$assert(strpos($source, 'esc_attr(vms_portal_notice(') === false, $relativePath . ' should not attribute-escape the full helper-generated fragment.');
	$assert(strpos($source, 'wp_kses(vms_portal_notice(') === false, $relativePath . ' should not add a divergent inline allowlist for this narrow slice.');
	$assert(!preg_match('~phpcs:ignore[^\n]*vms_portal_notice~i', $source), $relativePath . ' should not silence portal notice sink findings without changing the sink.');
}

foreach ($targetSources as $relativePath => $source) {
	preg_match_all('~vms_portal_notice\([^\n]+~', $source, $noticeCallMatches);
	foreach ($noticeCallMatches[0] as $noticeCall) {
		$assert(strpos($noticeCall, '<a ') === false && strpos($noticeCall, 'href=') === false, $relativePath . ' portal notice calls should not introduce link markup; the helper contract is escaped text inside the notice div.');
	}
}

$escAttr = static function (string $value): string {
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$escHtml = static function (string $value): string {
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$portalNotice = static function (string $type, string $message) use ($escAttr, $escHtml): string {
	$type = ($type === 'success' || $type === 'warning') ? $type : 'error';
	return '<div class="vms-notice vms-notice-' . $escAttr($type) . '">' . $escHtml($message) . '</div>';
};

$assert(
	$portalNotice('success', 'Tax Profile saved.') === '<div class="vms-notice vms-notice-success">Tax Profile saved.</div>',
	'Portal notice helper should preserve the expected success notice fragment.'
);
$assert(
	$portalNotice('warning', 'No upcoming events currently allow this vendor account to add complimentary guests.') === '<div class="vms-notice vms-notice-warning">No upcoming events currently allow this vendor account to add complimentary guests.</div>',
	'Portal notice helper should preserve the expected warning notice fragment.'
);
$assert(
	$portalNotice('"><script>alert(1)</script>', '<img src=x onerror=alert(1)>') === '<div class="vms-notice vms-notice-error">&lt;img src=x onerror=alert(1)&gt;</div>',
	'Portal notice helper should normalize malicious types and escape malicious message HTML before the sink receives it.'
);

fwrite(STDOUT, "Portal notice sink remediation OK.\n");
