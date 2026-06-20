<?php
declare(strict_types=1);

if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', true);
}
if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('VMS_Admin_Event_Plans')) {
    require_once dirname(__DIR__) . '/vendor-management-system.php';
}

final class VMS_Calendar_Suppress_Ajax_Exit extends RuntimeException
{
}

function vms_calendar_suppress_test_wp_die_handler($message = '', $title = '', $args = array()): void
{
    unset($title, $args);

    if (is_scalar($message)) {
        throw new VMS_Calendar_Suppress_Ajax_Exit((string) $message);
    }

    throw new VMS_Calendar_Suppress_Ajax_Exit('wp_die');
}

$assert = static function (bool $condition, string $message): void {
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
};

$createdPosts = array();
$originalPost = $_POST ?? array();
$originalGet = $_GET ?? array();
$originalRequest = $_REQUEST ?? array();

$registerPost = static function (int $postId) use (&$createdPosts): int {
    $createdPosts[] = $postId;
    return $postId;
};

$cleanup = static function () use (&$createdPosts, &$originalPost, &$originalGet, &$originalRequest): void {
    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }

    $_POST = $originalPost;
    $_GET = $originalGet;
    $_REQUEST = $originalRequest;
};

try {
    wp_set_current_user(1);
    $assert(current_user_can('edit_posts'), 'Expected test user to be able to edit posts.');
    $assert(false !== has_action('wp_ajax_vms_save_event_plan_calendar_unpublished_suppress'), 'Expected suppressor AJAX action to remain registered.');

    $dieHandlerFilter = static function (): string {
        return 'vms_calendar_suppress_test_wp_die_handler';
    };
    add_filter('wp_die_handler', $dieHandlerFilter);
    add_filter('wp_die_ajax_handler', $dieHandlerFilter);

    $createPlan = static function (string $title) use ($registerPost): int {
        $planId = wp_insert_post(array(
            'post_type' => 'vms_event_plan',
            'post_status' => 'publish',
            'post_title' => $title,
        ), true);
        if (is_wp_error($planId) || (int) $planId <= 0) {
            throw new RuntimeException('Failed to create test Event Plan.');
        }

        return $registerPost((int) $planId);
    };

    $dispatchAjax = static function (string $action, array $payload = array()) use ($assert): array {
        $prevPost = $_POST ?? array();
        $prevGet = $_GET ?? array();
        $prevRequest = $_REQUEST ?? array();

        $payload['action'] = $action;
        $payload['nonce'] = wp_create_nonce('vms_event_plan_calendar_unpublished_suppress_save');
        $_POST = $payload;
        $_GET = array();
        $_REQUEST = $payload;

        $bufferLevel = ob_get_level();
        $json = '';

        try {
            ob_start();
            try {
                do_action('wp_ajax_' . $action);
            } catch (VMS_Calendar_Suppress_Ajax_Exit $e) {
                unset($e);
            }
            $json = (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            $_POST = $prevPost;
            $_GET = $prevGet;
            $_REQUEST = $prevRequest;
        }

        $decoded = json_decode(trim($json), true);
        $assert(is_array($decoded), 'Failed to decode AJAX JSON. Raw output: ' . substr(trim($json), 0, 200));

        return $decoded;
    };

    $kSuppress = function_exists('vms_meta_key')
        ? (vms_meta_key('event_plan', 'calendar_unpublished_suppress') ?: '_vms_calendar_unpublished_suppress')
        : '_vms_calendar_unpublished_suppress';
    $kSecondaryIds = function_exists('vms_meta_key')
        ? (vms_meta_key('event_plan', 'secondary_vendor_ids') ?: '_vms_secondary_vendor_ids')
        : '_vms_secondary_vendor_ids';
    $kSecondaryType = function_exists('vms_meta_key')
        ? (vms_meta_key('event_plan', 'secondary_vendor_type') ?: '_vms_secondary_vendor_type')
        : '_vms_secondary_vendor_type';
    $kTicketOverride = function_exists('vms_meta_key')
        ? (vms_meta_key('event_plan', 'ticketing_enabled_override') ?: '_vms_ticketing_enabled_override')
        : '_vms_ticketing_enabled_override';
    $kTicketLayoutOverride = '_vms_ticket_ui_layout_override';
    $kTicketHeadingOverride = '_vms_ticket_ui_addons_heading_override';
    $kIntegrityIssue = function_exists('vms_meta_key')
        ? (vms_meta_key('event_plan', 'integrity_issue') ?: '_vms_integrity_issue')
        : '_vms_integrity_issue';
    $kIntegrityTs = function_exists('vms_meta_key')
        ? (vms_meta_key('event_plan', 'integrity_ts') ?: '_vms_integrity_ts')
        : '_vms_integrity_ts';

    $captureState = static function (int $planId) use ($kSuppress, $kSecondaryIds, $kSecondaryType, $kTicketOverride, $kTicketLayoutOverride, $kTicketHeadingOverride, $kIntegrityIssue, $kIntegrityTs): array {
        return array(
            'core_details' => array(
                'event_date' => (string) get_post_meta($planId, '_vms_event_date', true),
                'start_time' => (string) get_post_meta($planId, '_vms_start_time', true),
                'end_time' => (string) get_post_meta($planId, '_vms_end_time', true),
                'venue_id' => (string) get_post_meta($planId, '_vms_venue_id', true),
            ),
            'secondary_vendors' => array(
                'type' => (string) get_post_meta($planId, $kSecondaryType, true),
                'ids' => (array) get_post_meta($planId, $kSecondaryIds, true),
                'index_ids' => (array) get_post_meta($planId, '_vms_secondary_vendor_id', false),
            ),
            'staffing' => array(
                'assignments' => (array) get_post_meta($planId, '_vms_staff_assignments', true),
            ),
            'ticketing' => array(
                'enabled_override' => (string) get_post_meta($planId, $kTicketOverride, true),
                'layout_override' => (string) get_post_meta($planId, $kTicketLayoutOverride, true),
                'heading_override' => (string) get_post_meta($planId, $kTicketHeadingOverride, true),
            ),
            'integrity' => array(
                'issue' => (string) get_post_meta($planId, $kIntegrityIssue, true),
                'ts' => (string) get_post_meta($planId, $kIntegrityTs, true),
            ),
            'suppressor' => (string) get_post_meta($planId, $kSuppress, true),
        );
    };

    $planId = $createPlan('Calendar Suppressor Save Slice Test');
    update_post_meta($planId, '_vms_event_date', '2026-06-12');
    update_post_meta($planId, '_vms_start_time', '18:30');
    update_post_meta($planId, '_vms_end_time', '22:00');
    update_post_meta($planId, '_vms_venue_id', '44');
    update_post_meta($planId, $kSecondaryType, 'food_truck');
    update_post_meta($planId, $kSecondaryIds, array(701, 702));
    delete_post_meta($planId, '_vms_secondary_vendor_id');
    add_post_meta($planId, '_vms_secondary_vendor_id', 701, false);
    add_post_meta($planId, '_vms_secondary_vendor_id', 702, false);
    update_post_meta($planId, '_vms_staff_assignments', array(
        97 => array(2443),
        85 => array(1248),
    ));
    update_post_meta($planId, $kTicketOverride, 'on');
    update_post_meta($planId, $kTicketLayoutOverride, 'progressive');
    update_post_meta($planId, $kTicketHeadingOverride, 'Fire Pits & Tables');
    update_post_meta($planId, $kIntegrityIssue, 'calendar_event_unpublished');
    update_post_meta($planId, $kIntegrityTs, 1234567890);
    delete_post_meta($planId, $kSuppress);

    $before = $captureState($planId);

    $enableResponse = $dispatchAjax('vms_save_event_plan_calendar_unpublished_suppress', array(
        'post_id' => $planId,
        'suppress' => '1',
    ));
    $assert(!empty($enableResponse['success']), 'Saving the suppressor ON should succeed.');

    $afterEnable = $captureState($planId);
    $assert($afterEnable['suppressor'] === '1', 'Saving the suppressor ON should set the suppressor meta.');
    $assert(wp_json_encode($before['core_details']) === wp_json_encode($afterEnable['core_details']), 'Saving the suppressor should not alter core details.');
    $assert(wp_json_encode($before['secondary_vendors']) === wp_json_encode($afterEnable['secondary_vendors']), 'Saving the suppressor should not alter Secondary Vendors.');
    $assert(wp_json_encode($before['staffing']) === wp_json_encode($afterEnable['staffing']), 'Saving the suppressor should not alter staffing data.');
    $assert(wp_json_encode($before['ticketing']) === wp_json_encode($afterEnable['ticketing']), 'Saving the suppressor should not alter ticketing overrides.');
    $assert(wp_json_encode($before['integrity']) === wp_json_encode($afterEnable['integrity']), 'Saving the suppressor should not alter integrity flags or timestamps.');

    $disableResponse = $dispatchAjax('vms_save_event_plan_calendar_unpublished_suppress', array(
        'post_id' => $planId,
        'suppress' => '0',
    ));
    $assert(!empty($disableResponse['success']), 'Saving the suppressor OFF should succeed.');

    $afterDisable = $captureState($planId);
    $assert($afterDisable['suppressor'] === '', 'Saving the suppressor OFF should clear the suppressor meta.');
    $assert(wp_json_encode($before['core_details']) === wp_json_encode($afterDisable['core_details']), 'Clearing the suppressor should not alter core details.');
    $assert(wp_json_encode($before['secondary_vendors']) === wp_json_encode($afterDisable['secondary_vendors']), 'Clearing the suppressor should not alter Secondary Vendors.');
    $assert(wp_json_encode($before['staffing']) === wp_json_encode($afterDisable['staffing']), 'Clearing the suppressor should not alter staffing data.');
    $assert(wp_json_encode($before['ticketing']) === wp_json_encode($afterDisable['ticketing']), 'Clearing the suppressor should not alter ticketing overrides.');
    $assert(wp_json_encode($before['integrity']) === wp_json_encode($afterDisable['integrity']), 'Clearing the suppressor should not alter integrity flags or timestamps.');

    $admin = (new ReflectionClass('VMS_Admin_Event_Plans'))->newInstanceWithoutConstructor();
    ob_start();
    $admin->render_event_plan_advanced_controls_host_meta_box(get_post($planId));
    $advancedHtml = (string) ob_get_clean();
    ob_start();
    vms_event_plan_editor_render_detached_forms();
    $detachedFormsHtml = (string) ob_get_clean();

    $assert(strpos($advancedHtml, 'name="vms_event_plan_action" value="resync_to_calendar"') === false, 'Re-sync to Calendar should no longer submit through the broad Event Plan save path.');
    $assert(strpos($advancedHtml, 'form="vms-event-plan-calendar-resync-' . $planId . '"') !== false, 'Re-sync to Calendar should submit through the detached resync form.');
    $assert(strpos($advancedHtml, 'Re-sync uses saved Event Plan data only.') !== false, 'Advanced Controls should render saved-state copy for the isolated Re-sync action.');
    $assert(strpos($advancedHtml, 'id="vms-calendar-unpublished-suppress"') !== false, 'Advanced Controls should still render the suppressor checkbox.');
    $assert(strpos($advancedHtml, 'id="vms-calendar-unpublished-suppress-save"') !== false, 'Advanced Controls should render the dedicated suppressor save button.');
    $assert(strpos($advancedHtml, 'data-save-nonce="') !== false, 'Advanced Controls should render the dedicated suppressor save nonce.');
    $assert(strpos($advancedHtml, 'name="vms_calendar_unpublished_suppress"') === false, 'Advanced Controls should no longer submit the suppressor through the broad Event Plan save path.');
    $assert(strpos($advancedHtml, '<form') === false, 'Advanced Controls should not introduce a nested form.');
    $assert(strpos($detachedFormsHtml, 'id="vms-event-plan-calendar-resync-' . $planId . '"') !== false, 'Detached Re-sync form should render in the admin footer output.');
    $assert(strpos($detachedFormsHtml, 'name="action" value="vms_resync_event_to_calendar"') !== false, 'Detached Re-sync form should target the isolated admin-post action.');
    $assert(strpos($detachedFormsHtml, 'name="_vms_resync_calendar_nonce"') !== false, 'Detached Re-sync form should include the dedicated resync nonce field.');
    $assert(strpos($detachedFormsHtml, 'name="post_id" value="' . $planId . '"') !== false, 'Detached Re-sync form should include the Event Plan ID only once as payload state.');
    $assert(strpos($detachedFormsHtml, 'name="source" value="advanced_controls"') !== false, 'Detached Re-sync form should mark the Advanced Controls source.');
    $assert(strpos($detachedFormsHtml, 'name="redirect_to"') !== false, 'Detached Re-sync form should include a redirect target back to the Event Plan editor.');

    fwrite(STDOUT, "event plan calendar unpublished suppress save regression: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan calendar unpublished suppress save regression: FAIL - ' . $e->getMessage() . "\n");
    $cleanup();
    exit(1);
}

$cleanup();
