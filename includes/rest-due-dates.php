<?php
defined('ABSPATH') || exit;

/**
 * REST: Due Dates actions (procedural)
 * Route(s):
 * - GET  /vms/v1/due-dates/obligations
 * - POST /vms/v1/due-dates/complete
 * - POST /vms/v1/due-dates/uncomplete
 */

add_action('rest_api_init', function () {
  register_rest_route('vms/v1', '/due-dates/obligations', [
    'methods'             => 'GET',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
    'callback'            => 'vms_rest_due_dates_obligations',
    'args'                => [
      'status' => [
        'required' => false,
        'sanitize_callback' => 'sanitize_key',
        'default' => 'open',
      ],
      'cadence' => [
        'required' => false,
        'sanitize_callback' => 'sanitize_key',
        'default' => 'all',
      ],
      'payee_id' => [
        'required' => false,
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'all',
      ],
      'include_archived' => [
        'required' => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => false,
      ],
      'lookback_days' => [
        'required' => false,
        'sanitize_callback' => 'absint',
        'default' => 120,
      ],
      'lookahead_days' => [
        'required' => false,
        'sanitize_callback' => 'absint',
        'default' => 120,
      ],
      'limit' => [
        'required' => false,
        'sanitize_callback' => 'absint',
        'default' => 500,
      ],
    ],
  ]);

  register_rest_route('vms/v1', '/due-dates/complete', [
    'methods'             => 'POST',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
    'callback'            => 'vms_rest_due_dates_complete',
    'args'                => [
      'obligation_id' => [
        'required' => true,
        'sanitize_callback' => 'sanitize_key',
      ],
      'due_date' => [
        'required' => true,
        'sanitize_callback' => 'sanitize_text_field',
      ],
      'notes' => [
        'required' => false,
        'sanitize_callback' => 'sanitize_textarea_field',
        'default' => '',
      ],
      'proof_url' => [
        'required' => false,
        'sanitize_callback' => 'esc_url_raw',
        'default' => '',
      ],
    ],
  ]);

  register_rest_route('vms/v1', '/due-dates/uncomplete', [
    'methods'             => 'POST',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
    'callback'            => 'vms_rest_due_dates_uncomplete',
    'args'                => [
      'obligation_id' => [
        'required' => true,
        'sanitize_callback' => 'sanitize_key',
      ],
      'due_date' => [
        'required' => true,
        'sanitize_callback' => 'sanitize_text_field',
      ],
      'notes' => [
        'required' => false,
        'sanitize_callback' => 'sanitize_textarea_field',
        'default' => '',
      ],
      'proof_url' => [
        'required' => false,
        'sanitize_callback' => 'esc_url_raw',
        'default' => '',
      ],
    ],
  ]);
});

function vms_rest_due_dates_obligations(WP_REST_Request $req)
{
  if (!function_exists('vms_due_build_obligations_list_response')) {
    $path = __DIR__ . '/core/due-dates.php';
    if (file_exists($path)) require_once $path;
  }

  $args = [
    'status' => sanitize_key((string) $req->get_param('status')),
    'cadence' => sanitize_key((string) $req->get_param('cadence')),
    'payee_id' => sanitize_text_field((string) $req->get_param('payee_id')),
    'include_archived' => rest_sanitize_boolean($req->get_param('include_archived')),
    'lookback_days' => absint($req->get_param('lookback_days')),
    'lookahead_days' => absint($req->get_param('lookahead_days')),
    'limit' => absint($req->get_param('limit')),
  ];

  return rest_ensure_response(vms_due_build_obligations_list_response($args));
}

function vms_rest_due_dates_complete(WP_REST_Request $req)
{
  if (!function_exists('vms_due_safe_complete')) {
    $path = __DIR__ . '/core/due-dates.php';
    if (file_exists($path)) require_once $path;
  }

  $oid = sanitize_key((string) $req->get_param('obligation_id'));
  $due = sanitize_text_field((string) $req->get_param('due_date'));
  $notes = sanitize_textarea_field((string) $req->get_param('notes'));
  $proof = esc_url_raw((string) $req->get_param('proof_url'));

  return rest_ensure_response(vms_due_safe_complete($oid, $due, $notes, $proof));
}

function vms_rest_due_dates_uncomplete(WP_REST_Request $req)
{
  if (!function_exists('vms_due_safe_uncomplete')) {
    $path = __DIR__ . '/core/due-dates.php';
    if (file_exists($path)) require_once $path;
  }

  $oid = sanitize_key((string) $req->get_param('obligation_id'));
  $due = sanitize_text_field((string) $req->get_param('due_date'));
  $notes = sanitize_textarea_field((string) $req->get_param('notes'));
  $proof = esc_url_raw((string) $req->get_param('proof_url'));

  return rest_ensure_response(vms_due_safe_uncomplete($oid, $due, $notes, $proof));
}
