<?php
declare(strict_types=1);

if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', true);
}
if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}

$wpLoad = dirname(__DIR__, 4) . '/wp-load.php';
if (!defined('ABSPATH')) {
    if (!file_exists($wpLoad)) {
        fwrite(STDERR, "Could not locate wp-load.php.\n");
        exit(1);
    }
    require_once $wpLoad;
}

if (!class_exists('VMS_Admin_Event_Plans')) {
    require_once dirname(__DIR__) . '/vendor-management-system.php';
}

final class VMS_Ticket_UI_Overrides_Ajax_Exit extends RuntimeException
{
}

function vms_ticket_ui_overrides_test_wp_die_handler($message = '', $title = '', $args = array()): void
{
    unset($title, $args);

    if (is_scalar($message)) {
        throw new VMS_Ticket_UI_Overrides_Ajax_Exit((string) $message);
    }

    throw new VMS_Ticket_UI_Overrides_Ajax_Exit('wp_die');
}

$assert = static function (bool $condition, string $message): void {
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
};

$createdPosts = array();
$createdTerms = array();
$originalPost = $_POST ?? array();
$originalGet = $_GET ?? array();
$originalRequest = $_REQUEST ?? array();
$dieHandlerFilter = null;

$registerPost = static function (int $postId) use (&$createdPosts): int {
    $createdPosts[] = $postId;
    return $postId;
};

$registerTerm = static function (array $termRow) use (&$createdTerms): array {
    $createdTerms[] = $termRow;
    return $termRow;
};

$cleanup = static function () use (&$createdPosts, &$createdTerms, &$originalPost, &$originalGet, &$originalRequest): void {
    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }
    foreach (array_reverse($createdTerms) as $termRow) {
        if (!empty($termRow['created']) && !empty($termRow['term_id'])) {
            wp_delete_term((int) $termRow['term_id'], 'vms_vendor_type');
        }
    }

    $_POST = $originalPost;
    $_GET = $originalGet;
    $_REQUEST = $originalRequest;
};

