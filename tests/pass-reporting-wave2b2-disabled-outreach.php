<?php

define('ABSPATH', __DIR__ . '/');
function __($text, $domain = null) { return $text; }

require dirname(__DIR__) . '/includes/modules/admissions/pass-reporting.php';

$state = bvmgr_pass_reporting_outreach_tables();
if (!empty($state['available']) || !str_contains((string) ($state['note'] ?? ''), 'disabled')) {
	fwrite(STDERR, "FAIL: disabled Outreach did not degrade truthfully.\n");
	exit(1);
}

echo "Wave 2B.2 disabled-Outreach fallback PASS\n";
