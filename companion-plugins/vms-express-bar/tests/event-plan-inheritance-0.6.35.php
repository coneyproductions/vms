<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$event_meta = array(
    '_vms_event_date' => '2030-09-19',
    '_vms_start_time' => '19:00',
    '_vmseb_open_at' => '',
    '_vmseb_close_at' => '',
    '_vms_express_bar_enabled' => '1',
);

function absint($value): int { return abs((int) $value); }
function post_type_exists(string $type): bool { return $type === 'vms_event_plan'; }
function get_option(string $key, $default = false)
{
    if ($key === 'vmseb_settings') {
        return array(
            'default_window_auto_apply' => 1,
            'default_window_open_minutes_before' => 2880,
            'default_window_close_minutes_before' => 0,
        );
    }
    if ($key === 'vmseb_bar_menu_defaults') return array();
    if ($key === 'timezone_string') return 'America/Chicago';
    if ($key === 'gmt_offset') return -6;
    return $default;
}
function get_post_meta(int $id, string $key, bool $single = false)
{
    global $event_meta;
    unset($id, $single);
    return $event_meta[$key] ?? '';
}
function wp_timezone(): DateTimeZone { return new DateTimeZone('America/Chicago'); }
function wp_date(string $format, ?int $timestamp = null): string
{
    $date = new DateTimeImmutable('@' . ($timestamp ?? time()));
    return $date->setTimezone(wp_timezone())->format($format);
}
function get_post(int $id) { unset($id); return null; }

require dirname(__DIR__) . '/includes/helpers.php';

function vmseb_inheritance_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$inherited = vmseb_get_event_window_status(1);
vmseb_inheritance_assert($inherited['opens_at'] instanceof DateTimeImmutable, 'blank event open did not inherit the global default');
vmseb_inheritance_assert($inherited['closes_at'] instanceof DateTimeImmutable, 'blank event close did not inherit the global default');
vmseb_inheritance_assert($inherited['opens_at']->format('Y-m-d H:i') === '2030-09-17 19:00', 'inherited open did not use the configured offset');
vmseb_inheritance_assert($inherited['closes_at']->format('Y-m-d H:i') === '2030-09-19 19:00', 'inherited close did not use the configured offset');

$event_meta['_vmseb_open_at'] = '2030-09-18T15:30';
$event_meta['_vmseb_close_at'] = '2030-09-20T01:15';
$explicit = vmseb_get_event_window_status(1);
vmseb_inheritance_assert($explicit['opens_at'] instanceof DateTimeImmutable && $explicit['opens_at']->format('Y-m-d H:i') === '2030-09-18 15:30', 'explicit event open did not beat the global default');
vmseb_inheritance_assert($explicit['closes_at'] instanceof DateTimeImmutable && $explicit['closes_at']->format('Y-m-d H:i') === '2030-09-20 01:15', 'explicit event close did not beat the global default');

fwrite(STDOUT, "PASS: Event Plan/global Express Bar inheritance 0.6.35\n");
