<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function absint($value): int { return abs((int) $value); }
function post_type_exists(string $type): bool { return $type === 'vms_event_plan'; }
function get_option(string $key, $default = false) {
    if ($key === 'vmseb_settings') return array(
        'default_window_auto_apply' => 1,
        'default_window_open_minutes_before' => 2880,
        'default_window_close_minutes_before' => 0,
    );
    if ($key === 'vmseb_bar_menu_defaults') return array();
    if ($key === 'timezone_string') return 'America/Chicago';
    if ($key === 'gmt_offset') return -6;
    return $default;
}
function get_post_meta(int $id, string $key, bool $single = false) {
    unset($id, $single);
    $values = array('_vms_event_date' => '2026-09-19', '_vms_start_time' => '19:00');
    return $values[$key] ?? '';
}
function wp_timezone(): DateTimeZone { return new DateTimeZone('America/Chicago'); }
function get_post(int $id) { unset($id); return null; }

require dirname(__DIR__) . '/includes/helpers.php';

function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

$window = vmseb_get_event_default_window(1);
check($window['open'] instanceof DateTimeImmutable, 'default open time missing');
check($window['close'] instanceof DateTimeImmutable, 'default close time missing');
check($window['open']->format('Y-m-d H:i') === '2026-09-17 19:00', 'default open is not 48 hours before showtime');
check($window['close']->format('Y-m-d H:i') === '2026-09-19 19:00', 'default close is not showtime');

fwrite(STDOUT, "PASS: default ordering window 0.6.30\n");
