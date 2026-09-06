<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Data Tools Events Import Engine
 * Preview → Commit, multi-venue safe, VMS keys.
 *
 * This engine imports:
 * - TEC events (post_type tribe_events)
 * - Woo tickets (post_type product) linked to TEC via _tribe_wooticket_for_event
 *
 * Identity:
 * - Event UID:  venue_key|event_key
 * - Ticket UID: venue_key|event_key|ticket_key
 */

function vms_dt_events_import_engine() {
    static $engine = null;
    if ($engine) { return $engine; }
    $engine = new VMS_DT_Events_Import_Engine();
    return $engine;
}

class VMS_DT_Events_Import_Engine {

    const NONCE_ACTION = 'vms_dt_events_import';
    const NONCE_NAME   = 'vms_dt_events_import_nonce';

    const OPT_EVENTS_URL  = 'vms_dt_events_csv_url';
    const OPT_TICKETS_URL = 'vms_dt_tickets_csv_url';

    const OPT_LAST_RUN = 'vms_dt_events_import_last_run';

    public function handle_request_and_get_view_state() {
        $state = array(
            'events_url'  => (string) get_option(self::OPT_EVENTS_URL, ''),
            'tickets_url' => (string) get_option(self::OPT_TICKETS_URL, ''),
            'preview'     => null,
            'notices'     => array(),
            'last_run'    => get_option(self::OPT_LAST_RUN, array()),
        );

        $is_post = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST');
        if (!$is_post) { return $state; }

        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            $state['notices'][] = array('class' => 'notice notice-error', 'message' => 'Security check failed.');
            return $state;
        }

        $action = isset($_POST['vms_dt_action']) ? sanitize_text_field((string) $_POST['vms_dt_action']) : '';

        if ($action === 'save_urls') {
            $events_url  = isset($_POST['events_url']) ? esc_url_raw(trim((string) $_POST['events_url'])) : '';
            $tickets_url = isset($_POST['tickets_url']) ? esc_url_raw(trim((string) $_POST['tickets_url'])) : '';
            update_option(self::OPT_EVENTS_URL, $events_url);
            update_option(self::OPT_TICKETS_URL, $tickets_url);
            $state['events_url']  = $events_url;
            $state['tickets_url'] = $tickets_url;
            $state['notices'][] = array('class' => 'notice notice-success', 'message' => 'URLs saved.');
            return $state;
        }

        if ($action === 'preview_upload') {
            $events_file  = isset($_FILES['events_csv']) ? $_FILES['events_csv'] : null;
            $tickets_file = isset($_FILES['tickets_csv']) ? $_FILES['tickets_csv'] : null;

            $preview = $this->build_preview_from_uploads($events_file, $tickets_file);
            if (isset($preview['error'])) {
                $state['notices'][] = array('class' => 'notice notice-error', 'message' => $preview['error']);
                return $state;
            }

            $state['preview'] = $preview;
            $state['notices'][] = array('class' => 'notice notice-info', 'message' => 'Preview generated. Review and commit when ready.');
            return $state;
        }

        if ($action === 'preview_urls') {
            $preview = $this->build_preview_from_urls($state['events_url'], $state['tickets_url']);
            if (isset($preview['error'])) {
                $state['notices'][] = array('class' => 'notice notice-error', 'message' => $preview['error']);
                return $state;
            }

            $state['preview'] = $preview;
            $state['notices'][] = array('class' => 'notice notice-info', 'message' => 'Preview generated from URLs. Review and commit when ready.');
            return $state;
        }

        if ($action === 'commit') {
            $payload = isset($_POST['preview_payload']) ? (string) wp_unslash($_POST['preview_payload']) : '';
            $decoded = json_decode($payload, true);

            if (!is_array($decoded) || empty($decoded['commit_token'])) {
                $state['notices'][] = array('class' => 'notice notice-error', 'message' => 'Commit payload missing or invalid.');
                return $state;
            }

            $res = $this->commit_preview($decoded);

            if (!empty($res['ok'])) {
                $state['notices'][] = array('class' => 'notice notice-success', 'message' => $res['message']);
                $state['last_run'] = $res['last_run'];
                $state['preview'] = null;
            } else {
                $state['notices'][] = array('class' => 'notice notice-error', 'message' => $res['message']);
                $state['preview'] = isset($decoded['preview']) ? $decoded['preview'] : null;
            }

            return $state;
        }

