<?php
defined('ABSPATH') || exit;

/**
 * Legacy main-filename bridge.
 *
 * This file intentionally has no WordPress plugin header, so the package exposes
 * one plugin entry only. Existing same-directory installations whose active
 * basename still points here load the canonical bootstrap and migrate that
 * active basename to backstage-venue-manager.php.
 */

if (!defined('VMS_LEGACY_PLUGIN_FILE')) {
	define('VMS_LEGACY_PLUGIN_FILE', __FILE__);
}

$vms_canonical_plugin_file = __DIR__ . '/backstage-venue-manager.php';
require_once $vms_canonical_plugin_file;

vms_register_legacy_plugin_basename_compatibility(__FILE__, $vms_canonical_plugin_file);
register_activation_hook(__FILE__, 'vms_activate_plugin');
register_deactivation_hook(__FILE__, 'vms_deactivate_plugin');

unset($vms_canonical_plugin_file);
