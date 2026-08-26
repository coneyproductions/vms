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

if (!class_exists('BVMGR_Admin_Event_Plans')) {
    require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

final class VMS_Legacy_Ticketing_Ajax_Exit extends RuntimeException
{
}

function vms_legacy_ticketing_test_wp_die_handler($message = '', $title = '', $args = array()): void
{
    unset($title, $args);

    if (is_scalar($message)) {
        throw new VMS_Legacy_Ticketing_Ajax_Exit((string) $message);
    }

    throw new VMS_Legacy_Ticketing_Ajax_Exit('wp_die');
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

    $assert(post_type_exists('tribe_events'), 'Expected The Events Calendar post type to be available.');
    $assert(class_exists('WooCommerce') && function_exists('wc_get_product'), 'Expected WooCommerce to be available.');
    $assert(current_user_can('edit_posts'), 'Expected test user to be able to edit posts.');

    $ajaxMap = array(
        'vms_ticketing_search_tec_events' => 'vms_ticketing_ajax_search_tec_events',
        'vms_ticketing_link_tec_event' => 'vms_ticketing_ajax_link_tec_event',
        'vms_ticketing_unlink_tec_event' => 'vms_ticketing_ajax_unlink_tec_event',
        'vms_ticketing_refresh_stats' => 'vms_ticketing_ajax_refresh_stats',
        'vms_ticketing_search_products' => 'vms_ticketing_ajax_search_products',
        'vms_ticketing_attach_product' => 'vms_ticketing_ajax_attach_product',
        'vms_ticketing_detach_product' => 'vms_ticketing_ajax_detach_product',
    );
    foreach ($ajaxMap as $action => $callback) {
        $assert(false !== has_action('wp_ajax_' . $action, $callback), 'Expected AJAX action hook to remain registered: ' . $action);
    }

    $dieHandlerFilter = static function (): string {
        return 'vms_legacy_ticketing_test_wp_die_handler';
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

        $planId = $registerPost((int) $planId);
        update_post_meta($planId, '_vms_event_date', '2026-06-12');
        update_post_meta($planId, '_vms_start_time', '19:00');
        update_post_meta($planId, '_vms_end_time', '21:00');

        return $planId;
    };

    $createTecEvent = static function (string $title) use ($registerPost): int {
        $eventId = wp_insert_post(array(
            'post_type' => 'tribe_events',
            'post_status' => 'publish',
            'post_title' => $title,
        ), true);
        if (is_wp_error($eventId) || (int) $eventId <= 0) {
            throw new RuntimeException('Failed to create test TEC event.');
        }

        $eventId = $registerPost((int) $eventId);
        update_post_meta($eventId, '_EventStartDate', '2026-06-12 19:00:00');

        return $eventId;
    };

    $createProduct = static function (string $title) use ($registerPost): int {
        $product = new WC_Product_Simple();
        $product->set_name($title);
        $product->set_status('publish');
        $product->set_regular_price('25.00');
        $product->set_price('25.00');
        $productId = (int) $product->save();
        if ($productId <= 0) {
            throw new RuntimeException('Failed to create test WooCommerce product.');
        }

        $registerPost($productId);
        update_post_meta($productId, 'total_sales', 3);

        return $productId;
    };

    $dispatchAjaxResponse = static function (string $action, array $payload = array(), ?callable $dispatcher = null) use ($assert): array {
        $prevPost = $_POST ?? array();
        $prevGet = $_GET ?? array();
        $prevRequest = $_REQUEST ?? array();
        $hadAjaxCaptureState = array_key_exists('vms_ajax_ob_started', $GLOBALS);
        $prevAjaxCaptureState = $hadAjaxCaptureState ? $GLOBALS['bvmgr_ajax_ob_started'] : null;
        $payload['action'] = $action;
        $payload['nonce'] = wp_create_nonce('vms_ticketing_nonce');
        $_POST = $payload;
        $_GET = array();
        $_REQUEST = $payload;

        $bufferLevel = ob_get_level();
        $collectorLevel = 0;
        $rawOutput = '';
        if ($dispatcher === null) {
            $dispatcher = static function (string $hook): void {
                do_action($hook);
            };
        }

        try {
            ob_start(static function (string $chunk) use (&$rawOutput): string {
                $rawOutput .= $chunk;
                return '';
            });
            $collectorLevel = ob_get_level();

            // Keep one throwaway buffer above the collector so stale runtime flags
            // can close it without discarding the actual dispatch output.
            ob_start();
            try {
                $dispatcher('wp_ajax_' . $action);
            } catch (VMS_Legacy_Ticketing_Ajax_Exit $e) {
                unset($e);
            }

            while (ob_get_level() > $collectorLevel) {
                ob_end_flush();
            }
            if (ob_get_level() === $collectorLevel) {
                ob_end_flush();
            }
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            if ($hadAjaxCaptureState) {
                $GLOBALS['bvmgr_ajax_ob_started'] = $prevAjaxCaptureState;
            } else {
                unset($GLOBALS['bvmgr_ajax_ob_started']);
            }
            $_POST = $prevPost;
            $_GET = $prevGet;
            $_REQUEST = $prevRequest;
        }

        $trimmedOutput = trim($rawOutput);
        $decoded = json_decode($trimmedOutput, true);
        $assert(is_array($decoded), 'Failed to decode AJAX JSON for ' . $action . '. Raw output: ' . substr($trimmedOutput, 0, 200));

        return array(
            'decoded' => $decoded,
            'raw' => $rawOutput,
            'raw_trimmed' => $trimmedOutput,
            'buffer_level_before' => $bufferLevel,
            'buffer_level_after' => ob_get_level(),
        );
    };

    $dispatchAjax = static function (string $action, array $payload = array(), ?callable $dispatcher = null) use ($dispatchAjaxResponse): array {
        return $dispatchAjaxResponse($action, $payload, $dispatcher)['decoded'];
    };

    $assertAjaxDecodeFailure = static function (string $label, callable $callback) use ($assert): void {
        try {
            $callback();
        } catch (RuntimeException $e) {
            $assert(strpos($e->getMessage(), 'Failed to decode AJAX JSON') !== false, 'Expected a JSON decode failure for ' . $label . '.');
            return;
        }

        throw new RuntimeException('Expected ' . $label . ' to fail JSON decoding.');
    };

    $probeStdout = '';
    ob_start();
    try {
        $probeResponse = $dispatchAjaxResponse('vms_ticketing_capture_probe_valid', array(), static function (): void {
            echo '{"success":true,"data":{"probe":"alpha"}}';
            throw new VMS_Legacy_Ticketing_Ajax_Exit('');
        });
    } finally {
        $probeStdout = (string) ob_get_clean();
    }
    $assert($probeStdout === '', 'dispatchAjax should not leak captured JSON to stdout.');
    $assert(($probeResponse['decoded']['data']['probe'] ?? '') === 'alpha', 'dispatchAjax should return the decoded JSON payload.');
    $assert($probeResponse['buffer_level_before'] === $probeResponse['buffer_level_after'], 'dispatchAjax should restore the starting output-buffer level.');

    $firstProbeResponse = $dispatchAjaxResponse('vms_ticketing_capture_probe_first', array(), static function (): void {
        echo '{"success":true,"data":{"probe":"first"}}';
        throw new VMS_Legacy_Ticketing_Ajax_Exit('');
    });
    $secondProbeResponse = $dispatchAjaxResponse('vms_ticketing_capture_probe_second', array(), static function (): void {
        echo '{"success":true,"data":{"probe":"second"}}';
        throw new VMS_Legacy_Ticketing_Ajax_Exit('');
    });
    $assert(($firstProbeResponse['decoded']['data']['probe'] ?? '') === 'first', 'The first sequential dispatch should capture its own JSON response.');
    $assert(($secondProbeResponse['decoded']['data']['probe'] ?? '') === 'second', 'The second sequential dispatch should capture its own JSON response.');
    $assert($firstProbeResponse['raw_trimmed'] !== $secondProbeResponse['raw_trimmed'], 'Sequential dispatches should capture distinct response bodies.');

    $assertAjaxDecodeFailure('empty AJAX response', static function () use ($dispatchAjaxResponse): void {
        $dispatchAjaxResponse('vms_ticketing_capture_probe_empty', array(), static function (): void {
            throw new VMS_Legacy_Ticketing_Ajax_Exit('');
        });
    });
    $assertAjaxDecodeFailure('malformed AJAX response', static function () use ($dispatchAjaxResponse): void {
        $dispatchAjaxResponse('vms_ticketing_capture_probe_malformed', array(), static function (): void {
            echo '{bad json';
            throw new VMS_Legacy_Ticketing_Ajax_Exit('');
        });
    });

    $staleCaptureStdout = '';
    ob_start();
    try {
        $staleCaptureResponse = $dispatchAjaxResponse('vms_ticketing_capture_probe_stale', array(), static function (): void {
            vms_ticketing_ajax_send_success(array('probe' => 'stale'));
        });
    } finally {
        $staleCaptureStdout = (string) ob_get_clean();
    }
    $assert($staleCaptureStdout === '', 'Stale AJAX capture state should not leak JSON to stdout.');
    $assert(($staleCaptureResponse['decoded']['data']['probe'] ?? '') === 'stale', 'Stale AJAX capture state should still return decoded JSON.');
    $assert($staleCaptureResponse['raw_trimmed'] !== '', 'Stale AJAX capture state should not produce empty decode input.');

    $buildLegacyIdentifierString = static function (int $tecId): string {
        if ($tecId <= 0 || !function_exists('vms_ticketing_get_tec_legacy_identifiers')) {
            return '';
        }

        $parts = array();
        foreach ((array) vms_ticketing_get_tec_legacy_identifiers($tecId) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            $value = isset($row['value']) ? trim((string) $row['value']) : '';
            if ($label !== '' && $value !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }

        return implode(' · ', $parts);
    };

    $uniqueSuffix = (string) wp_generate_password(8, false, false);
    $planId = $createPlan('Legacy Ticketing Smoke Plan ' . $uniqueSuffix);
    $tecId = $createTecEvent('Legacy Ticketing Smoke Event ' . $uniqueSuffix);
    $productId = $createProduct('Legacy Ticketing Smoke Product ' . $uniqueSuffix);

    $searchTecResponse = $dispatchAjax('vms_ticketing_search_tec_events', array(
        'q' => 'Smoke Event ' . $uniqueSuffix,
    ));
    $assert(!empty($searchTecResponse['success']), 'TEC search should succeed.');
    $tecItems = isset($searchTecResponse['data']['items']) && is_array($searchTecResponse['data']['items']) ? $searchTecResponse['data']['items'] : array();
    $matchedTec = array_filter($tecItems, static function ($row) use ($tecId): bool {
        return absint($row['wp_id'] ?? ($row['id'] ?? 0)) === $tecId;
    });
    $assert(!empty($matchedTec), 'TEC search should return the created event.');

    $linkResponse = $dispatchAjax('vms_ticketing_link_tec_event', array(
        'plan_id' => $planId,
        'tec_event_id' => $tecId,
    ));
    $assert(!empty($linkResponse['success']), 'TEC link should succeed.');
    $tecMetaKey = function_exists('vms_ticketing_meta_key') ? vms_ticketing_meta_key('tec_event_id', '_vms_tec_event_id') : '_vms_tec_event_id';
    $assert((int) get_post_meta($planId, $tecMetaKey, true) === $tecId, 'Linked TEC event meta should be stored on the Event Plan.');

    $searchProductResponse = $dispatchAjax('vms_ticketing_search_products', array(
        'q' => 'Smoke Product ' . $uniqueSuffix,
    ));
    $assert(!empty($searchProductResponse['success']), 'Woo product search should succeed.');
    $productItems = isset($searchProductResponse['data']['items']) && is_array($searchProductResponse['data']['items']) ? $searchProductResponse['data']['items'] : array();
    $matchedProduct = array_filter($productItems, static function ($row) use ($productId): bool {
        return absint($row['id'] ?? 0) === $productId;
    });
    $assert(!empty($matchedProduct), 'Woo product search should return the created product.');

    $attachResponse = $dispatchAjax('vms_ticketing_attach_product', array(
        'plan_id' => $planId,
        'product_id' => $productId,
    ));
    $assert(!empty($attachResponse['success']), 'Manual Woo product attach should succeed.');
    $attachedIds = isset($attachResponse['data']['manual_product_ids']) && is_array($attachResponse['data']['manual_product_ids'])
        ? array_map('absint', $attachResponse['data']['manual_product_ids'])
        : array();
    $assert(in_array($productId, $attachedIds, true), 'Attached product list should include the created product.');

    $refreshResponse = $dispatchAjax('vms_ticketing_refresh_stats', array(
        'plan_id' => $planId,
    ));
    $assert(!empty($refreshResponse['success']), 'Ticket stats refresh should succeed.');
    $refreshedTicketIds = isset($refreshResponse['data']['ticket_product_ids']) && is_array($refreshResponse['data']['ticket_product_ids'])
        ? array_map('absint', $refreshResponse['data']['ticket_product_ids'])
        : array();
    $assert(in_array($productId, $refreshedTicketIds, true), 'Refreshed ticket product IDs should include the attached manual Woo product.');
    $refreshStats = isset($refreshResponse['data']['stats']) && is_array($refreshResponse['data']['stats']) ? $refreshResponse['data']['stats'] : array();
    $assert(isset($refreshStats['provider']) && $refreshStats['provider'] !== '', 'Refresh stats response should include a provider.');

    $admin = (new ReflectionClass('BVMGR_Admin_Event_Plans'))->newInstanceWithoutConstructor();
    ob_start();
    $admin->render_event_plan_advanced_controls_host_meta_box(get_post($planId));
    $advancedHtml = (string) ob_get_clean();

    $assert($advancedHtml !== '', 'Advanced Controls metabox should render.');
    $assert(strpos($advancedHtml, 'Re-sync to Calendar') !== false, 'Advanced Controls render should still include Re-sync to Calendar.');
    $assert(strpos($advancedHtml, 'id="vms-calendar-unpublished-suppress"') !== false, 'Advanced Controls render should still include the unpublished-calendar suppressor control.');
    $assert(strpos($advancedHtml, 'id="vms-calendar-unpublished-suppress-save"') !== false, 'Advanced Controls render should still include the dedicated suppressor save button.');
    $assert(strpos($advancedHtml, 'name="vms_calendar_unpublished_suppress"') === false, 'Advanced Controls render should not submit the suppressor through the broad Event Plan save path.');
    $assert(strpos($advancedHtml, 'id="vms-ticketing-v2-editor"') === false, 'Advanced Controls render should still omit the Ticketing v2 editor.');

    $capturePartial = Closure::bind(
        function (string $partial, array $vars = array()): string {
            return $this->capture_event_plan_partial($partial, $vars);
        },
        $admin,
        'BVMGR_Admin_Event_Plans'
    );

    $ticketMetaKey = function_exists('vms_ticketing_meta_key') ? vms_ticketing_meta_key('ticket_product_ids', '_vms_ticket_product_ids_v1') : '_vms_ticket_product_ids_v1';
    $ticketStatsKey = function_exists('vms_ticketing_meta_key') ? vms_ticketing_meta_key('ticket_stats', '_vms_ticket_stats_v1') : '_vms_ticket_stats_v1';
    $legacyHtml = (string) $capturePartial('legacy-imported-ticketing-integration', array(
        'post' => get_post($planId),
        'linked_tec_id' => $tecId,
        'linked_tec_title' => (string) get_the_title($tecId),
        'linked_tec_legacy_str' => $buildLegacyIdentifierString($tecId),
        'ticket_stats' => (array) get_post_meta($planId, $ticketStatsKey, true),
        'ticket_pids' => (array) get_post_meta($planId, $ticketMetaKey, true),
        'ticket_pids_meta_exists' => metadata_exists('post', $planId, $ticketMetaKey),
        'manual_ticket_pids' => function_exists('vms_ticketing_get_manual_product_ids') ? vms_ticketing_get_manual_product_ids($planId) : array(),
    ));

    $assert($legacyHtml !== '', 'Legacy/imported ticketing partial should render.');
    $assert(strpos($legacyHtml, '<form') === false, 'Legacy/imported ticketing partial must not introduce a nested form.');
    $assert(strpos($legacyHtml, 'type="submit"') === false, 'Legacy/imported ticketing partial must not introduce submit buttons.');
    $assert(strpos($legacyHtml, 'name="vms_event_plan_action"') === false, 'Legacy/imported ticketing partial must not submit through the broad Event Plan save path.');
    $assert(strpos($legacyHtml, 'Re-sync to Calendar') === false, 'Legacy/imported ticketing partial should not render the Re-sync to Calendar control.');
    $assert(strpos($legacyHtml, 'vms_calendar_unpublished_suppress') === false, 'Legacy/imported ticketing partial should not render vms_calendar_unpublished_suppress.');
    $assert(strpos($legacyHtml, 'id="vms-ticketing-search"') !== false, 'Legacy/imported ticketing partial should still render the TEC search field.');
    $assert(strpos($legacyHtml, 'id="vms-ticketing-link-btn"') !== false, 'Legacy/imported ticketing partial should still render the TEC link button.');
    $assert(strpos($legacyHtml, 'id="vms-ticketing-unlink-btn"') !== false, 'Legacy/imported ticketing partial should still render the TEC unlink button.');
    $assert(strpos($legacyHtml, 'id="vms-ticketing-refresh-btn"') !== false, 'Legacy/imported ticketing partial should still render the stats refresh button.');
    $assert(strpos($legacyHtml, 'id="vms-ticketing-product-search"') !== false, 'Legacy/imported ticketing partial should still render the Woo product search field.');
    $assert(strpos($legacyHtml, 'id="vms-ticketing-manual-list"') !== false, 'Legacy/imported ticketing partial should still render the manual Woo product list container.');

    $unlinkResponse = $dispatchAjax('vms_ticketing_unlink_tec_event', array(
        'plan_id' => $planId,
    ));
    $assert(!empty($unlinkResponse['success']), 'TEC unlink should succeed.');
    $assert((int) get_post_meta($planId, $tecMetaKey, true) === 0, 'Linked TEC event meta should be cleared after unlink.');

    $detachResponse = $dispatchAjax('vms_ticketing_detach_product', array(
        'plan_id' => $planId,
        'product_id' => $productId,
    ));
    $assert(!empty($detachResponse['success']), 'Manual Woo product detach should succeed.');
    $detachedIds = isset($detachResponse['data']['manual_product_ids']) && is_array($detachResponse['data']['manual_product_ids'])
        ? array_map('absint', $detachResponse['data']['manual_product_ids'])
        : array();
    $assert(!in_array($productId, $detachedIds, true), 'Detached product list should no longer include the created product.');

    fwrite(STDOUT, "event plan legacy/imported ticketing integration smoke: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan legacy/imported ticketing integration smoke: FAIL - ' . $e->getMessage() . "\n");
    $cleanup();
    exit(1);
}

$cleanup();
