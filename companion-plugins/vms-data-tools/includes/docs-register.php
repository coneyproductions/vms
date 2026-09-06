<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register VMS Data Tools docs with VMS Core.
 */

add_action('vms_register_docs_sources', 'vms_dt_register_docs_sources');

function vms_dt_register_docs_sources($register)
{
    if (!is_callable($register)) {
        return;
    }

    $register([
        'module' => 'vms-data-tools',
        'label'  => 'VMS Data Tools',
        'path'   => untrailingslashit(dirname(__DIR__)) . '/docs',
        'public_base' => 'data-tools',
    ]);
}
