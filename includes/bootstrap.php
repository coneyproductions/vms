<?php
defined('ABSPATH') || exit;

/**
 * Loader rules (locked):
 * - Only this file and area loaders include other files.
 * - Feature files never include other feature files.
 * - New files are added to the appropriate area loader.
 */

/**
 * Registries first
 */
require_once __DIR__ . '/core/registry/constants.php';
require_once __DIR__ . '/core/registry/admin-slugs.php';
require_once __DIR__ . '/core/registry/admin-menu.php';
require_once __DIR__ . '/core/registry/statuses.php';
require_once __DIR__ . '/core/registry/csv-contracts.php';
require_once __DIR__ . '/core/registry/meta-keys.php';
require_once __DIR__ . '/core/registry/vendor-schema.php';
require_once __DIR__ . '/core/registry/normalizers.php';
require_once __DIR__ . '/core/registry/class-vms-vendor-schema-registry.php';
require_once __DIR__ . '/core/registry/tours.php';
require_once __DIR__ . '/tours/tours.php';


/**
 * Area loaders (deterministic order)
 */
require_once __DIR__ . '/core/load.php';
require_once __DIR__ . '/integrations/load.php';
require_once __DIR__ . '/rest/load.php';
require_once __DIR__ . '/public/load.php';
require_once __DIR__ . '/portal/load.php';
require_once __DIR__ . '/social-share/load.php';

// Intentionally not bootstrapped from core in this build:
// - includes/safety/* (source-only prototype, excluded from the public release)
// - Express Bar (moved to a standalone module)

if (is_admin()) {
	require_once __DIR__ . '/admin/load.php';
}

// Support loaders
require_once __DIR__ . '/support/load.php';

// Legacy optional files that were previously loaded through includes/core/bootstrap.php.
// Keep behavior here in the canonical bootstrap instead of loading a second core shim.
$bvmgr_optional_bootstrap_files = [
	__DIR__ . '/taxonomies/vendor-type.php',
	__DIR__ . '/taxonomies/vendor-category.php',
	__DIR__ . '/meta/event-plan.php',
	__DIR__ . '/meta/vendor.php',
];
foreach ($bvmgr_optional_bootstrap_files as $bvmgr_optional_bootstrap_file) {
	if (is_string($bvmgr_optional_bootstrap_file) && $bvmgr_optional_bootstrap_file !== '' && file_exists($bvmgr_optional_bootstrap_file)) {
		require_once $bvmgr_optional_bootstrap_file;
	}
}
unset($bvmgr_optional_bootstrap_file, $bvmgr_optional_bootstrap_files);

/**
 * Modules
 */
require_once __DIR__ . '/modules/load.php';
if (function_exists('vms_load_modules')) {
	vms_load_modules();
}

do_action('vms_loaded');
