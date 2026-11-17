<?php
defined('ABSPATH') || exit;

/**
 * Legacy admin loader shim.
 *
 * Canonical admin bootstrap lives at: includes/admin/load.php
 * Keep this file only as a compatibility delegate so older include paths
 * still land on the canonical loader instead of maintaining a second admin
 * bootstrap list.
 */

require_once dirname(__DIR__) . '/includes/admin/load.php';
