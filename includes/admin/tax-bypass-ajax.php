<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/../core/tax-bypass.php';

add_action('wp_ajax_vms_tax_bypass_set', function () {
  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Forbidden'], 403);
  }

  check_ajax_referer('vms_tax_bypass_ajax', 'nonce');

  $post_id = bvmgr_request_read_absint($_POST, 'post_id');
  $until   = bvmgr_request_read_text_field($_POST, 'until');
  $reason  = bvmgr_request_read_text_field($_POST, 'reason');

  if ($post_id <= 0) {
    wp_send_json_error(['message' => 'Missing post_id'], 400);
  }

  $pt = get_post_type($post_id);
  if (!in_array($pt, bvmgr_tax_bypass_supported_post_types(), true)) {
    wp_send_json_error(['message' => 'Unsupported post type'], 400);
  }

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
    wp_send_json_error(['message' => 'Invalid until date'], 400);
  }
  if (trim($reason) === '') {
    wp_send_json_error(['message' => 'Reason required'], 400);
  }

  $st = bvmgr_tax_bypass_apply($post_id, true, $until, $reason);

  wp_send_json_success(['status' => $st]);
});

add_action('wp_ajax_vms_tax_bypass_clear', function () {
  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Forbidden'], 403);
  }

  check_ajax_referer('vms_tax_bypass_ajax', 'nonce');

  $post_id = bvmgr_request_read_absint($_POST, 'post_id');
  if ($post_id <= 0) {
    wp_send_json_error(['message' => 'Missing post_id'], 400);
  }

  $pt = get_post_type($post_id);
  if (!in_array($pt, bvmgr_tax_bypass_supported_post_types(), true)) {
    wp_send_json_error(['message' => 'Unsupported post type'], 400);
  }

  $st = bvmgr_tax_bypass_apply($post_id, false);

  wp_send_json_success(['status' => $st]);
});
