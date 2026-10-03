<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

final class WP_Post
{
    public int $ID;
    public string $post_type = 'vms_event_plan';
    public string $post_status = 'publish';
    public function __construct(int $id = 0) { $this->ID = $id; }
}

$last_get_posts_args = array();
$meta = array(
    2535 => array(
        '_vms_express_bar_enabled' => '1',
        '_vms_event_date' => '2099-10-02',
        '_vms_start_time' => '19:00',
        '_vms_event_plan_status' => 'draft',
    ),
    5797 => array(
        '_vms_express_bar_enabled' => '1',
        '_vms_event_date' => '2099-11-20',
        '_vms_start_time' => '19:00',
        '_vms_event_plan_status' => 'published',
    ),
    6000 => array(
        '_vms_express_bar_enabled' => '0',
        '_vms_event_date' => '2099-09-18',
        '_vms_start_time' => '19:00',
        '_vms_event_plan_status' => 'published',
    ),
);
$status = array(2535 => 'draft', 5797 => 'publish', 6000 => 'publish');

function absint($value): int { return abs((int) $value); }
function post_type_exists(string $type): bool { return $type === 'vms_event_plan'; }
function get_post_type(int $id): string { return isset($GLOBALS['status'][$id]) ? 'vms_event_plan' : ''; }
function get_post_status(int $id): string { return $GLOBALS['status'][$id] ?? ''; }
function get_post_meta(int $id, string $key, bool $single = false) { unset($single); return $GLOBALS['meta'][$id][$key] ?? ''; }
function get_posts(array $args): array { $GLOBALS['last_get_posts_args'] = $args; return array(2535, 5797, 6000); }
function get_option(string $key, $default = false) {
    if ($key === 'timezone_string') return 'America/Chicago';
    if ($key === 'gmt_offset') return -6;
    if ($key === 'vmseb_settings') return array();
    if ($key === 'vmseb_bar_menu_defaults') return array();
    return $default;
}
function wp_timezone(): DateTimeZone { return new DateTimeZone('America/Chicago'); }
function get_post(int $id) { return null; }
function get_the_title(int $id): string { return 'Event ' . $id; }

require dirname(__DIR__) . '/includes/helpers.php';

function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

check(vmseb_event_plan_is_public_candidate(2535, true) === false, 'draft Event Plan was accepted as public candidate');
check(vmseb_event_plan_is_public_candidate(5797, true) === true, 'published enabled Event Plan was rejected');
check(vmseb_event_plan_is_public_candidate(6000, true) === false, 'disabled Event Plan was accepted when Express Bar is required');

$resolved = vmseb_resolve_public_event_plan_id();
check($resolved === 5797, 'resolver did not skip draft/disabled plans');
check(($last_get_posts_args['post_status'] ?? '') === 'publish', 'resolver query is not publish-only');
check(($last_get_posts_args['meta_key'] ?? '') === '_vms_express_bar_enabled', 'resolver query does not require Express Bar marker');
check(($last_get_posts_args['meta_value'] ?? '') === '1', 'resolver query does not require Express Bar enabled value');

fwrite(STDOUT, "PASS: public event selection 0.6.30\n");
