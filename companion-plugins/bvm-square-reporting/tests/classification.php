<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
function add_action(...$args): void { unset($args); }
function sanitize_key($value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }

require dirname(__DIR__) . '/includes/core.php';

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

check(bvm_sqr_class_for_role('ga_ticket') === 'online_ticket', 'ga_ticket should map to online_ticket');
check(bvm_sqr_class_for_role('ticket') === 'online_ticket', 'ticket should map to online_ticket');
check(bvm_sqr_class_for_role('entitlement') === 'online_addon', 'entitlement should map to online_addon');
check(bvm_sqr_class_for_role('rental') === 'rental', 'rental should map to rental');
check(bvm_sqr_class_for_role('online_tip') === 'online_tips', 'online_tip should map to online_tips');
check(bvm_sqr_class_for_role('express_bar') === '', 'normal Express Bar product should not be overridden');

fwrite(STDOUT, "PASS: BVM Square Reporting role classification\n");
