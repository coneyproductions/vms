<?php
/** Manifest every table in the already hard-bound disposable database. */
function staffing_read_manifest(): array {
    global $wpdb;
    $out = array();
    foreach ($wpdb->get_col('SHOW TABLES') as $table) {
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i', $table), ARRAY_A);
        $encoded = array_map('wp_json_encode', $rows); sort($encoded, SORT_STRING);
        $out[$table] = array('schema' => $wpdb->get_row($wpdb->prepare('SHOW CREATE TABLE %i', $table), ARRAY_N), 'rows' => hash('sha256', wp_json_encode($encoded)), 'count' => count($rows));
    }
    return $out;
}
