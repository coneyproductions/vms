<?php
defined('ABSPATH') || exit;

/**
 * Portal (front-end) loader
 *
 * Portal feature files own their shortcode registration.
 * This loader only includes the live portal surfaces.
 */

require_once __DIR__ . '/vendor-portal.php';
require_once __DIR__ . '/staff-portal.php';
