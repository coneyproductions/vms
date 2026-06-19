<?php
defined('ABSPATH') || exit;

/**
 * Canonical VMS docs bootstrap.
 *
 * Keep docs system wiring here so `includes/bootstrap.php` can stay focused on
 * area/support loaders instead of special one-off feature includes.
 */
require_once dirname(__DIR__) . '/docs-registry.php';
require_once dirname(__DIR__) . '/docs-render.php';
require_once dirname(__DIR__) . '/docs-public.php';
