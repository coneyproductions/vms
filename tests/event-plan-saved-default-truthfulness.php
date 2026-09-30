<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

$pluginRootEnv = getenv('BVMGR_TEST_PLUGIN_ROOT');
$pluginRoot = is_string($pluginRootEnv) && $pluginRootEnv !== '' ? realpath($pluginRootEnv) : dirname(__DIR__);
if (!is_string($pluginRoot) || !is_dir($pluginRoot)) {
    throw new RuntimeException('BVMGR_TEST_PLUGIN_ROOT must identify the exact plugin package under test.');
}
if (!class_exists('BVMGR_Admin_Event_Plans')) {
    $withoutInstalledBvm = static function ($plugins): array {
        return array_values(array_filter((array) $plugins, static function ($plugin): bool {
            return basename((string) $plugin) !== 'backstage-venue-manager.php';
        }));
    };
    add_filter('option_active_plugins', $withoutInstalledBvm);
    require_once $pluginRoot . '/backstage-venue-manager.php';
    remove_filter('option_active_plugins', $withoutInstalledBvm);
}
if (!function_exists('bvmgr_get_current_venue_id')) {
    require_once $pluginRoot . '/includes/admin/venue-context.php';
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion ' . $assertions . ' failed: ' . $message);
    }
};

$createdPosts = array();
$originalGet = $_GET ?? array();
$originalPost = $_POST ?? array();
$originalRequest = $_REQUEST ?? array();
$originalUserId = get_current_user_id();
$adminUserIds = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
$adminUserId = !empty($adminUserIds) ? (int) $adminUserIds[0] : 1;
$venueContextKey = defined('BVMGR_SCH_CURRENT_VENUE_META_KEY') ? (string) BVMGR_SCH_CURRENT_VENUE_META_KEY : '_vms_current_venue_id';
$venueContextExisted = metadata_exists('user', $adminUserId, $venueContextKey);
$originalVenueContext = get_user_meta($adminUserId, $venueContextKey, true);

$registerPost = static function (int $postId) use (&$createdPosts): int {
    $createdPosts[] = $postId;
    return $postId;
};

$selectHtml = static function (string $html, string $id): string {
    if (preg_match('~<select\b[^>]*\bid="' . preg_quote($id, '~') . '"[^>]*>.*?</select>~s', $html, $match) !== 1) {
        throw new RuntimeException('Could not isolate select #' . $id . '.');
    }
    return (string) $match[0];
};

$cleanup = static function () use (&$createdPosts, $originalGet, $originalPost, $originalRequest, $originalUserId, $adminUserId, $venueContextKey, $venueContextExisted, $originalVenueContext): void {
    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }
    if ($venueContextExisted) {
        update_user_meta($adminUserId, $venueContextKey, $originalVenueContext);
    } else {
        delete_user_meta($adminUserId, $venueContextKey);
    }
    wp_set_current_user($originalUserId);
    $_GET = $originalGet;
    $_POST = $originalPost;
    $_REQUEST = $originalRequest;
};

