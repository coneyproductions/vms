<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$passClaims = file_get_contents($root . '/includes/modules/admissions/pass-claims.php');
$outreach = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
if (!is_string($passClaims) || !is_string($outreach)) {
	throw new RuntimeException('Could not read Guest Pass contact-policy sources.');
}

$assertions = array(
	'Native claims still require phone by default' => strpos($passClaims, "!array_key_exists('require_phone', \$context) || !empty(\$context['require_phone'])") !== false,
	'Email is optional unless a trusted caller requires it' => strpos($passClaims, "\$require_email = !empty(\$context['require_email'])") !== false,
	'Required email uses WordPress email validation' => strpos($passClaims, "\$require_email && (\$email === '' || !is_email(\$email))") !== false,
	'Optional empty phone skips phone-limit accounting' => strpos($passClaims, "\$max_per_phone > 0 && \$phone_norm !== ''") !== false,
	'Outreach alone selects email-required phone-optional behavior' => strpos($outreach, "'require_phone' => false") !== false && strpos($outreach, "'require_email' => true") !== false,
	'Outreach validates claimant email before claim mutation' => strpos($outreach, "new WP_Error('invalid_claimant_email'") !== false && strpos($outreach, "!is_email(\$email)") !== false,
);

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed) {
	throw new RuntimeException("Guest Pass contact-policy assertions failed:\n- " . implode("\n- ", $failed));
}

echo 'Guest Pass contact policy PASS (' . count($assertions) . " assertions)\n";
