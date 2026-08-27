<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/scripts/lib/wporg-prefix-inventory.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

if (!defined('ABSPATH')) {
	define('ABSPATH', $root . '/');
}
if (!defined('BVMGR_PLUGIN_PATH')) {
	define('BVMGR_PLUGIN_PATH', $root . '/');
}

final class WP_Post
{
	public int $ID;
	public string $post_content;

	public function __construct(int $id, string $content = '')
	{
		$this->ID = $id;
		$this->post_content = $content;
	}
}

final class WP_Query
{
	/** @var array<int,WP_Post> */
	public array $posts;

	public function __construct(array $args = array())
	{
		$post = $GLOBALS['bvmgr_test_query_post'] ?? null;
		$this->posts = $post instanceof WP_Post ? array($post) : array();
	}

	public function set_404(): void
	{
	}
}

function add_action(...$args): void {}
function add_filter(...$args): void {}
function add_shortcode(...$args): void {}
function get_query_var(string $name)
{
	return $GLOBALS['bvmgr_test_query_vars'][$name] ?? '';
}
function sanitize_title(string $value): string
{
	return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
}
function setup_postdata($post): void
{
	$GLOBALS['bvmgr_test_setup_postdata_id'] = $post instanceof WP_Post ? $post->ID : 0;
}
function bvmgr_vendor_profile_is_enabled(int $postId): bool
{
	return $postId > 0;
}
function plugin_dir_path(string $file): string
{
	return rtrim(str_replace('\\', '/', dirname($file)), '/') . '/';
}
function get_header(): void
{
	echo '<!-- bvmgr-test-header -->';
}
function get_footer(): void
{
	echo '<!-- bvmgr-test-footer -->';
}
function wp_reset_postdata(): void
{
	$GLOBALS['bvmgr_test_reset_count'] = (int) ($GLOBALS['bvmgr_test_reset_count'] ?? 0) + 1;
}
function esc_html__(string $text, string $domain): string
{
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function esc_html(string $text): string
{
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function esc_attr(string $text): string
{
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function esc_url(string $url): string
{
	return $url;
}
function sanitize_email(string $email): string
{
	return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
}
function get_post_meta(int $postId, string $key, bool $single = false)
{
	$GLOBALS['bvmgr_test_requested_meta_keys'][] = $key;
	return $GLOBALS['bvmgr_test_meta'][$key] ?? '';
}
function bvmgr_meta_key(string $scope, string $field): string
{
	return '_vms_' . $scope . '_' . $field;
}
function vms_vendor_profiles_render_next_show_card(int $postId): string
{
	return '<section class="next-show">Next show ' . $postId . '</section>';
}
function vms_vendor_profiles_render_social_links(int $postId): string
{
	return '<a class="social" href="https://social.test/' . $postId . '"><svg><path d="M0 0"></path></svg></a>';
}
function vms_vendor_profiles_promo_allowed_html(): array
{
	return array('a' => array('href' => true), 'span' => array('class' => true));
}
function vms_vendor_profiles_social_icon_allowed_html(): array
{
	return array('svg' => array('viewbox' => true), 'path' => array('d' => true));
}
function wp_kses_allowed_html(string $context): array
{
	return array();
}
function wp_kses(string $html, array $allowed): string
{
	$GLOBALS['bvmgr_test_last_allowed_html'] = $allowed;
	return $html;
}
function wp_oembed_get(string $url, array $args = array())
{
	$GLOBALS['bvmgr_test_oembed_request'] = array($url, $args);
	return $GLOBALS['bvmgr_test_oembed_result'] ?? false;
}
function has_post_thumbnail(int $postId): bool
{
	return (bool) ($GLOBALS['bvmgr_test_has_thumbnail'] ?? false);
}
function get_the_post_thumbnail(int $postId, string $size): string
{
	return '<img class="featured" src="https://images.test/featured-' . $postId . '.jpg" alt="">';
}
function get_the_title(int $postId): string
{
	return (string) ($GLOBALS['bvmgr_test_titles'][$postId] ?? 'Unknown vendor');
}
function apply_filters(string $hook, $value)
{
	$GLOBALS['bvmgr_test_filter_calls'][] = $hook;
	return $hook === 'the_content' ? '<p>filtered:' . $value . '</p>' : $value;
}
function __($text, $domain = '') { return $text; }
function esc_attr__($text, $domain = '') { return esc_attr((string) $text); }
function status_header(int $status): void {}
function nocache_headers(): void {}
function get_404_template(): string { return ''; }

require_once $root . '/includes/public/vendor-profiles.php';

/**
 * @param array<string,string> $meta
 */
function bvmgr_test_render_vendor_profile(?WP_Post $post, array $meta, bool $thumbnail, $oembed): string
{
	$GLOBALS['bvmgr_vendor_profile_post'] = $post;
	$GLOBALS['bvmgr_test_meta'] = $meta;
	$GLOBALS['bvmgr_test_has_thumbnail'] = $thumbnail;
	$GLOBALS['bvmgr_test_oembed_result'] = $oembed;
	$GLOBALS['bvmgr_test_requested_meta_keys'] = array();
	$GLOBALS['bvmgr_test_filter_calls'] = array();
	$GLOBALS['bvmgr_test_reset_count'] = 0;
	ob_start();
	include dirname(__DIR__) . '/includes/public/templates/vendor-profile.php';
	return (string) ob_get_clean();
}

$vendor = new WP_Post(77, 'Vendor biography');
$GLOBALS['bvmgr_test_query_vars'] = array('vms_vendor_profile' => 'The Example Vendor');
$GLOBALS['bvmgr_test_query_post'] = $vendor;
$selectedTemplate = vms_vendor_profiles_template_include('/theme/index.php');
$assert($selectedTemplate === $root . '/includes/public/templates/vendor-profile.php', 'Vendor-profile query must select the same plugin template path.');
$assert(($GLOBALS['bvmgr_vendor_profile_post'] ?? null) === $vendor, 'Template selection must preserve the bvmgr_vendor_profile_post carrier.');
$assert(($GLOBALS['post'] ?? null) === $vendor, 'Template selection must preserve the WordPress-owned global $post contract.');
$assert(($GLOBALS['bvmgr_test_setup_postdata_id'] ?? null) === 77, 'Template selection must preserve setup_postdata ordering.');

$fullMeta = array(
	'_vms_vendor_public_profile_show_email' => '',
	'_vms_vendor_public_profile_show_phone' => '1',
	'_vms_vendor_public_profile_show_website' => '1',
	'_vms_vendor_public_profile_show_location' => '1',
	'_vms_vendor_primary_email' => 'artist@example.test',
	'_vms_vendor_primary_phone' => '+1 (903) 555-1212',
	'_vms_vendor_website' => 'https://vendor.example.test',
	'_vms_vendor_city' => '',
	'_vms_vendor_state' => '',
	'_vms_vendor_location' => 'Tyler, TX',
	'_vms_vendor_featured_video_url' => 'https://video.example.test/watch/77',
	'_vms_vendor_gallery_image_1' => 'https://images.example.test/one.jpg',
	'_vms_vendor_gallery_image_2' => '',
	'_vms_vendor_gallery_image_3' => 'https://images.example.test/three.jpg',
	'_vms_vendor_gallery_image_4' => '',
	'_vms_vendor_gallery_image_5' => '',
);
$GLOBALS['bvmgr_test_titles'][77] = 'The Example Vendor';
$fullOutput = bvmgr_test_render_vendor_profile($vendor, $fullMeta, true, false);

foreach (array(
	'The Example Vendor' => 'vendor identity/title',
	'Tyler, TX' => 'legacy location fallback',
	'tel:+19035551212' => 'generated phone link',
	'mailto:artist@example.test' => 'generated email link',
	'https://vendor.example.test' => 'generated website link',
	'Next show 77' => 'next-show presentation',
	'https://social.test/77' => 'social presentation',
	'filtered:Vendor biography' => 'core content-filter output',
	'https://video.example.test/watch/77' => 'video fallback link',
	'https://images.example.test/one.jpg' => 'first gallery link',
	'https://images.example.test/three.jpg' => 'later gallery branch',
) as $needle => $label) {
	$assert(str_contains($fullOutput, $needle), "Vendor-profile render must preserve {$label}.");
}
$assert(strpos($fullOutput, '<!-- bvmgr-test-header -->') < strpos($fullOutput, '<main'), 'Header must render before the vendor profile.');
$assert(strpos($fullOutput, '</main>') < strpos($fullOutput, '<!-- bvmgr-test-footer -->'), 'Footer must render after the vendor profile.');
$assert(($GLOBALS['bvmgr_test_filter_calls'] ?? array()) === array('the_content'), 'Stored vendor content must pass through the_content exactly once.');
$assert(isset($GLOBALS['bvmgr_test_last_allowed_html']['svg'], $GLOBALS['bvmgr_test_last_allowed_html']['path']), 'Social SVG/path allowances must remain merged into profile HTML policy.');
$assert(($GLOBALS['bvmgr_test_reset_count'] ?? 0) === 1, 'Successful template render must reset postdata once.');
$assert(($GLOBALS['bvmgr_test_oembed_request'] ?? null) === array('https://video.example.test/watch/77', array('width' => 960)), 'Featured-video request and dimensions must remain unchanged.');

$privateMeta = $fullMeta;
$privateMeta['_vms_vendor_public_profile_show_email'] = '0';
$privateMeta['_vms_vendor_public_profile_show_phone'] = '0';
$privateMeta['_vms_vendor_public_profile_show_website'] = '0';
$privateMeta['_vms_vendor_public_profile_show_location'] = '0';
$privateMeta['_vms_vendor_featured_video_url'] = '';
$privateMeta['_vms_vendor_gallery_image_1'] = '';
$privateMeta['_vms_vendor_gallery_image_3'] = '';
$privateVendor = new WP_Post(78, '');
$GLOBALS['bvmgr_test_titles'][78] = 'Private Contact Vendor';
$privateOutput = bvmgr_test_render_vendor_profile($privateVendor, $privateMeta, false, false);
$assert(!str_contains($privateOutput, 'vms-vp-location'), 'Location visibility opt-out must remain effective.');
$assert(!str_contains($privateOutput, 'mailto:'), 'Email visibility opt-out must remain effective.');
$assert(!str_contains($privateOutput, 'tel:'), 'Phone visibility opt-out must remain effective.');
$assert(!str_contains($privateOutput, '<h2 class="vms-vp-h2">Contact</h2>'), 'Contact card must remain absent when every contact field is hidden.');
$assert(!str_contains($privateOutput, '<h2 class="vms-vp-h2">About</h2>'), 'About card must remain absent for empty content.');
$assert(!str_contains($privateOutput, '<h2 class="vms-vp-h2">Featured video</h2>'), 'Video card must remain absent without a URL.');
$assert(!str_contains($privateOutput, '<h2 class="vms-vp-h2">Photos</h2>'), 'Gallery card must remain absent without images.');
$assert(str_contains($privateOutput, 'vms-vp-avatar--placeholder'), 'Thumbnail fallback branch must remain unchanged.');

$missingOutput = bvmgr_test_render_vendor_profile(null, array(), false, false);
$assert(str_contains($missingOutput, 'Vendor not found.'), 'Missing vendor branch must retain its public message.');
$assert(!str_contains($missingOutput, 'vms-vp-hero'), 'Missing vendor branch must return before normal profile rendering.');
$assert(($GLOBALS['bvmgr_test_reset_count'] ?? 0) === 1, 'Missing vendor branch must reset postdata once.');

$templateSource = (string) file_get_contents($root . '/includes/public/templates/vendor-profile.php');
$topLevelAssignments = BVMGR_WPORG_Prefix_Inventory::topLevelVariableAssignments($root);
$topLevelAssignmentIndex = array();
foreach ($topLevelAssignments as $assignment) {
	$topLevelAssignmentIndex[$assignment['file'] . '|' . $assignment['variable']] = true;
}
foreach (BVMGR_WPORG_Prefix_Inventory::b2_5GlobalMigrations() as $migration) {
	$legacyName = (string) $migration['legacy'];
	$canonicalName = (string) $migration['canonical'];
	$file = (string) $migration['file'];
	$assert(!isset($topLevelAssignmentIndex[$file . '|' . $legacyName]), "Legacy B2.5 global must be absent: \${$legacyName}.");
	$assert(isset($topLevelAssignmentIndex[$file . '|' . $canonicalName]), "Canonical B2.5 global must exist: \${$canonicalName}.");
}
$assert(str_contains($templateSource, '$bvmgr_vendor_profile_social_icon_tag'), 'Semantic guard must explicitly retain the canonical replacement for scanner-missed $tag.');

$manifest = json_decode((string) file_get_contents($root . '/docs/wporg-prefix-migration-manifest.json'), true);
$b2_5Map = (array) ($manifest['completed_batches']['B2_5']['symbol_map'] ?? array());
$sitesByLegacy = array();
foreach ($b2_5Map as $entry) {
	$sitesByLegacy[(string) ($entry['legacy_identifier'] ?? '')] = count((array) ($entry['declaration_sites'] ?? array()));
}
$assert(($sitesByLegacy['loader:tax_file'] ?? null) === 3, 'Vendor taxonomy/profile loader must retain exactly three coupled token sites.');
$assert(($sitesByLegacy['loader:pt'] ?? null) === 6, 'Vendor application post-type loops must retain exactly six coupled token sites.');
$assert(($sitesByLegacy['loader:hook'] ?? null) === 2, 'Social cron hook registration must retain exactly two coupled token sites.');

$vendorPortalSource = (string) file_get_contents($root . '/includes/portal/vendor-portal.php');
$assert(str_contains($vendorPortalSource, "\$bvmgr_vendor_tax_profile_file = plugin_dir_path(__FILE__) . 'vendor-tax-profile.php';"), 'Vendor portal must retain the same taxonomy/profile loader path expression.');
$assert(str_contains($vendorPortalSource, 'if (file_exists($bvmgr_vendor_tax_profile_file))'), 'Vendor portal must retain the loader existence gate.');
$assert(str_contains($vendorPortalSource, 'require_once $bvmgr_vendor_tax_profile_file;'), 'Vendor portal must require the same resolved taxonomy/profile module.');
$assert(is_file($root . '/includes/portal/vendor-tax-profile.php'), 'Vendor taxonomy/profile loader target must remain present.');

$vendorApplicationsSource = (string) file_get_contents($root . '/includes/vendor-applications.php');
$assert(substr_count($vendorApplicationsSource, 'foreach (vms_vendor_app_cpt_slugs() as $bvmgr_vendor_application_post_type)') === 3, 'All three top-level vendor application post-type registration loops must use the canonical variable.');
$assert(str_contains($vendorApplicationsSource, "'manage_' . \$bvmgr_vendor_application_post_type . '_posts_columns'"), 'Vendor application columns hook value must remain unchanged.');
$assert(str_contains($vendorApplicationsSource, "'manage_' . \$bvmgr_vendor_application_post_type . '_posts_custom_column'"), 'Vendor application custom-column hook value must remain unchanged.');
$assert(str_contains($vendorApplicationsSource, "'views_edit-' . \$bvmgr_vendor_application_post_type"), 'Vendor application views hook value must remain unchanged.');

$socialQueueSource = (string) file_get_contents($root . '/includes/social-share/queue-runner.php');
$assert(str_contains($socialQueueSource, "\$bvmgr_social_cron_hook = defined('BVMGR_SOCIAL_CRON_HOOK') ? (string) BVMGR_SOCIAL_CRON_HOOK : 'vms_social_process_queue';"), 'Social queue runner must retain the canonical constant and legacy physical cron-hook fallback value.');
$assert(str_contains($socialQueueSource, 'add_action($bvmgr_social_cron_hook, function (): void {'), 'Social queue runner must register the unchanged queue callback under the resolved hook.');
$assert(str_contains($socialQueueSource, 'vms_social_process_queue(20)'), 'Social queue runner batch size and processing call must remain unchanged.');

if ($failures !== array()) {
	fwrite(STDERR, "B2.5 runtime correction failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B2.5 vendor-template and loader behavior tests passed.\n";