try {
    wp_set_current_user($adminUserId);
    $assert(current_user_can('edit_posts'), 'Expected an administrator fixture user.');
    foreach (array('vms_event_plan', 'vms_venue', 'vms_vendor') as $postType) {
        if (!post_type_exists($postType)) {
            register_post_type($postType, array('public' => false, 'show_ui' => true, 'label' => $postType));
        }
    }

    $venueId = wp_insert_post(array(
        'post_type' => 'vms_venue',
        'post_status' => 'publish',
        'post_title' => 'Saved-default truthfulness venue',
    ), true);
    $assert(!is_wp_error($venueId) && (int) $venueId > 0, 'Failed to create Venue fixture.');
    $venueId = $registerPost((int) $venueId);
    update_post_meta($venueId, '_vms_default_start_time', '18:30');
    update_post_meta($venueId, '_vms_default_end_time', '22:30');
    update_user_meta($adminUserId, $venueContextKey, (string) $venueId);
    $assert((int) bvmgr_get_current_venue_id() === $venueId, 'Current Venue fixture did not resolve through the canonical helper: ' . (string) bvmgr_get_current_venue_id());

    $vendorId = wp_insert_post(array(
        'post_type' => 'vms_vendor',
        'post_status' => 'publish',
        'post_title' => 'Saved-default truthfulness vendor',
    ), true);
    $assert(!is_wp_error($vendorId) && (int) $vendorId > 0, 'Failed to create Primary Vendor fixture.');
    $vendorId = $registerPost((int) $vendorId);

    $planId = wp_insert_post(array(
        'post_type' => 'vms_event_plan',
        'post_status' => 'auto-draft',
        'post_title' => 'Saved-default truthfulness auto-draft',
    ), true);
    $assert(!is_wp_error($planId) && (int) $planId > 0, 'Failed to create Event Plan auto-draft fixture.');
    $planId = $registerPost((int) $planId);
    update_post_meta($planId, '_vms_band_vendor_id', $vendorId);
    $plan = get_post($planId);
    $assert($plan instanceof WP_Post && $plan->post_status === 'auto-draft', 'Fixture must remain a brand-new auto-draft.');

    foreach (array('_vms_venue_id', '_vms_start_time', '_vms_end_time') as $metaKey) {
        $assert(!metadata_exists('post', $planId, $metaKey), 'Fixture unexpectedly stored ' . $metaKey . ' before render.');
    }

    $_GET = array();
    $_POST = array();
    $_REQUEST = array();
    $controller = new BVMGR_Admin_Event_Plans();
    ob_start();
    $controller->render_event_plan_details_meta_box($plan);
    $html = (string) ob_get_clean();

    $venueHtml = $selectHtml($html, 'vms_venue_id');
    $startHtml = $selectHtml($html, 'vms_start_time');
    $endHtml = $selectHtml($html, 'vms_end_time');
    $assert(strpos($venueHtml, 'data-vms-persisted-state=""') !== false, 'Missing Venue meta must render an empty persisted-state baseline.');
    $assert(preg_match('~<option\s+value="' . $venueId . '"[^>]*selected~', $venueHtml) === 1, 'Current Venue fallback must remain visibly selected: ' . $venueHtml);
    $assert(strpos($startHtml, 'data-vms-persisted-state=""') !== false && preg_match('~<option\s+value="18:30"\s+selected~', $startHtml) === 1, 'Missing Start Time must expose an empty baseline while rendering its default.');
    $assert(strpos($endHtml, 'data-vms-persisted-state=""') !== false && preg_match('~<option\s+value="22:30"\s+selected~', $endHtml) === 1, 'Missing End Time must expose an empty baseline while rendering its default.');

    foreach (array('_vms_venue_id', '_vms_start_time', '_vms_end_time') as $metaKey) {
        $assert(!metadata_exists('post', $planId, $metaKey), 'Opening the editor persisted rendered default ' . $metaKey . '.');
    }
    $beforeCodes = array_column(bvmgr_event_plan_mark_ready_blockers($planId), 'code');
    $assert(in_array('missing_venue', $beforeCodes, true), 'Readiness must continue treating the rendered Venue fallback as canonically missing.');
    $assert(in_array('missing_start_end_time', $beforeCodes, true), 'Readiness must continue treating rendered time defaults as canonically missing.');

    $saveMethod = new ReflectionMethod(BVMGR_Admin_Event_Plans::class, 'run_event_plan_scoped_save');
    $saveMethod->setAccessible(true);
    $saveSection = static function (string $section, array $request) use ($controller, $saveMethod, $planId): array {
        $filtered = bvmgr_event_plan_filter_section_request($section, $request);
        $result = $saveMethod->invoke($controller, $planId, $section, $filtered);
        return is_array($result) ? $result : array();
    };

    $basicsResult = $saveSection('basics', array(
        'vms_event_date' => '2027-05-15',
        'vms_venue_id' => (string) $venueId,
    ));
    $assert(!empty($basicsResult['ok']) && !empty($basicsResult['verified']), 'Explicit Event Details save must verify the rendered Venue fallback.');
    $scheduleResult = $saveSection('schedule', array(
        'vms_start_time' => '18:30',
        'vms_end_time' => '22:30',
        'vms_band_vendor_id' => (string) $vendorId,
        'vms_lineup_present' => '1',
        'vms_lineup_entries' => array(
            'primary' => array(
                'role' => 'primary',
                'sort_order' => '0',
                'vendor_id' => (string) $vendorId,
                'set_start' => '',
                'set_end' => '',
            ),
        ),
    ));
    $assert(!empty($scheduleResult['ok']) && !empty($scheduleResult['verified']), 'Explicit Schedule save must verify the rendered time defaults: ' . wp_json_encode($scheduleResult));
    $assert((int) get_post_meta($planId, '_vms_venue_id', true) === $venueId, 'Explicit save did not persist Venue.');
    $assert((string) get_post_meta($planId, '_vms_start_time', true) === '18:30', 'Explicit save did not persist Start Time.');
    $assert((string) get_post_meta($planId, '_vms_end_time', true) === '22:30', 'Explicit save did not persist End Time.');

    $savedPlan = get_post($planId);
    $assert($savedPlan instanceof WP_Post, 'Saved Event Plan fixture disappeared.');
    ob_start();
    $controller->render_event_plan_details_meta_box($savedPlan);
    $savedHtml = (string) ob_get_clean();
    $savedVenueHtml = $selectHtml($savedHtml, 'vms_venue_id');
    $savedStartHtml = $selectHtml($savedHtml, 'vms_start_time');
    $savedEndHtml = $selectHtml($savedHtml, 'vms_end_time');
    $assert(strpos($savedVenueHtml, 'data-vms-persisted-state="' . $venueId . '"') !== false, 'Persisted Venue baseline must match its rendered value after save.');
    $assert(strpos($savedStartHtml, 'data-vms-persisted-state="18:30"') !== false, 'Persisted Start Time baseline must match its rendered value after save.');
    $assert(strpos($savedEndHtml, 'data-vms-persisted-state="22:30"') !== false, 'Persisted End Time baseline must match its rendered value after save.');
    $afterCodes = array_column(bvmgr_event_plan_mark_ready_blockers($planId), 'code');
    $assert(!in_array('missing_venue', $afterCodes, true), 'Readiness must see the explicitly persisted Venue.');
    $assert(!in_array('missing_start_end_time', $afterCodes, true), 'Readiness must see the explicitly persisted times.');

    $eventPlansSource = (string) file_get_contents($pluginRoot . '/includes/cpt/event-plans.php');
    $assert(strpos($eventPlansSource, "'ticketing_v2' => array(") !== false, 'Ticketing registry must remain present and untouched by saved-default handling.');

    fwrite(STDOUT, 'PASS: ' . $assertions . " Event Plan saved-default truthfulness assertions.\n");
} finally {
    $cleanup();
}
