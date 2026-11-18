<?php
defined('ABSPATH') || exit;

/**
 * Legacy VMS Core Bootstrap shim.
 *
 * Canonical live bootstrap now runs through /includes/bootstrap.php.
 * Keep this file as a compatibility bridge only and delegate immediately
 * to the canonical bootstrap so there is only one live include path.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
