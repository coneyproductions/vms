<?php
defined('ABSPATH') || exit;

/**
 * Canonical support bootstrap.
 *
 * Keep support-only loaders grouped here so `includes/bootstrap.php` does not
 * grow a separate special-case include block for each helper subsystem.
 */
require_once dirname(__DIR__) . '/data-tools/load.php';
require_once dirname(__DIR__) . '/docs/load.php';
