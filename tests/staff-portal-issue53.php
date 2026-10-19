<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/../');
define('ARRAY_A', 'ARRAY_A');
function add_shortcode(...$args): void {}
function add_action(...$args): void {}
function add_filter(...$args): void {}
function plugin_dir_path($file): string { return '/nonexistent/'; }
function absint($value): int { return abs((int) $value); }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_title($value): string { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $value)), '-'); }
function __($value, $domain = ''): string { return (string) $value; }
function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html__($value, $domain = ''): string { return esc_html($value); }
function esc_url($value): string { return (string) $value; }
function home_url($path = ''): string { return 'https://example.test' . $path; }
function wp_timezone(): DateTimeZone { return new DateTimeZone('America/Chicago'); }
function wp_date($format, $timestamp = null, $timezone = null): string {
    return $format === 'Y-m-d' ? '2026-10-19' : date((string) $format, (int) $timestamp);
}
function get_option($key, $default = null): array { return array('staff_portal_doc_visibility_role_ids' => array(999)); }
function get_post_meta($id, $key, $single = true) {
    return $GLOBALS['plans'][$id][$key] ?? '';
}
function get_post_type($id): string { return $id === 3222 ? 'vms_staff' : 'vms_event_plan'; }
function get_post_status($id): string { return $GLOBALS['post_status'][$id] ?? 'publish'; }
function get_the_title($id): string { return 'Plan ' . $id; }
function get_user_meta($id, $key, $single = true): int { return $id === 120 ? 3222 : 0; }
function get_userdata($id): object { return (object) array('roles' => $GLOBALS['user_roles']); }
function get_current_user_id(): int { return $GLOBALS['current_user']; }
function is_user_logged_in(): bool { return $GLOBALS['current_user'] > 0; }
function current_user_can(...$args): bool { return false; }
function bvmgr_staffing_table_name($kind): string { return 'wp_vms_' . $kind; }
function bvmgr_staffing_role_map_by_id($include_inactive = true): array { return $GLOBALS['roles']; }
function bvmgr_event_plan_get_status($id, $context = ''): string { return $GLOBALS['plans'][$id]['status'] ?? 'published'; }
function bvmgr_staff_portal_assignment_status_label($status): string { return ucfirst($status); }
function bvmgr_tech_doc_role_is_technical(array $role): bool { return ($role['slug'] ?? '') === 'sound-tech'; }
function bvmgr_tech_doc_types(): array { return array('stage_plot' => 'Stage plot'); }
function bvmgr_tech_doc_vendor_ids(int $plan_id): array { return $plan_id === 500 ? array(77) : array(); }

class Issue53DB {
    public string $postmeta = 'wp_postmeta';
    public string $posts = 'wp_posts';
    public string $last_sql = '';
    public function prepare($sql, ...$args): string { $this->last_sql = $sql; return $sql; }
    public function get_results($sql, $format): array {
        $eligible_before_limit = strpos($sql, 'event_date.meta_value >= %s') !== false;
        $rows = array_filter($GLOBALS['fixture'], static function ($row) use ($eligible_before_limit, $sql) {
            if (strpos($sql, "a.status = 'confirmed'") !== false && $row['assignment_status'] !== 'confirmed') return false;
            if (strpos($sql, "a.status = 'proposed'") !== false && $row['assignment_status'] !== 'proposed') return false;
            return !$eligible_before_limit || $GLOBALS['plans'][$row['event_plan_id']]['_vms_event_date'] >= '2026-10-19';
        });
        usort($rows, static function ($a, $b) {
            return strcmp($GLOBALS['plans'][$a['event_plan_id']]['_vms_event_date'], $GLOBALS['plans'][$b['event_plan_id']]['_vms_event_date']);
        });
        return array_slice($rows, 0, 12);
    }
    public function get_col($sql): array { return $GLOBALS['assignment_active'] ? array($GLOBALS['assignment_role']) : array(); }
}
$wpdb = new Issue53DB();
$GLOBALS['plans'] = array();
$GLOBALS['fixture'] = array();
$GLOBALS['roles'] = array(2 => array('role_id' => 2, 'slug' => 'sound-tech', 'name' => 'Sound Tech'),
    3 => array('role_id' => 3, 'slug' => 'event-manager', 'name' => 'Event Manager'));
