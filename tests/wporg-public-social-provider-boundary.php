<?php
/** No WordPress bootstrap, database, HTTP, or normal-local write. */
define('ABSPATH', __DIR__);
function sanitize_key($key) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $key)); }
function apply_filters($hook, $value) { return $value; }
$root = dirname(__DIR__);
require $root . '/includes/social-share/providers/interface-provider.php';
foreach (array('webhook', 'meta', 'linkedin') as $name) require $root . '/includes/social-share/providers/class-provider-' . $name . '.php';
require $root . '/includes/social-share/providers/registry.php';
$providers = bvmgr_social_get_providers();
if (array_keys($providers) !== array('linkedin', 'meta', 'webhook') || bvmgr_social_get_provider('mock') !== null) throw new RuntimeException('Unexpected public provider');
$forbidden = array(
	'load.php' => 'class-provider-mock.php',
	'installer.php' => "'mock'",
	'admin.php' => "'mock'",
	'providers/registry.php' => "'mock'",
);
foreach ($forbidden as $file => $needle) {
	if (strpos(file_get_contents($root . '/includes/social-share/' . $file), $needle) !== false) throw new RuntimeException('Public mock reference: ' . $file);
}
if (strpos(file_get_contents($root . '/release-public-excludes.txt'), 'includes/social-share/providers/class-provider-mock.php') === false) throw new RuntimeException('Mock packaging exclusion missing');
echo "Public social provider registry, absent-provider fallback, seeds, UI and package boundary PASS\n";