        $state['notices'][] = array('class' => 'notice notice-warning', 'message' => 'Unknown action.');
        return $state;
    }

    public function render_page($state) {
        $events_url  = (string) $state['events_url'];
        $tickets_url = (string) $state['tickets_url'];

        ob_start();

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<h2>Upload CSVs (Preview)</h2>';
        echo '<p><label>Events CSV <input type="file" name="events_csv" accept=".csv" /></label></p>';
        echo '<p><label>Tickets CSV <input type="file" name="tickets_csv" accept=".csv" /></label></p>';
        echo '<p><button class="button button-primary" type="submit" name="vms_dt_action" value="preview_upload">Preview Upload</button></p>';

        echo '<hr />';

        echo '<h2>Remote URLs</h2>';
        echo '<p><label>Events CSV URL<br /><input class="large-text" type="url" name="events_url" value="' . esc_attr($events_url) . '" /></label></p>';
        echo '<p><label>Tickets CSV URL<br /><input class="large-text" type="url" name="tickets_url" value="' . esc_attr($tickets_url) . '" /></label></p>';

        echo '<p>';
        echo '<button class="button" type="submit" name="vms_dt_action" value="save_urls">Save URLs</button> ';
        echo '<button class="button" type="submit" name="vms_dt_action" value="preview_urls">Preview from URLs</button>';
        echo '</p>';

        echo '</form>';

        echo '<hr />';

        if (!empty($state['last_run']) && is_array($state['last_run'])) {
            $lr = $state['last_run'];
            $ts = !empty($lr['time_gmt']) ? (int) $lr['time_gmt'] : 0;
            $local = $ts ? get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts), 'Y-m-d H:i:s') : '';
            echo '<h2>Last Import</h2>';
            echo '<p>';
            echo $local ? ('<strong>' . esc_html($local) . '</strong><br />') : '';
            if (!empty($lr['summary'])) {
                echo nl2br(esc_html((string) $lr['summary']));
            }
            echo '</p>';
            echo '<hr />';
        }

        if (!empty($state['preview'])) {
            $this->render_preview_block($state['preview']);
        } else {
            echo '<p>No preview loaded.</p>';
        }

        return ob_get_clean();
    }

    private function render_preview_block($preview) {
        $counts = isset($preview['counts']) ? $preview['counts'] : array();
        $errs   = isset($preview['errors']) ? $preview['errors'] : array();

        $payload = array(
            'commit_token' => $preview['commit_token'],
            'preview'      => $preview,
        );

        echo '<h2>Preview</h2>';

        if (!empty($counts)) {
            echo '<p><strong>Planned changes:</strong></p>';
            echo '<ul>';
            echo '<li>Events: create ' . intval($counts['events_create']) . ', update ' . intval($counts['events_update']) . ', skip ' . intval($counts['events_skip']) . '</li>';
            echo '<li>Tickets: create ' . intval($counts['tickets_create']) . ', update ' . intval($counts['tickets_update']) . ', skip ' . intval($counts['tickets_skip']) . '</li>';
            echo '</ul>';
        }

        if (!empty($errs)) {
            echo '<div class="notice notice-error"><p><strong>Validation errors found.</strong> Fix these before commit.</p></div>';
            echo '<ul>';
            foreach ($errs as $e) {
                echo '<li>' . esc_html((string) $e) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="notice notice-success"><p>Validation passed. You can commit.</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<input type="hidden" name="preview_payload" value="' . esc_attr(wp_json_encode($payload)) . '" />';
        echo '<p><button class="button button-primary" type="submit" name="vms_dt_action" value="commit" ' . (empty($errs) ? '' : 'disabled') . '>Commit Import</button></p>';
        echo '</form>';
    }

    private function build_preview_from_uploads($events_file, $tickets_file) {
        $events_path  = $this->tmp_path_from_upload($events_file);
        $tickets_path = $this->tmp_path_from_upload($tickets_file);

        if (!$events_path && !$tickets_path) {
            return array('error' => 'Please upload at least one CSV (Events and/or Tickets).');
        }

        return $this->build_preview_from_paths($events_path, $tickets_path, 'upload');
    }

    private function build_preview_from_urls($events_url, $tickets_url) {
        $events_tmp  = $events_url ? download_url($events_url) : null;
        $tickets_tmp = $tickets_url ? download_url($tickets_url) : null;

        if ($events_tmp && is_wp_error($events_tmp)) { $events_tmp = null; }
        if ($tickets_tmp && is_wp_error($tickets_tmp)) { $tickets_tmp = null; }

        if (!$events_tmp && !$tickets_tmp) {
            return array('error' => 'No valid URL downloads. Check URLs and ensure they are publicly accessible CSV endpoints.');
        }

        $preview = $this->build_preview_from_paths($events_tmp, $tickets_tmp, 'urls');

        if ($events_tmp && is_string($events_tmp)) { @unlink($events_tmp); }
        if ($tickets_tmp && is_string($tickets_tmp)) { @unlink($tickets_tmp); }

        return $preview;
    }

    private function build_preview_from_paths($events_path, $tickets_path, $source_label) {
        $preview = array(
            'commit_token' => wp_generate_password(32, false, false),
            'source'       => $source_label,
            'counts'       => array(
                'events_create'  => 0,
                'events_update'  => 0,
                'events_skip'    => 0,
                'tickets_create' => 0,
                'tickets_update' => 0,
                'tickets_skip'   => 0,
            ),
            'errors'       => array(),
            'events_rows'  => array(),
            'tickets_rows' => array(),
        );

        // Parse and validate Events
        if ($events_path) {
            $events_parse = $this->parse_events_csv($events_path);
            if (!empty($events_parse['errors'])) {
                foreach ($events_parse['errors'] as $e) { $preview['errors'][] = 'Events: ' . $e; }
            } else {
                $preview['events_rows'] = $events_parse['rows'];
            }
        }

        // Parse and validate Tickets
        if ($tickets_path) {
            $tickets_parse = $this->parse_tickets_csv($tickets_path);
            if (!empty($tickets_parse['errors'])) {
                foreach ($tickets_parse['errors'] as $e) { $preview['errors'][] = 'Tickets: ' . $e; }
            } else {
                $preview['tickets_rows'] = $tickets_parse['rows'];
            }
        }

        // Build plan counts (create vs update vs skip) without writing.
        $this->plan_counts($preview);

        return $preview;
    }

    private function plan_counts(&$preview) {
        // Events plan
        foreach ($preview['events_rows'] as $row) {
            if (!$row['import']) { $preview['counts']['events_skip']++; continue; }

            $uid = $row['vms_event_uid'];
            $existing = $this->find_event_by_uid($uid);

            if ($existing) { $preview['counts']['events_update']++; }
            else { $preview['counts']['events_create']++; }
        }

        // Tickets plan
        foreach ($preview['tickets_rows'] as $row) {
            if (!$row['import']) { $preview['counts']['tickets_skip']++; continue; }

            $uid = $row['vms_ticket_uid'];

            $existing = $this->find_ticket_by_uid($uid);

            if ($existing) { $preview['counts']['tickets_update']++; }
            else { $preview['counts']['tickets_create']++; }
        }
    }

    private function commit_preview($decoded) {
        if (!isset($decoded['preview']) || !is_array($decoded['preview'])) {
            return array('ok' => false, 'message' => 'Preview missing in commit payload.');
        }

        $preview = $decoded['preview'];

        if (!empty($preview['errors'])) {
            return array('ok' => false, 'message' => 'Validation errors exist. Commit is blocked.');
        }

        // Hard requirements
        if (!function_exists('tribe_tickets') || (!function_exists('tribe_create_event') && !class_exists('Tribe__Events__Main'))) {
            return array('ok' => false, 'message' => 'The Events Calendar and Event Tickets (Woo) must be active for this import.');
        }

        $events_created = 0; $events_updated = 0; $events_skipped = 0;
        $tickets_created = 0; $tickets_updated = 0; $tickets_skipped = 0;

        // Commit events first (tickets depend on them)
        foreach ($preview['events_rows'] as $row) {
            if (!$row['import']) { $events_skipped++; continue; }

            $res = $this->upsert_event($row);

            if ($res === 'created') { $events_created++; }
            elseif ($res === 'updated') { $events_updated++; }
            else { $events_skipped++; }
        }

        // Commit tickets
        foreach ($preview['tickets_rows'] as $row) {
            if (!$row['import']) { $tickets_skipped++; continue; }

            $res = $this->upsert_ticket($row);

            if ($res === 'created') { $tickets_created++; }
            elseif ($res === 'updated') { $tickets_updated++; }
            else { $tickets_skipped++; }
        }

        $summary =
            'Commit complete.' . "\n" .
            'Events: created ' . $events_created . ', updated ' . $events_updated . ', skipped ' . $events_skipped . "\n" .
            'Tickets: created ' . $tickets_created . ', updated ' . $tickets_updated . ', skipped ' . $tickets_skipped;

        $last_run = array(
            'time_gmt' => time(),
            'summary'  => $summary,
        );

        update_option(self::OPT_LAST_RUN, $last_run);

        return array(
            'ok' => true,
            'message' => 'Import committed. See Last Import for details.',
            'last_run' => $last_run,
        );
    }

    private function tmp_path_from_upload($file) {
        if (!$file || empty($file['tmp_name'])) { return null; }
        if (!empty($file['error'])) { return null; }
        return (string) $file['tmp_name'];
    }

    private function parse_events_csv($path) {
        $out = array('errors' => array(), 'rows' => array());

        $h = fopen($path, 'r');
        if (!$h) { $out['errors'][] = 'Unable to open file.'; return $out; }

        $header = fgetcsv($h);
        if (!$header) { fclose($h); $out['errors'][] = 'Missing header row.'; return $out; }

        $cols = array_flip($header);

        // Required: venue_key, event_key, title, start_date
        $required = array('venue_key','event_key','title','start_date');
        foreach ($required as $col) {
            if (!isset($cols[$col])) { $out['errors'][] = 'Missing required column: ' . $col; }
        }
        if (!empty($out['errors'])) { fclose($h); return $out; }

        // Optional compatibility: start_datetime / end_datetime
        if (!isset($cols['start_date']) && isset($cols['start_datetime'])) { $cols['start_date'] = $cols['start_datetime']; }
        if (!isset($cols['end_date']) && isset($cols['end_datetime'])) { $cols['end_date'] = $cols['end_datetime']; }

        $row_num = 1;
        while (($row = fgetcsv($h)) !== false) {
            $row_num++;

            $import = true;
            if (isset($cols['import'])) {
                $flag = strtolower(trim((string) $row[$cols['import']]));
                $import = ($flag === 'yes');
            }

            $venue_key = trim((string) $row[$cols['venue_key']]);
            $event_key = trim((string) $row[$cols['event_key']]);
            $title     = trim((string) $row[$cols['title']]);
            $start     = trim((string) $row[$cols['start_date']]);
            $end       = isset($cols['end_date']) ? trim((string) $row[$cols['end_date']]) : '';

            if ($import) {
                if ($venue_key === '' || $event_key === '' || $title === '' || $start === '') {
                    $out['errors'][] = 'Row ' . $row_num . ': missing venue_key, event_key, title, or start_date.';
                    continue;
                }
            }

            $uid = $venue_key . '|' . $event_key;

            $out['rows'][] = array(
                'import'        => $import,
                'venue_key'     => $venue_key,
                'event_key'     => $event_key,
                'vms_event_uid' => $uid,

                'title'         => $title,
                'start_date'    => $start,
                'end_date'      => $end,

                'status'        => isset($cols['status']) ? strtolower(trim((string) $row[$cols['status']])) : 'publish',
                'description'   => isset($cols['description']) ? (string) $row[$cols['description']] : '',
                'url'           => isset($cols['url']) ? esc_url_raw(trim((string) $row[$cols['url']])) : '',
                'image_url'     => isset($cols['image_url']) ? esc_url_raw(trim((string) $row[$cols['image_url']])) : '',
                'categories'    => isset($cols['categories']) ? (string) $row[$cols['categories']] : '',
                'tags'          => isset($cols['tags']) ? (string) $row[$cols['tags']] : '',
                'food_truck'    => isset($cols['food_truck']) ? (string) $row[$cols['food_truck']] : '',
                'food_truck_website' => isset($cols['food_truck_website']) ? esc_url_raw(trim((string) $row[$cols['food_truck_website']])) : '',
            );
        }

        fclose($h);
        return $out;
    }

    private function parse_tickets_csv($path) {
        $out = array('errors' => array(), 'rows' => array());

        $h = fopen($path, 'r');
        if (!$h) { $out['errors'][] = 'Unable to open file.'; return $out; }

        $header = fgetcsv($h);
        if (!$header) { fclose($h); $out['errors'][] = 'Missing header row.'; return $out; }

        $cols = array_flip($header);

        // Required: venue_key, event_key, ticket_key, ticket_name, price, capacity
        $required = array('venue_key','event_key','ticket_key','ticket_name','price','capacity');
        foreach ($required as $col) {
            if (!isset($cols[$col])) { $out['errors'][] = 'Missing required column: ' . $col; }
        }
        if (!empty($out['errors'])) { fclose($h); return $out; }

        $row_num = 1;
        while (($row = fgetcsv($h)) !== false) {
            $row_num++;

            $import = true;
            if (isset($cols['import'])) {
                $flag = strtolower(trim((string) $row[$cols['import']]));
                $import = ($flag === 'yes');
            }

            $venue_key  = trim((string) $row[$cols['venue_key']]);
            $event_key  = trim((string) $row[$cols['event_key']]);
            $ticket_key = trim((string) $row[$cols['ticket_key']]);
            $name       = trim((string) $row[$cols['ticket_name']]);

            if ($import) {
                if ($venue_key === '' || $event_key === '' || $ticket_key === '' || $name === '') {
                    $out['errors'][] = 'Row ' . $row_num . ': missing venue_key, event_key, ticket_key, or ticket_name.';
                    continue;
                }
            }

            $event_uid  = $venue_key . '|' . $event_key;
            $ticket_uid = $event_uid . '|' . $ticket_key;

            $raw_price = (string) $row[$cols['price']];
            $price = floatval(str_replace(array(',', '$'), '', $raw_price));
            $capacity = intval((string) $row[$cols['capacity']]);

            $active = '';
            if (isset($cols['active'])) {
                $active = strtolower(trim((string) $row[$cols['active']]));
            }

            $sku = '';
            if (isset($cols['sku'])) {
                $sku = trim((string) $row[$cols['sku']]);
            }

            $sort_order = isset($cols['sort_order']) ? intval((string) $row[$cols['sort_order']]) : 0;

            $min_qty = isset($cols['min_quantity']) ? intval((string) $row[$cols['min_quantity']]) : 0;
            $qual    = isset($cols['addon_qualifier']) ? strtolower(trim((string) $row[$cols['addon_qualifier']])) : '';

            $sales_start = isset($cols['sales_start']) ? trim((string) $row[$cols['sales_start']]) : '';
            $sales_end   = isset($cols['sales_end']) ? trim((string) $row[$cols['sales_end']]) : '';

            $product_tags = isset($cols['ticket_product_tags']) ? (string) $row[$cols['ticket_product_tags']] : '';

            $out['rows'][] = array(
                'import'         => $import,
                'venue_key'      => $venue_key,
                'event_key'      => $event_key,
                'ticket_key'     => $ticket_key,

                'vms_event_uid'  => $event_uid,
                'vms_ticket_uid' => $ticket_uid,

                'ticket_name'    => $name,
                'price'          => $price,
                'capacity'       => $capacity,

                'active'         => $active,
                'sku'            => $sku,
                'sort_order'     => $sort_order,

                'min_quantity'   => $min_qty,
                'addon_qualifier'=> $qual,

                'sales_start'    => $sales_start,
                'sales_end'      => $sales_end,

                'ticket_product_tags' => $product_tags,
            );
        }

        fclose($h);
        return $out;
    }

    private function find_event_by_uid($vms_event_uid) {
        $existing = get_posts(array(
            'post_type'   => 'tribe_events',
            'meta_key'    => '_vms_event_uid',
            'meta_value'  => $vms_event_uid,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
        ));

        return !empty($existing) ? intval($existing[0]) : 0;
    }

    private function upsert_event($row) {
        $uid = $row['vms_event_uid'];
        $event_id = $this->find_event_by_uid($uid);

        $status = in_array($row['status'], array('publish','draft','pending','private'), true) ? $row['status'] : 'publish';

        $start_ts = strtotime((string) $row['start_date']);
        $end_ts   = $row['end_date'] ? strtotime((string) $row['end_date']) : $start_ts;

        if (!$start_ts) { return 'skipped'; }
        if (!$end_ts) { $end_ts = $start_ts; }

        $args = array(
            'post_title'   => (string) $row['title'],
            'post_content' => (string) $row['description'],
            'post_status'  => $status,
            'post_type'    => 'tribe_events',
        );

        if ($event_id) {
            $args['ID'] = $event_id;
            wp_update_post($args);
        } else {
            $event_id = wp_insert_post($args);
            if (!$event_id || is_wp_error($event_id)) { return 'skipped'; }
        }

        // Dates (TEC standard metas)
        update_post_meta($event_id, '_EventStartDate', date('Y-m-d H:i:s', $start_ts));
        update_post_meta($event_id, '_EventEndDate',   date('Y-m-d H:i:s', $end_ts));

        // Optional URL
        if (!empty($row['url'])) { update_post_meta($event_id, '_EventURL', (string) $row['url']); }

        // VMS identity metas
        update_post_meta($event_id, '_vms_venue_key', (string) $row['venue_key']);
        update_post_meta($event_id, '_vms_event_key', (string) $row['event_key']);
        update_post_meta($event_id, '_vms_event_uid', (string) $row['vms_event_uid']);

        // Featured image import tracking
        $image_url = (string) $row['image_url'];
        if ($image_url) {
            $prev = (string) get_post_meta($event_id, '_vms_event_image_url', true);
            if ($prev !== $image_url) {
                if (!function_exists('media_sideload_image')) {
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                }
                $attachment_id = media_sideload_image($image_url, $event_id, (string) $row['title'], 'id');
                if (!is_wp_error($attachment_id) && $attachment_id) {
                    set_post_thumbnail($event_id, $attachment_id);
                    update_post_meta($event_id, '_vms_event_image_url', $image_url);
                }
            }
        } else {
            $has_prev = (string) get_post_meta($event_id, '_vms_event_image_url', true);
            if ($has_prev !== '') {
                delete_post_thumbnail($event_id);
                delete_post_meta($event_id, '_vms_event_image_url');
            }
        }

        // Categories and tags
        if (isset($row['categories'])) {
            $cats = trim((string) $row['categories']);
            if ($cats !== '') {
                $terms = array_filter(array_map('trim', explode(',', $cats)));
                wp_set_object_terms($event_id, $terms, 'tribe_events_cat', false);
            } else {
                wp_set_object_terms($event_id, array(), 'tribe_events_cat', false);
            }
        }

        if (isset($row['tags'])) {
            $tags = trim((string) $row['tags']);
            if ($tags !== '') {
                $terms = array_filter(array_map('trim', explode(',', $tags)));
                wp_set_post_terms($event_id, $terms, 'post_tag', false);
            } else {
                wp_set_post_terms($event_id, array(), 'post_tag', false);
            }
        }

        // Food truck mirrors (keep TEC custom fields if you still use them)
        $food = trim((string) $row['food_truck']);
        $food_url = (string) $row['food_truck_website'];

        if ($food !== '') {
            update_post_meta($event_id, '_vms_food_truck', $food);
            update_post_meta($event_id, '_ecp_custom_2', $food);
        } else {
            delete_post_meta($event_id, '_vms_food_truck');
            delete_post_meta($event_id, '_ecp_custom_2');
        }

        if ($food_url !== '') {
            update_post_meta($event_id, '_vms_food_truck_url', $food_url);
            update_post_meta($event_id, '_ecp_custom_3', $food_url);
        } else {
            delete_post_meta($event_id, '_vms_food_truck_url');
            delete_post_meta($event_id, '_ecp_custom_3');
        }

        return (isset($args['ID']) ? 'updated' : 'created');
    }

    private function find_ticket_by_uid($vms_ticket_uid) {
        $existing = get_posts(array(
            'post_type'   => 'product',
            'meta_key'    => '_vms_ticket_uid',
            'meta_value'  => $vms_ticket_uid,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
        ));

        return !empty($existing) ? intval($existing[0]) : 0;
    }

    private function find_event_id_for_ticket_row($row) {
        $event_id = $this->find_event_by_uid($row['vms_event_uid']);
        return $event_id ? $event_id : 0;
    }

    private function upsert_ticket($row) {
        $event_id = $this->find_event_id_for_ticket_row($row);
        if (!$event_id) { return 'skipped'; }

        $uid = (string) $row['vms_ticket_uid'];
        $ticket_id = $this->find_ticket_by_uid($uid);

        $status = 'publish';
        if ($row['active'] === 'no') { $status = 'draft'; }
        if ($row['active'] === 'yes') { $status = 'publish'; }

        $price    = floatval($row['price']);
        $capacity = intval($row['capacity']);

        $sales_start = current_time('mysql');
        $sales_end   = '';

        if (!empty($row['sales_start'])) {
            $ts = strtotime((string) $row['sales_start']);
            if ($ts) { $sales_start = gmdate('Y-m-d H:i:s', $ts); }
        }

        if (!empty($row['sales_end'])) {
            $ts = strtotime((string) $row['sales_end']);
            if ($ts) { $sales_end = gmdate('Y-m-d H:i:s', $ts); }
        }

        if ($ticket_id) {
            wp_update_post(array(
                'ID'         => $ticket_id,
                'post_title' => (string) $row['ticket_name'],
                'post_status'=> $status,
                'menu_order' => intval($row['sort_order']),
            ));
        } else {
            $args = array(
                'title'                      => (string) $row['ticket_name'],
                'status'                     => $status,
                '_tribe_wooticket_for_event' => $event_id,

                '_price'                     => $price,
                '_regular_price'             => $price,
                '_virtual'                   => 'yes',
                '_manage_stock'              => 'yes',
                '_stock'                     => $capacity,
                '_stock_status'              => $capacity > 0 ? 'instock' : 'outofstock',

                '_tribe_ticket_capacity'     => $capacity,
                '_global_stock_mode'         => 'own',

                '_ticket_start_date'         => $sales_start,
            );

            if ($sales_end) { $args['_ticket_end_date'] = $sales_end; }
            if (!empty($row['sku'])) { $args['_sku'] = (string) $row['sku']; }

            $ticket = tribe_tickets('woo')->set_args($args)->create();
            if (!$ticket || is_wp_error($ticket)) { return 'skipped'; }

            $ticket_id = is_object($ticket) ? intval($ticket->ID) : intval($ticket);
            if (!$ticket_id) { return 'skipped'; }

            if (intval($row['sort_order']) > 0) {
                wp_update_post(array('ID' => $ticket_id, 'menu_order' => intval($row['sort_order'])));
            }
        }

        // Core Woo meta
        update_post_meta($ticket_id, '_price',         $price);
        update_post_meta($ticket_id, '_regular_price', $price);
        update_post_meta($ticket_id, '_virtual',       'yes');
        update_post_meta($ticket_id, '_manage_stock',  'yes');
        update_post_meta($ticket_id, '_stock',         $capacity);
        update_post_meta($ticket_id, '_stock_status',  $capacity > 0 ? 'instock' : 'outofstock');

        update_post_meta($ticket_id, '_tribe_ticket_capacity', $capacity);
        update_post_meta($ticket_id, '_global_stock_mode',     'own');

        update_post_meta($ticket_id, '_ticket_start_date', $sales_start);
        if ($sales_end) { update_post_meta($ticket_id, '_ticket_end_date', $sales_end); }
        else { delete_post_meta($ticket_id, '_ticket_end_date'); }

        if (!empty($row['sku'])) { update_post_meta($ticket_id, '_sku', (string) $row['sku']); }

        // VMS identity metas
        update_post_meta($ticket_id, '_vms_venue_key', (string) $row['venue_key']);
        update_post_meta($ticket_id, '_vms_event_key', (string) $row['event_key']);
        update_post_meta($ticket_id, '_vms_event_uid', (string) $row['vms_event_uid']);
        update_post_meta($ticket_id, '_vms_ticket_uid', (string) $row['vms_ticket_uid']);
        update_post_meta($ticket_id, '_tribe_wooticket_for_event', $event_id);

        // Qualifier metas (VMS keys)
        if (intval($row['min_quantity']) > 0 && $row['addon_qualifier'] !== 'yes') {
            update_post_meta($ticket_id, '_vms_required_qualifiers_per_unit', max(0, intval($row['min_quantity'])));
        } else {
            delete_post_meta($ticket_id, '_vms_required_qualifiers_per_unit');
        }

        if ($row['addon_qualifier'] !== '') {
            update_post_meta($ticket_id, '_vms_addon_qualifier', (string) $row['addon_qualifier']);
        } else {
            delete_post_meta($ticket_id, '_vms_addon_qualifier');
        }

        // Product tags (fixed, reads from CSV column ticket_product_tags)
        $raw = trim((string) $row['ticket_product_tags']);
        if ($raw !== '') {
            $tags = array();
            $parts = preg_split('/[|,]/', $raw);
            foreach ($parts as $t) {
                $t = trim((string) $t);
                if ($t !== '') { $tags[] = $t; }
            }
            if (!empty($tags)) { wp_set_object_terms($ticket_id, $tags, 'product_tag', true); }
        }

        return ($ticket_id ? (isset($args) ? 'created' : 'updated') : 'skipped');
    }
}
