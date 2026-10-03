<?php
defined('ABSPATH') || exit;

require_once VMSEB_PATH . 'includes/helpers.php';
require_once VMSEB_PATH . 'includes/cache.php';

add_action('init', 'vmseb_maybe_invalidate_public_cache_on_version_change', 99);

if (is_admin()) {
    require_once VMSEB_PATH . 'includes/admin.php';
}

require_once VMSEB_PATH . 'includes/public.php';
require_once VMSEB_PATH . 'includes/tips.php';
