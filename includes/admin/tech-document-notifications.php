<?php
/** Narrow document/status surfaces; all sending is confined to explicit POST or upload. */
defined('ABSPATH') || exit;

function bvmgr_tech_doc_admin_url(int $plan_id = 0, int $vendor_id = 0): string
{
    return admin_url('admin.php?page=bvm-tech-documents&plan_id=' . $plan_id . '&vendor_id=' . $vendor_id);
}

add_action('admin_menu', static function (): void {
    add_submenu_page(null, __('Technical documents', 'backstage-venue-manager'), __('Technical documents', 'backstage-venue-manager'),
        'edit_posts', 'bvm-tech-documents', 'bvmgr_tech_doc_admin_page');
});

function bvmgr_tech_doc_status_render(int $plan_id): void
{
    echo '<div class="bvm-tech-doc-status">';
    if (!bvmgr_tech_doc_storage_ready()) {
        echo '<p class="notice notice-error">' . esc_html__('Technician notifications are blocked: verified non-public document storage is not ready. Reconcile private-storage authority and configuration before delivery.', 'backstage-venue-manager') . '</p>';
    }
    $vendors = bvmgr_tech_doc_vendor_ids($plan_id);
    if (!$vendors) {
        echo '<p>' . esc_html__('No artist/vendor is linked to this event.', 'backstage-venue-manager') . '</p>';
    }
    foreach ($vendors as $vendor_id) {
        echo '<p><strong>' . esc_html(get_the_title($vendor_id)) . '</strong></p>';
        $people = bvmgr_tech_doc_recipients($plan_id, $vendor_id);
        if (!$people) {
            echo '<p class="notice notice-warning">' . esc_html__('No confirmed technician assigned. Technical documents have no technician recipient.', 'backstage-venue-manager') . '</p>';
        }
        foreach ($people as $person) {
            echo '<p>' . esc_html($person['label'] . ' — ' . ($person['email'] ?: __('Missing email', 'backstage-venue-manager')) . ($person['error'] ? ' — ' . $person['error'] : ' — ' . __('Confirmed technical assignment', 'backstage-venue-manager'))) . '</p>';
        }
        $logs = bvmgr_tech_doc_logs($plan_id);
        foreach (bvmgr_tech_doc_types() as $key => $label) {
            $doc = bvmgr_tech_doc_snapshot($vendor_id, $key);
            echo '<p>' . esc_html($label . ': ' . ($doc['name'] ?? __('Not available', 'backstage-venue-manager'))) . '</p>';
            if (!empty($doc['hash'])) {
                foreach ($people as $person) {
                    $state = $person['error'] ?: bvmgr_tech_doc_delivery_state($logs, $vendor_id, $person['email'], $doc);
                    $label_state = $state === 'sent' ? __('Accepted by email transport; inbox delivery unverified', 'backstage-venue-manager') : $state;
                    echo '<p>' . esc_html($person['label'] . ': ' . $label_state) . '</p>';
                }
            }
        }
    }
    echo '<p><a href="' . esc_url(bvmgr_tech_doc_admin_url($plan_id)) . '">' . esc_html__('Notification history / notify current technicians', 'backstage-venue-manager') . '</a></p></div>';
}

add_action('add_meta_boxes', static function (): void {
    add_meta_box('bvm-tech-document-notifications', __('Technician document notifications', 'backstage-venue-manager'), static function ($post): void {
        if (current_user_can('edit_post', $post->ID)) {
            bvmgr_tech_doc_status_render((int) $post->ID);
        }
    }, 'vms_event_plan', 'normal', 'default');
    add_meta_box('bvm-vendor-tech-documents', __('Technical documents', 'backstage-venue-manager'), static function ($post): void {
        if (current_user_can('edit_post', $post->ID)) {
            echo '<a href="' . esc_url(bvmgr_tech_doc_admin_url(0, (int) $post->ID)) . '">' . esc_html__('Upload / replace technical documents', 'backstage-venue-manager') . '</a>';
        }
    }, 'vms_vendor', 'side', 'default');
});

