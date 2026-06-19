<?php
defined('ABSPATH') || exit;

/**
 * Legacy root shim.
 *
 * Canonical plugin entry point is vendor-management-system.php.
 * Keep this file as a compatibility bridge only and delegate immediately
 * to the canonical root so plugin constants come from one place.
 */

require_once __DIR__ . '/vendor-management-system.php';