try {
    wp_set_current_user(1);
    $assert(current_user_can('edit_posts'), 'Expected test user to be able to edit posts.');
    $assert(false !== has_action('wp_ajax_vms_save_event_plan_ticket_ui_overrides'), 'Expected Ticket UI override AJAX action to remain registered.');

    $dieHandlerFilter = static function (): string {
        return 'vms_ticket_ui_overrides_test_wp_die_handler';
    };
    add_filter('wp_die_handler', $dieHandlerFilter);
    add_filter('wp_die_ajax_handler', $dieHandlerFilter);

    $ensureVendorType = static function (string $slug, string $name) use ($registerTerm): string {
        $existing = get_term_by('slug', $slug, 'vms_vendor_type');
        if ($existing instanceof WP_Term) {
            return (string) $existing->slug;
        }

        $created = wp_insert_term($name, 'vms_vendor_type', array('slug' => $slug));
        if (is_wp_error($created) || empty($created['term_id'])) {
            throw new RuntimeException('Failed to create test vendor type term: ' . $slug);
        }

        $registerTerm(array(
            'term_id' => (int) $created['term_id'],
            'created' => true,
        ));

        return $slug;
    };

    $createVendor = static function (string $title, string $typeSlug = '') use ($registerPost): int {
        $vendorId = wp_insert_post(array(
            'post_type' => 'vms_vendor',
            'post_status' => 'publish',
            'post_title' => $title,
        ), true);
        if (is_wp_error($vendorId) || (int) $vendorId <= 0) {
            throw new RuntimeException('Failed to create test vendor: ' . $title);
        }
        $vendorId = $registerPost((int) $vendorId);
        if ($typeSlug !== '') {
            wp_set_object_terms($vendorId, array($typeSlug), 'vms_vendor_type', false);
        }
        return $vendorId;
    };

    $createPlan = static function (string $title, string $content = '') use ($registerPost): int {
        $planId = wp_insert_post(array(
            'post_type' => 'vms_event_plan',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $content,
        ), true);
        if (is_wp_error($planId) || (int) $planId <= 0) {
            throw new RuntimeException('Failed to create test Event Plan.');
        }
        return $registerPost((int) $planId);
    };

    $normalizeValue = static function ($value) use (&$normalizeValue) {
        if (is_array($value)) {
            $isList = array_keys($value) === range(0, count($value) - 1);
            $next = array();
            foreach ($value as $key => $child) {
                $next[$key] = $normalizeValue($child);
            }
            if ($isList) {
                usort($next, static function ($left, $right): int {
                    return strcmp(wp_json_encode($left), wp_json_encode($right));
                });
            } else {
                ksort($next);
            }
            return $next;
        }

        return $value;
    };

    $dispatchAjax = static function (string $action, array $payload = array()) use ($assert): array {
        $prevPost = $_POST ?? array();
        $prevGet = $_GET ?? array();
        $prevRequest = $_REQUEST ?? array();

        $payload['action'] = $action;
        $payload['nonce'] = wp_create_nonce('vms_event_plan_ticket_ui_overrides_save');
        $_POST = $payload;
        $_GET = array();
        $_REQUEST = $payload;

        $bufferLevel = ob_get_level();
        $json = '';

        try {
            ob_start();
            try {
                do_action('wp_ajax_' . $action);
            } catch (VMS_Ticket_UI_Overrides_Ajax_Exit $e) {
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

    $runBroadSave = static function (int $planId, array $overrides = array()): void {
        $defaults = array(
            'vms_event_plan_details_nonce' => wp_create_nonce('vms_save_event_plan_details'),
            'post_ID' => $planId,
            'original_post_status' => 'publish',
            'vms_event_plan_action' => 'save_draft',
            'vms_staffing_lazy_unloaded' => '1',
            'vms_secondary_vendors_lazy_unloaded' => '1',
        );
        $_POST = array_merge($defaults, $overrides);
        $_GET = array();
        $_REQUEST = $_POST;

        $reflection = new ReflectionClass('VMS_Admin_Event_Plans');
        /** @var VMS_Admin_Event_Plans $admin */
        $admin = $reflection->newInstanceWithoutConstructor();
        $admin->save_event_plan_meta($planId, get_post($planId));
        clean_post_cache($planId);
    };

    $secondaryTypeSlug = $ensureVendorType('food_truck', 'Food Truck');
    $primaryVendorId = $createVendor('Ticket UI Primary Vendor');
    $secondaryVendorId = $createVendor('Ticket UI Secondary Vendor', $secondaryTypeSlug);

    $ticketConfigKey = function_exists('vms_ticketing_v2_k')
        ? (string) vms_ticketing_v2_k('config')
        : '_vms_ticketing_config_v2';
    $tecMetaKey = function_exists('vms_ticketing_meta_key')
        ? (string) vms_ticketing_meta_key('tec_event_id', '_vms_tec_event_id')
        : '_vms_tec_event_id';
    $suppressMetaKey = function_exists('vms_meta_key')
        ? (string) (vms_meta_key('event_plan', 'calendar_unpublished_suppress') ?: '_vms_calendar_unpublished_suppress')
        : '_vms_calendar_unpublished_suppress';
    $legacyTicketKeys = function_exists('vms_event_plan_legacy_ticket_meta_keys')
        ? (array) vms_event_plan_legacy_ticket_meta_keys()
        : array('_vms_price_ga', '_vms_enable_tables');

    $captureState = static function (int $planId) use ($ticketConfigKey, $tecMetaKey, $suppressMetaKey, $legacyTicketKeys, $normalizeValue): array {
        $secondaryIds = get_post_meta($planId, '_vms_secondary_vendor_ids', true);
        if (!is_array($secondaryIds)) {
            $secondaryIds = array();
        }
        $staffAssignments = get_post_meta($planId, '_vms_staff_assignments', true);
        if (!is_array($staffAssignments)) {
            $staffAssignments = array();
        }

        $legacyImportMeta = array(
            $tecMetaKey => get_post_meta($planId, $tecMetaKey, true),
        );
        foreach ($legacyTicketKeys as $legacyKey) {
            $legacyImportMeta[(string) $legacyKey] = get_post_meta($planId, (string) $legacyKey, true);
        }

        return $normalizeValue(array(
            'target_overrides' => array(
                'layout_override' => (string) get_post_meta($planId, '_vms_ticket_ui_layout_override', true),
                'addons_heading_override' => (string) get_post_meta($planId, '_vms_ticket_ui_addons_heading_override', true),
                'addons_subtext_override' => (string) get_post_meta($planId, '_vms_ticket_ui_addons_subtext_override', true),
                'help_tickets_override' => (string) get_post_meta($planId, '_vms_ticket_ui_help_tickets_override', true),
                'help_addons_override' => (string) get_post_meta($planId, '_vms_ticket_ui_help_addons_override', true),
            ),
            'core_details' => array(
                'title' => (string) get_post_field('post_title', $planId),
                'content' => (string) get_post_field('post_content', $planId),
                'event_date' => (string) get_post_meta($planId, '_vms_event_date', true),
                'start_time' => (string) get_post_meta($planId, '_vms_start_time', true),
                'end_time' => (string) get_post_meta($planId, '_vms_end_time', true),
                'venue_id' => (string) get_post_meta($planId, '_vms_venue_id', true),
                'band_vendor_id' => (string) get_post_meta($planId, '_vms_band_vendor_id', true),
            ),
            'secondary_vendors' => array(
                'type' => (string) get_post_meta($planId, '_vms_secondary_vendor_type', true),
                'ids' => array_values(array_unique(array_map('absint', $secondaryIds))),
                'index_ids' => array_values(array_unique(array_map('absint', (array) get_post_meta($planId, '_vms_secondary_vendor_id', false)))),
            ),
            'staffing' => array(
                'assignments' => $staffAssignments,
            ),
            'ticketing' => array(
                'enabled_override' => (string) get_post_meta($planId, '_vms_ticketing_enabled_override', true),
                'config' => get_post_meta($planId, $ticketConfigKey, true),
            ),
            'ga_image_legacy' => array(
                'mode' => (string) get_post_meta($planId, '_vms_ticketing_ga_image_mode', true),
                'image_id' => (string) get_post_meta($planId, '_vms_ticketing_ga_image_id', true),
            ),
            'calendar_resync' => array(
                'tec_event_id' => (string) get_post_meta($planId, $tecMetaKey, true),
                'tec_event_url' => (string) get_post_meta($planId, '_vms_tec_event_url', true),
                'maintenance_queued_at' => (string) get_post_meta($planId, '_vms_calendar_maintenance_queued_at', true),
                'maintenance_reason' => (string) get_post_meta($planId, '_vms_calendar_maintenance_reason', true),
            ),
            'unpublished_suppressor' => (string) get_post_meta($planId, $suppressMetaKey, true),
            'legacy_import_ticketing' => $legacyImportMeta,
        ));
    };

    $seedPlan = static function (int $planId, int $primaryVendorId, int $secondaryVendorId, string $secondaryTypeSlug) use ($ticketConfigKey, $tecMetaKey, $suppressMetaKey, $legacyTicketKeys): void {
        update_post_meta($planId, '_vms_event_date', '2026-06-12');
        update_post_meta($planId, '_vms_start_time', '19:00');
        update_post_meta($planId, '_vms_end_time', '22:00');
        update_post_meta($planId, '_vms_venue_id', '44');
        update_post_meta($planId, '_vms_band_vendor_id', $primaryVendorId);
        update_post_meta($planId, '_vms_secondary_vendor_type', $secondaryTypeSlug);
        update_post_meta($planId, '_vms_secondary_vendor_ids', array($secondaryVendorId));
        delete_post_meta($planId, '_vms_secondary_vendor_id');
        add_post_meta($planId, '_vms_secondary_vendor_id', $secondaryVendorId, false);
        update_post_meta($planId, '_vms_staff_assignments', array(
            97 => array(2443),
            85 => array(1248),
        ));

        update_post_meta($planId, '_vms_ticketing_enabled_override', 'on');
        update_post_meta($planId, '_vms_ticket_ui_layout_override', 'progressive');
        update_post_meta($planId, '_vms_ticket_ui_addons_heading_override', 'Fire Pits & Tables');
        update_post_meta($planId, '_vms_ticket_ui_addons_subtext_override', 'Upgrade your night with premium seating.');
        update_post_meta($planId, '_vms_ticket_ui_help_tickets_override', '<p>Existing ticket help.</p>');
        update_post_meta($planId, '_vms_ticket_ui_help_addons_override', '<p>Existing add-on help.</p>');

        update_post_meta($planId, $ticketConfigKey, array(
            'mode' => 'vms_managed',
            'tickets' => array(
                array(
                    'enabled' => true,
                    'ticket_key' => 'ga',
                    'title' => 'GA Ticket',
                ),
            ),
            'entitlements' => array(),
        ));
        update_post_meta($planId, '_vms_ticketing_ga_image_mode', 'custom');
        update_post_meta($planId, '_vms_ticketing_ga_image_id', '5678');
        update_post_meta($planId, $tecMetaKey, '9876');
        update_post_meta($planId, '_vms_tec_event_url', 'https://example.com/event');
        update_post_meta($planId, '_vms_calendar_maintenance_queued_at', '1234567890');
        update_post_meta($planId, '_vms_calendar_maintenance_reason', 'resync_to_calendar');
        update_post_meta($planId, $suppressMetaKey, '1');
        foreach (array_slice($legacyTicketKeys, 0, 3) as $index => $legacyKey) {
            update_post_meta($planId, (string) $legacyKey, 'legacy_value_' . ($index + 1));
        }
    };

    $planId = $createPlan('Ticket UI Overrides Isolation Test', 'Core content must not change.');
    $seedPlan($planId, $primaryVendorId, $secondaryVendorId, $secondaryTypeSlug);

    $beforeAjaxState = $captureState($planId);
    $ajaxPayload = array(
        'post_id' => $planId,
        'vms_ticket_ui_layout_override' => 'classic',
        'vms_ticket_ui_addons_heading_override' => 'Tables & Fire Pits',
        'vms_ticket_ui_addons_subtext_override' => 'Reserve add-ons before they sell out.',
        'vms_ticket_ui_help_tickets_override' => '<p>Updated ticket help copy.</p>',
        'vms_ticket_ui_help_addons_override' => '<p>Updated add-on help copy.</p>',
    );
    $ajaxResponse = $dispatchAjax('vms_save_event_plan_ticket_ui_overrides', $ajaxPayload);
    $assert(!empty($ajaxResponse['success']), 'Saving Ticket UI overrides through AJAX should succeed.');

    $changedFields = isset($ajaxResponse['data']['changed_fields']) && is_array($ajaxResponse['data']['changed_fields'])
        ? array_values(array_unique(array_map('sanitize_key', $ajaxResponse['data']['changed_fields'])))
        : array();
    sort($changedFields);
    $expectedChangedFields = array(
        'vms_ticket_ui_addons_heading_override',
        'vms_ticket_ui_addons_subtext_override',
        'vms_ticket_ui_help_addons_override',
        'vms_ticket_ui_help_tickets_override',
        'vms_ticket_ui_layout_override',
    );
    sort($expectedChangedFields);
    $assert($changedFields === $expectedChangedFields, 'The isolated AJAX save should report exactly the five target override fields as changed.');

    $afterAjaxState = $captureState($planId);
    $assert(wp_json_encode($afterAjaxState['target_overrides']) === wp_json_encode(array(
        'addons_heading_override' => 'Tables & Fire Pits',
        'addons_subtext_override' => 'Reserve add-ons before they sell out.',
        'help_addons_override' => '<p>Updated add-on help copy.</p>',
        'help_tickets_override' => '<p>Updated ticket help copy.</p>',
        'layout_override' => 'classic',
    )), 'The isolated AJAX save should persist the five override values.');
    $assert(wp_json_encode($beforeAjaxState['core_details']) === wp_json_encode($afterAjaxState['core_details']), 'Saving Ticket UI overrides should not alter core details.');
    $assert(wp_json_encode($beforeAjaxState['secondary_vendors']) === wp_json_encode($afterAjaxState['secondary_vendors']), 'Saving Ticket UI overrides should not alter Secondary Vendors.');
    $assert(wp_json_encode($beforeAjaxState['staffing']) === wp_json_encode($afterAjaxState['staffing']), 'Saving Ticket UI overrides should not alter staffing data.');
    $assert(wp_json_encode($beforeAjaxState['ticketing']) === wp_json_encode($afterAjaxState['ticketing']), 'Saving Ticket UI overrides should not alter ticketing config or the enabled override.');
    $assert(wp_json_encode($beforeAjaxState['ga_image_legacy']) === wp_json_encode($afterAjaxState['ga_image_legacy']), 'Saving Ticket UI overrides should not alter GA image legacy meta.');
    $assert(wp_json_encode($beforeAjaxState['calendar_resync']) === wp_json_encode($afterAjaxState['calendar_resync']), 'Saving Ticket UI overrides should not alter calendar resync metadata.');
    $assert(wp_json_encode($beforeAjaxState['unpublished_suppressor']) === wp_json_encode($afterAjaxState['unpublished_suppressor']), 'Saving Ticket UI overrides should not alter the unpublished-calendar suppressor.');
    $assert(wp_json_encode($beforeAjaxState['legacy_import_ticketing']) === wp_json_encode($afterAjaxState['legacy_import_ticketing']), 'Saving Ticket UI overrides should not alter legacy/import ticketing meta.');

    $beforeBroadGuardState = $captureState($planId);
    $runBroadSave($planId, array(
        'vms_ticket_ui_layout_override' => 'v2',
        'vms_ticket_ui_addons_heading_override' => 'Stale Broad Save Heading',
        'vms_ticket_ui_addons_subtext_override' => 'Stale broad save subtext.',
        'vms_ticket_ui_help_tickets_override' => '<p>Stale broad save ticket help.</p>',
        'vms_ticket_ui_help_addons_override' => '<p>Stale broad save add-on help.</p>',
    ));
    $afterBroadGuardState = $captureState($planId);
    $assert(wp_json_encode($beforeBroadGuardState['target_overrides']) === wp_json_encode($afterBroadGuardState['target_overrides']), 'The broad Event Plan save should ignore the five Ticket UI override fields without explicit save intent.');

    $beforeBroadIntentState = $captureState($planId);
    $runBroadSave($planId, array(
        'vms_ticket_ui_overrides_save_intent' => '1',
        'vms_ticket_ui_layout_override' => 'v2',
        'vms_ticket_ui_addons_heading_override' => 'Explicit Broad Save Heading',
        'vms_ticket_ui_addons_subtext_override' => 'Explicit broad save subtext.',
        'vms_ticket_ui_help_tickets_override' => '<p>Explicit broad save ticket help.</p>',
        'vms_ticket_ui_help_addons_override' => '<p>Explicit broad save add-on help.</p>',
    ));
    $afterBroadIntentState = $captureState($planId);
    $assert(wp_json_encode($afterBroadIntentState['target_overrides']) === wp_json_encode(array(
        'addons_heading_override' => 'Explicit Broad Save Heading',
        'addons_subtext_override' => 'Explicit broad save subtext.',
        'help_addons_override' => '<p>Explicit broad save add-on help.</p>',
        'help_tickets_override' => '<p>Explicit broad save ticket help.</p>',
        'layout_override' => 'v2',
    )), 'The explicit compatibility save-intent marker should allow the five Ticket UI overrides to save through the shared helper.');
    $assert(wp_json_encode($beforeBroadIntentState['core_details']) === wp_json_encode($afterBroadIntentState['core_details']), 'The compatibility fallback should still leave core details unchanged.');
    $assert(wp_json_encode($beforeBroadIntentState['secondary_vendors']) === wp_json_encode($afterBroadIntentState['secondary_vendors']), 'The compatibility fallback should still leave Secondary Vendors unchanged.');
    $assert(wp_json_encode($beforeBroadIntentState['staffing']) === wp_json_encode($afterBroadIntentState['staffing']), 'The compatibility fallback should still leave staffing unchanged.');
    $assert(wp_json_encode($beforeBroadIntentState['ticketing']) === wp_json_encode($afterBroadIntentState['ticketing']), 'The compatibility fallback should still leave ticket config and ticketing enabled override unchanged.');
    $assert(wp_json_encode($beforeBroadIntentState['ga_image_legacy']) === wp_json_encode($afterBroadIntentState['ga_image_legacy']), 'The compatibility fallback should still leave GA image legacy meta unchanged.');
    $assert(wp_json_encode($beforeBroadIntentState['calendar_resync']) === wp_json_encode($afterBroadIntentState['calendar_resync']), 'The compatibility fallback should still leave calendar resync metadata unchanged.');
    $assert(wp_json_encode($beforeBroadIntentState['unpublished_suppressor']) === wp_json_encode($afterBroadIntentState['unpublished_suppressor']), 'The compatibility fallback should still leave the unpublished-calendar suppressor unchanged.');
    $assert(wp_json_encode($beforeBroadIntentState['legacy_import_ticketing']) === wp_json_encode($afterBroadIntentState['legacy_import_ticketing']), 'The compatibility fallback should still leave legacy/import ticketing meta unchanged.');

    $reflection = new ReflectionClass('VMS_Admin_Event_Plans');
    /** @var VMS_Admin_Event_Plans $admin */
    $admin = $reflection->newInstanceWithoutConstructor();
    $prevGet = $_GET ?? array();
    $_GET['vms_ep_load_section'] = 'ticketing_v2';
    ob_start();
    $admin->render_event_plan_ticketing_v2_host_meta_box(get_post($planId));
    $ticketingHtml = (string) ob_get_clean();
    $_GET = $prevGet;

    $assert(strpos($ticketingHtml, 'Save public UI overrides') !== false, 'Ticketing metabox should render the detached Ticket UI override save button.');
    $assert(strpos($ticketingHtml, 'These public UI/help text overrides save separately from ticket configuration.') !== false, 'Ticketing metabox should render the detached save helper text.');
    $assert(strpos($ticketingHtml, 'name="vms_ticket_ui_overrides_save_intent"') !== false, 'Ticketing metabox should render the explicit compatibility save-intent marker.');

    $adminTicketingJs = (string) file_get_contents(dirname(__DIR__) . '/assets/admin-ticketing.js');
    $assert(strpos($adminTicketingJs, "vms_save_event_plan_ticket_ui_overrides") !== false, 'admin-ticketing.js should call the isolated Ticket UI override AJAX action.');
    $assert(strpos($adminTicketingJs, "ensureTicketUiOverridesReadyForAction('Save config')") !== false, 'Save config should save dirty Ticket UI overrides before continuing.');
    $assert(strpos($adminTicketingJs, "ensureTicketUiOverridesReadyForAction('Preview sync')") !== false, 'Preview sync should save dirty Ticket UI overrides before continuing.');
    $assert(strpos($adminTicketingJs, "ensureTicketUiOverridesReadyForAction('Commit sync')") !== false, 'Commit sync should save dirty Ticket UI overrides before continuing.');
    $assert(strpos($adminTicketingJs, 'Public ticket UI overrides have unsaved changes. Use Save public UI overrides before updating the Event Plan.') !== false, 'admin-ticketing.js should block the main Event Plan submit with the required Ticket UI override warning.');
    $assert(strpos($adminTicketingJs, 'bindTicketUiOverrideMainFormGuard') !== false, 'admin-ticketing.js should bind a dedicated main form guard for dirty Ticket UI overrides.');
    $assert(strpos($adminTicketingJs, 'focusTicketUiOverridesSaveControl') !== false, 'admin-ticketing.js should focus or scroll to the detached Ticket UI override save control when blocking the main form submit.');

    $ticketingBootstrapPhp = (string) file_get_contents(dirname(__DIR__) . '/includes/integrations/ticketing.php');
    $assert(strpos($ticketingBootstrapPhp, "ticketUiOverridesNonce") !== false, 'Ticketing bootstrap should localize the dedicated Ticket UI override nonce.');
    $assert(strpos($ticketingBootstrapPhp, "vms_event_plan_ticket_ui_overrides_save") !== false, 'Ticketing bootstrap should use the dedicated Ticket UI override nonce action.');

    fwrite(STDOUT, "Ticket UI override isolation test passed.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Ticket UI override isolation test failed: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    if ($dieHandlerFilter !== null) {
        remove_filter('wp_die_handler', $dieHandlerFilter);
        remove_filter('wp_die_ajax_handler', $dieHandlerFilter);
    }
}

$cleanup();
exit(0);