function bvmgr_tech_doc_admin_page(): void
{
    $plan_id = isset($_GET['plan_id']) && is_scalar($_GET['plan_id']) ? absint($_GET['plan_id']) : 0;
    $vendor_id = isset($_GET['vendor_id']) && is_scalar($_GET['vendor_id']) ? absint($_GET['vendor_id']) : 0;
    if ($plan_id > 0) {
        if (get_post_type($plan_id) !== 'vms_event_plan' || !current_user_can('edit_post', $plan_id)) {
            wp_die(esc_html__('You cannot edit this Event Plan.', 'backstage-venue-manager'));
        }
    } elseif ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor' || !current_user_can('edit_post', $vendor_id)) {
        wp_die(esc_html__('You cannot edit this vendor.', 'backstage-venue-manager'));
    }
    echo '<div class="wrap"><h1>' . esc_html__('Technical documents', 'backstage-venue-manager') . '</h1>';
    if (!$plan_id) {
        bvmgr_vendor_portal_render_tech_docs($vendor_id);
        $plans = bvmgr_tech_doc_event_ids($vendor_id);
        if (!$plans) {
            echo '<p>' . esc_html__('No current linked event. No technician notification can be delivered yet.', 'backstage-venue-manager') . '</p>';
        }
        foreach ($plans as $id) {
            echo '<p><a href="' . esc_url(bvmgr_tech_doc_admin_url($id)) . '">' . esc_html(get_the_title($id)) . '</a></p>';
        }
        echo '</div>';
        return;
    }
    if (bvmgr_request_method() === 'post' && isset($_POST['bvmgr_tech_doc_retry'])) {
        check_admin_referer('bvmgr_tech_doc_retry_' . $plan_id, 'bvmgr_tech_doc_nonce');
        foreach (bvmgr_tech_doc_vendor_ids($plan_id) as $id) {
            $changes = array();
            foreach (bvmgr_tech_doc_types() as $key => $label) {
                if ((int) get_post_meta($id, bvmgr_vendor_portal_tech_doc_meta_key($key), true) > 0) {
                    $changes[$key] = 'current';
                }
            }
            bvmgr_tech_doc_dispatch($plan_id, $id, $changes, 'manual_retry');
        }
        echo '<p>' . esc_html__('Current assignments checked. See delivery results below.', 'backstage-venue-manager') . '</p>';
    }
    echo '<h2>' . esc_html(get_the_title($plan_id)) . '</h2>';
    bvmgr_tech_doc_status_render($plan_id);
    echo '<form method="post">';
    wp_nonce_field('bvmgr_tech_doc_retry_' . $plan_id, 'bvmgr_tech_doc_nonce');
    echo '<button class="button" name="bvmgr_tech_doc_retry" value="1">' . esc_html__('Notify current technicians / retry unsent', 'backstage-venue-manager') . '</button></form>';
    echo '<p>' . esc_html__('Uses current confirmed technical assignments. Previously accepted content is suppressed. Failed attempts wait 60 seconds before retry. An unfinished attempt requires delivery investigation; it is not automatically resent.', 'backstage-venue-manager') . '</p>';
    echo '<h2>' . esc_html__('Notification history (UTC)', 'backstage-venue-manager') . '</h2>';
    $logs = bvmgr_tech_doc_logs($plan_id);
    if (!$logs) {
        echo '<p>' . esc_html__('No technician notification attempts recorded.', 'backstage-venue-manager') . '</p>';
    }
    foreach (array_slice($logs, 0, 100) as $log) {
        $payload = json_decode((string) $log['payload_json'], true);
        $names = array_map(static function ($doc) { return (string) ($doc['name'] ?? $doc['key'] ?? ''); }, (array) ($payload['documents'] ?? array()));
        echo '<details><summary>' . esc_html($log['created_at'] . ' — ' . $log['status'] . ' — ' . ($log['recipient_address'] ?: __('Unresolved recipient', 'backstage-venue-manager')) . ' — ' . $log['error_message']) . '</summary>';
        echo '<p>' . esc_html(get_the_title((int) ($payload['vendor_id'] ?? 0)) . ': ' . implode(', ', $names)) . '</p>';
        echo '<pre style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html(wp_json_encode($payload, JSON_PRETTY_PRINT)) . '</pre></details>';
    }
    echo '</div>';
}