$GLOBALS['user_roles'] = array('subscriber');
$GLOBALS['current_user'] = 120;
$GLOBALS['assignment_active'] = true;
$GLOBALS['assignment_role'] = 2;
for ($i = 1; $i <= 13; $i++) {
    $id = 100 + $i;
    $GLOBALS['plans'][$id] = array('_vms_event_date' => '2026-03-' . sprintf('%02d', $i), 'status' => 'published');
    $GLOBALS['fixture'][] = array('assignment_id' => $i, 'staff_id' => 3222, 'assignment_status' => 'proposed',
        'event_plan_id' => $id, 'role_id' => 2, 'shift_start_ts' => 0, 'shift_end_ts' => 0);
}
foreach (array(400 => array('2026-10-20', 'proposed'), 500 => array('2026-10-24', 'confirmed')) as $id => $values) {
    $GLOBALS['plans'][$id] = array('_vms_event_date' => $values[0], 'status' => 'published');
    $GLOBALS['fixture'][] = array('assignment_id' => $id, 'staff_id' => 3222, 'assignment_status' => $values[1],
        'event_plan_id' => $id, 'role_id' => 2, 'shift_start_ts' => 0, 'shift_end_ts' => 0);
}
require __DIR__ . '/../includes/portal/staff-portal.php';
require __DIR__ . '/../includes/portal/vendor-portal.php';
$check = static function ($ok, $label): void { if (!$ok) throw new RuntimeException($label); };
$rows = bvmgr_staff_portal_get_assignment_rows(3222, 12);
$check(count($rows) === 2, '13 past assignments must not consume the future limit');
$check(strpos($wpdb->last_sql, 'event_date.meta_value >= %s') < strpos($wpdb->last_sql, 'LIMIT %d'), 'date eligibility before limit');
$confirmed = bvmgr_staff_portal_get_assignment_rows(3222, 12, 'confirmed');
$proposed = bvmgr_staff_portal_get_assignment_rows(3222, 12, 'proposed');
$check(count($confirmed) === 1 && $confirmed[0]['event_plan_id'] === 500, 'earliest confirmed next shift');
$check(count($proposed) === 1 && $proposed[0]['event_plan_id'] === 400, 'proposal remains separate');
for ($i = 0; $i < 13; $i++) {
    $id = 600 + $i;
    $GLOBALS['plans'][$id] = array('_vms_event_date' => '2026-10-21', 'status' => 'published');
    $GLOBALS['fixture'][] = array('assignment_id' => $id, 'staff_id' => 3222, 'assignment_status' => 'proposed',
        'event_plan_id' => $id, 'role_id' => 2, 'shift_start_ts' => 0, 'shift_end_ts' => 0);
}
$confirmed = bvmgr_staff_portal_get_assignment_rows(3222, 12, 'confirmed');
$check(count($confirmed) === 1 && $confirmed[0]['event_plan_id'] === 500, 'future proposals cannot displace confirmed next shift');
$check(bvmgr_staff_portal_user_can_access_event(120, 500, true), 'sound technician event access');
$check(!bvmgr_staff_portal_user_can_access_event(0, 500, true), 'guest denied');
$check(!bvmgr_staff_portal_user_can_access_event(121, 500, true), 'unlinked staff denied');
$check(bvmgr_vendor_portal_user_can_download_tech_doc(77, 'stage_plot', 500), 'linked document allowed');
$check(!bvmgr_vendor_portal_user_can_download_tech_doc(78, 'stage_plot', 500), 'guessed vendor document denied');
$check(!bvmgr_vendor_portal_user_can_download_tech_doc(77, 'stage_plot', 400), 'wrong event denied');
$GLOBALS['assignment_active'] = false;
$check(!bvmgr_staff_portal_user_can_access_event(120, 500, true), 'revoked assignment denied');
$GLOBALS['assignment_active'] = true;
unset($GLOBALS['roles'][2]);
$check(!bvmgr_staff_portal_user_can_access_event(120, 500, true), 'removed role denied');
$GLOBALS['roles'][2] = array('role_id' => 2, 'slug' => 'sound-tech', 'name' => 'Sound Tech');
$GLOBALS['user_roles'] = array('event_manager');
$check(bvmgr_staff_portal_user_can_access_event(120, 500, true), 'assigned manager can open documents independent of role ID');
$GLOBALS['post_status'][500] = 'draft';
$check(!bvmgr_staff_portal_user_can_access_event(120, 500, true), 'draft plan denied');
ob_start();
bvmgr_staff_portal_render_my_account_entry();
$account_entry = ob_get_clean();
$check(strpos($account_entry, 'Open Staff Portal') !== false, 'linked staff My Account entry');
$GLOBALS['current_user'] = 121;
ob_start();
bvmgr_staff_portal_render_my_account_entry();
$check(ob_get_clean() === '', 'unlinked customer has no staff entry');
echo "Staff Portal issue #53: 17 checks passed\n";
