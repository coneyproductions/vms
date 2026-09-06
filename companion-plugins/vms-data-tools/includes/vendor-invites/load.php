<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tokens.php';
require_once __DIR__ . '/logs.php';
require_once __DIR__ . '/retention.php';
require_once __DIR__ . '/open-dates.php';
require_once __DIR__ . '/opportunities.php';
require_once __DIR__ . '/mailpoet.php';
require_once __DIR__ . '/orchestrator.php';
require_once __DIR__ . '/claim.php';
require_once __DIR__ . '/portal.php';

if (is_admin()) {
	require_once __DIR__ . '/vendor-edit.php';
	require_once __DIR__ . '/tours.php';
	require_once __DIR__ . '/admin-page.php';
}

add_action('init', 'vms_dt_vio_maybe_upgrade_db', 9);