/** Stable, non-secret email landing route creates recipient-bound download nonces only after login. */
function bvmgr_tech_doc_landing(): void
{
    if (!is_user_logged_in()) {
        auth_redirect();
        return;
    }
    $plan_id = isset($_GET['plan_id']) && is_scalar($_GET['plan_id']) ? absint($_GET['plan_id']) : 0;
    $vendor_id = isset($_GET['vendor_id']) && is_scalar($_GET['vendor_id']) ? absint($_GET['vendor_id']) : 0;
    if (!bvmgr_tech_doc_plan_is_current($plan_id) || !in_array($vendor_id, bvmgr_tech_doc_vendor_ids($plan_id), true)
        || !bvmgr_vendor_portal_user_can_download_tech_doc($vendor_id, 'stage_plot', $plan_id)) {
        wp_die(esc_html__('You do not have access to these event documents.', 'backstage-venue-manager'), '', array('response' => 403));
    }
    nocache_headers();
    header('Content-Type: text/html; charset=' . get_option('blog_charset'));
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html__('Technical documents', 'backstage-venue-manager') . '</title></head><body><main><h1>' . esc_html(get_the_title($plan_id)) . '</h1><h2>' . esc_html(get_the_title($vendor_id)) . '</h2><ul>';
    foreach (bvmgr_tech_doc_types() as $key => $label) {
        $doc = bvmgr_tech_doc_snapshot($vendor_id, $key);
        echo '<li>' . esc_html($label) . ': ';
        if (empty($doc['error'])) {
            echo '<a href="' . esc_url(bvmgr_vendor_portal_tech_doc_download_url($vendor_id, $key, $plan_id)) . '">' . esc_html($doc['name']) . '</a>';
        } else {
            echo esc_html__('Not available', 'backstage-venue-manager');
        }
        echo '</li>';
    }
    echo '</ul></main></body></html>';
    exit;
}
add_action('admin_post_bvmgr_tech_documents', 'bvmgr_tech_doc_landing');
add_action('admin_post_nopriv_bvmgr_tech_documents', 'bvmgr_tech_doc_landing');

function bvmgr_tech_doc_role_field($term = null): void
{
    $id = is_object($term) ? (int) $term->term_id : 0;
    $enabled = $id ? bvmgr_tech_doc_role_is_technical(array('role_id' => $id, 'name' => $term->name, 'slug' => $term->slug)) : false;
    if ($id) {
        echo '<tr class="form-field"><th>' . esc_html__('Technical document alerts', 'backstage-venue-manager') . '</th><td>';
    } else {
        echo '<div class="form-field">';
    }
    wp_nonce_field('bvmgr_tech_doc_role', 'bvmgr_tech_doc_role_nonce');
    echo '<label><input type="checkbox" name="bvmgr_receives_tech_docs" value="1" ' . checked($enabled, true, false) . '> ' . esc_html__('Notify confirmed staff assigned to this role when event technical documents change.', 'backstage-venue-manager') . '</label>';
    echo $id ? '</td></tr>' : '</div>';
}
add_action('vms_staff_role_add_form_fields', 'bvmgr_tech_doc_role_field');
add_action('vms_staff_role_edit_form_fields', 'bvmgr_tech_doc_role_field');
function bvmgr_tech_doc_role_save(int $term_id): void
{
    $taxonomy = get_taxonomy('vms_staff_role');
    $nonce = isset($_POST['bvmgr_tech_doc_role_nonce']) && is_string($_POST['bvmgr_tech_doc_role_nonce']) ? sanitize_text_field(wp_unslash($_POST['bvmgr_tech_doc_role_nonce'])) : '';
    if (!$taxonomy || !current_user_can($taxonomy->cap->manage_terms) || !$nonce || !wp_verify_nonce($nonce, 'bvmgr_tech_doc_role')) {
        return;
    }
    update_term_meta($term_id, '_bvmgr_receives_tech_docs', isset($_POST['bvmgr_receives_tech_docs']) && $_POST['bvmgr_receives_tech_docs'] === '1' ? '1' : '0');
}
add_action('created_vms_staff_role', 'bvmgr_tech_doc_role_save');
add_action('edited_vms_staff_role', 'bvmgr_tech_doc_role_save');
